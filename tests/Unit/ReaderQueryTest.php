<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Exception\InvalidQueryException;
use Kalle\Xml\Exception\UnknownQueryNamespacePrefixException;
use Kalle\Xml\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

final class ReaderQueryTest extends TestCase
{
    public function testItRejectsEmptyXPathExpressions(): void
    {
        $document = XmlReader::fromString('<catalog/>');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('cannot be empty');

        $document->findAll('');
    }

    public function testItRejectsInvalidXPathExpressions(): void
    {
        $document = XmlReader::fromString('<catalog><book/></catalog>');

        try {
            $document->findAll('//book[');
            self::fail('Expected an InvalidQueryException.');
        } catch (InvalidQueryException $exception) {
            self::assertStringContainsString('Invalid XPath query', $exception->getMessage());
            self::assertStringContainsString('//book[', $exception->getMessage());
            self::assertStringNotContainsString('DOMXPath::query()', $exception->getMessage());
        }
    }

    public function testItRejectsDefaultNamespaceAliasesWithoutAnExplicitPrefix(): void
    {
        $document = XmlReader::fromString('<feed xmlns="urn:feed"><entry/></feed>');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('non-empty prefix');

        $document->findAll('//feed:entry', ['' => 'urn:feed']);
    }

    public function testItRejectsQueriesWithUnknownNamespacePrefixes(): void
    {
        $document = XmlReader::fromString('<feed xmlns:atom="urn:feed"><atom:entry/></feed>');

        $this->expectException(UnknownQueryNamespacePrefixException::class);
        $this->expectExceptionMessage('Unknown XPath namespace prefix');
        $this->expectExceptionMessage('//missing:entry');

        $document->findAll('//missing:entry');
    }

    public function testItRejectsNonElementNodeSelectionsForElementQueries(): void
    {
        $document = XmlReader::fromString('<catalog><book isbn="9780132350884"/></catalog>');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('must select elements');

        $document->findAll('//@isbn');
    }

    public function testFindFirstAlsoRejectsNonElementNodeSelections(): void
    {
        $document = XmlReader::fromString('<catalog><book isbn="9780132350884"/></catalog>');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('must select elements');

        $document->findFirst('//@isbn');
    }

    public function testItReturnsEmptyQueryResults(): void
    {
        $document = XmlReader::fromString('<catalog><book/></catalog>');

        self::assertSame([], $document->findAll('//magazine'));
        self::assertNull($document->findFirst('//magazine'));
    }
}
