<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\PiiRedactor;
use PHPUnit\Framework\TestCase;

/** Covers OQ-6: contact details and identity numbers do not reach providers. */
class PiiRedactorTest extends TestCase
{
    public function test_email_is_removed(): void
    {
        $out = (new PiiRedactor())->redact('Email me at private.person@example.com please');

        $this->assertStringNotContainsString('private.person', $out);
        $this->assertStringContainsString('[email]', $out);
    }

    public function test_malaysian_phone_numbers_are_removed(): void
    {
        $r = new PiiRedactor();

        $this->assertStringContainsString('[phone]', $r->redact('call 012-3456789 tomorrow'));
        $this->assertStringContainsString('[phone]', $r->redact('+60 12 345 6789'));
        $this->assertStringContainsString('[phone]', $r->redact('wa 60123456789'));
    }

    public function test_nric_and_passport_numbers_are_removed(): void
    {
        $r = new PiiRedactor();

        $this->assertStringContainsString('[id-number]', $r->redact('my IC 900101-14-5678'));
        $this->assertStringContainsString('[passport]', $r->redact('passport A1234567'));
    }

    public function test_ordinary_travel_text_is_unchanged(): void
    {
        $text = 'Langkawi 3 days 2 nights under RM1200 for 4 adults';

        $this->assertSame($text, (new PiiRedactor())->redact($text));
    }
}
