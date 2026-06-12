(function () {
    'use strict';

    var form = document.getElementById('players-bulk-delete-form');
    if (!form) {
        return;
    }

    var checkAll = document.getElementById('players-check-all');
    var selectAllBtn = document.getElementById('players-select-all');
    var unselectAllBtn = document.getElementById('players-unselect-all');
    var submitBtn = document.getElementById('players-bulk-delete-submit');
    var rowChecks = Array.prototype.slice.call(form.querySelectorAll('.players-row-check'));

    function selectedCount() {
        return rowChecks.filter(function (checkbox) {
            return checkbox.checked;
        }).length;
    }

    function setAll(checked) {
        rowChecks.forEach(function (checkbox) {
            checkbox.checked = checked;
        });
        updateState();
    }

    function updateState() {
        var count = selectedCount();
        if (submitBtn) {
            submitBtn.disabled = count === 0;
        }
        if (checkAll) {
            checkAll.checked = rowChecks.length > 0 && count === rowChecks.length;
            checkAll.indeterminate = count > 0 && count < rowChecks.length;
        }
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            setAll(checkAll.checked);
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            setAll(true);
        });
    }

    if (unselectAllBtn) {
        unselectAllBtn.addEventListener('click', function () {
            setAll(false);
        });
    }

    rowChecks.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateState);
    });

    form.addEventListener('submit', function (event) {
        if (form.dataset.confirmed === '1') {
            return;
        }

        if (selectedCount() === 0) {
            event.preventDefault();
            if (typeof alertError === 'function') {
                alertError(form.dataset.noSelection || '');
            }
            return;
        }

        event.preventDefault();
        if (typeof confirmSubmit === 'function') {
            confirmSubmit(form, form.dataset.confirm || '', {type: 'danger'});
        }
    });

    updateState();
})();
