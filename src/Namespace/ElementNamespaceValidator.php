<?php

declare(strict_types=1);

namespace Kalle\Xml\Namespace;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Exception\InvalidNamespaceDeclarationException;
use Kalle\Xml\Name\QualifiedName;

use function array_key_exists;
use function sprintf;

final class ElementNamespaceValidator
{
    private function __construct() {}

    /**
     * @param array<string, Attribute> $attributes
     * @param array<string, NamespaceDeclaration> $namespaceDeclarations
     */
    public static function assertCoherent(
        QualifiedName $elementName,
        array $attributes,
        array $namespaceDeclarations,
    ): void {
        self::assertDefaultNamespaceMatchesElement($elementName, $namespaceDeclarations);

        $requiredPrefixMappings = [];

        self::collectRequiredPrefixMapping(
            $requiredPrefixMappings,
            $elementName->prefix(),
            $elementName->namespaceUri(),
            sprintf('element "%s"', $elementName->lexicalName()),
        );

        foreach ($attributes as $attribute) {
            self::collectRequiredPrefixMapping(
                $requiredPrefixMappings,
                $attribute->prefix(),
                $attribute->namespaceUri(),
                sprintf('attribute "%s"', $attribute->name()),
            );
        }

        foreach ($requiredPrefixMappings as $prefix => $mapping) {
            $declaration = $namespaceDeclarations[$prefix] ?? null;

            if ($declaration === null || $declaration->uri() === $mapping['uri']) {
                continue;
            }

            throw new InvalidNamespaceDeclarationException(sprintf(
                'Element "%s" uses prefix "%s" for "%s" but declares it as "%s".',
                $elementName->lexicalName(),
                $prefix,
                $mapping['uri'],
                $declaration->uri(),
            ));
        }
    }

    /**
     * @param array<string, NamespaceDeclaration> $namespaceDeclarations
     */
    private static function assertDefaultNamespaceMatchesElement(
        QualifiedName $elementName,
        array $namespaceDeclarations,
    ): void {
        $defaultDeclaration = $namespaceDeclarations[''] ?? null;

        if ($defaultDeclaration === null) {
            return;
        }

        $elementNamespaceUri = $elementName->namespaceUri();

        if ($elementName->prefix() === null && $elementNamespaceUri === null && $defaultDeclaration->uri() !== '') {
            throw new InvalidNamespaceDeclarationException(sprintf(
                'Unqualified element "%s" cannot declare default namespace "%s".',
                $elementName->lexicalName(),
                $defaultDeclaration->uri(),
            ));
        }

        if ($elementName->prefix() === null && $elementNamespaceUri !== null && $defaultDeclaration->uri() !== $elementNamespaceUri) {
            throw new InvalidNamespaceDeclarationException(sprintf(
                'Element "%s" requires default namespace "%s" but declares "%s".',
                $elementName->lexicalName(),
                $elementNamespaceUri,
                $defaultDeclaration->uri(),
            ));
        }
    }

    /**
     * @param array<string, array{context: string, uri: string}> $requiredPrefixMappings
     */
    private static function collectRequiredPrefixMapping(
        array &$requiredPrefixMappings,
        ?string $prefix,
        ?string $namespaceUri,
        string $context,
    ): void {
        if ($prefix === null || $namespaceUri === null) {
            return;
        }

        if (!array_key_exists($prefix, $requiredPrefixMappings)) {
            $requiredPrefixMappings[$prefix] = [
                'context' => $context,
                'uri' => $namespaceUri,
            ];

            return;
        }

        if ($requiredPrefixMappings[$prefix]['uri'] === $namespaceUri) {
            return;
        }

        throw new InvalidNamespaceDeclarationException(sprintf(
            'Prefix "%s" is used for both "%s" in %s and "%s" in %s.',
            $prefix,
            $requiredPrefixMappings[$prefix]['uri'],
            $requiredPrefixMappings[$prefix]['context'],
            $namespaceUri,
            $context,
        ));
    }
}
