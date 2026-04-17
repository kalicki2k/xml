<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Kalle\Xml\Reader\XmlReader;

$document = XmlReader::fromString(
    <<<'XML'
<config environment="prod" version="2026.04">
    <database driver="pgsql" primary="true">
        <host>db.internal</host>
        <port>5432</port>
    </database>
    <feature name="search" enabled="true">
        <label>Global Search</label>
    </feature>
    <feature name="recommendations" enabled="false">
        <label>Recommendations</label>
    </feature>
</config>
XML,
);

$root = $document->rootElement();
$database = $root->firstChildElement('database');
$searchFeature = $root->firstChildElement('feature');

echo sprintf(
    "%s | %s | %s | %s\n",
    $root->attributeValue('environment') ?? 'n/a',
    $database?->firstChildElement('host')?->text() ?? 'n/a',
    $database?->attributeValue('driver') ?? 'n/a',
    $searchFeature?->firstChildElement('label')?->text() ?? 'n/a',
);
