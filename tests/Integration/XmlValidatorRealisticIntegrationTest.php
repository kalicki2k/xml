<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Validation\XmlValidator;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class XmlValidatorRealisticIntegrationTest extends TestCase
{
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function testItValidatesARealisticWriterBuiltCatalogDocumentAgainstAnXsd(): void
    {
        $validator = XmlValidator::fromString($this->realisticCatalogSchema());

        $result = $validator->validateXmlDocument($this->createCatalogDocument());

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());
        self::assertNull($result->firstError());
    }

    public function testItCollectsMultipleValidationErrorsForAnInvalidWriterBuiltCatalogDocument(): void
    {
        $validator = XmlValidator::fromString($this->realisticCatalogSchema());

        $result = $validator->validateXmlDocument($this->createInvalidCatalogDocument());

        self::assertFalse($result->isValid());
        self::assertCount(2, $result->errors());
        $firstError = $result->firstError();

        $messages = implode(
            "\n",
            array_map(
                static fn ($error): string => $error->message(),
                $result->errors(),
            ),
        );

        self::assertStringContainsString('Expected is ( price )', $messages);
        self::assertStringContainsString('Expected is ( author )', $messages);
        self::assertNotNull($firstError);
        self::assertNotNull($firstError->line());
    }

    public function testItValidatesANamespaceAwareWriterBuiltInvoiceDocumentAgainstImportedSchemas(): void
    {
        $directory = $this->createTemporaryValidationDirectory('invoice-schema');
        $schemaPath = $this->writeInvoiceSchemaFiles($directory);

        try {
            $validator = XmlValidator::fromFile($schemaPath);
            $result = $validator->validateXmlDocument($this->createInvoiceDocument());

            self::assertTrue($result->isValid());
            self::assertSame([], $result->errors());
            self::assertNull($result->firstError());
        } finally {
            $this->cleanupValidationDirectory(
                $directory,
                [
                    $directory . '/invoice.xsd',
                    $directory . '/common-aggregate-components.xsd',
                    $directory . '/common-basic-components.xsd',
                ],
            );
        }
    }

    public function testItValidatesAnInvoiceXmlFileAgainstImportedSchemas(): void
    {
        $directory = $this->createTemporaryValidationDirectory('invoice-file');
        $schemaPath = $this->writeInvoiceSchemaFiles($directory);
        $xmlPath = $directory . '/invoice.xml';

        file_put_contents($xmlPath, XmlWriter::toString($this->createInvoiceDocument()));

        try {
            $validator = XmlValidator::fromFile($schemaPath);
            $result = $validator->validateFile($xmlPath);

            self::assertTrue($result->isValid());
            self::assertSame([], $result->errors());
            self::assertNull($result->firstError());
        } finally {
            $this->cleanupValidationDirectory(
                $directory,
                [
                    $xmlPath,
                    $directory . '/invoice.xsd',
                    $directory . '/common-aggregate-components.xsd',
                    $directory . '/common-basic-components.xsd',
                ],
            );
        }
    }

    private function realisticCatalogSchema(): string
    {
        return <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="book" type="BookType" maxOccurs="unbounded"/>
            </xs:sequence>
            <xs:attribute name="generatedAt" type="xs:dateTime" use="required"/>
        </xs:complexType>
    </xs:element>
    <xs:complexType name="BookType">
        <xs:sequence>
            <xs:element name="title" type="xs:string"/>
            <xs:element name="author" type="xs:string"/>
            <xs:element name="price" type="PriceType"/>
        </xs:sequence>
        <xs:attribute name="isbn" type="xs:string" use="required"/>
        <xs:attribute name="available" type="xs:boolean" use="required"/>
    </xs:complexType>
    <xs:complexType name="PriceType">
        <xs:simpleContent>
            <xs:extension base="xs:decimal">
                <xs:attribute name="currency" type="xs:string" use="required"/>
            </xs:extension>
        </xs:simpleContent>
    </xs:complexType>
</xs:schema>
XSD;
    }

    private function createCatalogDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->attribute('available', true)
                        ->child(XmlBuilder::element('title')->text('Clean Code'))
                        ->child(XmlBuilder::element('author')->text('Robert C. Martin'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('39.90')),
                )
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780321125217')
                        ->attribute('available', false)
                        ->child(XmlBuilder::element('title')->text('Domain-Driven Design'))
                        ->child(XmlBuilder::element('author')->text('Eric Evans'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('54.90')),
                ),
        );
    }

    private function createInvalidCatalogDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->attribute('available', true)
                        ->child(XmlBuilder::element('title')->text('Clean Code'))
                        ->child(XmlBuilder::element('author')->text('Robert C. Martin')),
                )
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780321125217')
                        ->attribute('available', false)
                        ->child(XmlBuilder::element('title')->text('Domain-Driven Design'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('54.90')),
                ),
        );
    }

    private function createInvoiceDocument(): XmlDocument
    {
        return XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('Invoice', self::UBL_INVOICE_NS))
                ->declareDefaultNamespace(self::UBL_INVOICE_NS)
                ->declareNamespace('cac', self::UBL_CAC_NS)
                ->declareNamespace('cbc', self::UBL_CBC_NS)
                ->declareNamespace('xsi', self::XSI_NS)
                ->attribute(XmlBuilder::qname('lang', QualifiedName::XML_NAMESPACE_URI, 'xml'), 'de')
                ->attribute(
                    XmlBuilder::qname('schemaLocation', self::XSI_NS, 'xsi'),
                    self::UBL_INVOICE_NS . ' invoice.xsd',
                )
                ->child(XmlBuilder::element(XmlBuilder::qname('ID', self::UBL_CBC_NS, 'cbc'))->text('RE-2026-0042'))
                ->child(XmlBuilder::element(XmlBuilder::qname('IssueDate', self::UBL_CBC_NS, 'cbc'))->text('2026-04-17'))
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('AccountingSupplierParty', self::UBL_CAC_NS, 'cac'))
                        ->child(
                            XmlBuilder::element(XmlBuilder::qname('Party', self::UBL_CAC_NS, 'cac'))
                                ->child(
                                    XmlBuilder::element(XmlBuilder::qname('EndpointID', self::UBL_CBC_NS, 'cbc'))
                                        ->attribute('schemeID', '0088')
                                        ->text('0409876543210'),
                                )
                                ->child(
                                    XmlBuilder::element(XmlBuilder::qname('PartyName', self::UBL_CAC_NS, 'cac'))
                                        ->child(XmlBuilder::element(XmlBuilder::qname('Name', self::UBL_CBC_NS, 'cbc'))->text('Muster Software GmbH')),
                                ),
                        ),
                ),
        );
    }

    private function writeInvoiceSchemaFiles(string $directory): string
    {
        file_put_contents(
            $directory . '/common-basic-components.xsd',
            sprintf(
                <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:cbc="%s" targetNamespace="%s" elementFormDefault="qualified">
    <xs:complexType name="IdentifierType">
        <xs:simpleContent>
            <xs:extension base="xs:string">
                <xs:attribute name="schemeID" type="xs:string" use="optional"/>
            </xs:extension>
        </xs:simpleContent>
    </xs:complexType>
    <xs:element name="ID" type="xs:string"/>
    <xs:element name="IssueDate" type="xs:date"/>
    <xs:element name="EndpointID" type="cbc:IdentifierType"/>
    <xs:element name="Name" type="xs:string"/>
</xs:schema>
XSD,
                self::UBL_CBC_NS,
                self::UBL_CBC_NS,
            ),
        );

        file_put_contents(
            $directory . '/common-aggregate-components.xsd',
            sprintf(
                <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:cac="%s" xmlns:cbc="%s" targetNamespace="%s" elementFormDefault="qualified">
    <xs:import namespace="%s" schemaLocation="common-basic-components.xsd"/>
    <xs:element name="AccountingSupplierParty" type="cac:AccountingSupplierPartyType"/>
    <xs:complexType name="AccountingSupplierPartyType">
        <xs:sequence>
            <xs:element name="Party" type="cac:PartyType"/>
        </xs:sequence>
    </xs:complexType>
    <xs:complexType name="PartyType">
        <xs:sequence>
            <xs:element ref="cbc:EndpointID"/>
            <xs:element name="PartyName" type="cac:PartyNameType"/>
        </xs:sequence>
    </xs:complexType>
    <xs:complexType name="PartyNameType">
        <xs:sequence>
            <xs:element ref="cbc:Name"/>
        </xs:sequence>
    </xs:complexType>
</xs:schema>
XSD,
                self::UBL_CAC_NS,
                self::UBL_CBC_NS,
                self::UBL_CAC_NS,
                self::UBL_CBC_NS,
            ),
        );

        $schemaPath = $directory . '/invoice.xsd';

        file_put_contents(
            $schemaPath,
            sprintf(
                <<<'XSD'
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:inv="%s" xmlns:cac="%s" xmlns:cbc="%s" targetNamespace="%s" elementFormDefault="qualified">
    <xs:import namespace="%s" schemaLocation="common-aggregate-components.xsd"/>
    <xs:import namespace="%s" schemaLocation="common-basic-components.xsd"/>
    <xs:element name="Invoice" type="inv:InvoiceType"/>
    <xs:complexType name="InvoiceType">
        <xs:sequence>
            <xs:element ref="cbc:ID"/>
            <xs:element ref="cbc:IssueDate"/>
            <xs:element ref="cac:AccountingSupplierParty"/>
        </xs:sequence>
        <xs:anyAttribute namespace="##other" processContents="lax"/>
    </xs:complexType>
</xs:schema>
XSD,
                self::UBL_INVOICE_NS,
                self::UBL_CAC_NS,
                self::UBL_CBC_NS,
                self::UBL_INVOICE_NS,
                self::UBL_CAC_NS,
                self::UBL_CBC_NS,
            ),
        );

        return $schemaPath;
    }

    private function createTemporaryValidationDirectory(string $suffix): string
    {
        $directory = sys_get_temp_dir() . '/kalle-xml-validation-' . $suffix . '-' . uniqid();

        self::assertTrue(mkdir($directory));

        return $directory;
    }

    /**
     * @param list<string> $files
     */
    private function cleanupValidationDirectory(string $directory, array $files): void
    {
        foreach ($files as $file) {
            @unlink($file);
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
