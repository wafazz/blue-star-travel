<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            // How many packs the item applies to. NULL = every pack on the booking, so
            // rows written before this column existed keep their meaning.
            $table->unsignedInteger('quantity')->nullable()->after('unit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
