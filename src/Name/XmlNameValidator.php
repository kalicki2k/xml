<?php

declare(strict_types=1);

namespace Kalle\Xml\Name;

use Kalle\Xml\Exception\InvalidXmlName;

use function preg_match;
use function sprintf;
use function str_contains;
use function strcasecmp;

final class XmlNameValidator
{
    private const NAME_START_CHAR_CLASS = 'A-Z_a-z\x{C0}-\x{D6}\x{D8}-\x{F6}\x{F8}-\x{2FF}\x{370}-\x{37D}\x{37F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}';
    private const NAME_CHAR_CLASS = self::NAME_START_CHAR_CLASS . '\-.0-9\x{B7}\x{0300}-\x{036F}\x{203F}-\x{2040}';

    private function __construct() {}

    public static function assertValidElementName(string $name): void
    {
        self::assertValidUnqualifiedName($name, 'Element');
    }

    public static function assertValidAttributeName(string $name): void
    {
        self::assertValidUnqualifiedName($name, 'Attribute');
    }

    public static function assertValidUnqualifiedName(string $name, string $context = 'XML'): void
    {
        if (str_contains($name, ':')) {
            throw new InvalidXmlName(sprintf(
                '%s name "%s" looks prefixed. Use %s.',
                $context,
                $name,
                'XmlBuilder::qname()',
            ));
        }

        self::assertValidNamePart($name, sprintf('%s name', $context));
    }

    public static function assertValidLocalName(string $name, string $context = 'XML'): void
    {
        self::assertValidNamePart($name, sprintf('%s local name', $context));
    }

    public static function assertValidNamespacePrefix(string $prefix, string $context = 'XML'): void
    {
        self::assertValidNamePart($prefix, sprintf('%s namespace prefix', $context));
    }

    public static function assertValidProcessingInstructionTarget(string $target): void
    {
        if (str_contains($target, ':')) {
            throw new InvalidXmlName(sprintf(
                'Processing instruction target "%s" cannot contain ":".',
                $target,
            ));
        }

        self::assertValidNamePart($target, 'Processing instruction target');

        if (strcasecmp($target, 'xml') === 0) {
            throw new InvalidXmlName(
                'Processing instruction target "xml" is reserved.',
            );
        }
    }

    private static function assertValidNamePart(string $value, string $label): void
    {
        if ($value === '') {
            throw new InvalidXmlName(sprintf('%s cannot be empty.', $label));
        }

        if (preg_match('//u', $value) !== 1) {
            throw new InvalidXmlName(sprintf('%s must be valid UTF-8.', $label));
        }

        $pattern = '/^[' . self::NAME_START_CHAR_CLASS . '][' . self::NAME_CHAR_CLASS . ']*$/u';

        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidXmlName(sprintf('%s "%s" is not a valid XML name.', $label, $value));
        }
    }
}
