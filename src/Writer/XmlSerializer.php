<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\FileWriteException;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Namespace\NamespaceScope;
use Kalle\Xml\Node\CDataNode;
use Kalle\Xml\Node\CommentNode;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Node\Node;
use Kalle\Xml\Node\ProcessingInstructionNode;
use Kalle\Xml\Node\TextNode;

use function count;
use function file_put_contents;
use function get_debug_type;
use function implode;
use function ksort;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_repeat;
use function str_replace;
use function strlen;

final class XmlSerializer
{
    public function serialize(XmlDocument $document, ?WriterConfig $config = null): string
    {
        $config ??= WriterConfig::compact();
        $parts = [];

        if ($config->emitDeclaration() && $document->declaration() !== null) {
            $parts[] = $this->serializeDeclaration($document->declaration());
        }

        $parts[] = $this->serializeElement($document->root(), $config, 0, NamespaceScope::empty());

        if ($config->prettyPrint() && count($parts) > 1) {
            return implode($config->newline(), $parts);
        }

        return implode('', $parts);
    }

    public function saveToFile(XmlDocument $document, string $path, ?WriterConfig $config = null): void
    {
        if ($path === '') {
            throw new FileWriteException('Cannot write XML to an empty path.');
        }

        $xml = $this->serialize($document, $config);
        $expectedBytes = strlen($xml);
        $writeError = null;

        set_error_handler(static function (int $severity, string $message) use (&$writeError): bool {
            $writeError = $message;

            return true;
        });

        try {
            $bytesWritten = file_put_contents($path, $xml);
        } finally {
            restore_error_handler();
        }

        if ($bytesWritten === false) {
            if ($writeError !== null && preg_match('/Only (\d+) of (\d+) bytes written/', $writeError, $matches) === 1) {
                throw new FileWriteException(sprintf(
                    'Incomplete XML write to "%s": wrote %d of %d bytes. PHP error: %s',
                    $path,
                    (int) $matches[1],
                    $expectedBytes,
                    $writeError,
                ));
            }

            $message = sprintf('Failed to write XML to "%s".', $path);

            if ($writeError !== null) {
                $message = sprintf('Failed to write XML to "%s": %s', $path, $writeError);
            }

            throw new FileWriteException($message);
        }

        if ($bytesWritten !== $expectedBytes) {
            $message = sprintf(
                'Incomplete XML write to "%s": wrote %d of %d bytes.',
                $path,
                $bytesWritten,
                $expectedBytes,
            );

            if ($writeError !== null) {
                $message .= sprintf(' PHP error: %s', $writeError);
            }

            throw new FileWriteException($message);
        }
    }

    private function serializeDeclaration(XmlDeclaration $declaration): string
    {
        $parts = [sprintf('version="%s"', $declaration->version())];

        if ($declaration->encoding() !== null) {
            $parts[] = sprintf('encoding="%s"', XmlEscaper::escapeAttributeValue($declaration->encoding()));
        }

        if ($declaration->standalone() !== null) {
            $parts[] = sprintf('standalone="%s"', $declaration->standalone() ? 'yes' : 'no');
        }

        return '<?xml ' . implode(' ', $parts) . '?>';
    }

    private function serializeElement(
        Element $element,
        WriterConfig $config,
        int $depth,
        NamespaceScope $context,
    ): string {
        $children = $element->children();
        $namespaceDeclarations = $this->resolveNamespaceDeclarations($element, $context);
        $inScopeContext = $context->withDeclarations($namespaceDeclarations);
        $attributes = $this->serializeAttributes($element, $namespaceDeclarations);
        $indent = $config->prettyPrint() ? str_repeat($config->indent(), $depth) : '';
        $elementName = $element->name();

        if ($children === []) {
            if ($config->selfCloseEmptyElements()) {
                return sprintf('%s<%s%s/>', $indent, $elementName, $attributes);
            }

            return sprintf('%s<%s%s></%s>', $indent, $elementName, $attributes, $elementName);
        }

        if ($config->prettyPrint() && $this->containsOnlyPrettyPrintableNodes($children)) {
            $serializedChildren = $this->serializePrettyPrintedChildNodes(
                $children,
                $config,
                $depth + 1,
                $inScopeContext,
            );

            return sprintf(
                '%s<%s%s>%s%s%s%s</%s>',
                $indent,
                $elementName,
                $attributes,
                $config->newline(),
                implode($config->newline(), $serializedChildren),
                $config->newline(),
                $indent,
                $elementName,
            );
        }

        $inlineConfig = $config->withPrettyPrint(false);
        $content = '';

        foreach ($children as $child) {
            $content .= $this->serializeInlineNode($child, $inlineConfig, $inScopeContext);
        }

        return sprintf('%s<%s%s>%s</%s>', $indent, $elementName, $attributes, $content, $elementName);
    }

    /**
     * @param list<Node> $children
     *
     * @return list<string>
     */
    private function serializePrettyPrintedChildNodes(
        array $children,
        WriterConfig $config,
        int $depth,
        NamespaceScope $context,
    ): array {
        $serializedChildren = [];

        foreach ($children as $child) {
            $serializedChildren[] = $this->serializeNode($child, $config, $depth, $context);
        }

        return $serializedChildren;
    }

    private function serializeInlineNode(
        Node $node,
        WriterConfig $config,
        NamespaceScope $context,
    ): string {
        return $this->serializeNode($node, $config, 0, $context);
    }

    /**
     * @param list<NamespaceDeclaration> $namespaceDeclarations
     */
    private function serializeAttributes(Element $element, array $namespaceDeclarations): string
    {
        $serialized = '';

        foreach ($namespaceDeclarations as $declaration) {
            $serialized .= sprintf(
                ' %s="%s"',
                $declaration->attributeName(),
                XmlEscaper::escapeAttributeValue($declaration->uri()),
            );
        }

        foreach ($element->attributes() as $attribute) {
            $serialized .= sprintf(
                ' %s="%s"',
                $attribute->name(),
                XmlEscaper::escapeAttributeValue($attribute->value()),
            );
        }

        return $serialized;
    }

    /**
     * @return list<NamespaceDeclaration>
     */
    private function resolveNamespaceDeclarations(Element $element, NamespaceScope $context): array
    {
        $declarations = [];

        foreach ($element->namespaceDeclarations() as $declaration) {
            $declarations[$declaration->prefixKey()] = $declaration;
        }

        $this->ensureElementNamespaceIsDeclared($element, $declarations, $context);

        foreach ($element->attributes() as $attribute) {
            $this->ensureAttributeNamespaceIsDeclared($attribute, $declarations, $context, $element);
        }

        return $this->sortNamespaceDeclarations($declarations);
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensureElementNamespaceIsDeclared(
        Element $element,
        array &$declarations,
        NamespaceScope $context,
    ): void {
        $qualifiedName = $element->qualifiedName();
        $namespaceUri = $qualifiedName->namespaceUri();
        $prefix = $qualifiedName->prefix();

        if ($namespaceUri === null) {
            if ($context->defaultNamespaceUri() !== null && !isset($declarations[''])) {
                $declarations[''] = new NamespaceDeclaration(null, '');
            }

            return;
        }

        if ($prefix === null) {
            $this->ensureDefaultNamespaceDeclaration(
                $declarations,
                $context,
                $namespaceUri,
                sprintf('element "%s"', $element->name()),
            );

            return;
        }

        $this->ensurePrefixedNamespaceDeclaration(
            $declarations,
            $context,
            $prefix,
            $namespaceUri,
            sprintf('element "%s"', $element->name()),
        );
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensureAttributeNamespaceIsDeclared(
        Attribute $attribute,
        array &$declarations,
        NamespaceScope $context,
        Element $element,
    ): void {
        $namespaceUri = $attribute->namespaceUri();
        $prefix = $attribute->prefix();

        if ($namespaceUri === null || $prefix === null) {
            return;
        }

        $this->ensurePrefixedNamespaceDeclaration(
            $declarations,
            $context,
            $prefix,
            $namespaceUri,
            sprintf('attribute "%s" on element "%s"', $attribute->name(), $element->name()),
        );
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensurePrefixedNamespaceDeclaration(
        array &$declarations,
        NamespaceScope $context,
        string $prefix,
        string $namespaceUri,
        string $contextLabel,
    ): void {
        $declaration = $declarations[$prefix] ?? null;

        if ($declaration !== null) {
            if ($declaration->uri() === $namespaceUri) {
                return;
            }

            throw new SerializationException(sprintf(
                'Prefix "%s" is already bound to "%s" and cannot also be "%s" while serializing %s.',
                $prefix,
                $declaration->uri(),
                $namespaceUri,
                $contextLabel,
            ));
        }

        if ($context->namespaceUriForPrefix($prefix) === $namespaceUri) {
            return;
        }

        $declarations[$prefix] = new NamespaceDeclaration($prefix, $namespaceUri);
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensureDefaultNamespaceDeclaration(
        array &$declarations,
        NamespaceScope $context,
        string $namespaceUri,
        string $contextLabel,
    ): void {
        $declaration = $declarations[''] ?? null;

        if ($declaration !== null) {
            if ($declaration->uri() === $namespaceUri) {
                return;
            }

            throw new SerializationException(sprintf(
                'Default namespace is already "%s" and cannot also be "%s" while serializing %s.',
                $declaration->uri(),
                $namespaceUri,
                $contextLabel,
            ));
        }

        if ($context->defaultNamespaceUri() === $namespaceUri) {
            return;
        }

        $declarations[''] = new NamespaceDeclaration(null, $namespaceUri);
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     *
     * @return list<NamespaceDeclaration>
     */
    private function sortNamespaceDeclarations(array $declarations): array
    {
        $defaultDeclaration = $declarations[''] ?? null;
        unset($declarations['']);

        ksort($declarations);

        $sorted = [];

        if ($defaultDeclaration !== null) {
            $sorted[] = $defaultDeclaration;
        }

        foreach ($declarations as $declaration) {
            $sorted[] = $declaration;
        }

        return $sorted;
    }

    /**
     * @param list<Node> $children
     */
    private function containsOnlyPrettyPrintableNodes(array $children): bool
    {
        foreach ($children as $child) {
            if (!$this->isPrettyPrintableNode($child)) {
                return false;
            }
        }

        return true;
    }

    private function serializeNode(
        Node $node,
        WriterConfig $config,
        int $depth,
        NamespaceScope $context,
    ): string {
        if ($node instanceof Element) {
            return $this->serializeElement($node, $config, $depth, $context);
        }

        if ($node instanceof TextNode) {
            return XmlEscaper::escapeText($node->content());
        }

        if ($node instanceof CDataNode) {
            return $this->serializeCData($node);
        }

        if ($node instanceof CommentNode) {
            return $this->serializeComment($node, $config, $depth);
        }

        if ($node instanceof ProcessingInstructionNode) {
            return $this->serializeProcessingInstruction($node, $config, $depth);
        }

        throw new SerializationException(sprintf(
            'Cannot serialize node of type %s.',
            get_debug_type($node),
        ));
    }

    private function serializeCData(CDataNode $node): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $node->content()) . ']]>';
    }

    private function serializeComment(CommentNode $node, WriterConfig $config, int $depth): string
    {
        $indent = $config->prettyPrint() ? str_repeat($config->indent(), $depth) : '';

        return sprintf('%s<!--%s-->', $indent, $node->content());
    }

    private function serializeProcessingInstruction(
        ProcessingInstructionNode $node,
        WriterConfig $config,
        int $depth,
    ): string {
        $indent = $config->prettyPrint() ? str_repeat($config->indent(), $depth) : '';

        if ($node->data() === '') {
            return sprintf('%s<?%s?>', $indent, $node->target());
        }

        return sprintf('%s<?%s %s?>', $indent, $node->target(), $node->data());
    }

    private function isPrettyPrintableNode(Node $node): bool
    {
        return $node instanceof Element
            || $node instanceof CommentNode
            || $node instanceof ProcessingInstructionNode;
    }
}
