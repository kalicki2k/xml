<?php

declare(strict_types=1);

namespace Kalle\Xml\Escape;

use Kalle\Xml\Exception\InvalidXmlCharacter;

use function ord;
use function preg_match;
use function strlen;
use function strtr;

final class XmlEscaper
{
    private const INVALID_CHARACTER_PATTERN = '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u';

    private function __construct() {}

    public static function escapeText(string $value): string
    {
        self::assertValidString($value, 'Text node content');

        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            "\r" => '&#xD;',
        ]);
    }

    public static function escapeAttributeValue(string $value): string
    {
        self::assertValidString($value, 'Attribute value');

        return strtr($value, [
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
            '\'' => '&apos;',
            "\t" => '&#x9;',
            "\n" => '&#xA;',
            "\r" => '&#xD;',
        ]);
    }

    public static function assertValidString(string $value, string $context = 'XML content'): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new InvalidXmlCharacter(sprintf('%s must be valid UTF-8.', $context));
        }

        if (preg_match(self::INVALID_CHARACTER_PATTERN, $value, $matches) === 1) {
            $codePoint = self::unicodeCodePoint($matches[0]);

            throw new InvalidXmlCharacter(sprintf(
                '%s contains invalid XML character U+%04X.',
                $context,
                $codePoint,
            ));
        }
    }

    private static function unicodeCodePoint(string $character): int
    {
        $length = strlen($character);

        if ($length === 1) {
            return ord($character);
        }

        if ($length === 2) {
            return ((ord($character[0]) & 0x1F) << 6)
                | (ord($character[1]) & 0x3F);
        }

        if ($length === 3) {
            return ((ord($character[0]) & 0x0F) << 12)
                | ((ord($character[1]) & 0x3F) << 6)
                | (ord($character[2]) & 0x3F);
        }

        if ($length === 4) {
            return ((ord($character[0]) & 0x07) << 18)
                | ((ord($character[1]) & 0x3F) << 12)
                | ((ord($character[2]) & 0x3F) << 6)
                | (ord($character[3]) & 0x3F);
        }

        throw new InvalidXmlCharacter('Invalid UTF-8 sequence.');
    }
}
