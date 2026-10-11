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
        Schema::create('notifier_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_template_id')->constrained('notifier_templates');
            $table->foreignId('user_id')->constrained();
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('content');
            $table->json('data')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifier_notifications');
    }
};
