<?php

namespace Usamamuneerchaudhary\Notifier\Tests\Feature;

use Usamamuneerchaudhary\Notifier\Models\NotificationPreference;
use Usamamuneerchaudhary\Notifier\Models\NotificationSetting;
use Usamamuneerchaudhary\Notifier\Tests\UserTestCase;

class PreferenceApiTest extends UserTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeChannel('email');
        $this->makeEvent('user.registered', ['channels' => ['email']]);
    }

    public function test_it_rejects_unauthenticated_preference_requests(): void
    {
        $response = $this->getJson('/api/notifier/preferences');

        $this->assertContains(
            $response->status(),
            [401, 302, 403],
            'Unauthenticated preference requests must be rejected.'
        );
    }

    public function test_it_returns_the_authenticated_users_preferences(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->getJson('/api/notifier/preferences')
            ->assertOk()
            ->assertJsonPath('data.0.event_key', 'user.registered')
            ->assertJsonPath('data.0.channels.email', true);
    }

    public function test_it_updates_preferences_for_an_event(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->putJson('/api/notifier/preferences/user.registered', [
                'channels' => ['email' => false],
            ])
            ->assertOk()
            ->assertJsonPath('data.channels.email', false);

        $this->assertDatabaseHas('notifier_preferences', [
            'user_id' => $user->id,
        ]);

        $preference = NotificationPreference::where('user_id', $user->id)->first();
        $this->assertFalse($preference->channels['email']);
    }

    public function test_it_returns_403_when_override_is_disabled(): void
    {
        NotificationSetting::set('preferences', [
            'enabled' => true,
            'allow_override' => false,
        ], 'preferences');

        $this->actingAs($this->makeUser())
            ->putJson('/api/notifier/preferences/user.registered', [
                'channels' => ['email' => false],
            ])
            ->assertForbidden();
    }

    public function test_it_rejects_an_unknown_channel(): void
    {
        $this->actingAs($this->makeUser())
            ->putJson('/api/notifier/preferences/user.registered', [
                'channels' => ['carrier_pigeon' => true],
            ])
            ->assertStatus(422);
    }
}
