<?php
declare(strict_types=1);

// Full chat workspace embedded in the dashboard.
// Pelny obszar czatu osadzony w dashboardzie.
if (!is_array($dashboardChatViewData ?? null)):
?>
<section class="card chat-dashboard-error" aria-labelledby="chat-heading">
    <h2 id="chat-heading"><?= t('chat.page_title') ?></h2>
    <p><?= t('chat.load_error') ?></p>
</section>
<?php
else:
    (static function (array $viewData): void {
        require __DIR__ . '/../views/chat/main.php';
    })($dashboardChatViewData);
endif;
