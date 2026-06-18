<?php
$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$chatHeading = $locale === 'en' ? 'Players chat' : 'Czat graczy';
$chatLoading = $locale === 'en' ? 'Loading messages...' : 'Ladowanie wiadomosci...';
$chatInputAria = $locale === 'en' ? 'Type a message' : 'Wpisz wiadomosc';
?>
<section class="card chat-box" aria-labelledby="chat-heading">
    <h2 id="chat-heading"><?= htmlspecialchars($chatHeading, ENT_QUOTES, 'UTF-8') ?><span class="chat-online" id="chatOnline"></span></h2>
    <!-- Pinned admin messages live outside the scroll area. -->
    <div class="chat-pinned-bar" id="chatPinnedBar" style="display:none"></div>
    <div class="chat-messages" id="chatMessages" role="log" aria-live="polite">
        <p class="chat-loading"><?= htmlspecialchars($chatLoading, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <form class="chat-form" id="chatForm" autocomplete="off">
        <input type="text" id="chatInput" class="chat-input"
               placeholder="<?= t('dm.new_message_ph') ?>"
               maxlength="500" required
               aria-label="<?= htmlspecialchars($chatInputAria, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-primary chat-send"><?= t('dm.send_btn') ?></button>
    </form>
</section>
