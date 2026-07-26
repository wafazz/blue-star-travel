<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentBookingStatusTest extends TestCase
{
    use RefreshDatabase;

    private function agent(): User
    {
        return User::factory()->create(['role' => 'agent', 'status' => 'active']);
    }

    private function booking(User $agent, string $status): Booking
    {
        static $n = 0;
        $n++;

        $package = Package::create([
            'code' => "PKG-{$n}", 'title' => "Package {$n}", 'slug' => "package-{$n}",
            'category' => 'domestic', 'status' => 'active',
        ]);
        $customer = Customer::create(['name' => "Customer {$n}", 'agent_id' => $agent->id]);

        return Booking::create([
            'booking_no'  => "BK-TEST-{$n}",
            'package_id'  => $package->id,
            'customer_id' => $customer->id,
            'agent_id'    => $agent->id,
            'status'      => $status,
        ]);
    }

    public function test_every_db_status_maps_to_one_of_the_six_agent_labels(): void
    {
        $labels = [];
        foreach (array_keys(Booking::STATUSES) as $status) {
            $labels[] = (new Booking(['status' => $status]))->agentStatusLabel();
        }

        $expected = array_values(Booking::AGENT_TABS);
        sort($expected);
        $actual = array_values(array_unique($labels));
        sort($actual);

        $this->assertSame($expected, $actual, 'Every DB status must collapse into the six agent-facing labels.');
    }

    public function test_needs_revision_renders_as_amber_need_revision(): void
    {
        $booking = new Booking(['status' => 'needs_revision']);

        $this->assertSame('Need Revision', $booking->agentStatusLabel());
        $this->assertSame('warning', $booking->agentStatusBadge());
        $this->assertTrue($booking->needsRevision());
        $this->assertTrue($booking->isEditableByAgent());
    }

    /** An agent may edit anything that is not finished or dead — confirmed included. */
    public function test_every_live_booking_is_editable_and_only_finished_ones_are_locked(): void
    {
        foreach (array_keys(Booking::STATUSES) as $status) {
            $editable = (new Booking(['status' => $status]))->isEditableByAgent();
            $this->assertSame(
                ! in_array($status, ['completed', 'cancelled', 'rejected', 'refunded']),
                $editable,
                "Wrong edit permission for {$status}"
            );
        }

        $this->assertTrue((new Booking(['status' => 'confirmed']))->isEditableByAgent());
        $this->assertFalse((new Booking(['status' => 'completed']))->isEditableByAgent());
        $this->assertFalse((new Booking(['status' => 'cancelled']))->isEditableByAgent());
    }

    public function test_the_submitted_tab_covers_every_status_that_shares_its_label(): void
    {
        $agent = $this->agent();
        foreach (['pending_payment', 'pending_verification'] as $status) {
            $this->booking($agent, $status);
        }
        $this->booking($agent, 'confirmed');

        $response = $this->actingAs($agent)->get(route('agent.bookings.index', ['tab' => 'submitted']));

        $response->assertOk();
        // A tab wired to a single status would silently hide the other.
        $this->assertCount(2, $response->viewData('bookings'));
    }

    /** The client's Status Guide calls a provider-side booking "Approved", not "Submitted". */
    public function test_waiting_on_the_provider_reads_as_approved(): void
    {
        $agent = $this->agent();
        $this->booking($agent, 'waiting_provider_confirmation');

        $response = $this->actingAs($agent)->get(route('agent.bookings.index', ['tab' => 'approved']));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('bookings'));
        $response->assertSee('Approved');
    }

    public function test_the_status_guide_legend_lists_every_agent_label(): void
    {
        $agent = $this->agent();
        $response = $this->actingAs($agent)->get(route('agent.bookings.index'));

        $response->assertOk();
        $response->assertSee('Status Guide');
        foreach (Booking::AGENT_STATUS_GUIDE as $label => [$badge, $meaning]) {
            $response->assertSee($label);
            $response->assertSee($meaning);
        }
    }

    public function test_the_list_can_be_searched_by_customer_name_and_booking_number(): void
    {
        $agent = $this->agent();
        $mine = $this->booking($agent, 'confirmed');
        $mine->customer->update(['name' => 'Nur Aina Binti Rahim', 'phone' => '0123456789']);
        $this->booking($agent, 'confirmed');

        $byName = $this->actingAs($agent)->get(route('agent.bookings.index', ['q' => 'Nur Aina']));
        $this->assertCount(1, $byName->viewData('bookings'));

        $byPhone = $this->actingAs($agent)->get(route('agent.bookings.index', ['q' => '0123456789']));
        $this->assertCount(1, $byPhone->viewData('bookings'));

        $byNo = $this->actingAs($agent)->get(route('agent.bookings.index', ['q' => $mine->booking_no]));
        $this->assertCount(1, $byNo->viewData('bookings'));
    }

    public function test_the_needs_revision_tab_returns_only_those_bookings(): void
    {
        $agent = $this->agent();
        $this->booking($agent, 'needs_revision');
        $this->booking($agent, 'pending_verification');
        $this->booking($agent, 'confirmed');

        $response = $this->actingAs($agent)->get(route('agent.bookings.index', ['tab' => 'needs_revision']));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('bookings'));
        $response->assertSee('Need Revision');
    }

    public function test_an_unknown_tab_falls_back_to_showing_everything(): void
    {
        $agent = $this->agent();
        $this->booking($agent, 'needs_revision');
        $this->booking($agent, 'confirmed');

        $response = $this->actingAs($agent)->get(route('agent.bookings.index', ['tab' => 'bogus']));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('bookings'));
    }

    public function test_tabs_never_leak_another_agents_bookings(): void
    {
        $mine = $this->agent();
        $theirs = $this->agent();
        $this->booking($mine, 'needs_revision');
        $this->booking($theirs, 'needs_revision');

        $response = $this->actingAs($mine)->get(route('agent.bookings.index', ['tab' => 'needs_revision']));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('bookings'));
    }

    public function test_pickup_details_survive_a_round_trip_and_show_on_the_detail_screen(): void
    {
        $agent = $this->agent();
        $booking = $this->booking($agent, 'needs_revision');
        $booking->update(['pickup_location' => 'KLIA2 Gate C, Level 2', 'arrival_time' => '06:30']);

        $response = $this->actingAs($agent)->get(route('agent.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('KLIA2 Gate C, Level 2');
        $response->assertSee('06:30');
    }
}
