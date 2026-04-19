<?php

declare(strict_types=1);

namespace Kalle\Xml\Node;

use Kalle\Xml\Escape\XmlEscaper;
use Kalle\Xml\Exception\InvalidXmlContent;
use Kalle\Xml\Name\XmlNameValidator;

use function str_contains;

final readonly class ProcessingInstructionNode implements Node
{
    public function __construct(
        private string $target,
        private string $data = '',
    ) {
        XmlNameValidator::assertValidProcessingInstructionTarget($this->target);
        XmlEscaper::assertValidString($this->data, 'Processing instruction data');

        if (str_contains($this->data, '?>')) {
            throw new InvalidXmlContent(sprintf(
                'Processing instruction data for "%s" cannot contain "?>".',
                $this->target,
            ));
        }
    }

    public function target(): string
    {
        return $this->target;
    }

    public function data(): string
    {
        return $this->data;
    }
}
