<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_amendments', function (Blueprint $table) {
            // Check-out for a requested date change. Null = no check-out given, which
            // falls back to the package length, the same as a booking with no check-out.
            $table->date('requested_return_date')->nullable()->after('requested_date');
        });
    }

    public function down(): void
    {
        Schema::table('booking_amendments', function (Blueprint $table) {
            $table->dropColumn('requested_return_date');
        });
    }
};
