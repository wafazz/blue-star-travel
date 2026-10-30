<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\IntentGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Covers REQ-AI-005: money, booking changes and complaints never reach the model. */
class IntentGuardTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function handoffMessages(): array
    {
        return [
            'english refund' => ['I want a refund for my trip'],
            'malay cancel' => ['saya nak batal booking'],
            'payment question' => ['Why was I charged twice? payment failed'],
            'complaint' => ['This is a complaint about my guide'],
            'date change' => ['Can I change date to next week?'],
            'uppercase' => ['CANCEL MY BOOKING'],
        ];
    }

    #[DataProvider('handoffMessages')]
    public function test_money_booking_and_complaint_messages_go_to_handoff(string $text): void
    {
        $this->assertSame(IntentGuard::ROUTE_HANDOFF, (new IntentGuard())->route($text));
    }

    public function test_normal_travel_question_goes_to_assistant(): void
    {
        $this->assertSame(
            IntentGuard::ROUTE_ASSISTANT,
            (new IntentGuard())->route('Suggest a beach package for 4 people in December')
        );
    }

    public function test_whole_word_only_so_unrelated_words_pass(): void
    {
        // "payment" must not match inside an unrelated word, and "pay" must not match "paysage".
        $this->assertSame(
            IntentGuard::ROUTE_ASSISTANT,
            (new IntentGuard())->route('Show me a paysage view package')
        );
    }

    public function test_handoff_message_is_bilingual_and_fixed(): void
    {
        $msg = (new IntentGuard())->handoffMessage();

        $this->assertStringContainsString('bayaran', $msg);
        $this->assertStringContainsString('payments', $msg);
    }
}
