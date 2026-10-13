<?php

declare(strict_types=1);

/*
 | In-process micro-benchmark of the request lifecycle (Application::handle).
 | It measures the framework's own dispatch cost — NOT a full HTTP round trip,
 | and NOT a comparison with any other framework.
 |
 |   php bin/benchmark.php [iterations]
 */

use BXCode\Application;
use BXCode\Database\Connection;
use BXCode\Http\Request;
use BXCode\Middleware\Cors;
use BXCode\Middleware\SecurityHeaders;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$iterations = isset($argv[1]) ? max(100, (int) $argv[1]) : 3000;

$app = Application::boot($root);

// In-memory DB for the database-backed scenario.
$db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, status TEXT)');
for ($i = 0; $i < 50; $i++) {
    $db->table('users')->insert(['name' => 'User ' . $i, 'status' => 'active']);
}
$app->setConnection($db);

$router = $app->router();
$router->get('/plain', fn () => 'Hello, world');
$router->get('/hello/{name}', fn (string $name) => "Hello, {$name}");
$router->get('/json', fn () => ['success' => true, 'items' => range(1, 10)]);
$router->get('/db', fn () => $app->db()->table('users')->where('status', '=', 'active')->limit(20)->get());
$router->get('/view', fn () => view('home', ['title' => 'Benchmark']));
$router->get('/mw', fn () => 'ok', null, [SecurityHeaders::class, Cors::class]);

/** @var array<string,Request> */
$scenarios = [
    'plain text'       => new Request('GET', '/plain'),
    'route parameter'  => new Request('GET', '/hello/world'),
    'json response'    => new Request('GET', '/json'),
    'database-backed'  => new Request('GET', '/db'),
    'template render'  => new Request('GET', '/view'),
    'middleware stack' => new Request('GET', '/mw'),
];

$percentile = static function (array $sorted, float $p): float {
    $index = (int) ceil($p / 100 * count($sorted)) - 1;

    return $sorted[max(0, $index)];
};

printf("BXCode micro-benchmark — %d iterations/scenario, PHP %s\n\n", $iterations, PHP_VERSION);
printf("%-18s %8s %8s %8s %8s\n", 'Scenario', 'mean(ms)', 'p50(ms)', 'p95(ms)', 'queries');
printf("%s\n", str_repeat('-', 54));

foreach ($scenarios as $label => $request) {
    // Query count: one measured run with logging on.
    $db->flushQueryLog();
    $db->enableQueryLog();
    $app->handle($request);
    $queries = count($db->queryLog());
    $db->disableQueryLog();

    // Warm up.
    for ($i = 0; $i < 100; $i++) {
        $app->handle($request);
    }

    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start   = hrtime(true);
        $app->handle($request);
        $times[] = (hrtime(true) - $start) / 1e6; // ms
    }

    sort($times);
    $mean = array_sum($times) / count($times);

    printf(
        "%-18s %8.4f %8.4f %8.4f %8d\n",
        $label,
        $mean,
        $percentile($times, 50),
        $percentile($times, 95),
        $queries,
    );
}

printf("\nPeak memory: %.2f MB\n", memory_get_peak_usage(true) / 1048576);
