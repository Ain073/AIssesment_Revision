<?php

namespace App\Models;

use App\Models\Concerns\UsesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class College extends Model
{
    use HasFactory, UsesPublicId;

    protected $primaryKey = 'college_id';

    protected $fillable = [
        'college_name',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'college_id', 'college_id');
    }

    public function programs(): HasManyThrough
    {
        return $this->hasManyThrough(
            Program::class,
            Department::class,
            'college_id',
            'department_id',
            'college_id',
            'department_id'
        );
    }
}
