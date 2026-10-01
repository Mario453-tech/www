(function () {
    var form = document.querySelector('[data-gm-delete-form]');
    if (!form) return;

    var selectAll = document.getElementById('gm-select-all');
    var boxes     = form.querySelectorAll('input[type="checkbox"][name="player_ids[]"]');
    var pagination = form.querySelector('[data-gm-delete-pagination]');
    var selectedCount = form.querySelector('[data-gm-selected-count]');
    var carried = Array.from(form.querySelectorAll('[data-gm-carried-selection]'), function (input) { return input.value; });

    function updateSelection() {
        var selected = new Set(carried);
        boxes.forEach(function (box) {
            if (box.checked) selected.add(box.value);
        });

        if (selectAll) {
            var checkedCount = Array.from(boxes).filter(function (box) { return box.checked; }).length;
            selectAll.checked = boxes.length > 0 && checkedCount === boxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
        }

        if (selectedCount) {
            selectedCount.textContent = selectedCount.dataset.label.replace(':count', String(selected.size));
        }

        if (pagination) {
            pagination.querySelectorAll('a[href]').forEach(function (link) {
                var url = new URL(link.href, window.location.href);
                Array.from(url.searchParams.keys()).forEach(function (key) {
                    if (key === 'selected' || key.indexOf('selected[') === 0) url.searchParams.delete(key);
                });
                selected.forEach(function (id) { url.searchParams.append('selected[]', id); });
                link.href = url.toString();
            });
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            boxes.forEach(function (box) { box.checked = selectAll.checked; });
            updateSelection();
        });
    }

    boxes.forEach(function (b) {
        b.addEventListener('change', function () {
            updateSelection();
        });
    });

    updateSelection();

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!carried.length && !Array.from(boxes).some(function (box) { return box.checked; })) return;
        confirmAction(form.dataset.confirm, function () {
            HTMLFormElement.prototype.submit.call(form);
        }, {type: 'danger', title: form.dataset.confirmTitle, confirmLabel: form.dataset.confirmLabel});
    });
})();
