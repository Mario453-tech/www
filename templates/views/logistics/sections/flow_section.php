    <!-- Oil flow section. -->
    <section class="logistics-flow-section">
        <div class="logistics-flow-header">
            <h3><?= t('logistics.flow_title') ?></h3>
            <span class="logistics-flow-sub"><?= t('logistics.flow_subtitle') ?></span>
        </div>
        <div class="logistics-flow-track">
            <!-- Odwierty -->
            <div class="logistics-flow-step">
                <div class="logistics-flow-icon logistics-flow-icon--wells">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="18" y="4" width="4" height="28" rx="2" fill="currentColor" opacity=".9"/>
                        <path d="M8 32 L20 4 L32 32" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                        <rect x="6" y="32" width="28" height="4" rx="2" fill="currentColor" opacity=".7"/>
                        <circle cx="20" cy="34" r="2" fill="currentColor"/>
                    </svg>
                </div>
                <div class="logistics-flow-label"><?= t('logistics.flow_step_wells') ?></div>
                <div class="logistics-flow-value c-good"><?= count($wells) ?> <?= t('logistics.flow_active') ?></div>
                <div class="logistics-flow-sub-val"><?= number_format($totalTransported + $totalLoss, 0, ',', ' ') ?> bbl/h</div>
            </div>

            <!-- Arrow 1 -->
            <div class="logistics-flow-arrow">
                <svg viewBox="0 0 48 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M0 8 H38 M32 2 L44 8 L32 14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?= number_format($totalTransported + $totalLoss, 0, ',', ' ') ?> bbl/h</span>
            </div>

            <!-- Transport 1 (wells -> hubs) -->
            <div class="logistics-flow-step">
                <div class="logistics-flow-icon logistics-flow-icon--transport">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="2" y="18" width="28" height="12" rx="3" fill="currentColor" opacity=".85"/>
                        <rect x="30" y="22" width="8" height="8" rx="2" fill="currentColor" opacity=".7"/>
                        <circle cx="9" cy="32" r="4" fill="#1a1a2e" stroke="currentColor" stroke-width="2"/>
                        <circle cx="27" cy="32" r="4" fill="#1a1a2e" stroke="currentColor" stroke-width="2"/>
                        <rect x="6" y="14" width="14" height="8" rx="2" fill="currentColor" opacity=".6"/>
                    </svg>
                </div>
                <div class="logistics-flow-label"><?= t('logistics.flow_step_transport') ?></div>
                <div class="logistics-flow-value <?= $totalLoss > 0 ? 'c-warn' : 'c-good' ?>"><?= $activeRoadTripsTotal ?> <?= t('logistics.flow_active') ?></div>
                <?php if ($totalLoss > 0): ?>
                <div class="logistics-flow-sub-val c-bad"><?= number_format($totalLoss, 1, ',', ' ') ?> bbl/h <?= t('logistics.flow_loss') ?></div>
                <?php else: ?>
                <div class="logistics-flow-sub-val c-good"><?= t('logistics.flow_no_loss') ?></div>
                <?php endif; ?>
            </div>

            <!-- Arrow 2 -->
            <div class="logistics-flow-arrow">
                <svg viewBox="0 0 48 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M0 8 H38 M32 2 L44 8 L32 14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?= number_format($efficiency, 1, ',', ' ') ?>% <?= t('logistics.flow_efficiency') ?></span>
            </div>

            <!-- Huby -->
            <div class="logistics-flow-step">
                <div class="logistics-flow-icon logistics-flow-icon--hubs">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="4" y="16" width="32" height="18" rx="3" fill="currentColor" opacity=".8"/>
                        <rect x="8" y="10" width="24" height="8" rx="2" fill="currentColor" opacity=".6"/>
                        <rect x="14" y="5" width="12" height="7" rx="2" fill="currentColor" opacity=".5"/>
                        <rect x="9" y="22" width="6" height="6" rx="1" fill="#1a1a2e" opacity=".7"/>
                        <rect x="17" y="22" width="6" height="6" rx="1" fill="#1a1a2e" opacity=".7"/>
                        <rect x="25" y="22" width="6" height="6" rx="1" fill="#1a1a2e" opacity=".7"/>
                    </svg>
                </div>
                <div class="logistics-flow-label"><?= t('logistics.flow_step_hubs') ?></div>
                <?php
                $hubTotal = count($hubCards);
                $hubActive = count(array_filter($hubCards, fn($h) => ($h['status'] ?? '') === 'active'));
                ?>
                <div class="logistics-flow-value <?= $hubActive > 0 ? 'c-good' : 'c-warn' ?>"><?= $hubActive ?>/<?= $hubTotal ?> <?= t('logistics.flow_in_use') ?></div>
                <div class="logistics-flow-sub-val"><?= number_format($efficiency, 1, ',', ' ') ?>% <?= t('logistics.kpi_efficiency') ?></div>
            </div>

            <!-- Arrow 3 -->
            <div class="logistics-flow-arrow">
                <svg viewBox="0 0 48 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M0 8 H38 M32 2 L44 8 L32 14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?= t('logistics.flow_step_transport') ?></span>
            </div>

            <!-- Transport 2 (hubs -> storage) -->
            <div class="logistics-flow-step">
                <div class="logistics-flow-icon logistics-flow-icon--transport2">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="20" cy="20" r="14" stroke="currentColor" stroke-width="2.5" fill="none" opacity=".5"/>
                        <path d="M8 20 Q14 10 20 20 Q26 30 32 20" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                        <circle cx="20" cy="20" r="3" fill="currentColor"/>
                        <path d="M20 6 L20 10 M20 30 L20 34 M6 20 L10 20 M30 20 L34 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".5"/>
                    </svg>
                </div>
                <div class="logistics-flow-label"><?= t('logistics.flow_step_transport2') ?></div>
                <div class="logistics-flow-value c-good"><?= (int)($pipelineSummary['total'] ?? 0) ?> <?= t('logistics.flow_pipelines') ?></div>
                <?php if ((int)($pipelineSummary['critical'] ?? 0) > 0): ?>
                <div class="logistics-flow-sub-val c-bad"><?= (int)$pipelineSummary['critical'] ?> <?= t('logistics.flow_critical') ?></div>
                <?php else: ?>
                <div class="logistics-flow-sub-val c-good"><?= t('logistics.flow_ok') ?></div>
                <?php endif; ?>
            </div>

            <!-- Arrow 4 -->
            <div class="logistics-flow-arrow">
                <svg viewBox="0 0 48 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M0 8 H38 M32 2 L44 8 L32 14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?= number_format($totalTransported, 0, ',', ' ') ?> bbl/h</span>
            </div>

            <!-- Magazyn -->
            <div class="logistics-flow-step logistics-flow-step--storage">
                <div class="logistics-flow-icon logistics-flow-icon--storage">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <ellipse cx="20" cy="12" rx="14" ry="5" fill="currentColor" opacity=".7"/>
                        <rect x="6" y="12" width="28" height="16" fill="currentColor" opacity=".6"/>
                        <ellipse cx="20" cy="28" rx="14" ry="5" fill="currentColor" opacity=".85"/>
                        <rect x="8" y="26" width="24" height="3" fill="currentColor" opacity=".3"/>
                    </svg>
                </div>
                <div class="logistics-flow-label"><?= t('logistics.flow_step_storage') ?></div>
                <div class="logistics-flow-value <?= $storagePct > 90 ? 'c-warn' : 'c-good' ?>"><?= number_format($storageBbl, 0, ',', ' ') ?> bbl</div>
                <div class="logistics-flow-sub-val"><?= $storagePct ?>% <?= t('logistics.flow_capacity') ?></div>
                <div class="logistics-flow-storage-bar" role="progressbar" aria-valuenow="<?= $storagePct ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="logistics-flow-storage-fill <?= $storagePct > 90 ? 'logistics-flow-storage-fill--warn' : '' ?>" data-progress-width="<?= $storagePct ?>"></div>
                </div>
            </div>
        </div>
    </section>
