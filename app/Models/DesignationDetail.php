<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignationDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'designation_details_id';

    protected $fillable = [
        'instructor_id',
        'designation_id',
        'department_id',
        'college_id',
        'academic_year',
        'effectivity_date',
        'end_date',
        'status',
        'designated_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'effectivity_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(InstructorProfile::class, 'instructor_id', 'instructor_profile_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id', 'designation_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id', 'college_id');
    }

    public function designatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designated_by', 'id');
    }
}
