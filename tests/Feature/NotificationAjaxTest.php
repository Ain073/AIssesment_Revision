<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'hashing.bcrypt.rounds' => 4,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->uuid('public_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        $this->createAuditTables();
    }

    public function test_poll_returns_only_the_latest_eight_owned_notifications_and_total_unread_count(): void
    {
        $user = $this->user();
        $other = $this->user();
        for ($i = 1; $i <= 11; $i++) $this->notification($user, "Owned {$i}");
        $this->notification($other, 'Private other user');
        $this->notification($user, '<script>alert(1)</script>');

        $response = $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()->assertJsonPath('unread_count', 12);
        $html = $response->json('html');
        $this->assertSame(9, substr_count($html, 'data-notification-form')); // Eight items and Mark all read.
        $this->assertStringContainsString('Owned 11', $html);
        $this->assertStringNotContainsString('Owned 4', $html);
        $this->assertStringNotContainsString('Private other user', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame(13, AppNotification::whereNull('read_at')->count());
    }

    public function test_read_returns_navigation_and_updated_count_and_is_idempotent(): void
    {
        $user = $this->user();
        $notification = $this->notification($user);
        $this->notification($user, 'Second');
        $this->actingAs($user)->postJson(route('notifications.read', $notification))
            ->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('url', url('/profile'));
        $readAt = $notification->fresh()->read_at;
        $this->postJson(route('notifications.read', $notification))->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertTrue($notification->fresh()->read_at->equalTo($readAt));
    }

    public function test_read_all_marks_only_the_current_users_notifications(): void
    {
        $user = $this->user();
        $owned = $this->notification($user);
        $foreign = $this->notification($this->user());
        $this->actingAs($user)->postJson(route('notifications.read-all'))
            ->assertOk()->assertJsonPath('unread_count', 0)
            ->assertJsonPath('message', 'Notifications marked as read.');
        $this->assertNotNull($owned->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_users_cannot_read_other_users_notifications(): void
    {
        $foreign = $this->notification($this->user());
        $this->actingAs($this->user())->postJson(route('notifications.read', $foreign))->assertForbidden();
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_guest_and_inactive_accounts_cannot_access_notification_ajax_endpoints(): void
    {
        $this->getJson(route('notifications.index'))->assertUnauthorized();
        $inactive = $this->user();
        $inactive->update(['status' => 'inactive']);
        $notification = $this->notification($inactive);
        $this->actingAs($inactive)->getJson(route('notifications.index'))->assertForbidden();
        $this->actingAs($inactive)->postJson(route('notifications.read', $notification))->assertForbidden();
        $this->actingAs($inactive)->postJson(route('notifications.read-all'))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_normal_notification_forms_keep_their_redirect_behavior(): void
    {
        $user = $this->user();
        $notification = $this->notification($user);
        $this->actingAs($user)->post(route('notifications.read', $notification))->assertRedirect(url('/profile'));
        $this->from(url('/profile'))->post(route('notifications.read-all'))->assertRedirect(url('/profile'));
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Account', 'email' => 'user'.(User::count() + 1).'@example.test',
            'password' => 'test-password', 'status' => 'active',
        ]);
    }

    private function notification(User $user, string $title = 'Update'): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user->id, 'title' => $title, 'message' => 'Your records were updated.',
            'type' => 'info', 'link' => url('/profile'),
        ]);
    }
}
