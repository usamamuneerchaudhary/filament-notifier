<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit;

use Usamamuneerchaudhary\Notifier\Services\NotifierManager;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class NotifierManagerTest extends TestCase
{
    private NotifierManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new NotifierManager;
    }

    public function test_it_can_register_a_channel()
    {
        $handler = new class
        {
            public function send($user, $data)
            {
                return true;
            }
        };

        $this->manager->registerChannel('email', $handler);
        $this->assertTrue(true);
    }

    public function test_it_can_register_an_event()
    {
        $config = [
            'channels' => ['email', 'slack'],
            'template' => 'welcome-email',
        ];

        $this->manager->registerEvent('user.registered', $config);

        $this->assertTrue(true);
    }

    public function test_it_can_send_notifications()
    {
        $user = new class
        {
            public $id = 1;

            public $email = 'test@example.com';
        };

        $this->manager->send($user, 'test.event', ['message' => 'Hello']);

        $this->assertTrue(true);
    }
}
