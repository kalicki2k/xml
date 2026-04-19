<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Kalle\Xml\Document\XmlDocument;

final class XmlWriter
{
    private function __construct() {}

    public static function toString(XmlDocument $document, ?WriterConfig $config = null): string
    {
        $output = new StringXmlOutput();
        $serializer = self::createSerializer($output, $config);

        $serializer->serializeDocument($document);
        $output->finish();

        return $output->contents();
    }

    public static function toFile(XmlDocument $document, string $path, ?WriterConfig $config = null): void
    {
        $output = StreamXmlOutput::forFile($path);
        $serializer = self::createSerializer($output, $config);

        $serializer->serializeDocument($document);
        $output->finish();
    }

    public static function toStream(XmlDocument $document, mixed $stream, ?WriterConfig $config = null): void
    {
        $output = StreamXmlOutput::forStream($stream);
        $serializer = self::createSerializer($output, $config);

        $serializer->serializeDocument($document);
        $output->finish();
    }

    private static function createSerializer(XmlOutput $output, ?WriterConfig $config): XmlTreeSerializer
    {
        return new XmlTreeSerializer($config ?? WriterConfig::compact(), $output);
    }
}
