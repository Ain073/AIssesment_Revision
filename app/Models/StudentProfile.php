<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProfile extends Model
{
    use HasFactory, UsesPublicId;

    protected $primaryKey = 'student_profile_id';

    protected $fillable = [
        'user_id',
        'program_id',
        'student_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(AcademicClass::class, 'class_students', 'student_profile_id', 'class_id')
            ->withTimestamps();
    }

    public function classJoinRequests(): HasMany
    {
        return $this->hasMany(ClassJoinRequest::class, 'student_profile_id', 'student_profile_id');
    }
}
