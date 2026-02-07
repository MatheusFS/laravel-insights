<?php

namespace Tests\Unit\Support;

use MatheusFS\Laravel\Insights\Support\LargestRemainderDistributor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MatheusFS\Laravel\Insights\Support\LargestRemainderDistributor
 */
class LargestRemainderDistributorTest extends TestCase
{
    private LargestRemainderDistributor $distributor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->distributor = new LargestRemainderDistributor();
    }

    /** @test */
    public function it_distributes_exactly_with_two_proportions(): void
    {
        // Caso real: 1078 erros em 2 dias (31.7% e 68.2%)
        $result = $this->distributor->distribute(
            total: 1078,
            proportions: [
                'day1' => 0.3168208092485549, // 274/865
                'day2' => 0.6831791907514451, // 590/865
            ]
        );

        $this->assertEquals(342, $result['day1']);
        $this->assertEquals(736, $result['day2']);
        $this->assertEquals(1078, array_sum($result)); // ✅ EXATO
    }

    /** @test */
    public function it_distributes_exactly_when_difference_exceeds_keys(): void
    {
        // Caso onde diferença (4) > número de chaves (2)
        // BOT: 2597 erros em 2 dias
        $result = $this->distributor->distribute(
            total: 2597,
            proportions: [
                'day1' => 0.3168208092485549,
                'day2' => 0.6831791907514451,
            ]
        );

        // Verificar que soma é EXATA (distribuição específica pode variar)
        $this->assertEquals(2597, array_sum($result)); // ✅ EXATO
        $this->assertGreaterThan(0, $result['day1']);
        $this->assertGreaterThan(0, $result['day2']);
        // Proporções devem ser aproximadamente corretas
        $this->assertEqualsWithDelta(822.6, $result['day1'], 2); // ~31.68%
        $this->assertEqualsWithDelta(1774.4, $result['day2'], 2); // ~68.32%
    }

    /** @test */
    public function it_distributes_exactly_with_three_proportions(): void
    {
        $result = $this->distributor->distribute(
            total: 100,
            proportions: [
                'a' => 0.333,
                'b' => 0.333,
                'c' => 0.334,
            ]
        );

        $this->assertEquals(100, array_sum($result));
        // Proporções similares devem resultar em valores próximos
        $this->assertEqualsWithDelta(33, $result['a'], 1);
        $this->assertEqualsWithDelta(33, $result['b'], 1);
        $this->assertEqualsWithDelta(34, $result['c'], 1);
    }

    /** @test */
    public function it_distributes_small_totals_correctly(): void
    {
        // ASSETS: 4 erros em 2 dias
        $result = $this->distributor->distribute(
            total: 4,
            proportions: [
                'day1' => 0.3168208092485549,
                'day2' => 0.6831791907514451,
            ]
        );

        $this->assertEquals(1, $result['day1']);
        $this->assertEquals(3, $result['day2']);
        $this->assertEquals(4, array_sum($result)); // ✅ EXATO
    }

    /** @test */
    public function it_distributes_large_totals_correctly(): void
    {
        // API: 182,108 requests em 2 dias
        $result = $this->distributor->distribute(
            total: 182108,
            proportions: [
                'day1' => 0.3168208092485549,
                'day2' => 0.6831791907514451,
            ]
        );

        $this->assertEquals(182108, array_sum($result)); // ✅ EXATO
        $this->assertGreaterThan(0, $result['day1']);
        $this->assertGreaterThan(0, $result['day2']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_proportions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Proportions must sum to 1.0');

        $this->distributor->distribute(
            total: 100,
            proportions: [
                'a' => 0.5,
                'b' => 0.3, // Soma = 0.8 (inválido)
            ]
        );
    }

    /** @test */
    public function it_handles_zero_total_correctly(): void
    {
        $result = $this->distributor->distribute(
            total: 0,
            proportions: [
                'day1' => 0.5,
                'day2' => 0.5,
            ]
        );

        $this->assertEquals(0, $result['day1']);
        $this->assertEquals(0, $result['day2']);
        $this->assertEquals(0, array_sum($result));
    }

    /** @test */
    public function it_preserves_order_with_distributed_ordered(): void
    {
        $result = $this->distributor->distributeOrdered(
            total: 100,
            proportions: [
                'first' => 0.4,
                'second' => 0.3,
                'third' => 0.3,
            ]
        );

        // Verificar que ordem foi preservada
        $keys = array_keys($result);
        $this->assertEquals(['first', 'second', 'third'], $keys);
        $this->assertEquals(100, array_sum($result));
    }

    /** @test */
    public function it_distributes_batch_correctly(): void
    {
        // Distribuir múltiplas métricas simultaneamente
        $result = $this->distributor->distributeBatch(
            totals: [
                'total_requests' => 182108,
                'errors_5xx' => 1078,
                'errors_4xx' => 500,
            ],
            proportions: [
                'day1' => 0.3168208092485549,
                'day2' => 0.6831791907514451,
            ]
        );

        // Verificar estrutura
        $this->assertArrayHasKey('day1', $result);
        $this->assertArrayHasKey('day2', $result);
        $this->assertArrayHasKey('total_requests', $result['day1']);
        $this->assertArrayHasKey('errors_5xx', $result['day1']);
        $this->assertArrayHasKey('errors_4xx', $result['day1']);

        // Verificar somas exatas
        $this->assertEquals(182108, $result['day1']['total_requests'] + $result['day2']['total_requests']);
        $this->assertEquals(1078, $result['day1']['errors_5xx'] + $result['day2']['errors_5xx']);
        $this->assertEquals(500, $result['day1']['errors_4xx'] + $result['day2']['errors_4xx']);
    }

    /** @test */
    public function it_handles_edge_case_all_remainder_goes_to_one_key(): void
    {
        // Caso onde todos os restos vão para uma única chave
        $result = $this->distributor->distribute(
            total: 10,
            proportions: [
                'a' => 0.999,
                'b' => 0.001,
            ]
        );

        $this->assertEquals(10, $result['a']);
        $this->assertEquals(0, $result['b']);
        $this->assertEquals(10, array_sum($result));
    }

    /** @test */
    public function it_matches_real_incident_data(): void
    {
        // Validar contra dados reais do incidente INC-2026-001
        $day1_proportion = 274 / 865; // 31.68%
        $day2_proportion = 590 / 865; // 68.32%

        $api_result = $this->distributor->distribute(
            total: 1078,
            proportions: ['day1' => $day1_proportion, 'day2' => $day2_proportion]
        );
        $this->assertEquals(342, $api_result['day1']);
        $this->assertEquals(736, $api_result['day2']);
        $this->assertEquals(1078, array_sum($api_result));

        $ui_result = $this->distributor->distribute(
            total: 562,
            proportions: ['day1' => $day1_proportion, 'day2' => $day2_proportion]
        );
        $this->assertEquals(178, $ui_result['day1']);
        $this->assertEquals(384, $ui_result['day2']);
        $this->assertEquals(562, array_sum($ui_result));

        $bot_result = $this->distributor->distribute(
            total: 2597,
            proportions: ['day1' => $day1_proportion, 'day2' => $day2_proportion]
        );
        // Verificar soma exata (distribuição específica pode variar ligeiramente)
        $this->assertEquals(2597, array_sum($bot_result));
        $this->assertEqualsWithDelta(823, $bot_result['day1'], 1);
        $this->assertEqualsWithDelta(1774, $bot_result['day2'], 1);

        $assets_result = $this->distributor->distribute(
            total: 4,
            proportions: ['day1' => $day1_proportion, 'day2' => $day2_proportion]
        );
        $this->assertEquals(1, $assets_result['day1']);
        $this->assertEquals(3, $assets_result['day2']);
        $this->assertEquals(4, array_sum($assets_result));
    }
}
