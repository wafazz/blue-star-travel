<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->date('depart_date');
            $table->date('return_date')->nullable();
            $table->unsignedInteger('seats_total')->default(0);
            $table->unsignedInteger('seats_booked')->default(0);
            $table->enum('status', ['open', 'closed', 'full'])->default('open')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_dates');
    }
};
