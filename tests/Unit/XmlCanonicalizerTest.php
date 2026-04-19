<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use DOMDocument;
use Kalle\Xml\Canonicalization\CanonicalizationOptions;
use Kalle\Xml\Canonicalization\XmlCanonicalizer;
use Kalle\Xml\Exception\CanonicalizationException;
use Kalle\Xml\Exception\ParseException;
use PHPUnit\Framework\TestCase;

final class XmlCanonicalizerTest extends TestCase
{
    public function testCanonicalizationOptionsExcludeCommentsByDefault(): void
    {
        self::assertFalse((new CanonicalizationOptions())->includeComments());
        self::assertFalse(CanonicalizationOptions::withoutComments()->includeComments());
    }

    public function testCanonicalizationOptionsCanEnableCommentsImmutably(): void
    {
        $options = CanonicalizationOptions::withComments();

        self::assertTrue($options->includeComments());
        self::assertFalse($options->withIncludeComments(false)->includeComments());
        self::assertSame($options, $options->withIncludeComments(true));
    }

    public function testItRejectsDomDocumentsWithoutADocumentElement(): void
    {
        $this->expectException(CanonicalizationException::class);
        $this->expectExceptionMessage('requires a DOMDocument with a document element');

        XmlCanonicalizer::domDocument(new DOMDocument('1.0', 'UTF-8'));
    }

    public function testItRejectsMalformedXmlStrings(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Malformed XML in string input.');

        XmlCanonicalizer::xmlString('<catalog><book></catalog>');
    }
}
