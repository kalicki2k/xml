<?php

declare(strict_types=1);

namespace Kalle\Xml\Attribute;

use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Name\QualifiedName;
use Stringable;

use function is_bool;

final readonly class Attribute
{
    private QualifiedName $qualifiedName;
    private string $value;

    public function __construct(
        string|QualifiedName $name,
        string|int|float|bool|Stringable $value,
    ) {
        $this->qualifiedName = QualifiedName::forAttribute($name);
        $this->value = self::normalizeValue($value);
        XmlEscaper::assertValidString($this->value, 'Attribute value');
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

    public function identityKey(): string
    {
        return $this->qualifiedName->identityKey();
    }

    public function value(): string
    {
        return $this->value;
    }

    private static function normalizeValue(string|int|float|bool|Stringable $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
