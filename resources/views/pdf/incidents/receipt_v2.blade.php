@extends('insights::pdf.layout_incident')

@section('title', 'Comprovante de Incidente - ' . $incident['id'])

@section('content')
    {{-- ============================================================================
         ICONS: Provided by IncidentPdfGeneratorV2.php via IconGenerator
         
         Icons are file-based PNG stored in storage/app/pdf-icons/
         IconGenerator (MatheusFS\Laravel\Insights\Helpers\IconGenerator) automatically
         generates PNG files for:
         - 10 colors: blue, red, orange, yellow, green, gray, purple, cyan, pink, teal
         - 7 types: dot, square, triangle, check, x, warning, info
         
         Usage in template: <img src="{{ $icons['color_type'] }}" width="11" height="11" />
         ============================================================================ --}}
    @php
        $icons = $icons ?? [];
        $iconSize = 11;
    @endphp
    
    {{-- ============================================================================
         HEADER: Logo + Título + Identificação Rápida
         ============================================================================ --}}
    <div class="header-section">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="width: 80px; vertical-align: top;">
                    @if(file_exists(public_path('images/logo.png')))
                        <img src="{{ public_path('images/logo.png') }}" alt="Logo" style="width: 70px; height: auto;" />
                    @else
                        <div style="width: 70px; height: 70px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">Logo</div>
                    @endif
                </td>
                <td style="padding-left: 15px; vertical-align: middle;">
                    <h1 style="margin: 0; font-size: 24px; font-weight: bold; color: #1976d2;">Comprovante de Incidente</h1>
                    <p style="margin: 3px 0; font-size: 11px; color: #666;">{{ $company ?? 'Continuo Tecnologia' }}</p>
                    <p style="margin: 3px 0; font-size: 10px; color: #999;">{{ $generated_at ?? 'Data não disponível' }}</p>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="background: {{ $incident['severity_color'] ?? '#f57c00' }}; color: white; padding: 10px 15px; border-radius: 4px; display: inline-block;">
                        <p style="margin: 0; font-size: 12px; font-weight: bold;">{{ $incident['environment'] ?? 'PROD' }}</p>
                        <p style="margin: 3px 0; font-size: 10px;">ID: {{ $incident['id'] }}</p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 1: IDENTIFICAÇÃO
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #1976d2;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #1976d2;">
            @if(!empty($icons['blue_info']))
                <img src="{{ $icons['blue_info'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Info" />
            @endif
            IDENTIFICAÇÃO
        </h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="padding: 6px; width: 25%;"><strong>ID do Incidente:</strong></td>
                <td style="padding: 6px; width: 25%;">{{ $incident['id'] }}</td>
                <td style="padding: 6px; width: 25%;"><strong>Status:</strong></td>
                <td style="padding: 6px; width: 25%;"><span style="background: {{ $incident['is_open'] ? '#ff9800' : '#4caf50' }}; color: white; padding: 2px 6px; border-radius: 3px;">{{ $incident['status_label'] }}</span></td>
            </tr>
            <tr>
                <td style="padding: 6px;"><strong>Ambiente:</strong></td>
                <td style="padding: 6px;">{{ $incident['environment'] }}</td>
                <td style="padding: 6px;"><strong>On-call:</strong></td>
                <td style="padding: 6px;">{{ $incident['oncall'] }}</td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 2: CLASSIFICAÇÃO
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #f57c00;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #f57c00;">
            @if(!empty($icons['orange_warning']))
                <img src="{{ $icons['orange_warning'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Warning" />
            @endif
            CLASSIFICAÇÃO
        </h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="padding: 6px; width: 25%;"><strong>Tipo de Erro:</strong></td>
                <td style="padding: 6px; width: 25%;">{{ $classification['error_type'] }}</td>
                <td style="padding: 6px; width: 25%;"><strong>Severidade:</strong></td>
                <td style="padding: 6px; width: 25%;"><span style="background: {{ $classification['severity_color'] }}; color: white; padding: 2px 6px; border-radius: 3px;">{{ $classification['severity'] }}</span></td>
            </tr>
            <tr>
                <td style="padding: 6px;"><strong>Nível de Severidade:</strong></td>
                <td style="padding: 6px;">{{ $classification['severity_level'] }}</td>
                <td style="padding: 6px;"><strong>Métrica:</strong></td>
                <td style="padding: 6px;">{{ $classification['metric_value'] }}{{ $classification['metric_unit'] }}</td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 3: IMPACTO
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #1976d2;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #1976d2;">
            @if(!empty($icons['blue_info']))
                <img src="{{ $icons['blue_info'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Info" />
            @endif
            IMPACTO
        </h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="padding: 6px; width: 25%;"><strong>Descrição:</strong></td>
                <td style="padding: 6px; width: 75%;" colspan="3">{{ $impact['description'] }}</td>
            </tr>
            <tr>
                <td style="padding: 6px;"><strong>Usuários Afetados:</strong></td>
                <td style="padding: 6px;">{{ $impact['users_affected'] }}</td>
                <td style="padding: 6px;"><strong>SLA Status:</strong></td>
                <td style="padding: 6px;"><span style="background: {{ $impact['sla_class'] === 'badge-critical' ? '#d32f2f' : '#388e3c' }}; color: white; padding: 2px 6px; border-radius: 3px;">{{ $impact['sla_status'] }}</span></td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 4: TIMELINE (SRE Metrics)
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #d32f2f;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #d32f2f;">
            @if(!empty($icons['red_dot']))
                <img src="{{ $icons['red_dot'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Timeline" />
            @endif
            TIMELINE DO INCIDENTE
        </h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="padding: 6px; width: 25%;"><strong>Iniciado em:</strong></td>
                <td style="padding: 6px; width: 25%;">{{ $timestamp['started_at'] }}</td>
                <td style="padding: 6px; width: 25%;"><strong>Detectado em:</strong></td>
                <td style="padding: 6px; width: 25%;">{{ $timestamp['detected_at'] }}</td>
            </tr>
            <tr>
                <td style="padding: 6px;"><strong>Classificado em:</strong></td>
                <td style="padding: 6px;">{{ $timestamp['classificated_at'] }}</td>
                <td style="padding: 6px;"><strong>Restaurado em:</strong></td>
                <td style="padding: 6px;">{{ $timestamp['restored_at'] }}</td>
            </tr>
            <tr>
                <td style="padding: 6px;"><strong>Encerrado em:</strong></td>
                <td style="padding: 6px;" colspan="3">{{ $timestamp['closed_at'] }}</td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 5: MÉTRICAS SRE (TTD, TTCY, TTRAD, TTR, TTC)
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #388e3c;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #388e3c;">
            @if(!empty($icons['green_check']))
                <img src="{{ $icons['green_check'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Metrics" />
            @endif
            MÉTRICAS SRE
        </h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="padding: 6px; width: 20%;"><strong>TTD</strong><br /><span style="color: #999; font-size: 9px;">Time to Detect</span></td>
                <td style="padding: 6px; width: 20%;"><strong>{{ $metrics['ttd'] ?? '—' }}</strong></td>
                <td style="padding: 6px; width: 20%;"><strong>TTCY</strong><br /><span style="color: #999; font-size: 9px;">Time to Classify</span></td>
                <td style="padding: 6px; width: 20%;"><strong>{{ $metrics['ttcy'] ?? '—' }}</strong></td>
                <td style="padding: 6px; width: 20%;"><strong>TTRAD</strong><br /><span style="color: #999; font-size: 9px;">Time to Restore Action</span></td>
                <td style="padding: 6px; width: 20%;"><strong>{{ $metrics['ttrad'] ?? '—' }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 6px;"><strong>TTR</strong><br /><span style="color: #999; font-size: 9px;">Time to Restore</span></td>
                <td style="padding: 6px;"><strong>{{ $metrics['ttr'] ?? '—' }}</strong></td>
                <td style="padding: 6px;"><strong>TTC</strong><br /><span style="color: #999; font-size: 9px;">Time to Close</span></td>
                <td style="padding: 6px;"><strong>{{ $metrics['ttc'] ?? '—' }}</strong></td>
                <td style="padding: 6px;"></td>
                <td style="padding: 6px;"></td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 6: CAUSA RAIZ
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #f57c00;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #f57c00;">
            @if(!empty($icons['orange_warning']))
                <img src="{{ $icons['orange_warning'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Root Cause" />
            @endif
            CAUSA RAIZ
        </h2>
        <p style="margin: 0; padding: 6px; font-size: 10px; line-height: 1.5;">{{ $root_cause }}</p>
    </div>

    {{-- ============================================================================
         SECTION 7: REMEDIAÇÃO
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #388e3c;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #388e3c;">
            @if(!empty($icons['green_check']))
                <img src="{{ $icons['green_check'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Remediation" />
            @endif
            REMEDIAÇÃO
        </h2>
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="padding: 6px; width: 20%; font-weight: bold; color: #d32f2f;">Imediata:</td>
                <td style="padding: 6px; width: 80%; background: #ffebee;">{{ $remediation['immediate'] }}</td>
            </tr>
            <tr>
                <td style="padding: 6px; font-weight: bold; color: #f57c00;">Curto Prazo:</td>
                <td style="padding: 6px; background: #fff3e0;">{{ $remediation['short_term'] }}</td>
            </tr>
            <tr>
                <td style="padding: 6px; font-weight: bold; color: #388e3c;">Longo Prazo:</td>
                <td style="padding: 6px; background: #e8f5e9;">{{ $remediation['long_term'] }}</td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 8: AÇÕES E ARTEFATOS
         ============================================================================ --}}
    @if(!empty($action_items) && is_array($action_items))
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #1976d2;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #1976d2;">
            @if(!empty($icons['blue_info']))
                <img src="{{ $icons['blue_info'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Actions" />
            @endif
            ITENS DE AÇÃO
        </h2>
        <ul style="margin: 0; padding: 6px 6px 6px 25px; font-size: 10px;">
            @foreach($action_items as $item)
            <li style="margin: 3px 0;">{{ $item }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ============================================================================
         FOOTER
         ============================================================================ --}}
    <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 9px; color: #999;">
        <p style="margin: 0;">Este documento foi gerado automaticamente pelo sistema de monitoramento de incidentes.</p>
        <p style="margin: 5px 0 0 0;">Para dúvidas, entre em contato com o time de SRE ou Operações.</p>
    </div>

@endsection
