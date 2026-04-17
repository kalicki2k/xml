<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlSerializer;
use PHPUnit\Framework\TestCase;

final class XmlSerializerTest extends TestCase
{
    public function testItSerializesASimpleDocument(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->text('Clean Code'),
                ),
        );

        $xml = (new XmlSerializer())->serialize($document);

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><catalog><book isbn="9780132350884">Clean Code</book></catalog>',
            $xml,
        );
    }

    public function testItSupportsPrettyAndCompactOutput(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(Xml::element('book'))
                ->child(Xml::element('magazine')),
        )->withoutDeclaration();

        $serializer = new XmlSerializer();

        self::assertSame(
            '<catalog><book/><magazine/></catalog>',
            $serializer->serialize($document, WriterConfig::compact(emitDeclaration: false)),
        );

        self::assertSame(
            "<catalog>\n    <book/>\n    <magazine/>\n</catalog>",
            $serializer->serialize($document, WriterConfig::pretty(emitDeclaration: false)),
        );
    }

    public function testItEscapesTextAndAttributesDuringSerializationOnly(): void
    {
        $document = Xml::document(
            Xml::element('note')
                ->attribute('title', 'Fish & "Chips"' . "\n" . '<today>')
                ->text('Use < & enjoy'),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<note title="Fish &amp; &quot;Chips&quot;&#xA;&lt;today&gt;">Use &lt; &amp; enjoy</note>',
            $xml,
        );
    }

    public function testItSerializesComments(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(Xml::comment('generated file'))
                ->child(Xml::element('book')),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<catalog><!--generated file--><book/></catalog>',
            $xml,
        );
    }

    public function testItSerializesProcessingInstructions(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(Xml::processingInstruction('xml-stylesheet', 'href="catalog.xsl" type="text/xsl"'))
                ->child(Xml::element('book')),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<catalog><?xml-stylesheet href="catalog.xsl" type="text/xsl"?><book/></catalog>',
            $xml,
        );
    }

    public function testItSerializesProcessingInstructionsWithoutData(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(Xml::processingInstruction('refresh')),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<catalog><?refresh?></catalog>',
            $xml,
        );
    }

    public function testItSerializesCDataNodes(): void
    {
        $document = Xml::document(
            Xml::element('script')->child(Xml::cdata('if (a < b && c > d) { return "ok"; }')),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<script><![CDATA[if (a < b && c > d) { return "ok"; }]]></script>',
            $xml,
        );
    }

    public function testItSerializesEdgeCasesAroundTextAndAttributesDeterministically(): void
    {
        $document = Xml::document(
            Xml::element('entry')
                ->attribute('empty', '')
                ->attribute('tabbed', "a\tb")
                ->text('')
                ->text("line\rbreak"),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<entry empty="" tabbed="a&#x9;b">line&#xD;break</entry>',
            $xml,
        );
    }

    public function testItSplitsCDataContentSafelyAroundTheClosingDelimiter(): void
    {
        $document = Xml::document(
            Xml::element('script')->child(Xml::cdata('alpha ]]> beta')),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<script><![CDATA[alpha ]]]]><![CDATA[> beta]]></script>',
            $xml,
        );
    }

    public function testItCanRenderEmptyElementsWithoutSelfClosingSyntax(): void
    {
        $document = Xml::document(Xml::element('catalog'))->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize(
            $document,
            WriterConfig::compact(emitDeclaration: false, selfCloseEmptyElements: false),
        );

        self::assertSame('<catalog></catalog>', $xml);
    }

    public function testPrettyPrintingDoesNotInjectWhitespaceIntoMixedContent(): void
    {
        $document = Xml::document(
            Xml::element('p')
                ->text('Hello ')
                ->child(Xml::element('strong')->text('world'))
                ->text('!'),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::pretty(emitDeclaration: false));

        self::assertSame('<p>Hello <strong>world</strong>!</p>', $xml);
    }

    public function testPrettyPrintingIndentsCommentsProcessingInstructionsAndElements(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->comment('generated file')
                ->processingInstruction('xml-stylesheet', 'href="catalog.xsl" type="text/xsl"')
                ->child(Xml::element('book')),
        )->withoutDeclaration();

        $xml = (new XmlSerializer())->serialize($document, WriterConfig::pretty(emitDeclaration: false));

        self::assertSame(
            "<catalog>\n    <!--generated file-->\n    <?xml-stylesheet href=\"catalog.xsl\" type=\"text/xsl\"?>\n    <book/>\n</catalog>",
            $xml,
        );
    }

    public function testRepeatedSerializationProducesTheSameOutput(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(Xml::element('book')->attribute('isbn', '9780132350884')->text('Clean Code')),
        );

        $serializer = new XmlSerializer();
        $config = WriterConfig::compact();

        self::assertSame(
            $serializer->serialize($document, $config),
            $serializer->serialize($document, $config),
        );
    }
}
