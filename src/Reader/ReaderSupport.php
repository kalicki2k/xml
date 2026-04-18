<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use Closure;
use LibXMLError;
use ValueError;

use function is_string;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function preg_replace;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_starts_with;
use function stream_get_meta_data;
use function substr;
use function trim;

final class ReaderSupport
{
    private function __construct() {}

    /**
     * @return array{0: mixed, 1: ?string}
     */
    public static function captureOperation(Closure $operation): array
    {
        $operationError = null;

        set_error_handler(static function (int $severity, string $message) use (&$operationError): bool {
            $operationError = self::normalizeReadError($message);

            return true;
        });

        try {
            $result = $operation();
        } catch (ValueError $exception) {
            $result = false;
            $operationError = self::normalizeReadError($exception->getMessage());
        } finally {
            restore_error_handler();
        }

        return [$result, $operationError];
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $operation
     *
     * @return array{0: TResult, 1: list<LibXMLError>}
     */
    public static function captureLibxmlErrors(Closure $operation): array
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $result = $operation();
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        return [$result, $errors];
    }

    /**
     * @param resource $stream
     */
    public static function describeStream($stream): string
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
    public static function isReadableStream($stream): bool
    {
        $mode = stream_get_meta_data($stream)['mode'];

        return preg_match('/[r+]/', $mode) === 1;
    }

    /**
     * @param list<LibXMLError> $errors
     */
    public static function hasParseErrors(array $errors): bool
    {
        foreach ($errors as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                return true;
            }
        }

        return false;
    }

    public static function formatLibxmlError(LibXMLError $error): string
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

    private static function normalizeReadError(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/^[a-z_]+\(\): /i', '', $message) ?? $message;

        if (str_starts_with($message, 'Failed to open stream: ')) {
            return substr($message, 23);
        }

        return $message;
    }
}
