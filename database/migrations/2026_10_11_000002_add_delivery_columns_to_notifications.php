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
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_retry_at')->nullable()->after('attempts');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('bounced_at')->nullable()->after('delivered_at');
            $table->timestamp('complained_at')->nullable()->after('bounced_at');
            $table->string('provider_message_id')->nullable()->index()->after('error');
            $table->unsignedBigInteger('fallback_for_notification_id')->nullable()->index()->after('provider_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifier_notifications', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
            $table->dropIndex(['fallback_for_notification_id']);
            $table->dropColumn([
                'attempts',
                'next_retry_at',
                'delivered_at',
                'bounced_at',
                'complained_at',
                'provider_message_id',
                'fallback_for_notification_id',
            ]);
        });
    }
};
