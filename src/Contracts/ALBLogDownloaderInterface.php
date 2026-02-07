<?php

namespace MatheusFS\Laravel\Insights\Contracts;

use Carbon\Carbon;

/**
 * Interface para Download de Logs ALB (Application Load Balancer)
 * 
 * Abstração para diferentes estratégias de obtenção de logs:
 * - Local storage (desenvolvimento)
 * - AWS CloudWatch (produção)
 * - Mock/Testing
 */
interface ALBLogDownloaderInterface
{
    /**
     * Retorna o tipo de fonte de logs que esta implementação usa
     * 
     * Valores possíveis: 's3', 'local', 'cloudwatch'
     * 
     * Use este método para validar em tempo de execução qual implementação está sendo usada.
     * Evita problemas com config keys incorretas na ServiceProvider.
     * 
     * @return string Tipo de fonte: 's3' | 'local' | 'cloudwatch'
     */
    public function getLogSource(): string;

    /**
     * Baixa logs ALB para um período específico
     * 
     * Estrutura retornada:
     * {
     *   "by_request_type": {
     *     "API": {"total_requests": 66005, "errors_5xx": 1512},
     *     "UI": {"total_requests": 40434, "errors_5xx": 1809},
     *     "BOT": {"total_requests": 13106, "errors_5xx": 0},
     *     "ASSETS": {"total_requests": 1273, "errors_5xx": 0}
     *   },
     *   "timestamp": "2026-02-06T00:00:00Z",
     *   "period": {"start": "2026-02-06T00:00:00Z", "end": "2026-02-06T23:59:59Z"}
     * }
     * 
     * @param Carbon $date Data do período (dia) a baixar
     * @param array $options Opções adicionais (filtros, fonte de dados, etc)
     * @return array Logs analisados por tipo de requisição
     */
    public function downloadForDate(Carbon $date, array $options = []): array;

    /**
     * Baixa logs para um período completo (ex: mês inteiro)
     * 
     * @param string $month Formato: 'Y-m' (ex: '2026-02')
     * @param array $options Opções adicionais
     * @return array Agregação mensal
     */
    public function downloadForMonth(string $month, array $options = []): array;

    /**
     * Baixa logs para um período customizado (data/hora início até fim)
     * 
     * Usa o diretório unificado de logs (access_logs_path).
     * Se há intersecção com períodos anteriores, reutiliza logs já baixados.
     * 
     * Fluxo:
     * 1. Itera por dias entre $start e $end
     * 2. Tenta usar dados já cacheados (sre_metrics_path)
     * 3. Se não houver, baixa logs do S3 e analisa
     * 4. Retorna dados agregados do período
     * 
     * @param Carbon $start Data/hora de início (qualquer timezone)
     * @param Carbon $end Data/hora de fim (qualquer timezone)
     * @param bool $force Forçar re-download mesmo com cache (padrão: false)
     * @return array Agregação do período customizado
     */
    public function downloadLogsForPeriod(Carbon $start, Carbon $end, bool $force = false): array;

    /**
     * Retorna caminho onde logs são armazenados
     * 
     * @return string Caminho base de armazenamento
     */
    public function getStoragePath(): string;

    /**
     * Verifica se dados de um período já foram baixados
     * 
     * @param Carbon $date Data a verificar
     * @return bool True se arquivo já existe
     */
    public function hasDataForDate(Carbon $date): bool;
}
