<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifier_notifications', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('error');
            $table->string('event_key')->nullable()->index()->after('channel');
        });

        DB::table('notifier_notifications')
            ->whereNull('tracking_token')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $data = json_decode((string) $row->data, true);
                    $token = is_array($data) ? ($data['tracking_token'] ?? null) : null;

                    if (is_string($token) && $token !== '') {
                        DB::table('notifier_notifications')
                            ->where('id', $row->id)
                            ->update(['tracking_token' => $token]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifier_notifications', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropIndex(['event_key']);
            $table->dropColumn(['tracking_token', 'event_key']);
        });
    }
};
