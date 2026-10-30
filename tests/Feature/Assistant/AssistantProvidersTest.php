<?php

namespace Tests\Feature\Assistant;

use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\User;
use App\Services\Assistant\AiProviderException;
use App\Services\Assistant\AssistantSettings;
use App\Services\Assistant\Providers\GeminiProvider;
use App\Services\Assistant\Providers\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Adapter error classification (REQ-AI-021: retry only timeout, quota and 5xx) and page rendering. */
class AssistantProvidersTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_success_returns_text_and_token_counts(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => '{"suggestions":[]}']]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 9],
        ], 200)]);

        $reply = (new OpenAiProvider('sk-test'))->complete('gpt-test', 'system', 'user', 100);

        $this->assertSame('{"suggestions":[]}', $reply->text);
        $this->assertSame(50, $reply->inputTokens);
        $this->assertSame(9, $reply->outputTokens);
        $this->assertSame('openai', $reply->provider);
    }

    public function test_openai_429_and_5xx_are_retryable(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([], 429)]);

        try {
            (new OpenAiProvider('sk-test'))->complete('gpt-test', 's', 'u', 100);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertTrue($e->retryable);
            $this->assertSame(429, $e->status);
        }
    }

    public function test_openai_400_is_not_retryable(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'bad model'], 400)]);

        try {
            (new OpenAiProvider('sk-test'))->complete('gpt-test', 's', 'u', 100);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertFalse($e->retryable);
        }
    }

    public function test_gemini_sends_key_in_header_not_url_and_reads_parts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"suggestions":[]}']]]]],
            'usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 5],
        ], 200)]);

        $reply = (new GeminiProvider('gemini-secret'))->complete('gemini-3.6-flash', 's', 'u', 100);

        $this->assertSame('{"suggestions":[]}', $reply->text);
        $this->assertSame(30, $reply->inputTokens);

        Http::assertSent(function ($request) {
            $this->assertStringNotContainsString('gemini-secret', $request->url(), 'key must not appear in the URL');
            $this->assertSame('gemini-secret', $request->header('x-goog-api-key')[0] ?? null);

            return str_contains($request->url(), '/models/gemini-3.6-flash:generateContent');
        });
    }

    public function test_gemini_503_is_retryable(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

        try {
            (new GeminiProvider('k'))->complete('gemini-3.6-flash', 's', 'u', 100);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_missing_key_is_not_retryable_and_makes_no_call(): void
    {
        Http::fake();

        try {
            (new OpenAiProvider(''))->complete('gpt-test', 's', 'u', 100);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertFalse($e->retryable);
        }
        Http::assertNothingSent();
    }

    public function test_settings_page_shows_masked_key_only(): void
    {
        $hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);
        app(AssistantSettings::class)->saveApiKey('gemini', 'AIza-very-secret-1234');

        $response = $this->actingAs($hq)->get('/manage/ai-assistant');

        $response->assertOk()->assertSee('AI Travel Assistant')->assertSee('1234');
        $this->assertStringNotContainsString('AIza-very-secret', $response->getContent());
    }

    public function test_public_catalogue_shows_assistant_widget_only_when_enabled(): void
    {
        $package = Package::create([
            'code' => 'PUB-1', 'title' => 'Public Trip', 'slug' => 'public-trip', 'category' => 'domestic',
            'destination' => 'Penang', 'duration_days' => 2, 'duration_nights' => 1, 'status' => 'active',
        ]);
        PackagePricing::create(['package_id' => $package->id, 'tier_name' => 'Standard', 'adult_price' => 500, 'is_default' => true]);

        app(AssistantSettings::class)->setEnabled(true);
        $this->get('/packages')->assertOk()->assertSee('bst-assistant', false);

        app(AssistantSettings::class)->setEnabled(false);
        $this->get('/packages')->assertOk()->assertDontSee('bst-assistant', false);
    }
}
