<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pricing tier is the room type (Quad / Triple / Double) — capacity is how
        // many travellers share it, used to suggest how many rooms a party needs.
        Schema::table('package_pricings', function (Blueprint $table) {
            $table->unsignedTinyInteger('capacity')->default(2)->after('tier_name');
        });

        // One line per room type on a booking. Prices are snapshotted so a later
        // package price change never rewrites an issued invoice.
        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('package_pricing_id')->nullable()->constrained('package_pricings')->nullOnDelete();
            $table->string('room_name');
            $table->unsignedTinyInteger('capacity')->default(2);
            $table->unsignedSmallInteger('rooms')->default(1);
            $table->unsignedSmallInteger('adults')->default(0);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('seniors')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->decimal('adult_price', 10, 2)->default(0);
            $table->decimal('child_price', 10, 2)->default(0);
            $table->decimal('senior_price', 10, 2)->default(0);
            $table->decimal('infant_price', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
        Schema::table('package_pricings', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
