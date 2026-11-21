<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class ErrorResponsesTest extends TestCase
{
    public function test_unknown_route_returns_json_404(): void
    {
        $this->get('/api/v1/nope', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Not found.']);
    }

    public function test_server_errors_have_no_stack_trace_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/v1/__boom', fn () => throw new RuntimeException('secret internals at /var/www'));

        $response = $this->getJson('/api/v1/__boom')->assertStatus(500);

        $response->assertExactJson(['message' => 'Server Error']);
        $this->assertStringNotContainsString('secret internals', $response->getContent());
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_cors_allows_the_configured_web_origin_preflight(): void
    {
        $this->call('OPTIONS', '/api/v1/enquiries', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, accept',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Methods');

        $this->assertStringContainsString(
            'content-type',
            strtolower((string) $this->call('OPTIONS', '/api/v1/enquiries', server: [
                'HTTP_ORIGIN' => 'http://localhost:5173',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, accept',
            ])->headers->get('Access-Control-Allow-Headers')),
        );
    }

    public function test_cors_never_allows_other_origins(): void
    {
        // With a single allowed origin, Laravel's CORS middleware always answers with that fixed origin,
        // so a browser on any other origin rejects the response. It must never echo the caller's origin.
        $actual = $this->getJson('/api/v1/nope', ['Origin' => 'https://evil.example']);
        $this->assertNotSame('https://evil.example', $actual->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('http://localhost:5173', $actual->headers->get('Access-Control-Allow-Origin'));

        $preflight = $this->call('OPTIONS', '/api/v1/enquiries', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
        $this->assertNotSame('https://evil.example', $preflight->headers->get('Access-Control-Allow-Origin'));
    }
}
