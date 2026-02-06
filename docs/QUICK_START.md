# 🚀 QUICK START: Testar Sistema de Logs Contínuos

## 1️⃣ Verificar que Tudo Foi Instalado

```bash
# Entrar no container
docker exec -it core-fpm-1 bash

# Verificar se classe existe
php -r "require 'vendor/autoload.php'; echo class_exists('Matheusfs\LaravelInsights\Contracts\ALBLogDownloaderInterface') ? 'OK' : 'FALHA';"

# Esperado: OK
```

---

## 2️⃣ Rodar Comando de Download

```bash
# Download para ontem (2026-02-05)
php artisan alb:download-logs --date=2026-02-05

# Se quiser um período específico
php artisan alb:download-logs --month=2026-02

# Output esperado:
# ✅ Logs baixados com sucesso!
#    Data: 2026-02-05
#    Total de requisições: 107739
#      - API: 66005 (5xx: 1512)
#      - UI: 40434 (5xx: 1809)
#      - BOT: 13106 (5xx: 0)
#      - ASSETS: 1273 (5xx: 0)
```

---

## 3️⃣ Verificar Arquivos Criados

```bash
# Listar diretório de SRE metrics
ls -lh storage/app/sre_metrics/2026-02/

# Ver conteúdo do arquivo diário
cat storage/app/sre_metrics/2026-02/2026-02-05.json | jq '.by_request_type'

# Ver agregado mensal
cat storage/app/sre_metrics/2026-02/monthly_aggregate.json | jq '.'
```

---

## 4️⃣ Testar Endpoint API

```bash
# Terminal 1: Entrar no container
docker exec -it core-fpm-1 bash

# Terminal 1: Rodar para simular a resposta
php artisan alb:download-logs --month=2026-02

# Terminal 2 (seu terminal local):
curl -s "http://localhost:8000/api/insights/reliability/sre-metrics?month=2026-02" | jq '.'

# Esperado: JSON com dados de API e UI
```

**Output esperado:**
```json
{
  "success": true,
  "data": {
    "services": {
      "API": {
        "raw": {
          "total_requests": 66005,
          "total_5xx": 1512
        },
        "sli": {
          "value": 97.71,
          "unit": "%"
        },
        "slo": {
          "value": 98.5,
          "target": 98.5,
          "status": "BREACHED"
        },
        "sla": {
          "value": 95.0,
          "status": "OK"
        },
        "error_budget": {
          "total_percent": 5.0,
          "used_percent": 2.29,
          "remaining_percent": 2.71,
          "status": "AVAILABLE"
        }
      },
      "UI": { ... }
    }
  }
}
```

---

## 5️⃣ Testar Frontend

1. Abra navegador: `http://localhost:8000`
2. Vá para: **Reliability → Incidents**
3. Procure pela seção **"SRE Metrics"**
4. Verifique se exibe:
   - 🔌 API Service: 66,005 requisições • 1,512 erros 5xx
   - 🖥️ UI Service: 40,434 requisições • 1,809 erros 5xx

---

## 6️⃣ Verificar Agendamento

```bash
php artisan schedule:list

# Procure por:
# alb:download-logs    Daily at 00:30
```

---

## ⚙️ Configuração (Opcional)

Se quiser mudar para CloudWatch (produção), edite `config/insights.php`:

```php
'alb_source' => 'cloudwatch',  // em vez de 'local'
```

Ou via `.env`:
```
ALB_LOG_SOURCE=cloudwatch
```

---

## 🐛 Troubleshooting

### Erro: "JSON.parse: unexpected character"

**Causa:** Endpoint antigo ainda sendo chamado  
**Solução:** Use o novo endpoint:
```
# ❌ Antigo (pode não funcionar)
/api/insights/reliability/sre-metrics/monthly

# ✅ Novo (recomendado)
/api/insights/reliability/sre-metrics?month=2026-02
```

### Erro: "Class not found"

**Causa:** Package não foi instalado corretamente  
**Solução:**
```bash
cd core
composer update matheusfs/laravel-insights
php artisan cache:clear
```

### Comando não aparece no `php artisan`

**Causa:** ServiceProvider não foi registrado  
**Solução:**
```bash
# Verify ServiceProvider in config/app.php
php artisan config:cache
php artisan vendor:publish --provider="Matheusfs\LaravelInsights\ServiceProvider"
```

---

## ✅ Checklist de Conclusão

- [ ] Comando `alb:download-logs` executa sem erros
- [ ] Arquivos criados em `storage/app/sre_metrics/2026-02/`
- [ ] Endpoint `/api/insights/reliability/sre-metrics` retorna JSON válido
- [ ] Frontend exibe SRE Metrics Card com dados corretos
- [ ] Agendamento aparece em `php artisan schedule:list`
- [ ] Dados batem com esperados (API: 66005, UI: 40434)

---

## 📊 Próximos Testes

Após validar o básico:

1. **Testar com força refresh:**
   ```bash
   php artisan alb:download-logs --date=2026-02-05 --force
   ```

2. **Testar SLO/SLA customizados:**
   ```bash
   curl "http://localhost:8000/api/insights/reliability/sre-metrics?month=2026-02&slo_target=99&sla_target=98"
   ```

3. **Testar com dados históricos:**
   ```bash
   php artisan alb:download-logs --month=2026-01
   ```

---

**Sucesso?** ✅ Sistema está pronto para uso!  
**Problemas?** 🐛 Consulte [ALB_DOWNLOADER_GUIDE.md](../docs/ALB_DOWNLOADER_GUIDE.md)
