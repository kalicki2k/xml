<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

use function fclose;
use function file_get_contents;
use function fopen;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class StreamingXmlWriterIntegrationTest extends TestCase
{
    public function testCompactStreamingWriterMatchesDocumentSerialization(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->attribute('generatedAt', '2026-04-17T10:30:00Z')
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->text('Clean Code'),
                ),
        );

        $writer = StreamingXmlWriter::forString(WriterConfig::compact());

        $writer
            ->startDocument()
            ->startElement('catalog')
            ->writeAttribute('generatedAt', '2026-04-17T10:30:00Z')
            ->startElement('book')
            ->writeAttribute('isbn', '9780132350884')
            ->writeText('Clean Code')
            ->endElement()
            ->endElement()
            ->finish();

        self::assertSame($document->toString(WriterConfig::compact()), $writer->toString());
    }

    public function testNamespacedStreamingWriterMatchesDocumentOutput(): void
    {
        $document = Xml::document(
            Xml::element(Xml::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('xlink', 'urn:xlink')
                ->child(
                    Xml::element(Xml::qname('entry', 'urn:feed', 'atom'))
                        ->attribute(
                            Xml::qname('href', 'urn:xlink', 'xlink'),
                            'https://example.com/items/1',
                        ),
                ),
        )->withoutDeclaration();

        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement(Xml::qname('feed', 'urn:feed', 'atom'))
            ->declareNamespace('xlink', 'urn:xlink')
            ->startElement(Xml::qname('entry', 'urn:feed', 'atom'))
            ->writeAttribute(
                Xml::qname('href', 'urn:xlink', 'xlink'),
                'https://example.com/items/1',
            )
            ->endElement()
            ->endElement()
            ->finish();

        self::assertSame(
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
            $writer->toString(),
        );
    }

    public function testStreamingWriterSupportsDefaultNamespacesIncrementally(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement(Xml::qname('catalog', 'urn:catalog'))
            ->startElement(Xml::qname('book', 'urn:catalog'))
            ->writeText('DDD')
            ->endElement()
            ->endElement()
            ->finish();

        self::assertSame(
            '<catalog xmlns="urn:catalog"><book>DDD</book></catalog>',
            $writer->toString(),
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
                ->startElement(Xml::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('xlink', 'urn:xlink')
                ->startElement(Xml::qname('entry', 'urn:feed', 'atom'))
                ->writeAttribute(
                    Xml::qname('href', 'urn:xlink', 'xlink'),
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
        $document = Xml::document(
            Xml::element('catalog')
                ->comment('generated file')
                ->processingInstruction('cache-control', 'ttl="300"')
                ->child(Xml::element('book'))
                ->child(Xml::element('magazine')),
        )->withoutDeclaration();

        $writer = StreamingXmlWriter::forString(WriterConfig::pretty(emitDeclaration: false));

        $writer
            ->startElement('catalog')
            ->writeComment('generated file')
            ->writeProcessingInstruction('cache-control', 'ttl="300"')
            ->startElement('book')
            ->endElement()
            ->startElement('magazine')
            ->endElement()
            ->endElement()
            ->finish();

        self::assertSame(
            $document->toString(WriterConfig::pretty(emitDeclaration: false)),
            $writer->toString(),
        );
    }

    public function testItCanWritePrebuiltElementSubtreesWhileStreaming(): void
    {
        $document = Xml::document(
            Xml::element('catalog')
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780132350884')
                        ->child(Xml::element('title')->text('Clean Code')),
                )
                ->child(
                    Xml::element('book')
                        ->attribute('isbn', '9780321125217')
                        ->child(Xml::element('title')->text('Domain-Driven Design')),
                ),
        )->withoutDeclaration();

        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement('catalog')
            ->writeElement(
                Xml::element('book')
                    ->attribute('isbn', '9780132350884')
                    ->child(Xml::element('title')->text('Clean Code')),
            )
            ->writeElement(
                Xml::element('book')
                    ->attribute('isbn', '9780321125217')
                    ->child(Xml::element('title')->text('Domain-Driven Design')),
            )
            ->endElement()
            ->finish();

        self::assertSame(
            $document->toString(WriterConfig::compact(emitDeclaration: false)),
            $writer->toString(),
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
        $writer = StreamingXmlWriter::forString(WriterConfig::pretty(emitDeclaration: false));

        $writer
            ->startElement('p')
            ->startElement('strong')
            ->writeText('Hello')
            ->endElement();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('text-like content');

        $writer->writeText(' world');
    }

    public function testItRejectsReturningAStringFromANonStringTarget(): void
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
                ->endElement()
                ->finish();

            $this->expectException(SerializationException::class);
            $this->expectExceptionMessage('StreamingXmlWriter::forString()');

            $writer->toString();
        } finally {
            fclose($stream);
        }
    }
}
