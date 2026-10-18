<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountSessionService;
use App\Services\UserAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSessionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null, 'hashing.driver' => 'bcrypt', 'hashing.bcrypt.rounds' => 4,
            'session.driver' => 'database', 'session.connection' => 'sqlite', 'session.encrypt' => false,
            'session.lottery' => [0, 100], 'session.secure' => false,
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, name TEXT, first_name TEXT, middle_name TEXT, last_name TEXT, email TEXT UNIQUE, password TEXT, status TEXT, must_change_password INTEGER DEFAULT 0, remember_token TEXT, created_at DATETIME, updated_at DATETIME)');
        (require database_path('migrations/2026_10_14_000001_add_session_version_to_users_table.php'))->up();
        DB::statement('CREATE TABLE roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, role_name TEXT, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE user_roles (user_id INTEGER, role_id INTEGER, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE instructor_profiles (instructor_profile_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, department_id INTEGER, employee_number TEXT, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE student_profiles (student_profile_id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, user_id INTEGER, program_id INTEGER, student_number TEXT, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE password_reset_tokens (email TEXT PRIMARY KEY, token TEXT, created_at DATETIME)');
        DB::statement('CREATE TABLE sessions (id TEXT PRIMARY KEY, user_id INTEGER, ip_address TEXT, user_agent TEXT, payload TEXT, last_activity INTEGER)');
        Role::create(['role_name' => 'student']);
        Route::middleware(['web', 'auth', 'password_changed'])->get('/session-security-probe', fn () => response()->json(['ok' => true]));
    }

    public static function drivers(): array
    {
        return ['database sessions' => ['database'], 'encrypted cookie sessions' => ['cookie']];
    }

    #[DataProvider('drivers')]
    public function test_password_change_keeps_current_browser_and_rejects_other_sessions_and_old_remember_cookies(string $driver): void
    {
        config(['session.driver' => $driver]);
        $user = $this->user();
        $current = $other = $rememberOnly = [];
        $this->login($current, $user);
        $this->login($other, $user, true);
        $rememberOnly['cookies'] = array_filter($other['cookies'], fn ($name) => str_starts_with($name, 'remember_'), ARRAY_FILTER_USE_KEY);
        $oldSession = $current['id'];

        $this->requestFrom($current, 'PUT', '/profile/password', $this->passwordPayload(), true)->assertRedirect();
        $this->assertNotSame($oldSession, $current['id']);
        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->requestFrom($current, 'GET', '/session-security-probe', [], true)->assertOk();
        $this->requestFrom($other, 'GET', '/session-security-probe', [], true)->assertUnauthorized()->assertJsonPath('code', 'authentication_required');
        $this->requestFrom($rememberOnly, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
    }

    #[DataProvider('drivers')]
    public function test_password_reset_rejects_every_old_session_and_a_reused_reset_token(string $driver): void
    {
        config(['session.driver' => $driver]);
        $user = $this->user();
        $first = $second = $guest = [];
        $this->login($first, $user);
        $this->login($second, $user, true);
        $token = Password::broker()->createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'reset-password-123', 'password_confirmation' => 'reset-password-123'];
        $this->requestFrom($guest, 'POST', '/reset-password', $payload)->assertRedirect(route('login'));
        $this->requestFrom($first, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
        $this->requestFrom($second, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
        $this->requestFrom($guest, 'POST', '/reset-password', $payload, true)->assertUnprocessable();
        $this->assertSame(1, $user->fresh()->session_version);
        $this->login($guest, $user->fresh(), false, 'reset-password-123');
        $this->requestFrom($guest, 'GET', '/session-security-probe', [], true)->assertOk();
    }

    #[DataProvider('drivers')]
    public function test_first_login_setup_keeps_its_browser_and_revokes_other_initial_password_sessions(string $driver): void
    {
        config(['session.driver' => $driver]);
        $user = $this->user();
        $user->update(['must_change_password' => true]);
        $first = $second = [];
        $this->login($first, $user);
        $this->login($second, $user);
        $this->requestFrom($second, 'GET', '/portal-search', ['q' => 'a'], true)
            ->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->requestFrom($first, 'PUT', '/password/setup', $this->passwordPayload())
            ->assertRedirect(route('student.dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->requestFrom($first, 'GET', '/session-security-probe', [], true)->assertOk();
        $this->requestFrom($second, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
    }

    public function test_preexisting_session_without_security_markers_cannot_adopt_the_new_password(): void
    {
        $user = $this->user();
        $legacy = $current = [];
        $this->login($legacy, $user);
        $this->login($current, $user);
        $row = DB::table('sessions')->where('id', $legacy['id'])->first();
        $payload = unserialize(base64_decode($row->payload), ['allowed_classes' => false]);
        unset($payload[AccountSessionService::VERSION_KEY], $payload['password_hash_web']);
        DB::table('sessions')->where('id', $legacy['id'])->update(['payload' => base64_encode(serialize($payload))]);

        $this->requestFrom($current, 'PUT', '/profile/password', $this->passwordPayload())->assertRedirect();
        $this->requestFrom($legacy, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
    }

    public function test_inactive_accounts_are_rejected_on_common_and_role_routes_and_cannot_save_password_changes(): void
    {
        $user = $this->user();
        $password = $user->password;
        foreach (['/profile', '/profile/photo', '/profile/activity-log', '/portal-search?q=a', '/notifications', '/student/classes'] as $uri) {
            $user->update(['status' => 'active']);
            $browser = [];
            $this->login($browser, $user);
            $user->update(['status' => 'inactive']);
            $this->requestFrom($browser, 'GET', $uri, [], true)->assertForbidden()->assertJsonPath('code', 'account_inactive');
        }
        $user->update(['status' => 'active']);
        $browser = [];
        $this->login($browser, $user);
        $user->update(['status' => 'inactive']);
        $this->requestFrom($browser, 'PUT', '/profile/password', $this->passwordPayload(), true)->assertForbidden();
        $this->assertSame($password, $user->fresh()->password);
        $user->update(['status' => 'active']);
        $browser = [];
        $this->login($browser, $user);
        $user->update(['status' => 'inactive']);
        $this->requestFrom($browser, 'GET', '/profile')->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_deactivation_through_account_service_revokes_sessions_even_after_reactivation(): void
    {
        $user = $this->user();
        $browser = [];
        $this->login($browser, $user);
        app(UserAccountService::class)->updateAccount($user, [
            'first_name' => 'Student', 'last_name' => 'Account', 'email' => $user->email,
            'status' => 'inactive', 'base_role' => 'student', 'program_id' => 1, 'student_number' => 'STU-123',
        ]);
        $this->assertSame(1, $user->fresh()->session_version);
        $user->update(['status' => 'active']);
        $this->requestFrom($browser, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
    }

    public function test_a_new_remember_me_login_works_after_an_earlier_session_revocation(): void
    {
        $user = $this->user();
        app(AccountSessionService::class)->invalidateAllSessions($user);
        $browser = [];
        $this->login($browser, $user, true);
        $browser['cookies'] = array_filter($browser['cookies'], fn ($name) => str_starts_with($name, 'remember_'), ARRAY_FILTER_USE_KEY);
        $this->requestFrom($browser, 'GET', '/session-security-probe', [], true)->assertOk();
    }

    public function test_invalid_current_password_does_not_change_password_or_revoke_another_device(): void
    {
        $user = $this->user();
        $current = $other = [];
        $this->login($current, $user);
        $this->login($other, $user);
        $this->requestFrom($current, 'PUT', '/profile/password', [
            ...$this->passwordPayload(), 'current_password' => 'wrong-password',
        ], true)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame(0, $user->fresh()->session_version);
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
        $this->requestFrom($other, 'GET', '/session-security-probe', [], true)->assertOk();
    }

    public function test_expired_sessions_return_json_and_do_not_expose_protected_pages(): void
    {
        $user = $this->user();
        $browser = [];
        $this->login($browser, $user);
        DB::table('sessions')->where('id', $browser['id'])->update(['last_activity' => time() - 8000]);
        $this->requestFrom($browser, 'GET', '/session-security-probe', [], true)
            ->assertUnauthorized()->assertJsonPath('code', 'authentication_required');
    }

    public function test_changing_one_accounts_password_does_not_revoke_another_accounts_session(): void
    {
        $owner = $this->user();
        $otherUser = $this->user();
        $current = $other = [];
        $this->login($current, $owner);
        $this->login($other, $otherUser);
        $this->requestFrom($current, 'PUT', '/profile/password', $this->passwordPayload())->assertRedirect();
        $this->assertSame(0, $otherUser->fresh()->session_version);
        $this->requestFrom($other, 'GET', '/session-security-probe', [], true)->assertOk();
    }

    public function test_database_login_throttle_survives_fresh_cache_instances_and_expires_after_one_minute(): void
    {
        (require database_path('migrations/0001_01_01_000001_create_cache_table.php'))->up();
        config(['cache.default' => 'database', 'cache.stores.database.connection' => 'sqlite']);
        $this->freezeTime();
        $user = $this->user();
        $browser = [];
        Log::spy();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->resetLoginLimiterCacheInstance();
            $this->requestFrom($browser, 'POST', '/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['email' => $attempt === 4
                    ? 'Too many login attempts. Please try again in 60 seconds.'
                    : 'Invalid email or password.']);
        }
        $this->resetLoginLimiterCacheInstance();
        $this->requestFrom($browser, 'POST', '/login', ['email' => $user->email, 'password' => 'original-password'])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) =>
                $message === 'Failed login attempt.'
                && $context['attempts'] === $attempt
                && $context['stored_attempts'] === $attempt
                && $context['cache_store'] === 'database'
                && $context['cache_driver'] === 'database'
                && $context['retry_after_seconds'] === 60
                && strlen($context['throttle_bucket']) === 64
            )->once();
        }
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) =>
            $message === 'Login attempt temporarily blocked.' && $context['stored_attempts'] === 5
        )->once();
        $this->requestFrom($browser, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
        $this->travel(61)->seconds();
        $this->resetLoginLimiterCacheInstance();
        $this->login($browser, $user);
        $this->requestFrom($browser, 'GET', '/session-security-probe', [], true)->assertOk();
    }

    public function test_login_form_stays_disabled_on_refresh_until_the_server_lockout_expires(): void
    {
        (require database_path('migrations/0001_01_01_000001_create_cache_table.php'))->up();
        config(['cache.default' => 'database', 'cache.stores.database.connection' => 'sqlite']);
        $this->freezeTime();
        $user = $this->user();
        $browser = [];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->resetLoginLimiterCacheInstance();
            $this->requestFrom($browser, 'POST', '/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }
        $this->requestFrom($browser, 'GET', '/login')->assertOk()
            ->assertViewHas('loginRetryAfter', 60)
            ->assertSee('id="loginFields" disabled', false)
            ->assertSee('data-login-retry-after="60"', false)
            ->assertSee('Try again in 60s')
            ->assertDontSee('wrong-password');

        $this->travel(15)->seconds();
        $this->resetLoginLimiterCacheInstance();
        // Repeated GETs must not depend on one-request flash data or restart the timer.
        for ($refresh = 0; $refresh < 2; $refresh++) {
            $this->requestFrom($browser, 'GET', '/login')->assertOk()
                ->assertViewHas('loginRetryAfter', 45)
                ->assertSee('id="loginFields" disabled', false)
                ->assertSee('value="'.$user->email.'"', false);
        }
        // Direct POST bypassing disabled HTML must still be rejected, even with the correct password.
        $this->requestFrom($browser, 'POST', '/login', ['email' => $user->email, 'password' => 'original-password'])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 45 seconds.']);
        $this->requestFrom($browser, 'GET', '/session-security-probe', [], true)->assertUnauthorized();
        $this->requestFrom($browser, 'GET', '/login')->assertViewHas('loginRetryAfter', 45);

        $this->travel(46)->seconds();
        $this->resetLoginLimiterCacheInstance();
        $this->requestFrom($browser, 'GET', '/login')->assertOk()
            ->assertViewHas('loginRetryAfter', 0)
            ->assertDontSee('id="loginFields" disabled', false)
            ->assertSessionMissing('login_lockout_email');
        $this->login($browser, $user);
    }

    public function test_login_form_remains_enabled_before_the_failed_attempt_limit(): void
    {
        $user = $this->user();
        $browser = [];
        $this->requestFrom($browser, 'GET', '/login')->assertOk()
            ->assertViewHas('loginRetryAfter', 0)
            ->assertDontSee('id="loginFields" disabled', false);
        $this->requestFrom($browser, 'POST', '/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        $this->requestFrom($browser, 'GET', '/login')->assertOk()
            ->assertViewHas('loginRetryAfter', 0)
            ->assertDontSee('id="loginFields" disabled', false);
        $this->login($browser, $user);
    }

    public function test_successful_login_clears_database_failed_attempt_counter(): void
    {
        (require database_path('migrations/0001_01_01_000001_create_cache_table.php'))->up();
        config(['cache.default' => 'database', 'cache.stores.database.connection' => 'sqlite']);
        $user = $this->user();
        $browser = [];
        for ($round = 0; $round < 2; $round++) {
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $this->resetLoginLimiterCacheInstance();
                $this->requestFrom($browser, 'POST', '/login', ['email' => $user->email, 'password' => 'wrong-password'])
                    ->assertSessionHasErrors(['email' => 'Invalid email or password.']);
            }
            $this->resetLoginLimiterCacheInstance();
            $this->login($browser, $user);
            $this->requestFrom($browser, 'POST', '/logout')->assertRedirect(route('login'));
        }
    }

    private function resetLoginLimiterCacheInstance(): void
    {
        // Fresh repositories model separate PHP requests; the attempt counter must survive in SQL.
        $this->app['cache']->forgetDriver('database');
        $this->app->forgetInstance(\Illuminate\Cache\RateLimiter::class);
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(\Illuminate\Cache\RateLimiter::class);
    }

    private function user(): User
    {
        $user = User::create([
            'name' => 'Student Account', 'first_name' => 'Student', 'last_name' => 'Account',
            'email' => 'user'.(User::count() + 1).'@example.test', 'password' => 'original-password', 'status' => 'active',
        ]);
        $user->roles()->attach(Role::first());
        return $user;
    }

    private function login(array &$browser, User $user, bool $remember = false, string $password = 'original-password'): void
    {
        $this->requestFrom($browser, 'POST', '/login', ['email' => $user->email, 'password' => $password, 'remember' => $remember])
            ->assertRedirect(route($user->must_change_password ? 'password.setup' : 'student.dashboard'));
    }

    private function passwordPayload(): array
    {
        return ['current_password' => 'original-password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
    }

    private function requestFrom(array &$browser, string $method, string $uri, array $data = [], bool $json = false): TestResponse
    {
        // Each cookie jar is an independent browser, using the real login and web middleware.
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('redirect');
        $this->app['cookie']->flushQueuedCookies();
        $headers = $json ? ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'] : [];
        $response = $this->call($method, $uri, $data, $browser['cookies'] ?? [], [], $this->transformHeadersToServerVars($headers));
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() && $cookie->getExpiresTime() <= time()) {
                unset($browser['cookies'][$cookie->getName()]);
            } else {
                $browser['cookies'][$cookie->getName()] = $cookie->getValue();
            }
        }
        $browser['id'] = $this->app['session']->getId();
        return $response;
    }
}
