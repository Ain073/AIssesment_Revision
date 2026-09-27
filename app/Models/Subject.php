<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Subject extends Model
{
    use HasFactory;

    protected $primaryKey = 'subject_id';

    protected $fillable = [
        'department_id',
        'subject_code',
        'subject_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function classes(): HasManyThrough
    {
        return $this->hasManyThrough(
            AcademicClass::class,
            ClassDetail::class,
            'subject_id',
            'class_id',
            'subject_id',
            'class_id'
        )->whereNull('class_details.student_id');
    }

    public function classDetails(): HasMany
    {
        return $this->hasMany(ClassDetail::class, 'subject_id', 'subject_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'subject_id', 'subject_id');
    }
}
