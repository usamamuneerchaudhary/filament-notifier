<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit\Services;

use Usamamuneerchaudhary\Notifier\Models\NotificationTemplate;
use Usamamuneerchaudhary\Notifier\Services\TemplateRenderingService;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class TemplateRenderingServiceTest extends TestCase
{
    public function test_it_escapes_html_in_email_templates(): void
    {
        $template = $this->template('<p>Hello {{name}}</p>');

        $rendered = app(TemplateRenderingService::class)->render($template, [
            'name' => '<script>alert(1)</script>',
        ], 'email');

        $this->assertStringContainsString('Hello &lt;script&gt;alert(1)&lt;/script&gt;', $rendered['content']);
        $this->assertStringNotContainsString('<script>', $rendered['content']);
    }

    public function test_it_inserts_raw_html_with_triple_braces(): void
    {
        $template = $this->template('<p>Hello {{{name}}}</p>');

        $rendered = app(TemplateRenderingService::class)->render($template, [
            'name' => '<strong>Jane</strong>',
        ], 'email');

        $this->assertStringContainsString('<strong>Jane</strong>', $rendered['content']);
    }

    public function test_it_does_not_escape_plain_text_email(): void
    {
        $template = $this->template('Hello {{name}} & friends');

        $rendered = app(TemplateRenderingService::class)->render($template, [
            'name' => "O'Brien",
        ], 'email');

        $this->assertSame("Hello O'Brien & friends", $rendered['content']);
    }

    public function test_it_does_not_escape_sms_content(): void
    {
        $template = $this->template('<p>Hello {{name}}</p>');

        $rendered = app(TemplateRenderingService::class)->render($template, [
            'name' => "O'Brien",
        ], 'sms');

        $this->assertStringContainsString("O'Brien", $rendered['content']);
    }

    private function template(string $content): NotificationTemplate
    {
        return NotificationTemplate::create([
            'name' => 'render-test-'.uniqid(),
            'event_key' => 'test.event',
            'subject' => 'Hi {{name}}',
            'content' => $content,
        ]);
    }
}
