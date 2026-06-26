<?php

namespace Tests\Unit;

use App\Services\AI\AiJsonParser;
use App\Services\AI\AiProviderConfig;
use App\Services\AI\AiServiceFactory;
use App\Services\AI\AnthropicService;
use App\Services\AI\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderServicesTest extends TestCase
{
    public function test_anthropic_entry_analysis_parses_json_response(): void
    {
        config([
            'trading.ai.anthropic.api_key' => 'sk-ant-test',
            'trading.ai.anthropic.model' => 'claude-sonnet-4-6',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '{"symbol":"XAUUSD","action":"BUY","confidence":90,"entry_price":3350,"stop_loss":3340,"take_profit":3370,"reason":"Breakout"}',
                ]],
            ]),
        ]);

        $result = app(AnthropicService::class)->analyzeEntry([
            'symbol' => ['symbol' => 'XAUUSD'],
        ]);

        $this->assertSame('BUY', $result['action']);
        $this->assertSame(90, $result['confidence']);
        $this->assertTrue(AiProviderConfig::isConfigured('anthropic'));
    }

    public function test_anthropic_parses_markdown_wrapped_json(): void
    {
        config([
            'trading.ai.anthropic.api_key' => 'sk-ant-test',
            'trading.ai.anthropic.model' => 'claude-sonnet-4-6',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => "Here is my analysis:\n\n```json\n{\"symbol\":\"XAUUSD\",\"action\":\"WAIT\",\"confidence\":55,\"entry_price\":0,\"stop_loss\":0,\"take_profit\":0,\"reason\":\"Choppy range\"}\n```",
                ]],
            ]),
        ]);

        $result = app(AnthropicService::class)->analyzeEntry([
            'symbol' => ['symbol' => 'XAUUSD'],
        ]);

        $this->assertSame('WAIT', $result['action']);
        $this->assertSame(55, $result['confidence']);
    }

    public function test_json_parser_extracts_object_from_preamble(): void
    {
        $parsed = AiJsonParser::parse(
            'Analysis complete. {"action":"HOLD","reason":"Trend intact"}',
            'Test'
        );

        $this->assertSame('HOLD', $parsed['action']);
    }

    public function test_json_parser_repairs_trailing_commas(): void
    {
        $parsed = AiJsonParser::parse(
            '{"action":"BUY","confidence":80,}',
            'Test'
        );

        $this->assertSame('BUY', $parsed['action']);
    }

    public function test_gemini_entry_analysis_parses_json_response(): void
    {
        config([
            'trading.ai.gemini.api_key' => 'gemini-test-key',
            'trading.ai.gemini.model' => 'gemini-2.0-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"symbol":"XAUUSD","action":"SELL","confidence":85,"entry_price":3350,"stop_loss":3360,"take_profit":3330,"reason":"Resistance"}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $result = app(GeminiService::class)->analyzeEntry([
            'symbol' => ['symbol' => 'XAUUSD'],
        ]);

        $this->assertSame('SELL', $result['action']);
        $this->assertSame(85, $result['confidence']);
        $this->assertTrue(AiProviderConfig::isConfigured('gemini'));
    }

    public function test_factory_resolves_anthropic_and_gemini_aliases(): void
    {
        config([
            'trading.ai.anthropic.api_key' => 'sk-ant-test',
            'trading.ai.gemini.api_key' => 'gemini-test-key',
        ]);

        $this->assertInstanceOf(AnthropicService::class, AiServiceFactory::make('claude'));
        $this->assertInstanceOf(GeminiService::class, AiServiceFactory::make('google'));
    }

    public function test_make_configured_throws_when_api_key_missing(): void
    {
        config(['trading.ai.anthropic.api_key' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Anthropic');

        AiServiceFactory::makeConfigured('anthropic');
    }
}
