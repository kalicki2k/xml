<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use function feof;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function is_array;
use function is_resource;

/**
 * @internal Internal bridge for native XMLReader stream-resource input.
 */
final class StreamingReaderStreamWrapper
{
    public mixed $context = null;

    /**
     * @var resource|null
     */
    private $stream = null;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $stream = StreamingReaderStreamRegistry::resolve($path);

        if (!is_resource($stream)) {
            return false;
        }

        $this->stream = $stream;
        $openedPath = $path;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if (!is_resource($this->stream)) {
            return false;
        }

        if ($count < 1) {
            return '';
        }

        return fread($this->stream, $count);
    }

    public function stream_eof(): bool
    {
        if (!is_resource($this->stream)) {
            return true;
        }

        return feof($this->stream);
    }

    public function stream_tell(): int
    {
        if (!is_resource($this->stream)) {
            return 0;
        }

        $position = ftell($this->stream);

        return $position !== false ? $position : 0;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if (!is_resource($this->stream)) {
            return false;
        }

        return fseek($this->stream, $offset, $whence) === 0;
    }

    /**
     * @return array<mixed>|false
     */
    public function stream_stat(): array|false
    {
        if (!is_resource($this->stream)) {
            return false;
        }

        $stat = fstat($this->stream);

        return is_array($stat) ? $stat : false;
    }

    /**
     * @return array<mixed>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return StreamingReaderStreamRegistry::stat($path);
    }

    public function stream_close(): void
    {
        $this->stream = null;
    }
}
