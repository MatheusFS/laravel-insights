<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PDF Document')</title>
    <style>
        {{-- ========================================================================
             RESET E CONFIGURAÇÕES GLOBAIS
             ======================================================================== --}}
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 18mm;
        }

        body {
            font-family: 'DejaVu Sans', 'Noto Color Emoji', 'Apple Color Emoji', sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }

        .pdf-page {
            padding: 18mm;
        }

        {{-- ========================================================================
             HEADER PRINCIPAL
             ======================================================================== --}}
        .header-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #1976d2;
        }

        .header-section h1 {
            font-size: 18pt;
            margin-bottom: 3px;
            color: #000;
            font-weight: bold;
        }

        .header-section .subtitle {
            font-size: 10pt;
            color: #666;
        }

        {{-- ========================================================================
             SEÇÕES
             ======================================================================== --}}
        .section {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 8px;
            padding: 8px 10px;
            background-color: #f0f0f0;
            border-left: 4px solid #1976d2;
            color: #000;
        }

        {{-- ========================================================================
             TABELAS
             ======================================================================== --}}
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table th {
            background-color: #f0f0f0;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            color: #000;
            font-size: 10pt;
        }

        table td {
            padding: 8px 10px;
            border: 1px solid #eee;
            color: #333;
            font-size: 9.5pt;
        }

        {{-- ========================================================================
             BADGES (Severity / Status)
             ======================================================================== --}}
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
        }

        .badge-critical {
            background-color: #d32f2f;
            color: white;
        }

        .badge-high {
            background-color: #f57c00;
            color: white;
        }

        .badge-medium {
            background-color: #1976d2;
            color: white;
        }

        .badge-low {
            background-color: #388e3c;
            color: white;
        }

        {{-- ========================================================================
             FIELD LABELS E VALUES
             ======================================================================== --}}
        .field-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            min-width: 120px;
            font-size: 10pt;
        }

        .field-value {
            color: #333;
            font-size: 10pt;
        }

        {{-- ========================================================================
             BOX (Highlight boxes)
             ======================================================================== --}}
        .box {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            background-color: #fafafa;
            border-radius: 3px;
            page-break-inside: avoid;
        }

        {{-- ========================================================================
             TIMELINE
             ======================================================================== --}}
        .timeline-item {
            margin: 8px 0;
            padding-left: 15px;
            border-left: 3px solid #1976d2;
            color: #333;
        }

        .timeline-item .time {
            font-weight: bold;
            color: #000;
            font-size: 10pt;
        }

        .timeline-item .label {
            color: #666;
            font-size: 9pt;
        }

        {{-- ========================================================================
             LISTAS
             ======================================================================== --}}
        ol, ul {
            margin-left: 20px;
            color: #333;
        }

        li {
            margin-bottom: 5px;
            font-size: 10pt;
        }

        {{-- ========================================================================
             FOOTER
             ======================================================================== --}}
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
            font-size: 8pt;
            color: #666;
        }

        .footer p {
            margin-bottom: 5px;
            text-align: center;
        }

        {{-- ========================================================================
             ALERTAS E NOTAS
             ======================================================================== --}}
        .note {
            padding: 10px;
            border-left: 4px solid #1976d2;
            background-color: #e3f2fd;
            font-size: 9pt;
            color: #1565c0;
            margin: 10px 0;
            page-break-inside: avoid;
        }

        .note.warning {
            border-left-color: #f57c00;
            background-color: #fff3e0;
            color: #e65100;
        }

        .note.success {
            border-left-color: #388e3c;
            background-color: #f1f8e9;
            color: #2e7d32;
        }

        .note.danger {
            border-left-color: #d32f2f;
            background-color: #ffebee;
            color: #c62828;
        }

        {{-- ========================================================================
             PRINT STYLES
             ======================================================================== --}}
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            body {
                padding: 0;
                margin: 0;
            }

            .pdf-page {
                padding: 18mm;
            }

            .page-break {
                page-break-after: always;
            }

            .no-break {
                page-break-inside: avoid;
            }

            table {
                page-break-inside: avoid;
            }

            .section {
                page-break-inside: avoid;
            }
        }

        {{-- ========================================================================
             RESPONSIVE PARA TELAS (em caso de visualização em HTML)
             ======================================================================== --}}
        @media screen {
            body {
                max-width: 900px;
                margin: 0 auto;
                background-color: #e0e0e0;
                padding: 10px;
            }

            .pdf-page {
                background-color: white;
                padding: 18mm;
                margin-bottom: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
        }
    </style>
</head>
<body>
    <div class="pdf-page">
        @yield('content')
    </div>
</body>
</html>
