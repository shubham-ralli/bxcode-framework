<?php

declare(strict_types=1);

/*
 | Shared-hosting readiness checker. Upload the app, then open this file in a
 | browser (https://your-site/check.php) or run `php public/check.php` from the
 | CLI. Delete it once setup is complete.
 */

use BXCode\Support\EnvironmentCheck;

$root     = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    $message = 'Dependencies are missing: vendor/autoload.php not found. '
        . 'Upload the production package (which bundles vendor/) or run composer install.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        http_response_code(500);
        echo '<h1>BXCode setup</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>';
    }
    exit(1);
}

require $autoload;

$result = (new EnvironmentCheck())->run($root . '/storage');

if (PHP_SAPI === 'cli') {
    foreach ($result['checks'] as $check) {
        printf("[%s] %-20s %s\n", $check['ok'] ? ' OK ' : 'FAIL', $check['name'], $check['detail']);
    }
    echo $result['pass'] ? "\nAll required checks passed.\n" : "\nSome required checks FAILED.\n";
    exit($result['pass'] ? 0 : 1);
}

http_response_code($result['pass'] ? 200 : 500);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BXCode Setup Check</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #ddd; }
        .ok { color: #137333; } .fail { color: #c5221f; }
        .summary { font-weight: 600; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>BXCode Setup Check</h1>
    <table>
        <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($result['checks'] as $check): ?>
            <tr>
                <td><?= htmlspecialchars($check['name'], ENT_QUOTES) ?><?= $check['required'] ? '' : ' (optional)' ?></td>
                <td class="<?= $check['ok'] ? 'ok' : 'fail' ?>"><?= $check['ok'] ? 'OK' : 'FAIL' ?></td>
                <td><?= htmlspecialchars($check['detail'], ENT_QUOTES) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="summary <?= $result['pass'] ? 'ok' : 'fail' ?>">
        <?= $result['pass'] ? 'All required checks passed. Remember to delete check.php.' : 'Some required checks failed.' ?>
    </p>
</body>
</html>
