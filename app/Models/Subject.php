<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $primaryKey = 'subject_id';

    protected $fillable = [
        'subject_code',
        'subject_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subjectPrograms(): HasMany
    {
        return $this->hasMany(SubjectProgram::class, 'subject_id', 'subject_id');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'subject_program', 'subject_id', 'program_id')
            ->withPivot(['subject_program_id', 'year_level', 'semester'])
            ->withTimestamps();
    }

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'subject_id', 'subject_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'subject_id', 'subject_id');
    }
}
