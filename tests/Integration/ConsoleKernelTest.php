<?php

declare(strict_types=1);

namespace BXCode\Tests\Integration;

use BXCode\Application;
use BXCode\Cache\FileStore;
use BXCode\Console\Kernel;
use BXCode\Console\Output;
use PHPUnit\Framework\TestCase;

final class ConsoleKernelTest extends TestCase
{
    private string $base;

    /** @var resource */
    private $stream;

    private Kernel $kernel;

    private Application $app;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/bxcode_cli_' . bin2hex(random_bytes(4));
        mkdir($this->base, 0775, true);

        $this->app    = Application::boot($this->base);
        $this->stream = fopen('php://memory', 'r+');
        $this->kernel = new Kernel($this->app, new Output($this->stream));
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        $this->rrmdir($this->base);
    }

    private function captured(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }

    public function test_list_shows_commands(): void
    {
        $code = $this->kernel->run([]);

        self::assertSame(0, $code);
        self::assertStringContainsString('make:controller', $this->captured());
        self::assertStringContainsString('migrate', $this->captured());
    }

    public function test_unknown_command_returns_error_code(): void
    {
        self::assertSame(1, $this->kernel->run(['nope']));
        self::assertStringContainsString('Unknown command', $this->captured());
    }

    public function test_make_controller_writes_file(): void
    {
        $code = $this->kernel->run(['make:controller', 'DashboardController']);

        self::assertSame(0, $code);
        $path = $this->base . '/app/Controllers/DashboardController.php';
        self::assertFileExists($path);
        self::assertStringContainsString('class DashboardController', (string) file_get_contents($path));
        self::assertStringContainsString('Created', $this->captured());
    }

    public function test_make_controller_refuses_overwrite(): void
    {
        $this->kernel->run(['make:controller', 'Dup']);
        $code = $this->kernel->run(['make:controller', 'Dup']);

        self::assertSame(1, $code);
        self::assertStringContainsString('already exists', $this->captured());
    }

    public function test_make_controller_rejects_invalid_name(): void
    {
        self::assertSame(1, $this->kernel->run(['make:controller', '9bad']));
    }

    public function test_make_middleware_writes_file(): void
    {
        self::assertSame(0, $this->kernel->run(['make:middleware', 'EnsureAdmin']));
        $contents = (string) file_get_contents($this->base . '/app/Middleware/EnsureAdmin.php');
        self::assertStringContainsString('implements Middleware', $contents);
    }

    public function test_make_migration_writes_timestamped_file(): void
    {
        self::assertSame(0, $this->kernel->run(['make:migration', 'CreatePostsTable']));

        $files = glob($this->base . '/database/migrations/*_create_posts_table.php') ?: [];
        self::assertCount(1, $files);
    }

    public function test_cache_clear(): void
    {
        $this->app->setCache(new FileStore($this->base . '/cache'));
        $this->app->cache()->put('k', 'v');

        self::assertSame(0, $this->kernel->run(['cache:clear']));
        self::assertFalse($this->app->cache()->has('k'));
        self::assertStringContainsString('Cache cleared', $this->captured());
    }

    public function test_route_list(): void
    {
        $this->app->router()->get('/widgets/{id}', fn () => 'ok', 'widgets.show');

        self::assertSame(0, $this->kernel->run(['route:list']));
        $out = $this->captured();
        self::assertStringContainsString('/widgets/{id}', $out);
        self::assertStringContainsString('widgets.show', $out);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
