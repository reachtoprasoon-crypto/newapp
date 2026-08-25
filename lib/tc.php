<?php
// Transfer Certificate (TC) issuance: moves a student from `students` to a
// `tcissued` archive table (a real DELETE, not the class="13Z" soft-delete
// used elsewhere — a TC means the student has genuinely left) and captures
// everything needed to reprint the certificate later. Ports
// issue-tc-flow.ts / get-issued-tcs-flow.ts.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity_log.php';
require_once __DIR__ . '/../vendor/autoload.php';

function ensure_tcissued_table($mysqli) {
    mysqli_query($mysqli, "
        CREATE TABLE IF NOT EXISTS tcissued (
            tcid INT AUTO_INCREMENT PRIMARY KEY,
            sid INT NOT NULL,
            schno INT NOT NULL,
            sname VARCHAR(255) NOT NULL,
            pname VARCHAR(255),
            mname VARCHAR(255),
            dob VARCHAR(50),
            sclass VARCHAR(50),
            branch VARCHAR(255),
            phone VARCHAR(50),
            hid INT,
            photo MEDIUMTEXT,
            tcr_no VARCHAR(100),
            sl_no INT,
            admitted_on VARCHAR(50),
            admitted_class VARCHAR(50),
            prev_school VARCHAR(255),
            left_on VARCHAR(50),
            character_cert VARCHAR(100),
            studying_class VARCHAR(50),
            board_stream VARCHAR(255),
            year_from VARCHAR(50),
            year_to VARCHAR(50),
            dob_words VARCHAR(255),
            promotion_status VARCHAR(255),
            issue_date VARCHAR(50),
            issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

function get_last_tc_serial_number($mysqli) {
    ensure_tcissued_table($mysqli);
    $row = db_fetch_one($mysqli, "SELECT MAX(sl_no) as lastNo FROM tcissued");
    return ($row && $row['lastNo'] !== null) ? (int) $row['lastNo'] : 0;
}

function get_issued_tcs($mysqli) {
    ensure_tcissued_table($mysqli);
    return db_fetch_all($mysqli, "SELECT * FROM tcissued ORDER BY issued_at DESC");
}

function get_issued_tc_by_id($mysqli, $tcid) {
    ensure_tcissued_table($mysqli);
    return db_fetch_one($mysqli, "SELECT * FROM tcissued WHERE tcid = ?", 'i', [$tcid]);
}

// $input keys: sid, tcr_no, sl_no, admitted_on, admitted_class, prev_school,
// left_on, character_cert, studying_class, board_stream, year_from, year_to,
// dob_words, promotion_status, issue_date.
// Irreversible: deletes the student from `students` once archived. Returns
// ['success'=>bool, 'error'=>?string, 'tc'=>?array] — 'tc' merges the input
// with the student snapshot, enough to render the certificate immediately
// without a second DB round trip.
function issue_tc($mysqli, $input, $actorName) {
    ensure_tcissued_table($mysqli);

    $student = db_fetch_one($mysqli, "SELECT * FROM students WHERE sid = ?", 'i', [$input['sid']]);
    if ($student === null) {
        return ['success' => false, 'error' => 'Student not found.'];
    }

    mysqli_begin_transaction($mysqli);
    try {
        $types = implode('', [
            'i', // sid
            'i', // schno
            's', // sname
            's', // pname
            's', // mname
            's', // dob
            's', // sclass
            's', // branch
            's', // phone
            'i', // hid
            's', // photo
            's', // tcr_no
            'i', // sl_no
            's', // admitted_on
            's', // admitted_class
            's', // prev_school
            's', // left_on
            's', // character_cert
            's', // studying_class
            's', // board_stream
            's', // year_from
            's', // year_to
            's', // dob_words
            's', // promotion_status
            's', // issue_date
        ]);
        $insert = db_execute(
            $mysqli,
            "INSERT INTO tcissued (
                sid, schno, sname, pname, mname, dob, sclass, branch, phone, hid, photo,
                tcr_no, sl_no, admitted_on, admitted_class, prev_school, left_on,
                character_cert, studying_class, board_stream, year_from, year_to,
                dob_words, promotion_status, issue_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            $types,
            [
                $student['sid'], $student['schno'], $student['sname'], $student['pname'], $student['mname'],
                $student['dob'], $student['sclass'], $student['branch'], $student['phone'], $student['hid'], $student['photo'],
                $input['tcr_no'], $input['sl_no'], $input['admitted_on'], $input['admitted_class'], $input['prev_school'], $input['left_on'],
                $input['character_cert'], $input['studying_class'], $input['board_stream'], $input['year_from'], $input['year_to'],
                $input['dob_words'], $input['promotion_status'], $input['issue_date'],
            ]
        );
        $tcid = $insert['insert_id'];

        db_execute($mysqli, "DELETE FROM students WHERE sid = ?", 'i', [$student['sid']]);

        mysqli_commit($mysqli);
    } catch (\Throwable $e) {
        mysqli_rollback($mysqli);
        return ['success' => false, 'error' => $e->getMessage()];
    }

    log_activity(
        $mysqli,
        $actorName,
        'Issue Transfer Certificate',
        "TC issued to {$student['sname']} (Sch No: {$student['schno']}). Student moved to archived records."
    );

    return ['success' => true, 'tc' => array_merge($input, [
        'tcid' => $tcid,
        'schno' => $student['schno'],
        'sname' => $student['sname'],
        'pname' => $student['pname'],
        'mname' => $student['mname'],
        'dob' => $student['dob'],
        'sclass' => $student['sclass'],
    ])];
}

// Ports issue-tc.tsx::handleDownloadDocx (built client-side there with the
// `docx` npm package; PHPWord here). $tc needs: schno, sname, pname, dob,
// tcr_no, sl_no, admitted_on, admitted_class, prev_school, left_on,
// character_cert, studying_class, board_stream, year_from, year_to,
// dob_words, promotion_status, issue_date. Returns a PhpWord instance.
function generate_tc_docx($tc) {
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();

    $centerP = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
    $bodyPlain = ['size' => 12];
    $bodyBold = ['bold' => true, 'size' => 12];
    $bodyValue = ['bold' => true, 'size' => 12, 'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_DOTTED];
    $lineSpacing = ['spaceBefore' => 120, 'spaceAfter' => 120];

    $logoPath = __DIR__ . '/../assets/images/logo.gif';
    if (is_file($logoPath)) {
        $section->addImage($logoPath, ['width' => 40, 'height' => 40, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    }

    $section->addText('Dr. Virendra Swarup Education Centre', ['bold' => true, 'size' => 22], $centerP);
    $section->addText('Avadhpuri, G.T. Road, Kanpur - 208 024', ['size' => 14], $centerP);
    $section->addText('Affiliated to C.I.S.C.E., New Delhi. Reg. No. UP094', ['size' => 12], $centerP);
    $section->addText(
        "SCHOLAR'S TRANSFER CERTIFICATE",
        ['bold' => true, 'size' => 16, 'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE],
        array_merge($centerP, ['spaceBefore' => 240, 'spaceAfter' => 240])
    );

    $run = $section->addTextRun();
    $run->addText('Scholar No. ' . $tc['schno'], $bodyBold);
    $run->addText("\t\t\t\t\t", $bodyPlain);
    $run->addText('TCR No. ' . $tc['tcr_no'], $bodyBold);

    $section->addTextRun()->addText('Sl. No. ' . $tc['sl_no'], $bodyBold);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('This is to certify that ', $bodyPlain);
    $run->addText(' ' . $tc['sname'] . ' ', $bodyValue);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('Son / Daughter of ', $bodyPlain);
    $run->addText(' Mr. ' . $tc['pname'] . ' ', $bodyValue);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('was admitted into this School on ', $bodyPlain);
    $run->addText(' ' . $tc['admitted_on'] . ' ', $bodyValue);
    $run->addText(' in Class ', $bodyPlain);
    $run->addText(' ' . $tc['admitted_class'] . ' ', $bodyValue);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('on a Transfer Certificate from ', $bodyPlain);
    $run->addText(' ' . $tc['prev_school'] . ' ', $bodyValue);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('and left on ', $bodyPlain);
    $run->addText(' ' . $tc['left_on'] . ' ', $bodyValue);
    $run->addText(' with a ', $bodyPlain);
    $run->addText(' ' . $tc['character_cert'] . ' ', $bodyValue);
    $run->addText(' character.', $bodyPlain);

    $run = $section->addTextRun(array_merge($centerP, ['spaceBefore' => 400]));
    $run->addText('He / She was then studying in the ', $bodyPlain);
    $run->addText(' ' . $tc['studying_class'] . ' ', $bodyValue);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('Class of the ', $bodyPlain);
    $run->addText(' ' . $tc['board_stream'] . ' ', $bodyValue);
    $run->addText(' stream. The School year being', $bodyPlain);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('from ', $bodyPlain);
    $run->addText(' ' . $tc['year_from'] . ' ', $bodyValue);
    $run->addText(' to ', $bodyPlain);
    $run->addText(' ' . $tc['year_to'] . ' ', $bodyValue);

    $section->addText(
        'All sums due to this School on his / her account have been remitted or satisfactorily arranged for.',
        $bodyPlain,
        ['spaceBefore' => 400]
    );

    $run = $section->addTextRun(['spaceBefore' => 400]);
    $run->addText("His / Her date of birth, according to our Admission Register is ", $bodyPlain);
    $run->addText(' ' . $tc['dob'] . ' ', $bodyValue);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('(in words) ', $bodyPlain);
    $run->addText(' ' . $tc['dob_words'] . ' ', $bodyValue);

    $run = $section->addTextRun($lineSpacing);
    $run->addText('Promotion has been ', $bodyPlain);
    $run->addText(' ' . $tc['promotion_status'] . ' ', $bodyValue);

    $section->addText('Date: ' . $tc['issue_date'], $bodyPlain, ['spaceBefore' => 800]);

    $run = $section->addTextRun(['spaceBefore' => 400]);
    $run->addText('____________________', $bodyPlain);
    $run->addText("\t\t\t\t\t", $bodyPlain);
    $run->addText('____________________', $bodyPlain);

    $run = $section->addTextRun();
    $run->addText('Prepared by', $bodyPlain);
    $run->addText("\t\t\t\t\t\t", $bodyPlain);
    $run->addText('Signature Principal', $bodyPlain);

    return $phpWord;
}

