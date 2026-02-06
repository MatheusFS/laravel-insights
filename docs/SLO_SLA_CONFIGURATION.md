# SLO/SLA Configuration Guide

## O Que Foi Adicionado

Agora há suporte a **diferentes SLO e SLA para API e UI**:

```php
// config/insights.php
'sre_targets' => [
    'API' => [
        'slo' => env('SRE_SLO_API', 98.5),   // Meta operacional
        'sla' => env('SRE_SLA_API', 95.0),   // Compromisso
    ],
    'UI' => [
        'slo' => env('SRE_SLO_UI', 98.0),    // Meta operacional
        'sla' => env('SRE_SLA_UI', 95.0),    // Compromisso
    ],
]
```

## Como Usar

### 1. No Controller (usando env ou config)

```php
// Opção 1: Via env direto
$api_slo = env('SRE_SLO_API', 98.5);
$api_sla = env('SRE_SLA_API', 95.0);

// Opção 2: Via config (recomendado)
$api_slo = config('insights.sre_targets.API.slo');    // 98.5
$api_sla = config('insights.sre_targets.API.sla');    // 95.0

$ui_slo = config('insights.sre_targets.UI.slo');      // 98.0
$ui_sla = config('insights.sre_targets.UI.sla');      // 95.0
```

### 2. Na API (com query params)

**Usar valores do config (padrão):**
```bash
GET /api/insights/reliability/sre-metrics?month=2026-02
```

**Override com query params:**
```bash
GET /api/insights/reliability/sre-metrics?month=2026-02&slo_target=99&sla_target=97
```

### 3. No .env

```bash
# Defaults (se não especificar, usa estes)
SRE_SLO_API=98.5
SRE_SLA_API=95.0
SRE_SLO_UI=98.0
SRE_SLA_UI=95.0
```

## Exemplo Real: Fevereiro 2026

### Dados Atuais
- **API**: 66,005 requisições • 1,512 erros 5xx • **SLI: 97.71%**
- **UI**: 40,434 requisições • 1,809 erros 5xx • **SLI: 95.53%**

### Análise com Config Padrão

```
API Service:
  SLI (atual):  97.71%
  SLO (meta):   98.5%  ❌ Não atingido (0.79% abaixo)
  SLA (contrato): 95.0% ✅ OK (2.71% acima)
  Error Budget: 5.0% - 2.29% remaining = 2.71% utilizado

UI Service:
  SLI (atual):  95.53%
  SLO (meta):   98.0%  ❌ Não atingido (2.47% abaixo)
  SLA (contrato): 95.0% ✅ OK (0.53% acima)
  Error Budget: 5.0% - 4.47% remaining = 0.53% utilizado
```

**Conclusão**: 
- API está abaixo de SLO (precisa melhorar confiabilidade)
- UI está no limite do SLA (muito próximo do breach)
- Ambos ainda cumprem contrato SLA

## Próximas Features Usando Estas Configs

1. **Alertas Automáticos**
   - Se SLI cair abaixo de SLO → aviso ao time
   - Se SLI cair abaixo de SLA → alerta crítico (SLA breach)

2. **Error Budget Tracking**
   - Dashboard mostrando quanto budget resta
   - Auto-pause de deploys se budget < 1%

3. **Recomendações Automáticas**
   - "UI está em risco de SLA breach - priorize confiabilidade"
   - "API recuperou bem - pode retomar velocidade"

4. **Histórico Comparativo**
   - "Comparado a janeiro, UI melhorou 2%"
   - "API piorou 1.5% vs. dezembro"

## Testing

```bash
# Ver configuração padrão
php artisan config:show insights.sre_targets

# Testar endpoint com defaults
curl "http://localhost:8000/api/insights/reliability/sre-metrics?month=2026-02" | jq '.data.services'

# Testar override (98% SLO para API)
curl "http://localhost:8000/api/insights/reliability/sre-metrics?month=2026-02&slo_target=98" | jq '.data.services.API.slo'
```
