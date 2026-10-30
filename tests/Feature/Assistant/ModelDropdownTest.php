<?php

namespace Tests\Feature\Assistant;

use App\Http\Controllers\Manage\AiAssistantController;
use App\Models\User;
use App\Services\Assistant\AssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Model dropdown on the HQ settings page: listed choices, and "Other" for IDs not in the list. */
class ModelDropdownTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $model, array $other = []): array
    {
        return [
            'primary' => 'openai',
            'model' => $model + ['anthropic' => '', 'openai' => '', 'gemini' => ''],
            'model_other' => $other,
            'key' => ['anthropic' => '', 'openai' => '', 'gemini' => ''],
            'per_customer_hour' => 10,
            'per_day_total' => 200,
        ];
    }

    public function test_settings_page_lists_the_known_model_ids_as_options(): void
    {
        $hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);

        $response = $this->actingAs($hq)->get('/manage/ai-assistant');

        $response->assertOk()
            ->assertSee('value="gpt-6-astra"', false)
            ->assertSee('value="claude-opus-5-5"', false)
            ->assertSee('value="gemini-3.6-flash"', false)
            ->assertSee('Other (type the model ID below)');
    }

    public function test_listed_choice_is_saved_as_the_model(): void
    {
        $hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);

        $this->actingAs($hq)->put('/manage/ai-assistant', $this->payload(['openai' => 'gpt-6-astra']))
            ->assertRedirect();

        $this->assertSame('gpt-6-astra', app(AssistantSettings::class)->model('openai'));
    }

    public function test_other_uses_the_typed_model_id(): void
    {
        $hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);

        $this->actingAs($hq)->put('/manage/ai-assistant', $this->payload(
            ['openai' => AiAssistantController::OTHER_MODEL],
            ['openai' => 'gpt-brand-new-id'],
        ))->assertRedirect();

        $this->assertSame('gpt-brand-new-id', app(AssistantSettings::class)->model('openai'));
    }

    public function test_other_with_blank_text_clears_the_model(): void
    {
        $hq = User::factory()->create(['role' => 'hq', 'status' => 'active']);
        app(AssistantSettings::class)->saveModel('openai', 'gpt-6-astra');

        $this->actingAs($hq)->put('/manage/ai-assistant', $this->payload(
            ['openai' => AiAssistantController::OTHER_MODEL],
            ['openai' => ''],
        ))->assertRedirect();

        // Blank falls back to the config default, which for OpenAI is empty.
        $this->assertNull(app(AssistantSettings::class)->model('openai'));
    }
}
