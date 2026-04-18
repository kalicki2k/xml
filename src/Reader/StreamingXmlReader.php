<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMDocument;
use DOMElement;
use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Exception\FileReadException;
use Kalle\Xml\Exception\ParseException;
use Kalle\Xml\Exception\StreamingReaderException;
use Kalle\Xml\Exception\StreamReadException;
use Kalle\Xml\Name\QualifiedName;
use XMLReader as PhpXmlReader;

use function fclose;
use function fopen;
use function get_debug_type;
use function get_resource_type;
use function is_resource;
use function is_string;
use function sprintf;

final class StreamingXmlReader
{
    private bool $closed = false;

    private bool $hasCurrentNode = false;

    private function __construct(
        private readonly PhpXmlReader $reader,
        private readonly string $sourceLabel,
        private readonly ?string $streamUri = null,
    ) {}

    public function __destruct()
    {
        $this->close();
    }

    /** @phpstan-impure */
    public static function fromFile(string $path): self
    {
        if ($path === '') {
            throw new FileReadException('StreamingXmlReader::fromFile() requires a non-empty path.');
        }

        [$handle, $readError] = ReaderSupport::captureOperation(static fn () => fopen($path, 'rb'));

        if (!is_resource($handle) || $readError !== null) {
            $message = sprintf('StreamingXmlReader::fromFile() could not read XML file "%s".', $path);

            if ($readError !== null) {
                $message = sprintf(
                    'StreamingXmlReader::fromFile() could not read XML file "%s": %s',
                    $path,
                    $readError,
                );
            }

            throw new FileReadException($message);
        }

        fclose($handle);

        $reader = new PhpXmlReader();
        [$result, $errors] = ReaderSupport::captureLibxmlErrors(
            static fn () => ReaderSupport::captureOperation(
                static fn () => $reader->open($path, null, LIBXML_NONET),
            ),
        );
        [$opened, $openError] = $result;

        if ($opened !== true) {
            throw new FileReadException(self::buildOpenFailureMessage(
                sprintf('StreamingXmlReader::fromFile() could not read XML file "%s".', $path),
                $openError,
                $errors,
            ));
        }

        return new self($reader, sprintf('file "%s"', $path));
    }

    /** @phpstan-impure */
    public static function fromStream(mixed $stream): self
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new StreamReadException(sprintf(
                'StreamingXmlReader::fromStream() requires a readable stream resource; %s given.',
                get_debug_type($stream),
            ));
        }

        if (!ReaderSupport::isReadableStream($stream)) {
            throw new StreamReadException(sprintf(
                'StreamingXmlReader::fromStream() requires a readable stream resource; %s is not readable.',
                ReaderSupport::describeStream($stream),
            ));
        }

        $reader = new PhpXmlReader();
        $sourceLabel = ReaderSupport::describeStream($stream);
        $streamUri = StreamingReaderStreamRegistry::register($stream);

        try {
            [$result, $errors] = ReaderSupport::captureLibxmlErrors(
                static fn () => ReaderSupport::captureOperation(
                    static fn () => $reader->open($streamUri, null, LIBXML_NONET),
                ),
            );
            [$opened, $openError] = $result;

            if ($opened !== true) {
                throw new StreamReadException(self::buildOpenFailureMessage(
                    sprintf('StreamingXmlReader::fromStream() could not read XML from %s.', $sourceLabel),
                    $openError,
                    $errors,
                ));
            }
        } catch (\Throwable $exception) {
            StreamingReaderStreamRegistry::unregister($streamUri);

            throw $exception;
        }

        return new self($reader, $sourceLabel, $streamUri);
    }

    /** @phpstan-impure */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->hasCurrentNode = false;
        $this->reader->close();

        if ($this->streamUri !== null) {
            StreamingReaderStreamRegistry::unregister($this->streamUri);
        }

        $this->closed = true;
    }

    public function isOpen(): bool
    {
        return !$this->closed;
    }

    public function hasCurrentNode(): bool
    {
        return !$this->closed && $this->hasCurrentNode;
    }

    /** @phpstan-impure */
    public function read(): bool
    {
        $this->requireOpen('read');
        [$readResult, $errors] = ReaderSupport::captureLibxmlErrors(
            fn () => $this->reader->read(),
        );

        if ($readResult === true) {
            $this->hasCurrentNode = true;

            return true;
        }

        $this->hasCurrentNode = false;

        if (ReaderSupport::hasParseErrors($errors)) {
            $this->close();

            throw new ParseException(
                'Malformed XML in ' . $this->sourceLabel . '. ' . ReaderSupport::formatLibxmlError($errors[0]),
            );
        }

        return false;
    }

    /** @phpstan-impure */
    public function nodeType(): ?StreamingNodeType
    {
        if (!$this->hasCurrentNode()) {
            return null;
        }

        return StreamingNodeType::fromNative($this->reader->nodeType);
    }

    /** @phpstan-impure */
    public function depth(): ?int
    {
        if (!$this->hasCurrentNode()) {
            return null;
        }

        return $this->reader->depth;
    }

    /** @phpstan-impure */
    public function name(): ?string
    {
        return $this->normalizeLexicalValue($this->reader->name);
    }

    /** @phpstan-impure */
    public function localName(): ?string
    {
        return $this->normalizeLexicalValue($this->reader->localName);
    }

    /** @phpstan-impure */
    public function prefix(): ?string
    {
        return $this->normalizeLexicalValue($this->reader->prefix);
    }

    /** @phpstan-impure */
    public function namespaceUri(): ?string
    {
        return $this->normalizeLexicalValue($this->reader->namespaceURI);
    }

    /** @phpstan-impure */
    public function value(): ?string
    {
        if (!$this->hasCurrentNode()) {
            return null;
        }

        return $this->reader->value;
    }

    /** @phpstan-impure */
    public function isStartElement(string|QualifiedName|null $name = null): bool
    {
        if (!$this->hasCurrentNode() || $this->reader->nodeType !== PhpXmlReader::ELEMENT) {
            return false;
        }

        if ($name === null) {
            return true;
        }

        return self::currentQualifiedName($this->reader)->identityKey() === QualifiedName::forElement($name)->identityKey();
    }

    /** @phpstan-impure */
    public function isEndElement(string|QualifiedName|null $name = null): bool
    {
        if (!$this->hasCurrentNode() || $this->reader->nodeType !== PhpXmlReader::END_ELEMENT) {
            return false;
        }

        if ($name === null) {
            return true;
        }

        return self::currentQualifiedName($this->reader)->identityKey() === QualifiedName::forElement($name)->identityKey();
    }

    /** @phpstan-impure */
    public function isText(): bool
    {
        return $this->hasCurrentNode() && $this->reader->nodeType === PhpXmlReader::TEXT;
    }

    /** @phpstan-impure */
    public function isComment(): bool
    {
        return $this->hasCurrentNode() && $this->reader->nodeType === PhpXmlReader::COMMENT;
    }

    /** @phpstan-impure */
    public function isCdata(): bool
    {
        return $this->hasCurrentNode() && $this->reader->nodeType === PhpXmlReader::CDATA;
    }

    /** @phpstan-impure */
    public function isEmptyElement(): bool
    {
        return $this->isStartElement() && $this->reader->isEmptyElement;
    }

    /**
     * @phpstan-impure
     * @return list<Attribute>
     */
    public function attributes(): array
    {
        if (!$this->isStartElement() || !$this->reader->hasAttributes) {
            return [];
        }

        $attributes = [];

        if (!$this->reader->moveToFirstAttribute()) {
            return [];
        }

        do {
            $attribute = self::currentAttribute($this->reader);

            if ($attribute !== null) {
                $attributes[] = $attribute;
            }
        } while ($this->reader->moveToNextAttribute());

        $this->reader->moveToElement();

        return $attributes;
    }

    /** @phpstan-impure */
    public function hasAttribute(string|QualifiedName $name): bool
    {
        return $this->attribute($name) !== null;
    }

    /** @phpstan-impure */
    public function attribute(string|QualifiedName $name): ?Attribute
    {
        if (!$this->isStartElement()) {
            return null;
        }

        $qualifiedName = QualifiedName::forAttribute($name);

        foreach ($this->attributes() as $attribute) {
            if ($attribute->qualifiedName()->identityKey() === $qualifiedName->identityKey()) {
                return $attribute;
            }
        }

        return null;
    }

    /** @phpstan-impure */
    public function attributeValue(string|QualifiedName $name): ?string
    {
        return $this->attribute($name)?->value();
    }

    /** @phpstan-impure */
    public function extractElementXml(): string
    {
        $element = $this->materializeCurrentElement('extractElementXml');
        $document = $element->ownerDocument;

        if (!$document instanceof DOMDocument) {
            throw new StreamingReaderException(
                'StreamingXmlReader::extractElementXml() could not access the materialized owner document.',
            );
        }

        $xml = $document->saveXML($element);

        if (!is_string($xml)) {
            throw new StreamingReaderException(
                'StreamingXmlReader::extractElementXml() could not serialize the current element subtree.',
            );
        }

        return $xml;
    }

    /** @phpstan-impure */
    public function expandElement(): ReaderElement
    {
        return ReaderElement::fromDomElement(
            $this->materializeCurrentElement('expandElement'),
        );
    }

    /**
     * @param list<\LibXMLError> $errors
     */
    private static function buildOpenFailureMessage(
        string $baseMessage,
        ?string $operationError,
        array $errors,
    ): string {
        if ($operationError !== null) {
            return $baseMessage . ' ' . $operationError;
        }

        if ($errors !== []) {
            return $baseMessage . ' ' . ReaderSupport::formatLibxmlError($errors[0]);
        }

        return $baseMessage;
    }

    private static function currentQualifiedName(PhpXmlReader $reader): QualifiedName
    {
        return new QualifiedName(
            $reader->localName !== '' ? $reader->localName : $reader->name,
            $reader->namespaceURI !== '' ? $reader->namespaceURI : null,
            $reader->prefix !== '' ? $reader->prefix : null,
        );
    }

    private static function currentAttribute(PhpXmlReader $reader): ?Attribute
    {
        if ($reader->namespaceURI === QualifiedName::XMLNS_NAMESPACE_URI) {
            return null;
        }

        return new Attribute(
            self::currentQualifiedName($reader),
            $reader->value,
        );
    }

    private function normalizeLexicalValue(string $value): ?string
    {
        if (!$this->hasCurrentNode()) {
            return null;
        }

        return $value !== '' ? $value : null;
    }

    private function materializeCurrentElement(string $method): DOMElement
    {
        $this->requireOpen($method);

        if (!$this->isStartElement()) {
            throw new StreamingReaderException(sprintf(
                'StreamingXmlReader::%s() requires the cursor to be positioned on a start element.',
                $method,
            ));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        [$expandedNode, $errors] = ReaderSupport::captureLibxmlErrors(
            fn () => $this->reader->expand($document),
        );

        if (ReaderSupport::hasParseErrors($errors)) {
            $this->close();

            throw new ParseException(
                'Malformed XML in ' . $this->sourceLabel . '. ' . ReaderSupport::formatLibxmlError($errors[0]),
            );
        }

        if (!$expandedNode instanceof DOMElement) {
            throw new StreamingReaderException(sprintf(
                'StreamingXmlReader::%s() could not materialize the current element subtree.',
                $method,
            ));
        }

        return $expandedNode;
    }

    private function requireOpen(string $method): void
    {
        if (!$this->closed) {
            return;
        }

        throw new StreamingReaderException(sprintf(
            'StreamingXmlReader::%s() cannot be used after the reader was closed.',
            $method,
        ));
    }
}
