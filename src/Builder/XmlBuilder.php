<?php

declare(strict_types=1);

namespace Kalle\Xml\Builder;

use Kalle\Xml\Document\XmlDeclaration;
use Kalle\Xml\Document\XmlDocument;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Node\CDataNode;
use Kalle\Xml\Node\CommentNode;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Node\ProcessingInstructionNode;
use Kalle\Xml\Node\TextNode;

final class XmlBuilder
{
    private function __construct() {}

    public static function document(Element $root): XmlDocument
    {
        return new XmlDocument($root, new XmlDeclaration());
    }

    public static function element(string|QualifiedName $name): Element
    {
        return new Element($name);
    }

    public static function qname(
        string $localName,
        ?string $namespaceUri = null,
        ?string $prefix = null,
    ): QualifiedName {
        return new QualifiedName($localName, $namespaceUri, $prefix);
    }

    public static function text(string $content): TextNode
    {
        return new TextNode($content);
    }

    public static function cdata(string $content): CDataNode
    {
        return new CDataNode($content);
    }

    public static function comment(string $content): CommentNode
    {
        return new CommentNode($content);
    }

    public static function processingInstruction(string $target, string $data = ''): ProcessingInstructionNode
    {
        return new ProcessingInstructionNode($target, $data);
    }

    public static function declaration(
        string $version = '1.0',
        ?string $encoding = 'UTF-8',
        ?bool $standalone = null,
    ): XmlDeclaration {
        return new XmlDeclaration($version, $encoding, $standalone);
    }
}
