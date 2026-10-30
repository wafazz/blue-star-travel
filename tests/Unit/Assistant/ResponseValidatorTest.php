<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\ResponseValidator;
use PHPUnit\Framework\TestCase;

/** Covers REQ-AI-014 (strict schema) and REQ-AI-015 (only supplied package IDs). */
class ResponseValidatorTest extends TestCase
{
    public function test_valid_reply_keeps_only_supplied_package_ids(): void
    {
        $v = new ResponseValidator();
        $raw = json_encode(['suggestions' => [
            ['package_id' => 1, 'reason' => 'Good for families'],
            ['package_id' => 999, 'reason' => 'Invented package'],
        ]]);

        $out = $v->validate($raw, [1, 2]);

        $this->assertTrue($out['ok']);
        $this->assertSame([['package_id' => 1, 'reason' => 'Good for families']], $out['suggestions']);
    }

    public function test_malformed_json_gives_safe_empty_result(): void
    {
        $v = new ResponseValidator();

        $out = $v->validate('Sure! Here are some ideas...', [1]);

        $this->assertFalse($out['ok']);
        $this->assertSame([], $out['suggestions']);
    }

    public function test_markdown_fence_is_accepted(): void
    {
        $v = new ResponseValidator();
        $raw = "```json\n{\"suggestions\": [{\"package_id\": 2, \"reason\": \"Beach\"}]}\n```";

        $out = $v->validate($raw, [2]);

        $this->assertTrue($out['ok']);
        $this->assertSame(2, $out['suggestions'][0]['package_id']);
    }

    public function test_price_in_model_text_is_ignored_and_not_returned(): void
    {
        $v = new ResponseValidator();
        $raw = json_encode(['suggestions' => [
            ['package_id' => 4, 'reason' => 'Only RM99!', 'price' => 99],
        ]]);

        $out = $v->validate($raw, [4]);

        $this->assertArrayNotHasKey('price', $out['suggestions'][0]);
    }

    public function test_string_package_id_is_rejected(): void
    {
        $v = new ResponseValidator();
        $raw = json_encode(['suggestions' => [['package_id' => '1', 'reason' => 'x']]]);

        $this->assertSame([], $v->validate($raw, [1])['suggestions']);
    }

    public function test_duplicates_and_extra_items_are_capped(): void
    {
        $v = new ResponseValidator();
        $items = [];
        foreach ([1, 1, 2, 3, 4, 5] as $id) {
            $items[] = ['package_id' => $id, 'reason' => 'r'];
        }

        $out = $v->validate(json_encode(['suggestions' => $items]), [1, 2, 3, 4, 5]);

        $ids = array_column($out['suggestions'], 'package_id');
        $this->assertSame([1, 2, 3], $ids);
    }

    public function test_html_in_reason_is_stripped(): void
    {
        $v = new ResponseValidator();
        $raw = json_encode(['suggestions' => [
            ['package_id' => 1, 'reason' => '<script>alert(1)</script>Nice beach'],
        ]]);

        $out = $v->validate($raw, [1]);

        $this->assertStringNotContainsString('<script>', $out['suggestions'][0]['reason']);
        $this->assertStringContainsString('Nice beach', $out['suggestions'][0]['reason']);
    }
}
