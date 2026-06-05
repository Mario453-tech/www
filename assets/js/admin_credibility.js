/*
   Reczna korekta wiarygodnosci firmy — sterowanie modalem.
   Company credibility manual adjustment — modal control.

   Otwiera modal z formularzem korekty dla wybranego gracza, wypelnia
   ukryte pole player_id i nazwe. Zamkniecie: przycisk Anuluj, klik w tlo, Escape.
   Opens the adjustment modal for the chosen player, fills the hidden
   player_id and name. Close: Cancel button, backdrop click, Escape.
*/
(function () {
    'use strict';

    var overlay = document.getElementById('cred-adjust-overlay');
    if (!overlay) {
        return;
    }

    var playerIdInput = document.getElementById('cred-adjust-player-id');
    var playerLabel   = document.getElementById('cred-adjust-player');

    // Otwarcie modala dla danego gracza / Open the modal for a given player
    function openModal(playerId, playerName) {
        if (playerIdInput) {
            playerIdInput.value = playerId;
        }
        if (playerLabel) {
            playerLabel.textContent = playerName + ' (#' + playerId + ')';
        }
        overlay.hidden = false;
        document.body.classList.add('cred-modal-open');

        // Fokus na pierwsze pole liczbowe / Focus the first numeric field
        var firstInput = overlay.querySelector('input[name="delta"]');
        if (firstInput) {
            firstInput.focus();
        }
    }

    // Zamkniecie modala / Close the modal
    function closeModal() {
        overlay.hidden = true;
        document.body.classList.remove('cred-modal-open');
    }

    // Przyciski "Korekta" w tabeli / "Adjust" buttons in the table
    document.querySelectorAll('[data-cred-adjust]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-player-id'), btn.getAttribute('data-player-name') || '');
        });
    });

    // Anuluj / Cancel
    overlay.querySelectorAll('[data-cred-cancel]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    // Klik w tlo zamyka / Backdrop click closes
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    // Escape zamyka / Escape closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) {
            closeModal();
        }
    });
})();
