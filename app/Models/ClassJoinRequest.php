<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassJoinRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $primaryKey = 'class_join_request_id';

    protected $fillable = [
        'class_id',
        'student_profile_id',
        'status',
        'requested_at',
        'responded_at',
        'responded_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id', 'class_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id', 'student_profile_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
