<?php

namespace App\Support;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\Frame;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReportPdf
{
    private const TEXT_CHUNK_LENGTH = 300;

    private const TEXT_CHUNK_LINES = 10;

    public static function stream(array $data, string $paper): Response
    {
        abort_unless(extension_loaded('gd'), 503, 'PDF generation requires the PHP GD extension.');

        $report = $data['rows']->first()['report'] ?? null;
        $data['reportLogoUrl'] = self::logoDataUri($report);
        $reportType = $data['reportType'];
        $data['pdfRows'] = $data['rows']->flatMap(static function (array $row) use ($reportType): array {
            $report = $row['report'];
            $fields = [
                'most' => self::splitText($report->concept_most_learned_skills),
                'least' => self::splitText($report->concept_least_learned_skills),
                'issues' => self::splitText($report->issues_concern),
                'action' => self::splitText($reportType === Report::TYPE_FORMATIVE
                    ? $report->interventions_done
                    : $report->future_plans_curriculum),
            ];
            $parts = max(array_map('count', $fields));

            return array_map(static fn (int $index): array => [
                'row' => $row,
                'first' => $index === 0,
                'last' => $index === $parts - 1,
                'most' => $fields['most'][$index] ?? '',
                'least' => $fields['least'][$index] ?? '',
                'issues' => $fields['issues'][$index] ?? '',
                'action' => $fields['action'][$index] ?? '',
            ], range(0, $parts - 1));
        });
        $paperSizes = config('reports.paper_sizes');
        $selectedPaper = $paperSizes[$paper] ?? $paperSizes['long'];
        $page = [
            0,
            0,
            (float) $selectedPaper['width'] * 72,
            (float) $selectedPaper['height'] * 72,
        ];

        $pdf = Pdf::loadView('reports.pdf', $data)
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->setPaper($page);
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'end_page_render',
            'f' => static fn (Frame $frame, Canvas $canvas) => self::drawMatrixBorders($frame, $canvas),
        ]]);
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(static function (int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics): void {
            $font = $fontMetrics->getFont('Helvetica', 'italic');
            $label = "Page {$pageNumber} of {$pageCount}";
            $x = $canvas->get_width() - 18 - $fontMetrics->getTextWidth($label, $font, 7);
            $canvas->text($x, $canvas->get_height() - 16, $label, $font, 7, [0.42, 0.45, 0.5]);
        });

        $response = $pdf->stream($data['reportType'].'-report.pdf');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private static function drawMatrixBorders(Frame $frame, Canvas $canvas): void
    {
        $node = $frame->get_node();
        if ($node instanceof \DOMElement && $node->tagName === 'table' && in_array('report-matrix', explode(' ', $node->getAttribute('class')), true)) {
            [$x, $y, $width, $height] = $frame->get_border_box();
            $canvas->line($x, $y, $x + $width, $y, [0.067, 0.094, 0.153], 0.6);
            $canvas->line($x, $y + $height, $x + $width, $y + $height, [0.067, 0.094, 0.153], 0.6);

            return;
        }

        foreach ($frame->get_children() as $child) {
            self::drawMatrixBorders($child, $canvas);
        }
    }

    private static function splitText(?string $value): array
    {
        $value = trim($value ?? '');
        if ($value === '') {
            return [''];
        }

        $lines = [];
        foreach (preg_split('/\R/u', $value) ?: [$value] as $line) {
            while (mb_strlen($line) > self::TEXT_CHUNK_LENGTH) {
                $window = mb_substr($line, 0, self::TEXT_CHUNK_LENGTH);
                $break = mb_strrpos($window, ' ');
                $break = $break === false || $break < self::TEXT_CHUNK_LENGTH / 2
                    ? self::TEXT_CHUNK_LENGTH
                    : $break;
                $lines[] = mb_substr($line, 0, $break);
                $line = ltrim(mb_substr($line, $break));
            }
            $lines[] = $line;
        }

        $chunks = [];
        $current = '';
        $lineCount = 0;
        foreach ($lines as $line) {
            if ($lineCount > 0 && (mb_strlen($current) + mb_strlen($line) + 1 > self::TEXT_CHUNK_LENGTH || $lineCount >= self::TEXT_CHUNK_LINES)) {
                $chunks[] = rtrim($current);
                $current = '';
                $lineCount = 0;
            }
            $current .= ($lineCount > 0 ? "\n" : '').$line;
            $lineCount++;
        }
        $chunks[] = rtrim($current);

        return $chunks;
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
