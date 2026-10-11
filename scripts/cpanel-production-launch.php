<?php

declare(strict_types=1);

const LAUNCH_AT = '2026-10-12 12:00:00';
const LAUNCH_TIMEZONE = 'Asia/Kuala_Lumpur';

date_default_timezone_set(LAUNCH_TIMEZONE);

$appRoot = dirname(__DIR__);
$accountRoot = dirname($appRoot);
$webRoot = $accountRoot.'/public_html/cucinow.co';
$publicRoot = $appRoot.'/public';
$marker = $accountRoot.'/.cucinow-production-launch.json';
$dryRun = in_array('--dry-run', $argv, true);
$now = new DateTimeImmutable('now', new DateTimeZone(LAUNCH_TIMEZONE));
$launchAt = new DateTimeImmutable(LAUNCH_AT, new DateTimeZone(LAUNCH_TIMEZONE));

function launchLog(string $message): void
{
    echo '['.(new DateTimeImmutable('now', new DateTimeZone(LAUNCH_TIMEZONE)))->format('Y-m-d H:i:s T')."] {$message}\n";
}

function requirePath(string $path, string $description, bool $directory = false): void
{
    $exists = $directory ? is_dir($path) : is_file($path);

    if (! $exists) {
        throw new RuntimeException("Missing {$description}: {$path}");
    }
}

function copyDirectory(string $source, string $destination): void
{
    if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
        throw new RuntimeException("Unable to create directory: {$destination}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = $iterator->getSubPathName();
        $target = $destination.DIRECTORY_SEPARATOR.$relative;

        if ($item->isDir()) {
            if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
                throw new RuntimeException("Unable to create directory: {$target}");
            }

            continue;
        }

        if (! copy($item->getPathname(), $target)) {
            throw new RuntimeException("Unable to copy public asset: {$relative}");
        }
    }
}

try {
    requirePath($webRoot, 'locked cucinow.co document root', true);
    requirePath($publicRoot, 'Laravel public directory', true);
    requirePath($appRoot.'/.env', 'production .env');
    requirePath($appRoot.'/vendor/autoload.php', 'production Composer dependencies');
    requirePath($appRoot.'/bootstrap/app.php', 'Laravel bootstrap');
    requirePath($publicRoot.'/build/manifest.json', 'compiled frontend assets');
    requirePath($publicRoot.'/.htaccess', 'Laravel .htaccess');
    requirePath($appRoot.'/scripts/cpanel-production-index.php', 'production public index');

    $environment = parse_ini_file($appRoot.'/.env', false, INI_SCANNER_RAW);
    if (! is_array($environment)
        || ($environment['APP_ENV'] ?? null) !== 'production'
        || ($environment['APP_DEBUG'] ?? null) !== 'false'
        || rtrim((string) ($environment['APP_URL'] ?? ''), '/') !== 'https://cucinow.co') {
        throw new RuntimeException('Production .env must use APP_ENV=production, APP_DEBUG=false and APP_URL=https://cucinow.co.');
    }

    if ($dryRun) {
        if (is_file($marker)) {
            $existingMarker = json_decode((string) file_get_contents($marker), true, flags: JSON_THROW_ON_ERROR);
            launchLog('PRE-FLIGHT NOTICE. Launch marker status: '.($existingMarker['status'] ?? 'unknown').'.');
            exit(0);
        }

        launchLog('PRE-FLIGHT OK. Production app and locked document root are ready.');
        launchLog('Scheduled switch: '.$launchAt->format('l, d M Y g:i A T'));
        exit(0);
    }

    $backupMetadata = null;
    if (is_file($marker)) {
        $backupMetadata = json_decode((string) file_get_contents($marker), true, flags: JSON_THROW_ON_ERROR);
        $status = $backupMetadata['status'] ?? null;

        if (in_array($status, ['complete', 'rolled_back'], true)) {
            launchLog("Launch marker status is {$status}. No files were changed.");
            exit(0);
        }

        if ($status !== 'launching') {
            throw new RuntimeException('Launch marker has an unknown status. No files were changed.');
        }

        launchLog('Resuming an interrupted launch using the original countdown backup.');
    } else {
        if ($now < $launchAt) {
            launchLog('Waiting for '.$launchAt->format('d M Y g:i A T').'.');
            exit(0);
        }

        $backupRoot = $accountRoot.'/cucinow-launch-backups/'.$now->format('Ymd-His');
        if (! mkdir($backupRoot, 0700, true) && ! is_dir($backupRoot)) {
            throw new RuntimeException("Unable to create launch backup: {$backupRoot}");
        }

        $backupMetadata = [
            'status' => 'launching',
            'backup' => $backupRoot,
            'launch_started_at' => $now->format(DATE_ATOM),
        ];
        foreach (['index.php', '.htaccess', 'favicon.ico', 'robots.txt', 'site.webmanifest'] as $filename) {
            $source = $webRoot.'/'.$filename;
            $backupMetadata['existing'][$filename] = is_file($source);
            if (is_file($source) && ! copy($source, $backupRoot.'/'.$filename)) {
                throw new RuntimeException("Unable to back up countdown file: {$filename}");
            }
        }

        if (file_put_contents($marker, json_encode($backupMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            throw new RuntimeException('Unable to write the launch safety marker. No website files were changed.');
        }
        @chmod($marker, 0600);
    }

    $backupRoot = $backupMetadata['backup'] ?? null;
    if (! is_string($backupRoot) || ! is_file($backupRoot.'/index.php')) {
        throw new RuntimeException('Original countdown backup is missing. Launch was stopped.');
    }

    foreach (scandir($publicRoot) ?: [] as $entry) {
        if (in_array($entry, ['.', '..', 'index.php', '.htaccess', 'storage'], true)) {
            continue;
        }

        $source = $publicRoot.'/'.$entry;
        $destination = $webRoot.'/'.$entry;
        if (is_dir($source)) {
            copyDirectory($source, $destination);
        } elseif (! copy($source, $destination)) {
            throw new RuntimeException("Unable to copy public file: {$entry}");
        }
    }

    $storageTarget = $appRoot.'/storage/app/public';
    $storageLink = $webRoot.'/storage';
    if (! file_exists($storageLink) && ! is_link($storageLink) && is_dir($storageTarget)) {
        @symlink($storageTarget, $storageLink);
    }

    $nextHtaccess = $webRoot.'/.htaccess.cucinow-next';
    $nextIndex = $webRoot.'/index.cucinow-next.php';
    if (! copy($publicRoot.'/.htaccess', $nextHtaccess)
        || ! copy($appRoot.'/scripts/cpanel-production-index.php', $nextIndex)) {
        throw new RuntimeException('Unable to prepare the atomic production switch files.');
    }

    if (! rename($nextHtaccess, $webRoot.'/.htaccess')) {
        throw new RuntimeException('Unable to activate the Laravel .htaccess file.');
    }

    if (! rename($nextIndex, $webRoot.'/index.php')) {
        throw new RuntimeException('Unable to activate the Laravel front controller.');
    }

    $backupMetadata['status'] = 'complete';
    $backupMetadata['launched_at'] = $now->format(DATE_ATOM);
    if (file_put_contents($marker, json_encode($backupMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException('Site launched, but the launch marker could not be updated. Disable the launch cron immediately.');
    }

    @chmod($marker, 0600);
    launchLog('LAUNCH COMPLETE. cucinow.co now serves the production Laravel application.');
    launchLog("Countdown backup: {$backupRoot}");
    launchLog('Remove the temporary production-launch cron job now.');
} catch (Throwable $exception) {
    launchLog('LAUNCH BLOCKED: '.$exception->getMessage());
    exit(1);
}
