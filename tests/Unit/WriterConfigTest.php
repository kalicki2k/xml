<?php

declare(strict_types=1);

namespace Kalle\Xml\Tests\Unit;

use Kalle\Xml\Writer\WriterConfig;
use PHPUnit\Framework\TestCase;

final class WriterConfigTest extends TestCase
{
    public function testNoOpWriterConfigWithersReturnTheSameInstance(): void
    {
        $config = WriterConfig::pretty(
            indent: "\t",
            newline: "\r\n",
            emitDeclaration: false,
            selfCloseEmptyElements: false,
        );

        self::assertSame($config, $config->withPrettyPrint(true));
        self::assertSame($config, $config->withIndent("\t"));
        self::assertSame($config, $config->withNewline("\r\n"));
        self::assertSame($config, $config->withEmitDeclaration(false));
        self::assertSame($config, $config->withSelfCloseEmptyElements(false));
    }
}
