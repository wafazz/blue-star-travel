








<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // fixed = scheduled departures only (seats enforced)
            // open  = traveller names their own date, no seat cap
            // both  = pick a departure OR request your own date
            $table->enum('date_mode', ['fixed', 'open', 'both'])->default('fixed')->after('duration_nights');
        });

        // A package with no departure rows can only ever have been open-dated.
        DB::table('packages')
            ->whereNotIn('id', fn ($q) => $q->select('package_id')->distinct()->from('package_dates'))
            ->update(['date_mode' => 'open']);
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('date_mode');
        });
    }
};
