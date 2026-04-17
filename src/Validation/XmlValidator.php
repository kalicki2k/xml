<?php

declare(strict_types=1);

namespace Kalle\Xml\Validation;

use Closure;
use DOMDocument;
use DOMElement;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Exception\FileReadException;
use Kalle\Xml\Exception\InvalidSchemaException;
use Kalle\Xml\Exception\ParseException;
use Kalle\Xml\Exception\StreamReadException;
use LibXMLError;
use LogicException;
use ValueError;

use function file_get_contents;
use function get_debug_type;
use function get_resource_type;
use function is_resource;
use function is_string;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function preg_replace;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function stream_get_contents;
use function stream_get_meta_data;
use function substr;
use function trim;

final readonly class XmlValidator
{
    private const XSD_NAMESPACE_URI = 'http://www.w3.org/2001/XMLSchema';

    private function __construct(
        private ?string $schemaSource,
        private ?string $schemaPath,
        private string $schemaLabel,
    ) {}

    public static function fromString(string $schema): self
    {
        self::loadSchemaDocument($schema, 'string input');
        self::assertSchemaCompiles($schema, null, 'string input');

        return new self($schema, null, 'string input');
    }

    public static function fromFile(string $path): self
    {
        if ($path === '') {
            throw new FileReadException('Cannot read XSD schema from an empty path.');
        }

        [$schema, $readError] = self::captureRead(static fn (): string|false => file_get_contents($path));

        if (!is_string($schema) || $readError !== null) {
            $message = sprintf('Cannot read XSD schema file "%s".', $path);

            if ($readError !== null) {
                $message = sprintf('Cannot read XSD schema file "%s": %s', $path, $readError);
            }

            throw new FileReadException($message);
        }

        $schemaLabel = sprintf('file "%s"', $path);

        self::loadSchemaDocument($schema, $schemaLabel);
        self::assertSchemaCompiles(null, $path, $schemaLabel);

        return new self(null, $path, $schemaLabel);
    }

    public static function fromStream(mixed $stream): self
    {
        $stream = self::requireReadableStream($stream, 'XSD');
        $schemaLabel = self::describeStream($stream);
        $schema = self::readStreamContents($stream, 'XSD schema', $schemaLabel);

        self::loadSchemaDocument($schema, $schemaLabel);
        self::assertSchemaCompiles($schema, null, $schemaLabel);

        return new self($schema, null, $schemaLabel);
    }

    public function validateString(string $xml): ValidationResult
    {
        return $this->validateDomDocument(
            self::loadXmlDocument($xml, 'string input'),
        );
    }

    public function validateFile(string $path): ValidationResult
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

        return $this->validateDomDocument(
            self::loadXmlDocument($xml, sprintf('file "%s"', $path)),
        );
    }

    public function validateStream(mixed $stream): ValidationResult
    {
        $stream = self::requireReadableStream($stream, 'XML');
        $sourceLabel = self::describeStream($stream);
        $xml = self::readStreamContents($stream, 'XML', $sourceLabel);

        return $this->validateDomDocument(
            self::loadXmlDocument($xml, $sourceLabel),
        );
    }

    public function validateXmlDocument(XmlDocument $document): ValidationResult
    {
        return $this->validateDomDocument(
            self::loadXmlDocument($document->toString(), 'XmlDocument input'),
        );
    }

    private function validateDomDocument(DOMDocument $document): ValidationResult
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            [$validated, $warning] = self::captureValidation(function () use ($document): bool {
                if ($this->schemaPath !== null) {
                    return $document->schemaValidate($this->schemaPath, LIBXML_NONET);
                }

                return $document->schemaValidateSource($this->schemaSource ?? '', LIBXML_NONET);
            });
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if (self::isSchemaCompileFailure($warning, $errors)) {
            throw self::buildInvalidSchemaException($this->schemaLabel, $warning, $errors);
        }

        if ($validated === true) {
            return ValidationResult::valid();
        }

        return ValidationResult::invalid(self::buildValidationErrors($warning, $errors));
    }

    private static function assertSchemaCompiles(?string $schemaSource, ?string $schemaPath, string $schemaLabel): void
    {
        $probe = new DOMDocument('1.0', 'UTF-8');
        $probe->loadXML('<kalle-validation-probe/>', LIBXML_NONET);

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            [$validated, $warning] = self::captureValidation(static function () use ($probe, $schemaSource, $schemaPath): bool {
                if ($schemaPath !== null) {
                    return $probe->schemaValidate($schemaPath, LIBXML_NONET);
                }

                return $probe->schemaValidateSource($schemaSource ?? '', LIBXML_NONET);
            });
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if ($validated === true) {
            return;
        }

        if (!self::isSchemaCompileFailure($warning, $errors)) {
            return;
        }

        throw self::buildInvalidSchemaException($schemaLabel, $warning, $errors);
    }

    private static function loadXmlDocument(string $xml, string $sourceLabel): DOMDocument
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

        if ($loaded !== true || self::hasLibxmlErrors($errors)) {
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

        return $document;
    }

    private static function loadSchemaDocument(string $schema, string $sourceLabel): DOMDocument
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $document->validateOnParse = false;

        try {
            $loaded = $document->loadXML($schema, LIBXML_NONET);
            $errors = libxml_get_errors();
        } catch (ValueError) {
            $loaded = false;
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }

        if ($loaded !== true || self::hasLibxmlErrors($errors)) {
            $message = sprintf('Invalid XSD schema in %s.', $sourceLabel);

            if ($errors !== []) {
                $message .= ' ' . self::formatLibxmlError($errors[0]);
            }

            throw new InvalidSchemaException($message);
        }

        $root = $document->documentElement;

        if (!$root instanceof DOMElement) {
            throw new InvalidSchemaException(sprintf(
                'Invalid XSD schema in %s: no document element was found.',
                $sourceLabel,
            ));
        }

        if (($root->localName ?? $root->tagName) !== 'schema' || $root->namespaceURI !== self::XSD_NAMESPACE_URI) {
            throw new InvalidSchemaException(sprintf(
                'Invalid XSD schema in %s: document element must be "{%s}schema".',
                $sourceLabel,
                self::XSD_NAMESPACE_URI,
            ));
        }

        return $document;
    }

    /**
     * @param ?string $warning
     * @param list<LibXMLError> $errors
     *
     * @return list<ValidationError>
     */
    private static function buildValidationErrors(?string $warning, array $errors): array
    {
        $validationErrors = [];

        foreach ($errors as $error) {
            $validationErrors[] = ValidationError::fromLibxmlError($error);
        }

        if ($validationErrors !== []) {
            return $validationErrors;
        }

        if ($warning !== null) {
            return [new ValidationError($warning)];
        }

        return [new ValidationError('XML does not match the XSD schema.')];
    }

    /**
     * @param ?string $warning
     * @param list<LibXMLError> $errors
     */
    private static function buildInvalidSchemaException(
        string $schemaLabel,
        ?string $warning,
        array $errors,
    ): InvalidSchemaException {
        $message = sprintf('Invalid XSD schema in %s.', $schemaLabel);

        if ($errors !== []) {
            $message .= ' ' . self::formatLibxmlError($errors[0]);
        } elseif ($warning !== null) {
            $message .= ' ' . $warning;
        }

        return new InvalidSchemaException($message);
    }

    /**
     * @param ?string $warning
     * @param list<LibXMLError> $errors
     */
    private static function isSchemaCompileFailure(?string $warning, array $errors): bool
    {
        if ($warning !== null) {
            foreach ([
                'Invalid Schema',
                'Schemas parser error',
                'Failed to locate the main schema resource',
                'failed to load external entity',
            ] as $fragment) {
                if (str_contains($warning, $fragment)) {
                    return true;
                }
            }
        }

        foreach ($errors as $error) {
            $message = trim($error->message);

            if (
                str_contains($message, '{' . self::XSD_NAMESPACE_URI . '}')
                || str_contains($message, 'Schemas parser error')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<LibXMLError> $errors
     */
    private static function hasLibxmlErrors(array $errors): bool
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

    private static function normalizeReadError(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/^[a-z_]+\(\): /i', '', $message) ?? $message;

        if (str_starts_with($message, 'Failed to open stream: ')) {
            return substr($message, 23);
        }

        return $message;
    }

    private static function normalizeValidationWarning(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/^DOMDocument::schemaValidateSource\(\): /', '', $message) ?? $message;
        $message = preg_replace('/^DOMDocument::schemaValidate\(\): /', '', $message) ?? $message;

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
     * @return resource
     */
    private static function requireReadableStream(mixed $stream, string $kind)
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new StreamReadException(sprintf(
                'XmlValidator requires a readable %s stream resource; %s given.',
                $kind,
                get_debug_type($stream),
            ));
        }

        if (!self::isReadableStream($stream)) {
            throw new StreamReadException(sprintf(
                'XmlValidator requires a readable %s stream resource; %s is not readable.',
                $kind,
                self::describeStream($stream),
            ));
        }

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private static function readStreamContents($stream, string $subject, string $streamLabel): string
    {
        [$contents, $readError] = self::captureRead(static fn (): string|false => stream_get_contents($stream));

        if (!is_string($contents) || $readError !== null) {
            $message = sprintf('Cannot read %s from %s.', $subject, $streamLabel);

            if ($readError !== null) {
                $message = sprintf('Cannot read %s from %s: %s', $subject, $streamLabel, $readError);
            }

            throw new StreamReadException($message);
        }

        return $contents;
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $operation
     *
     * @return array{0: TResult|false, 1: ?string}
     */
    private static function captureValidation(Closure $operation): array
    {
        $warning = null;

        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = self::normalizeValidationWarning($message);

            return true;
        });

        try {
            $result = $operation();
        } catch (ValueError $exception) {
            $result = false;
            $warning = self::normalizeValidationWarning($exception->getMessage());
        } finally {
            restore_error_handler();
        }

        return [$result, $warning];
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
}
