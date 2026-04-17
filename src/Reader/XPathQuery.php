<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\InvalidNamespaceDeclarationException;
use Kalle\Xml\Exception\InvalidQueryException;
use Kalle\Xml\Exception\InvalidXmlCharacter;
use Kalle\Xml\Exception\UnknownQueryNamespacePrefixException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use ValueError;

use function get_debug_type;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * @internal Reader-side XPath bridge for the compact query API.
 */
final readonly class XPathQuery
{
    private function __construct(
        private DOMDocument $document,
        private DOMNode $contextNode,
        private DOMElement $namespaceSource,
        private string $contextLabel,
    ) {}

    public static function forDocument(DOMDocument $document): self
    {
        $rootElement = $document->documentElement;

        if (!$rootElement instanceof DOMElement) {
            throw new InvalidQueryException('XPath queries require a document element.');
        }

        return new self($document, $document, $rootElement, 'document');
    }

    public static function forElement(DOMElement $element): self
    {
        $document = $element->ownerDocument;

        if (!$document instanceof DOMDocument) {
            throw new InvalidQueryException(sprintf(
                'XPath queries require a document for element "%s".',
                $element->tagName,
            ));
        }

        return new self(
            $document,
            $element,
            $element,
            sprintf('element "%s"', $element->tagName),
        );
    }

    /**
     * @param array<string, string> $namespaces
     *
     * @return list<ReaderElement>
     */
    public function findAll(string $expression, array $namespaces = []): array
    {
        $nodes = $this->executeNodeQuery($expression, $namespaces);
        $elements = [];

        foreach ($nodes as $node) {
            $elements[] = $this->readerElementFromQueryNode($node, $expression);
        }

        return $elements;
    }

    /**
     * @param array<string, string> $namespaces
     */
    public function findFirst(string $expression, array $namespaces = []): ?ReaderElement
    {
        $node = $this->executeFirstNodeQuery($expression, $namespaces);

        if ($node !== null) {
            return $this->readerElementFromQueryNode($node, $expression);
        }

        return null;
    }

    /**
     * @param array<string, string> $namespaces
     */
    private function createXPath(array $namespaces): DOMXPath
    {
        $xpath = new DOMXPath($this->document);
        $this->registerNamespaceDeclaration(
            $xpath,
            new NamespaceDeclaration('xml', QualifiedName::XML_NAMESPACE_URI),
        );

        foreach (DomNamespaceInspector::namespacesInScope($this->namespaceSource) as $declaration) {
            if ($declaration->isDefault()) {
                continue;
            }

            $this->registerNamespaceDeclaration($xpath, $declaration);
        }

        foreach ($namespaces as $prefix => $uri) {
            $this->registerExplicitNamespace($xpath, $prefix, $uri);
        }

        return $xpath;
    }

    private function registerExplicitNamespace(DOMXPath $xpath, mixed $prefix, mixed $uri): void
    {
        if (!is_string($prefix)) {
            throw new InvalidQueryException(sprintf(
                'XPath query namespaces require string prefixes; %s given.',
                get_debug_type($prefix),
            ));
        }

        if ($prefix === '') {
            throw new InvalidQueryException(
                'XPath query namespaces require a non-empty prefix. Map default namespaces to an explicit alias such as "feed".',
            );
        }

        if (!is_string($uri)) {
            throw new InvalidQueryException(sprintf(
                'XPath query namespace URI for prefix "%s" must be a string; %s given.',
                $prefix,
                get_debug_type($uri),
            ));
        }

        try {
            XmlEscaper::assertValidString($uri, sprintf('XPath query namespace URI for prefix "%s"', $prefix));
            $declaration = new NamespaceDeclaration($prefix, $uri);
        } catch (InvalidNamespaceDeclarationException|InvalidXmlCharacter|ValueError $exception) {
            throw new InvalidQueryException($exception->getMessage(), previous: $exception);
        }

        $this->registerNamespaceDeclaration($xpath, $declaration);
    }

    private function registerNamespaceDeclaration(DOMXPath $xpath, NamespaceDeclaration $declaration): void
    {
        if ($declaration->isDefault()) {
            return;
        }

        [$registered, $error] = $this->captureXPath(
            static fn () => $xpath->registerNamespace($declaration->prefix() ?? '', $declaration->uri()),
        );

        if ($registered === true) {
            return;
        }

        $error = $this->normalizeXPathError($error);
        $message = sprintf(
            'Cannot register XPath namespace prefix "%s" for %s.',
            $declaration->prefix(),
            $this->contextLabel,
        );

        if ($error !== null) {
            $message .= ' ' . $error;
        }

        throw new InvalidQueryException($message);
    }

    private function assertNonEmptyExpression(string $expression): void
    {
        if ($expression !== '') {
            return;
        }

        throw new InvalidQueryException(sprintf(
            'XPath query expression cannot be empty for %s.',
            $this->contextLabel,
        ));
    }

    /**
     * @param array<string, string> $namespaces
     *
     * @return list<mixed>
     */
    private function executeNodeQuery(string $expression, array $namespaces): array
    {
        $this->assertNonEmptyExpression($expression);

        $xpath = $this->createXPath($namespaces);
        [$result, $error] = $this->captureXPath(fn () => $xpath->query($expression, $this->contextNode));

        if ($result instanceof DOMNodeList) {
            $nodes = [];

            foreach ($result as $node) {
                $nodes[] = $node;
            }

            return $nodes;
        }

        $this->throwForQueryError($expression, $error);
    }

    /**
     * @param array<string, string> $namespaces
     */
    private function executeFirstNodeQuery(string $expression, array $namespaces): mixed
    {
        $this->assertNonEmptyExpression($expression);

        $xpath = $this->createXPath($namespaces);
        [$result, $error] = $this->captureXPath(fn () => $xpath->query($expression, $this->contextNode));

        if ($result instanceof DOMNodeList) {
            foreach ($result as $node) {
                return $node;
            }

            return null;
        }

        $this->throwForQueryError($expression, $error);
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $operation
     *
     * @return array{0: TResult|false|null, 1: ?string}
     */
    private function captureXPath(callable $operation): array
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

    private function readerElementFromQueryNode(mixed $node, string $expression): ReaderElement
    {
        if ($node instanceof DOMElement) {
            return ReaderElement::fromDomElement($node);
        }

        throw new InvalidQueryException(sprintf(
            'XPath query "%s" for %s must select elements.',
            $expression,
            $this->contextLabel,
        ));
    }

    private function normalizeXPathError(?string $error): ?string
    {
        if ($error === null) {
            return null;
        }

        foreach (['DOMXPath::query(): ', 'DOMXPath::registerNamespace(): '] as $prefix) {
            if (str_starts_with($error, $prefix)) {
                return trim(substr($error, strlen($prefix)));
            }
        }

        return trim($error);
    }

    private function throwForQueryError(string $expression, ?string $error): never
    {
        $error = $this->normalizeXPathError($error);

        if ($error !== null && str_contains($error, 'Undefined namespace prefix')) {
            throw new UnknownQueryNamespacePrefixException(sprintf(
                'Unknown XPath namespace prefix in query "%s" for %s.',
                $expression,
                $this->contextLabel,
            ));
        }

        $message = sprintf(
            'Invalid XPath query "%s" for %s.',
            $expression,
            $this->contextLabel,
        );

        if ($error !== null) {
            $message .= ' ' . $error;
        }

        throw new InvalidQueryException($message);
    }
}
