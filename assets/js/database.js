(function () {
    $('#btnDownloadBackup').on('click', function (e) {
        e.preventDefault();
        triggerDownload($(this).attr('href'), 15000);
    });

    function loadConfig() {
        ajaxCall({ url: '/api/database/config.php', method: 'GET', silent: true }).then(function (config) {
            $('#db_host').val(config.host);
            $('#db_user').val(config.user);
            $('#db_database').val(config.database);
            $('#db_password').val('');
        });
    }

    $('#dbConfigForm').on('submit', function (e) {
        e.preventDefault();

        Swal.fire({
            title: 'Change database connection?',
            html: 'This changes what database the ENTIRE application connects to.<br>The new settings will be tested before anything is saved, and a backup of the current configuration will be made automatically.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Test & Save',
            confirmButtonColor: '#dc3545',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            ajaxCall({
                url: '/api/database/update_config.php',
                data: {
                    host: $('#db_host').val().trim(),
                    user: $('#db_user').val().trim(),
                    password: $('#db_password').val(),
                    database: $('#db_database').val().trim(),
                },
                successMessage: 'Database configuration updated.',
            }).then(function () {
                $('#db_password').val('');
            });
        });
    });

    loadConfig();
})();
