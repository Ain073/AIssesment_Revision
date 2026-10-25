<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Report extends Model
{
    use HasFactory;

    public const TYPE_FORMATIVE = 'formative';

    public const TYPE_SUMMATIVE = 'summative';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    protected $primaryKey = 'report_id';

    protected function casts(): array
    {
        return ['revision' => 'integer', 'column_headers' => 'array'];
    }

    protected $fillable = [
        'publish_assessment_id',
        'report_type',
        'course_code_title',
        'column_headers',
        'campus',
        'logo_path',
        'document_code',
        'document_revision',
        'document_date',
        'prepared_by_name',
        'reviewed_by_name',
        'concept_most_learned_skills',
        'concept_least_learned_skills',
        'issues_concern',
        'interventions_done',
        'future_plans_curriculum',
        'report_status',
    ];

    public function publishAssessment(): BelongsTo
    {
        return $this->belongsTo(PublishAssessment::class, 'publish_assessment_id', 'publish_assessment_id');
    }

    public function logoUrl(): string
    {
        if (! $this->logo_path) {
            return asset('images/psu-logo-transparent.png');
        }

        return Str::startsWith($this->logo_path, 's3:')
            ? Storage::disk('s3')->url(Str::after($this->logo_path, 's3:'))
            : asset('storage/'.$this->logo_path);
    }
}
