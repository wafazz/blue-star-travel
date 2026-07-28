<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Check-out. Until now it was always derived from the departure row or the
            // package duration; an open-dated booking had nowhere to record a real one.
            $table->date('return_date')->nullable()->after('travel_date');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('return_date');
        });
    }
};
