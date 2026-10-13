<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function test_accessors_return_constructor_values(): void
    {
        $r = new Request(
            method: 'post',
            path: '/users',
            query: ['page' => '2'],
            body: ['name' => 'Ada'],
            headers: ['content-type' => 'application/json'],
        );

        self::assertSame('post', $r->method());
        self::assertSame('/users', $r->path());
        self::assertSame('2', $r->query('page'));
        self::assertSame('Ada', $r->input('name'));
        self::assertSame('application/json', $r->header('Content-Type'));
    }

    public function test_all_merges_body_over_query(): void
    {
        $r = new Request('GET', '/', ['a' => '1', 'b' => 'q'], ['b' => 'body']);

        self::assertSame(['a' => '1', 'b' => 'body'], $r->all());
    }

    public function test_with_params_is_immutable_copy(): void
    {
        $r = new Request('GET', '/hello/ada');
        $withParams = $r->withParams(['name' => 'ada']);

        self::assertNull($r->param('name'));
        self::assertSame('ada', $withParams->param('name'));
    }

    public function test_capture_parses_path_and_method_from_server(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/health?debug=1';
        $_GET                      = ['debug' => '1'];

        $r = Request::capture();

        self::assertSame('GET', $r->method());
        self::assertSame('/api/health', $r->path());
        self::assertSame('1', $r->query('debug'));
    }
}
