<?php

namespace MatheusFS\Laravel\Insights\Tests\Unit\Services\Domain\Security;

use MatheusFS\Laravel\Insights\Services\Domain\Security\WAFRuleGeneratorService;
use PHPUnit\Framework\TestCase;

class WAFRuleGeneratorServiceTest extends TestCase
{
    private WAFRuleGeneratorService $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new WAFRuleGeneratorService;
    }

    public function test_generates_ip_set_with_correct_structure(): void
    {
        $ips = ['192.168.1.1', '192.168.1.2', '192.168.1.3'];
        $name = 'test-blocklist';
        $description = 'Test blocklist for incident';

        $result = $this->generator->generateIpSet($ips, $name, $description);

        $this->assertEquals($name, $result['Name']);
        $this->assertEquals('CLOUDFRONT', $result['Scope']);
        $this->assertEquals('IPV4', $result['IPAddressVersion']);
        $this->assertEquals($description, $result['Description']);
        $this->assertCount(3, $result['Addresses']);
        $this->assertEquals('192.168.1.1/32', $result['Addresses'][0]);
        $this->assertEquals('192.168.1.2/32', $result['Addresses'][1]);
        $this->assertEquals('192.168.1.3/32', $result['Addresses'][2]);
    }

    public function test_generates_empty_ip_set(): void
    {
        $result = $this->generator->generateIpSet([], 'empty-set', 'Empty set');

        $this->assertEmpty($result['Addresses']);
    }

    public function test_generates_block_rule_with_correct_structure(): void
    {
        $ipSetId = 'test-ip-set-id';
        $ipSetArn = 'arn:aws:wafv2:us-east-1:123456789:global/ipset/test/abc123';
        $priority = 5;

        $result = $this->generator->generateBlockRule($ipSetId, $ipSetArn, $priority);

        $this->assertEquals('BlockMaliciousIPs', $result['Name']);
        $this->assertEquals($priority, $result['Priority']);
        $this->assertArrayHasKey('IPSetReferenceStatement', $result['Statement']);
        $this->assertEquals($ipSetArn, $result['Statement']['IPSetReferenceStatement']['ARN']);
        $this->assertArrayHasKey('Block', $result['Action']);
        $this->assertTrue($result['VisibilityConfig']['SampledRequestsEnabled']);
        $this->assertTrue($result['VisibilityConfig']['CloudWatchMetricsEnabled']);
    }

    public function test_generates_rate_limit_rule_with_correct_structure(): void
    {
        $userAgent = 'Googlebot';
        $rateLimit = 2000;
        $priority = 10;

        $result = $this->generator->generateRateLimitRule($userAgent, $rateLimit, $priority);

        $this->assertEquals('RateLimit_Googlebot', $result['Name']);
        $this->assertEquals($priority, $result['Priority']);
        $this->assertArrayHasKey('RateBasedStatement', $result['Statement']);
        $this->assertEquals($rateLimit, $result['Statement']['RateBasedStatement']['Limit']);
        $this->assertEquals('IP', $result['Statement']['RateBasedStatement']['AggregateKeyType']);

        // Verificar ByteMatchStatement para User-Agent
        $scopeDown = $result['Statement']['RateBasedStatement']['ScopeDownStatement'];
        $this->assertArrayHasKey('ByteMatchStatement', $scopeDown);
        $this->assertEquals($userAgent, $scopeDown['ByteMatchStatement']['SearchString']);
        $this->assertEquals('user-agent', $scopeDown['ByteMatchStatement']['FieldToMatch']['SingleHeader']['Name']);
        $this->assertEquals('CONTAINS', $scopeDown['ByteMatchStatement']['PositionalConstraint']);
    }

    public function test_generates_rate_limit_rule_sanitizes_user_agent_in_name(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows; U)';
        $result = $this->generator->generateRateLimitRule($userAgent, 1000, 1);

        // Nome deve remover caracteres especiais
        $this->assertEquals('RateLimit_Mozilla50WindowsU', $result['Name']);
    }

    public function test_generates_complete_ruleset_with_all_categories(): void
    {
        $classified = [
            'malicious' => [
                ['ip' => '192.168.1.1', 'total_requests' => 1000, 'error_rate' => 95],
                ['ip' => '192.168.1.2', 'total_requests' => 800, 'error_rate' => 90],
            ],
            'suspicious' => [
                ['ip' => '192.168.1.10', 'total_requests' => 500, 'error_rate' => 60],
            ],
            'legitimate' => [
                ['ip' => '192.168.1.100', 'total_requests' => 100, 'error_rate' => 5],
            ],
        ];

        $incidentId = 'INC-2026-001';

        $result = $this->generator->generateCompleteRuleset($classified, $incidentId);

        // Verificar blocklist
        $this->assertArrayHasKey('blocklist', $result);
        $this->assertCount(2, $result['blocklist']['ips']);
        $this->assertEquals(['192.168.1.1', '192.168.1.2'], $result['blocklist']['ips']);
        $this->assertEquals("incident-{$incidentId}-blocklist", $result['blocklist']['ip_set_name']);

        // Verificar watchlist
        $this->assertArrayHasKey('watchlist', $result);
        $this->assertCount(1, $result['watchlist']['ips']);
        $this->assertEquals(['192.168.1.10'], $result['watchlist']['ips']);
        $this->assertEquals("incident-{$incidentId}-watchlist", $result['watchlist']['ip_set_name']);

        // Verificar allowlist
        $this->assertArrayHasKey('allowlist', $result);
        $this->assertEquals("incident-{$incidentId}-allowlist", $result['allowlist']['ip_set_name']);
    }

    public function test_generates_complete_ruleset_with_empty_categories(): void
    {
        $classified = [
            'malicious' => [],
            'suspicious' => [],
            'legitimate' => [],
        ];

        $result = $this->generator->generateCompleteRuleset($classified, 'INC-2026-002');

        $this->assertEmpty($result['blocklist']['ips']);
        $this->assertEmpty($result['watchlist']['ips']);
        $this->assertEmpty($result['allowlist']['ips']);
    }

    public function test_block_rule_defaults_to_priority_1(): void
    {
        $result = $this->generator->generateBlockRule('id', 'arn');

        $this->assertEquals(1, $result['Priority']);
    }

    public function test_rate_limit_rule_defaults_to_priority_2(): void
    {
        $result = $this->generator->generateRateLimitRule('Bot', 1000);

        $this->assertEquals(2, $result['Priority']);
    }

    public function test_rate_limit_rule_applies_lowercase_transformation(): void
    {
        $result = $this->generator->generateRateLimitRule('GoogleBot', 1000, 1);

        $transformations = $result['Statement']['RateBasedStatement']['ScopeDownStatement']['ByteMatchStatement']['TextTransformations'];

        $this->assertCount(1, $transformations);
        $this->assertEquals(0, $transformations[0]['Priority']);
        $this->assertEquals('LOWERCASE', $transformations[0]['Type']);
    }

    public function test_generates_multiple_ip_sets_for_different_purposes(): void
    {
        $maliciousIps = ['192.168.1.1', '192.168.1.2'];
        $suspiciousIps = ['192.168.1.10', '192.168.1.11', '192.168.1.12'];

        $blocklist = $this->generator->generateIpSet($maliciousIps, 'blocklist', 'Block these');
        $watchlist = $this->generator->generateIpSet($suspiciousIps, 'watchlist', 'Watch these');

        $this->assertCount(2, $blocklist['Addresses']);
        $this->assertCount(3, $watchlist['Addresses']);
        $this->assertEquals('blocklist', $blocklist['Name']);
        $this->assertEquals('watchlist', $watchlist['Name']);
    }

    public function test_block_rule_metric_name_matches_rule_name(): void
    {
        $result = $this->generator->generateBlockRule('id', 'arn', 1);

        $this->assertEquals(
            $result['Name'],
            $result['VisibilityConfig']['MetricName']
        );
    }

    public function test_rate_limit_rule_metric_name_matches_rule_name(): void
    {
        $result = $this->generator->generateRateLimitRule('TestBot', 1000, 1);

        $this->assertEquals(
            $result['Name'],
            $result['VisibilityConfig']['MetricName']
        );
    }
}
