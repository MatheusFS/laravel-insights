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
     * 
     * Estrutura retornada (quando válida):
     * - 'timestamp' (string): ISO 8601 timestamp
     * - 'elb_status_code' (int): Código HTTP retornado pelo ELB (não usar para contar erros!)
     * - 'target_status_code' (int): Código HTTP do servidor backend (USE ESTE para detectar 5xx!)
     * - 'path' (string): URL path normalizado
     * - 'user_agent' (string): User-Agent header
     * - 'request_type' (string): 'API'|'UI'|'BOT'|'ASSETS'
     * - 'client_ip' (string): IP do cliente
     * - 'method' (string): HTTP method (GET, POST, etc)
     * - 'is_staging' (bool): Se a request foi para staging
     * - ... outros campos (ver código para lista completa)
     * 
     * @see ALBLogAnalyzer::processLogEntry() Consome este formato
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
        $rawPath = $requestParts[1] ?? '/';
        $path = $this->normalizePath($rawPath);
        $host = $this->extractHost($rawPath);
        $isStaging = $host !== null && str_contains($host, 'staging.refresher.com.br');
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
            'raw_path' => $rawPath,
            'host' => $host,
            'is_staging' => $isStaging,
            'protocol' => $protocol,
            'user_agent' => $userAgent,
            'ssl_cipher' => $matches[17],
            'ssl_protocol' => $matches[18],
            'request_type' => $this->classifyRequestType($path, $userAgent),
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
        \Log::debug("LogParserService::parseLogFile - START", [
            'file_path' => $file_path,
            'file_exists' => file_exists($file_path),
            'timestamp' => now()->toIso8601String(),
        ]);

        if (! file_exists($file_path)) {
            \Log::warning("ALB log file not found: {$file_path}");
            return [];
        }

        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            \Log::error("Failed to read file: {$file_path}");
            return [];
        }

        $total_lines = count($lines);
        
        \Log::debug("LogParserService::parseLogFile - FILE READ", [
            'file_path' => basename($file_path),
            'total_lines' => $total_lines,
        ]);
        
        $records = $this->parseLogLines($lines);
        $parsed_count = count($records);

        \Log::info("Parsed ALB log file: {$file_path}", [
            'filename' => basename($file_path),
            'total_lines' => $total_lines,
            'parsed_count' => $parsed_count,
            'success_rate' => $total_lines > 0 ? round(($parsed_count / $total_lines) * 100, 2) . '%' : '0%',
        ]);

        return $records;
    }

    /**
     * Classifica um request pela path e user-agent
     *
     * @param  string  $path  Path do request (ex: /api/briefings)
     * @param  string  $userAgent  User-Agent do request (opcional)
     * @return string Tipo de request: 'API', 'ASSETS', 'BOT' ou 'UI'
     */
    public function classifyRequestType(string $path, string $userAgent = ''): string
    {
        $normalizedPath = $this->normalizePath($path);

        // BOT detection (verifica user-agent) - PRIMEIRA PRIORIDADE
        if (!empty($userAgent) && $this->isBot($userAgent)) {
            return 'BOT';
        }

        // MALICIOUS detection (padrões de attack) - SEGUNDA PRIORIDADE
        if ($this->isMaliciousRequest($normalizedPath)) {
            return 'BOT';
        }

        // API request
        if (str_starts_with($normalizedPath, '/api/')) {
            return 'API';
        }

        // Assets by file extension (sufixos)
        $asset_extensions = [
            '.css', '.js', '.scss', '.less',
            '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.ico',
            '.woff', '.woff2', '.ttf', '.eot', '.otf',
            '.mp3', '.mp4', '.webm', '.ogg',
            '.pdf', '.zip', '.tar', '.gz',
        ];

        $path_lower = strtolower($normalizedPath);
        foreach ($asset_extensions as $ext) {
            if (str_ends_with($path_lower, $ext)) {
                return 'ASSETS';
            }
        }

        // Assets by directory patterns (prefixos)
        $asset_patterns = [
            '/assets/',
            '/css/',
            '/js/',
            '/images/',
            '/fonts/',
            '/favicon.ico',
            '/robots.txt',
            '/sitemap.xml',
        ];

        foreach ($asset_patterns as $pattern) {
            if (str_starts_with($normalizedPath, $pattern)) {
                return 'ASSETS';
            }
        }

        // UI requests (everything else)
        return 'UI';
    }

    /**
     * Normaliza path quando o log traz URL completa (https://dominio:443/api/...)
     */
    private function normalizePath(string $path): string
    {
        $trimmed = trim($path);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $parsedPath = parse_url($trimmed, PHP_URL_PATH);
            return $parsedPath ?: '/';
        }

        return $trimmed !== '' ? $trimmed : '/';
    }

    /**
     * Extrai host quando o path vem como URL completa
     */
    private function extractHost(string $path): ?string
    {
        $trimmed = trim($path);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            $host = parse_url($trimmed, PHP_URL_HOST);
            return $host ? strtolower($host) : null;
        }

        return null;
    }

    /**
     * Detecta se um request é de um BOT pela análise do user-agent
     *
     * @param  string  $userAgent  User-Agent do request
     * @return bool True se é bot, false caso contrário
     */
    private function isBot(string $userAgent): bool
    {
        $botPatterns = [
            // Search engines
            'googlebot' => 'Google',
            'bingbot' => 'Bing',
            'slurp' => 'Yahoo',
            'duckduckbot' => 'DuckDuckGo',
            'baiduspider' => 'Baidu',
            'yandexbot' => 'Yandex',
            'exabot' => 'Exalead',
            'facebookexternalhit' => 'Facebook',
            'twitterbot' => 'Twitter',
            'linkedinbot' => 'LinkedIn',
            'whatsapp' => 'WhatsApp',
            'telegrambot' => 'Telegram',
            
            // Monitoring & crawlers
            'monitoring' => 'Generic Monitoring',
            'uptimerobot' => 'UptimeRobot',
            'nagios' => 'Nagios',
            'check_http' => 'Icinga/Nagios',
            'pingdom' => 'Pingdom',
            'newrelic' => 'New Relic',
            'datadog' => 'Datadog',
            'elastic' => 'Elastic',
            'prometheus' => 'Prometheus',
            'crawler' => 'Generic Crawler',
            'spider' => 'Generic Spider',
            'bot' => 'Generic Bot',
            'scraper' => 'Scraper',
            
            // APIs & tools
            'curl' => 'cURL',
            'wget' => 'wget',
            'python' => 'Python',
            'java' => 'Java',
            'powershell' => 'PowerShell',
            'httpclient' => 'HTTP Client',
            'postman' => 'Postman',
            'insomnia' => 'Insomnia',
        ];

        $userAgent_lower = strtolower($userAgent);

        foreach ($botPatterns as $pattern => $botName) {
            if (strpos($userAgent_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identifica se é uma requisição maliciosa (scanner/bot/attack)
     *
     * @param  string  $path  Path da requisição
     * @return bool True se for malicioso
     */
    private function isMaliciousRequest(string $path): bool
    {
        $maliciousPatterns = [
            // WordPress exploits
            'wp-login.php',
            'xmlrpc.php',
            'wp-admin',
            'wp-content',
            'wp-includes',

            // Laravel exploits
            '_ignition/execute-solution',
            '_ignition/health-check',

            // Info disclosure
            'phpinfo.php',
            'info.php',
            '.env',
            '.git',
            '.svn',
            '.htaccess',

            // Common exploits
            'wpo.php',
            'shell.php',
            'c99.php',
            'r57.php',
            'adminer.php',
            'test.php',
            'php.php',
            'phpversion.php',
            'phpmyadmin',
            'pma',
            'enclas.php',
            'tgrs.php',
            'i.php',
            'error.php',
            'shellalfa.php',
            'admin.php',
            'about.php',
            'file.php',
            'dropdown.php',
            'autoload_classmap.php',
            'classwithtostring.php',
            'ioxi-o.php',
            'abcd.php',
            'css.php',
            'wp-good.php',
            '1.php',
            'cong.php',
            '222.php',
            'xx.php',
            'x.php',
            'inputs.php',
            'chosen.php',
            'flower.php',
            'adminfuns.php',
            'themes.php',
            'wp-l0gin.php',
            'alfa.php',
            'radio.php',
            'wp-trackback.php',
            'xmrlpc.php',
            'rip.php',
            'wp-plain.php',

            // Path traversal attempts
            '../',
            '..\\',
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (stripos($path, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
