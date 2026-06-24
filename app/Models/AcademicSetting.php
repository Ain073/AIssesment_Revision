<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicSetting extends Model
{
    public const SEMESTERS = [
        'First Semester',
        'Second Semester',
        'Summer',
    ];

    protected $fillable = [
        'active_semester',
    ];
}
