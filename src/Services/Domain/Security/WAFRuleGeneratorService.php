<?php

namespace MatheusFS\Laravel\Insights\Services\Domain\Security;

/**
 * WAFRuleGeneratorService - Geração de regras AWS WAF
 *
 * Responsabilidade: Transformar classificação de IPs em regras WAF
 * Lógica de negócio pura
 */
class WAFRuleGeneratorService
{
    /**
     * Gera estrutura de IP Set para AWS WAF
     *
     * @param  array  $ips  Lista de IPs para bloquear
     * @param  string  $name  Nome do IP Set
     * @param  string  $description  Descrição do IP Set
     * @return array Estrutura para AWS CLI
     */
    public function generateIpSet(array $ips, string $name, string $description): array
    {
        $addresses = array_map(fn ($ip) => $ip.'/32', $ips);

        return [
            'Name' => $name,
            'Scope' => 'CLOUDFRONT',
            'IPAddressVersion' => 'IPV4',
            'Addresses' => $addresses,
            'Description' => $description,
        ];
    }

    /**
     * Gera regra de bloqueio para Web ACL
     *
     * @param  string  $ipSetId  ID do IP Set criado
     * @param  string  $ipSetArn  ARN do IP Set
     * @param  int  $priority  Prioridade da regra
     * @return array Estrutura de regra WAF
     */
    public function generateBlockRule(string $ipSetId, string $ipSetArn, int $priority = 1): array
    {
        return [
            'Name' => 'BlockMaliciousIPs',
            'Priority' => $priority,
            'Statement' => [
                'IPSetReferenceStatement' => [
                    'ARN' => $ipSetArn,
                ],
            ],
            'Action' => [
                'Block' => [],
            ],
            'VisibilityConfig' => [
                'SampledRequestsEnabled' => true,
                'CloudWatchMetricsEnabled' => true,
                'MetricName' => 'BlockMaliciousIPs',
            ],
        ];
    }

    /**
     * Gera regra de rate limiting por User-Agent
     *
     * @param  string  $userAgentPattern  Pattern de User-Agent (ex: "Googlebot")
     * @param  int  $rateLimit  Requests por 5 minutos
     * @param  int  $priority  Prioridade da regra
     * @return array Estrutura de regra WAF
     */
    public function generateRateLimitRule(
        string $userAgentPattern,
        int $rateLimit,
        int $priority = 2
    ): array {
        return [
            'Name' => 'RateLimit_'.preg_replace('/[^a-zA-Z0-9]/', '', $userAgentPattern),
            'Priority' => $priority,
            'Statement' => [
                'RateBasedStatement' => [
                    'Limit' => $rateLimit,
                    'AggregateKeyType' => 'IP',
                    'ScopeDownStatement' => [
                        'ByteMatchStatement' => [
                            'SearchString' => $userAgentPattern,
                            'FieldToMatch' => [
                                'SingleHeader' => [
                                    'Name' => 'user-agent',
                                ],
                            ],
                            'TextTransformations' => [
                                [
                                    'Priority' => 0,
                                    'Type' => 'LOWERCASE',
                                ],
                            ],
                            'PositionalConstraint' => 'CONTAINS',
                        ],
                    ],
                ],
            ],
            'Action' => [
                'Block' => [],
            ],
            'VisibilityConfig' => [
                'SampledRequestsEnabled' => true,
                'CloudWatchMetricsEnabled' => true,
                'MetricName' => 'RateLimit_'.preg_replace('/[^a-zA-Z0-9]/', '', $userAgentPattern),
            ],
        ];
    }

    /**
     * Gera configuração completa de blocklist/allowlist/watchlist
     *
     * @param  array  $classified  IPs classificados (malicious, suspicious, legitimate)
     * @param  string  $incidentId  ID do incidente para naming
     * @return array Estrutura com blocklist, allowlist, watchlist
     */
    public function generateCompleteRuleset(array $classified, string $incidentId): array
    {
        return [
            'blocklist' => [
                'ips' => array_column($classified['malicious'], 'ip'),
                'ip_set_name' => "incident-{$incidentId}-blocklist",
                'description' => "Auto-generated blocklist from {$incidentId}",
            ],
            'watchlist' => [
                'ips' => array_column($classified['suspicious'], 'ip'),
                'ip_set_name' => "incident-{$incidentId}-watchlist",
                'description' => "Suspicious IPs requiring monitoring from {$incidentId}",
            ],
            'allowlist' => [
                'ips' => $this->identifyLegitimateBotsIps($classified['legitimate']),
                'ip_set_name' => "incident-{$incidentId}-allowlist",
                'description' => "Legitimate bots and services from {$incidentId}",
            ],
        ];
    }

    /**
     * Identifica IPs de bots legítimos baseado em User-Agent
     */
    private function identifyLegitimateBotsIps(array $legitimateIps): array
    {
        $legitimateBotPatterns = [
            'Googlebot',
            'bingbot',
            'Yahoo! Slurp',
            'DuckDuckBot',
            'Baiduspider',
            'YandexBot',
            'facebookexternalhit',
            'LinkedInBot',
            'Twitterbot',
        ];

        // Por ora, retorna lista vazia
        // Em implementação real, correlacionaria com user-agents dos IPs
        return [];
    }
}
