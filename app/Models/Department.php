<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory, UsesPublicId;

    protected $primaryKey = 'department_id';

    protected $fillable = [
        'college_id',
        'dept_name',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id', 'college_id');
    }

    public function instructorProfiles(): HasMany
    {
        return $this->hasMany(InstructorProfile::class, 'department_id', 'department_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'department_id', 'department_id');
    }

}
