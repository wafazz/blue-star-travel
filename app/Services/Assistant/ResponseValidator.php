<?php

namespace App\Services\Assistant;

/**
 * Validates the model's reply against a strict schema and the package IDs that were
 * actually supplied in the prompt (REQ-AI-014, REQ-AI-015, ADR-AI-002).
 *
 * Expected model output:
 *   {"suggestions": [{"package_id": 12, "reason": "short text"}]}
 *
 * Anything else yields an empty, safe result. Prices and names are never read from the
 * model; the view looks them up from the database by package_id.
 */
class ResponseValidator
{
    public const MAX_SUGGESTIONS = 3;
    public const MAX_REASON_CHARS = 300;

    /**
     * @param  array<int>  $allowedPackageIds  IDs of active packages supplied to the model
     * @return array{ok: bool, suggestions: array<int, array{package_id: int, reason: string}>}
     */
    public function validate(string $raw, array $allowedPackageIds): array
    {
        $empty = ['ok' => false, 'suggestions' => []];

        // Models sometimes wrap JSON in a markdown fence; strip it before decoding.
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)) ?? '');

        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['suggestions']) || ! is_array($data['suggestions'])) {
            return $empty;
        }

        $allowed = array_flip(array_map('intval', $allowedPackageIds));
        $seen = [];
        $suggestions = [];

        foreach ($data['suggestions'] as $item) {
            if (! is_array($item) || ! isset($item['package_id'], $item['reason'])) {
                continue;
            }
            if (! is_int($item['package_id']) || ! is_string($item['reason'])) {
                continue;
            }

            $id = $item['package_id'];
            if (! isset($allowed[$id]) || isset($seen[$id])) {
                continue; // unknown or duplicate package: drop silently (ADR-AI-002)
            }

            $reason = trim(strip_tags($item['reason']));
            if ($reason === '') {
                continue;
            }

            $seen[$id] = true;
            $suggestions[] = [
                'package_id' => $id,
                'reason' => mb_substr($reason, 0, self::MAX_REASON_CHARS, 'UTF-8'),
            ];

            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return ['ok' => true, 'suggestions' => $suggestions];
    }
}
