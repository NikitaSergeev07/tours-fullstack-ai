<?php

namespace Tests\Unit;

use App\Services\LLM\AnthropicTourGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AnthropicTourGeneratorTest extends TestCase
{
    public function test_normalises_partial_drafts_into_safe_defaults(): void
    {
        $gen = new AnthropicTourGenerator(apiKey: 'fake', model: 'fake', baseUrl: 'http://localhost');

        $ref = new ReflectionClass($gen);
        $method = $ref->getMethod('normalise');
        $method->setAccessible(true);

        $out = $method->invoke($gen, [
            'title' => '  Тур  ',
            // duration_days missing -> defaults to 1
            'difficulty' => 'extreme', // invalid -> falls back to easy
            'highlights' => ['ok', 42, null, 'two'],
            'route_points' => [['lat' => 1.0, 'lon' => 2.0]],
        ]);

        $this->assertSame('  Тур  ', $out['title']);
        $this->assertSame(1, $out['duration_days']);
        $this->assertSame('easy', $out['difficulty']);
        $this->assertSame(['ok', 'two'], $out['highlights']);
        $this->assertCount(1, $out['route_points']);
        $this->assertSame([], $out['dates']);
    }

    public function test_prefers_bearer_auth_when_token_is_set(): void
    {
        $gen = new AnthropicTourGenerator(
            apiKey: 'direct-key',
            authToken: 'proxy-token',
            model: 'm', baseUrl: 'http://localhost'
        );
        $headers = $this->invokeHeaders($gen);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertSame('Bearer proxy-token', $headers['Authorization']);
        $this->assertArrayNotHasKey('x-api-key', $headers);
    }

    public function test_falls_back_to_x_api_key_when_only_api_key_is_set(): void
    {
        $gen = new AnthropicTourGenerator(apiKey: 'direct-key', model: 'm', baseUrl: 'http://localhost');
        $headers = $this->invokeHeaders($gen);
        $this->assertArrayHasKey('x-api-key', $headers);
        $this->assertSame('direct-key', $headers['x-api-key']);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function test_throws_when_neither_credential_is_set(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/ANTHROPIC_API_KEY|ANTHROPIC_AUTH_TOKEN/');
        $gen = new AnthropicTourGenerator(model: 'm', baseUrl: 'http://localhost');
        $this->invokeHeaders($gen);
    }

    /** @return array<string,string> */
    private function invokeHeaders(AnthropicTourGenerator $gen): array
    {
        $ref = new ReflectionClass($gen);
        $method = $ref->getMethod('authHeaders');
        $method->setAccessible(true);
        return $method->invoke($gen);
    }
}
