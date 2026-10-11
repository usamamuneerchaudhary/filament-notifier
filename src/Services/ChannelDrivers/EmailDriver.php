<?php

namespace Usamamuneerchaudhary\Notifier\Services\ChannelDrivers;

use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Usamamuneerchaudhary\Notifier\Models\Notification;
use Usamamuneerchaudhary\Notifier\Models\NotificationChannel;
use Usamamuneerchaudhary\Notifier\Services\AnalyticsService;
use Usamamuneerchaudhary\Notifier\Services\UnsubscribeService;

class EmailDriver implements ChannelDriverInterface, ProvidesDeliveryReference
{
    protected ?string $messageId = null;

    public function send(Notification $notification): bool
    {
        try {
            $channel = NotificationChannel::where('type', 'email')->first();
            $user = $notification->user;

            if (! $user || ! $user->email) {
                return false;
            }

            $settings = $channel->settings ?? [];
            $fromAddress = $settings['from_address'] ?? config('mail.from.address', 'noreply@example.com');
            $fromName = $settings['from_name'] ?? config('mail.from.name', 'Notification');

            // Inject tracking pixel if analytics is enabled
            $content = $notification->content;
            $trackingToken = $notification->data['tracking_token'] ?? null;

            if ($trackingToken) {
                $analyticsService = app(AnalyticsService::class);
                if ($analyticsService->isOpenTrackingEnabled()) {
                    $content .= $analyticsService->generateTrackingPixel($trackingToken);
                }
            }

            $unsubscribeUrl = app(UnsubscribeService::class)->urlFor($user, $notification->event_key);

            $isHtml = strip_tags($content) !== $content;

            $configureMessage = function (Message $message) use ($notification, $user, $fromAddress, $fromName, $unsubscribeUrl) {
                $message->to($user->email)
                    ->subject($notification->subject)
                    ->from($fromAddress, $fromName);

                if ($unsubscribeUrl) {
                    $headers = $message->getHeaders();
                    $headers->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
                    $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            };

            $sentMessage = $isHtml
                ? Mail::html($content, $configureMessage)
                : Mail::raw($content, $configureMessage);

            $this->messageId = $sentMessage instanceof SentMessage
                ? $this->normalizeMessageId($sentMessage->getMessageId())
                : null;

            return true;
        } catch (\Exception $e) {
            Log::error('Email notification failed: '.$e->getMessage());

            return false;
        }
    }

    public function deliveryReference(): ?string
    {
        return $this->messageId;
    }

    public function validateSettings(array $settings): bool
    {
        return ! empty($settings['from_address'] ?? null);
    }

    protected function normalizeMessageId(?string $messageId): ?string
    {
        $messageId = trim((string) $messageId, " \t\n\r\0\x0B<>");

        return $messageId !== '' ? $messageId : null;
    }
}
