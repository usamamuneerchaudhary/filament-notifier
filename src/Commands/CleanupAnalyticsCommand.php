<?php

namespace Usamamuneerchaudhary\Notifier\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;

class CleanupAnalyticsCommand extends Command
{
    protected $signature = 'notifier:cleanup-analytics {--dry-run : Show what would be cleaned without actually deleting}';

    protected $description = 'Clean up old analytics data based on retention settings';

    public function handle(): int
    {
        $analytics = NotificationSetting::getAnalytics();

        if (! ($analytics['enabled'] ?? config('notifier.settings.analytics.enabled', true))) {
            $this->info('Analytics is disabled. Skipping cleanup.');

            return Command::SUCCESS;
        }

        $retentionDays = $analytics['retention_days'] ?? config('notifier.settings.analytics.retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        $this->info("Cleaning up analytics data older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d H:i:s')})...");

        // Find notifications with analytics data older than retention period
        // Must be created before cutoff AND have analytics data
        $query = Notification::where('created_at', '<', $cutoffDate)
            ->where(function ($q) use ($cutoffDate) {
                $q->where(function ($subQ) use ($cutoffDate) {
                    $subQ->whereNotNull('opened_at')
                        ->where('opened_at', '<', $cutoffDate);
                })
                    ->orWhere(function ($subQ) use ($cutoffDate) {
                        $subQ->whereNotNull('clicked_at')
                            ->where('clicked_at', '<', $cutoffDate);
                    })
                    ->orWhere(function ($subQ) {
                        $subQ->where('opens_count', '>', 0)
                            ->orWhere('clicks_count', '>', 0);
                    });
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No old analytics data found to clean up.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would clean up {$count} notification(s) with old analytics data.");
            $this->table(
                ['ID', 'User ID', 'Channel', 'Opened At', 'Clicked At', 'Opens Count', 'Clicks Count', 'Created At'],
                $query->limit(10)->get()->map(function ($notification) {
                    return [
                        $notification->id,
                        $notification->user_id,
                        $notification->channel,
                        $notification->opened_at?->format('Y-m-d H:i:s') ?? 'N/A',
                        $notification->clicked_at?->format('Y-m-d H:i:s') ?? 'N/A',
                        $notification->opens_count,
                        $notification->clicks_count,
                        $notification->created_at->format('Y-m-d H:i:s'),
                    ];
                })->toArray()
            );

            return Command::SUCCESS;
        }

        $updated = 0;
        $notifications = $query->get();

        foreach ($notifications as $notification) {
            DB::statement('UPDATE notifier_notifications SET opened_at = NULL, clicked_at = NULL, opens_count = 0, clicks_count = 0 WHERE id = ?', [$notification->id]);
            $updated++;
        }

        $this->info("Successfully cleaned up analytics data for {$updated} notification(s).");

        return Command::SUCCESS;
    }
}
