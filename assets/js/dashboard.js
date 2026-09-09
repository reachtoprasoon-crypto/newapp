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

    // Marks the given <a data-slug> active, plus its parent dropdown's
    // toggle (if any) so the top-level "Academics"/"Tools"/etc. link shows
    // which section the current tab lives under.
    function markActive($link) {
        $('#dashboardTabs a[data-slug], #dashboardTabs .dropdown-toggle').removeClass('active');
        $link.addClass('active');
        $link.closest('.dropdown').find('.dropdown-toggle').first().addClass('active');
    }

    // Selects both the flat top-level links (roles with few enough tabs to
    // skip grouping) and dropdown items — but not the dropdown toggles
    // themselves (they have no data-slug and just open the menu).
    $('#dashboardTabs').on('click', 'a[data-slug]', function (e) {
        e.preventDefault();
        markActive($(this));
        loadTab($(this).data('slug'));

        // On mobile the nav is a collapsed menu — close it after picking a tab.
        const collapseEl = document.getElementById('dashboardNavCollapse');
        if (collapseEl && collapseEl.classList.contains('show')) {
            bootstrap.Collapse.getOrCreateInstance(collapseEl).hide();
        }
    });

    const firstTab = $('#dashboardTabs a[data-slug]').first();
    if (firstTab.length) {
        markActive(firstTab);
        loadTab(firstTab.data('slug'));
    }
});
