<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Namespace\NamespaceScope;
use Kalle\Xml\Node\CDataNode;
use Kalle\Xml\Node\CommentNode;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Node\ProcessingInstructionNode;
use Kalle\Xml\Node\TextNode;
use Stringable;

use function array_values;
use function count;
use function sprintf;
use function str_repeat;

final class StreamingXmlWriter
{
    /**
     * @var list<OpenElementFrame>
     */
    private array $stack = [];

    private bool $declarationWritten = false;

    private bool $rootWritten = false;

    private bool $finished = false;

    private readonly XmlTreeSerializer $treeSerializer;

    private function __construct(
        private readonly WriterConfig $config,
        private readonly XmlOutput $output,
        private readonly NamespaceDeclarationResolver $namespaceResolver = new NamespaceDeclarationResolver(),
    ) {
        $this->treeSerializer = new XmlTreeSerializer(
            $this->config,
            $this->output,
            $this->namespaceResolver,
        );
    }

    public static function forFile(string $path, ?WriterConfig $config = null): self
    {
        return new self($config ?? WriterConfig::compact(), StreamXmlOutput::forFile($path));
    }

    public static function forStream(mixed $stream, ?WriterConfig $config = null, bool $closeOnFinish = false): self
    {
        return new self(
            $config ?? WriterConfig::compact(),
            StreamXmlOutput::forStream($stream, $closeOnFinish),
        );
    }

    public function startDocument(?XmlDeclaration $declaration = null): self
    {
        $this->ensureWritable();

        if ($this->declarationWritten) {
            throw new SerializationException('The XML declaration has already been written.');
        }

        if ($this->rootWritten || $this->stack !== []) {
            throw new SerializationException(
                'The XML declaration must be written before the document root element.',
            );
        }

        $this->output->write($this->treeSerializer->serializeDeclaration($declaration ?? new XmlDeclaration()));

        if ($this->config->prettyPrint()) {
            $this->output->write($this->config->newline());
        }

        $this->declarationWritten = true;

        return $this;
    }

    public function startElement(string|QualifiedName $name): self
    {
        $this->ensureWritable();

        $qualifiedName = QualifiedName::forElement($name);

        if ($this->stack === []) {
            if ($this->rootWritten) {
                throw new SerializationException(
                    'The streaming XML writer already wrote the document root element.',
                );
            }

            $this->rootWritten = true;
            $this->stack[] = new OpenElementFrame(
                $qualifiedName,
                NamespaceScope::empty(),
                0,
                false,
                $this->config->prettyPrint(),
            );

            return $this;
        }

        $childContext = $this->beginStructuralChild('start a child element');

        $this->stack[] = new OpenElementFrame(
            $qualifiedName,
            $childContext['context'],
            $childContext['depth'],
            $childContext['prependNewline'],
            $childContext['prettyPrint'],
        );

        return $this;
    }

    /**
     * @param string|QualifiedName $name
     * @param string|int|float|bool|Stringable|null $value
     * @return StreamingXmlWriter
     */
    public function writeAttribute(string|QualifiedName $name, string|int|float|bool|Stringable|null $value): self
    {
        $this->ensureWritable();

        $qualifiedName = QualifiedName::forAttribute($name);
        $frame = $this->requireOpenElement(sprintf(
            'write attribute "%s"',
            $qualifiedName->lexicalName(),
        ));

        if ($frame->startTagFlushed()) {
            throw new SerializationException(sprintf(
                'Cannot add attribute "%s" after writing content for element "%s".',
                $qualifiedName->lexicalName(),
                $frame->lexicalName(),
            ));
        }

        if ($value === null) {
            $frame->removeAttribute($qualifiedName);

            return $this;
        }

        $frame->addAttribute(new Attribute($qualifiedName, $value));

        return $this;
    }

    public function declareNamespace(string $prefix, string $uri): self
    {
        return $this->declareNamespaceDeclaration(new NamespaceDeclaration($prefix, $uri));
    }

    public function declareDefaultNamespace(string $uri): self
    {
        return $this->declareNamespaceDeclaration(new NamespaceDeclaration(null, $uri));
    }

    public function writeText(string $content): self
    {
        return $this->writeTextNode(new TextNode($content));
    }

    public function writeCdata(string $content): self
    {
        return $this->writeCDataNode(new CDataNode($content));
    }

    public function writeComment(string $content): self
    {
        return $this->writeCommentNode(new CommentNode($content));
    }

    public function writeProcessingInstruction(string $target, string $data = ''): self
    {
        return $this->writeProcessingInstructionNode(new ProcessingInstructionNode($target, $data));
    }

    public function writeElement(Element $element): self
    {
        $this->ensureWritable();

        if ($this->stack === []) {
            if ($this->rootWritten) {
                throw new SerializationException(
                    'The streaming XML writer already wrote the document root element.',
                );
            }

            $this->rootWritten = true;
            $this->treeSerializer->serializeElement(
                $element,
                0,
                NamespaceScope::empty(),
                $this->config->prettyPrint(),
            );

            return $this;
        }

        $childContext = $this->beginStructuralChild('write an element subtree');

        if ($childContext['prependNewline']) {
            $this->output->write($this->config->newline());
        }

        $this->treeSerializer->serializeElement(
            $element,
            $childContext['depth'],
            $childContext['context'],
            $childContext['prettyPrint'],
        );

        return $this;
    }

    public function endElement(): self
    {
        $this->ensureWritable();

        $frame = $this->requireOpenElement('end the current element');

        if (!$frame->startTagFlushed()) {
            $this->writeEmptyElementFrame($frame);
            array_pop($this->stack);

            return $this;
        }

        if ($frame->prettyPrintEnabled() && $frame->contentMode() === OpenElementFrame::CONTENT_STRUCTURAL) {
            $this->output->write($this->config->newline());
            $this->output->write($this->indent($frame->depth()));
        }

        $this->output->write(sprintf('</%s>', $frame->lexicalName()));
        array_pop($this->stack);

        return $this;
    }

    public function finish(): void
    {
        if ($this->finished) {
            return;
        }

        if ($this->stack !== []) {
            throw new SerializationException(sprintf(
                'Cannot finish XML output: element "%s" is still open.',
                $this->stack[count($this->stack) - 1]->lexicalName(),
            ));
        }

        if (!$this->rootWritten) {
            throw new SerializationException(
                'Cannot finish XML output without writing a document root element.',
            );
        }

        $this->finished = true;
        $this->output->finish();
    }

    private function writeTextNode(TextNode $node): self
    {
        $this->ensureWritable();
        $this->beginTextLikeChild('write text');
        $this->output->write(XmlEscaper::escapeText($node->content()));

        return $this;
    }

    private function writeCDataNode(CDataNode $node): self
    {
        $this->ensureWritable();
        $this->beginTextLikeChild('write CDATA');
        $this->output->write($this->treeSerializer->serializeCData($node));

        return $this;
    }

    private function writeCommentNode(CommentNode $node): self
    {
        $this->ensureWritable();

        $childContext = $this->beginStructuralChild('write a comment');

        if ($childContext['prependNewline']) {
            $this->output->write($this->config->newline());
        }

        $this->output->write(
            $this->treeSerializer->serializeComment($node, $childContext['depth'], $childContext['prettyPrint']),
        );

        return $this;
    }

    private function writeProcessingInstructionNode(ProcessingInstructionNode $node): self
    {
        $this->ensureWritable();

        $childContext = $this->beginStructuralChild('write a processing instruction');

        if ($childContext['prependNewline']) {
            $this->output->write($this->config->newline());
        }

        $this->output->write(
            $this->treeSerializer->serializeProcessingInstruction($node, $childContext['depth'], $childContext['prettyPrint']),
        );

        return $this;
    }

    private function declareNamespaceDeclaration(NamespaceDeclaration $declaration): self
    {
        $this->ensureWritable();

        $frame = $this->requireOpenElement(sprintf(
            'declare %s',
            $declaration->isDefault() ? 'the default namespace' : sprintf('prefix "%s"', $declaration->prefix()),
        ));

        if ($frame->startTagFlushed()) {
            throw new SerializationException(sprintf(
                'Cannot declare %s after writing content for element "%s".',
                $declaration->isDefault() ? 'the default namespace' : sprintf('prefix "%s"', $declaration->prefix()),
                $frame->lexicalName(),
            ));
        }

        $frame->addNamespaceDeclaration($declaration);

        return $this;
    }

    /**
     * @return array{context: NamespaceScope, depth: int, prependNewline: bool, prettyPrint: bool}
     */
    private function beginStructuralChild(string $operation): array
    {
        $frame = $this->requireOpenElement($operation);
        $this->flushStartTag($frame);

        $childDepth = $frame->depth() + 1;

        if ($frame->contentMode() === OpenElementFrame::CONTENT_TEXT || !$frame->prettyPrintEnabled()) {
            return [
                'context' => $frame->inScopeContext(),
                'depth' => $childDepth,
                'prependNewline' => false,
                'prettyPrint' => false,
            ];
        }

        $frame->markStructuralContent();

        return [
            'context' => $frame->inScopeContext(),
            'depth' => $childDepth,
            'prependNewline' => true,
            'prettyPrint' => true,
        ];
    }

    private function beginTextLikeChild(string $operation): void
    {
        $frame = $this->requireOpenElement($operation);

        if ($frame->prettyPrintEnabled() && $frame->contentMode() === OpenElementFrame::CONTENT_STRUCTURAL) {
            throw new SerializationException(sprintf(
                'Pretty-printed streaming output cannot add text-like content after structural children in element "%s". Use compact mode or write that subtree as a prebuilt element.',
                $frame->lexicalName(),
            ));
        }

        $this->flushStartTag($frame);
        $frame->markTextLikeContent();
    }

    private function flushStartTag(OpenElementFrame $frame): void
    {
        if ($frame->startTagFlushed()) {
            return;
        }

        $namespaceDeclarations = $this->namespaceResolver->resolve(
            $frame->qualifiedName(),
            $frame->attributes(),
            $frame->namespaceDeclarations(),
            $frame->parentContext(),
        );
        $inScopeContext = $frame->parentContext()->withDeclarations($namespaceDeclarations);

        if ($frame->prettyPrintEnabled() && $frame->prependNewline()) {
            $this->output->write($this->config->newline());
        }

        if ($frame->prettyPrintEnabled()) {
            $this->output->write($this->indent($frame->depth()));
        }

        $this->output->write(sprintf(
            '<%s%s>',
            $frame->lexicalName(),
            $this->treeSerializer->serializeAttributes(array_values($frame->attributes()), $namespaceDeclarations),
        ));

        $frame->markStartTagFlushed($inScopeContext);
    }

    private function writeEmptyElementFrame(OpenElementFrame $frame): void
    {
        $namespaceDeclarations = $this->namespaceResolver->resolve(
            $frame->qualifiedName(),
            $frame->attributes(),
            $frame->namespaceDeclarations(),
            $frame->parentContext(),
        );

        if ($frame->prettyPrintEnabled() && $frame->prependNewline()) {
            $this->output->write($this->config->newline());
        }

        $attributes = $this->treeSerializer->serializeAttributes(array_values($frame->attributes()), $namespaceDeclarations);
        $prefix = $frame->prettyPrintEnabled() ? $this->indent($frame->depth()) : '';

        if ($this->config->selfCloseEmptyElements()) {
            $this->output->write(sprintf('%s<%s%s/>', $prefix, $frame->lexicalName(), $attributes));

            return;
        }

        $this->output->write(sprintf(
            '%s<%s%s></%s>',
            $prefix,
            $frame->lexicalName(),
            $attributes,
            $frame->lexicalName(),
        ));
    }

    private function requireOpenElement(string $operation): OpenElementFrame
    {
        if ($this->stack === []) {
            throw new SerializationException(sprintf(
                'Cannot %s when no element is open.',
                $operation,
            ));
        }

        return $this->stack[count($this->stack) - 1];
    }

    private function indent(int $depth): string
    {
        return str_repeat($this->config->indent(), $depth);
    }

    private function ensureWritable(): void
    {
        if ($this->finished) {
            throw new SerializationException('Cannot write to a finished streaming XML writer.');
        }
    }
}
