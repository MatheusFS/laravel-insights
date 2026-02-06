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
        ];
    }

    /**
     * Parse de múltiplas linhas de log
     *
     * @param  array  $lines  Array de linhas de log
     * @return array Array de registros parseados
     */
    public function parseLogLines(array $lines): array
    {
        $records = [];

        foreach ($lines as $line) {
            $parsed = $this->parseLogLine(trim($line));
            if ($parsed !== null) {
                $records[] = $parsed;
            }
        }

        return $records;
    }

    /**
     * Parse de arquivo de log ALB
     *
     * @param  string  $file_path  Caminho do arquivo .log
     * @return array Array de registros parseados
     */
    public function parseLogFile(string $file_path): array
    {
        if (! file_exists($file_path)) {
            \Log::warning("ALB log file not found: {$file_path}");
            return [];
        }

        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total_lines = count($lines);
        
        $records = $this->parseLogLines($lines);
        $parsed_count = count($records);

        \Log::info("Parsed ALB log file: {$file_path}", [
            'total_lines' => $total_lines,
            'parsed_count' => $parsed_count,
            'success_rate' => $total_lines > 0 ? round(($parsed_count / $total_lines) * 100, 2) . '%' : '0%',
        ]);

        return $records;
    }
}
