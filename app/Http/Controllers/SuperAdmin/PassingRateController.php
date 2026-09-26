<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PassingRateSetting;
use App\Services\AuditLogger;
use App\Support\YearLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PassingRateController extends Controller
{
    public function index(): View
    {
        $rates = PassingRateSetting::ratesByYearLevel();

        return view('super-admin.passing-rates.index', [
            'rates' => collect([1, 2, 3, 4])->map(fn (int $yearLevel): array => [
                'year_level' => $yearLevel,
                'label' => YearLevel::label($yearLevel),
                'passing_rate' => $rates[$yearLevel] ?? PassingRateSetting::DEFAULT_RATE,
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        DB::transaction(function () use ($validated): void {
            foreach ([1, 2, 3, 4] as $yearLevel) {
                PassingRateSetting::query()->updateOrCreate(
                    ['year_level' => $yearLevel],
                    ['passing_rate' => round((float) ($validated['rates'][$yearLevel] ?? PassingRateSetting::DEFAULT_RATE), 2)],
                );
            }
        });

        PassingRateSetting::clearRateCache();

        AuditLogger::log('UPDATE', 'Passing Rates', 'Updated passing rate benchmarks for 1st-4th year levels');

        return redirect()
            ->route('super-admin.passing-rates')
            ->with('status', 'Passing rates updated.');
    }
}
