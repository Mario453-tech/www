<?php extract($viewData, EXTR_SKIP); ?>

<h1> <?= t('admin.market_debug.heading') ?></h1>

<section aria-label="<?= t('admin.market_debug.market_state_aria') ?>">
    <div class="cards">
        <div class="card">
            <p class="label"><?= t('admin.market_debug.current_price') ?></p>
            <p class="value <?= $currentPrice > 120 ? 'green' : ($currentPrice < 70 ? 'red' : 'orange') ?>">
                $<?= number_format($currentPrice, 0) ?>
            </p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.market_debug.base_price') ?></p>
            <p class="value"><?= number_format($basePrice, 0) ?> $</p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.market_debug.price_deviation') ?></p>
            <?php $diff = $currentPrice - $basePrice; ?>
            <p class="value <?= $diff > 0 ? 'green' : 'red' ?>"><?= $diff > 0 ? '+' : '' ?><?= number_format($diff, 1) ?> $</p>
        </div>
        <div class="card">
            <p class="label"><?= t('admin.market_debug.active_trend') ?></p>
            <p class="value sm <?= $activeTrend ? 'orange' : '' ?>">
                <?= $activeTrend ? htmlspecialchars($activeTrend['trend_name']) : t('admin.market_debug.no_trend') ?>
            </p>
        </div>
        <?php if ($activeTrend): ?>
        <div class="card">
            <p class="label"><?= t('admin.market_debug.trend_modifier') ?></p>
            <p class="value <?= (float)$activeTrend['price_modifier'] >= 1.0 ? 'green' : 'red' ?>">
                ×<?= $activeTrend['price_modifier'] ?>
            </p>
        </div>
        <?php endif ?>
        <div class="card">
            <p class="label"><?= t('admin.market_debug.volatility') ?></p>
            <p class="value sm"><?= $market['volatility'] ?? '—' ?></p>
        </div>
    </div>
</section>

<div class="action-row">

<section class="panel" aria-label="<?= t('admin.market_debug.global_prod_aria') ?>">
    <p class="panel-title"> <?= t('admin.market_debug.global_prod_title') ?></p>
    <div class="detail-grid">
        <article>
            <p class="dl"><?= t('admin.market_debug.active_players') ?></p>
            <p class="dv"><?= (int)($prodGlobal['active_players'] ?? 0) ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.active_wells') ?></p>
            <p class="dv green"><?= (int)($prodGlobal['active_wells'] ?? 0) ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.total_base_prod') ?></p>
            <p class="dv orange"><?= number_format((float)($prodGlobal['total_base_prod'] ?? 0), 1) ?> bbl/h</p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.avg_condition') ?></p>
            <p class="dv <?= (float)($prodGlobal['avg_condition'] ?? 100) < 50 ? 'red' : 'green' ?>">
                <?= number_format((float)($prodGlobal['avg_condition'] ?? 100), 1) ?>%
            </p>
        </article>
    </div>
</section>

<section class="panel" aria-label="<?= t('admin.market_debug.storage_aria') ?>">
    <p class="panel-title"> <?= t('admin.market_debug.storage_title') ?></p>
    <div class="detail-grid">
        <article>
            <p class="dl"><?= t('admin.market_debug.total_capacity') ?></p>
            <p class="dv"><?= number_format((float)($storageGlobal['total_capacity'] ?? 0), 0) ?> bbl</p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.storage_used') ?></p>
            <?php $fillPct = $storageGlobal['total_capacity'] > 0
                ? round($storageGlobal['total_used'] / $storageGlobal['total_capacity'] * 100, 1) : 0; ?>
            <p class="dv <?= $fillPct > 85 ? 'red' : ($fillPct > 60 ? 'orange' : 'green') ?>">
                <?= number_format((float)($storageGlobal['total_used'] ?? 0), 0) ?> bbl (<?= $fillPct ?>%)
            </p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.pipeline_avg_loss') ?></p>
            <?php $avgLoss = (float)($pipelineLoss['avg_loss_pct'] ?? 0); ?>
            <p class="dv <?= $avgLoss > 15 ? 'red' : ($avgLoss > 5 ? 'orange' : 'green') ?>">
                <?= number_format($avgLoss, 2) ?>%
            </p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.pipeline_max_loss') ?></p>
            <?php $maxLoss = (float)($pipelineLoss['max_loss_pct'] ?? 0); ?>
            <p class="dv <?= $maxLoss > 20 ? 'red' : 'orange' ?>"><?= number_format($maxLoss, 2) ?>%</p>
        </article>
    </div>
</section>

</div>

<?php if ($demandData): ?>
<section class="panel">
    <p class="panel-title"> <?= t('admin.market_debug.demand_title') ?></p>
    <div class="detail-grid">
        <article>
            <p class="dl"><?= t('admin.market_debug.demand_index') ?></p>
            <p class="dv"><?= number_format((float)($demandData['demand_index'] ?? 1), 3) ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.world_production') ?></p>
            <p class="dv"><?= number_format((float)($demandData['world_production'] ?? 0), 0) ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.last_update') ?></p>
            <p class="dv sm"><?= $demandData['updated_at'] ?? '—' ?></p>
        </article>
    </div>
</section>
<?php endif ?>

<section class="panel">
    <p class="panel-title"> <?= t('admin.market_debug.transport_prod_title') ?></p>
    <?php if (empty($prodByTransport)): ?>
    <p class="empty-state"><?= t('admin.market_debug.no_active_wells') ?></p>
    <?php else: ?>
    <div class="detail-grid">
        <?php
        $tIcons = ['rurociag' => '', 'ciezarowki' => '', 'tankowiec' => ''];
        $tNames = [
            'rurociag'   => t('admin.market_debug.transport_pipeline'),
            'ciezarowki' => t('admin.market_debug.transport_trucks'),
            'tankowiec'  => t('admin.market_debug.transport_tanker'),
        ];
        foreach ($prodByTransport as $row):
            $t2 = $row['transport_type'];
            $baseProd       = (float)$row['base_prod_sum'];
            $capPct         = (float)$row['avg_capacity'];
            $transportedEst = $baseProd * ($capPct / 100);
        ?>
        <article>
            <p class="dl">
                <?= ($tIcons[$t2] ?? '?') . ' ' . ($tNames[$t2] ?? $t2) ?>
                (<?= (int)$row['wells'] ?> <?= t('admin.market_debug.wells_count') ?>)
            </p>
            <p class="dv orange"><?= number_format($baseProd, 1) ?> bbl/h <?= t('admin.market_debug.base') ?></p>
            <p class="detail-note-sm">
                cap: <?= round($capPct, 1) ?>% | est. transport: <?= number_format($transportedEst, 1) ?> bbl/h<br>
                opex: <?= round((float)$row['avg_opex'], 1) ?>% | cond: <?= round((float)$row['avg_condition'], 1) ?>%
            </p>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php if (!empty($priceHistory)): ?>
<section class="panel">
    <p class="panel-title"> <?= t('admin.market_debug.price_history_title', ['count' => count($priceHistory)]) ?></p>
    <div class="price-bar-wrap">
        <?php
        $prices = array_column($priceHistory, 'price');
        $minP   = min($prices); $maxP = max($prices);
        $rangeP = max(1, $maxP - $minP);
        foreach ($priceHistory as $ph):
            $pv       = (float)$ph['price'];
            $barH     = max(4, round(($pv - $minP) / $rangeP * 50));
            $barClass = $pv > $currentPrice ? 'price-bar--up' : 'price-bar--down';
        ?>
        <div class="price-bar <?= $barClass ?>" style="height:<?= $barH ?>px"
             title="$<?= round($pv,1) ?> | <?= $ph['recorded_at'] ?>"></div>
        <?php endforeach ?>
    </div>
    <p class="muted font-xs">
        <?= t('admin.market_debug.price_min') ?>: $<?= round($minP,1) ?> |
        <?= t('admin.market_debug.price_max') ?>: $<?= round($maxP,1) ?> |
        <?= t('admin.market_debug.price_current') ?>: $<?= round($currentPrice,1) ?>
    </p>
</section>
<?php endif ?>

<section class="panel">
    <p class="panel-title"> <?= t('admin.market_debug.player_economy_title') ?></p>
    <?php if (empty($playerEconomy)): ?>
    <p class="empty-state"><?= t('admin.market_debug.no_players') ?></p>
    <?php else: ?>
    <div class="list-header player-economy-grid">
        <span><?= t('admin.market_debug.col_player') ?></span>
        <span><?= t('admin.market_debug.col_cash') ?></span>
        <span><?= t('admin.market_debug.col_wells') ?></span>
        <span><?= t('admin.market_debug.col_prod_base') ?></span>
        <span><?= t('admin.market_debug.col_storage') ?></span>
        <span><?= t('admin.market_debug.col_avg_cap') ?></span>
        <span><?= t('admin.market_debug.col_status') ?></span>
    </div>
    <div class="data-list player-economy-grid">
    <?php foreach ($playerEconomy as $p):
        $storagePct = (float)$p['storage_capacity'] > 0
            ? round($p['storage_used'] / $p['storage_capacity'] * 100) : 0;
    ?>
    <article class="list-row">
        <span class="text-bold">
            <a href="/admin/player.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['login']) ?></a>
        </span>
        <span class="player-cash font-sm"><?= number_format((float)$p['cash'] / 1000, 0) ?>k</span>
        <span>
            <span class="text-green"><?= (int)$p['active_wells'] ?></span>
            <span class="muted">/<?= (int)$p['total_wells'] ?></span>
        </span>
        <span class="text-orange"><?= number_format((float)$p['base_prod_sum'], 1) ?></span>
        <span class="<?= $storagePct > 85 ? 'text-red' : '' ?>"><?= $storagePct ?>%</span>
        <span><?= round((float)$p['avg_transport_cap'], 0) ?>%</span>
        <span class="badge badge-<?= $p['status'] === 'active' ? 'active' : ($p['status'] === 'bankrupt' ? 'bankrupt' : 'paused') ?>"><?= $p['status'] ?></span>
    </article>
    <?php endforeach ?>
    </div>
    <?php endif ?>
</section>

<?php if (!empty($supplyDemandHistory)): ?>
<section class="panel">
    <p class="panel-title"> <?= t('admin.market_debug.supply_demand_title', ['count' => count($supplyDemandHistory)]) ?></p>
    <div class="list-header supply-demand-grid">
        <span><?= t('admin.market_debug.col_time') ?></span>
        <span><?= t('admin.market_debug.col_supply') ?></span>
        <span><?= t('admin.market_debug.col_demand') ?></span>
        <span><?= t('admin.market_debug.col_ratio') ?></span>
        <span><?= t('admin.market_debug.col_price') ?></span>
    </div>
    <div class="data-list supply-demand-grid">
    <?php foreach ($supplyDemandHistory as $row):
        $ratio    = (float)$row['ratio'];
        $ratioCol = $ratio > 1.1 ? 'text-red' : ($ratio < 0.9 ? 'text-green' : '');
    ?>
    <article class="list-row">
        <span class="muted font-sm"><?= $row['created_at'] ?></span>
        <span><?= number_format((float)$row['supply'], 1) ?></span>
        <span><?= number_format((float)$row['demand'], 1) ?></span>
        <span class="<?= $ratioCol ?>"><?= number_format($ratio, 3) ?></span>
        <span class="text-orange">$<?= (int)$row['price'] ?></span>
    </article>
    <?php endforeach ?>
    </div>
    <p class="muted font-xs mt-sm"><?= t('admin.market_debug.ratio_hint') ?></p>
</section>
<?php endif ?>

<section class="panel">
    <p class="panel-title"> <?= t('admin.market_debug.balance_title') ?></p>
    <?php
    $totalBaseProd = (float)($prodGlobal['total_base_prod'] ?? 0);
    $estEffProd    = $totalBaseProd * 0.85;
    $estTransport  = $estEffProd * (1 - ($pipelineLoss['avg_loss_pct'] ?? 0) / 100);
    $worldProd     = (float)($demandData['world_production'] ?? 0);
    $demand        = (float)($demandData['demand_index'] ?? 1) * 1000;
    ?>
    <div class="detail-grid">
        <article>
            <p class="dl"><?= t('admin.market_debug.est_eff_prod') ?></p>
            <p class="dv orange"><?= number_format($estEffProd, 1) ?> bbl/h</p>
            <p class="detail-note"><?= t('admin.market_debug.est_eff_prod_note') ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.est_transport') ?></p>
            <p class="dv"><?= number_format($estTransport, 1) ?> bbl/h</p>
            <p class="detail-note">
                <?= t('admin.market_debug.est_transport_note') ?> <?= number_format((float)($pipelineLoss['avg_loss_pct'] ?? 0), 2) ?>%
            </p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.world_prod_eco') ?></p>
            <p class="dv"><?= number_format($worldProd, 0) ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.market_debug.demand_x1000') ?></p>
            <p class="dv"><?= number_format($demand, 0) ?></p>
        </article>
    </div>
    <p class="panel-footer-note">
         <?= t('admin.market_debug.balance_note') ?>
        <a href="/admin/market.php"> <?= t('admin.market_debug.link_market') ?></a>
    </p>
</section>
