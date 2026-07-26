<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackagePricing;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;
    private Package $package;
    private PackagePricing $quad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['bookings']]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active', 'name' => 'Ariff Agent']);

        $this->package = Package::create([
            'code' => 'PKG-1', 'title' => 'Umrah 12D', 'slug' => 'umrah-12d',
            'category' => 'umrah', 'status' => 'active', 'date_mode' => 'open',
        ]);
        $this->quad = PackagePricing::create([
            'package_id' => $this->package->id, 'tier_name' => 'Quad', 'capacity' => 4,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0, 'is_default' => true,
        ]);
    }

    private function booking(): Booking
    {
        static $n = 0;
        $n++;

        $customer = Customer::create(['name' => 'Nur Aina', 'phone' => '0123456789', 'agent_id' => $this->agent->id]);
        $booking = Booking::create([
            'booking_no' => "BK-HIST-{$n}", 'package_id' => $this->package->id,
            'customer_id' => $customer->id, 'agent_id' => $this->agent->id,
            'status' => 'pending_verification', 'travel_date' => '2026-09-14',
            'adults' => 2, 'total_pax' => 2, 'subtotal' => 17000, 'total_amount' => 17000,
        ]);
        $booking->rooms()->create([
            'package_pricing_id' => $this->quad->id, 'room_name' => 'Quad', 'rooms' => 1,
            'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0,
            'adult_price' => 8500, 'child_price' => 6000, 'senior_price' => 8500, 'infant_price' => 0,
            'subtotal' => 17000,
        ]);
        $booking->pax()->create(['name' => 'Nur Aina', 'type' => 'adult', 'is_lead' => true]);

        return $booking;
    }

    /** Drive one full round: admin sends back, agent edits, agent resubmits. */
    private function round(Booking $booking, string $phone): void
    {
        app(BookingService::class)->requestRevision($booking->refresh(), $this->admin, 'Fix the phone.', ['customer.phone']);

        $this->actingAs($this->agent)->post(route('agent.bookings.review', $booking), [
            'customer_name' => 'Nur Aina', 'customer_phone' => $phone,
            'package_id' => $this->package->id, 'travel_date' => '2026-09-14',
            'rooms' => [['package_pricing_id' => $this->quad->id, 'adults' => 2, 'children' => 0, 'seniors' => 0, 'infants' => 0]],
            'pax'   => [['key' => (string) $booking->pax()->first()->id, 'name' => 'Nur Aina', 'type' => 'adult']],
        ]);
        $this->actingAs($this->agent)->post(route('agent.bookings.resubmit', $booking), ['confirm' => '1']);
    }

    public function test_the_staff_screen_renders_three_correctly_nested_tabs(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0198887766');

        $html = $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))
            ->assertOk()->getContent();

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        foreach (['tab-details', 'tab-activity', 'tab-documents'] as $id) {
            $this->assertSame(1, $xpath->query("//div[@id='{$id}']")->length, "Missing pane #{$id}");
        }

        // Panes must be siblings, not nested inside one another — a stray </div> would
        // silently swallow one tab's content into another.
        foreach (['tab-activity', 'tab-documents'] as $id) {
            $this->assertSame(
                0,
                $xpath->query("//div[@id='tab-details']//div[@id='{$id}']")->length,
                "#{$id} is nested inside #tab-details — the tab markup is unbalanced."
            );
        }

        // Content landed in the right pane.
        $this->assertSame(1, $xpath->query("//div[@id='tab-details']//h6[contains(text(),'Passengers')]")->length);
        $this->assertSame(1, $xpath->query("//div[@id='tab-activity']//h6[contains(text(),'Activity History')]")->length);
        $this->assertSame(1, $xpath->query("//div[@id='tab-documents']//h6[contains(text(),'Documents')]")->length);

        // Revision History sits in the sidebar, always visible — never inside a tab pane.
        $this->assertSame(1, $xpath->query("//h6[contains(text(),'Revision History')]")->length);
        $this->assertSame(0, $xpath->query("//div[contains(@class,'tab-pane')]//h6[contains(text(),'Revision History')]")->length);
    }

    public function test_the_timeline_no_longer_appears_in_the_sidebar(): void
    {
        $booking = $this->booking();
        $html = $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))->getContent();

        // It moved into the Activity Log pane; the old sidebar heading must be gone.
        $this->assertStringNotContainsString('<h6 class="fw-bold mb-0">Timeline</h6>', $html);
        $this->assertStringContainsString('Financials', $html);
    }

    public function test_versions_are_listed_newest_first_with_author_and_timestamp(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0111111111');
        $this->round($booking, '0122222222');

        $response = $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))->assertOk();
        $versions = $response->viewData('booking')->versions;

        $this->assertSame([3, 2, 1], $versions->pluck('version')->all());
        $response->assertSee('Version 3');
        $response->assertSee('Latest');
        $response->assertSee('Ariff Agent');
        $response->assertSee('Original submission');
        $response->assertSee('Agent revision');
    }

    public function test_the_history_panel_never_loads_payload_or_changes_columns(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0198887766');

        $versions = $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))
            ->viewData('booking')->versions;

        // Two JSON blobs per row would be pulled for a list that only shows a label.
        $this->assertArrayNotHasKey('payload', $versions->first()->getAttributes());
        $this->assertArrayNotHasKey('changes', $versions->first()->getAttributes());
    }

    /** The only honest N+1 check: query count must not grow with the number of versions. */
    public function test_the_history_panel_query_count_does_not_grow_with_version_count(): void
    {
        $one = $this->booking();
        $this->round($one, '0111111111');

        $many = $this->booking();
        $this->round($many, '0111111111');
        $this->round($many, '0122222222');
        $this->round($many, '0133333333');

        $count = function (Booking $booking) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->assertSame($one->versions()->count() + 1, $many->versions()->count() - 1);
        $this->assertSame(
            $count($one),
            $count($many),
            'Rendering 4 versions costs more queries than 2 — the history panel is lazy-loading per row.'
        );
    }

    public function test_a_version_opens_and_shows_its_frozen_diff_and_its_own_data(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0198887766');
        $v2 = $booking->versions()->where('version', 2)->first();

        $response = $this->actingAs($this->admin)
            ->get(route('manage.bookings.versions.show', [$booking, $v2]))->assertOk();

        $response->assertSee('Version 2');
        $response->assertSee('Phone Number');
        $response->assertSee('0123456789');      // before
        $response->assertSee('0198887766');      // after
        $response->assertSee('Umrah 12D');       // payload label, no lookup needed
        $response->assertSee('Fix the phone.');  // the request it answered
    }

    public function test_the_initial_version_shows_no_diff_rows(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0198887766');
        $v1 = $booking->versions()->where('version', 1)->first();

        $this->actingAs($this->admin)
            ->get(route('manage.bookings.versions.show', [$booking, $v1]))
            ->assertOk()
            ->assertSee('No field-level changes were recorded');
    }

    public function test_a_version_from_another_booking_is_not_reachable(): void
    {
        $mine = $this->booking();
        $theirs = $this->booking();
        $this->round($mine, '0198887766');
        $this->round($theirs, '0177777777');

        $foreign = $theirs->versions()->first();

        $this->actingAs($this->admin)
            ->get(route('manage.bookings.versions.show', [$mine, $foreign]))
            ->assertNotFound();
    }

    public function test_an_agent_cannot_reach_the_staff_version_viewer(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0198887766');
        $version = $booking->versions()->first();

        $this->actingAs($this->agent)
            ->get(route('manage.bookings.versions.show', [$booking, $version]))
            ->assertForbidden();
    }

    public function test_an_admin_without_the_bookings_permission_is_denied(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0198887766');
        $version = $booking->versions()->first();
        $stranger = User::factory()->create(['role' => 'admin', 'status' => 'active', 'permissions' => ['reports']]);

        $this->actingAs($stranger)
            ->get(route('manage.bookings.versions.show', [$booking, $version]))
            ->assertForbidden();
    }

    public function test_the_timeline_entry_links_through_to_its_version(): void
    {
        $booking = $this->booking();
        $this->round($booking, '0198887766');
        $v2 = $booking->versions()->where('version', 2)->first();

        $this->actingAs($this->admin)->get(route('manage.bookings.show', $booking))
            ->assertOk()
            ->assertSee(route('manage.bookings.versions.show', [$booking, $v2]), false)
            ->assertSee('view changes');
    }
}
