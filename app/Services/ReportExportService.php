<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        private readonly ReportService $reports
    ) {}

    public function download(string $reportType, array $filters = []): StreamedResponse
    {
        $rows = $this->reports->fullFieldReportRows($reportType, $filters);
        $normalizedType = $this->reports->normalizeReportType($reportType);
        $filename = sprintf(
            '%s_%s_to_%s.csv',
            $normalizedType,
            $filters['date_from'] ?? 'start',
            $filters['date_to'] ?? 'end'
        );

        return response()->streamDownload(function () use ($rows, $normalizedType, $filters): void {
            $handle = fopen('php://output', 'w');
            $headers = $rows->isNotEmpty()
                ? array_keys((array) $rows->first())
                : ['report_type', 'date_from', 'date_to', 'message'];

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            if ($rows->isEmpty()) {
                fputcsv($handle, [
                    $normalizedType,
                    $filters['date_from'] ?? null,
                    $filters['date_to'] ?? null,
                    'No transactions found for the selected filters.',
                ]);
            }

            foreach ($rows as $row) {
                $row = (array) $row;
                fputcsv($handle, array_map(
                    fn (string $header) => $this->safeCsvValue($row[$header] ?? null),
                    $headers
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@'], true) ? "'{$value}" : $value;
    }
}
