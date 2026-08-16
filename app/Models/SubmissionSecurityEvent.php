<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionSecurityEvent extends Model
{
    use HasFactory;

    public const TYPE_COPY = 'copy_attempt';

    public const TYPE_CUT = 'cut_attempt';

    public const TYPE_PASTE = 'paste_attempt';

    public const TYPE_CONTEXT_MENU = 'context_menu_attempt';

    public const TYPE_TAB_HIDDEN = 'tab_hidden';

    public const TYPE_WINDOW_BLUR = 'window_blur';

    public const TYPE_FLOATING_WINDOW = 'floating_window';

    public const TYPE_PRINT_SHORTCUT = 'print_shortcut';

    public const TYPE_SCREENSHOT_SHORTCUT = 'screenshot_shortcut';

    protected $primaryKey = 'submission_security_event_id';

    protected $fillable = [
        'submission_id',
        'event_uuid',
        'event_type',
        'occurred_at',
        'request_fingerprint',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id', 'submission_id');
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::TYPE_COPY => 'Copy attempted',
            self::TYPE_CUT => 'Cut attempted',
            self::TYPE_PASTE => 'Paste attempted',
            self::TYPE_CONTEXT_MENU => 'Context menu opened',
            self::TYPE_TAB_HIDDEN => 'Assessment tab hidden',
            self::TYPE_WINDOW_BLUR => 'Assessment window lost focus',
            self::TYPE_FLOATING_WINDOW => 'Floating or split screen detected',
            self::TYPE_PRINT_SHORTCUT => 'Print shortcut attempted',
            self::TYPE_SCREENSHOT_SHORTCUT => 'Screenshot shortcut attempted',
        ];
    }

    public function displayLabel(): string
    {
        return self::labels()[$this->event_type]
            ?? ucfirst(str_replace('_', ' ', $this->event_type));
    }
}
