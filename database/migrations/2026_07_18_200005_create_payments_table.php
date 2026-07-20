<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('method', ['fpx', 'online_banking', 'slip_upload', 'cash', 'card', 'other'])->default('slip_upload');
            $table->enum('type', ['deposit', 'partial', 'balance', 'full'])->default('full');
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending')->index();
            $table->string('slip_path')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
