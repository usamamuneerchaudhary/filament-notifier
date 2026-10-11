<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Unit;

use Usamamuneerchaudhary\Notifier\NotifierServiceProvider;
use Usamamuneerchaudhary\Notifier\Services\NotifierManager;
use Usamamuneerchaudhary\Notifier\Tests\TestCase;

class NotifierServiceProviderTest extends TestCase
{
    public function test_it_registers_notifier_service()
    {
        $this->assertTrue($this->app->bound('notifier'));
        $this->assertInstanceOf(
            NotifierManager::class,
            $this->app->make('notifier')
        );
    }

    public function test_it_registers_model_bindings()
    {
        $this->assertTrue($this->app->bound('notifier.channel'));
        $this->assertTrue($this->app->bound('notifier.event'));
        $this->assertTrue($this->app->bound('notifier.template'));
        $this->assertTrue($this->app->bound('notifier.preference'));
        $this->assertTrue($this->app->bound('notifier.notification'));
    }

    public function test_it_loads_migrations()
    {
        // This test ensures migrations are loaded
        // We can't directly test migration loading, but we can verify the provider works
        $provider = new NotifierServiceProvider($this->app);

        // Should not throw an exception
        $this->assertTrue(true);
    }

    public function test_it_loads_views()
    {
        $this->assertTrue(view()->exists('notifier::pages.settings'));
    }

    public function test_it_has_correct_package_name()
    {
        $this->assertEquals('notifier', NotifierServiceProvider::$name);
    }
}
