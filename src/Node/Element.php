<?php

declare(strict_types=1);

namespace Kalle\Xml\Node;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Exception\DuplicateAttributeException;
use Kalle\Xml\Exception\DuplicateNamespaceDeclarationException;
use Kalle\Xml\Exception\InvalidNamespaceDeclarationException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\ElementNamespaceValidator;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Stringable;
use TypeError;

use function array_key_exists;
use function array_values;
use function get_debug_type;

final readonly class Element implements Node
{
    private QualifiedName $qualifiedName;

    /**
     * @var array<string, Attribute>
     */
    private array $attributes;

    /**
     * @var list<Node>
     */
    private array $children;

    /**
     * @var array<string, NamespaceDeclaration>
     */
    private array $namespaceDeclarations;

    /**
     * @param string|QualifiedName $name
     * @param iterable<mixed> $attributes
     * @param iterable<mixed> $children
     * @param iterable<mixed> $namespaceDeclarations
     */
    public function __construct(
        string|QualifiedName $name,
        iterable $attributes = [],
        iterable $children = [],
        iterable $namespaceDeclarations = [],
    ) {
        $this->qualifiedName = QualifiedName::forElement($name);
        $elementName = $this->qualifiedName->lexicalName();

        $this->attributes = self::normalizeAttributes($attributes, $elementName);
        $this->children = self::normalizeChildren($children, $elementName);
        $this->namespaceDeclarations = self::normalizeNamespaceDeclarations(
            $namespaceDeclarations,
            $elementName,
        );

        ElementNamespaceValidator::assertCoherent(
            $this->qualifiedName,
            $this->attributes,
            $this->namespaceDeclarations,
        );
    }

    public function name(): string
    {
        return $this->qualifiedName->lexicalName();
    }

    public function qualifiedName(): QualifiedName
    {
        return $this->qualifiedName;
    }

    public function localName(): string
    {
        return $this->qualifiedName->localName();
    }

    public function prefix(): ?string
    {
        return $this->qualifiedName->prefix();
    }

    public function namespaceUri(): ?string
    {
        return $this->qualifiedName->namespaceUri();
    }

    /**
     * @return list<Attribute>
     */
    public function attributes(): array
    {
        return array_values($this->attributes);
    }

    /**
     * @return list<Node>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * @return list<NamespaceDeclaration>
     */
    public function namespaceDeclarations(): array
    {
        return array_values($this->namespaceDeclarations);
    }

    /**
     * @param string|QualifiedName $name
     * @param string|int|float|bool|Stringable|null $value
     */
    public function attribute(
        string|QualifiedName $name,
        string|int|float|bool|Stringable|null $value,
    ): self {
        $attributeName = QualifiedName::forAttribute($name);
        $attributes = $this->attributes;
        $identityKey = $attributeName->identityKey();
        $existing = $attributes[$identityKey] ?? null;

        if ($value === null) {
            if ($existing === null) {
                return $this;
            }

            unset($attributes[$identityKey]);

            return $this->rebuild(attributes: $attributes);
        }

        $attribute = new Attribute($attributeName, $value);

        if ($existing !== null && $existing->name() === $attribute->name() && $existing->value() === $attribute->value()) {
            return $this;
        }

        $attributes[$identityKey] = $attribute;

        return $this->rebuild(attributes: $attributes);
    }

    public function withoutAttribute(string|QualifiedName $name): self
    {
        $identityKey = QualifiedName::forAttribute($name)->identityKey();

        if (!array_key_exists($identityKey, $this->attributes)) {
            return $this;
        }

        $attributes = $this->attributes;
        unset($attributes[$identityKey]);

        return $this->rebuild(attributes: $attributes);
    }

    public function declareNamespace(string $prefix, string $uri): self
    {
        if ($prefix === '') {
            throw new InvalidNamespaceDeclarationException(
                'Namespace prefix cannot be empty. Use declareDefaultNamespace() instead.',
            );
        }

        return $this->withNamespaceDeclaration(new NamespaceDeclaration($prefix, $uri));
    }

    public function declareDefaultNamespace(string $uri): self
    {
        return $this->withNamespaceDeclaration(new NamespaceDeclaration(null, $uri));
    }

    public function child(Node $child): self
    {
        $children = $this->children;
        $children[] = $child;

        return $this->rebuild(children: $children);
    }

    public function text(string $content): self
    {
        return $this->child(new TextNode($content));
    }

    public function cdata(string $content): self
    {
        return $this->child(new CDataNode($content));
    }

    public function comment(string $content): self
    {
        return $this->child(new CommentNode($content));
    }

    public function processingInstruction(string $target, string $data = ''): self
    {
        return $this->child(new ProcessingInstructionNode($target, $data));
    }

    /**
     * @param iterable<mixed> $attributes
     *
     * @return array<string, Attribute>
     */
    private static function normalizeAttributes(iterable $attributes, string $elementName): array
    {
        $normalized = [];

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof Attribute) {
                throw new TypeError(sprintf(
                    'Element "%s" attributes must contain only Attribute instances; %s given.',
                    $elementName,
                    get_debug_type($attribute),
                ));
            }

            if (array_key_exists($attribute->identityKey(), $normalized)) {
                throw new DuplicateAttributeException(sprintf(
                    'Element "%s" already has attribute "%s".',
                    $elementName,
                    $attribute->name(),
                ));
            }

            $normalized[$attribute->identityKey()] = $attribute;
        }

        return $normalized;
    }

    /**
     * @param iterable<mixed> $children
     *
     * @return list<Node>
     */
    private static function normalizeChildren(iterable $children, string $elementName): array
    {
        $normalized = [];

        foreach ($children as $child) {
            if (!$child instanceof Node) {
                throw new TypeError(sprintf(
                    'Element "%s" children must contain only Node instances; %s given.',
                    $elementName,
                    get_debug_type($child),
                ));
            }

            $normalized[] = $child;
        }

        return $normalized;
    }

    /**
     * @param iterable<mixed> $namespaceDeclarations
     *
     * @return array<string, NamespaceDeclaration>
     */
    private static function normalizeNamespaceDeclarations(iterable $namespaceDeclarations, string $elementName): array
    {
        $normalized = [];

        foreach ($namespaceDeclarations as $declaration) {
            if (!$declaration instanceof NamespaceDeclaration) {
                throw new TypeError(sprintf(
                    'Element "%s" namespace declarations must contain only NamespaceDeclaration instances; %s given.',
                    $elementName,
                    get_debug_type($declaration),
                ));
            }

            $prefixKey = $declaration->prefixKey();

            if (!array_key_exists($prefixKey, $normalized)) {
                $normalized[$prefixKey] = $declaration;
                continue;
            }

            $existing = $normalized[$prefixKey];

            if ($existing->uri() === $declaration->uri()) {
                continue;
            }

            throw new DuplicateNamespaceDeclarationException(sprintf(
                'Element "%s" already declares %s as "%s"; cannot redeclare it as "%s".',
                $elementName,
                self::describeNamespacePrefix($declaration),
                $existing->uri(),
                $declaration->uri(),
            ));
        }

        return $normalized;
    }

    private function withNamespaceDeclaration(NamespaceDeclaration $declaration): self
    {
        $prefixKey = $declaration->prefixKey();
        $existing = $this->namespaceDeclarations[$prefixKey] ?? null;

        if ($existing !== null) {
            if ($existing->uri() === $declaration->uri()) {
                return $this;
            }

            throw new DuplicateNamespaceDeclarationException(sprintf(
                'Element "%s" already declares %s as "%s"; cannot redeclare it as "%s".',
                $this->name(),
                self::describeNamespacePrefix($existing),
                $existing->uri(),
                $declaration->uri(),
            ));
        }

        $namespaceDeclarations = $this->namespaceDeclarations;
        $namespaceDeclarations[$prefixKey] = $declaration;

        return $this->rebuild(namespaceDeclarations: $namespaceDeclarations);
    }

    private static function describeNamespacePrefix(NamespaceDeclaration $declaration): string
    {
        if ($declaration->isDefault()) {
            return 'the default namespace';
        }

        return sprintf('prefix "%s"', $declaration->prefix());
    }

    /**
     * @param array<string, Attribute>|null $attributes
     * @param list<Node>|null $children
     * @param array<string, NamespaceDeclaration>|null $namespaceDeclarations
     */
    private function rebuild(
        ?array $attributes = null,
        ?array $children = null,
        ?array $namespaceDeclarations = null,
    ): self {
        return new self(
            $this->qualifiedName,
            $attributes ?? $this->attributes,
            $children ?? $this->children,
            $namespaceDeclarations ?? $this->namespaceDeclarations,
        );
    }
}
