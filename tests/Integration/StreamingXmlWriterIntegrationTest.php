<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_get_contents;
use function fopen;
use function is_resource;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class StreamingXmlWriterIntegrationTest extends TestCase
{
    public function testCompactStreamingWriterMatchesDocumentSerialization(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->text('Clean Code'),
                ),
        );

        $actual = $this->streamToString(WriterConfig::compact(), static function (StreamingXmlWriter $writer): void {
            $writer
                ->startDocument()
                ->startElement('catalog')
                ->writeAttribute('generatedAt', '2026-04-17T10:30:00Z')
                ->startElement('book')
                ->writeAttribute('isbn', '9780132350884')
                ->writeText('Clean Code')
                ->endElement()
                ->endElement();
        });

        self::assertSame(XmlWriter::toString($document, WriterConfig::compact()), $actual);
    }

    public function testNamespacedStreamingWriterMatchesDocumentOutput(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('xlink', 'urn:xlink')
                ->child(
                    XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                        ->attribute(
                            XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                            'https://example.com/items/1',
                        ),
                ),
        )->withoutDeclaration();

        $actual = $this->streamToString(
            WriterConfig::compact(emitDeclaration: false),
            static function (StreamingXmlWriter $writer): void {
                $writer
                    ->startElement(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                    ->declareNamespace('xlink', 'urn:xlink')
                    ->startElement(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                    ->writeAttribute(
                        XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                        'https://example.com/items/1',
                    )
                    ->endElement()
                    ->endElement();
            },
        );

        self::assertSame(
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
            $actual,
        );
    }

    public function testStreamingWriterSupportsDefaultNamespacesIncrementally(): void
    {
        $actual = $this->streamToString(
            WriterConfig::compact(emitDeclaration: false),
            static function (StreamingXmlWriter $writer): void {
                $writer
                    ->startElement(XmlBuilder::qname('catalog', 'urn:catalog'))
                    ->startElement(XmlBuilder::qname('book', 'urn:catalog'))
                    ->writeText('DDD')
                    ->endElement()
                    ->endElement();
            },
        );

        self::assertSame(
            '<catalog xmlns="urn:catalog"><book>DDD</book></catalog>',
            $actual,
        );
    }

    public function testStreamingWriterCanWriteNamespacedOutputDirectlyToAFilePath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-stream-');

        self::assertNotFalse($path);

        try {
            $writer = StreamingXmlWriter::forFile(
                $path,
                WriterConfig::compact(emitDeclaration: false),
            );

            $writer
                ->startElement(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('xlink', 'urn:xlink')
                ->startElement(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                ->writeAttribute(
                    XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                    'https://example.com/items/1',
                )
                ->endElement()
                ->endElement()
                ->finish();

            self::assertSame(
                '<atom:feed xmlns:atom="urn:feed" xmlns:xlink="urn:xlink"><atom:entry xlink:href="https://example.com/items/1"/></atom:feed>',
                (string) file_get_contents($path),
            );
        } finally {
            @unlink($path);
        }
    }

    public function testPrettyPrintedStreamingWriterMatchesDocumentOutputForStructuralContent(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->comment('generated file')
                ->processingInstruction('cache-control', 'ttl="300"')
                ->child(XmlBuilder::element('book'))
                ->child(XmlBuilder::element('magazine')),
        )->withoutDeclaration();

        $actual = $this->streamToString(
            WriterConfig::pretty(emitDeclaration: false),
            static function (StreamingXmlWriter $writer): void {
                $writer
                    ->startElement('catalog')
                    ->writeComment('generated file')
                    ->writeProcessingInstruction('cache-control', 'ttl="300"')
                    ->startElement('book')
                    ->endElement()
                    ->startElement('magazine')
                    ->endElement()
                    ->endElement();
            },
        );

        self::assertSame(
            XmlWriter::toString($document, WriterConfig::pretty(emitDeclaration: false)),
            $actual,
        );
    }

    public function testItCanWritePrebuiltElementSubtreesWhileStreaming(): void
    {
        $document = XmlBuilder::document(
            XmlBuilder::element('catalog')
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->child(XmlBuilder::element('title')->text('Clean Code')),
                )
                ->child(
                    XmlBuilder::element('book')
                        ->attribute('isbn', '9780321125217')
                        ->child(XmlBuilder::element('title')->text('Domain-Driven Design')),
                ),
        )->withoutDeclaration();

        $actual = $this->streamToString(
            WriterConfig::compact(emitDeclaration: false),
            static function (StreamingXmlWriter $writer): void {
                $writer
                    ->startElement('catalog')
                    ->writeElement(
                        XmlBuilder::element('book')
                            ->attribute('isbn', '9780132350884')
                            ->child(XmlBuilder::element('title')->text('Clean Code')),
                    )
                    ->writeElement(
                        XmlBuilder::element('book')
                            ->attribute('isbn', '9780321125217')
                            ->child(XmlBuilder::element('title')->text('Domain-Driven Design')),
                    )
                    ->endElement();
            },
        );

        self::assertSame(
            XmlWriter::toString($document, WriterConfig::compact(emitDeclaration: false)),
            $actual,
        );
    }

    public function testItWritesDirectlyToAProvidedStreamResource(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        try {
            $writer = StreamingXmlWriter::forStream(
                $stream,
                WriterConfig::compact(emitDeclaration: false),
            );

            $writer
                ->startElement('catalog')
                ->startElement('book')
                ->endElement()
                ->endElement()
                ->finish();

            rewind($stream);

            self::assertSame('<catalog><book/></catalog>', (string) stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testPrettyPrintedStreamingRejectsLateMixedContentTransitions(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        try {
            $writer = StreamingXmlWriter::forStream(
                $stream,
                WriterConfig::pretty(emitDeclaration: false),
            );

            $writer
                ->startElement('p')
                ->startElement('strong')
                ->writeText('Hello')
                ->endElement();

            $this->expectException(SerializationException::class);
            $this->expectExceptionMessage('text-like content');

            $writer->writeText(' world');
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param callable(StreamingXmlWriter): void $write
     */
    private function streamToString(WriterConfig $config, callable $write): string
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        try {
            $writer = StreamingXmlWriter::forStream($stream, $config);
            $write($writer);
            $writer->finish();

            rewind($stream);

            return (string) stream_get_contents($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
