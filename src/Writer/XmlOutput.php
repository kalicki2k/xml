<?php

declare(strict_types=1);

namespace Kalle\Xml\Writer;

interface XmlOutput
{
    public function write(string $chunk): void;

    public function finish(): void;
}
