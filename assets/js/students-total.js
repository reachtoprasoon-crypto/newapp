(function () {
    let allData = [];

    function render(data) {
        const tbody = $('#st_body').empty();
        data.forEach(function (s) {
            const row = $('<tr>');
            row.append($('<td>').text(s.schno));
            row.append($('<td>').text(s.roll));
            row.append($('<td>').text(s.sname));
            row.append($('<td>').text(s.sclass));
            row.append($('<td>').text(s.total_marks === null ? 'N/A' : s.total_marks));
            tbody.append(row);
        });
    }

    function applyFilter() {
        const q = $('#st_search').val().trim().toLowerCase();
        if (!q) {
            render(allData);
            return;
        }
        render(allData.filter(function (s) {
            return s.sname.toLowerCase().indexOf(q) !== -1 || String(s.schno).indexOf(q) !== -1 || s.sclass.toLowerCase().indexOf(q) !== -1;
        }));
    }

    function load() {
        ajaxCall({ url: '/api/reporting/students_total.php', method: 'GET', silent: true }).then(function (data) {
            allData = data;
            render(allData);
        });
    }

    $('#st_search').on('input', applyFilter);
    $('#btnExportStudentsTotal').on('click', function () {
        const q = $('#st_search').val().trim();
        window.location.href = BASE_URL + '/api/reporting/students_total_export.php' + (q ? '?q=' + encodeURIComponent(q) : '');
    });

    load();
})();
