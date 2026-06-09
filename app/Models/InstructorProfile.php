<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstructorProfile extends Model
{
    use HasFactory;

    protected $primaryKey = 'instructor_profile_id';

    protected $fillable = [
        'user_id',
        'department_id',
        'employee_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'instructor_id', 'instructor_profile_id');
    }
}
