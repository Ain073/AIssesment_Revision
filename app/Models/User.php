<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\UsesPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, UsesPublicId;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

    public function instructorProfile(): HasOne
    {
        return $this->hasOne(InstructorProfile::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('role_name', $roleName);
    }

    public function displayName(): string
    {
        $fullName = collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])->filter()->implode(' ');

        return $fullName !== '' ? $fullName : $this->name;
    }

    public function portalRouteName(): ?string
    {
        $roles = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->get();

        if ($roles->contains('role_name', 'super_admin')) {
            return 'super-admin.dashboard';
        }

        if ($roles->contains('role_name', 'admin_dean')) {
            return 'admin-dean.dashboard';
        }

        if ($roles->pluck('role_name')->intersect(['instructor', 'department_chair'])->isNotEmpty()) {
            return 'instructor.dashboard';
        }

        if ($roles->contains('role_name', 'student')) {
            return 'student.dashboard';
        }

        return null;
    }
}
