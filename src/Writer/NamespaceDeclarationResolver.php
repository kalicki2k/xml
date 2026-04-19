<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

use Kalle\Xml\Attribute\Attribute;
use Kalle\Xml\Exception\SerializationException;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\ElementNamespaceValidator;
use Kalle\Xml\Namespace\NamespaceDeclaration;
use Kalle\Xml\Namespace\NamespaceScope;

use function ksort;
use function sprintf;

/**
 * @internal
 */
final class NamespaceDeclarationResolver
{
    /**
     * @param array<string, Attribute> $attributes
     * @param array<string, NamespaceDeclaration> $explicitDeclarations
     *
     * @return list<NamespaceDeclaration>
     */
    public function resolve(
        QualifiedName $elementName,
        array $attributes,
        array $explicitDeclarations,
        NamespaceScope $context,
    ): array {
        ElementNamespaceValidator::assertCoherent(
            $elementName,
            $attributes,
            $explicitDeclarations,
        );

        $declarations = $explicitDeclarations;
        $elementLabel = sprintf('element "%s"', $elementName->lexicalName());

        $this->ensureElementNamespaceIsDeclared($elementName, $declarations, $context, $elementLabel);

        foreach ($attributes as $attribute) {
            $this->ensureAttributeNamespaceIsDeclared(
                $attribute,
                $declarations,
                $context,
                $elementLabel,
            );
        }

        return $this->sortNamespaceDeclarations($declarations);
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensureElementNamespaceIsDeclared(
        QualifiedName $elementName,
        array &$declarations,
        NamespaceScope $context,
        string $contextLabel,
    ): void {
        $namespaceUri = $elementName->namespaceUri();
        $prefix = $elementName->prefix();

        if ($namespaceUri === null) {
            if ($context->defaultNamespaceUri() !== null && !isset($declarations[''])) {
                $declarations[''] = new NamespaceDeclaration(null, '');
            }

            return;
        }

        if ($prefix === null) {
            $this->ensureDefaultNamespaceDeclaration(
                $declarations,
                $context,
                $namespaceUri,
                $contextLabel,
            );

            return;
        }

        $this->ensurePrefixedNamespaceDeclaration(
            $declarations,
            $context,
            $prefix,
            $namespaceUri,
            $contextLabel,
        );
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensureAttributeNamespaceIsDeclared(
        Attribute $attribute,
        array &$declarations,
        NamespaceScope $context,
        string $elementLabel,
    ): void {
        $namespaceUri = $attribute->namespaceUri();
        $prefix = $attribute->prefix();

        if ($namespaceUri === null || $prefix === null) {
            return;
        }

        $this->ensurePrefixedNamespaceDeclaration(
            $declarations,
            $context,
            $prefix,
            $namespaceUri,
            sprintf('attribute "%s" on %s', $attribute->name(), $elementLabel),
        );
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensurePrefixedNamespaceDeclaration(
        array &$declarations,
        NamespaceScope $context,
        string $prefix,
        string $namespaceUri,
        string $contextLabel,
    ): void {
        $declaration = $declarations[$prefix] ?? null;

        if ($declaration !== null) {
            if ($declaration->uri() === $namespaceUri) {
                return;
            }

            throw new SerializationException(sprintf(
                'Prefix "%s" is already bound to "%s" and cannot also be "%s" while serializing %s.',
                $prefix,
                $declaration->uri(),
                $namespaceUri,
                $contextLabel,
            ));
        }

        if ($context->namespaceUriForPrefix($prefix) === $namespaceUri) {
            return;
        }

        $declarations[$prefix] = new NamespaceDeclaration($prefix, $namespaceUri);
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     */
    private function ensureDefaultNamespaceDeclaration(
        array &$declarations,
        NamespaceScope $context,
        string $namespaceUri,
        string $contextLabel,
    ): void {
        $declaration = $declarations[''] ?? null;

        if ($declaration !== null) {
            if ($declaration->uri() === $namespaceUri) {
                return;
            }

            throw new SerializationException(sprintf(
                'Default namespace is already "%s" and cannot also be "%s" while serializing %s.',
                $declaration->uri(),
                $namespaceUri,
                $contextLabel,
            ));
        }

        if ($context->defaultNamespaceUri() === $namespaceUri) {
            return;
        }

        $declarations[''] = new NamespaceDeclaration(null, $namespaceUri);
    }

    /**
     * @param array<string, NamespaceDeclaration> $declarations
     *
     * @return list<NamespaceDeclaration>
     */
    private function sortNamespaceDeclarations(array $declarations): array
    {
        $defaultDeclaration = $declarations[''] ?? null;
        unset($declarations['']);

        ksort($declarations);

        $sorted = [];

        if ($defaultDeclaration !== null) {
            $sorted[] = $defaultDeclaration;
        }

        foreach ($declarations as $declaration) {
            $sorted[] = $declaration;
        }

        return $sorted;
    }
}
