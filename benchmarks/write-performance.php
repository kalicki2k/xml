<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Builder\Xml;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Writer\StreamingXmlWriter;
use Kalle\Xml\Writer\WriterConfig;

const FEED_NS = 'urn:feed';
const MEDIA_NS = 'urn:media';
const XLINK_NS = 'urn:xlink';
const DC_NS = 'urn:dc';

$scenarios = createScenarios();
[$selectedScenarioKeys, $iterationsOverride] = parseArguments($argv, array_keys($scenarios));

echo "kalle/xml write benchmark suite\n";
echo sprintf("PHP: %s\n", PHP_VERSION);
echo "Measurement: end-to-end writing per implementation\n";
echo "Memory metric: maximum peak delta per iteration relative to the starting baseline\n";
echo "Validation: semantic XML comparison via DOM C14N before timing\n\n";

foreach ($selectedScenarioKeys as $scenarioKey) {
    $scenario = $scenarios[$scenarioKey];
    $iterations = $iterationsOverride ?? $scenario['iterations'];
    $expectedXml = $scenario['expected_xml']();
    $expectedCanonicalXml = canonicalizeXml($expectedXml);
    $implementations = $scenario['implementations']();

    echo sprintf(
        "Scenario: %s\n%s\nIterations: %d\nBaseline bytes: %d\n\n",
        $scenario['label'],
        $scenario['description'],
        $iterations,
        strlen($expectedXml),
    );

    echo sprintf(
        "%-28s %12s %12s %14s\n",
        'Implementation',
        'Total ms',
        'Avg ms',
        'Peak KiB',
    );

    foreach ($implementations as $label => $runner) {
        $warmupXml = $runner();
        assertEquivalentXml($expectedXml, $expectedCanonicalXml, $warmupXml, $scenario['label'], $label);

        $result = benchmarkImplementation($iterations, $runner);

        echo sprintf(
            "%-28s %12s %12s %14s\n",
            $label,
            number_format($result['total_ms'], 2, '.', ''),
            number_format($result['avg_ms'], 3, '.', ''),
            number_format($result['peak_delta_kib'], 1, '.', ''),
        );
    }

    echo "\n";
}

/**
 * @return array<string, array{
 *   label: string,
 *   description: string,
 *   iterations: int,
 *   expected_xml: callable(): string,
 *   implementations: callable(): array<string, callable(): string>
 * }>
 */
function createScenarios(): array
{
    return [
        'small' => createCatalogScenario(
            label: 'Small document',
            description: '5 book entries with attributes and nested text elements.',
            itemCount: 5,
            iterations: 1000,
        ),
        'medium' => createCatalogScenario(
            label: 'Medium document',
            description: '250 book entries with repeated, realistically nested structure.',
            itemCount: 250,
            iterations: 150,
        ),
        'large' => createCatalogScenario(
            label: 'Large document',
            description: '2500 book entries for broader throughput and memory trends.',
            itemCount: 2500,
            iterations: 20,
        ),
        'namespace-heavy' => createNamespaceHeavyScenario(
            entryCount: 300,
            iterations: 60,
        ),
    ];
}

/**
 * @return array{
 *   label: string,
 *   description: string,
 *   iterations: int,
 *   expected_xml: callable(): string,
 *   implementations: callable(): array<string, callable(): string>
 * }
 */
function createCatalogScenario(
    string $label,
    string $description,
    int $itemCount,
    int $iterations,
): array {
    $config = WriterConfig::compact(emitDeclaration: false);

    return [
        'label' => $label,
        'description' => $description,
        'iterations' => $iterations,
        'expected_xml' => static function () use ($itemCount, $config): string {
            return buildCatalogDocument($itemCount)->toString($config);
        },
        'implementations' => static function () use ($itemCount, $config): array {
            $implementations = [
                'kalle/xml document model' => static function () use ($itemCount, $config): string {
                    return buildCatalogDocument($itemCount)->toString($config);
                },
                'StreamingXmlWriter' => static function () use ($itemCount, $config): string {
                    $writer = StreamingXmlWriter::forString($config);
                    writeCatalogWithStreamingWriter($writer, $itemCount);
                    $writer->finish();

                    return $writer->toString();
                },
            ];

            if (class_exists(DOMDocument::class)) {
                $implementations['DOMDocument'] = static function () use ($itemCount): string {
                    return buildCatalogWithDomDocument($itemCount);
                };
            }

            if (class_exists(XMLWriter::class)) {
                $implementations['XMLWriter'] = static function () use ($itemCount): string {
                    return buildCatalogWithXmlWriter($itemCount);
                };
            }

            return $implementations;
        },
    ];
}

/**
 * @return array{
 *   label: string,
 *   description: string,
 *   iterations: int,
 *   expected_xml: callable(): string,
 *   implementations: callable(): array<string, callable(): string>
 * }
 */
function createNamespaceHeavyScenario(int $entryCount, int $iterations): array
{
    $config = WriterConfig::compact(emitDeclaration: false);

    return [
        'label' => 'Namespace-heavy document',
        'description' => '300 feed entries with default and prefixed namespaces plus prefixed attributes.',
        'iterations' => $iterations,
        'expected_xml' => static function () use ($entryCount, $config): string {
            return buildNamespaceHeavyDocument($entryCount)->toString($config);
        },
        'implementations' => static function () use ($entryCount, $config): array {
            $implementations = [
                'kalle/xml document model' => static function () use ($entryCount, $config): string {
                    return buildNamespaceHeavyDocument($entryCount)->toString($config);
                },
                'StreamingXmlWriter' => static function () use ($entryCount, $config): string {
                    $writer = StreamingXmlWriter::forString($config);
                    writeNamespaceHeavyWithStreamingWriter($writer, $entryCount);
                    $writer->finish();

                    return $writer->toString();
                },
            ];

            if (class_exists(DOMDocument::class)) {
                $implementations['DOMDocument'] = static function () use ($entryCount): string {
                    return buildNamespaceHeavyWithDomDocument($entryCount);
                };
            }

            if (class_exists(XMLWriter::class)) {
                $implementations['XMLWriter'] = static function () use ($entryCount): string {
                    return buildNamespaceHeavyWithXmlWriter($entryCount);
                };
            }

            return $implementations;
        },
    ];
}

/**
 * @param array<int, string> $argv
 * @param list<string> $availableScenarioKeys
 *
 * @return array{list<string>, ?int}
 */
function parseArguments(array $argv, array $availableScenarioKeys): array
{
    $selectedScenarioKeys = $availableScenarioKeys;
    $iterationsOverride = null;
    $firstArgument = $argv[1] ?? null;

    if ($firstArgument !== null) {
        if (ctype_digit($firstArgument)) {
            $iterationsOverride = max(1, (int) $firstArgument);
        } elseif ($firstArgument !== 'all') {
            if (!in_array($firstArgument, $availableScenarioKeys, true)) {
                printUsageAndExit($availableScenarioKeys);
            }

            $selectedScenarioKeys = [$firstArgument];
        }
    }

    if (isset($argv[2])) {
        if (!ctype_digit($argv[2])) {
            printUsageAndExit($availableScenarioKeys);
        }

        $iterationsOverride = max(1, (int) $argv[2]);
    }

    return [$selectedScenarioKeys, $iterationsOverride];
}

/**
 * @param list<string> $availableScenarioKeys
 */
function printUsageAndExit(array $availableScenarioKeys): never
{
    $scenarioList = implode(', ', $availableScenarioKeys);

    fwrite(
        STDERR,
        "Usage:\n"
        . "  php benchmarks/write-performance.php\n"
        . "  php benchmarks/write-performance.php <scenario>\n"
        . "  php benchmarks/write-performance.php <scenario> <iterations>\n"
        . "  php benchmarks/write-performance.php <iterations>\n\n"
        . "Available scenarios: {$scenarioList}\n",
    );

    exit(1);
}

/**
 * @param callable(): string $runner
 *
 * @return array{total_ms: float, avg_ms: float, peak_delta_kib: float}
 */
function benchmarkImplementation(int $iterations, callable $runner): array
{
    $totalNanoseconds = 0;
    $maxPeakDeltaBytes = 0;

    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        gc_collect_cycles();

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $baselineUsage = memory_get_usage(true);
        $startedAt = hrtime(true);
        $xml = $runner();
        $totalNanoseconds += hrtime(true) - $startedAt;
        $maxPeakDeltaBytes = max(
            $maxPeakDeltaBytes,
            max(0, memory_get_peak_usage(true) - $baselineUsage),
        );

        if ($xml === '') {
            throw new RuntimeException('Benchmark runner produced an empty XML string.');
        }

        unset($xml);
        gc_collect_cycles();
    }

    $totalMs = $totalNanoseconds / 1_000_000;

    return [
        'total_ms' => $totalMs,
        'avg_ms' => $totalMs / $iterations,
        'peak_delta_kib' => $maxPeakDeltaBytes / 1024,
    ];
}

function buildCatalogDocument(int $itemCount): XmlDocument
{
    $catalog = Xml::element('catalog')
        ->attribute('generatedAt', '2026-04-17T10:30:00Z')
        ->attribute('source', 'benchmark');

    for ($index = 1; $index <= $itemCount; $index++) {
        $catalog = $catalog->child(
            Xml::element('book')
                ->attribute('id', sprintf('bk-%05d', $index))
                ->attribute('available', $index % 2 === 0)
                ->child(Xml::element('title')->text(sprintf('Book %d', $index)))
                ->child(Xml::element('author')->text(sprintf('Author %d', $index % 41)))
                ->child(Xml::element('price')->attribute('currency', 'EUR')->text(sprintf('%0.2f', 19.5 + ($index % 11))))
                ->child(Xml::element('description')->text(sprintf('Entry %d with escaped text & data.', $index))),
        );
    }

    return Xml::document($catalog)->withoutDeclaration();
}

function writeCatalogWithStreamingWriter(StreamingXmlWriter $writer, int $itemCount): void
{
    $writer
        ->startElement('catalog')
        ->writeAttribute('generatedAt', '2026-04-17T10:30:00Z')
        ->writeAttribute('source', 'benchmark');

    for ($index = 1; $index <= $itemCount; $index++) {
        $writer
            ->startElement('book')
            ->writeAttribute('id', sprintf('bk-%05d', $index))
            ->writeAttribute('available', $index % 2 === 0)
            ->startElement('title')
            ->writeText(sprintf('Book %d', $index))
            ->endElement()
            ->startElement('author')
            ->writeText(sprintf('Author %d', $index % 41))
            ->endElement()
            ->startElement('price')
            ->writeAttribute('currency', 'EUR')
            ->writeText(sprintf('%0.2f', 19.5 + ($index % 11)))
            ->endElement()
            ->startElement('description')
            ->writeText(sprintf('Entry %d with escaped text & data.', $index))
            ->endElement()
            ->endElement();
    }

    $writer->endElement();
}

function buildCatalogWithDomDocument(int $itemCount): string
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $root = $dom->createElement('catalog');
    $root->setAttribute('generatedAt', '2026-04-17T10:30:00Z');
    $root->setAttribute('source', 'benchmark');
    $dom->appendChild($root);

    for ($index = 1; $index <= $itemCount; $index++) {
        $book = $dom->createElement('book');
        $book->setAttribute('id', sprintf('bk-%05d', $index));
        $book->setAttribute('available', $index % 2 === 0 ? 'true' : 'false');
        appendTextElement($dom, $book, 'title', sprintf('Book %d', $index));
        appendTextElement($dom, $book, 'author', sprintf('Author %d', $index % 41));

        $price = $dom->createElement('price');
        $price->setAttribute('currency', 'EUR');
        $price->appendChild($dom->createTextNode(sprintf('%0.2f', 19.5 + ($index % 11))));
        $book->appendChild($price);

        appendTextElement($dom, $book, 'description', sprintf('Entry %d with escaped text & data.', $index));
        $root->appendChild($book);
    }

    $xml = $dom->saveXML($dom->documentElement);

    if ($xml === false) {
        throw new RuntimeException('DOMDocument failed to serialize the catalog benchmark.');
    }

    return $xml;
}

function buildCatalogWithXmlWriter(int $itemCount): string
{
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->startElement('catalog');
    $writer->writeAttribute('generatedAt', '2026-04-17T10:30:00Z');
    $writer->writeAttribute('source', 'benchmark');

    for ($index = 1; $index <= $itemCount; $index++) {
        $writer->startElement('book');
        $writer->writeAttribute('id', sprintf('bk-%05d', $index));
        $writer->writeAttribute('available', $index % 2 === 0 ? 'true' : 'false');

        $writer->startElement('title');
        $writer->text(sprintf('Book %d', $index));
        $writer->endElement();

        $writer->startElement('author');
        $writer->text(sprintf('Author %d', $index % 41));
        $writer->endElement();

        $writer->startElement('price');
        $writer->writeAttribute('currency', 'EUR');
        $writer->text(sprintf('%0.2f', 19.5 + ($index % 11)));
        $writer->endElement();

        $writer->startElement('description');
        $writer->text(sprintf('Entry %d with escaped text & data.', $index));
        $writer->endElement();

        $writer->endElement();
    }

    $writer->endElement();

    return $writer->outputMemory(true);
}

function buildNamespaceHeavyDocument(int $entryCount): XmlDocument
{
    $feed = Xml::element(Xml::qname('feed', FEED_NS, 'atom'))
        ->declareNamespace('atom', FEED_NS)
        ->declareNamespace('dc', DC_NS)
        ->declareNamespace('media', MEDIA_NS)
        ->declareNamespace('xlink', XLINK_NS);

    for ($index = 1; $index <= $entryCount; $index++) {
        $feed = $feed->child(
            Xml::element(Xml::qname('entry', FEED_NS, 'atom'))
                ->attribute(
                    Xml::qname('href', XLINK_NS, 'xlink'),
                    sprintf('https://example.com/items/%d', $index),
                )
                ->child(Xml::element(Xml::qname('title', FEED_NS, 'atom'))->text(sprintf('Entry %d & more', $index)))
                ->child(Xml::element(Xml::qname('identifier', DC_NS, 'dc'))->text(sprintf('item-%05d', $index)))
                ->child(
                    Xml::element(Xml::qname('thumbnail', MEDIA_NS, 'media'))
                        ->attribute(Xml::qname('href', XLINK_NS, 'xlink'), sprintf('https://cdn.example.com/%d.jpg', $index))
                        ->attribute('width', 320)
                        ->attribute('height', 180),
                ),
        );
    }

    return Xml::document($feed)->withoutDeclaration();
}

function writeNamespaceHeavyWithStreamingWriter(StreamingXmlWriter $writer, int $entryCount): void
{
    $writer
        ->startElement(Xml::qname('feed', FEED_NS, 'atom'))
        ->declareNamespace('dc', DC_NS)
        ->declareNamespace('media', MEDIA_NS)
        ->declareNamespace('xlink', XLINK_NS);

    for ($index = 1; $index <= $entryCount; $index++) {
        $writer
            ->startElement(Xml::qname('entry', FEED_NS, 'atom'))
            ->writeAttribute(
                Xml::qname('href', XLINK_NS, 'xlink'),
                sprintf('https://example.com/items/%d', $index),
            )
            ->startElement(Xml::qname('title', FEED_NS, 'atom'))
            ->writeText(sprintf('Entry %d & more', $index))
            ->endElement()
            ->startElement(Xml::qname('identifier', DC_NS, 'dc'))
            ->writeText(sprintf('item-%05d', $index))
            ->endElement()
            ->startElement(Xml::qname('thumbnail', MEDIA_NS, 'media'))
            ->writeAttribute(
                Xml::qname('href', XLINK_NS, 'xlink'),
                sprintf('https://cdn.example.com/%d.jpg', $index),
            )
            ->writeAttribute('width', 320)
            ->writeAttribute('height', 180)
            ->endElement()
            ->endElement();
    }

    $writer->endElement();
}

function buildNamespaceHeavyWithDomDocument(int $entryCount): string
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $feed = $dom->createElementNS(FEED_NS, 'atom:feed');
    $feed->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', DC_NS);
    $feed->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:media', MEDIA_NS);
    $feed->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', XLINK_NS);
    $dom->appendChild($feed);

    for ($index = 1; $index <= $entryCount; $index++) {
        $entry = $dom->createElementNS(FEED_NS, 'atom:entry');
        $entry->setAttributeNS(
            XLINK_NS,
            'xlink:href',
            sprintf('https://example.com/items/%d', $index),
        );

        appendNamespacedTextElement($dom, $entry, FEED_NS, 'atom:title', sprintf('Entry %d & more', $index));
        appendNamespacedTextElement($dom, $entry, DC_NS, 'dc:identifier', sprintf('item-%05d', $index));

        $thumbnail = $dom->createElementNS(MEDIA_NS, 'media:thumbnail');
        $thumbnail->setAttributeNS(
            XLINK_NS,
            'xlink:href',
            sprintf('https://cdn.example.com/%d.jpg', $index),
        );
        $thumbnail->setAttribute('width', '320');
        $thumbnail->setAttribute('height', '180');
        $entry->appendChild($thumbnail);

        $feed->appendChild($entry);
    }

    $xml = $dom->saveXML($dom->documentElement);

    if ($xml === false) {
        throw new RuntimeException('DOMDocument failed to serialize the namespace-heavy benchmark.');
    }

    return $xml;
}

function buildNamespaceHeavyWithXmlWriter(int $entryCount): string
{
    $writer = new XMLWriter();
    $writer->openMemory();
    $writer->startElementNS('atom', 'feed', FEED_NS);
    $writer->writeAttribute('xmlns:dc', DC_NS);
    $writer->writeAttribute('xmlns:media', MEDIA_NS);
    $writer->writeAttribute('xmlns:xlink', XLINK_NS);

    for ($index = 1; $index <= $entryCount; $index++) {
        $writer->startElementNS('atom', 'entry', FEED_NS);
        $writer->writeAttributeNS('xlink', 'href', XLINK_NS, sprintf('https://example.com/items/%d', $index));

        $writer->startElementNS('atom', 'title', FEED_NS);
        $writer->text(sprintf('Entry %d & more', $index));
        $writer->endElement();

        $writer->startElementNS('dc', 'identifier', DC_NS);
        $writer->text(sprintf('item-%05d', $index));
        $writer->endElement();

        $writer->startElementNS('media', 'thumbnail', MEDIA_NS);
        $writer->writeAttributeNS('xlink', 'href', XLINK_NS, sprintf('https://cdn.example.com/%d.jpg', $index));
        $writer->writeAttribute('width', '320');
        $writer->writeAttribute('height', '180');
        $writer->endElement();

        $writer->endElement();
    }

    $writer->endElement();

    return $writer->outputMemory(true);
}

function appendTextElement(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
{
    $element = $dom->createElement($name);
    $element->appendChild($dom->createTextNode($value));
    $parent->appendChild($element);
}

function appendNamespacedTextElement(
    DOMDocument $dom,
    DOMElement $parent,
    string $namespaceUri,
    string $qualifiedName,
    string $value,
): void {
    $element = $dom->createElementNS($namespaceUri, $qualifiedName);
    $element->appendChild($dom->createTextNode($value));
    $parent->appendChild($element);
}

function assertEquivalentXml(
    string $expectedXml,
    string $expectedCanonicalXml,
    string $actualXml,
    string $scenarioLabel,
    string $implementationLabel,
): void {
    $actualCanonicalXml = canonicalizeXml($actualXml);

    if ($actualCanonicalXml !== $expectedCanonicalXml) {
        throw new RuntimeException(sprintf(
            'Implementation "%s" produced semantically different XML in scenario "%s".',
            $implementationLabel,
            $scenarioLabel,
        ));
    }

    if ($expectedXml === '' || $actualXml === '') {
        throw new RuntimeException(sprintf(
            'Implementation "%s" produced empty XML in scenario "%s".',
            $implementationLabel,
            $scenarioLabel,
        ));
    }
}

function canonicalizeXml(string $xml): string
{
    if (!class_exists(DOMDocument::class)) {
        return normalizeXmlString($xml);
    }

    $previousUseInternalErrors = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $dom = new DOMDocument('1.0', 'UTF-8');

    try {
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        $errors = libxml_get_errors();
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);
    }

    if ($loaded !== true) {
        $message = 'DOMDocument failed to load benchmark XML.';

        if ($errors !== []) {
            $message .= ' First libxml error: ' . trim((string) $errors[0]->message);
        }

        throw new RuntimeException($message);
    }

    $canonicalXml = $dom->C14N();

    if ($canonicalXml === false) {
        throw new RuntimeException('DOMDocument failed to canonicalize benchmark XML.');
    }

    return $canonicalXml;
}

function normalizeXmlString(string $xml): string
{
    return trim(str_replace(["\r\n", "\r"], "\n", $xml));
}
