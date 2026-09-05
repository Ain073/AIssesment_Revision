<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $primaryKey = 'subject_id';

    protected $fillable = [
        'program_id',
        'semester_id',
        'subject_code',
        'subject_name',
        'year_level',
        'is_active',
    ];

    protected $casts = [
        'year_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'subject_id', 'subject_id');
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
