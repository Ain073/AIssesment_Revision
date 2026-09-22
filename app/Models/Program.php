<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory, UsesPublicId;

    protected $primaryKey = 'program_id';

    protected $fillable = [
        'department_id',
        'program_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class, 'program_id', 'program_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'program_id', 'program_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'program_id', 'program_id');
    }
}
