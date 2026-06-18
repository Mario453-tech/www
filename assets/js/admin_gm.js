(function () {
    var form = document.querySelector('[data-gm-delete-form]');
    if (!form) return;

    var selectAll = document.getElementById('gm-select-all');
    var boxes     = form.querySelectorAll('input[name="player_ids[]"]');

    selectAll.addEventListener('change', function () {
        boxes.forEach(function (b) { b.checked = selectAll.checked; });
    });

    boxes.forEach(function (b) {
        b.addEventListener('change', function () {
            var allChecked = Array.from(boxes).every(function (x) { return x.checked; });
            var anyChecked = Array.from(boxes).some(function (x) { return x.checked; });
            selectAll.checked       = allChecked;
            selectAll.indeterminate = anyChecked && !allChecked;
        });
    });
})();
