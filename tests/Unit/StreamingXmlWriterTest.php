<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Exception\InvalidNamespaceDeclarationException;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

final class StreamingXmlWriterTest extends TestCase
{
    public function testItWritesBasicElementTextAndDeclarationOutput(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact());

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
            $writer->toString(),
        );
    }

    public function testItWritesAllSupportedNodeTypesWithinAnOpenElement(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

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
            $writer->toString(),
        );
    }

    public function testItAutoDeclaresNamespacesForStreamingElementsAndAttributes(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement(Xml::qname('feed', 'urn:feed', 'atom'))
            ->startElement(Xml::qname('entry', 'urn:feed', 'atom'))
            ->writeAttribute(
                Xml::qname('href', 'urn:xlink', 'xlink'),
                'https://example.com/items/1',
            )
            ->endElement()
            ->endElement()
            ->finish();

        self::assertSame(
            '<atom:feed xmlns:atom="urn:feed"><atom:entry xmlns:xlink="urn:xlink" xlink:href="https://example.com/items/1"/></atom:feed>',
            $writer->toString(),
        );
    }

    public function testItAppliesTheDefaultNamespaceToElementsButNotToAttributes(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement(Xml::qname('link', 'urn:atom'))
            ->writeAttribute('rel', 'self')
            ->writeAttribute(
                Xml::qname('href', 'urn:xlink', 'xlink'),
                'https://example.com/items/1',
            )
            ->endElement()
            ->finish();

        self::assertSame(
            '<link xmlns="urn:atom" xmlns:xlink="urn:xlink" rel="self" xlink:href="https://example.com/items/1"/>',
            $writer->toString(),
        );
    }

    public function testItRejectsConflictingNamespaceDeclarationsDuringStreaming(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement(Xml::qname('feed', 'urn:feed', 'atom'))
            ->declareNamespace('atom', 'urn:other-feed');

        $this->expectException(InvalidNamespaceDeclarationException::class);
        $this->expectExceptionMessage('uses prefix "atom"');

        $writer->endElement();
    }

    public function testItRejectsAttributesWithoutAnOpenElement(): void
    {
        $writer = StreamingXmlWriter::forString();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Cannot write attribute "id" when no element is open.');

        $writer->writeAttribute('id', '123');
    }

    public function testItRejectsEndingAnElementWhenNoneIsOpen(): void
    {
        $writer = StreamingXmlWriter::forString();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Cannot end the current element when no element is open.');

        $writer->endElement();
    }

    public function testItRejectsAttributesAfterElementContentHasStarted(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement('book')
            ->writeText('Clean Code');

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Cannot add attribute "isbn" after writing content for element "book".');

        $writer->writeAttribute('isbn', '9780132350884');
    }

    public function testItRejectsWritingAnotherRootElementAfterTheFirstOneHasBeenClosed(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement('catalog')
            ->endElement();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('document root element');

        $writer->startElement('another-root');
    }

    public function testItRejectsWritingAfterFinish(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer
            ->startElement('catalog')
            ->endElement()
            ->finish();

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('finished streaming XML writer');

        $writer->writeComment('late');
    }

    public function testItRejectsReturningAStringBeforeFinish(): void
    {
        $writer = StreamingXmlWriter::forString(WriterConfig::compact(emitDeclaration: false));

        $writer->startElement('catalog');

        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('finish()');

        $writer->toString();
    }
}
