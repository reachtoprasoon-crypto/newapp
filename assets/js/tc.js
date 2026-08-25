(function () {
    let students = [];
    let selectedStudent = null;

    // Ports cardinalToWords/ordinalToWords/dateToWords from issue-tc.tsx —
    // pure client-side convenience for the "DOB in Words" auto-fill.
    function cardinalToWords(num) {
        const units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        if (num === 0) return 'Zero';
        if (num < 20) return units[num];
        if (num < 100) return tens[Math.floor(num / 10)] + (num % 10 !== 0 ? ' ' + units[num % 10] : '');
        if (num < 1000) return cardinalToWords(Math.floor(num / 100)) + ' Hundred' + (num % 100 !== 0 ? ' and ' + cardinalToWords(num % 100) : '');
        return cardinalToWords(Math.floor(num / 1000)) + ' Thousand' + (num % 1000 !== 0 ? ' ' + cardinalToWords(num % 1000) : '');
    }

    function ordinalToWords(num) {
        const ordinals = ['', 'First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth', 'Seventh', 'Eighth', 'Ninth', 'Tenth', 'Eleventh', 'Twelfth', 'Thirteenth', 'Fourteenth', 'Fifteenth', 'Sixteenth', 'Seventeenth', 'Eighteenth', 'Nineteenth', 'Twentieth', 'Twenty-First', 'Twenty-Second', 'Twenty-Third', 'Twenty-Fourth', 'Twenty-Fifth', 'Twenty-Sixth', 'Twenty-Seventh', 'Twenty-Eighth', 'Twenty-Ninth', 'Thirtieth', 'Thirty-First'];
        return ordinals[num] || '';
    }

    // dateStr is dd-mm-yyyy (this app's DOB display convention).
    function dateToWords(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length !== 3) return '';
        const day = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10);
        const year = parseInt(parts[2], 10);
        if (!day || !month || !year || day > 31 || month > 12) return '';
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return ordinalToWords(day) + ' of ' + months[month - 1] + ', ' + cardinalToWords(year);
    }

    function populateClasses() {
        const sel = $('#tc_class');
        window.APP_DATA.classes.forEach(function (c) {
            sel.append($('<option>').val(c).text('Class ' + c));
        });
    }

    function onClassChange() {
        const sclass = $('#tc_class').val();
        $('#tc_student').html('<option value="">Select student</option>').prop('disabled', true);
        $('#tc_formWrap').addClass('d-none');
        selectedStudent = null;
        students = [];
        if (!sclass) return;

        ajaxCall({ url: '/api/students/list.php', method: 'GET', data: { sclass: sclass }, silent: true })
            .then(function (data) {
                students = data;
                const sel = $('#tc_student').empty().append('<option value="">Select student</option>').prop('disabled', false);
                students.forEach(function (s) {
                    sel.append($('<option>').val(s.sid).text(s.sname + ' (Roll: ' + s.roll + ')'));
                });
            });
    }

    function onStudentChange() {
        const sid = parseInt($('#tc_student').val(), 10);
        selectedStudent = students.find(function (s) { return s.sid === sid; }) || null;
        if (!selectedStudent) {
            $('#tc_formWrap').addClass('d-none');
            return;
        }

        ajaxCall({ url: '/api/tc/last_serial.php', method: 'GET', silent: true }).then(function (data) {
            const year = new Date().getFullYear();
            $('#tc_tcr_no').val('3200/' + selectedStudent.schno + '/' + (year % 100) + '-' + ((year + 1) % 100));
            $('#tc_sl_no').val(data.lastSerial + 1);
            $('#tc_studying_class').val(selectedStudent.sclass);
            $('#tc_dob_words').val(dateToWords(selectedStudent.dob));
            $('#tc_dobDisplay').text(selectedStudent.dob || '');

            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const yyyy = today.getFullYear();
            $('#tc_issue_date, #tc_left_on').val(dd + '-' + mm + '-' + yyyy);
            $('#tc_year_from').val('01-04-' + (yyyy - 1));
            $('#tc_year_to').val('31-03-' + yyyy);

            $('#tc_formWrap').removeClass('d-none');
        });
    }

    function downloadTcDocx(tcid) {
        window.open(BASE_URL + '/api/tc/docx.php?tcid=' + tcid, '_blank');
    }

    function issueTC() {
        if (!selectedStudent) return;

        const required = {
            'TCR No.': $('#tc_tcr_no').val(),
            'Sl. No.': $('#tc_sl_no').val(),
            'Issue Date': $('#tc_issue_date').val(),
            'Admitted on': $('#tc_admitted_on').val(),
            'Admitted Class': $('#tc_admitted_class').val(),
            'Previous School': $('#tc_prev_school').val(),
            'Left on': $('#tc_left_on').val(),
            'Character': $('#tc_character_cert').val(),
            'Studying Class': $('#tc_studying_class').val(),
            'Board/Stream': $('#tc_board_stream').val(),
            'Year From': $('#tc_year_from').val(),
            'Year To': $('#tc_year_to').val(),
            'DOB in Words': $('#tc_dob_words').val(),
            'Promotion Status': $('#tc_promotion_status').val(),
        };
        for (const label in required) {
            if (!required[label]) {
                toastError(label + ' is required.');
                return;
            }
        }

        confirmDelete('This will permanently remove ' + selectedStudent.sname + ' from active records and cannot be undone.').then(function (confirmed) {
            if (!confirmed) return;

            ajaxCall({
                url: '/api/tc/issue.php',
                data: {
                    sid: selectedStudent.sid,
                    tcr_no: $('#tc_tcr_no').val(),
                    sl_no: $('#tc_sl_no').val(),
                    admitted_on: $('#tc_admitted_on').val(),
                    admitted_class: $('#tc_admitted_class').val(),
                    prev_school: $('#tc_prev_school').val(),
                    left_on: $('#tc_left_on').val(),
                    character_cert: $('#tc_character_cert').val(),
                    studying_class: $('#tc_studying_class').val(),
                    board_stream: $('#tc_board_stream').val(),
                    year_from: $('#tc_year_from').val(),
                    year_to: $('#tc_year_to').val(),
                    dob_words: $('#tc_dob_words').val(),
                    promotion_status: $('#tc_promotion_status').val(),
                    issue_date: $('#tc_issue_date').val(),
                },
                successMessage: 'TC issued and student archived.',
            }).then(function (tc) {
                downloadTcDocx(tc.tcid);
                onClassChange();
                loadHistory();
            });
        });
    }

    function loadHistory() {
        ajaxCall({ url: '/api/tc/list.php', method: 'GET', silent: true }).then(function (records) {
            const tbody = $('#tc_historyBody').empty();
            records.forEach(function (tc) {
                const row = $('<tr>');
                row.append($('<td>').text(tc.sl_no));
                row.append($('<td>').text(tc.schno));
                row.append($('<td>').text(tc.sname));
                row.append($('<td>').text(tc.sclass));
                row.append($('<td>').text(tc.issue_date));
                row.append($('<td>').html('<button class="btn btn-sm btn-outline-primary btn-tc-docx" data-tcid="' + tc.tcid + '"><i class="fa-solid fa-download me-1"></i>DOCX</button>'));
                tbody.append(row);
            });
        });
    }

    $('#tc_class').on('change', onClassChange);
    $('#tc_student').on('change', onStudentChange);
    $('#btnIssueTC').on('click', issueTC);
    $('#btnRefreshTCHistory').on('click', loadHistory);
    $('#tc_historyBody').on('click', '.btn-tc-docx', function () {
        downloadTcDocx($(this).data('tcid'));
    });

    populateClasses();
    loadHistory();
})();
