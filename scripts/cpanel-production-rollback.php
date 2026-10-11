<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Kuala_Lumpur');

$appRoot = dirname(__DIR__);
$accountRoot = dirname($appRoot);
$webRoot = $accountRoot.'/public_html/cucinow.co';
$marker = $accountRoot.'/.cucinow-production-launch.json';

function rollbackLog(string $message): void
{
    echo '['.date('Y-m-d H:i:s T')."] {$message}\n";
}

try {
    if (! is_file($marker)) {
        throw new RuntimeException('Launch marker not found. Rollback was not attempted.');
    }

    $metadata = json_decode((string) file_get_contents($marker), true, flags: JSON_THROW_ON_ERROR);
    if (($metadata['status'] ?? null) === 'rolled_back') {
        rollbackLog('Rollback was already completed. No files were changed.');
        exit(0);
    }

    $backupRoot = $metadata['backup'] ?? null;
    if (! is_string($backupRoot) || ! is_file($backupRoot.'/index.php')) {
        throw new RuntimeException('The countdown index backup is missing. Rollback was not attempted.');
    }

    $nextIndex = $webRoot.'/index.countdown-next.php';
    if (! copy($backupRoot.'/index.php', $nextIndex) || ! rename($nextIndex, $webRoot.'/index.php')) {
        throw new RuntimeException('Unable to restore the countdown index.');
    }

    if (($metadata['existing']['.htaccess'] ?? false) === true && is_file($backupRoot.'/.htaccess')) {
        $nextHtaccess = $webRoot.'/.htaccess.countdown-next';
        if (! copy($backupRoot.'/.htaccess', $nextHtaccess) || ! rename($nextHtaccess, $webRoot.'/.htaccess')) {
            throw new RuntimeException('Unable to restore the countdown .htaccess file.');
        }
    } elseif (is_file($webRoot.'/.htaccess')) {
        rename($webRoot.'/.htaccess', $webRoot.'/.htaccess.cucinow-disabled-'.date('Ymd-His'));
    }

    $metadata['status'] = 'rolled_back';
    $metadata['rolled_back_at'] = date(DATE_ATOM);
    file_put_contents($marker, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    rollbackLog('ROLLBACK COMPLETE. The countdown front controller has been restored.');
    rollbackLog('Keep the production-launch cron disabled. Laravel assets remain recoverable in the document root.');
} catch (Throwable $exception) {
    rollbackLog('ROLLBACK BLOCKED: '.$exception->getMessage());
    exit(1);
}
