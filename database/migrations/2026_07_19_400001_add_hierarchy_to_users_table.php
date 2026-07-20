<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referrer_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->string('agent_code')->nullable()->unique()->after('referrer_id');
            $table->enum('agent_tier', ['silver', 'gold', 'platinum'])->default('silver')->after('agent_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_id');
            $table->dropColumn(['agent_code', 'agent_tier']);
        });
    }
};
