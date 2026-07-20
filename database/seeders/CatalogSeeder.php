<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(['id' => 1], [
            'name'            => 'Blue Star Travel And Tours Sdn Bhd',
            'legal_name'      => 'Blue Star Travel And Tours Sdn Bhd',
            'registration_no' => '202401001234 (1234567-A)',
            'license_no'      => 'KPL/LN 1234',
            'email'           => 'hello@bluetravel.com',
            'phone'           => '+60 3-1234 5678',
            'website'         => 'https://bluetravel.com',
            'address'         => 'Level 12, Menara Blue, Jalan Ampang',
            'city'            => 'Kuala Lumpur',
            'state'           => 'Wilayah Persekutuan',
            'postcode'        => '50450',
            'country'         => 'Malaysia',
            'currency'        => 'MYR',
            'bank_name'       => 'Maybank',
            'bank_account_no' => '5123 4567 8901',
            'bank_account_name' => 'Blue Star Travel And Tours Sdn Bhd',
        ]);

        $providers = [
            ['Grand Hotel KL',        'hotel',          'Aiman Yusof',   'grandhotel@bluetravel.com'],
            ['Malaysia Airlines',     'airline',        'MAS Group Desk','mas@bluetravel.com'],
            ['Saudi Umrah Operator',  'local_operator', 'Abdullah Aziz', 'umrah@bluetravel.com'],
            ['Star Cruise Lines',     'local_operator', null,            null],
            ['Sakura Coach Japan',    'transport',      'Kenji Tanaka',  'sakura@bluetravel.com'],
            ['Bali Local Guides',     'tour_guide',     'Made Surya',    'bali@bluetravel.com'],
        ];

        $provModels = [];
        foreach ($providers as [$name, $type, $contact, $email]) {
            $provModels[$name] = Provider::updateOrCreate(
                ['name' => $name],
                ['type' => $type, 'contact_person' => $contact, 'email' => $email, 'status' => 'active', 'country' => 'Malaysia']
            );
        }

        // Link the demo provider login to the operator behind the showcase package,
        // otherwise that account owns no provider row (portal correctly locks it out)
        // or owns one with no bookings (portal is empty).
        $providerUser = User::where('email', 'provider@bluetravel.com')->first();
        if ($providerUser && isset($provModels['Saudi Umrah Operator'])) {
            $provModels['Saudi Umrah Operator']->update(['user_id' => $providerUser->id]);
        }

        $packages = [
            [
                'title' => 'Umrah Premium 12 Days', 'category' => 'umrah', 'provider' => 'Saudi Umrah Operator',
                'destination' => 'Makkah & Madinah', 'days' => 12, 'nights' => 11, 'featured' => true, 'status' => 'active',
                'summary' => 'A blessed 12-day Umrah journey with 5-star hotels near the Haram.',
                'adult' => 8900, 'child' => 6900, 'infant' => 1500, 'promo' => 8500,
            ],
            [
                'title' => 'Bali Free & Easy 5D4N', 'category' => 'free_easy', 'provider' => 'Bali Local Guides',
                'destination' => 'Bali, Indonesia', 'days' => 5, 'nights' => 4, 'featured' => true, 'status' => 'active',
                'summary' => 'Relax your way — flights, villa stay and airport transfers included.',
                'adult' => 3200, 'child' => 2400, 'infant' => 600, 'promo' => null,
            ],
            [
                'title' => 'Tokyo Discovery 7D6N', 'category' => 'international', 'provider' => 'Sakura Coach Japan',
                'destination' => 'Tokyo, Japan', 'days' => 7, 'nights' => 6, 'featured' => true, 'status' => 'active',
                'summary' => 'Explore Tokyo, Mt. Fuji and Disneyland with a guided coach tour.',
                'adult' => 11800, 'child' => 9800, 'infant' => 2000, 'promo' => 11200,
            ],
            [
                'title' => 'Langkawi Cruise 3D2N', 'category' => 'cruise', 'provider' => 'Star Cruise Lines',
                'destination' => 'Langkawi, Malaysia', 'days' => 3, 'nights' => 2, 'featured' => false, 'status' => 'active',
                'summary' => 'Set sail around Langkawi with full-board dining and entertainment.',
                'adult' => 2450, 'child' => 1800, 'infant' => 400, 'promo' => null,
            ],
            [
                'title' => 'Phuket Getaway 4D3N', 'category' => 'international', 'provider' => 'Grand Hotel KL',
                'destination' => 'Phuket, Thailand', 'days' => 4, 'nights' => 3, 'featured' => false, 'status' => 'active',
                'summary' => 'Sun, sand and island hopping in beautiful Phuket.',
                'adult' => 2100, 'child' => 1600, 'infant' => 350, 'promo' => 1950,
            ],
            [
                'title' => 'Cameron Highlands Getaway 3D2N', 'category' => 'domestic', 'provider' => 'Grand Hotel KL',
                'destination' => 'Cameron Highlands, Malaysia', 'days' => 3, 'nights' => 2, 'featured' => false, 'status' => 'active',
                'summary' => 'Cool weather, tea plantations and strawberry farms.',
                'adult' => 890, 'child' => 650, 'infant' => 150, 'promo' => null,
            ],
        ];

        $seq = 1;
        foreach ($packages as $p) {
            $package = Package::updateOrCreate(
                ['code' => 'PKG-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT)],
                [
                    'title'       => $p['title'],
                    'slug'        => Str::slug($p['title']),
                    'category'    => $p['category'],
                    'provider_id' => $provModels[$p['provider']]->id ?? null,
                    'destination' => $p['destination'],
                    'duration_days'   => $p['days'],
                    'duration_nights' => $p['nights'],
                    'summary'     => $p['summary'],
                    'description' => $p['summary'],
                    'itinerary'   => "Day 1: Arrival & check-in\nDay 2: Guided tour\nDay 3: Free & easy / departure",
                    'inclusions'  => "Return flights\nHotel accommodation\nDaily breakfast\nAirport transfers\nTour guide",
                    'exclusions'  => "Personal expenses\nTravel insurance\nOptional tours",
                    'terms'       => 'Prices are per person. Subject to availability and seasonal surcharge.',
                    'featured'    => $p['featured'],
                    'status'      => $p['status'],
                ]
            );

            $package->pricings()->delete();
            $package->pricings()->create([
                'tier_name'    => 'Standard',
                'adult_price'  => $p['adult'],
                'child_price'  => $p['child'],
                'infant_price' => $p['infant'],
                'promo_price'  => $p['promo'],
                'group_min'    => 10,
                'group_discount_percent' => 5,
                'is_default'   => true,
            ]);
            $package->pricings()->create([
                'tier_name'    => 'Deluxe',
                'adult_price'  => round($p['adult'] * 1.25),
                'child_price'  => round($p['child'] * 1.25),
                'infant_price' => $p['infant'],
                'is_default'   => false,
            ]);

            $package->dates()->delete();
            foreach ([30, 60, 90] as $offset) {
                $depart = now()->addDays($offset)->startOfDay();
                $package->dates()->create([
                    'depart_date' => $depart->toDateString(),
                    'return_date' => $depart->copy()->addDays($p['days'] - 1)->toDateString(),
                    'seats_total' => 40,
                    'seats_booked' => 8,
                    'status' => 'open',
                ]);
            }
            $seq++;
        }

        $agent = User::where('role', 'agent')->first();
        $customerUser = User::where('role', 'customer')->first();

        $customers = [
            ['Aisyah Kamal', 'aisyah@example.com', '013-4567890', 'A12345678'],
            ['Daniel Tan', 'daniel@example.com', '012-3456789', 'A23456789'],
            ['Farah Idris', 'farah@example.com', '019-8765432', 'A34567890'],
            ['Hafiz Rahman', 'hafiz@example.com', '017-2345678', 'A45678901'],
            ['Nurul Huda', 'nurul@example.com', '014-9988776', 'A56789012'],
        ];
        foreach ($customers as $i => [$name, $email, $phone, $passport]) {
            Customer::updateOrCreate(['email' => $email], [
                'user_id'  => $i === 0 && $customerUser ? $customerUser->id : null,
                'agent_id' => $agent?->id,
                'name'     => $name,
                'phone'    => $phone,
                'ic_passport_no' => $passport,
                'passport_expiry' => now()->addYears(4)->toDateString(),
                'nationality' => 'Malaysian',
                'emergency_contact_name'  => 'Next of Kin',
                'emergency_contact_phone' => '011-00000000',
                'loyalty_points' => ($i + 1) * 250,
                'status'   => 'active',
            ]);
        }
    }
}
