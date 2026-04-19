<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Closure;
use Kalle\Xml\Exception\FileWriteException;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Exception\StreamWriteException;
use ValueError;

use function fclose;
use function fopen;
use function fwrite;
use function get_debug_type;
use function get_resource_type;
use function is_resource;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function stream_get_meta_data;
use function strlen;
use function substr;

/**
 * @internal
 */
final class StreamXmlOutput implements XmlOutput
{
    private bool $finished = false;

    /**
     * @param resource $stream
     * @param class-string<SerializationException> $exceptionClass
     */
    private function __construct(
        private $stream,
        private string $targetLabel,
        private bool $closeOnFinish,
        private string $exceptionClass,
    ) {}

    public function __destruct()
    {
        if ($this->finished || !$this->closeOnFinish || !is_resource($this->stream)) {
            return;
        }

        @fclose($this->stream);
    }

    public static function forFile(string $path): self
    {
        if ($path === '') {
            throw new FileWriteException('Cannot write XML to an empty path.');
        }

        [$stream, $openError] = self::captureIo(static fn () => fopen($path, 'wb'));

        if ($stream === false) {
            $message = sprintf('Failed to write XML to file "%s".', $path);

            if ($openError !== null) {
                $message = sprintf('Failed to write XML to file "%s": %s', $path, $openError);
            }

            throw new FileWriteException($message);
        }

        return new self(
            $stream,
            sprintf('file "%s"', $path),
            true,
            FileWriteException::class,
        );
    }

    public static function forStream(mixed $stream, bool $closeOnFinish = false): self
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new StreamWriteException(sprintf(
                'XML output requires a writable stream resource; %s given.',
                get_debug_type($stream),
            ));
        }

        if (!self::isWritableStream($stream)) {
            throw new StreamWriteException(sprintf(
                'XML output requires a writable stream resource; %s is not writable.',
                self::describeStream($stream),
            ));
        }

        return new self(
            $stream,
            self::describeStream($stream),
            $closeOnFinish,
            StreamWriteException::class,
        );
    }

    public function write(string $chunk): void
    {
        if ($chunk === '') {
            return;
        }

        $remaining = $chunk;
        $expectedBytes = strlen($chunk);
        $bytesWrittenTotal = 0;

        while ($remaining !== '') {
            [$bytesWritten, $writeError] = self::captureIo(
                fn () => fwrite($this->stream, $remaining),
            );

            if ($bytesWritten === false) {
                $message = sprintf('Failed to write XML to %s.', $this->targetLabel);

                if ($writeError !== null) {
                    $message = sprintf('Failed to write XML to %s: %s', $this->targetLabel, $writeError);
                }

                $this->throwWriteException($message);
            }

            if ($bytesWritten === 0) {
                $message = sprintf(
                    'Incomplete XML write to %s: wrote %d of %d bytes.',
                    $this->targetLabel,
                    $bytesWrittenTotal,
                    $expectedBytes,
                );

                if ($writeError !== null) {
                    $message .= sprintf(' PHP error: %s', $writeError);
                }

                $this->throwWriteException($message);
            }

            $bytesWrittenTotal += $bytesWritten;
            $remaining = substr($remaining, $bytesWritten);
        }
    }

    public function finish(): void
    {
        if ($this->finished) {
            return;
        }

        $this->finished = true;

        if (!$this->closeOnFinish) {
            return;
        }

        [$closed, $closeError] = self::captureIo(fn () => fclose($this->stream));

        if ($closed !== false) {
            return;
        }

        $message = sprintf('Failed to finalize XML output to %s.', $this->targetLabel);

        if ($closeError !== null) {
            $message = sprintf('Failed to finalize XML output to %s: %s', $this->targetLabel, $closeError);
        }

        $this->throwWriteException($message);
    }

    /**
     * @param resource $stream
     */
    private static function describeStream($stream): string
    {
        $metadata = stream_get_meta_data($stream);
        $uri = $metadata['uri'] ?? null;

        if ($uri !== null && $uri !== '') {
            return sprintf('stream "%s"', $uri);
        }

        return 'stream resource';
    }

    /**
     * @param resource $stream
     */
    private static function isWritableStream($stream): bool
    {
        $metadata = stream_get_meta_data($stream);

        return preg_match('/[waxc+]/', $metadata['mode']) === 1;
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $operation
     *
     * @return array{0: TResult|false, 1: ?string}
     */
    private static function captureIo(Closure $operation): array
    {
        $error = null;

        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $result = $operation();
        } catch (ValueError $exception) {
            $result = false;
            $error = $exception->getMessage();
        } finally {
            restore_error_handler();
        }

        return [$result, $error];
    }

    private function throwWriteException(string $message): never
    {
        $exceptionClass = $this->exceptionClass;

        throw new $exceptionClass($message);
    }
}
