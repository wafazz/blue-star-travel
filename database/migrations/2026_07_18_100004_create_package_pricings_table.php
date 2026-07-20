<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('tier_name')->default('Standard');
            $table->decimal('adult_price', 10, 2)->default(0);
            $table->decimal('child_price', 10, 2)->default(0);
            $table->decimal('infant_price', 10, 2)->default(0);
            $table->decimal('promo_price', 10, 2)->nullable();
            $table->decimal('early_bird_price', 10, 2)->nullable();
            $table->date('early_bird_until')->nullable();
            $table->unsignedSmallInteger('group_min')->nullable();
            $table->decimal('group_discount_percent', 5, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_pricings');
    }
};
