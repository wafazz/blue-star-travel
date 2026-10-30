<?php

namespace App\Services\Assistant;

/**
 * Decides whether a message must go to a human instead of the model (REQ-AI-005, ADR-AI-003).
 *
 * Money, booking changes and complaints are never answered by the assistant. This check
 * runs before any provider call, so a refused request costs nothing and the model never
 * sees it.
 */
class IntentGuard
{
    public const ROUTE_ASSISTANT = 'assistant';
    public const ROUTE_HANDOFF = 'handoff';

    /**
     * English and Bahasa Malaysia terms. Matched as whole words, case-insensitive.
     *
     * @var array<int, string>
     */
    private const HANDOFF_TERMS = [
        // money
        'refund', 'refunds', 'pay', 'paid', 'payment', 'bayar', 'pembayaran', 'bayaran',
        'charge', 'charged', 'invoice', 'receipt', 'resit', 'wallet', 'deposit', 'fpx',
        // booking changes
        'cancel', 'cancelled', 'cancellation', 'batal', 'pembatalan',
        'reschedule', 'change date', 'tukar tarikh', 'amend', 'amendment', 'ubah',
        // complaints and disputes
        'complaint', 'complain', 'aduan', 'komplen', 'scam', 'penipuan', 'lawyer', 'peguam',
    ];

    public function route(string $text): string
    {
        return $this->matches($text) ? self::ROUTE_HANDOFF : self::ROUTE_ASSISTANT;
    }

    public function matches(string $text): bool
    {
        $normalised = mb_strtolower($text, 'UTF-8');

        foreach (self::HANDOFF_TERMS as $term) {
            $pattern = '/(?<![\pL\pN])' . preg_quote($term, '/') . '(?![\pL\pN])/u';
            if (preg_match($pattern, $normalised) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fixed reply shown instead of a model answer. Translations are kept in the same place
     * so staff can review them together.
     */
    public function handoffMessage(): string
    {
        return 'Untuk bantuan mengenai bayaran, pembatalan, atau aduan, sila hubungi pasukan kami. '
            . 'For help with payments, cancellations, or complaints, please contact our team.';
    }
}
