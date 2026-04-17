<?php

declare(strict_types=1);

namespace Kalle\Xml\Namespace;

use Kalle\Xml\Name\QualifiedName;

final readonly class NamespaceScope
{
    /**
     * @param array<string, string> $bindings
     */
    private function __construct(
        private array $bindings,
    ) {}

    public static function empty(): self
    {
        return new self([
            'xml' => QualifiedName::XML_NAMESPACE_URI,
        ]);
    }

    public function namespaceUriForPrefix(?string $prefix): ?string
    {
        return $this->bindings[$prefix ?? ''] ?? null;
    }

    public function defaultNamespaceUri(): ?string
    {
        return $this->namespaceUriForPrefix(null);
    }

    /**
     * @param list<NamespaceDeclaration> $declarations
     */
    public function withDeclarations(array $declarations): self
    {
        $bindings = $this->bindings;

        foreach ($declarations as $declaration) {
            $prefix = $declaration->prefixKey();

            if ($prefix === '' && $declaration->uri() === '') {
                unset($bindings['']);
                continue;
            }

            $bindings[$prefix] = $declaration->uri();
        }

        return new self($bindings);
    }
}
