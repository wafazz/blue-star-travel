<?php

namespace Tests\Feature\Assistant;

use App\Models\User;
use App\Services\Assistant\AssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "Check for new models": add-only, chat models only, no secrets shown (see enhancement/plan.md). */
class ModelCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $hq;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.drivers.anthropic.api_key' => null, 'ai.drivers.openai.api_key' => null, 'ai.drivers.gemini.api_key' => null]);

        $this->hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);
        app(AssistantSettings::class)->saveApiKey('openai', 'sk-test-openai-secret');
        app(AssistantSettings::class)->saveApiKey('gemini', 'gemini-test-secret');
    }

    public function test_openai_check_adds_new_chat_models_and_skips_non_chat_ones(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => [
            ['id' => 'gpt-6-astra'],        // already in the list
            ['id' => 'gpt-7-new'],          // new chat model
            ['id' => 'gpt-image-2'],        // image: excluded
            ['id' => 'text-embedding-3'],   // not gpt-, excluded
        ]], 200)]);

        $response = $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'openai'));

        $response->assertSessionHas('ok');
        $this->assertStringContainsString('gpt-7-new', session('ok'));
        $this->assertStringNotContainsString('gpt-image-2', session('ok'));
        $this->assertSame(['gpt-7-new'], app(AssistantSettings::class)->discoveredModels('openai'));
    }

    public function test_new_model_appears_in_the_dropdown_but_is_not_made_active(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['id' => 'gpt-7-new']]], 200)]);

        $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'openai'));

        $this->actingAs($this->hq)->get('/manage/ai-assistant')
            ->assertSee('value="gpt-7-new"', false);

        // Adding a model must not change the model in use.
        $this->assertNull(app(AssistantSettings::class)->model('openai'));
    }

    public function test_check_never_removes_models_already_discovered(): void
    {
        app(AssistantSettings::class)->saveDiscoveredModels('openai', ['gpt-old-extra']);
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['id' => 'gpt-7-new']]], 200)]);

        $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'openai'));

        $this->assertSame(['gpt-old-extra', 'gpt-7-new'], app(AssistantSettings::class)->discoveredModels('openai'));
    }

    public function test_gemini_check_keeps_text_models_and_drops_embedding_and_tts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['models' => [
            ['name' => 'models/gemini-4-pro', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/gemini-2.5-flash-preview-tts', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/text-embedding-004', 'supportedGenerationMethods' => ['embedContent']],
        ]], 200)]);

        $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'gemini'));

        $this->assertSame(['gemini-4-pro'], app(AssistantSettings::class)->discoveredModels('gemini'));
    }

    public function test_nothing_new_says_so_and_changes_nothing(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['id' => 'gpt-6-astra']]], 200)]);

        $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'openai'))
            ->assertSessionHas('ok', 'OpenAI: no new models. Your list is up to date.');

        $this->assertSame([], app(AssistantSettings::class)->discoveredModels('openai'));
    }

    public function test_provider_error_is_reported_and_list_is_untouched(): void
    {
        app(AssistantSettings::class)->saveDiscoveredModels('openai', ['gpt-keep']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'down'], 500)]);

        $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'openai'))
            ->assertSessionHasErrors('openai');

        $this->assertSame(['gpt-keep'], app(AssistantSettings::class)->discoveredModels('openai'));
    }

    public function test_missing_key_gives_a_clear_message_without_calling_out(): void
    {
        Http::fake();

        // Anthropic has no key in this test setup.
        $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'anthropic'))
            ->assertSessionHasErrors('anthropic');

        Http::assertNothingSent();
    }

    public function test_only_hq_can_check_and_unknown_provider_is_404(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);

        $this->actingAs($customer)->post(route('manage.ai-assistant.models.refresh', 'openai'))->assertStatus(403);
        $this->actingAs($this->hq)->post('/manage/ai-assistant/models/nope/refresh')->assertStatus(404);
    }

    public function test_settings_page_does_not_reveal_the_key_after_a_check(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['id' => 'gpt-7-new']]], 200)]);

        $this->actingAs($this->hq)->post(route('manage.ai-assistant.models.refresh', 'openai'))->assertRedirect();

        $page = $this->actingAs($this->hq)->get('/manage/ai-assistant');
        $page->assertOk()->assertSee('value="gpt-7-new"', false);
        $this->assertStringNotContainsString('sk-test-openai-secret', $page->getContent());
    }
}
