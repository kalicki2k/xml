<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

$itemCount = isset($argv[1]) ? max(1, intval($argv[1])) : 2000;
$iterations = isset($argv[2]) ? max(1, intval($argv[2])) : 25;
$config = WriterConfig::compact(emitDeclaration: false);
$document = buildCatalogDocument($itemCount);

$documentStringResult = benchmark($iterations, static function () use ($document, $config): int {
    return strlen($document->toString($config));
});

$streamedDocumentResult = benchmark($iterations, static function () use ($document, $config): int {
    $stream = fopen('php://temp', 'wb+');

    if ($stream === false) {
        throw new RuntimeException('Unable to open php://temp for document streaming benchmark.');
    }

    try {
        $document->saveToStream($stream, $config);
        rewind($stream);

        return strlen((string) stream_get_contents($stream));
    } finally {
        fclose($stream);
    }
});

$streamWriterResult = benchmark($iterations, static function () use ($itemCount, $config): int {
    $stream = fopen('php://temp', 'wb+');

    if ($stream === false) {
        throw new RuntimeException('Unable to open php://temp for stream-writer benchmark.');
    }

    try {
        $writer = StreamingXmlWriter::forStream($stream, $config);
        $writer->startElement('catalog');

        for ($index = 1; $index <= $itemCount; $index++) {
            $writer
                ->startElement('book')
                ->writeAttribute('id', sprintf('bk-%04d', $index))
                ->writeAttribute('available', $index % 2 === 0)
                ->startElement('title')
                ->writeText(sprintf('Book %d', $index))
                ->endElement()
                ->startElement('price')
                ->writeAttribute('currency', 'EUR')
                ->writeText(sprintf('%0.2f', 19.5 + ($index % 11)))
                ->endElement()
                ->endElement();
        }

        $writer->endElement()->finish();
        rewind($stream);

        return strlen((string) stream_get_contents($stream));
    } finally {
        fclose($stream);
    }
});

if ($documentStringResult['bytes'] !== $streamedDocumentResult['bytes'] || $documentStringResult['bytes'] !== $streamWriterResult['bytes']) {
    throw new RuntimeException('Benchmark variants produced different byte counts.');
}

echo "kalle/xml benchmark\n";
echo sprintf("items: %d\niterations: %d\nbytes: %d\n\n", $itemCount, $iterations, $documentStringResult['bytes']);
echo formatResult('document->toString()', $documentStringResult);
echo formatResult('document->saveToStream()', $streamedDocumentResult);
echo formatResult('StreamingXmlWriter', $streamWriterResult);

/**
 * @return array{bytes: int, total_ms: float, per_iteration_ms: float}
 */
function benchmark(int $iterations, callable $callback): array
{
    $bytes = 0;
    $startedAt = hrtime(true);

    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $bytes = $callback();
    }

    $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;

    return [
        'bytes' => $bytes,
        'total_ms' => $elapsedMs,
        'per_iteration_ms' => $elapsedMs / $iterations,
    ];
}

function buildCatalogDocument(int $itemCount): XmlDocument
{
    $catalog = Xml::element('catalog');

    for ($index = 1; $index <= $itemCount; $index++) {
        $catalog = $catalog->child(
            Xml::element('book')
                ->attribute('id', sprintf('bk-%04d', $index))
                ->attribute('available', $index % 2 === 0)
                ->child(Xml::element('title')->text(sprintf('Book %d', $index)))
                ->child(Xml::element('price')->attribute('currency', 'EUR')->text(sprintf('%0.2f', 19.5 + ($index % 11)))),
        );
    }

    return Xml::document($catalog)->withoutDeclaration();
}

/**
 * @param array{bytes: int, total_ms: float, per_iteration_ms: float} $result
 */
function formatResult(string $label, array $result): string
{
    return sprintf(
        "%-24s total=%9sms  per-iter=%8sms\n",
        $label,
        number_format($result['total_ms'], 2, '.', ''),
        number_format($result['per_iteration_ms'], 2, '.', ''),
    );
}
