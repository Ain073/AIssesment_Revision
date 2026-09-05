<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function classes(): HasManyThrough
    {
        return $this->hasManyThrough(
            AcademicClass::class,
            ClassDetail::class,
            'student_id',
            'class_id',
            'student_profile_id',
            'class_id'
        )->where('class_details.status', ClassDetail::STATUS_APPROVED);
    }

    public function classDetails(): HasMany
    {
        return $this->hasMany(ClassDetail::class, 'student_id', 'student_profile_id');
    }

    public function classJoinRequests(): HasMany
    {
        return $this->classDetails()
            ->where('entry_method', ClassDetail::METHOD_JOIN_CODE);
    }
}
