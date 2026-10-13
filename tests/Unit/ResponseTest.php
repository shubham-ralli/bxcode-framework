<?php

declare(strict_types=1);

namespace BXCode\Tests\Unit;

use BXCode\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_html_sets_body_status_and_content_type(): void
    {
        $r = Response::html('<h1>Hi</h1>');

        self::assertSame('<h1>Hi</h1>', $r->body());
        self::assertSame(200, $r->status());
        self::assertSame('text/html; charset=UTF-8', $r->header('content-type'));
    }

    public function test_json_encodes_payload_and_sets_content_type(): void
    {
        $r = Response::json(['success' => true, 'framework' => 'BXCode'], 201);

        self::assertSame('{"success":true,"framework":"BXCode"}', $r->body());
        self::assertSame(201, $r->status());
        self::assertSame('application/json; charset=UTF-8', $r->header('content-type'));
    }

    public function test_redirect_sets_location_and_status(): void
    {
        $r = Response::redirect('/login', 301);

        self::assertSame(301, $r->status());
        self::assertSame('/login', $r->header('location'));
        self::assertSame('', $r->body());
    }

    public function test_headers_are_case_insensitive(): void
    {
        $r = Response::make('x')->withHeader('X-Custom', 'yes');

        self::assertSame('yes', $r->header('x-custom'));
        self::assertSame('yes', $r->header('X-CUSTOM'));
    }

    public function test_send_emits_body(): void
    {
        $r = Response::text('payload');

        ob_start();
        $r->send();
        $out = ob_get_clean();

        self::assertSame('payload', $out);
    }
}
