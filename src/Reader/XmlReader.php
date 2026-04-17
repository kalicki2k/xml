<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use Closure;
use DOMDocument;
use DOMElement;
use Kalle\Xml\Exception\FileReadException;
use Kalle\Xml\Exception\ParseException;
use Kalle\Xml\Exception\StreamReadException;
use LibXMLError;
use LogicException;
use ValueError;

use function file_get_contents;
use function get_debug_type;
use function get_resource_type;
use function is_resource;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function preg_replace;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_starts_with;
use function stream_get_contents;
use function stream_get_meta_data;
use function substr;
use function trim;

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
            throw new FileReadException('Cannot read XML from an empty path.');
        }

        [$xml, $readError] = self::captureRead(static fn (): string|false => file_get_contents($path));

        if (!is_string($xml) || $readError !== null) {
            $message = sprintf('Cannot read XML file "%s".', $path);

            if ($readError !== null) {
                $message = sprintf('Cannot read XML file "%s": %s', $path, $readError);
            }

            throw new FileReadException($message);
        }

        return self::parseDocument($xml, sprintf('file "%s"', $path));
    }

    public static function fromStream(mixed $stream): ReaderDocument
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new StreamReadException(sprintf(
                'XML reader requires a readable stream resource; %s given.',
                get_debug_type($stream),
            ));
        }

        if (!self::isReadableStream($stream)) {
            throw new StreamReadException(sprintf(
                'XML reader requires a readable stream resource; %s is not readable.',
                self::describeStream($stream),
            ));
        }

        [$xml, $readError] = self::captureRead(static fn (): string|false => stream_get_contents($stream));

        if (!is_string($xml) || $readError !== null) {
            $message = sprintf('Cannot read XML from %s.', self::describeStream($stream));

            if ($readError !== null) {
                $message = sprintf('Cannot read XML from %s: %s', self::describeStream($stream), $readError);
            }

            throw new StreamReadException($message);
        }

        return self::parseDocument($xml, self::describeStream($stream));
    }

    private static function parseDocument(string $xml, string $sourceLabel): ReaderDocument
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $document->validateOnParse = false;

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
            $errors = libxml_get_errors();
        } catch (ValueError) {
            $loaded = false;
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if ($loaded !== true || self::hasParseErrors($errors)) {
            $message = sprintf('Malformed XML in %s.', $sourceLabel);

            if ($errors !== []) {
                $message .= ' ' . self::formatLibxmlError($errors[0]);
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

    /**
     * @param resource $stream
     */
    private static function describeStream($stream): string
    {
        $metadata = stream_get_meta_data($stream);
        $uri = $metadata['uri'] ?? null;

        if (is_string($uri) && $uri !== '') {
            return sprintf('stream "%s"', $uri);
        }

        return 'stream resource';
    }

    /**
     * @param resource $stream
     */
    private static function isReadableStream($stream): bool
    {
        $mode = stream_get_meta_data($stream)['mode'];

        return preg_match('/[r+]/', $mode) === 1;
    }

    private static function normalizeReadError(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/^[a-z_]+\(\): /i', '', $message) ?? $message;

        if (str_starts_with($message, 'Failed to open stream: ')) {
            return substr($message, 23);
        }

        return $message;
    }

    /**
     * @return array{0: string|false, 1: ?string}
     */
    private static function captureRead(Closure $read): array
    {
        $readError = null;

        set_error_handler(static function (int $severity, string $message) use (&$readError): bool {
            $readError = self::normalizeReadError($message);

            return true;
        });

        try {
            $result = $read();
        } catch (ValueError $exception) {
            $result = false;
            $readError = self::normalizeReadError($exception->getMessage());
        } finally {
            restore_error_handler();
        }

        if (!is_string($result) && $result !== false) {
            throw new LogicException('Internal XML read operations must return string or false.');
        }

        return [$result, $readError];
    }

    /**
     * @param list<LibXMLError> $errors
     */
    private static function hasParseErrors(array $errors): bool
    {
        foreach ($errors as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                return true;
            }
        }

        return false;
    }

    private static function formatLibxmlError(LibXMLError $error): string
    {
        $message = trim($error->message);

        if ($error->line > 0 && $error->column > 0) {
            return sprintf(
                'First libxml error at line %d, column %d: %s',
                $error->line,
                $error->column,
                $message,
            );
        }

        if ($error->line > 0) {
            return sprintf(
                'First libxml error at line %d: %s',
                $error->line,
                $message,
            );
        }

        return 'First libxml error: ' . $message;
    }
}
