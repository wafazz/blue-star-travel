<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('earner_id')->nullable()->constrained('users')->nullOnDelete();   // who earns (upline agent or null=HQ)
            $table->foreignId('source_agent_id')->nullable()->constrained('users')->nullOnDelete(); // the selling agent
            $table->unsignedInteger('level');
            $table->boolean('is_orphan')->default(false); // no upline at this level → reserved HQ
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('percent', 5, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'paid', 'reversed'])->default('pending')->index();
            $table->string('period', 7)->index(); // YYYY-MM
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['earner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
