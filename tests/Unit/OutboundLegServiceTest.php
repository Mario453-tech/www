<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/OutboundLegService.php';

final class OutboundLegServiceTest extends BaseTestCase
{
 /** @return array<string, array<string, float>> */
    private function config(): array
    {
        return [
            'ciezarowki' => ['cost_per_bbl' => 2.5, 'incident' => 1.3],
        ];
    }

    private function mults(): array
    {
        return ['loss_mult' => 1.0, 'global_loss' => 1.0, 'opex' => 1.0, 'transport_cost_mult' => 1.0];
    }

    public function testDirectWhenUnset(): void
    {
        $svc = new OutboundLegService($this->config());
        $res = $svc->compute('nieustawiony', null, 100.0, 70.0, $this->mults());

        $this->assertSame('direct', $res['kind']);
        $this->assertSame(0.0, $res['loss_bbl']);
        $this->assertSame(0.0, $res['cost']);
    }

    public function testPipelineAppliesLossAndCost(): void
    {
        $svc  = new OutboundLegService($this->config());
        $pipe = [
            '_is_operational' => true,
            'transport_loss'  => 5.0,    // 5%
            'opex_per_tick'   => 100.0,
            'opex_per_bbl'    => 0.25,
        ];
        $res = $svc->compute('rurociag', $pipe, 200.0, 70.0, $this->mults());

        $this->assertSame('pipeline', $res['kind']);
 // 5% of 200 = 10 bbl lost
        $this->assertEqualsWithDelta(10.0, $res['loss_bbl'], 0.001);
        $this->assertEqualsWithDelta(10.0 * 70.0, $res['loss_value'], 0.01);
 // cost = 100 + 200*0.25 = 150
        $this->assertEqualsWithDelta(150.0, $res['cost'], 0.01);
    }

    public function testPipelineNonOperationalIsDirect(): void
    {
        $svc  = new OutboundLegService($this->config());
        $pipe = ['_is_operational' => false, 'transport_loss' => 5.0, 'opex_per_tick' => 100.0];
        $res  = $svc->compute('rurociag', $pipe, 200.0, 70.0, $this->mults());

        $this->assertSame('direct', $res['kind']);
        $this->assertSame(0.0, $res['cost']);
    }

    public function testRoadChargesCostAndBoundedLoss(): void
    {
        $svc = new OutboundLegService($this->config());
        $res = $svc->compute('ciezarowki', null, 100.0, 70.0, $this->mults());

        $this->assertSame('road', $res['kind']);
 // cost = 100 * 2.5 = 250
        $this->assertEqualsWithDelta(250.0, $res['cost'], 0.01);
 // loss is a random incident, but always bounded to [0, bbl]
        $this->assertGreaterThanOrEqual(0.0, $res['loss_bbl']);
        $this->assertLessThanOrEqual(100.0, $res['loss_bbl']);
    }

    public function testZeroBarrelsIsNoop(): void
    {
        $svc = new OutboundLegService($this->config());
        $res = $svc->compute('rurociag', ['_is_operational' => true, 'transport_loss' => 5.0], 0.0, 70.0, $this->mults());

        $this->assertSame('direct', $res['kind']);
        $this->assertSame(0.0, $res['loss_bbl']);
        $this->assertSame(0.0, $res['cost']);
    }
}
