<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait UsesPublicId
{
    protected static function bootUsesPublicId(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
