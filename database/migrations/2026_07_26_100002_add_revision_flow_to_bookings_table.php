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
            'confirmed', 'rejected', 'cancelled', 'completed', 'refunded', 'needs_revision',
        ]);

        Schema::table('bookings', function (Blueprint $table) {
            // Asked on the agent form and flaggable in a revision request, so the
            // columns have to exist before a revision can point at them.
            $table->string('pickup_location')->nullable()->after('travel_date');
            $table->time('arrival_time')->nullable()->after('pickup_location');

            // Denormalised counter so list screens print "v3" without a subquery.
            // Authoritative copy is MAX(booking_versions.version).
            $table->unsignedSmallInteger('revision_no')->default(0)->after('provider_status');
            $table->timestamp('revision_requested_at')->nullable()->after('submitted_at');
            // Separate from submitted_at, which must keep the ORIGINAL submission time.
            $table->timestamp('resubmitted_at')->nullable()->after('revision_requested_at');

            // Agent list, one tab at a time: "my bookings with this status, newest first".
            $table->index(['agent_id', 'status', 'created_at']);
            // Same list with no tab selected — the composite above cannot sort that one.
            $table->index(['agent_id', 'created_at']);
            // Admin queue + tab counts: "everything needing revision, newest first".
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        // The agent_id composites are what MySQL leans on to satisfy the agent_id foreign
        // key, so it refuses to drop the last one. Put the plain FK index back first.
        if (DB::getDriverName() === 'mysql') {
            Schema::table('bookings', function (Blueprint $table) {
                $table->index('agent_id', 'bookings_agent_id_foreign');
            });
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['agent_id', 'created_at']);
            $table->dropIndex(['agent_id', 'status', 'created_at']);
            $table->dropColumn([
                'pickup_location', 'arrival_time', 'revision_no',
                'revision_requested_at', 'resubmitted_at',
            ]);
        });

        // Rows still parked on the new status would be truncated by the MODIFY below.
        DB::table('bookings')->where('status', 'needs_revision')->update(['status' => 'pending_verification']);

        $this->setStatusEnum([
            'draft', 'pending_payment', 'pending_verification', 'waiting_provider_confirmation',
            'confirmed', 'rejected', 'cancelled', 'completed', 'refunded',
        ]);
    }

    // `needs_revision` is APPENDED to the end of the list. MySQL stores enums by ordinal,
    // so slipping it in mid-list would silently re-label every existing row.
    private function setStatusEnum(array $values): void
    {
        // Raw MODIFY on MySQL: ->change() would regenerate the value list from the PHP
        // array and drop the attributes it wasn't told to restate (Laravel 12 has no dbal).
        if (DB::getDriverName() === 'mysql') {
            $list = "'" . implode("','", $values) . "'";
            DB::statement("ALTER TABLE bookings MODIFY status ENUM({$list}) NOT NULL DEFAULT 'pending_verification'");

            return;
        }

        // SQLite (the test connection) enforces enums with a CHECK constraint, so the
        // column has to be rebuilt by the grammar instead.
        Schema::table('bookings', function (Blueprint $table) use ($values) {
            $table->enum('status', $values)->default('pending_verification')->change();
        });
    }
};
