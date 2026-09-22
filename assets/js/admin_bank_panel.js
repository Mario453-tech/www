(function () {
    'use strict';

    var modal   = document.getElementById('abp-modal');
    var form    = document.getElementById('abp-modal-form');
    var btnX    = document.getElementById('abp-modal-close');
    var btnCnl  = document.getElementById('abp-modal-cancel');
    var credit  = document.getElementById('abp-credit-btn');
    var debit   = document.getElementById('abp-debit-btn');
    var elAct   = document.getElementById('abp-modal-action');
    var elPid   = document.getElementById('abp-modal-pid');
    var elPlr   = document.getElementById('abp-modal-player-lbl');
    var elTitle = document.getElementById('abp-modal-title');
    var elSubmit= document.getElementById('abp-modal-submit');
    var elIcon  = document.getElementById('abp-modal-icon');

    if (!modal || !form) return;

    // SVG ikony bez emoji / SVG icons (no emoji)
    var svgPlus = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#4ec97a" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
    var svgMinus = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#e05555" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';

    function openModal(action, pid, email) {
        elAct.value = action;
        elPid.value = pid;
        if (elPlr)   elPlr.textContent = '#' + pid + ' ' + email;
        if (action === 'admin_credit') {
            if (elTitle)  elTitle.textContent = modal.dataset.credit;
            if (elSubmit) { elSubmit.textContent = modal.dataset.credit; elSubmit.className = 'btn btn-success'; }
            if (elIcon)   elIcon.innerHTML = svgPlus;
        } else {
            if (elTitle)  elTitle.textContent = modal.dataset.debit;
            if (elSubmit) { elSubmit.textContent = modal.dataset.debit; elSubmit.className = 'btn btn-danger'; }
            if (elIcon)   elIcon.innerHTML = svgMinus;
        }
        var fAmt  = document.getElementById('abp-modal-amount');
        var fNote = document.getElementById('abp-modal-note');
        if (fAmt)  fAmt.value  = '';
        if (fNote) fNote.value = '';
        modal.hidden = false;
        document.body.classList.add('abp-modal-open');
        setTimeout(function () { if (fAmt) fAmt.focus(); }, 40);
    }

    function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('abp-modal-open');
    }

    credit  && credit.addEventListener('click',  function () { openModal('admin_credit', this.dataset.playerId, this.dataset.playerEmail); });
    debit   && debit.addEventListener('click',   function () { openModal('admin_debit',  this.dataset.playerId, this.dataset.playerEmail); });
    btnX    && btnX.addEventListener('click',    closeModal);
    btnCnl  && btnCnl.addEventListener('click',  closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });
    document.getElementById('abp-player-select')?.addEventListener('change', function () {
        if (this.value) location.href = '/admin/bank.php?player_id=' + encodeURIComponent(this.value);
    });
})();
