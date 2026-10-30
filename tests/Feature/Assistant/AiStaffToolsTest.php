<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\AssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Staff log, reply drafting, usage totals and prices (TASK-AI-031, TASK-AI-032, TASK-AI-040). */
class AiStaffToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $hq;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.drivers.anthropic.api_key' => null]);
        config(['ai.drivers.gemini.api_key' => null]);
        config(['ai.drivers.openai.api_key' => null]);

        $this->hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);

        $settings = app(AssistantSettings::class);
        $settings->setEnabled(true);
        $settings->saveProviderOrder('openai');
        $settings->saveModel('openai', 'gpt-test-model');
        $settings->saveApiKey('openai', 'sk-test-openai-secret');
    }

    private function conversationWithVisitorMessage(string $body): array
    {
        $conversation = AssistantConversation::create(['session_hash' => hash('sha256', $body)]);
        $message = $conversation->messages()->create([
            'role' => 'user', 'body' => $body, 'route' => 'assistant', 'status' => 'ok', 'created_at' => now(),
        ]);

        return [$conversation, $message];
    }

    private function openAiReply(string $content, int $in = 100, int $out = 20): array
    {
        return [
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out],
        ];
    }

    public function test_hq_can_see_conversation_list_and_visitor_text(): void
    {
        [$conversation] = $this->conversationWithVisitorMessage('Beach trip for two');

        $this->actingAs($this->hq)->get('/manage/ai-assistant/conversations')
            ->assertOk()->assertSee('Anonymous visitor');

        $this->actingAs($this->hq)->get(route('manage.ai-assistant.conversation', $conversation))
            ->assertOk()->assertSee('Beach trip for two');
    }

    public function test_ordinary_staff_and_customers_cannot_read_conversations(): void
    {
        [$conversation] = $this->conversationWithVisitorMessage('Private question');

        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);

        foreach ([$customer, $admin] as $user) {
            $this->actingAs($user)->get('/manage/ai-assistant/conversations')->assertStatus(403);
            $this->actingAs($user)->get(route('manage.ai-assistant.conversation', $conversation))->assertStatus(403);
            $this->actingAs($user)->get('/manage/ai-assistant/usage')->assertStatus(403);
        }
    }

    public function test_draft_is_generated_with_contact_details_removed_and_logged(): void
    {
        [$conversation, $message] = $this->conversationWithVisitorMessage(
            'Please refund me. Email a.person@example.com or call +60 12 345 6789'
        );

        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply('Thank you for your message. Our team will confirm the refund details.'), 200)]);

        $this->actingAs($this->hq)
            ->post(route('manage.ai-assistant.draft', $conversation), ['message_id' => $message->id])
            ->assertRedirect()
            ->assertSessionHas('draft_status', 'ok');

        Http::assertSent(function ($request) {
            $userMessage = (string) data_get($request->data(), 'messages.1.content');

            $this->assertStringNotContainsString('a.person@example.com', $userMessage);
            $this->assertStringNotContainsString('345 6789', $userMessage);
            $this->assertStringContainsString('[email]', $userMessage);

            return true;
        });

        $draft = AssistantMessage::where('conversation_id', $conversation->id)->where('route', 'draft')->first();
        $this->assertNotNull($draft);
        $this->assertSame('openai', $draft->provider);
        $this->assertSame(100, $draft->input_tokens);
    }

    public function test_draft_is_refused_when_assistant_is_switched_off(): void
    {
        [$conversation, $message] = $this->conversationWithVisitorMessage('Beach trip');
        app(AssistantSettings::class)->setEnabled(false);
        Http::fake();

        $this->actingAs($this->hq)
            ->post(route('manage.ai-assistant.draft', $conversation), ['message_id' => $message->id])
            ->assertSessionHas('draft_status', 'disabled');

        Http::assertNothingSent();
    }

    public function test_draft_reports_unavailable_without_leaking_provider_error(): void
    {
        [$conversation, $message] = $this->conversationWithVisitorMessage('Beach trip');
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'quota'], 429)]);

        $this->actingAs($this->hq)
            ->post(route('manage.ai-assistant.draft', $conversation), ['message_id' => $message->id])
            ->assertSessionHas('draft_status', 'unavailable');

        $this->assertSame(0, AssistantMessage::where('route', 'draft')->count());
    }

    public function test_visitor_question_is_redacted_before_it_reaches_the_provider(): void
    {
        // The assistant only calls a model when there is at least one active package to recommend.
        \App\Models\Package::create([
            'code' => 'RED-1', 'title' => 'Langkawi Escape', 'slug' => 'langkawi-escape-red', 'category' => 'domestic',
            'destination' => 'Langkawi', 'duration_days' => 3, 'duration_nights' => 2, 'status' => 'active',
        ]);
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply('{"suggestions":[]}'), 200)]);

        $this->postJson('/assistant/message', ['message' => 'Call me on 012-3456789 about Langkawi'])->assertOk();

        Http::assertSent(function ($request) {
            $userMessage = (string) data_get($request->data(), 'messages.1.content');

            $this->assertStringNotContainsString('012-3456789', $userMessage);
            $this->assertStringContainsString('[phone]', $userMessage);

            return true;
        });
    }

    public function test_usage_totals_and_cost_use_entered_prices(): void
    {
        [$conversation] = $this->conversationWithVisitorMessage('Usage seed');
        $conversation->messages()->create([
            'role' => 'assistant', 'body' => 'ok', 'route' => 'assistant', 'provider' => 'openai', 'model' => 'gpt-test-model',
            'input_tokens' => 1_000_000, 'output_tokens' => 500_000, 'status' => 'ok', 'created_at' => now(),
        ]);

        // $2 per 1M input and $4 per 1M output: 1M * 2 + 0.5M * 4 = 4.00
        app(AssistantSettings::class)->savePrices('openai', 2.0, 4.0);

        $response = $this->actingAs($this->hq)->get('/manage/ai-assistant/usage');
        $response->assertOk();

        $this->assertSame(1, $response->viewData('totals')['calls']);
        $this->assertSame(4.0, $response->viewData('totals')['cost']);
        $response->assertSee('4.00');
    }

    public function test_cost_is_not_guessed_when_a_used_provider_has_no_price(): void
    {
        [$conversation] = $this->conversationWithVisitorMessage('Usage seed');
        $conversation->messages()->create([
            'role' => 'assistant', 'body' => 'ok', 'route' => 'assistant', 'provider' => 'openai', 'model' => 'gpt-test-model',
            'input_tokens' => 10, 'output_tokens' => 10, 'status' => 'ok', 'created_at' => now(),
        ]);

        $response = $this->actingAs($this->hq)->get('/manage/ai-assistant/usage');

        $this->assertNull($response->viewData('totals')['cost']);
        $response->assertSee('Enter prices in settings');
    }

    public function test_hq_can_save_prices_from_settings_page(): void
    {
        $this->actingAs($this->hq)->put('/manage/ai-assistant', [
            'primary' => 'openai',
            'model' => ['openai' => 'gpt-test-model', 'anthropic' => '', 'gemini' => ''],
            'key' => ['openai' => '', 'anthropic' => '', 'gemini' => ''],
            'price_in' => ['openai' => '2.5', 'anthropic' => '', 'gemini' => ''],
            'price_out' => ['openai' => '10', 'anthropic' => '', 'gemini' => ''],
            'per_customer_hour' => 10,
            'per_day_total' => 200,
        ])->assertRedirect();

        $this->assertSame(2.5, app(AssistantSettings::class)->priceIn('openai'));
        $this->assertSame(10.0, app(AssistantSettings::class)->priceOut('openai'));
    }
}
