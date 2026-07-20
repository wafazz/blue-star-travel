<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Running points balance on the agent.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('reward_points')->default(0)->after('agent_tier');
        });

        // Mission templates (admin-defined daily missions).
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('code')->unique();           // e.g. follow_up_10, complete_booking
            $table->unsignedInteger('points')->default(0);
            $table->enum('frequency', ['daily', 'weekly', 'once'])->default('daily');
            $table->boolean('auto')->default(false);     // auto-completed by a system event vs manual tick
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('mission_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_date');                 // the day/week this completion belongs to
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['mission_id', 'user_id', 'period_date']);
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('checkin_date');
            $table->unsignedInteger('day_number')->default(1); // streak day at time of check-in
            $table->unsignedInteger('points')->default(0);
            $table->string('reward')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checkin_date']);
        });

        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('current')->default(0);
            $table->unsignedInteger('longest')->default(0);
            $table->date('last_active_date')->nullable();
            $table->timestamps();
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem'])->index();
            $table->integer('points');                   // + earn / - redeem stored as magnitude with type
            $table->integer('balance_after');
            $table->string('source')->nullable();        // checkin / mission / booking / redemption / referral
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('description')->nullable();
            $table->enum('criteria_type', [
                'bookings_count', 'sales_total', 'customers_count',
                'rank_top', 'streak_days', 'followups_count', 'referrals_count', 'manual',
            ])->default('manual');
            $table->unsignedInteger('criteria_value')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('agent_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->unique(['achievement_id', 'user_id']);
        });

        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('redemption_no')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');                      // cash / travel_voucher / shopping / merchandise / commission / free_trip / hotel
            $table->unsignedInteger('points_cost');
            $table->decimal('cash_value', 10, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'fulfilled', 'rejected'])->default('pending')->index();
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
        Schema::dropIfExists('agent_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('streaks');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('mission_completions');
        Schema::dropIfExists('missions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reward_points');
        });
    }
};
