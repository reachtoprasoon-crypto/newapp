$(function () {
    function loadTab(slug) {
        $('#tabContent').html('<div class="tab-pane-loading">Loading...</div>');
        $.get(BASE_URL + '/partials/load.php', { slug: slug })
            .done(function (html) {
                $('#tabContent').html(html);
                $(document).trigger('tabLoaded', [slug]);
            })
            .fail(function () {
                $('#tabContent').html('<div class="text-danger text-center py-5">Failed to load this tab.</div>');
            });
    }

    $('#dashboardTabs').on('click', 'a.nav-link', function (e) {
        e.preventDefault();
        $('#dashboardTabs a.nav-link').removeClass('active');
        $(this).addClass('active');
        loadTab($(this).data('slug'));

        // On mobile the nav is a collapsed menu — close it after picking a tab.
        const collapseEl = document.getElementById('dashboardNavCollapse');
        if (collapseEl && collapseEl.classList.contains('show')) {
            bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
        }
    });

    const firstTab = $('#dashboardTabs a.nav-link').first();
    if (firstTab.length) {
        loadTab(firstTab.data('slug'));
    }
});
