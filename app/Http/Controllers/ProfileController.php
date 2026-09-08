<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = $this->profileUser();

        return view('profile.index', $this->layoutData($user) + [
            'user' => $user,
            'detailRows' => $this->detailRows($user),
        ]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $this->profileUser();
        $file = $validated['profile_photo'];
        $photoAttributes = $this->storeProfilePhoto($file, $user);

        $this->deleteExistingProfilePhoto($user);

        $user->update($photoAttributes);

        return back()->with('status', 'Profile picture updated.');
    }

    public function photo(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        abort_unless(
            $user->profile_photo_path === 'database' && filled($user->profile_photo_data),
            404
        );

        $photo = base64_decode($user->profile_photo_data, true);

        abort_if($photo === false, 404);

        return response($photo, 200)
            ->header('Content-Type', $user->profile_photo_mime ?: 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function storeProfilePhoto(UploadedFile $file, User $user): array
    {
        if ($this->storesProfilePhotosInDatabase()) {
            return $this->storeProfilePhotoInDatabase($file);
        }

        if ($this->storesProfilePhotosOnS3()) {
            return $this->storeProfilePhotoOnS3($file);
        }

        if ($this->storesProfilePhotosOnCloudinary()) {
            return [
                'profile_photo_path' => $this->uploadProfilePhotoToCloudinary($file, $user),
                'profile_photo_mime' => null,
                'profile_photo_data' => null,
            ];
        }

        return [
            'profile_photo_path' => $file->store('profile-photos', 'public'),
            'profile_photo_mime' => null,
            'profile_photo_data' => null,
        ];
    }

    private function deleteExistingProfilePhoto(User $user): void
    {
        if (! $user->profile_photo_path) {
            return;
        }

        if (Str::startsWith($user->profile_photo_path, 's3:')) {
            Storage::disk('s3')->delete(Str::after($user->profile_photo_path, 's3:'));

            return;
        }

        if (
            $user->profile_photo_path !== 'database'
            && ! Str::startsWith($user->profile_photo_path, ['http://', 'https://'])
        ) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
    }

    private function storesProfilePhotosInDatabase(): bool
    {
        return config('services.cloudinary.driver') === 'database';
    }

    private function storeProfilePhotoInDatabase(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Profile picture could not be read. Please try again.',
            ]);
        }

        return [
            'profile_photo_path' => 'database',
            'profile_photo_mime' => $file->getMimeType() ?: 'image/jpeg',
            'profile_photo_data' => base64_encode($contents),
        ];
    }

    private function storesProfilePhotosOnS3(): bool
    {
        return in_array(config('services.cloudinary.driver'), ['s3', 'r2'], true);
    }

    private function storeProfilePhotoOnS3(UploadedFile $file): array
    {
        $path = $file->store('profile-photos', 's3');

        if ($path === false) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Profile picture could not be uploaded. Please try again.',
            ]);
        }

        return [
            'profile_photo_path' => 's3:'.$path,
            'profile_photo_mime' => null,
            'profile_photo_data' => null,
        ];
    }

    private function storesProfilePhotosOnCloudinary(): bool
    {
        $cloudinary = config('services.cloudinary');

        return ($cloudinary['driver'] ?? 'local') === 'cloudinary'
            && filled($cloudinary['cloud_name'] ?? null)
            && filled($cloudinary['api_key'] ?? null)
            && filled($cloudinary['api_secret'] ?? null);
    }

    private function uploadProfilePhotoToCloudinary(UploadedFile $file, User $user): string
    {
        $cloudinary = config('services.cloudinary');
        $cloudName = (string) $cloudinary['cloud_name'];
        $apiKey = (string) $cloudinary['api_key'];
        $apiSecret = (string) $cloudinary['api_secret'];
        $folder = trim((string) ($cloudinary['folder'] ?? 'aissessment/pfp'), '/');

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Profile picture could not be read. Please try again.',
            ]);
        }

        $params = [
            'overwrite' => 'true',
            'public_id' => 'u'.$user->id.'-'.Str::random(10),
            'timestamp' => time(),
        ];

        if ($folder !== '') {
            $params['folder'] = $folder;
        }

        $response = Http::timeout(20)
            ->attach('file', $contents, $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", $params + [
                'api_key' => $apiKey,
                'signature' => $this->cloudinarySignature($params, $apiSecret),
            ]);

        if (! $response->successful() || blank($response->json('secure_url'))) {
            Log::warning('Profile picture Cloudinary upload failed.', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            throw ValidationException::withMessages([
                'profile_photo' => 'Profile picture could not be uploaded. Please try again.',
            ]);
        }

        return (string) $response->json('secure_url');
    }

    private function cloudinarySignature(array $params, string $apiSecret): string
    {
        ksort($params);

        $payload = collect($params)
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value, string $key) => $key.'='.$value)
            ->implode('&');

        return sha1($payload.$apiSecret);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->profileUser()->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return back()->with('status', 'Password updated.');
    }

    private function profileUser(): User
    {
        /** @var User $user */
        $user = Auth::user()->loadMissing(
            'roles',
            'studentProfile.program.department.college',
            'instructorProfile.department.college',
        );

        return $user;
    }

    private function layoutData(User $user): array
    {
        return [
            'portalSubtitle' => $this->portalSubtitle($user),
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => $this->profileMeta($user),
            'navItems' => $this->navItems($user),
            'showTopbarSearch' => false,
        ];
    }

    private function detailRows(User $user): array
    {
        if ($user->studentProfile) {
            $program = $user->studentProfile->program;
            $programAndCollege = collect([
                $program?->program_name,
                $program?->department?->dept_name,
                $program?->department?->college?->college_name,
            ])->filter()->join(' - ');

            return [
                ['label' => 'Student Number', 'value' => $user->studentProfile->student_number ?? 'Not assigned'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Program / Department / College', 'value' => $programAndCollege ?: 'Not assigned'],
            ];
        }

        if ($user->instructorProfile) {
            $department = $user->instructorProfile->department;
            $departmentAndCollege = collect([
                $department?->dept_name,
                $department?->college?->college_name,
            ])->filter()->join(' - ');

            return [
                ['label' => 'Employee Number', 'value' => $user->instructorProfile->employee_number ?? 'Not assigned'],
                ['label' => 'Email', 'value' => $user->email],
                ['label' => 'Department / College', 'value' => $departmentAndCollege ?: 'Not assigned'],
            ];
        }

        return [
            ['label' => 'Email', 'value' => $user->email],
        ];
    }

    private function navItems(User $user): array
    {
        if ($user->hasRole('super_admin')) {
            return $this->markNavActive([
                ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('super-admin.dashboard'), 'active_route' => 'super-admin.dashboard'],
                ['label' => 'Colleges & Departments', 'icon' => 'account_balance', 'href' => route('super-admin.colleges'), 'active_route' => 'super-admin.colleges'],
                ['label' => 'Programs', 'icon' => 'school', 'href' => route('super-admin.programs'), 'active_route' => 'super-admin.programs'],
                ['label' => 'Dean Designation', 'icon' => 'admin_panel_settings', 'href' => route('super-admin.roles'), 'active_route' => 'super-admin.roles'],
                ['label' => 'Users', 'icon' => 'person_search', 'href' => route('super-admin.users'), 'active_route' => 'super-admin.users*'],
            ]);
        }

        $items = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $items = array_merge($items, [
                ['label' => 'Classes', 'icon' => 'school', 'href' => route('instructor.classes'), 'active_route' => 'instructor.classes*'],
                ['label' => 'Assessments', 'icon' => 'assignment', 'href' => route('instructor.assessments'), 'active_route' => 'instructor.assessments*'],
                ['label' => 'Reports', 'icon' => 'summarize', 'href' => route('instructor.reports'), 'active_route' => 'instructor.reports*'],
            ]);
        }

        if ($user->hasRole('admin_dean')) {
            $items = array_merge($items, [
                ['label' => 'Departments', 'icon' => 'apartment', 'href' => route('admin-dean.departments'), 'active_route' => 'admin-dean.departments'],
                ['label' => 'Users', 'icon' => 'groups', 'href' => route('admin-dean.teachers'), 'active_route' => 'admin-dean.teachers'],
                ['label' => 'Chair Designation', 'icon' => 'admin_panel_settings', 'href' => route('admin-dean.designations'), 'active_route' => 'admin-dean.designations'],
            ]);
        }

        if ($user->hasRole('department_chair')) {
            $items = array_merge($items, [
                ['label' => 'Users', 'icon' => 'groups', 'href' => route('department-chair.teachers'), 'active_route' => ['department-chair.teachers', 'department-chair.students*']],
                ['label' => 'Subjects', 'icon' => 'menu_book', 'href' => route('department-chair.subjects'), 'active_route' => 'department-chair.subjects*'],
            ]);
        }

        if (! empty($items)) {
            array_unshift($items, $this->dashboardNavItem($user));

            return $this->markNavActive($items);
        }

        return $this->markNavActive([
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('student.dashboard'), 'active_route' => 'student.dashboard'],
            ['label' => 'Classes', 'icon' => 'school', 'href' => route('student.classes'), 'active_route' => 'student.classes*'],
            ['label' => 'Assessments', 'icon' => 'assignment', 'href' => route('student.assessments'), 'active_route' => 'student.assessments*'],
            ['label' => 'Results', 'icon' => 'grading', 'href' => route('student.results'), 'active_route' => 'student.results'],
        ]);
    }

    private function markNavActive(array $items): array
    {
        return array_map(function (array $item): array {
            $activeRoute = (array) $item['active_route'];
            unset($item['active_route']);

            return $item + ['active' => request()->routeIs(...$activeRoute)];
        }, $items);
    }

    private function dashboardNavItem(User $user): array
    {
        $href = route('instructor.dashboard');
        $activeRoutes = [];

        if ($user->hasRole('instructor') || $user->hasRole('department_chair')) {
            $activeRoutes[] = 'instructor.dashboard';
        }

        if ($user->hasRole('admin_dean')) {
            $activeRoutes[] = 'admin-dean.dashboard';
        }

        if ($user->hasRole('department_chair')) {
            $activeRoutes[] = 'department-chair.dashboard';
        }

        if (request()->routeIs('admin-dean.*') && $user->hasRole('admin_dean')) {
            $href = route('admin-dean.dashboard');
        } elseif (request()->routeIs('department-chair.*') && $user->hasRole('department_chair')) {
            $href = route('department-chair.dashboard');
        }

        return [
            'label' => 'Dashboard',
            'icon' => 'dashboard',
            'href' => $href,
            'active_route' => $activeRoutes ?: 'instructor.dashboard',
        ];
    }

    private function portalSubtitle(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return 'Admin Panel';
        }

        if ($user->hasRole('admin_dean')) {
            return 'Dean';
        }

        if ($user->hasRole('department_chair')) {
            return 'Department Chair';
        }

        if ($user->hasRole('instructor')) {
            return 'Instructor';
        }

        return 'Student';
    }

    private function profileMeta(User $user): string
    {
        if ($user->hasRole('super_admin')) {
            return 'Admin Account';
        }

        return $this->roleLabel($user).' Account';
    }

    private function roleLabel(User $user): string
    {
        $labels = [
            'super_admin' => 'Admin',
            'admin_dean' => 'Dean',
            'department_chair' => 'Department Chair',
            'instructor' => 'Instructor',
            'student' => 'Student',
        ];

        return $user->roles
            ->pluck('role_name')
            ->map(fn (string $role) => $labels[$role] ?? Str::headline($role))
            ->join(', ');
    }
}
