<?php

declare(strict_types=1);

$appRoot = dirname(__DIR__);
$publicRoot = $appRoot.'/public';
$environment = is_file($appRoot.'/.env')
    ? parse_ini_file($appRoot.'/.env', false, INI_SCANNER_RAW)
    : [];

$configuredWebRoot = is_array($environment)
    ? trim((string) ($environment['CPANEL_PUBLIC_ROOT'] ?? ''))
    : '';
$defaultWebRoot = dirname($appRoot).'/public_html/cucinow.co';

if ($configuredWebRoot !== '') {
    $webRoot = rtrim($configuredWebRoot, '/');
} elseif (basename($appRoot) === 'cucinowco-system' && is_dir($defaultWebRoot)) {
    $webRoot = $defaultWebRoot;
} else {
    echo "Public asset sync skipped: this checkout does not use a separate production webroot.\n";
    exit(0);
}

function syncLog(string $message): void
{
    echo '['.date('Y-m-d H:i:s')."] {$message}\n";
}

function ensureDirectory(string $directory): void
{
    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        throw new RuntimeException("Unable to create directory: {$directory}");
    }
}

function copyFileSafely(string $source, string $destination): void
{
    ensureDirectory(dirname($destination));

    if (! copy($source, $destination)) {
        throw new RuntimeException("Unable to copy public asset: {$source}");
    }

    @chmod($destination, 0644);
}

function copyDirectory(string $source, string $destination, array $excludedNames = []): void
{
    ensureDirectory($destination);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        $relative = $iterator->getSubPathName();
        if (in_array($relative, $excludedNames, true)) {
            continue;
        }

        $target = $destination.DIRECTORY_SEPARATOR.$relative;
        if ($item->isDir()) {
            ensureDirectory($target);
        } else {
            copyFileSafely($item->getPathname(), $target);
        }
    }
}

try {
    if (! is_dir($publicRoot) || ! is_dir($webRoot)) {
        throw new RuntimeException('Laravel public directory or configured cPanel webroot is missing.');
    }

    if (! str_contains(str_replace('\\', '/', $webRoot), '/public_html/')) {
        throw new RuntimeException('Refusing to sync outside a cPanel public_html directory.');
    }

    $manifest = $publicRoot.'/build/manifest.json';
    if (! is_file($manifest)) {
        throw new RuntimeException('Compiled Vite manifest is missing. Wait for the GitHub deploy build.');
    }

    foreach (scandir($publicRoot) ?: [] as $entry) {
        if (in_array($entry, ['.', '..', 'index.php', '.htaccess', 'storage', 'build'], true)) {
            continue;
        }

        $source = $publicRoot.'/'.$entry;
        $destination = $webRoot.'/'.$entry;
        is_dir($source)
            ? copyDirectory($source, $destination)
            : copyFileSafely($source, $destination);
    }

    $buildDestination = $webRoot.'/build';
    copyDirectory($publicRoot.'/build', $buildDestination, ['manifest.json']);

    $nextManifest = $buildDestination.'/.manifest.cucinow-next.json';
    copyFileSafely($manifest, $nextManifest);
    if (! rename($nextManifest, $buildDestination.'/manifest.json')) {
        throw new RuntimeException('Unable to activate the new Vite manifest.');
    }

    syncLog('PUBLIC ASSET SYNC COMPLETE: '.$webRoot);
} catch (Throwable $exception) {
    syncLog('PUBLIC ASSET SYNC FAILED: '.$exception->getMessage());
    exit(1);
}
