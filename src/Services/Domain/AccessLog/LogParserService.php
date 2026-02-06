<?php

namespace MatheusFS\Laravel\Insights\Services\Domain\AccessLog;

/**
 * LogParserService - Parsing de logs ALB/NLB
 *
 * Responsabilidade: Transformar logs brutos em estruturas analisáveis
 * Lógica de negócio pura, sem I/O
 */
class LogParserService
{
    /**
     * Classifica tipo de request baseado no path
     */
    public function classifyRequestType(string $path): string
    {
        // API requests
        if (str_starts_with($path, '/api/')) {
            return 'API';
        }

        // Asset extensions
        $assetExtensions = [
            '.js', '.css', '.png', '.jpg', '.jpeg', '.gif', '.svg',
            '.woff', '.woff2', '.ttf', '.eot', '.ico', '.webp',
            '.map', '.json',
        ];

        foreach ($assetExtensions as $ext) {
            if (str_ends_with($path, $ext)) {
                return 'ASSETS';
            }
        }

        // Tudo resto é UI (HTML pages)
        return 'UI';
    }

    /**
     * Parse de linha de log ALB
     *
     * @param  string  $line  Linha bruta do log
     * @return array|null Array com campos extraídos ou null se inválido
     */
    public function parseLogLine(string $line): ?array
    {
        // ALB log format pattern
        // type timestamp elb client:port target:port request_processing_time target_processing_time response_processing_time
        // elb_status_code target_status_code received_bytes sent_bytes "request" "user_agent" ssl_cipher ssl_protocol
        // target_group_arn "trace_id" "domain_name" "chosen_cert_arn" matched_rule_priority request_creation_time
        // "actions_executed" "redirect_url" "error_reason"

        $pattern = '/^(\S+) (\S+) (\S+) (\S+):(\d+) (?:(\S+):(\d+)|-) (\S+) (-?\S+) (\S+) (\d+|-) (\d+|-) (\d+) (\d+) "([^"]*)" "([^"]*)" (\S+) (\S+)/';

        if (! preg_match($pattern, $line, $matches)) {
            return null;
        }

        // Extrair método, path e protocolo do request
        $request = $matches[15];
        $requestParts = explode(' ', $request);

        $method = $requestParts[0] ?? 'UNKNOWN';
        $path = $requestParts[1] ?? '/';
        $protocol = $requestParts[2] ?? 'HTTP/1.1';

        $clientIp = explode(':', $matches[4])[0];
        $elbStatusCode = (int) $matches[11];
        $targetStatusCode = (int) $matches[12];
        $userAgent = $matches[16];

        return [
            'timestamp' => $matches[2],
            'elb' => $matches[3],
            'client_ip' => $clientIp,
            'client_port' => (int) $matches[5],
            'target_ip' => (! empty($matches[6]) && $matches[6] !== '-') ? $matches[6] : null,
            'target_port' => (! empty($matches[7]) && $matches[7] !== '-') ? (int) $matches[7] : null,
            'request_processing_time' => (float) $matches[8],
            'target_processing_time' => $matches[9] === '-1' ? -1 : (float) $matches[9],
            'response_processing_time' => (float) $matches[10],
            'elb_status_code' => $elbStatusCode,
            'target_status_code' => $targetStatusCode,
            'received_bytes' => (int) $matches[13],
            'sent_bytes' => (int) $matches[14],
            'method' => $method,
            'path' => $path,
            'protocol' => $protocol,
            'user_agent' => $userAgent,
            'ssl_cipher' => $matches[17],
            'ssl_protocol' => $matches[18],
            'request_type' => $this->classifyRequestType($path),
        ];
    }

    /**
     * Expande linhas ALB concatenadas (múltiplas entradas em 1 linha física)
     *
     * ALB logs podem estar concatenados sem newlines separadores.
     * Este método detecta e divide by timestamp pattern (2025-10-21THOUR:MIN:SEC).
     *
     * @param  array  $lines  Array potencialmente com linhas concatenadas
     * @return array Array com linhas expandidas
     */
    public function expandConcatenatedLogLines(array $lines): array
    {
        $expanded = [];

        foreach ($lines as $line) {
            // Pattern: timestamp em ISO 8601 com Z suffix (ex: 2025-10-21T16:58:15.295691Z)
            // Se uma linha contém múltiplos timestamps, dividir por eles
            
            $parts = preg_split(
                '/(?=\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/',
                $line,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            if (count($parts) > 1) {
                // Linha tinha múltiplas entradas concatenadas
                $expanded = array_merge($expanded, $parts);
            } else {
                // Linha normal, adicionar como está
                $expanded[] = $line;
            }
        }

        return $expanded;
    }

    /**
     * Parse de múltiplas linhas de log
     *
     * @param  array  $lines  Array de linhas de log (potencialmente concatenadas)
     * @return array Array de records parseados
     */
    public function parseLogLines(array $lines): array
    {
        $records = [];

        // Primeiro, expandir linhas concatenadas
        $expandedLines = $this->expandConcatenatedLogLines($lines);

        foreach ($expandedLines as $line) {
            $parsed = $this->parseLogLine(trim($line));
            if ($parsed !== null) {
                $records[] = $parsed;
            }
        }

        return $records;
    }
}
