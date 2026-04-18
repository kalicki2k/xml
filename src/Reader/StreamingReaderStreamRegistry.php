<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use function array_key_exists;
use function bin2hex;
use function fstat;
use function in_array;
use function is_array;
use function is_resource;
use function ltrim;
use function parse_url;
use function random_bytes;
use function stream_get_wrappers;
use function stream_wrapper_register;

/**
 * @internal Internal bridge for native XMLReader stream-resource input.
 */
final class StreamingReaderStreamRegistry
{
    private const SCHEME = 'kalle-xml-stream';

    /**
     * @var array<string, resource>
     */
    private static array $streams = [];

    private function __construct() {}

    /**
     * @param resource $stream
     */
    public static function register($stream): string
    {
        self::ensureWrapperRegistered();

        $token = bin2hex(random_bytes(16));
        self::$streams[$token] = $stream;

        return self::SCHEME . '://' . $token;
    }

    public static function unregister(string $uri): void
    {
        $token = self::tokenFromUri($uri);

        if ($token === null) {
            return;
        }

        unset(self::$streams[$token]);
    }

    /**
     * @return resource|null
     */
    public static function resolve(string $uri)
    {
        $token = self::tokenFromUri($uri);

        if ($token === null || !array_key_exists($token, self::$streams)) {
            return null;
        }

        return self::$streams[$token];
    }

    /**
     * @return array<mixed>|false
     */
    public static function stat(string $uri): array|false
    {
        $stream = self::resolve($uri);

        if (!is_resource($stream)) {
            return false;
        }

        $stat = @fstat($stream);

        return is_array($stat) ? $stat : false;
    }

    private static function ensureWrapperRegistered(): void
    {
        if (in_array(self::SCHEME, stream_get_wrappers(), true)) {
            return;
        }

        stream_wrapper_register(self::SCHEME, StreamingReaderStreamWrapper::class);
    }

    private static function tokenFromUri(string $uri): ?string
    {
        $parts = parse_url($uri);

        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;

        if (is_string($host) && $host !== '') {
            return $host;
        }

        $path = $parts['path'] ?? null;

        if (!is_string($path) || $path === '') {
            return null;
        }

        return ltrim($path, '/');
    }
}
