<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setStatusEnum([
            'draft', 'pending_payment', 'pending_verification', 'waiting_provider_confirmation',
            'confirmed', 'rejected', 'cancelled', 'completed', 'refunded', 'needs_revision', 'postponed',
        ]);
    }

    public function down(): void
    {
        // A postponed booking is a confirmed booking that lost its date — that is what it
        // reverts to, and the MODIFY below would truncate the value to '' otherwise.
        DB::table('bookings')->where('status', 'postponed')->update(['status' => 'confirmed']);

        $this->setStatusEnum([
            'draft', 'pending_payment', 'pending_verification', 'waiting_provider_confirmation',
            'confirmed', 'rejected', 'cancelled', 'completed', 'refunded', 'needs_revision',
        ]);
    }

    // Same rule as 2026_07_26_100002: APPEND. MySQL stores enums by ordinal, so slipping
    // the new value in mid-list would silently re-label every existing row.
    private function setStatusEnum(array $values): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = "'" . implode("','", $values) . "'";
            DB::statement("ALTER TABLE bookings MODIFY status ENUM({$list}) NOT NULL DEFAULT 'pending_verification'");

            return;
        }

        Schema::table('bookings', function (Blueprint $table) use ($values) {
            $table->enum('status', $values)->default('pending_verification')->change();
        });
    }
};
