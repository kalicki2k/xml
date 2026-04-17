<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Exception\ImportException;
use Kalle\Xml\Import\XmlImporter;
use Kalle\Xml\Reader\XmlReader;
use PHPUnit\Framework\TestCase;

final class XmlImporterTest extends TestCase
{
    public function testItRejectsDoctypeBackedDocumentImports(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<!DOCTYPE catalog [
    <!ELEMENT catalog ANY>
]>
<catalog/>
XML,
        );

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('DOCTYPE declaration');

        XmlImporter::document($document);
    }

    public function testItRejectsDocumentLevelProcessingInstructions(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<?xml-stylesheet href="catalog.xsl" type="text/xsl"?>
<catalog/>
XML,
        );

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('document-level processing instructions');

        XmlImporter::document($document);
    }

    public function testItRejectsDocumentLevelComments(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<!--generated export-->
<catalog/>
XML,
        );

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('document-level comments');

        XmlImporter::document($document);
    }

    public function testItRejectsEntityReferencesInsideImportedElements(): void
    {
        $document = XmlReader::fromString(
            <<<'XML'
<!DOCTYPE catalog [
    <!ENTITY title "Clean Code">
]>
<catalog>&title;</catalog>
XML,
        );

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('entity references');

        XmlImporter::element($document->rootElement());
    }
}
