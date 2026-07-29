<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_amendments', function (Blueprint $table) {
            // Nullable at the DB level because a pickup change needs no evidence — the
            // "mandatory" in the client's rule is per-type and lives in the request layer.
            $table->string('attachment_path')->nullable()->after('reason');
            // A date change with no new date. Distinct from "no date supplied yet" —
            // the agent is stating the customer has NOT decided, which is the whole point.
            $table->boolean('is_postponement')->default(false)->after('requested_arrival_time');
        });
    }

    public function down(): void
    {
        Schema::table('booking_amendments', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'is_postponement']);
        });
    }
};
