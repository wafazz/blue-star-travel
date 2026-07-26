<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_pax', function (Blueprint $table) {
            // The client asks agents for a child's AGE, not a birthday — it is what the
            // provider needs for bed/fare rules. `dob` stays for passport paperwork.
            $table->unsignedTinyInteger('age')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('booking_pax', function (Blueprint $table) {
            $table->dropColumn('age');
        });
    }
};
