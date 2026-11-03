<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_postponements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            // The passengers put off. Counts by type, held against the booking's own totals.
            $table->unsignedSmallInteger('adults')->default(0);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('seniors')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            // pending = no new date yet; redated = given their own departure.
            $table->string('status', 20)->default('pending');
            // Their own departure once redated. Null while pending.
            $table->foreignId('package_date_id')->nullable()->constrained('package_dates')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('redated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('redated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_postponements');
    }
};
