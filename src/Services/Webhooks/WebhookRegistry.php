<?php

namespace Usamamuneerchaudhary\Notifier\Services\Webhooks;

class WebhookRegistry
{
    /**
     * @var array<string, class-string<WebhookHandler>>
     */
    protected array $handlers = [
        'mailgun' => MailgunWebhookHandler::class,
        'postmark' => PostmarkWebhookHandler::class,
        'ses' => SesWebhookHandler::class,
        'twilio' => TwilioWebhookHandler::class,
    ];

    /**
     * Register a custom provider, for example from a service provider.
     *
     * @param  class-string<WebhookHandler>  $handler
     */
    public function extend(string $provider, string $handler): void
    {
        $this->handlers[strtolower($provider)] = $handler;
    }

    public function handlerFor(string $provider): ?WebhookHandler
    {
        $class = $this->handlers[strtolower($provider)] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * @return array<int, string>
     */
    public function providers(): array
    {
        return array_keys($this->handlers);
    }
}
