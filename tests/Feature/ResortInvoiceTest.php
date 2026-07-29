<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResortInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->create([
            'role' => 'admin', 'status' => 'active', 'permissions' => ['bookings'],
        ]);
        $this->agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
    }

    private function booking(string $status = 'pending_verification'): Booking
    {
        static $n = 0;
        $n++;

        $provider = Provider::create(['name' => "Provider {$n}", 'type' => 'local_operator', 'status' => 'active']);
        $package = Package::create([
            'code' => "PKG-RI-{$n}", 'title' => "Package {$n}", 'slug' => "package-ri-{$n}",
            'category' => 'domestic', 'status' => 'active', 'provider_id' => $provider->id,
        ]);
        $customer = Customer::create(['name' => "Customer {$n}", 'agent_id' => $this->agent->id]);

        return Booking::create([
            'booking_no'  => "BK-RI-{$n}",
            'package_id'  => $package->id,
            'customer_id' => $customer->id,
            'agent_id'    => $this->agent->id,
            'provider_id' => $provider->id,
            'status'      => $status,
        ]);
    }

    private function upload(Booking $booking, ?UploadedFile $file = null)
    {
        return $this->actingAs($this->admin)->post(route('manage.bookings.resort-invoice', $booking), [
            'resort_invoice' => $file ?? UploadedFile::fake()->create('resort.pdf', 40, 'application/pdf'),
        ]);
    }

    public function test_confirm_is_refused_until_the_resort_invoice_is_uploaded(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->admin)->post(route('manage.bookings.confirm', $booking))
            ->assertSessionHasErrors('resort_invoice');

        $this->assertSame('pending_verification', $booking->fresh()->status);
    }

    public function test_confirm_goes_through_once_the_invoice_is_on_file(): void
    {
        $booking = $this->booking();
        $this->upload($booking)->assertRedirect();

        $this->actingAs($this->admin)->post(route('manage.bookings.confirm', $booking))
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_the_upload_lands_on_the_private_disk_and_is_logged(): void
    {
        $booking = $this->booking();
        $this->upload($booking);

        $doc = $booking->fresh()->resortInvoice();
        $this->assertNotNull($doc);
        $this->assertTrue($doc->isInternal());
        $this->assertSame($this->admin->id, $doc->uploaded_by);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertTrue($booking->timeline()->where('action', 'Resort invoice uploaded')->exists());
    }

    public function test_re_uploading_replaces_the_file_instead_of_stacking_documents(): void
    {
        $booking = $this->booking();
        $this->upload($booking);
        $first = $booking->fresh()->resortInvoice()->file_path;

        $this->upload($booking, UploadedFile::fake()->create('resort-v2.pdf', 40, 'application/pdf'));

        $booking->refresh();
        $this->assertSame(1, $booking->documents()->where('type', 'resort_invoice')->count());
        $this->assertNotSame($first, $booking->resortInvoice()->file_path);
        Storage::disk('local')->assertMissing($first);
    }

    public function test_the_agent_who_owns_the_booking_cannot_download_the_resort_invoice(): void
    {
        $booking = $this->booking();
        $this->upload($booking);
        $doc = $booking->fresh()->resortInvoice();

        $this->actingAs($this->agent)->get(route('documents.download.portal', $doc))->assertForbidden();
        $this->actingAs($this->admin)->get(route('documents.download', $doc))->assertOk();
    }

    public function test_the_agent_booking_screen_never_lists_the_resort_invoice(): void
    {
        $booking = $this->booking();
        $this->upload($booking);
        BookingDocument::create([
            'booking_id' => $booking->id,
            'type'       => 'voucher',
            'title'      => 'Voucher-' . $booking->booking_no,
            'file_path'  => "booking-docs/{$booking->id}/voucher.pdf",
        ]);

        $this->actingAs($this->agent)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Travel Voucher')
            ->assertDontSee('Resort Invoice');
    }

    public function test_an_agent_cannot_post_to_the_upload_route(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->agent)->post(route('manage.bookings.resort-invoice', $booking), [
            'resort_invoice' => UploadedFile::fake()->create('resort.pdf', 40, 'application/pdf'),
        ])->assertForbidden();
    }
}
