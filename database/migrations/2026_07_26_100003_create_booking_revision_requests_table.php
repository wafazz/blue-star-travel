<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per admin "needs revision" action: the free-text remark plus the exact
        // fields the agent must fix. Closed when the agent resubmits. A booking can go
        // round more than once, so this is a table and not a pair of columns.
        Schema::create('booking_revision_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remark')->nullable();
            // Flagged field keys, e.g. ['customer.phone','booking.travel_date']. Read as an
            // opaque set on one screen and never queried by field, so JSON beats a child table.
            $table->json('fields')->nullable();
            $table->enum('status', ['open', 'resolved', 'cancelled'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // "Is there an open request on this booking?" — read on every detail render.
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_revision_requests');
    }
};
