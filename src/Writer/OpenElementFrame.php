<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Exception\DuplicateNamespaceDeclarationException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Namespace\NamespaceScope;

use function sprintf;

final class OpenElementFrame
{
    public const CONTENT_EMPTY = 'empty';
    public const CONTENT_STRUCTURAL = 'structural';
    public const CONTENT_TEXT = 'text';

    /**
     * @var array<string, Attribute>
     */
    private array $attributes = [];

    /**
     * @var array<string, NamespaceDeclaration>
     */
    private array $namespaceDeclarations = [];

    private ?NamespaceScope $inScopeContext = null;

    private bool $startTagFlushed = false;

    private string $contentMode = self::CONTENT_EMPTY;

    public function __construct(
        private readonly QualifiedName $qualifiedName,
        private readonly NamespaceScope $parentContext,
        private readonly int $depth,
        private readonly bool $prependNewline,
        private readonly bool $prettyPrintEnabled,
    ) {}

    public function qualifiedName(): QualifiedName
    {
        return $this->qualifiedName;
    }

    public function lexicalName(): string
    {
        return $this->qualifiedName->lexicalName();
    }

    public function parentContext(): NamespaceScope
    {
        return $this->parentContext;
    }

    public function depth(): int
    {
        return $this->depth;
    }

    public function prependNewline(): bool
    {
        return $this->prependNewline;
    }

    public function prettyPrintEnabled(): bool
    {
        return $this->prettyPrintEnabled;
    }

    public function startTagFlushed(): bool
    {
        return $this->startTagFlushed;
    }

    public function contentMode(): string
    {
        return $this->contentMode;
    }

    /**
     * @return array<string, Attribute>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, NamespaceDeclaration>
     */
    public function namespaceDeclarations(): array
    {
        return $this->namespaceDeclarations;
    }

    public function inScopeContext(): NamespaceScope
    {
        return $this->inScopeContext ?? $this->parentContext;
    }

    public function addAttribute(Attribute $attribute): void
    {
        $this->attributes[$attribute->identityKey()] = $attribute;
    }

    public function removeAttribute(QualifiedName $name): void
    {
        unset($this->attributes[$name->identityKey()]);
    }

    public function addNamespaceDeclaration(NamespaceDeclaration $declaration): void
    {
        $prefixKey = $declaration->prefixKey();
        $existing = $this->namespaceDeclarations[$prefixKey] ?? null;

        if ($existing !== null) {
            if ($existing->uri() === $declaration->uri()) {
                return;
            }

            throw new DuplicateNamespaceDeclarationException(sprintf(
                'Element "%s" already declares %s as "%s"; cannot redeclare it as "%s".',
                $this->lexicalName(),
                self::describeNamespacePrefix($existing),
                $existing->uri(),
                $declaration->uri(),
            ));
        }

        $this->namespaceDeclarations[$prefixKey] = $declaration;
    }

    public function markStartTagFlushed(NamespaceScope $inScopeContext): void
    {
        $this->startTagFlushed = true;
        $this->inScopeContext = $inScopeContext;
    }

    public function markStructuralContent(): void
    {
        if ($this->contentMode === self::CONTENT_EMPTY) {
            $this->contentMode = self::CONTENT_STRUCTURAL;
        }
    }

    public function markTextLikeContent(): void
    {
        $this->contentMode = self::CONTENT_TEXT;
    }

    private static function describeNamespacePrefix(NamespaceDeclaration $declaration): string
    {
        if ($declaration->isDefault()) {
            return 'the default namespace';
        }

        return sprintf('prefix "%s"', $declaration->prefix());
    }
}
