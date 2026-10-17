<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountSessionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null, 'hashing.bcrypt.rounds' => 4,
            'services.cloudinary.driver' => 'local',
        ]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, name TEXT, first_name TEXT, last_name TEXT, email TEXT UNIQUE, password TEXT, status TEXT, must_change_password INTEGER DEFAULT 0, session_version INTEGER DEFAULT 0, remember_token TEXT, profile_photo_path TEXT, profile_photo_mime TEXT, profile_photo_data TEXT, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE roles (role_id INTEGER PRIMARY KEY AUTOINCREMENT, role_name TEXT, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE user_roles (user_id INTEGER, role_id INTEGER, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE instructor_profiles (instructor_profile_id INTEGER PRIMARY KEY, user_id INTEGER, department_id INTEGER)');
        DB::statement('CREATE TABLE student_profiles (student_profile_id INTEGER PRIMARY KEY, user_id INTEGER, program_id INTEGER)');
        DB::statement('CREATE TABLE app_notifications (notification_id INTEGER PRIMARY KEY, public_id TEXT, user_id INTEGER, title TEXT, message TEXT, type TEXT, link TEXT, read_at DATETIME, created_at DATETIME, updated_at DATETIME)');
        Storage::fake('public');
        Storage::fake('s3', ['url' => 'https://assets.example.test']);
        Http::preventStrayRequests();
    }

    public static function drivers(): array
    {
        return array_combine(['database', 'local', 's3', 'r2', 'cloudinary'], array_map(fn ($driver) => [$driver], ['database', 'local', 's3', 'r2', 'cloudinary']));
    }

    public static function accountRoles(): array
    {
        return array_combine(['super_admin', 'admin_dean', 'department_chair', 'instructor', 'student'],
            array_map(fn ($role) => [$role], ['super_admin', 'admin_dean', 'department_chair', 'instructor', 'student']));
    }

    #[DataProvider('accountRoles')]
    public function test_every_account_role_can_update_only_its_own_photo(string $role): void
    {
        config(['services.cloudinary.driver' => 'database']);
        $user = $this->account($role);
        $password = $user->password;
        $this->signIn($user)->post(route('profile.photo.update'), [
            '_method' => 'PUT', 'profile_photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertOk();
        $user->refresh();
        $this->assertSame('database', $user->profile_photo_path);
        $this->assertSame($password, $user->password);
        $this->assertSame(0, $user->session_version);
    }

    #[DataProvider('drivers')]
    public function test_ajax_upload_uses_existing_storage_and_returns_only_the_saved_photo_url(string $driver): void
    {
        config(['services.cloudinary.driver' => $driver]);
        if ($driver === 'cloudinary') {
            config([
                'services.cloudinary.cloud_name' => 'test-cloud', 'services.cloudinary.api_key' => 'test-key',
                'services.cloudinary.api_secret' => 'test-secret',
            ]);
            Http::fake(['api.cloudinary.com/*' => Http::response(['secure_url' => 'https://res.cloudinary.com/test/image/upload/avatar.png'])]);
        }
        $user = $this->account();
        Storage::disk('public')->put('profile-photos/old.png', 'old image');
        $photo = $this->image();
        $response = $this->signIn($user)->post(route('profile.photo.update'), [
            '_method' => 'PUT', 'profile_photo' => $photo,
        ], ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $user->refresh();
        $this->assertSame(['message', 'photo_url'], array_keys($response->json()));
        $response->assertJsonPath('message', 'Profile picture updated.')->assertJsonPath('photo_url', $user->profilePhotoUrl());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Storage::disk('public')->assertMissing('profile-photos/old.png');
        if ($driver === 'database') {
            $this->assertSame('database', $user->profile_photo_path);
            $this->assertSame('image/png', $user->profile_photo_mime);
            $this->get(route('profile.photo.show'))->assertOk()->assertHeader('Content-Type', 'image/png')
                ->assertContent(base64_decode($user->profile_photo_data));
        } elseif (in_array($driver, ['s3', 'r2'], true)) {
            Storage::disk('s3')->assertExists(substr($user->profile_photo_path, 3));
        } elseif ($driver === 'local') {
            Storage::disk('public')->assertExists($user->profile_photo_path);
        } else {
            Http::assertSentCount(1);
        }
    }

    public function test_invalid_uploads_preserve_the_existing_photo(): void
    {
        $user = $this->account();
        $this->signIn($user);
        Storage::disk('public')->put('profile-photos/old.png', 'old image');
        $disguised = UploadedFile::fake()->createWithContent('pretend.png', 'This is not an image.');
        foreach ([
            null,
            // Use the real UploadedFile MIME inspection, not the fake's filename-based MIME.
            new UploadedFile($disguised->getRealPath(), 'pretend.png', 'image/png', null, true),
            UploadedFile::fake()->createWithContent('avatar.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')),
            $this->image()->size(2049),
            UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ] as $photo) {
            $this->post(route('profile.photo.update'), ['_method' => 'PUT', 'profile_photo' => $photo], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('profile_photo');
            $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo_path);
            Storage::disk('public')->assertExists('profile-photos/old.png');
        }
    }

    public function test_storage_failure_does_not_remove_the_existing_photo(): void
    {
        $user = $this->account();
        $disk = Storage::disk('public');
        $disk->put('profile-photos/old.png', 'old image');
        $failedDisk = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $failedDisk->shouldReceive('putFileAs')->once()->andReturn(false);
        $failedDisk->shouldNotReceive('delete');
        Storage::shouldReceive('disk')->with('public')->andReturn($failedDisk);
        $this->signIn($user)->post(route('profile.photo.update'), [
            '_method' => 'PUT', 'profile_photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('profile_photo');
        $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo_path);
        $this->assertTrue($disk->exists('profile-photos/old.png'));
    }

    public function test_failed_database_save_keeps_the_existing_file_and_photo_reference(): void
    {
        $user = $this->account();
        Storage::disk('public')->put('profile-photos/old.png', 'old image');
        DB::unprepared("CREATE TRIGGER fail_photo_save BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'Simulated photo save failure'); END");
        $this->signIn($user)->post(route('profile.photo.update'), [
            '_method' => 'PUT', 'profile_photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertStatus(500);
        Storage::disk('public')->assertExists('profile-photos/old.png');
        $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo_path);
    }

    public function test_failure_to_clean_up_an_old_file_does_not_report_a_saved_upload_as_failed(): void
    {
        $user = $this->account();
        $disk = Storage::disk('public');
        $disk->put('profile-photos/old.png', 'old image');
        $cleanupFailure = Mockery::mock($disk);
        $cleanupFailure->shouldReceive('delete')->with('profile-photos/old.png')->once()
            ->andThrow(new \RuntimeException('Simulated cleanup failure'));
        Storage::shouldReceive('disk')->with('public')->andReturn($cleanupFailure);
        $this->signIn($user)->post(route('profile.photo.update'), [
            '_method' => 'PUT', 'profile_photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotSame('profile-photos/old.png', $user->fresh()->profile_photo_path);
        $this->assertTrue($disk->exists($user->fresh()->profile_photo_path));
    }

    public function test_cloudinary_failure_preserves_the_previous_photo(): void
    {
        config([
            'services.cloudinary.driver' => 'cloudinary', 'services.cloudinary.cloud_name' => 'test-cloud',
            'services.cloudinary.api_key' => 'test-key', 'services.cloudinary.api_secret' => 'test-secret',
        ]);
        Http::fake(['api.cloudinary.com/*' => Http::response([], 503)]);
        $user = $this->account();
        Storage::disk('public')->put('profile-photos/old.png', 'old image');
        $this->signIn($user)->post(route('profile.photo.update'), [
            '_method' => 'PUT', 'profile_photo' => $this->image(),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('profile_photo');
        $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo_path);
        Storage::disk('public')->assertExists('profile-photos/old.png');
    }

    public function test_upload_only_updates_the_authenticated_account_and_keeps_html_fallback(): void
    {
        $owner = $this->account();
        $other = $this->account();
        $this->signIn($owner)->from(route('profile.show'))->post(route('profile.photo.update'), [
            '_method' => 'PUT', 'profile_photo' => $this->image(), 'user_id' => $other->id,
        ])->assertRedirect(route('profile.show'))->assertSessionHas('status', 'Profile picture updated.');
        $this->assertNotSame('profile-photos/old.png', $owner->fresh()->profile_photo_path);
        $this->assertSame('profile-photos/old.png', $other->fresh()->profile_photo_path);
    }

    public function test_upload_requires_an_active_authenticated_account_with_password_setup_completed(): void
    {
        $this->postJson(route('profile.photo.update'), ['_method' => 'PUT'])->assertUnauthorized();
        $user = $this->account();
        $user->update(['must_change_password' => true]);
        $this->signIn($user)->postJson(route('profile.photo.update'), ['_method' => 'PUT'])
            ->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $user->update(['must_change_password' => false, 'status' => 'inactive']);
        $this->signIn($user)->postJson(route('profile.photo.update'), ['_method' => 'PUT'])
            ->assertForbidden()->assertJsonPath('code', 'account_inactive');
        $this->assertSame('profile-photos/old.png', $user->fresh()->profile_photo_path);
    }

    public function test_profile_page_has_upload_and_display_markers_and_database_photos_have_content_versions(): void
    {
        $user = $this->account();
        $response = $this->signIn($user)->get(route('profile.show'))->assertOk();
        foreach (['data-profile-photo-form', 'data-photo-preview', 'data-photo-progress', 'data-profile-photo-display', 'js/profile-photo.js'] as $marker) {
            $this->assertTrue(str_contains($response->getContent(), $marker), "Missing profile upload marker: {$marker}");
        }
        $user->forceFill(['profile_photo_path' => 'database', 'profile_photo_data' => base64_encode('first image')]);
        $first = $user->profilePhotoUrl();
        $user->profile_photo_data = base64_encode('second image');
        $second = $user->profilePhotoUrl();
        $this->assertNotSame($first, $second);
        $this->assertSame($second, $user->profilePhotoUrl());
    }

    private function image(): UploadedFile
    {
        // A real PNG fixture avoids requiring the optional GD extension to run these tests.
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAAK0lEQVR4nGMQN71AU8QwasGoBaMWjFowasGoBaMWjFowasGoBaMWmA4RCwC7i3A9YST7bwAAAABJRU5ErkJggg=='
        ));
    }

    private function account(string $roleName = 'super_admin'): User
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $number = User::count() + 1;
        $user = User::create([
            'name' => 'Test Account', 'first_name' => 'Test', 'last_name' => 'Account', 'email' => "account{$number}@example.test",
            'password' => 'test-password', 'status' => 'active', 'must_change_password' => false,
            'profile_photo_path' => 'profile-photos/old.png',
        ]);
        $user->roles()->sync([$role->getKey()]);
        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->withSession([
            AccountSessionService::VERSION_KEY => (int) $user->session_version,
            'password_hash_web' => Auth::guard()->hashPasswordForCookie($user->getAuthPassword()),
        ])->actingAs($user);
    }
}
