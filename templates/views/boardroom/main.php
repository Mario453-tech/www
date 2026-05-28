<?php if ($headerSubtitle || count($readyRecruitments) > 0): ?>
<div class="br-shell-summary">
  <?php if ($headerSubtitle): ?><span><?= $headerSubtitle ?></span><?php endif ?>
  <span><?= t('boardroom.seats_label') ?> <strong><?= $occupiedSeats ?>/<?= $totalSeats ?></strong></span>
  <?php if (count($readyRecruitments) > 0): ?>
    <span><?= t('boardroom.cv_count', ['count' => count($readyRecruitments)]) ?></span>
  <?php endif ?>
</div>
<?php endif ?>

<div class="scene">
  <div class="scene-bg"></div>
  <div class="slots" id="slots"></div>

  <div class="legend">
    <div class="legend-item">
      <div class="legend-dot legend-dot-director"></div>
      <?= t('boardroom.legend_director') ?>
    </div>
    <div class="legend-item">
      <div class="legend-dot legend-dot-occupied"></div>
      <?= t('boardroom.legend_occupied') ?>
    </div>
    <div class="legend-item">
      <div class="legend-dot legend-dot-empty"></div>
      <?= t('boardroom.legend_empty') ?>
    </div>
  </div>

  <div class="seat-count">
    <div class="seat-count-num"><?= $totalSeats ?></div>
    <div class="seat-count-label"><?= t('boardroom.seat_count_label') ?></div>
  </div>
</div>

<script>
window.APP_LOCALE   = '<?= t('common.locale') ?>';
window.APP_CURRENCY = '<?= t('common.currency') ?>';
const phpData = {
    members: <?= json_encode($boardMembers) ?>,
    membersByRole: <?= json_encode($membersByRole) ?>,
    activeRecruitments: <?= json_encode($activeRecruitments) ?>,
    readyRecruitments: <?= json_encode($readyRecruitments) ?>,
    candidateCounts: <?= json_encode($candidateCounts) ?>,
    roleIdByCode: <?= json_encode($roleIdByCode ?? [], JSON_UNESCAPED_UNICODE) ?>,
    csrfToken: '<?= $csrfToken ?>'
};
window.BR_LANG = <?= json_encode($brLang, JSON_UNESCAPED_UNICODE) ?>;
window.REC_LANG = <?= json_encode($recLang, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('/assets/js/boardroom-dynamic.js') ?>"></script>
<script src="/assets/js/recruitment.js"></script>
