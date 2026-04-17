<?php

declare(strict_types=1);

namespace Kalle\Xml\Reader;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Kalle\Xml\Name\QualifiedName;
use Kalle\Xml\Namespace\NamespaceDeclaration;

use function ksort;
use function str_starts_with;
use function substr;

/**
 * @internal Reader-side DOM namespace inspection helper.
 */
final class DomNamespaceInspector
{
    private function __construct() {}

    /**
     * @return list<NamespaceDeclaration>
     */
    public static function namespacesInScope(DOMElement $element): array
    {
        $document = $element->ownerDocument;

        if (!$document instanceof DOMDocument) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $namespaceNodes = $xpath->query('namespace::*', $element);

        if ($namespaceNodes === false) {
            return [];
        }

        $declarations = [];

        foreach ($namespaceNodes as $namespaceNode) {
            $nodeName = $namespaceNode->nodeName;

            if ($nodeName === 'xmlns') {
                $prefix = null;
            } elseif (str_starts_with($nodeName, 'xmlns:')) {
                $prefix = substr($nodeName, 6);
            } else {
                continue;
            }

            if ($prefix === 'xml' && $namespaceNode->nodeValue === QualifiedName::XML_NAMESPACE_URI) {
                continue;
            }

            $declaration = new NamespaceDeclaration($prefix, $namespaceNode->nodeValue ?? '');

            $declarations[$declaration->prefixKey()] = $declaration;
        }

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
