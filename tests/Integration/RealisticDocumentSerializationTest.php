<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

final class RealisticDocumentSerializationTest extends TestCase
{
    public function testItSerializesARealisticCatalogDocumentDeterministically(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->attribute('locale', 'de-DE')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('sku', 'bk-001')
                        ->attribute('available', true)
                        ->child(XmlBuilder::element('title')->text('Domain-Driven Design'))
                        ->child(XmlBuilder::element('author')->text('Eric Evans'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('54.90'))
                        ->child(XmlBuilder::element('description')->text('Blue Book & companion material')),
                )
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('sku', 'bk-002')
                        ->attribute('available', false)
                        ->child(XmlBuilder::element('title')->text('Patterns of Enterprise Application Architecture'))
                        ->child(XmlBuilder::element('author')->text('Martin Fowler'))
                        ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('49.00')),
                ),
        );

        $expected = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<catalog generatedAt="2026-04-17T10:30:00Z" locale="de-DE">
    <book sku="bk-001" available="true">
        <title>Domain-Driven Design</title>
        <author>Eric Evans</author>
        <price currency="EUR">54.90</price>
        <description>Blue Book &amp; companion material</description>
    </book>
    <book sku="bk-002" available="false">
        <title>Patterns of Enterprise Application Architecture</title>
        <author>Martin Fowler</author>
        <price currency="EUR">49.00</price>
    </book>
</catalog>
XML;

        $prettyConfig = WriterConfig::pretty();

        self::assertSame($expected, XmlWriter::toString($document, $prettyConfig));
        self::assertSame($expected, XmlWriter::toString($document, $prettyConfig));
    }

    public function testFluentChainingProducesExpectedCompactOutput(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('invoice')
                ->attribute('number', 'INV-2026-0001')
                ->attribute('paid', false)
                ->child(
                    XmlBuilder::element('customer')
                        ->child(XmlBuilder::element('name')->text('Jane Doe'))
                        ->child(XmlBuilder::element('email')->text('jane@example.com')),
                )
                ->child(
                    XmlBuilder::element('totals')
                        ->child(XmlBuilder::element('net')->attribute('currency', 'EUR')->text('99.00'))
                        ->child(XmlBuilder::element('tax')->attribute('currency', 'EUR')->text('18.81'))
                        ->child(XmlBuilder::element('gross')->attribute('currency', 'EUR')->text('117.81')),
                ),
        )->withoutDeclaration();

        self::assertSame(
            '<invoice number="INV-2026-0001" paid="false"><customer><name>Jane Doe</name><email>jane@example.com</email></customer><totals><net currency="EUR">99.00</net><tax currency="EUR">18.81</tax><gross currency="EUR">117.81</gross></totals></invoice>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );
    }
}
