<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_commission_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->boolean('is_hq')->default(false); // true → company/HQ override line (not an upline payout)
            $table->enum('rate_type', ['percent', 'fixed'])->default('percent');
            // Per pax-type value: percent → % of that pax's fare; fixed → flat RM per pax.
            $table->decimal('adult_value', 10, 2)->default(0);
            $table->decimal('child_value', 10, 2)->default(0);
            $table->decimal('senior_value', 10, 2)->default(0);
            $table->decimal('infant_value', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['package_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_commission_levels');
    }
};
