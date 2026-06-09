<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    use HasFactory;

    protected $primaryKey = 'college_id';

    protected $fillable = [
        'college_name',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'college_id', 'college_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'college_id', 'college_id');
    }
}
