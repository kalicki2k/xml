<?php

declare(strict_types=1);

namespace Kalle\Xml\Node;

use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\InvalidXmlContent;

use function str_contains;
use function str_ends_with;

final readonly class CommentNode implements Node
{
    public function __construct(private string $content)
    {
        XmlEscaper::assertValidString($this->content, 'Comment content');

        if (str_contains($this->content, '--')) {
            throw new InvalidXmlContent('Comment content cannot contain "--".');
        }

        if (str_ends_with($this->content, '-')) {
            throw new InvalidXmlContent('Comment content cannot end with "-".');
        }
    }

    public function content(): string
    {
        return $this->content;
    }
}
