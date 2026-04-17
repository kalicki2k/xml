<?php

declare(strict_types=1);

namespace Kalle\Xml\Node;

use Kalle\Xml\Escape\XmlEscaper;

final readonly class CDataNode implements Node
{
    public function __construct(private string $content)
    {
        XmlEscaper::assertValidString($this->content, 'CDATA content');
    }

    public function content(): string
    {
        return $this->content;
    }
}
