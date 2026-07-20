<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_pax', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['adult', 'child', 'infant'])->default('adult');
            $table->string('ic_passport_no')->nullable();
            $table->date('dob')->nullable();
            $table->string('nationality')->nullable();
            $table->boolean('is_lead')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_pax');
    }
};
