<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit\Jobs;

use Usamamuneerchaudhary\Notifier\Jobs\SendNotificationJob;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    public function test_it_handles_missing_notification()
    {
        $job = new SendNotificationJob(999);

        $job->handle();

        // Should not throw an exception
        $this->assertTrue(true);
    }

    public function test_it_skips_already_sent_notifications()
    {
        $notification = Notification::create([
            'notification_template_id' => 1,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test Subject',
            'content' => 'Test Content',
            'status' => 'sent',
        ]);

        $job = new SendNotificationJob($notification->id);

        $job->handle();

        // Status should remain 'sent'
        $this->assertEquals('sent', $notification->fresh()->status);
    }

    public function test_it_marks_notification_as_failed_when_driver_not_found()
    {
        $notification = Notification::create([
            'notification_template_id' => 1,
            'user_id' => 1,
            'channel' => 'unknown-channel',
            'subject' => 'Test Subject',
            'content' => 'Test Content',
            'status' => 'pending',
        ]);

        $job = new SendNotificationJob($notification->id);

        $job->handle();

        $this->assertEquals('failed', $notification->fresh()->status);
        $this->assertStringContainsString('No driver found', $notification->fresh()->error);
    }

    public function test_it_handles_job_failure()
    {
        $notification = Notification::create([
            'notification_template_id' => 1,
            'user_id' => 1,
            'channel' => 'email',
            'subject' => 'Test Subject',
            'content' => 'Test Content',
            'status' => 'pending',
        ]);

        $job = new SendNotificationJob($notification->id);

        $exception = new \Exception('Test exception');

        $job->failed($exception);

        $this->assertEquals('failed', $notification->fresh()->status);
        $this->assertEquals('Test exception', $notification->fresh()->error);
    }

    public function test_it_has_correct_job_properties()
    {
        $job = new SendNotificationJob(1);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(30, $job->timeout);
    }
}
