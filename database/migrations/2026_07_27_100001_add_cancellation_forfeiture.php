<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Null falls back to Package::DEFAULT_CANCELLATION_FEE. An explicit 0 waives it.
            $table->decimal('cancellation_fee_per_pack', 10, 2)->nullable()->after('date_mode');
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Cumulative, never recomputed — two successive reductions must charge for both.
            $table->unsignedSmallInteger('forfeited_packs')->default(0)->after('paid_amount');
            $table->decimal('forfeited_amount', 12, 2)->default(0)->after('forfeited_packs');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('cancellation_fee_per_pack');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['forfeited_packs', 'forfeited_amount']);
        });
    }
};
