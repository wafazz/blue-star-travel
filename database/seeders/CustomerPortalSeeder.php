<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Services\BookingService;
use App\Services\TicketService;
use Illuminate\Database\Seeder;

class CustomerPortalSeeder extends Seeder
{
    public function run(): void
    {
        // Complete the demo customer's travel profile (passport + emergency contact).
        // The customers row carries its own email — look it up through the login user.
        $customer = User::where('email', 'customer@bluetravel.com')->first()?->customerProfile;
        if ($customer) {
            $customer->update([
                'ic_passport_no'          => 'A99887766',
                'passport_expiry'         => now()->addYears(5)->toDateString(),
                'nationality'             => 'Malaysian',
                'gender'                  => 'female',
                'address'                 => 'No 12, Jalan Kemuning 3',
                'postcode'                => '40150',
                'city'                    => 'Shah Alam',
                'state'                   => 'Selangor',
                'country'                 => 'Malaysia',
                'emergency_contact_name'  => 'Kamal Hassan',
                'emergency_contact_phone' => '019-888 7777',
            ]);
        }

        // A customer who signed up through an agent's referral link (?ref=BT-AG002).
        $referrer = User::where('agent_code', 'BT-AG002')->first();
        $referred = User::firstOrCreate(['email' => 'siti@bluetravel.com'], [
            'name'     => 'Siti Nurhaliza',
            'phone'    => '017-777 8888',
            'role'     => 'customer',
            'status'   => 'active',
            'password' => 'password',
        ]);
        Customer::firstOrCreate(['user_id' => $referred->id], [
            'agent_id' => $referrer?->id,
            'name'     => $referred->name,
            'email'    => $referred->email,
            'phone'    => $referred->phone,
            'status'   => 'active',
        ]);

        // A self-service online booking made from the customer portal, deposit paid,
        // still awaiting verification — showcases the customer booking → payment flow.
        $package = Package::where('status', 'active')->orderBy('id')->first();
        if ($customer && $package && ! Booking::where('customer_id', $customer->id)->where('type', 'online')->exists()) {
            $bookings = app(BookingService::class);

            $booking = $bookings->create([
                'package_id'  => $package->id,
                'customer_id' => $customer->id,
                'agent_id'    => $customer->agent_id,
                'type'        => 'online',
                'adults'      => 2,
                'children'    => 1,
                'infants'     => 0,
                'coupon_code' => 'RAYA2026',
                'notes'       => 'Please arrange adjoining rooms if possible.',
            ], $customer->user, [
                ['name' => $customer->name, 'type' => 'adult', 'ic_passport_no' => $customer->ic_passport_no, 'is_lead' => true],
            ]);

            $bookings->recordPayment($booking, [
                'amount'    => 2000,
                'method'    => 'slip_upload',
                'type'      => 'deposit',
                'reference' => 'TRX-ONLINE-0001',
            ], $customer->user);
        }

        // A customer-side support ticket (staff queue + threaded reply).
        if ($customer?->user && ! $customer->user->tickets()->exists()) {
            app(TicketService::class)->open($customer->user, [
                'subject'  => 'Room upgrade request',
                'category' => 'booking',
                'priority' => 'normal',
                'message'  => 'Hi, is it possible to upgrade our room to a quad for the Umrah trip? Thank you.',
            ]);
        }
    }
}
