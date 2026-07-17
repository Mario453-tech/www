    <section class="logistics-alerts">
        <?php foreach ($alerts as $alert): ?>
        <div class="logistics-alert logistics-alert--<?= htmlspecialchars($alert['type']) ?>">
            <?= htmlspecialchars($alert['text']) ?>
        </div>
        <?php endforeach ?>
    </section>
