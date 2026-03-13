<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$pluginMainFile = $repoRoot . '/wp-plugin/vogo-plugin.php';
$outputFile = $repoRoot . '/build/version.json';

if (!is_file($pluginMainFile)) {
    fwrite(STDERR, "Plugin file not found: {$pluginMainFile}\n");
    exit(1);
}

$pluginContents = file_get_contents($pluginMainFile);
if ($pluginContents === false) {
    fwrite(STDERR, "Unable to read plugin file: {$pluginMainFile}\n");
    exit(1);
}

$readHeader = static function (string $header, string $source): string {
    $pattern = '/^[\s\/*#@]*' . preg_quote($header, '/') . ':\s*(.+)$/mi';
    if (preg_match($pattern, $source, $matches) === 1) {
        return trim($matches[1]);
    }

    return '';
};

$version = $readHeader('Version', $pluginContents);
$requires = $readHeader('Requires at least', $pluginContents);
$requiresPhp = $readHeader('Requires PHP', $pluginContents);
$description = $readHeader('Description', $pluginContents);
$name = $readHeader('Plugin Name', $pluginContents);

if ($version === '') {
    fwrite(STDERR, "Version header missing in {$pluginMainFile}\n");
    exit(1);
}

$metadata = [
    'name' => $name !== '' ? $name : 'VOGO Plugin',
    'slug' => 'vogo-plugin',
    'version' => $version,
    'download_url' => 'https://plugins.vogo.family/vogo-plugin/vogo-plugin.zip',
    'requires' => $requires,
    'tested' => '',
    'requires_php' => $requiresPhp,
    'last_updated' => gmdate('Y-m-d'),
    'sections' => [
        'description' => $description,
        'changelog' => sprintf('<h4>%s</h4><ul><li>Release %s</li></ul>', $version, $version),
    ],
];

$json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "Failed to encode metadata JSON\n");
    exit(1);
}

if (file_put_contents($outputFile, $json . PHP_EOL) === false) {
    fwrite(STDERR, "Unable to write metadata file: {$outputFile}\n");
    exit(1);
}

echo "Generated {$outputFile}\n";
