<?php

namespace MatheusFS\Laravel\Insights\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Base PDF Generator Service
 *
 * Provides reusable PDF generation with standardized configuration:
 * - A4 paper size (210mm x 297mm)
 * - UTF-8 safe fonts (DejaVu Sans)
 * - Portrait orientation by default
 */
class PdfGenerator
{
    /**
     * Generate PDF from Blade view and return as Response
     *
     * @param  string  $view  Blade view path (e.g., 'insights::pdf.incidents.receipt_v2')
     * @param  array  $data  Data to pass to the view
     * @param  string  $filename  Download filename (without .pdf extension)
     * @param  array  $options  Additional PDF options
     */
    public function generate(
        string $view,
        array $data,
        string $filename,
        array $options = []
    ): Response {
        $pdf = $this->createPdf($view, $data, $options);

        return $pdf->download($filename.'.pdf');
    }

    /**
     * Generate PDF and return as inline (browser preview)
     *
     * @param  string  $view  Blade view path
     * @param  array  $data  Data to pass to the view
     * @param  array  $options  Additional PDF options
     */
    public function stream(
        string $view,
        array $data,
        array $options = []
    ): Response {
        $pdf = $this->createPdf($view, $data, $options);

        return $pdf->stream();
    }

    /**
     * Create configured PDF instance
     *
     * @param  string  $view  Blade view path
     * @param  array  $data  Data to pass to the view
     * @param  array  $options  Additional options
     */
    protected function createPdf(
        string $view,
        array $data,
        array $options = []
    ): \Barryvdh\DomPDF\PDF {
        $pdf = Pdf::loadView($view, $data);

        // Force A4 paper size
        $pdf->setPaper('A4', $options['orientation'] ?? 'portrait');

        // Set DomPDF options (UTF-8 safe fonts, rendering settings)
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false, // Security: disable remote resources
            'defaultFont' => 'DejaVu Sans', // UTF-8 safe font with emoji support
            'dpi' => $options['dpi'] ?? 96,
            'defaultMediaType' => 'print',
            'isFontSubsettingEnabled' => true,
            'fontDir' => base_path('resources/fonts'),
            'fontCache' => storage_path('fonts'),
            'isPhpEnabled' => false,
        ]);

        return $pdf;
    }

    /**
     * Get PDF as base64 string (useful for API responses)
     *
     * @param  string  $view  Blade view path
     * @param  array  $data  Data to pass to the view
     * @param  array  $options  Additional options
     * @return string Base64 encoded PDF
     */
    public function toBase64(
        string $view,
        array $data,
        array $options = []
    ): string {
        $pdf = $this->createPdf($view, $data, $options);

        return base64_encode($pdf->output());
    }
}
