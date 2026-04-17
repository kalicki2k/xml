<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Integration;

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Exception\FileWriteException;
use Kalle\Xml\Exception\StreamWriteException;
use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function min;
use function random_bytes;
use function rewind;
use function stream_get_contents;
use function stream_get_wrappers;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class XmlDocumentFileOutputTest extends TestCase
{
    private const PARTIAL_WRITE_SCHEME = 'kalle-partial-write';

    public function testItSavesADocumentToAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-');

        self::assertNotFalse($path);

        try {
            Xml::document(Xml::element('catalog')->child(Xml::element('book')))
                ->saveToFile($path, WriterConfig::compact(emitDeclaration: false));

            self::assertSame('<catalog><book/></catalog>', (string) file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function testItOverwritesExistingFileContentWithPrettyPrintedXml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-');

        self::assertNotFalse($path);

        try {
            file_put_contents($path, 'stale');

            Xml::document(
                Xml::element('catalog')
                    ->child(Xml::element('book')->attribute('isbn', '9780132350884'))
                    ->child(Xml::element('book')->attribute('isbn', '9780321125217')),
            )->saveToFile(
                $path,
                WriterConfig::pretty(emitDeclaration: false),
            );

            self::assertSame(
                "<catalog>\n    <book isbn=\"9780132350884\"/>\n    <book isbn=\"9780321125217\"/>\n</catalog>",
                (string) file_get_contents($path),
            );
        } finally {
            @unlink($path);
        }
    }

    public function testItRaisesALibrarySpecificExceptionForAnInvalidPath(): void
    {
        $this->expectException(FileWriteException::class);
        $this->expectExceptionMessage('empty path');

        Xml::document(Xml::element('catalog'))->saveToFile('', WriterConfig::compact());
    }

    public function testItRaisesALibrarySpecificExceptionForPathsContainingNullBytes(): void
    {
        $this->expectException(FileWriteException::class);
        $this->expectExceptionMessage('null bytes');

        Xml::document(Xml::element('catalog'))->saveToFile("\0invalid.xml", WriterConfig::compact());
    }

    public function testItRaisesALibrarySpecificExceptionWhenTheTargetDirectoryDoesNotExist(): void
    {
        $path = sys_get_temp_dir() . '/kalle-xml-missing-' . bin2hex(random_bytes(6)) . '/document.xml';

        $this->expectException(FileWriteException::class);
        $this->expectExceptionMessage($path);

        Xml::document(Xml::element('catalog'))->saveToFile($path, WriterConfig::compact());
    }

    public function testItRaisesALibrarySpecificExceptionForPartialWrites(): void
    {
        $path = self::PARTIAL_WRITE_SCHEME . '://document.xml';

        $this->registerPartialWriteWrapper();

        try {
            $this->expectException(FileWriteException::class);
            $this->expectExceptionMessage($path);
            $this->expectExceptionMessage('Incomplete XML write');

            Xml::document(Xml::element('catalog'))->saveToFile($path, WriterConfig::compact());
        } finally {
            $this->unregisterPartialWriteWrapper();
        }
    }

    public function testItSavesANamespacedDocumentToAFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-');

        self::assertNotFalse($path);

        try {
            Xml::document(
                Xml::element(Xml::qname('feed', 'urn:feed', 'atom'))
                    ->declareNamespace('atom', 'urn:feed')
                    ->declareNamespace('xlink', 'urn:xlink')
                    ->child(
                        Xml::element(Xml::qname('entry', 'urn:feed', 'atom'))
                            ->attribute(Xml::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1'),
                    ),
            )->saveToFile($path, WriterConfig::compact(emitDeclaration: false));

            self::assertSame(
                '<atom:feed xmlns:atom="urn:feed" xmlns:xlink="urn:xlink"><atom:entry xlink:href="https://example.com/items/1"/></atom:feed>',
                (string) file_get_contents($path),
            );
        } finally {
            @unlink($path);
        }
    }

    public function testItSavesADocumentToAStreamResource(): void
    {
        $stream = fopen('php://temp', 'wb+');

        self::assertIsResource($stream);

        try {
            Xml::document(
                Xml::element('catalog')
                    ->child(Xml::element('book')->attribute('isbn', '9780132350884')),
            )->saveToStream($stream, WriterConfig::compact(emitDeclaration: false));

            rewind($stream);

            self::assertSame(
                '<catalog><book isbn="9780132350884"/></catalog>',
                (string) stream_get_contents($stream),
            );
        } finally {
            fclose($stream);
        }
    }

    public function testItRaisesAStreamSpecificExceptionForPartialWritesToAProvidedResource(): void
    {
        $this->registerPartialWriteWrapper();

        $stream = fopen(self::PARTIAL_WRITE_SCHEME . '://document.xml', 'wb');

        self::assertIsResource($stream);

        try {
            $this->expectException(StreamWriteException::class);
            $this->expectExceptionMessage('Incomplete XML write');
            $this->expectExceptionMessage('kalle-partial-write://document.xml');

            Xml::document(Xml::element('catalog'))->saveToStream($stream, WriterConfig::compact());
        } finally {
            fclose($stream);
            $this->unregisterPartialWriteWrapper();
        }
    }

    public function testItRejectsNonStreamResourcesWhenSavingToAStream(): void
    {
        $directory = opendir(sys_get_temp_dir());

        self::assertIsResource($directory);

        try {
            $this->expectException(StreamWriteException::class);
            $this->expectExceptionMessage('stream resource');

            Xml::document(Xml::element('catalog'))->saveToStream($directory, WriterConfig::compact());
        } finally {
            closedir($directory);
        }
    }

    public function testItRejectsNonWritableStreamResourcesWhenSavingToAStream(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kalle-xml-readonly-');

        self::assertNotFalse($path);

        $stream = fopen($path, 'rb');

        self::assertIsResource($stream);

        try {
            $this->expectException(StreamWriteException::class);
            $this->expectExceptionMessage('not writable');

            Xml::document(Xml::element('catalog'))->saveToStream($stream, WriterConfig::compact());
        } finally {
            fclose($stream);
            @unlink($path);
        }
    }

    private function registerPartialWriteWrapper(): void
    {
        if (in_array(self::PARTIAL_WRITE_SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PARTIAL_WRITE_SCHEME);
        }

        stream_wrapper_register(self::PARTIAL_WRITE_SCHEME, PartialWriteStreamWrapper::class);
    }

    private function unregisterPartialWriteWrapper(): void
    {
        if (in_array(self::PARTIAL_WRITE_SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::PARTIAL_WRITE_SCHEME);
        }
    }
}

final class PartialWriteStreamWrapper
{
    public mixed $context = null;

    private int $writeCalls = 0;

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->writeCalls = 0;

        return true;
    }

    public function stream_write(string $data): int
    {
        $this->writeCalls++;

        if ($this->writeCalls > 1) {
            return 0;
        }

        return min(3, strlen($data));
    }
}
