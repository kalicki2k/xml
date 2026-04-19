<?php

declare(strict_types=1);

namespace Kalle\Xml\Canonicalization;

use DOMDocument;
use DOMElement;
use DOMNode;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Dom\XmlDomBridge;
use Kalle\Xml\Exception\CanonicalizationException;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\ReaderDocument;
use Kalle\Xml\Reader\ReaderElement;
use Kalle\Xml\Reader\XmlReader;

use function is_string;
use function sprintf;

final class XmlCanonicalizer
{
    private function __construct() {}

    public static function document(
        XmlDocument $document,
        ?CanonicalizationOptions $options = null,
    ): string {
        return self::domDocument(
            XmlDomBridge::toDomDocument($document),
            $options,
        );
    }

    public static function readerDocument(
        ReaderDocument $document,
        ?CanonicalizationOptions $options = null,
    ): string {
        return self::canonicalizeNode(
            $document->toDomDocument(),
            self::normalizeOptions($options),
            'readerDocument',
        );
    }

    public static function readerElement(
        ReaderElement $element,
        ?CanonicalizationOptions $options = null,
    ): string {
        return self::domDocument(
            XmlDomBridge::elementToDomDocument(
                XmlImporter::element($element),
            ),
            $options,
        );
    }

    public static function domDocument(
        DOMDocument $document,
        ?CanonicalizationOptions $options = null,
    ): string {
        if (!$document->documentElement instanceof DOMElement) {
            throw new CanonicalizationException(
                'XmlCanonicalizer::domDocument() requires a DOMDocument with a document element.',
            );
        }

        return self::canonicalizeNode(
            $document,
            self::normalizeOptions($options),
            'domDocument',
        );
    }

    public static function xmlString(
        string $xml,
        ?CanonicalizationOptions $options = null,
    ): string {
        return self::readerDocument(
            XmlReader::fromString($xml),
            $options,
        );
    }

    private static function normalizeOptions(?CanonicalizationOptions $options): CanonicalizationOptions
    {
        return $options ?? CanonicalizationOptions::withoutComments();
    }

    private static function canonicalizeNode(
        DOMNode $node,
        CanonicalizationOptions $options,
        string $method,
    ): string {
        $canonicalXml = $node->C14N(false, $options->includeComments());

        if (is_string($canonicalXml)) {
            return $canonicalXml;
        }

        throw new CanonicalizationException(sprintf(
            'XmlCanonicalizer::%s() could not canonicalize the provided XML.',
            $method,
        ));
    }
}
