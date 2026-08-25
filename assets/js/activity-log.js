(function () {
    function escapeHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function load() {
        ajaxCall({ url: '/api/activity-log/list.php', method: 'GET', silent: true })
            .then(function (rows) {
                const tbody = $('#al_body').empty();
                rows.forEach(function (r) {
                    const row = $('<tr>');
                    row.append($('<td>').addClass('text-nowrap small').text(r.timestamp));
                    row.append($('<td>').text(r.actor_name));
                    row.append($('<td>').text(r.action));
                    row.append($('<td>').addClass('small text-muted').html(escapeHtml(r.details)));
                    tbody.append(row);
                });
            });
    }

    $('#btnRefreshLog').on('click', load);
    load();
})();
