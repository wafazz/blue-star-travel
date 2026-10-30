<?php

namespace App\Services\Assistant;

/**
 * Input hygiene for the AI assistant (REQ-AI-012, REQ-AI-013).
 *
 * - Customer text is length-limited, stripped of control characters and placed only in
 *   the user turn by the caller.
 * - Catalogue fields are escaped so they cannot close the data block they are wrapped in.
 * - Nothing here builds the system prompt; that stays a fixed string in code.
 */
class PromptSanitiser
{
    public const MAX_USER_CHARS = 500;

    public function sanitiseUserText(string $text, int $maxChars = self::MAX_USER_CHARS): string
    {
        // Drop control characters (keep normal spaces); newlines are folded to spaces.
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
        $clean = trim($clean);

        return mb_substr($clean, 0, $maxChars, 'UTF-8');
    }

    /**
     * Escape one catalogue field so it is plain data inside the delimited block.
     * Angle brackets are replaced so a field cannot write a closing tag.
     */
    public function sanitiseField(?string $value, int $maxChars = 200): string
    {
        $clean = (string) $value;
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $clean) ?? '';
        $clean = str_replace(['<', '>'], ['(', ')'], $clean);
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        return mb_substr($clean, 0, $maxChars, 'UTF-8');
    }

    /**
     * Wrap catalogue rows in a block the model is told to treat as data only.
     *
     * @param  array<int, array{id:int, name:?string, category:?string, destination:?string, days:?int, price:?string}>  $packages
     */
    public function catalogueBlock(array $packages): string
    {
        $lines = [];
        foreach ($packages as $package) {
            $lines[] = sprintf(
                '{"package_id": %d, "name": "%s", "category": "%s", "destination": "%s", "days": %d, "price": "%s"}',
                (int) $package['id'],
                $this->sanitiseField($package['name'] ?? ''),
                $this->sanitiseField($package['category'] ?? ''),
                $this->sanitiseField($package['destination'] ?? ''),
                (int) ($package['days'] ?? 0),
                $this->sanitiseField($package['price'] ?? 'on request'),
            );
        }

        return "<catalogue_data>\n" . implode("\n", $lines) . "\n</catalogue_data>";
    }
}
