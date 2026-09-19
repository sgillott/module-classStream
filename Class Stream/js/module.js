// Class Stream module script. Core loads modules/<name>/js/module.js once per page load, and
// Gibbon swaps page content with htmx afterwards, so nothing here may depend on DOMContentLoaded:
// every listener is delegated from document and looks the elements up when the event fires.

// People page: the header "Mute" checkbox (#csMuteAll) ticks or unticks every student row
// (input.csMuteRow), and follows the rows when they are changed one by one.
(function () {
    var rows = function () {
        return Array.prototype.slice.call(document.querySelectorAll('input.csMuteRow'));
    };

    var syncMaster = function (master) {
        var all = rows();
        var ticked = all.filter(function (box) { return box.checked; }).length;
        master.checked = all.length > 0 && ticked === all.length;
        master.indeterminate = ticked > 0 && ticked < all.length;
    };

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || target.tagName !== 'INPUT') return;

        if (target.id === 'csMuteAll') {
            rows().forEach(function (box) { box.checked = target.checked; });
            target.indeterminate = false;
        } else if (target.classList.contains('csMuteRow')) {
            var master = document.getElementById('csMuteAll');
            if (master) syncMaster(master);
        } else if (target.id === 'csReuseAll') {
            // Reuse page: the header box selects or clears every post row.
            Array.prototype.forEach.call(document.querySelectorAll('input.csReuseRow'), function (box) { box.checked = target.checked; });
        }
    });
})();

// People page: click a heading with class cs-sort to sort the rows by that column. Each cell
// carries its sort value in data-sort (a number, or text). Click again to reverse.
(function () {
    document.addEventListener('click', function (event) {
        var th = event.target.closest && event.target.closest('th.cs-sort');
        if (!th) return;

        var table = th.closest('table');
        var headers = Array.prototype.slice.call(th.parentNode.children);
        var index = headers.indexOf(th);
        var numeric = th.getAttribute('data-type') === 'number';
        var ascending = th.getAttribute('data-dir') !== 'asc';

        headers.forEach(function (h) { h.removeAttribute('data-dir'); });
        th.setAttribute('data-dir', ascending ? 'asc' : 'desc');

        var rows = Array.prototype.slice.call(table.querySelectorAll('tr')).filter(function (r) { return !r.classList.contains('head'); });
        rows.sort(function (a, b) {
            var av = a.children[index].getAttribute('data-sort') || '';
            var bv = b.children[index].getAttribute('data-sort') || '';
            var cmp = numeric ? (parseFloat(av) - parseFloat(bv)) : av.localeCompare(bv, undefined, { sensitivity: 'base' });
            return ascending ? cmp : -cmp;
        });
        rows.forEach(function (r) { r.parentNode.appendChild(r); });
    });
})();
