@extends('insights::pdf.layout_incident')

@section('title', 'Recibo de Incidente - ' . $incident['id'])

@section('content')
    {{-- ============================================================================
         IMAGENS PNG DO PROJETO
         
         LOGO: LogoPath helper
         - Localização: assets/logo_fundo_claro.png (para documentos)
         - Uso: LogoPath::getPdfUriLight() para PDFs, LogoPath::getUri() para web
         - Compatível com DOMPDF 3.x via base64 data URIs
         
         ÍCONES E EMOJIS: EmojiPath helper
         - Localização: public/emojis/{source}/{codepoint}.png (ex: public/emojis/twemoji/1f534.png)
         - Fontes: twemoji (MIT) ou noto (Apache 2.0)
         - Ícones coloridos mapeados como: blue_info, blue_dot, red_dot, orange_warning, green_check
         - Uso: $icons array passado pelo IncidentPdfGeneratorV2 (EmojiPath::getIconArray())
         - Exemplo emoji customizado: EmojiPath::getUri('1f600') para 😀
         ============================================================================ --}}
    @php
        use MatheusFS\Laravel\Insights\Helpers\LogoPath;
        use MatheusFS\Laravel\Insights\Helpers\EmojiPath;
        
        $icons = $icons ?? [];
        $iconSize = 14;
    @endphp
    
    {{-- ============================================================================
         HEADER: Logo + Título + Identificação Rápida
         ============================================================================ --}}
    <div class="header-section">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="width: 80px; vertical-align: top;">
                    {{-- ============================================================================
                         LOGO: Centralizado via LogoPath helper
                         - Arquivo: assets/logo_fundo_claro.png (para documentos)
                         - URI: base64 data URI (compatível DOMPDF 3.x)
                         ============================================================================ --}}
                    @php
                        $logoUri = LogoPath::exists() ? LogoPath::getPdfUri() : '';
                    @endphp
                    @if($logoUri)
                        <img src="{{ $logoUri }}" alt="Logo Continuo Tecnologia" style="width: 96px; height: auto;" />
                    @else
                        <div style="width: 96px; height: 96px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">CT</div>
                    @endif
                </td>
                <td style="padding-left: 15px; vertical-align: middle;">
                    <h1 style="margin: 0; font-size: 24px; font-weight: bold; color: #1976d2;">Recibo de Incidente</h1>
                    <span style="margin: 3px 0; font-size: 10px; color: #999;">{{ $generated_at ?? 'Data não disponível' }}</span>
                    <span style="background: {{ $incident['is_open'] ? '#ff9800' : '#4caf50' }}; color: white; padding: 2px 6px; border-radius: 3px;">{{ $incident['status_label'] }}</span>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="background: {{ $incident['severity_color'] ?? '#f57c00' }}; color: white; padding: 10px 15px; border-radius: 4px; display: inline-block;">
                        <p style="margin: 0; font-size: 12px; font-weight: bold;">{{ $incident['id'] }}</p>
                        <p style="margin: 3px 0; font-size: 10px;">{{ $incident['oncall'] }}</p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================================================
         SECTION 2: CLASSIFICAÇÃO
         ============================================================================ --}}
    <div class="section" style="margin-bottom: 20px; padding: 12px; background: #f9f9f9; border-left: 4px solid #f57c00;">
        <h2 style="margin: 0 0 10px 0; font-size: 13px; font-weight: bold; color: #f57c00;">
            @if(!empty($icons['26a0']))
                <img src="{{ $icons['26a0'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Warning" />
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
            @if(!empty($icons['2139']))
                <img src="{{ $icons['2139'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Info" />
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
            @if(!empty($icons['1f534']))
                <img src="{{ $icons['1f534'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Timeline" />
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
            @if(!empty($icons['2705']))
                <img src="{{ $icons['2705'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Metrics" />
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
            @if(!empty($icons['26a0']))
                <img src="{{ $icons['26a0'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Root Cause" />
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
            @if(!empty($icons['2705']))
                <img src="{{ $icons['2705'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Remediation" />
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
            @if(!empty($icons['2139']))
                <img src="{{ $icons['2139'] }}" width="{{ $iconSize }}" height="{{ $iconSize }}" style="vertical-align: -1px; margin-right: 6px;" alt="Actions" />
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

    {{-- ============================================================================
         EXEMPLO: Como usar EmojiPath para adicionar emojis customizados
         
         Os ícones do PDF ($icons) são gerados automaticamente via EmojiPath::getIconArray()
         Para adicionar emojis customizados além dos ícones padrão, descomente:
         
         <div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
             @php
                 $emojiAlert = EmojiPath::exists('26a0') ? EmojiPath::getUri('26a0') : '';
             @endphp
             @if($emojiAlert)
                 <img src="{{ $emojiAlert }}" width="24" height="24" style="vertical-align: middle; margin-right: 8px;" alt="Alert" />
             @endif
             <strong>Alerta:</strong> Emojis PNG podem ser adicionados via EmojiPath helper
         </div>
         
         Ícones padrão do PDF (mapeados via EmojiPath::getIconArray()):
         - blue_info = 2139 (ℹ️)
         - blue_dot = 1f535 (🔵)
         - red_dot = 1f534 (🔴)
         - orange_warning = 26a0 (⚠️)
         - green_check = 2705 (✅)
         
         Codepoints comuns para adicionar:
         - 1f4a9 = 💩 Error
         - 1f4a1 = 💡 Idea
         - 26a0 = ⚠️ Warning
         - 2705 = ✅ Check/Success
         - 274c = ❌ X/Error
         
         ============================================================================ --}}

@endsection
