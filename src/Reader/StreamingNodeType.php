<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use LogicException;
use XMLReader as PhpXmlReader;

enum StreamingNodeType: int
{
    case None = PhpXmlReader::NONE;
    case Element = PhpXmlReader::ELEMENT;
    case Attribute = PhpXmlReader::ATTRIBUTE;
    case Text = PhpXmlReader::TEXT;
    case Cdata = PhpXmlReader::CDATA;
    case EntityReference = PhpXmlReader::ENTITY_REF;
    case Entity = PhpXmlReader::ENTITY;
    case ProcessingInstruction = PhpXmlReader::PI;
    case Comment = PhpXmlReader::COMMENT;
    case Document = PhpXmlReader::DOC;
    case DocumentType = PhpXmlReader::DOC_TYPE;
    case DocumentFragment = PhpXmlReader::DOC_FRAGMENT;
    case Notation = PhpXmlReader::NOTATION;
    case Whitespace = PhpXmlReader::WHITESPACE;
    case SignificantWhitespace = PhpXmlReader::SIGNIFICANT_WHITESPACE;
    case EndElement = PhpXmlReader::END_ELEMENT;
    case EndEntity = PhpXmlReader::END_ENTITY;
    case XmlDeclaration = PhpXmlReader::XML_DECLARATION;

    public static function fromNative(int $nodeType): self
    {
        return match ($nodeType) {
            PhpXmlReader::NONE => self::None,
            PhpXmlReader::ELEMENT => self::Element,
            PhpXmlReader::ATTRIBUTE => self::Attribute,
            PhpXmlReader::TEXT => self::Text,
            PhpXmlReader::CDATA => self::Cdata,
            PhpXmlReader::ENTITY_REF => self::EntityReference,
            PhpXmlReader::ENTITY => self::Entity,
            PhpXmlReader::PI => self::ProcessingInstruction,
            PhpXmlReader::COMMENT => self::Comment,
            PhpXmlReader::DOC => self::Document,
            PhpXmlReader::DOC_TYPE => self::DocumentType,
            PhpXmlReader::DOC_FRAGMENT => self::DocumentFragment,
            PhpXmlReader::NOTATION => self::Notation,
            PhpXmlReader::WHITESPACE => self::Whitespace,
            PhpXmlReader::SIGNIFICANT_WHITESPACE => self::SignificantWhitespace,
            PhpXmlReader::END_ELEMENT => self::EndElement,
            PhpXmlReader::END_ENTITY => self::EndEntity,
            PhpXmlReader::XML_DECLARATION => self::XmlDeclaration,
            default => throw new LogicException(sprintf(
                'Unsupported XMLReader node type %d.',
                $nodeType,
            )),
        };
    }
}
