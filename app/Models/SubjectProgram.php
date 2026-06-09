<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectProgram extends Model
{
    use HasFactory;

    protected $table = 'subject_program';

    protected $primaryKey = 'subject_program_id';

    protected $fillable = [
        'subject_id',
        'program_id',
        'year_level',
        'semester',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'subject_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }
}
