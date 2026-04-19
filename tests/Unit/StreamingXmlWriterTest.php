<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Exception\InvalidNamespaceDeclarationException;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

use function fclose;
use function fopen;
use function is_resource;
use function rewind;
use function stream_get_contents;

final class StreamingXmlWriterTest extends TestCase
{
    public function testItWritesBasicElementTextAndDeclarationOutput(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact());

        try {
            $writer
                ->startDocument()
                ->startElement('catalog')
                ->startElement('book')
                ->writeAttribute('isbn', '9780132350884')
                ->writeText('Clean Code')
                ->endElement()
                ->endElement()
                ->finish();

            self::assertSame(
                '<?xml version="1.0" encoding="UTF-8"?><catalog><book isbn="9780132350884">Clean Code</book></catalog>',
                $this->readBuffer($stream),
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItWritesAllSupportedNodeTypesWithinAnOpenElement(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact(emitDeclaration: false));

        try {
            $writer
                ->startElement('payload')
                ->writeAttribute('kind', 'demo')
                ->writeText('alpha')
                ->writeCdata(' <beta> ')
                ->writeComment('note')
                ->writeProcessingInstruction('cache', 'ttl="300"')
                ->endElement()
                ->finish();

            self::assertSame(
                '<payload kind="demo">alpha<![CDATA[ <beta> ]]><!--note--><?cache ttl="300"?></payload>',
                $this->readBuffer($stream),
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItAutoDeclaresNamespacesForStreamingElementsAndAttributes(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact(emitDeclaration: false));

        try {
            $writer
                ->startElement(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                ->startElement(XmlBuilder::qname('entry', 'urn:feed', 'atom'))
                ->writeAttribute(
                    XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                    'https://example.com/items/1',
                )
                ->endElement()
                ->endElement()
                ->finish();

            self::assertSame(
                '<atom:feed xmlns:atom="urn:feed"><atom:entry xmlns:xlink="urn:xlink" xlink:href="https://example.com/items/1"/></atom:feed>',
                $this->readBuffer($stream),
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItAppliesTheDefaultNamespaceToElementsButNotToAttributes(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact(emitDeclaration: false));

        try {
            $writer
                ->startElement(XmlBuilder::qname('link', 'urn:atom'))
                ->writeAttribute('rel', 'self')
                ->writeAttribute(
                    XmlBuilder::qname('href', 'urn:xlink', 'xlink'),
                    'https://example.com/items/1',
                )
                ->endElement()
                ->finish();

            self::assertSame(
                '<link xmlns="urn:atom" xmlns:xlink="urn:xlink" rel="self" xlink:href="https://example.com/items/1"/>',
                $this->readBuffer($stream),
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItRejectsConflictingNamespaceDeclarationsDuringStreaming(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact(emitDeclaration: false));

        try {
            $writer
                ->startElement(XmlBuilder::qname('feed', 'urn:feed', 'atom'))
                ->declareNamespace('atom', 'urn:other-feed');

            $this->expectException(InvalidNamespaceDeclarationException::class);
            $this->expectExceptionMessage('uses prefix "atom"');

            $writer->endElement();
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItRejectsAttributesWithoutAnOpenElement(): void
    {
        [$stream, $writer] = $this->openBufferWriter();

        try {
            $this->expectException(SerializationException::class);
            $this->expectExceptionMessage('Cannot write attribute "id" when no element is open.');

            $writer->writeAttribute('id', '123');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItRejectsEndingAnElementWhenNoneIsOpen(): void
    {
        [$stream, $writer] = $this->openBufferWriter();

        try {
            $this->expectException(SerializationException::class);
            $this->expectExceptionMessage('Cannot end the current element when no element is open.');

            $writer->endElement();
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItRejectsAttributesAfterElementContentHasStarted(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact(emitDeclaration: false));

        try {
            $writer
                ->startElement('book')
                ->writeText('Clean Code');

            $this->expectException(SerializationException::class);
            $this->expectExceptionMessage('Cannot add attribute "isbn" after writing content for element "book".');

            $writer->writeAttribute('isbn', '9780132350884');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItRejectsWritingAnotherRootElementAfterTheFirstOneHasBeenClosed(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact(emitDeclaration: false));

        try {
            $writer
                ->startElement('catalog')
                ->endElement();

            $this->expectException(SerializationException::class);
            $this->expectExceptionMessage('document root element');

            $writer->startElement('another-root');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItRejectsWritingAfterFinish(): void
    {
        [$stream, $writer] = $this->openBufferWriter(WriterConfig::compact(emitDeclaration: false));

        try {
            $writer
                ->startElement('catalog')
                ->endElement()
                ->finish();

            $this->expectException(SerializationException::class);
            $this->expectExceptionMessage('finished streaming XML writer');

            $writer->writeComment('late');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function testItCanCloseTheProvidedStreamWhenRequested(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        $writer = StreamingXmlWriter::forStream(
            $stream,
            WriterConfig::compact(emitDeclaration: false),
            closeOnFinish: true,
        );

        $writer
            ->startElement('catalog')
            ->endElement()
            ->finish();

        self::assertFalse(is_resource($stream));
    }

    public function testItDoesNotExposeBufferedOrWholeDocumentConveniences(): void
    {
        $publicMethods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(StreamingXmlWriter::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        self::assertNotContains('forString', $publicMethods);
        self::assertNotContains('toString', $publicMethods);
        self::assertNotContains('writeDocument', $publicMethods);
    }

    /**
     * @return array{0: resource, 1: StreamingXmlWriter}
     */
    private function openBufferWriter(?WriterConfig $config = null): array
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        return [
            $stream,
            StreamingXmlWriter::forStream($stream, $config ?? WriterConfig::compact()),
        ];
    }

    /**
     * @param resource $stream
     */
    private function readBuffer($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
