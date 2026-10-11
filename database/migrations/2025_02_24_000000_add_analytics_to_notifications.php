<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifier_notifications', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->unsignedInteger('opens_count')->default(0)->after('clicked_at');
            $table->unsignedInteger('clicks_count')->default(0)->after('opens_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifier_notifications', function (Blueprint $table) {
            $table->dropColumn(['opened_at', 'clicked_at', 'opens_count', 'clicks_count']);
        });
    }
};
