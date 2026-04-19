<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Namespace\NamespaceScope;
use Kalle\Xml\Node\CDataNode;
use Kalle\Xml\Node\CommentNode;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Node\Node;
use Kalle\Xml\Node\ProcessingInstructionNode;
use Kalle\Xml\Node\TextNode;

use function get_debug_type;
use function implode;
use function sprintf;
use function str_repeat;
use function str_replace;

/**
 * @internal
 */
final readonly class XmlTreeSerializer
{
    public function __construct(
        private WriterConfig $config,
        private XmlOutput $output,
        private NamespaceDeclarationResolver $namespaceResolver = new NamespaceDeclarationResolver(),
    ) {}

    public function serializeDocument(XmlDocument $document): void
    {
        if ($this->config->emitDeclaration() && $document->declaration() !== null) {
            $this->output->write($this->serializeDeclaration($document->declaration()));

            if ($this->config->prettyPrint()) {
                $this->output->write($this->config->newline());
            }
        }

        $this->serializeElement(
            $document->root(),
            0,
            NamespaceScope::empty(),
            $this->config->prettyPrint(),
        );
    }

    public function serializeElement(
        Element $element,
        int $depth,
        NamespaceScope $context,
        bool $prettyPrint,
    ): void {
        $children = $element->children();
        $namespaceDeclarations = $this->namespaceResolver->resolve(
            $element->qualifiedName(),
            $this->attributesByIdentity($element->attributes()),
            $this->namespaceDeclarationsByPrefix($element->namespaceDeclarations()),
            $context,
        );
        $inScopeContext = $context->withDeclarations($namespaceDeclarations);
        $attributes = $this->serializeAttributes($element->attributes(), $namespaceDeclarations);
        $indent = $prettyPrint ? $this->indent($depth) : '';
        $elementName = $element->name();

        if ($children === []) {
            if ($this->config->selfCloseEmptyElements()) {
                $this->output->write(sprintf('%s<%s%s/>', $indent, $elementName, $attributes));

                return;
            }

            $this->output->write(sprintf('%s<%s%s></%s>', $indent, $elementName, $attributes, $elementName));

            return;
        }

        if ($prettyPrint && $this->containsOnlyPrettyPrintableNodes($children)) {
            $this->output->write(sprintf('%s<%s%s>', $indent, $elementName, $attributes));
            $this->output->write($this->config->newline());

            foreach ($children as $index => $child) {
                if ($index > 0) {
                    $this->output->write($this->config->newline());
                }

                $this->serializeNode($child, $depth + 1, $inScopeContext, true);
            }

            $this->output->write($this->config->newline());
            $this->output->write(sprintf('%s</%s>', $indent, $elementName));

            return;
        }

        $this->output->write(sprintf('%s<%s%s>', $indent, $elementName, $attributes));

        foreach ($children as $child) {
            $this->serializeNode($child, 0, $inScopeContext, false);
        }

        $this->output->write(sprintf('</%s>', $elementName));
    }

    public function serializeDeclaration(XmlDeclaration $declaration): string
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

    public function serializeCData(CDataNode $node): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $node->content()) . ']]>';
    }

    public function serializeComment(CommentNode $node, int $depth, bool $prettyPrint): string
    {
        $indent = $prettyPrint ? $this->indent($depth) : '';

        return sprintf('%s<!--%s-->', $indent, $node->content());
    }

    public function serializeProcessingInstruction(
        ProcessingInstructionNode $node,
        int $depth,
        bool $prettyPrint,
    ): string {
        $indent = $prettyPrint ? $this->indent($depth) : '';

        if ($node->data() === '') {
            return sprintf('%s<?%s?>', $indent, $node->target());
        }

        return sprintf('%s<?%s %s?>', $indent, $node->target(), $node->data());
    }

    /**
     * @param list<Attribute> $attributes
     * @param list<NamespaceDeclaration> $namespaceDeclarations
     */
    public function serializeAttributes(array $attributes, array $namespaceDeclarations): string
    {
        $serialized = '';

        foreach ($namespaceDeclarations as $declaration) {
            $serialized .= sprintf(
                ' %s="%s"',
                $declaration->attributeName(),
                XmlEscaper::escapeAttributeValue($declaration->uri()),
            );
        }

        foreach ($attributes as $attribute) {
            $serialized .= sprintf(
                ' %s="%s"',
                $attribute->name(),
                XmlEscaper::escapeAttributeValue($attribute->value()),
            );
        }

        return $serialized;
    }

    private function serializeNode(
        Node $node,
        int $depth,
        NamespaceScope $context,
        bool $prettyPrint,
    ): void {
        if ($node instanceof Element) {
            $this->serializeElement($node, $depth, $context, $prettyPrint);

            return;
        }

        if ($node instanceof TextNode) {
            $this->output->write(XmlEscaper::escapeText($node->content()));

            return;
        }

        if ($node instanceof CDataNode) {
            $this->output->write($this->serializeCData($node));

            return;
        }

        if ($node instanceof CommentNode) {
            $this->output->write($this->serializeComment($node, $depth, $prettyPrint));

            return;
        }

        if ($node instanceof ProcessingInstructionNode) {
            $this->output->write($this->serializeProcessingInstruction($node, $depth, $prettyPrint));

            return;
        }

        throw new SerializationException(sprintf(
            'Cannot serialize node of type %s.',
            get_debug_type($node),
        ));
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

    private function isPrettyPrintableNode(Node $node): bool
    {
        return $node instanceof Element
            || $node instanceof CommentNode
            || $node instanceof ProcessingInstructionNode;
    }

    /**
     * @param list<Attribute> $attributes
     *
     * @return array<string, Attribute>
     */
    private function attributesByIdentity(array $attributes): array
    {
        $indexed = [];

        foreach ($attributes as $attribute) {
            $indexed[$attribute->identityKey()] = $attribute;
        }

        return $indexed;
    }

    /**
     * @param list<NamespaceDeclaration> $namespaceDeclarations
     *
     * @return array<string, NamespaceDeclaration>
     */
    private function namespaceDeclarationsByPrefix(array $namespaceDeclarations): array
    {
        $indexed = [];

        foreach ($namespaceDeclarations as $declaration) {
            $indexed[$declaration->prefixKey()] = $declaration;
        }

        return $indexed;
    }

    private function indent(int $depth): string
    {
        return str_repeat($this->config->indent(), $depth);
    }
}
