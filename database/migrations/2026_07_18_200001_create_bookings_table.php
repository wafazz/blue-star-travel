<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_no')->unique();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            $table->foreignId('package_date_id')->nullable()->constrained('package_dates')->nullOnDelete();
            $table->foreignId('package_pricing_id')->nullable()->constrained('package_pricings')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['manual', 'online', 'group', 'family', 'corporate', 'walk_in'])->default('online');
            $table->enum('status', [
                'draft', 'pending_payment', 'pending_verification', 'waiting_provider_confirmation',
                'confirmed', 'rejected', 'cancelled', 'completed', 'refunded',
            ])->default('pending_verification')->index();
            $table->enum('provider_status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->date('travel_date')->nullable();
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->unsignedSmallInteger('total_pax')->default(1);

            $table->decimal('adult_price', 10, 2)->default(0);
            $table->decimal('child_price', 10, 2)->default(0);
            $table->decimal('infant_price', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('provider_note')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('sent_to_provider_at')->nullable();
            $table->timestamp('provider_responded_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
