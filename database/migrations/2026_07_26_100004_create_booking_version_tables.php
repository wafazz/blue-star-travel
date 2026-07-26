<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable snapshot of the whole booking at each submit. `payload` is the full
        // state and `changes` is the diff frozen against the previous version, so the
        // history panel never recomputes it under a schema that has since moved.
        Schema::create('booking_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('revision_request_id')->nullable()->constrained('booking_revision_requests')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('version');
            $table->enum('reason', ['initial', 'revision', 'amendment', 'admin_edit'])->default('revision');
            $table->json('payload');
            $table->json('changes')->nullable();   // null on the initial snapshot — nothing to compare against
            $table->timestamps();

            // Serves the history list and "latest version" lookup, and stops two
            // concurrent resubmits both claiming the same version number.
            $table->unique(['booking_id', 'version']);
        });

        // Work-in-progress edit of an ALREADY-SUBMITTED booking. Lives outside the live
        // record so a half-finished edit can never reach admin, the provider or an invoice.
        Schema::create('booking_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            // Scratch work owned by one person — the deliberate exception to nullOnDelete.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('revision_request_id')->nullable()->constrained('booking_revision_requests')->nullOnDelete();
            // The version this edit started from, so a stale draft can be spotted at review.
            $table->unsignedSmallInteger('base_version')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['booking_id', 'user_id']);
        });

        Schema::table('booking_timeline', function (Blueprint $table) {
            // Lets an Activity History line ("Revision 3 submitted") deep-link to its diff.
            $table->foreignId('booking_version_id')->nullable()->after('user_id')->constrained('booking_versions')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            // A replaced receipt must never overwrite the old file — an older version's
            // snapshot still points at it. The old row is chained, not mutated.
            $table->foreignId('superseded_by')->nullable()->after('status')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by');
        });
        Schema::table('booking_timeline', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_version_id');
        });
        Schema::dropIfExists('booking_drafts');
        Schema::dropIfExists('booking_versions');
    }
};
