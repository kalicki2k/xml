<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Reader\ReaderDocument;
use Kalle\Xml\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

final class XmlReaderRealisticIntegrationTest extends TestCase
{
    private const FEED_NS = 'urn:feed';
    private const DC_NS = 'urn:dc';
    private const MEDIA_NS = 'urn:media';
    private const XLINK_NS = 'urn:xlink';
    private const UBL_INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const UBL_CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const UBL_CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    public function testItReadsAWriterGeneratedCatalogFixtureWithMixedAttributesAndChildren(): void
    {
        $document = $this->readWriterDocument($this->createCatalogExampleDocument());
        $root = $document->rootElement();
        $books = $root->childElements('book');
        $firstBook = $root->firstChildElement('book');
        $secondBook = $books[1] ?? null;

        self::assertSame('catalog', $root->name());
        self::assertSame('2026-04-17T10:30:00Z', $root->attributeValue('generatedAt'));
        self::assertCount(2, $books);
        self::assertNotNull($firstBook);
        self::assertNotNull($secondBook);
        self::assertSame('9780132350884', $firstBook->attributeValue('isbn'));
        self::assertSame('true', $firstBook->attributeValue('available'));
        self::assertSame('Clean Code', $firstBook->firstChildElement('title')?->text());
        self::assertSame('Robert C. Martin', $firstBook->firstChildElement('author')?->text());
        self::assertSame('EUR', $firstBook->firstChildElement('price')?->attributeValue('currency'));
        self::assertSame('54.90', $secondBook->firstChildElement('price')?->text());
        self::assertSame('false', $secondBook->attributeValue('available'));
    }

    public function testItQueriesAWriterGeneratedCatalogFixtureWithNestedAttributeFilters(): void
    {
        $document = $this->readWriterDocument($this->createCatalogExampleDocument());

        $availableBook = $document->findFirst('/catalog/book[@available="true"]');
        $prices = $document->findAll('/catalog/book/price[@currency="EUR"]');

        self::assertNotNull($availableBook);
        self::assertSame('9780132350884', $availableBook->attributeValue('isbn'));
        self::assertSame('Clean Code', $availableBook->findFirst('./title')?->text());
        self::assertCount(2, $prices);
        self::assertSame('39.90', $prices[0]->text());
        self::assertSame('54.90', $prices[1]->text());
    }

    public function testItReadsAWriterGeneratedConfigFixtureWithNestedSectionsAndTextValues(): void
    {
        $document = $this->readWriterDocument($this->createConfigDocument());
        $root = $document->rootElement();
        $database = $root->firstChildElement();
        $featureFlags = $root->childElements('feature');
        $searchFeature = $root->firstChildElement('feature');

        self::assertSame('config', $root->name());
        self::assertSame('prod', $root->attributeValue('environment'));
        self::assertSame('2026.04', $root->attributeValue('version'));
        self::assertNotNull($database);
        self::assertSame('database', $database->name());
        self::assertSame('pgsql', $database->attributeValue('driver'));
        self::assertSame('db.internal', $database->firstChildElement('host')?->text());
        self::assertSame('5432', $database->firstChildElement('port')?->text());
        self::assertCount(2, $featureFlags);
        self::assertNotNull($searchFeature);
        self::assertSame('search', $searchFeature->attributeValue('name'));
        self::assertSame('true', $searchFeature->attributeValue('enabled'));
        self::assertSame('Global Search', $searchFeature->firstChildElement('label')?->text());
    }

    public function testItQueriesAWriterGeneratedConfigFixtureWithMixedAttributesAndChildElements(): void
    {
        $document = $this->readWriterDocument($this->createConfigDocument());

        $database = $document->findFirst('/config/database[@driver="pgsql" and @primary="true"]');
        $searchFeature = $document->findFirst('/config/feature[@name="search"]');

        self::assertNotNull($database);
        self::assertSame('db.internal', $database->findFirst('./host')?->text());
        self::assertSame('application', $database->findFirst('./schema')?->text());
        self::assertNotNull($searchFeature);
        self::assertSame('Global Search', $searchFeature->findFirst('./label')?->text());
        self::assertCount(2, $document->findAll('/config/feature/label'));
    }

    public function testItReadsAWriterGeneratedDefaultNamespaceFeedFixture(): void
    {
        $document = $this->readWriterDocument($this->createDefaultNamespaceFeedDocument());
        $root = $document->rootElement();
        $entries = $root->childElements(Xml::qname('entry', self::FEED_NS, 'atom'));
        $firstEntry = $root->firstChildElement(Xml::qname('entry', self::FEED_NS, 'atom'));

        self::assertSame('feed', $root->name());
        self::assertSame(self::FEED_NS, $root->namespaceUri());
        self::assertCount(2, $entries);
        self::assertNotNull($firstEntry);
        self::assertSame(
            'https://example.com/products/item-1001',
            $firstEntry->attributeValue(Xml::qname('href', self::XLINK_NS, 'link')),
        );
        self::assertSame(
            'Blue mug',
            $firstEntry->firstChildElement(Xml::qname('title', self::FEED_NS, 'feed'))?->text(),
        );
        self::assertSame(
            'item-1001',
            $firstEntry->firstChildElement(Xml::qname('identifier', self::DC_NS, 'meta'))?->text(),
        );

        $thumbnail = $firstEntry->firstChildElement(Xml::qname('thumbnail', self::MEDIA_NS, 'thumb'));

        self::assertNotNull($thumbnail);
        self::assertSame('320', $thumbnail->attributeValue('width'));
        self::assertSame('180', $thumbnail->attributeValue('height'));
        self::assertSame(
            'https://cdn.example.com/products/item-1001.jpg',
            $thumbnail->attributeValue(Xml::qname('href', self::XLINK_NS, 'xlink')),
        );
    }

    public function testItQueriesAWriterGeneratedDefaultNamespaceFeedFixtureWithAliases(): void
    {
        $document = $this->readWriterDocument($this->createDefaultNamespaceFeedDocument());
        $namespaces = [
            'feed' => self::FEED_NS,
            'dc' => self::DC_NS,
            'media' => self::MEDIA_NS,
            'xlink' => self::XLINK_NS,
        ];

        $entries = $document->findAll('/feed:feed/feed:entry[@xlink:href]', $namespaces);

        self::assertCount(2, $entries);

        $firstEntry = $entries[0];
        $thumbnail = $firstEntry->findFirst('./media:thumbnail[@width="320"]', $namespaces);

        self::assertSame('Blue mug', $firstEntry->findFirst('./feed:title', $namespaces)?->text());
        self::assertSame('item-1001', $firstEntry->findFirst('./dc:identifier', $namespaces)?->text());
        self::assertNotNull($thumbnail);
        self::assertSame('180', $thumbnail->attributeValue('height'));
        self::assertSame(
            'https://cdn.example.com/products/item-1001.jpg',
            $thumbnail->attributeValue(Xml::qname('href', self::XLINK_NS, 'xlink')),
        );
    }

    public function testItReadsAWriterGeneratedPrefixedNamespaceFeedFixture(): void
    {
        $document = $this->readWriterDocument($this->createPrefixedFeedExampleDocument());
        $root = $document->rootElement();
        $entry = $root->firstChildElement(Xml::qname('entry', self::FEED_NS));

        self::assertSame('atom:feed', $root->name());
        self::assertSame('atom', $root->prefix());
        self::assertSame(self::FEED_NS, $root->namespaceUri());
        self::assertCount(2, $root->namespacesInScope());
        self::assertNotNull($entry);
        self::assertSame('atom:entry', $entry->name());
        self::assertSame('Example entry', $entry->firstChildElement(Xml::qname('title', self::FEED_NS))?->text());
        self::assertSame(
            'https://example.com/items/1',
            $entry->attributeValue(Xml::qname('href', self::XLINK_NS, 'link')),
        );
    }

    public function testItSupportsElementScopedNamespaceQueriesOnAWriterGeneratedInvoiceFixture(): void
    {
        $document = $this->readWriterDocument($this->createInvoiceDocument());
        $namespaces = [
            'inv' => self::UBL_INVOICE_NS,
            'cac' => self::UBL_CAC_NS,
            'cbc' => self::UBL_CBC_NS,
        ];

        $supplierParty = $document->findFirst(
            '/inv:Invoice/cac:AccountingSupplierParty/cac:Party',
            $namespaces,
        );

        self::assertNotNull($supplierParty);

        $endpoint = $supplierParty->findFirst('./cbc:EndpointID[@schemeID="0088"]');
        $supplierName = $supplierParty->findFirst('./cac:PartyName/cbc:Name');

        self::assertNotNull($endpoint);
        self::assertSame('0409876543210', $endpoint->text());
        self::assertNotNull($supplierName);
        self::assertSame('Muster Software GmbH', $supplierName->text());
    }

    public function testItReadsAWriterGeneratedInvoiceFixtureAndExtractsRealisticText(): void
    {
        $document = $this->readWriterDocument($this->createInvoiceDocument());
        $root = $document->rootElement();
        $supplierParty = $root
            ->firstChildElement(Xml::qname('AccountingSupplierParty', self::UBL_CAC_NS))
            ?->firstChildElement(Xml::qname('Party', self::UBL_CAC_NS));

        self::assertSame('Invoice', $root->name());
        self::assertSame('de', $root->attributeValue(Xml::qname('lang', QualifiedName::XML_NAMESPACE_URI, 'xml')));
        self::assertSame(
            self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd',
            $root->attributeValue(Xml::qname('schemaLocation', self::XSI_NS, 'schema')),
        );
        self::assertSame(
            'RE-2026-0042',
            $root->firstChildElement(Xml::qname('ID', self::UBL_CBC_NS, 'cbc'))?->text(),
        );
        self::assertSame(
            '2026-04-17',
            $root->firstChildElement(Xml::qname('IssueDate', self::UBL_CBC_NS, 'cbc'))?->text(),
        );
        self::assertNotNull($supplierParty);
        $endpoint = $supplierParty->firstChildElement(Xml::qname('EndpointID', self::UBL_CBC_NS, 'cbc'));
        $partyName = $supplierParty->firstChildElement(Xml::qname('PartyName', self::UBL_CAC_NS, 'cac'));
        $supplierName = $partyName?->firstChildElement(Xml::qname('Name', self::UBL_CBC_NS, 'cbc'));

        self::assertNotNull($endpoint);
        self::assertSame(
            '0409876543210',
            $endpoint->text(),
        );
        self::assertSame('0088', $endpoint->attributeValue('schemeID'));
        self::assertNotNull($partyName);
        self::assertNotNull($supplierName);
        self::assertSame('Muster Software GmbH', $supplierName->text());
    }

    private function readWriterDocument(XmlDocument $document): ReaderDocument
    {
        return XmlReader::fromString($document->toString());
    }

    private function createCatalogExampleDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->attribute('available', true)
                        ->child(Xml::element('title')->text('Clean Code'))
                        ->child(Xml::element('author')->text('Robert C. Martin'))
                        ->child(Xml::element('price')->attribute('currency', 'EUR')->text('39.90')),
                )
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780321125217')
                        ->attribute('available', false)
                        ->child(Xml::element('title')->text('Domain-Driven Design'))
                        ->child(Xml::element('author')->text('Eric Evans'))
                        ->child(Xml::element('price')->attribute('currency', 'EUR')->text('54.90')),
                ),
        );
    }

    private function createConfigDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element('config')
                ->attribute('environment', 'prod')
                ->attribute('version', '2026.04')
                ->child(
                    Xml::element('database')
                        ->attribute('driver', 'pgsql')
                        ->attribute('primary', true)
                        ->child(Xml::element('host')->text('db.internal'))
                        ->child(Xml::element('port')->text('5432'))
                        ->child(Xml::element('schema')->text('application')),
                )
                ->child(
                    Xml::element('feature')
                        ->attribute('name', 'search')
                        ->attribute('enabled', true)
                        ->child(Xml::element('label')->text('Global Search')),
                )
                ->child(
                    Xml::element('feature')
                        ->attribute('name', 'recommendations')
                        ->attribute('enabled', false)
                        ->child(Xml::element('label')->text('Recommendations')),
                ),
        );
    }

    private function createDefaultNamespaceFeedDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element(Xml::qname('feed', self::FEED_NS))
                ->declareDefaultNamespace(self::FEED_NS)
                ->declareNamespace('dc', self::DC_NS)
                ->declareNamespace('media', self::MEDIA_NS)
                ->declareNamespace('xlink', self::XLINK_NS)
                ->child(
                    Xml::element(Xml::qname('entry', self::FEED_NS))
                        ->attribute(Xml::qname('href', self::XLINK_NS, 'xlink'), 'https://example.com/products/item-1001')
                        ->child(Xml::element(Xml::qname('title', self::FEED_NS))->text('Blue mug'))
                        ->child(Xml::element(Xml::qname('identifier', self::DC_NS, 'dc'))->text('item-1001'))
                        ->child(
                            Xml::element(Xml::qname('thumbnail', self::MEDIA_NS, 'media'))
                                ->attribute(Xml::qname('href', self::XLINK_NS, 'xlink'), 'https://cdn.example.com/products/item-1001.jpg')
                                ->attribute('width', 320)
                                ->attribute('height', 180),
                        ),
                )
                ->child(
                    Xml::element(Xml::qname('entry', self::FEED_NS))
                        ->attribute(Xml::qname('href', self::XLINK_NS, 'xlink'), 'https://example.com/products/item-1002')
                        ->child(Xml::element(Xml::qname('title', self::FEED_NS))->text('Notebook set'))
                        ->child(Xml::element(Xml::qname('identifier', self::DC_NS, 'dc'))->text('item-1002')),
                ),
        );
    }

    private function createPrefixedFeedExampleDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element(Xml::qname('feed', self::FEED_NS, 'atom'))
                ->declareNamespace('atom', self::FEED_NS)
                ->declareNamespace('xlink', self::XLINK_NS)
                ->child(
                    Xml::element(Xml::qname('entry', self::FEED_NS, 'atom'))
                        ->attribute(Xml::qname('href', self::XLINK_NS, 'xlink'), 'https://example.com/items/1')
                        ->child(Xml::element(Xml::qname('title', self::FEED_NS, 'atom'))->text('Example entry')),
                ),
        )->withoutDeclaration();
    }

    private function createInvoiceDocument(): XmlDocument
    {
        return Xml::document(
            Xml::element(Xml::qname('Invoice', self::UBL_INVOICE_NS))
                ->declareDefaultNamespace(self::UBL_INVOICE_NS)
                ->declareNamespace('cac', self::UBL_CAC_NS)
                ->declareNamespace('cbc', self::UBL_CBC_NS)
                ->declareNamespace('xsi', self::XSI_NS)
                ->attribute(Xml::qname('lang', QualifiedName::XML_NAMESPACE_URI, 'xml'), 'de')
                ->attribute(
                    Xml::qname('schemaLocation', self::XSI_NS, 'xsi'),
                    self::UBL_INVOICE_NS . ' UBL-Invoice-2.1.xsd',
                )
                ->child(Xml::element(Xml::qname('ID', self::UBL_CBC_NS, 'cbc'))->text('RE-2026-0042'))
                ->child(Xml::element(Xml::qname('IssueDate', self::UBL_CBC_NS, 'cbc'))->text('2026-04-17'))
                ->child(
                    Xml::element(Xml::qname('AccountingSupplierParty', self::UBL_CAC_NS, 'cac'))
                        ->child(
                            Xml::element(Xml::qname('Party', self::UBL_CAC_NS, 'cac'))
                                ->child(
                                    Xml::element(Xml::qname('EndpointID', self::UBL_CBC_NS, 'cbc'))
                                        ->attribute('schemeID', '0088')
                                        ->text('0409876543210'),
                                )
                                ->child(
                                    Xml::element(Xml::qname('PartyName', self::UBL_CAC_NS, 'cac'))
                                        ->child(Xml::element(Xml::qname('Name', self::UBL_CBC_NS, 'cbc'))->text('Muster Software GmbH')),
                                ),
                        ),
                ),
        );
    }
}
