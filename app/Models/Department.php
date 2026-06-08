<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory;

    protected $primaryKey = 'department_id';

    protected $fillable = [
        'college_id',
        'dept_name',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id', 'college_id');
    }
}
