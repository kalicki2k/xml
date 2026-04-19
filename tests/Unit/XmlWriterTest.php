<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class XmlWriterTest extends TestCase
{
    public function testItSerializesASimpleDocument(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->text('Clean Code'),
                ),
        );

        $xml = XmlWriter::toString($document);

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><catalog><book isbn="9780132350884">Clean Code</book></catalog>',
            $xml,
        );
    }

    public function testItSupportsPrettyAndCompactOutput(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->child(XmlBuilder::element('book'))
                ->child(XmlBuilder::element('magazine')),
        )->withoutDeclaration();

        self::assertSame(
            '<catalog><book/><magazine/></catalog>',
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
        );

        self::assertSame(
            "<catalog>\n    <book/>\n    <magazine/>\n</catalog>",
            XmlWriter::toString($document, WriterConfig::pretty(emitDeclaration: false)),
        );
    }

    public function testItEscapesTextAndAttributesDuringSerializationOnly(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('note')
                ->attribute('title', 'Fish & "Chips"' . "\n" . '<today>')
                ->text('Use < & enjoy'),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<note title="Fish &amp; &quot;Chips&quot;&#xA;&lt;today&gt;">Use &lt; &amp; enjoy</note>',
            $xml,
        );
    }

    public function testItSerializesComments(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->child(XmlBuilder::comment('generated file'))
                ->child(XmlBuilder::element('book')),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<catalog><!--generated file--><book/></catalog>',
            $xml,
        );
    }

    public function testItSerializesProcessingInstructions(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->child(XmlBuilder::processingInstruction('xml-stylesheet', 'href="catalog.xsl" type="text/xsl"'))
                ->child(XmlBuilder::element('book')),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<catalog><?xml-stylesheet href="catalog.xsl" type="text/xsl"?><book/></catalog>',
            $xml,
        );
    }

    public function testItSerializesProcessingInstructionsWithoutData(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->child(XmlBuilder::processingInstruction('refresh')),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<catalog><?refresh?></catalog>',
            $xml,
        );
    }

    public function testItSerializesCDataNodes(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('script')->child(XmlBuilder::cdata('if (a < b && c > d) { return "ok"; }')),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<script><![CDATA[if (a < b && c > d) { return "ok"; }]]></script>',
            $xml,
        );
    }

    public function testItSerializesEdgeCasesAroundTextAndAttributesDeterministically(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('entry')
                ->attribute('empty', '')
                ->attribute('tabbed', "a\tb")
                ->text('')
                ->text("line\rbreak"),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<entry empty="" tabbed="a&#x9;b">line&#xD;break</entry>',
            $xml,
        );
    }

    public function testItSplitsCDataContentSafelyAroundTheClosingDelimiter(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('script')->child(XmlBuilder::cdata('alpha ]]> beta')),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false));

        self::assertSame(
            '<script><![CDATA[alpha ]]]]><![CDATA[> beta]]></script>',
            $xml,
        );
    }

    public function testItCanRenderEmptyElementsWithoutSelfClosingSyntax(): void
    {
        $document = XmlBuilder::document(XmlBuilder::element('catalog'))->withoutDeclaration();

        $xml = XmlWriter::toString(
            $document,
            WriterConfig::compact(
                emitDeclaration: false,
                selfCloseEmptyElements: false,
            ),
        );

        self::assertSame('<catalog></catalog>', $xml);
    }

    public function testPrettyPrintingDoesNotInjectWhitespaceIntoMixedContent(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('p')
                ->text('Hello ')
                ->child(XmlBuilder::element('strong')->text('world'))
                ->text('!'),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::pretty(emitDeclaration: false));

        self::assertSame('<p>Hello <strong>world</strong>!</p>', $xml);
    }

    public function testPrettyPrintingIndentsCommentsProcessingInstructionsAndElements(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->comment('generated file')
                ->processingInstruction('xml-stylesheet', 'href="catalog.xsl" type="text/xsl"')
                ->child(XmlBuilder::element('book')),
        )->withoutDeclaration();

        $xml = XmlWriter::toString($document, WriterConfig::pretty(emitDeclaration: false));

        self::assertSame(
            "<catalog>\n    <!--generated file-->\n    <?xml-stylesheet href=\"catalog.xsl\" type=\"text/xsl\"?>\n    <book/>\n</catalog>",
            $xml,
        );
    }

    public function testRepeatedSerializationProducesTheSameOutput(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(XmlBuilder::element('book')->attribute('isbn', '9780132350884')->text('Clean Code')),
        );

        $config = WriterConfig::compact();

        self::assertSame(
            XmlWriter::toString($document, $config),
            XmlWriter::toString($document, $config),
        );
    }

    public function testItExposesOnlyWholeDocumentSerializationEntryPoints(): void
    {
        $publicMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(XmlWriter::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        self::assertContains('toString', $publicMethods);
        self::assertContains('toFile', $publicMethods);
        self::assertContains('toStream', $publicMethods);
        self::assertNotContains('forFile', $publicMethods);
        self::assertNotContains('forStream', $publicMethods);
        self::assertNotContains('writeElement', $publicMethods);
    }
}
