<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

final class StringXmlOutput implements XmlOutput
{
    private string $buffer = '';

    public function write(string $chunk): void
    {
        $this->buffer .= $chunk;
    }

    public function finish(): void {}

    public function toString(): string
    {
        return $this->buffer;
    }
}
