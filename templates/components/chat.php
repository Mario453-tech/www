<?php
declare(strict_types=1);

// Chat widget component for sidebar/dashboard.
// Komponent widgetu czatu dla paska bocznego / pulpitu.
?>
<section class="card chat-box" aria-labelledby="chat-heading">
    <h2 id="chat-heading">
        <?= htmlspecialchars(t('chat.page_title'), ENT_QUOTES, 'UTF-8') ?>
        <span class="chat-online" id="chatOnline"></span>
        <a href="/chat" class="chat-open-full-btn" title="<?= t('chat.page_title') ?>">&nearr;</a>
    </h2>
    <!-- Pinned admin messages live outside the scroll area. -->
    <!-- Przypiete wiadomosci admina znajduja sie poza obszarem przewijania. -->
    <div class="chat-pinned-bar" id="chatPinnedBar" hidden></div>
    <div class="chat-messages" id="chatMessages" role="log" aria-live="polite">
        <p class="chat-loading"><?= htmlspecialchars(t('chat.loading_messages'), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <form class="chat-form" id="chatForm" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" id="chatInput" class="chat-input"
               placeholder="<?= t('chat.placeholder_widget') ?>"
               maxlength="500" required
               aria-label="<?= htmlspecialchars(t('chat.input_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-primary chat-send"><?= t('chat.send') ?></button>
    </form>
</section>
