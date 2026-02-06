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
