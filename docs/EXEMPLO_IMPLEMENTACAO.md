<?php

/**
 * EXEMPLO: Usando Imagens PNG em PDF com Laravel Insights
 * 
 * Demonstração end-to-end de como usar Logo, Emojis e Ícones
 * em templates de PDF com DOMPDF 3.1+
 */

// ============================================
// 1. SETUP INICIAL
// ============================================

// Baixar emojis (uma vez no setup)
// $ bash download_twemoji.sh

// Verificar que helpers estão disponíveis
// $ php artisan test ImageHelpersTest


// ============================================
// 2. USAR EM CONTROLLER (PHP)
// ============================================

namespace App\Http\Controllers;

use MatheusFS\Laravel\Insights\Helpers\LogoPath;
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;
use MatheusFS\Laravel\Insights\Helpers\IconGenerator;
use Barryvdh\DomPDF\Facade\Pdf;

class IncidentReportController extends Controller
{
    public function generatePdf($incidentId)
    {
        // Buscar dados
        $incident = Incident::findOrFail($incidentId);
        
        // Preparar dados para template
        $data = [
            // Logo (sempre disponível)
            'logo_uri' => LogoPath::getUri(),
            
            // Status emoji baseado em resolução
            'status_emoji' => $incident->is_resolved 
                ? EmojiPath::byName('check')
                : EmojiPath::byName('warning'),
            
            // Cores para indicadores
            'cpu_color' => $this->getColorForUsage($incident->cpu_usage),
            'memory_color' => $this->getColorForUsage($incident->memory_usage),
            
            // Todos os ícones disponíveis para uso
            'icon_red_dot' => IconGenerator::getIcon('dot', 'red'),
            'icon_green_check' => IconGenerator::getIcon('check', 'green'),
            'icon_yellow_warning' => IconGenerator::getIcon('warning', 'yellow'),
            
            // Dados do incidente
            'incident' => $incident,
        ];
        
        // Gerar PDF
        return Pdf::loadView('pdf.incident-report', $data)
            ->download("incident-{$incidentId}.pdf");
    }
    
    /**
     * Retorna cor baseada em percentual de uso
     */
    private function getColorForUsage(int $percentage): string
    {
        if ($percentage < 50) return 'green';
        if ($percentage < 70) return 'yellow';
        if ($percentage < 85) return 'orange';
        return 'red';
    }
}


// ============================================
// 3. USAR EM BLADE TEMPLATE (Recomendado)
// ============================================

/**
 * resources/views/pdf/incident-report.blade.php
 */

?>

@use('MatheusFS\Laravel\Insights\Helpers\LogoPath')
@use('MatheusFS\Laravel\Insights\Helpers\EmojiPath')
@use('MatheusFS\Laravel\Insights\Helpers\IconGenerator')

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório de Incidente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #ddd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header img {
            max-width: 150px;
            height: auto;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f5f5f5;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .status-badge img {
            width: 20px;
            height: 20px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
            padding-left: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        table th {
            background: #f5f5f5;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #ddd;
        }
        
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .metric-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .metric-icon {
            width: 16px;
            height: 16px;
        }
        
        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #e7f3ff;
            border: 1px solid #007bff;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .badge img {
            width: 12px;
            height: 12px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- HEADER COM LOGO -->
    <div class="header">
        @if(LogoPath::exists())
            <img 
                src="{{ LogoPath::getUri() }}" 
                alt="Continuo Tecnologia"
            />
        @endif
        <h1>Relatório de Incidente</h1>
        <p>Gerado em {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <!-- STATUS COM EMOJI -->
    <div class="status-badge">
        @if(LogoPath::exists() && $incident->is_resolved)
            <img src="{{ EmojiPath::byName('check') }}" alt="✓ Resolvido" />
            <strong>Status: Resolvido</strong>
        @else
            <img src="{{ EmojiPath::byName('warning') }}" alt="⚠ Pendente" />
            <strong>Status: Pendente</strong>
        @endif
    </div>

    <!-- INFORMAÇÕES BÁSICAS -->
    <div class="section">
        <h2>
            <img src="{{ IconGenerator::getIcon('info', 'blue') }}" class="metric-icon" />
            Informações do Incidente
        </h2>
        <table>
            <tr>
                <th>Campo</th>
                <th>Valor</th>
            </tr>
            <tr>
                <td>ID</td>
                <td>{{ $incident->id }}</td>
            </tr>
            <tr>
                <td>Título</td>
                <td>{{ $incident->title }}</td>
            </tr>
            <tr>
                <td>Data Criação</td>
                <td>{{ $incident->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td>Severidade</td>
                <td>{{ ucfirst($incident->severity) }}</td>
            </tr>
        </table>
    </div>

    <!-- MÉTRICAS COM ÍCONES COLORIDOS -->
    <div class="section">
        <h2>
            <img src="{{ IconGenerator::getIcon('chart-up', 'green') }}" class="metric-icon" />
            Métricas de Sistema
        </h2>
        
        <!-- CPU -->
        <div class="metric-row">
            <img 
                src="{{ IconGenerator::getIcon('dot', $cpuColor) }}" 
                alt="CPU Usage"
                class="metric-icon"
            />
            <span><strong>CPU:</strong> {{ $incident->cpu_usage }}%</span>
        </div>

        <!-- Memória -->
        <div class="metric-row">
            <img 
                src="{{ IconGenerator::getIcon('dot', $memoryColor) }}" 
                alt="Memory Usage"
                class="metric-icon"
            />
            <span><strong>Memória:</strong> {{ $incident->memory_usage }}%</span>
        </div>

        <!-- Disco -->
        <div class="metric-row">
            <img 
                src="{{ IconGenerator::getIcon('square', 'purple') }}" 
                alt="Disk Usage"
                class="metric-icon"
            />
            <span><strong>Disco:</strong> {{ $incident->disk_usage }}%</span>
        </div>
    </div>

    <!-- SINAIS DE ALERTA -->
    <div class="section">
        <h2>
            <img src="{{ EmojiPath::byName('alert') }}" style="width: 20px; height: 20px;" />
            Sinais Detectados
        </h2>
        <div class="badge-list">
            @if($incident->has_cpu_spike)
                <span class="badge">
                    <img src="{{ EmojiPath::byName('fire') }}" alt="🔥" />
                    CPU Spike
                </span>
            @endif
            
            @if($incident->has_memory_leak)
                <span class="badge">
                    <img src="{{ EmojiPath::byName('warning') }}" alt="⚠" />
                    Memory Leak
                </span>
            @endif
            
            @if($incident->has_disk_full)
                <span class="badge">
                    <img src="{{ EmojiPath::byName('fail') }}" alt="❌" />
                    Disco Cheio
                </span>
            @endif
            
            @if($incident->is_resolved)
                <span class="badge">
                    <img src="{{ EmojiPath::byName('perfect') }}" alt="💯" />
                    Resolvido
                </span>
            @endif
        </div>
    </div>

    <!-- DESCRIÇÃO -->
    <div class="section">
        <h2>Descrição Detalhada</h2>
        <p>{{ $incident->description }}</p>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <p>
            <img src="{{ EmojiPath::byName('check') }}" style="width: 12px; vertical-align: middle;" />
            Relatório gerado automaticamente pelo Laravel Insights
        </p>
    </div>
</body>
</html>


// ============================================
// 4. USANDO EMOJIS ESPECÍFICOS
// ============================================

/**
 * Emojis disponíveis por padrão (config/emojis.php):
 */

// Status/Validação
EmojiPath::byName('check')      // ✔️ Checkmark
EmojiPath::byName('fail')       // ❌ Cross
EmojiPath::byName('warning')    // ⚠️ Warning
EmojiPath::byName('info')       // ℹ️ Information

// Indicadores
EmojiPath::byName('fire')       // 🔥 Fire/Critical
EmojiPath::byName('clock')      // 🕐 Clock/Time
EmojiPath::byName('dot')        // 🔵 Blue Circle
EmojiPath::byName('star')       // ⭐ Star/Important

// Gestos
EmojiPath::byName('ok')         // 👌 OK
EmojiPath::byName('no')         // 👎 No
EmojiPath::byName('yes')        // 👍 Yes

// Especiais
EmojiPath::byName('alert')      // 🚨 Alert
EmojiPath::byName('speed')      // 💨 Speed
EmojiPath::byName('perfect')    // 💯 Perfect
EmojiPath::byName('check2')     // ✅ Check Mark Button


// ============================================
// 5. USANDO ÍCONES DINÂMICOS
// ============================================

// Todos os tipos
IconGenerator::getIcon('dot', 'red')        // 🔴
IconGenerator::getIcon('square', 'blue')    // 🟦
IconGenerator::getIcon('check', 'green')    // ✅
IconGenerator::getIcon('warning', 'yellow') // ⚠️
IconGenerator::getIcon('error', 'red')      // ❌
IconGenerator::getIcon('info', 'blue')      // ℹ️
IconGenerator::getIcon('alert', 'orange')   // 🚨

// 10 cores disponíveis
$colors = ['red', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink', 'gray', 'black', 'white'];

foreach ($colors as $color) {
    $icon = IconGenerator::getIcon('dot', $color);
    echo "<img src=\"{$icon}\" alt=\"{$color}\" />";
}


// ============================================
// 6. EXEMPLO DE TESTE
// ============================================

namespace Tests\Feature;

use MatheusFS\Laravel\Insights\Helpers\LogoPath;
use MatheusFS\Laravel\Insights\Helpers\EmojiPath;
use MatheusFS\Laravel\Insights\Helpers\IconGenerator;
use Tests\TestCase;

class IncidentPdfTest extends TestCase
{
    public function test_pdf_generates_with_images()
    {
        $incident = Incident::factory()->create();
        
        $response = $this->get("/incidents/{$incident->id}/pdf");
        
        $this->assertEquals(200, $response->status());
        // PDF deve conter imagens
    }
    
    public function test_logo_exists()
    {
        $this->assertTrue(LogoPath::exists());
    }
    
    public function test_emoji_check_available()
    {
        $uri = EmojiPath::byName('check');
        $this->assertNotNull($uri);
    }
    
    public function test_icon_generator_works()
    {
        $uri = IconGenerator::getIcon('dot', 'red');
        $this->assertStringStartsWith('file://', $uri);
    }
}


// ============================================
// 7. TROUBLESHOOTING RÁPIDO
// ============================================

// Logo não aparece?
dd(LogoPath::exists());      // true?
dd(LogoPath::getUri());      // file://...?
dd(LogoPath::getPath());     // /abs/path/...?

// Emoji não carregado?
dd(EmojiPath::common());     // Array com nomes?
dd(EmojiPath::byName('check')); // Path ou null?
ls -la resources/emojis/twemoji/ // Arquivos existem?

// Ícone não renderiza?
dd(IconGenerator::getIcon('dot', 'red')); // file://...?
ls -la storage/app/pdf-icons/ // Cache criado?


// ============================================
// 8. PRÓXIMOS PASSOS
// ============================================

/*
1. ✅ Executar: bash download_twemoji.sh
2. ✅ Verificar: php artisan test ImageHelpersTest
3. ✅ Usar template: copiar receipt_v2.blade.php
4. ✅ Gerar PDF: php artisan insights:generate-pdf
5. ✅ Validar: abrir PDF e verificar imagens
6. 📝 Customizar: adicionar mais emojis se necessário
*/

?>
