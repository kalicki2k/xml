<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMDocument;
use DOMElement;
use Kalle\Xml\Exception\DomInteropException;
use Kalle\Xml\Exception\FileReadException;
use Kalle\Xml\Exception\ParseException;
use Kalle\Xml\Exception\StreamReadException;
use LogicException;

use function file_get_contents;
use function get_debug_type;
use function get_resource_type;
use function is_resource;
use function sprintf;
use function stream_get_contents;

final class XmlReader
{
    private function __construct() {}

    public static function fromString(string $xml): ReaderDocument
    {
        return self::parseDocument($xml, 'string input');
    }

    public static function fromFile(string $path): ReaderDocument
    {
        if ($path === '') {
            throw new FileReadException('XmlReader::fromFile() requires a non-empty path.');
        }

        [$xml, $readError] = ReaderSupport::captureOperation(static fn (): string|false => file_get_contents($path));

        if (!is_string($xml) || $readError !== null) {
            $message = sprintf('XmlReader::fromFile() could not read XML file "%s".', $path);

            if ($readError !== null) {
                $message = sprintf('XmlReader::fromFile() could not read XML file "%s": %s', $path, $readError);
            }

            throw new FileReadException($message);
        }

        return self::parseDocument($xml, sprintf('file "%s"', $path));
    }

    public static function fromStream(mixed $stream): ReaderDocument
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new StreamReadException(sprintf(
                'XmlReader::fromStream() requires a readable stream resource; %s given.',
                get_debug_type($stream),
            ));
        }

        if (!ReaderSupport::isReadableStream($stream)) {
            throw new StreamReadException(sprintf(
                'XmlReader::fromStream() requires a readable stream resource; %s is not readable.',
                ReaderSupport::describeStream($stream),
            ));
        }

        [$xml, $readError] = ReaderSupport::captureOperation(static fn (): string|false => stream_get_contents($stream));

        if (!is_string($xml) || $readError !== null) {
            $message = sprintf('XmlReader::fromStream() could not read XML from %s.', ReaderSupport::describeStream($stream));

            if ($readError !== null) {
                $message = sprintf(
                    'XmlReader::fromStream() could not read XML from %s: %s',
                    ReaderSupport::describeStream($stream),
                    $readError,
                );
            }

            throw new StreamReadException($message);
        }

        return self::parseDocument($xml, ReaderSupport::describeStream($stream));
    }

    public static function fromDomDocument(DOMDocument $document): ReaderDocument
    {
        try {
            return ReaderDocument::fromDomDocument($document);
        } catch (LogicException $exception) {
            throw new DomInteropException(
                'XmlReader::fromDomDocument() requires a DOMDocument with a document element.',
                previous: $exception,
            );
        }
    }

    public static function fromDomElement(DOMElement $element): ReaderElement
    {
        return ReaderElement::fromDomElement($element);
    }

    private static function parseDocument(string $xml, string $sourceLabel): ReaderDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $document->validateOnParse = false;

        [$result, $errors] = ReaderSupport::captureLibxmlErrors(
            static fn () => ReaderSupport::captureOperation(
                static fn () => $document->loadXML($xml, LIBXML_NONET),
            ),
        );
        [$loaded] = $result;

        if ($loaded !== true || ReaderSupport::hasParseErrors($errors)) {
            $message = sprintf('Malformed XML in %s.', $sourceLabel);

            if ($errors !== []) {
                $message .= ' ' . ReaderSupport::formatLibxmlError($errors[0]);
            }

            throw new ParseException($message);
        }

        if (!$document->documentElement instanceof DOMElement) {
            throw new ParseException(sprintf(
                'Malformed XML in %s: no document element was found.',
                $sourceLabel,
            ));
        }

        return ReaderDocument::fromDomDocument($document);
    }
}
