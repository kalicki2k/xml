<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Exception\FileReadException;
use Kalle\Xml\Exception\InvalidSchemaException;
use Kalle\Xml\Exception\ParseException;
use Kalle\Xml\Exception\StreamReadException;
use Kalle\Xml\Validation\XmlValidator;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class XmlValidatorTest extends TestCase
{
    public function testItRejectsMalformedSchemaStrings(): void
    {
        try {
            XmlValidator::fromString('<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:element');
            self::fail('Expected malformed schema input to throw an InvalidSchemaException.');
        } catch (InvalidSchemaException $exception) {
            self::assertStringContainsString('Invalid XSD schema in string input.', $exception->getMessage());
            self::assertStringContainsString('First libxml error', $exception->getMessage());
        }
    }

    public function testItRejectsSchemaDocumentsWithTheWrongRootElement(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('document element must be');

        XmlValidator::fromString('<catalog/>');
    }

    public function testItRejectsInvalidSchemaDefinitions(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Invalid XSD schema in string input.');

        XmlValidator::fromString(
            <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element ref="missing"/>
</xs:schema>
XSD,
        );
    }

    public function testItRejectsAnEmptySchemaFilePath(): void
    {
        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('empty path');

        XmlValidator::fromFile('');
    }

    public function testItRejectsMissingSchemaFiles(): void
    {
        $path = sys_get_temp_dir() . '/kalle-xml-missing-schema.xsd';

        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('Cannot read XSD schema file');
        $this->expectExceptionMessage('No such file or directory');

        XmlValidator::fromFile($path);
    }

    public function testItRejectsNonStreamSchemaInputs(): void
    {
        $this->expectException(StreamReadException::class);
        $this->expectExceptionMessage('XmlValidator requires a readable XSD stream resource');

        XmlValidator::fromStream(123);
    }

    public function testItRejectsNonStreamXmlInputsDuringValidation(): void
    {
        $validator = XmlValidator::fromString(
            <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog"/>
</xs:schema>
XSD,
        );

        $this->expectException(StreamReadException::class);
        $this->expectExceptionMessage('XmlValidator requires a readable XML stream resource');

        $validator->validateStream(123);
    }

    public function testItRejectsMalformedXmlDuringValidation(): void
    {
        $validator = XmlValidator::fromString(
            <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog"/>
</xs:schema>
XSD,
        );

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Malformed XML in string input.');

        $validator->validateString('<catalog><book></catalog>');
    }

    public function testItRejectsNonReadableSchemaStreams(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-xsd-readonly-');

        self::assertNotFalse($path);

        $stream = fopen($path, 'wb');

        self::assertIsResource($stream);

        try {
            $this->expectException(StreamReadException::class);
            $this->expectExceptionMessage('XmlValidator requires a readable XSD stream resource');
            $this->expectExceptionMessage('not readable');

            XmlValidator::fromStream($stream);
        } finally {
            fclose($stream);
            @unlink($path);
        }
    }
}
