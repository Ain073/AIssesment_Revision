<?php

namespace App\Support;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReportPdf
{
    public static function stream(array $data, string $paper): Response
    {
        abort_unless(extension_loaded('gd'), 503, 'PDF generation requires the PHP GD extension.');

        $report = $data['rows']->first()['report'] ?? null;
        $data['reportLogoUrl'] = self::logoDataUri($report);
        $paperSizes = config('reports.paper_sizes');
        $selectedPaper = $paperSizes[$paper] ?? $paperSizes['long'];
        $page = [
            0,
            0,
            (float) $selectedPaper['width'] * 72,
            (float) $selectedPaper['height'] * 72,
        ];

        $response = Pdf::loadView('reports.pdf', $data)
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->setPaper($page)
            ->stream($data['reportType'].'-report.pdf');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private static function logoDataUri(?Report $report): string
    {
        $defaultLogo = file_get_contents(public_path('images/psu-logo-transparent.png'));
        $image = $defaultLogo;

        if ($report?->logo_path) {
            try {
                $path = $report->logo_path;
                $isCloud = Str::startsWith($path, 's3:');
                $image = Storage::disk($isCloud ? 's3' : 'public')
                    ->get($isCloud ? Str::after($path, 's3:') : $path);
            } catch (Throwable $exception) {
                Log::warning('Report PDF logo could not be loaded.', ['report_id' => $report->report_id]);
                $image = $defaultLogo;
            }
        }

        $imageInfo = @getimagesizefromstring($image);
        $mime = $imageInfo[2] ?? null;
        if (! in_array($mime, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            Log::warning('Report PDF logo is not a supported image.', ['report_id' => $report?->report_id]);
            $image = $defaultLogo;
            $mime = IMAGETYPE_PNG;
        }
        if ($mime === IMAGETYPE_WEBP && ! function_exists('imagecreatefromwebp')) {
            Log::warning('Report PDF logo needs the GD extension for WebP.', ['report_id' => $report?->report_id]);
            $image = $defaultLogo;
            $mime = IMAGETYPE_PNG;
        }

        return 'data:'.image_type_to_mime_type($mime).';base64,'.base64_encode($image);
    }
}
