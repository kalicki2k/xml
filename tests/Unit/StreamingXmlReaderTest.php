<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Exception\FileReadException;
use Kalle\Xml\Exception\StreamingReaderException;
use Kalle\Xml\Exception\StreamReadException;
use Kalle\Xml\Reader\StreamingXmlReader;
use PHPUnit\Framework\TestCase;

use function fclose;
use function fopen;
use function fwrite;
use function rewind;
use function sys_get_temp_dir;

final class StreamingXmlReaderTest extends TestCase
{
    public function testItRejectsAnEmptyFilePath(): void
    {
        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('StreamingXmlReader::fromFile() requires a non-empty path.');

        StreamingXmlReader::fromFile('');
    }

    public function testItRejectsMissingFilesWithAFileSpecificException(): void
    {
        $path = sys_get_temp_dir() . '/kalle-xml-missing-streaming-reader.xml';

        $this->expectException(FileReadException::class);
        $this->expectExceptionMessage('StreamingXmlReader::fromFile() could not read XML file');
        $this->expectExceptionMessage('No such file or directory');

        StreamingXmlReader::fromFile($path);
    }

    public function testItRejectsNonStreamResources(): void
    {
        $this->expectException(StreamReadException::class);
        $this->expectExceptionMessage('StreamingXmlReader::fromStream() requires a readable stream resource');

        StreamingXmlReader::fromStream('not-a-stream');
    }

    public function testItRejectsNonReadableStreamResources(): void
    {
        $stream = fopen('php://output', 'wb');

        self::assertIsResource($stream);

        try {
            $this->expectException(StreamReadException::class);
            $this->expectExceptionMessage('StreamingXmlReader::fromStream() requires a readable stream resource');
            $this->expectExceptionMessage('is not readable');

            StreamingXmlReader::fromStream($stream);
        } finally {
            fclose($stream);
        }
    }

    public function testItRejectsReadCallsAfterClose(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite($stream, '<catalog/>'));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $reader->close();

            $this->expectException(StreamingReaderException::class);
            $this->expectExceptionMessage('StreamingXmlReader::read() cannot be used after the reader was closed.');

            $reader->read();
        } finally {
            fclose($stream);
        }
    }

    public function testItRejectsSubtreeExtractionWhenTheCursorIsNotOnAStartElement(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite($stream, '<catalog><!--note--><book/></catalog>'));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);

            self::assertTrue($reader->read());
            self::assertTrue($reader->read());

            $this->expectException(StreamingReaderException::class);
            $this->expectExceptionMessage(
                'StreamingXmlReader::extractElementXml() requires the cursor to be positioned on a start element.',
            );

            $reader->extractElementXml();
        } finally {
            fclose($stream);
        }
    }

    public function testReadElementsSkipsNestedMatchingDescendantsInsideEachYieldedRecord(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite($stream, '<catalog><book id="1"><book id="nested"/></book><book id="2"/></catalog>'));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);
            $ids = [];

            foreach ($reader->readElements('book') as $bookRecord) {
                $ids[] = $bookRecord->attributeValue('id');
            }

            self::assertSame(['1', '2'], $ids);
        } finally {
            fclose($stream);
        }
    }

    public function testReadElementsKeepsSkippingTheYieldedSubtreeWhenIterationStopsEarly(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);
        self::assertNotFalse(fwrite($stream, '<catalog><book id="1"><book id="nested"/></book><book id="2"/></catalog>'));
        rewind($stream);

        try {
            $reader = StreamingXmlReader::fromStream($stream);

            foreach ($reader->readElements('book') as $bookRecord) {
                self::assertSame('1', $bookRecord->attributeValue('id'));

                break;
            }

            $remainingIds = [];

            foreach ($reader->readElements('book') as $bookRecord) {
                $remainingIds[] = $bookRecord->attributeValue('id');
            }

            self::assertSame(['2'], $remainingIds);
        } finally {
            fclose($stream);
        }
    }
}
