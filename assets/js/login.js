$(function () {
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        ajaxCall({
            url: '/api/auth/login.php',
            data: { username: $('#username').val(), password: $('#password').val() },
        }).then(function (data) {
            if (data.student) {
                window.location.href = BASE_URL + '/student_dashboard.php';
            } else {
                window.location.href = BASE_URL + '/dashboard.php';
            }
        });
    });

    $('#forgotPasswordLink').on('click', function (e) {
        e.preventDefault();
        $('#fpStep1').show();
        $('#fpStep2, #fpStep3').hide();
        $('#fpUsername, #fpDob, #fpNewPassword').val('');
        $('#fpStep1Error, #fpStep2Error').text('');
        new bootstrap.Modal('#forgotPasswordModal').show();
    });

    let fpVerifiedUsername = null;

    $('#fpStep1Next').on('click', function () {
        const username = $('#fpUsername').val().trim();
        $('#fpStep1Error').text('');
        if (!username) {
            $('#fpStep1Error').text('Please enter your username.');
            return;
        }
        ajaxCall({ url: '/api/auth/forgot_password.php', data: { username: username }, silent: true })
            .then(function (data) {
                if (!data.resetAllowed) {
                    $('#fpStep1Error').text(data.error || 'Password reset is disabled by the administrator.');
                    return;
                }
                if (!data.found) {
                    $('#fpStep1Error').text(data.error || 'Username not found.');
                    return;
                }
                fpVerifiedUsername = username;
                $('#fpStep1').hide();
                $('#fpStep2').show();
            });
    });

    $('#fpStep2Submit').on('click', function () {
        const dob = $('#fpDob').val().trim();
        const newPassword = $('#fpNewPassword').val();
        $('#fpStep2Error').text('');
        if (!dob || !newPassword) {
            $('#fpStep2Error').text('Please fill in all fields.');
            return;
        }
        ajaxCall({
            url: '/api/auth/reset_password.php',
            data: { username: fpVerifiedUsername, dob: dob, newPassword: newPassword },
            silent: true,
        }).then(function () {
            $('#fpStep2').hide();
            $('#fpStep3').show();
        }, function (xhr) {
            $('#fpStep2Error').text((xhr.responseJSON && xhr.responseJSON.error) || 'Reset failed.');
        });
    });
});
