<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Services\BookingService;
use App\Services\CommissionService;
use Illuminate\Database\Seeder;

class ShowcaseSeeder extends Seeder
{
    /**
     * The end-to-end demo sale: agent BT-AG003 sells an Umrah package, the booking
     * clears the provider + payment workflow, which cascades commission up the MLM
     * tree, fires gamification and notifies everyone. Keeps `migrate:fresh --seed`
     * reproducing the full showcase instead of relying on hand-made records.
     */
    public function run(): void
    {
        $seller = User::where('agent_code', 'BT-AG003')->first();
        $admin  = User::where('email', 'super@bluetravel.com')->first();
        $package = Package::where('status', 'active')->orderBy('id')->first();

        if (! $seller || ! $package || Booking::where('agent_id', $seller->id)->exists()) {
            return;
        }

        $customer = Customer::whereNull('user_id')->orderBy('id')->first();
        if (! $customer) {
            return;
        }
        $customer->update(['agent_id' => $seller->id]);

        $bookings = app(BookingService::class);

        $booking = $bookings->create([
            'package_id'  => $package->id,
            'customer_id' => $customer->id,
            'agent_id'    => $seller->id,
            'type'        => 'manual',
            'adults'      => 2,
            'children'    => 0,
            'infants'     => 0,
            'travel_date' => now()->addMonths(2)->toDateString(),
            'notes'       => 'Repeat customer — please arrange wheelchair assistance.',
        ], $seller, [
            ['name' => $customer->name, 'type' => 'adult', 'ic_passport_no' => $customer->ic_passport_no, 'is_lead' => true],
        ]);

        // Admin verifies → provider approves → admin confirms (invoice + voucher issued).
        $bookings->submitToProvider($booking, $admin);
        $bookings->providerRespond($booking, $admin, 'approved', 'Seats and hotel confirmed.');
        $bookings->confirm($booking, $admin);

        // Paid in full → receipt, commission cascade, missions + achievements.
        $payment = $bookings->recordPayment($booking, [
            'amount'    => $booking->total_amount,
            'method'    => 'fpx',
            'type'      => 'full',
            'reference' => 'FPX-DEMO-0001',
        ], $seller);
        $bookings->verifyPayment($payment, $admin);

        // HQ releases the commission run into agent wallets (KPDN approval gate).
        app(CommissionService::class)->approvePeriod($booking->fresh()->created_at->format('Y-m'), $admin);
    }
}
