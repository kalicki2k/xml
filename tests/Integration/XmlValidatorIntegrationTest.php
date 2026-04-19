<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Validation\XmlValidator;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_put_contents;
use function fopen;
use function fwrite;
use function is_dir;
use function mkdir;
use function rewind;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class XmlValidatorIntegrationTest extends TestCase
{
    public function testItValidatesXmlStringsAgainstASchemaString(): void
    {
        $validator = XmlValidator::fromString($this->catalogSchema());

        $result = $validator->validateString(
            <<<'XML'
<catalog>
    <book isbn="9780132350884">
        <title>Clean Code</title>
        <price>39.90</price>
    </book>
</catalog>
XML,
        );

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());
        self::assertNull($result->firstError());
    }

    public function testItReturnsValidationErrorsForInvalidXmlStrings(): void
    {
        $validator = XmlValidator::fromString($this->catalogSchema());

        $result = $validator->validateString(
            <<<'XML'
<catalog>
    <book isbn="9780132350884">
        <title>Clean Code</title>
    </book>
</catalog>
XML,
        );

        self::assertFalse($result->isValid());
        $firstError = $result->firstError();

        self::assertNotNull($firstError);
        self::assertCount(1, $result->errors());
        self::assertStringContainsString('Missing child element', $firstError->message());
        self::assertSame(LIBXML_ERR_ERROR, $firstError->level());
        self::assertSame(2, $firstError->line());
    }

    public function testItCollectsMultipleValidationErrors(): void
    {
        $validator = XmlValidator::fromString($this->catalogSchema());

        $result = $validator->validateString(
            <<<'XML'
<catalog>
    <book isbn="9780132350884">
        <title>Clean Code</title>
    </book>
    <book isbn="9780321125217">
        <title>Domain-Driven Design</title>
    </book>
</catalog>
XML,
        );

        self::assertFalse($result->isValid());
        self::assertCount(2, $result->errors());
        self::assertStringContainsString('Missing child element', $result->errors()[0]->message());
        self::assertStringContainsString('Missing child element', $result->errors()[1]->message());
    }

    public function testItValidatesWriterBuiltDocumentsAgainstTheCatalogSchema(): void
    {
        $validator = XmlValidator::fromString($this->catalogSchema());
        $document = $this->catalogDocument();

        $result = $validator->validateXmlDocument($document);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());
        self::assertNull($result->firstError());
    }

    public function testItReturnsValidationErrorsForInvalidWriterBuiltDocuments(): void
    {
        $validator = XmlValidator::fromString($this->catalogSchema());
        $document = $this->catalogDocument(includePrice: false);

        $result = $validator->validateXmlDocument($document);

        self::assertFalse($result->isValid());
        $firstError = $result->firstError();

        self::assertNotNull($firstError);
        self::assertStringContainsString('Missing child element', $firstError->message());
    }

    public function testXmlDeclarationDoesNotChangeWriterBuiltValidationOutcome(): void
    {
        $validator = XmlValidator::fromString($this->catalogSchema());
        $documentWithDeclaration = $this->catalogDocument();
        $documentWithoutDeclaration = $documentWithDeclaration->withoutDeclaration();

        $resultWithDeclaration = $validator->validateXmlDocument($documentWithDeclaration);
        $resultWithoutDeclaration = $validator->validateXmlDocument($documentWithoutDeclaration);

        self::assertTrue($resultWithDeclaration->isValid());
        self::assertTrue($resultWithoutDeclaration->isValid());
        self::assertSame([], $resultWithDeclaration->errors());
        self::assertSame([], $resultWithoutDeclaration->errors());
    }

    public function testItValidatesNamespacedXmlDocuments(): void
    {
        $validator = XmlValidator::fromString(
            <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:feed" xmlns:feed="urn:feed" elementFormDefault="qualified">
    <xs:element name="feed">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="entry" maxOccurs="unbounded">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="title" type="xs:string"/>
                        </xs:sequence>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD,
        );

        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed'))
                ->declareDefaultNamespace('urn:feed')
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
                        ->child(XmlBuilder::element(XmlBuilder::qname('title', 'urn:feed'))->text('Blue mug')),
                ),
        );

        $result = $validator->validateXmlDocument($document);

        self::assertTrue($result->isValid());
        self::assertNull($result->firstError());
    }

    public function testItValidatesXmlFilesAgainstSchemaFilesWithRelativeIncludes(): void
    {
        $directory = sys_get_temp_dir() . '/kalle-xml-xsd-' . uniqid();

        self::assertTrue(mkdir($directory));

        $schemaPath = $directory . '/catalog.xsd';
        $typesPath = $directory . '/catalog-types.xsd';
        $xmlPath = $directory . '/catalog.xml';

        file_put_contents(
            $typesPath,
            <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:complexType name="BookType">
        <xs:sequence>
            <xs:element name="title" type="xs:string"/>
            <xs:element name="price" type="xs:decimal"/>
        </xs:sequence>
        <xs:attribute name="isbn" type="xs:string" use="required"/>
    </xs:complexType>
</xs:schema>
XSD,
        );
        file_put_contents(
            $schemaPath,
            <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:include schemaLocation="catalog-types.xsd"/>
    <xs:element name="catalog">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="book" type="BookType" maxOccurs="unbounded"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD,
        );
        file_put_contents(
            $xmlPath,
            <<<'XML'
<catalog>
    <book isbn="9780132350884">
        <title>Clean Code</title>
        <price>39.90</price>
    </book>
</catalog>
XML,
        );

        try {
            $validator = XmlValidator::fromFile($schemaPath);
            $result = $validator->validateFile($xmlPath);

            self::assertTrue($result->isValid());
            self::assertSame([], $result->errors());
        } finally {
            @unlink($xmlPath);
            @unlink($schemaPath);
            @unlink($typesPath);

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testItValidatesSchemasAndXmlFromStreams(): void
    {
        $schemaStream = fopen('php://temp', 'wb+');
        $xmlStream = fopen('php://temp', 'wb+');

        self::assertIsResource($schemaStream);
        self::assertIsResource($xmlStream);

        fwrite(
            $schemaStream,
            <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="config">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="database">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="host" type="xs:string"/>
                        </xs:sequence>
                        <xs:attribute name="driver" type="xs:string" use="required"/>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD,
        );
        fwrite(
            $xmlStream,
            <<<'XML'
<config>
    <database driver="pgsql">
        <host>db.internal</host>
    </database>
</config>
XML,
        );

        rewind($schemaStream);
        rewind($xmlStream);

        try {
            $validator = XmlValidator::fromStream($schemaStream);
            $result = $validator->validateStream($xmlStream);

            self::assertTrue($result->isValid());
            self::assertNull($result->firstError());
        } finally {
            fclose($schemaStream);
            fclose($xmlStream);
        }
    }

    private function catalogSchema(): string
    {
        return <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="book" maxOccurs="unbounded">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="title" type="xs:string"/>
                            <xs:element name="price" type="xs:decimal"/>
                        </xs:sequence>
                        <xs:attribute name="isbn" type="xs:string" use="required"/>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>
XSD;
    }

    private function catalogDocument(bool $includePrice = true): XmlDocument
    {
        $book = XmlBuilder::element('book')
            ->attribute('isbn', '9780132350884')
            ->child(XmlBuilder::element('title')->text('Clean Code'));

        if ($includePrice) {
            $book = $book->child(XmlBuilder::element('price')->text('39.90'));
        }

        return XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->child($book),
        );
    }
}
