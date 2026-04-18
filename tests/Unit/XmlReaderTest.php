<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use DOMDocument;
use Kalle\Xml\Exception\DomInteropException;
use Kalle\Xml\Exception\FileReadException;
use Kalle\Xml\Exception\InvalidXmlName;
use Kalle\Xml\Exception\ParseException;
use Kalle\Xml\Exception\StreamReadException;
use Kalle\Xml\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

final class XmlReaderTest extends TestCase
{
    public function testItRejectsMalformedXmlStringsWithALibrarySpecificParseException(): void
    {
        try {
            XmlReader::fromString('<catalog><book></catalog>');
            self::fail('Expected malformed XML input to throw a ParseException.');
        } catch (ParseException $exception) {
            self::assertStringContainsString('Malformed XML in', $exception->getMessage());
            self::assertStringContainsString('string input', $exception->getMessage());
            self::assertStringContainsString('First libxml error', $exception->getMessage());
        }
    }

    public function testItRejectsEmptyXmlStringsWithAParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('string input');

        XmlReader::fromString('');
    }

    public function testItRejectsAnEmptyFilePath(): void
    {
        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('empty path');

        XmlReader::fromFile('');
    }

    public function testItRejectsFilePathsContainingNullBytes(): void
    {
        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('null bytes');

        XmlReader::fromFile("\0invalid.xml");
    }

    public function testItRejectsMissingFilesWithAFileSpecificException(): void
    {
        $path = sys_get_temp_dir() . '/kalle-xml-missing-reader.xml';

        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('Cannot read XML file');
        $this->expectExceptionMessage('No such file or directory');

        XmlReader::fromFile($path);
    }

    public function testItRejectsDirectoryPathsAsInvalidFileInput(): void
    {
        $path = sys_get_temp_dir() . '/kalle-xml-reader-dir-' . uniqid();

        self::assertTrue(mkdir($path));

        try {
            $this->expectException(FileReadException::class);
            $this->expectExceptionMessage('Cannot read XML file');
            $this->expectExceptionMessage('Is a directory');

            XmlReader::fromFile($path);
        } finally {
            rmdir($path);
        }
    }

    public function testItRejectsNonStreamInputs(): void
    {
        $this->expectException(StreamReadException::class);
        $this->expectExceptionMessage('readable stream resource');

        XmlReader::fromStream(123);
    }

    public function testItRejectsNonReadableStreams(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-readonly-');

        self::assertNotFalse($path);

        $stream = fopen($path, 'wb');

        self::assertIsResource($stream);

        try {
            $this->expectException(StreamReadException::class);
            $this->expectExceptionMessage('not readable');

            XmlReader::fromStream($stream);
        } finally {
            fclose($stream);
            @unlink($path);
        }
    }

    public function testItRejectsRawPrefixedLookupStringsInFavorOfQualifiedNames(): void
    {
        $document = XmlReader::fromString('<root xmlns:a="urn:feed"><a:entry/></root>');

        $this->expectException(InvalidXmlName::class);
        $this->expectExceptionMessage('Xml::qname()');

        $document->rootElement()->firstChildElement('a:entry');
    }

    public function testItRejectsMalformedNamespaceXmlWithParseContext(): void
    {
        try {
            XmlReader::fromString('<root><a:child/></root>');
            self::fail('Expected malformed namespace XML to throw a ParseException.');
        } catch (ParseException $exception) {
            self::assertStringContainsString('Malformed XML in', $exception->getMessage());
            self::assertStringContainsString('line 1', $exception->getMessage());
            self::assertStringContainsString('column', $exception->getMessage());
            self::assertStringContainsString('Namespace prefix a on child is not defined', $exception->getMessage());
        }
    }

    public function testItRejectsDomDocumentsWithoutADocumentElement(): void
    {
        $this->expectException(DomInteropException::class);
        $this->expectExceptionMessage('XmlReader::fromDomDocument() requires a DOMDocument with a document element.');

        XmlReader::fromDomDocument(new DOMDocument('1.0', 'UTF-8'));
    }
}
