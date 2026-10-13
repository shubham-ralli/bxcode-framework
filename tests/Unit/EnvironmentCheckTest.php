<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Support\EnvironmentCheck;
use PHPUnit\Framework\TestCase;

final class EnvironmentCheckTest extends TestCase
{
    public function test_passes_on_a_writable_storage_directory(): void
    {
        $dir = sys_get_temp_dir() . '/bxcode_env_' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        $result = (new EnvironmentCheck())->run($dir);

        self::assertTrue($result['pass']);
        $names = array_column($result['checks'], 'name');
        self::assertContains('PHP version', $names);
        self::assertContains('ext-json', $names);
        self::assertContains('storage writable', $names);

        @rmdir($dir);
    }

    public function test_fails_when_storage_is_not_writable(): void
    {
        $result = (new EnvironmentCheck())->run('/no/such/path/definitely/missing');

        self::assertFalse($result['pass']);

        $storage = array_values(array_filter(
            $result['checks'],
            static fn (array $c): bool => $c['name'] === 'storage writable',
        ))[0];
        self::assertFalse($storage['ok']);
    }
}
