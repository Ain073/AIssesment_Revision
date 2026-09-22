<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PassingRateSetting extends Model
{
    public const DEFAULT_RATE = 50.0;

    protected $fillable = [
        'year_level',
        'passing_rate',
    ];

    private static ?array $rateCache = null;

    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'passing_rate' => 'float',
        ];
    }

    public static function ratesByYearLevel(): array
    {
        if (self::$rateCache !== null) {
            return self::$rateCache;
        }

        $defaults = [
            1 => self::DEFAULT_RATE,
            2 => self::DEFAULT_RATE,
            3 => self::DEFAULT_RATE,
            4 => self::DEFAULT_RATE,
        ];

        $storedRates = self::query()
            ->pluck('passing_rate', 'year_level')
            ->mapWithKeys(fn ($rate, $yearLevel): array => [(int) $yearLevel => (float) $rate])
            ->all();

        return self::$rateCache = array_replace($defaults, $storedRates);
    }

    public static function rateForYearLevel(?int $yearLevel): float
    {
        $yearLevel = (int) $yearLevel;

        return self::ratesByYearLevel()[$yearLevel] ?? self::DEFAULT_RATE;
    }

    public static function passingScore(float $maxScore, ?int $yearLevel): float
    {
        return $maxScore * (self::rateForYearLevel($yearLevel) / 100);
    }

    public static function clearRateCache(): void
    {
        self::$rateCache = null;
    }
}
