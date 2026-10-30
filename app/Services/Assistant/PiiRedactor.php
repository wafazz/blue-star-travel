<?php

namespace App\Services\Assistant;

/**
 * Removes contact details and identity numbers from text before it is sent to an AI provider
 * (OQ-6: no customer personal data leaves the system). Applied to visitor questions sent to the
 * model and to staff drafting input. The original text is still kept in our own log.
 *
 * This is best-effort pattern matching. Staff are told not to paste personal details at all.
 */
class PiiRedactor
{
    public function redact(string $text): string
    {
        // Email addresses.
        $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $text) ?? $text;

        // Malaysian mobile and landline style numbers: +60 / 60 / 0 prefix, optional separators.
        $text = preg_replace('/(?<![\w])(?:\+?60|0)[\s-]?\d{1,2}[\s-]?\d{3,4}[\s-]?\d{3,4}(?![\w])/', '[phone]', $text) ?? $text;

        // Malaysian NRIC (YYMMDD-PB-###G), with or without dashes.
        $text = preg_replace('/(?<![\w])\d{6}-?\d{2}-?\d{4}(?![\w])/', '[id-number]', $text) ?? $text;

        // Passport-style numbers: one letter then 6 to 9 digits, or two letters then digits.
        $text = preg_replace('/(?<![\w])[A-Z]{1,2}\d{6,9}(?![\w])/i', '[passport]', $text) ?? $text;

        return $text;
    }
}
