<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountSessionService;
use App\Support\PortalNavigation;
use Illuminate\Http\JsonResponse;
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

    public function activityLog(Request $request): View
    {
        $user = $this->profileUser();

        if ($user->hasRole('student')) {
            abort(403, 'Activity logs are not available for student accounts.');
        }

        $query = AuditLog::query()->where('user_id', $user->id);

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', $term)
                    ->orWhere('module', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('ip_address', 'like', $term);
            });
        }

        $logs = $query->latest('created_at')->paginate(15)->withQueryString();

        $modules = AuditLog::where('user_id', $user->id)->distinct()->pluck('module')->sort()->values();

        return view('profile.activity-log', $this->layoutData($user) + [
            'user' => $user,
            'logs' => $logs,
            'modules' => $modules,
        ]);
    }

    public function updatePhoto(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $this->profileUser();
        $file = $validated['profile_photo'];
        $photoAttributes = $this->storeProfilePhoto($file, $user);

        $previousPhoto = clone $user;
        $user->update($photoAttributes);

        // Retain the previous image if the database or required audit write fails.
        $user->getConnection()->afterCommit(function () use ($previousPhoto, $user): void {
            try {
                $this->deleteExistingProfilePhoto($previousPhoto);
            } catch (\Throwable) {
                Log::warning('Previous profile picture could not be cleaned up.', ['user_id' => $user->id]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Profile picture updated.',
                'photo_url' => $user->profilePhotoUrl(),
            ])->header('Cache-Control', 'no-store');
        }

        return back()->with('status', 'Profile picture updated.');
    }

    public function photo(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->profile_photo_path !== 'database' || blank($user->profile_photo_data)) {
            return $this->placeholderProfilePhotoResponse($user);
        }

        $photo = base64_decode($user->profile_photo_data, true);

        if ($photo === false) {
            return $this->placeholderProfilePhotoResponse($user);
        }

        return response($photo, 200)
            ->header('Content-Type', $user->profile_photo_mime ?: 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function placeholderProfilePhotoResponse(User $user): Response
    {
        $initial = Str::upper(Str::substr($user->first_name ?: $user->displayName(), 0, 1)) ?: 'U';
        $escapedInitial = htmlspecialchars($initial, ENT_QUOTES, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">
  <rect width="96" height="96" rx="48" fill="#ffda27"/>
  <text x="48" y="58" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="38" font-weight="700" fill="#001a70">{$escapedInitial}</text>
</svg>
SVG;

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
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

        $path = $file->store('profile-photos', 'public');
        if ($path === false) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Profile picture could not be uploaded. Please try again.',
            ]);
        }

        return [
            'profile_photo_path' => $path,
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

    public function updatePassword(Request $request, AccountSessionService $sessions): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $sessions->changePassword($this->profileUser(), $validated['password'], $request);

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
            'portalSubtitle' => $user->portalSubtitle(),
            'profileInitials' => strtoupper(Str::substr($user->first_name ?? $user->displayName(), 0, 1)),
            'profileName' => $user->displayName(),
            'profileMeta' => $user->portalProfileMeta(),
            'navItems' => PortalNavigation::forUser($user),
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
                $program?->department?->college?->displayName(),
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
                $department?->college?->displayName(),
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
}
