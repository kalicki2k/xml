<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Builder\XmlBuilder;
use Kalle\Xml\Node\Element;
use Kalle\Xml\Node\TextNode;
use Kalle\Xml\Writer\XmlWriter;
use PHPUnit\Framework\TestCase;
use Stringable;
use TypeError;

use function array_map;

final class ElementTest extends TestCase
{
    public function testItAddsAttributesInADeterministicOrder(): void
    {
        $element = XmlBuilder::element('book')
            ->attribute('isbn', '9780132350884')
            ->attribute('available', true)
            ->attribute('title', new class () implements Stringable {
                public function __toString(): string
                {
                    return 'Clean Code';
                }
            });

        $attributes = $element->attributes();

        self::assertCount(3, $attributes);
        self::assertSame(['isbn', 'available', 'title'], array_map(
            static fn (Attribute $attribute): string => $attribute->name(),
            $attributes,
        ));
        self::assertSame(['9780132350884', 'true', 'Clean Code'], array_map(
            static fn (Attribute $attribute): string => $attribute->value(),
            $attributes,
        ));
    }

    public function testNullAttributeValueRemovesAnExistingAttribute(): void
    {
        $element = XmlBuilder::element('book')
            ->attribute('isbn', '9780132350884')
            ->attribute('format', 'hardcover');

        $updated = $element->attribute('isbn', null);

        self::assertSame(['isbn', 'format'], array_map(
            static fn (Attribute $attribute): string => $attribute->name(),
            $element->attributes(),
        ));
        self::assertSame(['format'], array_map(
            static fn (Attribute $attribute): string => $attribute->name(),
            $updated->attributes(),
        ));
    }

    public function testWithoutAttributeProvidesAClearerRemovalApi(): void
    {
        $element = XmlBuilder::element('book')
            ->attribute('isbn', '9780132350884')
            ->attribute('format', 'hardcover');

        $updated = $element->withoutAttribute('isbn');

        self::assertSame(['isbn', 'format'], array_map(
            static fn (Attribute $attribute): string => $attribute->name(),
            $element->attributes(),
        ));
        self::assertSame(['format'], array_map(
            static fn (Attribute $attribute): string => $attribute->name(),
            $updated->attributes(),
        ));
    }

    public function testWithoutAttributeReturnsSameInstanceWhenNothingChanges(): void
    {
        $element = XmlBuilder::element('book')->attribute('isbn', '9780132350884');

        self::assertSame($element, $element->withoutAttribute('format'));
    }

    public function testReplacingAnExistingAttributePreservesItsPosition(): void
    {
        $element = XmlBuilder::element('book')
            ->attribute('isbn', '9780132350884')
            ->attribute('format', 'hardcover')
            ->attribute('isbn', '9780132350885');

        self::assertSame(['isbn', 'format'], array_map(
            static fn (Attribute $attribute): string => $attribute->name(),
            $element->attributes(),
        ));
        self::assertSame(['9780132350885', 'hardcover'], array_map(
            static fn (Attribute $attribute): string => $attribute->value(),
            $element->attributes(),
        ));
    }

    public function testSettingTheSameAttributeValueReturnsTheSameInstance(): void
    {
        $element = XmlBuilder::element('book')->attribute('isbn', '9780132350884');

        self::assertSame($element, $element->attribute('isbn', '9780132350884'));
    }

    public function testItAddsChildElementsAndTextNodes(): void
    {
        $element = XmlBuilder::element('book')
            ->child(XmlBuilder::element('title')->text('Clean Code'))
            ->text(' second edition');

        $children = $element->children();

        self::assertCount(2, $children);
        self::assertInstanceOf(Element::class, $children[0]);
        self::assertInstanceOf(TextNode::class, $children[1]);
        self::assertSame(' second edition', $children[1]->content());
    }

    public function testFluentOperationsDoNotMutateTheOriginalElement(): void
    {
        $original = XmlBuilder::element('book');
        $updated = $original
            ->attribute('isbn', '9780132350884')
            ->child(XmlBuilder::text('Clean Code'));

        self::assertNotSame($original, $updated);
        self::assertCount(0, $original->attributes());
        self::assertCount(0, $original->children());
        self::assertCount(1, $updated->attributes());
        self::assertCount(1, $updated->children());
    }

    public function testConstructorTypeErrorsIncludeTheElementNameForAttributes(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Element "book" attributes');

        new Element('book', ['isbn']);
    }

    public function testConstructorTypeErrorsIncludeTheElementNameForChildren(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Element "book" children');

        new Element('book', children: ['title']);
    }

    public function testConstructorTypeErrorsIncludeTheElementNameForNamespaceDeclarations(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Element "book" namespace declarations');

        new Element('book', namespaceDeclarations: ['urn:catalog']);
    }

    public function testFluentChainingBuildsTheExpectedElementTree(): void
    {
        $element = XmlBuilder::element('book')
            ->attribute('isbn', '9780132350884')
            ->attribute('available', true)
            ->child(XmlBuilder::element('title')->text('Clean Code'))
            ->child(XmlBuilder::element('price')->attribute('currency', 'EUR')->text('39.90'));
        $title = $element->children()[0];
        $price = $element->children()[1];

        self::assertSame('book', $element->name());
        self::assertCount(2, $element->attributes());
        self::assertCount(2, $element->children());
        self::assertInstanceOf(Element::class, $title);
        self::assertInstanceOf(Element::class, $price);
        self::assertSame('title', $title->name());
        self::assertSame('price', $price->name());
    }

    public function testNamespaceAwareFluentChainingBuildsExpectedMetadata(): void
    {
        $element = XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
            ->attribute(XmlBuilder::qname('href', 'urn:xlink', 'xlink'), 'https://example.com/items/1')
            ->declareNamespace('media', 'urn:media')
            ->child(XmlBuilder::element(XmlBuilder::qname('thumbnail', 'urn:media', 'media')));

        self::assertSame('entry', $element->name());
        self::assertSame('urn:feed', $element->namespaceUri());
        self::assertNull($element->prefix());
        self::assertCount(1, $element->namespaceDeclarations());
        self::assertSame('media', $element->namespaceDeclarations()[0]->prefix());
        self::assertSame('xlink:href', $element->attributes()[0]->name());
        self::assertSame('urn:xlink', $element->attributes()[0]->namespaceUri());
    }

    public function testRedeclaringTheSameNamespaceReturnsTheSameInstance(): void
    {
        $element = XmlBuilder::element(XmlBuilder::qname('entry', 'urn:feed'))
            ->declareNamespace('media', 'urn:media');

        self::assertSame($element, $element->declareNamespace('media', 'urn:media'));
    }

    public function testNamespacedAttributesCanBeRemovedUsingQualifiedNameIdentity(): void
    {
        $attributeName = XmlBuilder::qname('href', 'urn:xlink', 'xlink');
        $element = XmlBuilder::element('link')->attribute($attributeName, 'https://example.com/items/1');

        $updated = $element->withoutAttribute($attributeName);

        self::assertCount(1, $element->attributes());
        self::assertCount(0, $updated->attributes());
    }

    public function testTextNodeContentRemainsRawUntilSerialization(): void
    {
        $document = XmlBuilder::document(XmlBuilder::element('summary')->text('Fish & Chips <3'));
        $textNode = $document->root()->children()[0];

        self::assertInstanceOf(TextNode::class, $textNode);

        self::assertSame('Fish & Chips <3', $textNode->content());
        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><summary>Fish &amp; Chips &lt;3</summary>',
            XmlWriter::toString($document),
        );
    }
}
