<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Post-confirmation change request. A confirmed booking has an issued invoice, a
        // voucher, booked seats and possibly calculated commission — it is never edited in
        // place, so the agent asks, admin approves, and approval writes a new version.
        Schema::create('booking_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['travel_date', 'pickup', 'other'])->default('travel_date');
            // Display copies frozen at request time, so admin reviews what the agent saw.
            $table->string('current_value')->nullable();
            $table->string('requested_value')->nullable();
            $table->date('requested_date')->nullable();
            $table->foreignId('requested_package_date_id')->nullable()->constrained('package_dates')->nullOnDelete();
            $table->string('requested_pickup_location')->nullable();
            $table->time('requested_arrival_time')->nullable();
            // Not nullable — an amendment with no justification is not reviewable.
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // "Any open amendment on this booking?" — read on both detail screens.
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_amendments');
    }
};
