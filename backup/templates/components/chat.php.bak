<?php
//  Komponent: Czat globalny graczy
?>
<section class="card chat-box" aria-labelledby="chat-heading">
    <h2 id="chat-heading">Czat graczy</h2>
    <!-- Pasek przypiętych wiadomości admina — poza obszarem scrollowania -->
    <div class="chat-pinned-bar" id="chatPinnedBar" style="display:none"></div>
    <div class="chat-messages" id="chatMessages" role="log" aria-live="polite">
        <p class="chat-loading">Ładowanie wiadomości…</p>
    </div>
    <form class="chat-form" id="chatForm" autocomplete="off">
        <input type="text" id="chatInput" class="chat-input"
               placeholder="<?= t('dm.new_message_ph') ?>"
               maxlength="500" required
               aria-label="Wpisz wiadomość">
        <button type="submit" class="btn btn-primary chat-send"><?= t('dm.send_btn') ?></button>
    </form>
</section>

