<?php
// Doctor/save_report.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
ob_start();
require_once '../config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* TCPDF bootstrap */
if (!class_exists('TCPDF')) {
    $tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
    if (file_exists($tcpdfPath)) { require_once $tcpdfPath; }
    else { http_response_code(500); ob_end_clean(); exit('TCPDF library not found.'); }
}

/* Auth */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'doctor') {
    http_response_code(403); ob_end_clean(); exit('Unauthorized access.');
}

/* Inputs */
$doctor_id      = (int)$_SESSION['user_id'];
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$patient_id     = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$diagnosis      = trim($_POST['diagnosis'] ?? '');
$description    = trim($_POST['description'] ?? '');
$datetime       = date('Y-m-d H:i:s');

if (!$appointment_id || !$patient_id || $diagnosis === '' || $description === '') {
    $q = http_build_query(['appointment_id'=>$appointment_id ?: 0,'err'=>'Please complete all required fields.']);
    ob_end_clean();
    header("Location: write_report.php?{$q}");
    exit();
}

if (!defined('BASE_URL')) { define('BASE_URL','http://localhost/labs'); }

/* Posted med arrays */
$medicine_ids  = is_array($_POST['medicine_ids'] ?? null)  ? $_POST['medicine_ids']  : [];
$med_names     = is_array($_POST['med_names'] ?? null)     ? $_POST['med_names']     : [];
$med_forms     = is_array($_POST['med_forms'] ?? null)     ? $_POST['med_forms']     : [];
$med_strengths = is_array($_POST['med_strengths'] ?? null) ? $_POST['med_strengths'] : [];
$pills_per_day = is_array($_POST['pills_per_day'] ?? null) ? $_POST['pills_per_day'] : [];
$days          = is_array($_POST['days'] ?? null)          ? $_POST['days']          : [];

$medCount = min(count($medicine_ids), count($med_names), count($med_forms), count($med_strengths), count($pills_per_day), count($days));

/* Write to DB */
$conn->begin_transaction();

try {
    // 1) report
    $stmt = $conn->prepare("
        INSERT INTO medical_reports (appointment_id, patient_id, doctor_id, diagnosis, description, created_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiisss', $appointment_id, $patient_id, $doctor_id, $diagnosis, $description, $datetime);
    $stmt->execute();
    $report_id = $stmt->insert_id;
    $stmt->close();

    // 2) prescriptions (and capture for PDF)
    $medicineDetails = [];
    if ($medCount > 0) {
        $stmtMed = $conn->prepare("
            INSERT INTO prescribed_medicines (report_id, medicine_id, pills_per_day, duration_days, doctor_id, patient_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtLookup = $conn->prepare("
            SELECT name, COALESCE(dosage_form,'') AS dosage_form, COALESCE(strength,'') AS strength
            FROM medicines WHERE id = ?
        ");

        for ($i=0; $i<$medCount; $i++) {
            $mid = (int)$medicine_ids[$i];
            $ppd = (int)$pills_per_day[$i];
            $dur = (int)$days[$i];

            $nm  = trim($med_names[$i] ?? '');
            $frm = trim($med_forms[$i] ?? '');
            $str = trim($med_strengths[$i] ?? '');

            if ($mid > 0 && ($nm==='' || $frm==='' || $str==='')) {
                $stmtLookup->bind_param('i', $mid);
                $stmtLookup->execute();
                if ($row = $stmtLookup->get_result()->fetch_assoc()) {
                    if ($nm==='')  $nm  = (string)$row['name'];
                    if ($frm==='') $frm = (string)$row['dosage_form'];
                    if ($str==='') $str = (string)$row['strength'];
                }
            }

            if ($mid>0 && $ppd>0 && $dur>0) {
                $stmtMed->bind_param('iiiiii', $report_id, $mid, $ppd, $dur, $doctor_id, $patient_id);
                $stmtMed->execute();

                $medicineDetails[] = [
                    'name'     => $nm ?: 'Unknown',
                    'form'     => $frm ?: '—',
                    'strength' => $str ?: '—',
                    'ppd'      => $ppd,
                    'days'     => $dur,
                ];
            }
        }
        $stmtLookup->close();
        $stmtMed->close();
    }

    // 3) patient info (for PDF + email)
    $stmtP = $conn->prepare("
        SELECT email, name, user_id, gender, date_of_birth, height_cm, weight_kg, bmi, location
        FROM users WHERE id = ?
    ");
    $stmtP->bind_param('i', $patient_id);
    $stmtP->execute();
    $pat = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();

    $patient_email    = $pat['email'] ?? '';
    $patient_name     = $pat['name'] ?? '';
    $patient_user_id  = (string)($pat['user_id'] ?? '');
    $p_gender         = (string)($pat['gender'] ?? '');
    $p_dob            = (string)($pat['date_of_birth'] ?? '');
    $p_height         = (string)($pat['height_cm'] ?? '');
    $p_weight         = (string)($pat['weight_kg'] ?? '');
    $p_bmi            = (string)($pat['bmi'] ?? '');
    $p_location       = (string)($pat['location'] ?? '');

    // 4) doctor info (public id + license + specialization)
    $stmtD = $conn->prepare("
        SELECT u.name, u.user_id, u.license_number, COALESCE(s.name,'') AS spec_name
        FROM users u
        LEFT JOIN specializations s ON s.id = u.specialization_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmtD->bind_param('i', $doctor_id);
    $stmtD->execute();
    $doc = $stmtD->get_result()->fetch_assoc();
    $stmtD->close();

    $doctor_name      = $doc['name'] ?? '';
    $doctor_public_id = (string)($doc['user_id'] ?? '');
    $doctor_license   = (string)($doc['license_number'] ?? '');
    $doctor_spec      = (string)($doc['spec_name'] ?? 'General');

    // 5) appointment time
    $stmtA = $conn->prepare("SELECT appointment_datetime FROM appointments WHERE id = ? LIMIT 1");
    $stmtA->bind_param('i', $appointment_id);
    $stmtA->execute();
    $ar = $stmtA->get_result()->fetch_assoc();
    $stmtA->close();
    $appt_dt = !empty($ar['appointment_datetime']) ? date('Y-m-d H:i', strtotime($ar['appointment_datetime'])) : '—';

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    ob_end_clean();
    exit('Error: '.$e->getMessage());
}

/* --------- PDF (matches your designed preview) --------- */
$pdfPath = null;
try {
    $reportsDir = __DIR__ . '/../uploads/reports';
    if (!is_dir($reportsDir)) { mkdir($reportsDir, 0775, true); }

    // build medicine table
    $medRowsHtml = '';
    if (!empty($medicineDetails)) {
        foreach ($medicineDetails as $m) {
            $medRowsHtml .= '<tr>
              <td>'.htmlspecialchars($m['name']).'</td>
              <td>'.htmlspecialchars($m['form']).'</td>
              <td>'.htmlspecialchars($m['strength']).'</td>
              <td style="text-align:center;">'.(int)$m['ppd'].'</td>
              <td style="text-align:center;">'.(int)$m['days'].'</td>
            </tr>';
        }
    } else {
        $medRowsHtml = '<tr><td colspan="5" style="text-align:center;color:#6b7280;">No medicines prescribed.</td></tr>';
    }

    // compute age
    $ageText = '—';
    if (!empty($p_dob) && $p_dob !== '0000-00-00') {
        $dob = new DateTime($p_dob);
        $ageText = $dob->diff(new DateTime('today'))->y . ' yrs';
    }

    // logo (filesystem path for TCPDF)
    $logoFs = realpath(__DIR__ . '/../assets/images/logo.png');
    $logoTag = $logoFs && file_exists($logoFs)
        ? '<img src="'.htmlspecialchars($logoFs).'" width="48" />'
        : '<div style="font-size:20px;font-weight:700;color:#0ea5e9;">MediVerse</div>';

    // signature (script-like via italic)
    $sigName = 'Dr. ' . $doctor_name;

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('MediVerse');
    $pdf->SetAuthor('MediVerse');
    $pdf->SetTitle('Medical Report');
    $pdf->SetMargins(14, 14, 14);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->setImageScale(1.25);
    $pdf->SetProtection(['print','copy'], $patient_user_id);
    $pdf->AddPage();

    // CSS (subset TCPDF supports)
    $css = '
    <style>
      .h1{font-size:18px;margin:0;color:#0f172a}
      .muted{color:#64748b}
      .brand{color:#0ea5e9}
      .badge{display:inline-block;padding:2px 6px;border-radius:6px;background:#eef2ff;color:#3730a3;font-size:10px}
      .section{border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px;margin-top:8px}
      .key{color:#334155;width:28%;font-weight:600}
      .val{color:#0f172a}
      .hr{height:1px;background:#e5e7eb;margin:8px 0 6px 0}
      .pill{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:2px 6px;border-radius:999px;font-size:10px}
      table.med{width:100%;border-collapse:collapse}
      table.med th{background:#f8fafc;border:1px solid #e5e7eb;padding:6px;text-align:left}
      table.med td{border:1px solid #e5e7eb;padding:6px}
      .sig{font-style:italic;font-size:16px;margin-top:6px}
      .footer{font-size:9px;color:#64748b;margin-top:10px;text-align:center}
    </style>';

    // Header (logo + clinic info) — mirrors preview design
    $header = '
      <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
          <td width="52" align="left" valign="middle">'.$logoTag.'</td>
          <td align="left" valign="middle">
            <div class="h1">MediVerse</div>
            <div class="muted">
              <span class="badge">'.htmlspecialchars($doctor_spec ?: 'General').'</span>
              &nbsp;•&nbsp; Doctor ID: '.htmlspecialchars($doctor_public_id ?: '—').'
              &nbsp;•&nbsp; License #: '.htmlspecialchars($doctor_license ?: '—').'
              <br/>Doctor: <strong>Dr. '.htmlspecialchars($doctor_name).'</strong>
            </div>
          </td>
          <td width="120" align="right" class="muted">
            Report #: MR-'.(int)$report_id.'<br/>Created: '.htmlspecialchars(date('Y-m-d H:i', strtotime($datetime))).'
          </td>
        </tr>
      </table>
      <div class="hr"></div>';

    // Patient block
    $patient = '
      <div class="section">
        <table width="100%" cellpadding="2">
          <tr>
            <td class="key">Patient</td><td class="val">'.htmlspecialchars($patient_name).'</td>
            <td class="key">Patient ID</td><td class="val">'.htmlspecialchars($patient_user_id ?: '—').'</td>
          </tr>
          <tr>
            <td class="key">Gender</td><td class="val">'.htmlspecialchars($p_gender ?: '—').'</td>
            <td class="key">Age</td><td class="val">'.$ageText.'</td>
          </tr>
          <tr>
            <td class="key">Height</td><td class="val">'.htmlspecialchars($p_height !== '' ? $p_height.' cm' : '—').'</td>
            <td class="key">Weight</td><td class="val">'.htmlspecialchars($p_weight !== '' ? $p_weight.' kg' : '—').'</td>
          </tr>
          <tr>
            <td class="key">BMI</td><td class="val">'.htmlspecialchars($p_bmi !== '' ? $p_bmi : '—').'</td>
            <td class="key">Location</td><td class="val">'.htmlspecialchars($p_location ?: '—').'</td>
          </tr>
          <tr>
            <td class="key">Appointment</td><td class="val" colspan="3">'.htmlspecialchars($appt_dt).'</td>
          </tr>
        </table>
      </div>';

    $diag = '
      <div class="section">
        <div style="font-weight:700;margin-bottom:4px;">Diagnosis</div>
        <div>'.nl2br(htmlspecialchars($diagnosis)).'</div>
      </div>';

    $notes = '
      <div class="section">
        <div style="font-weight:700;margin-bottom:4px;">Notes / Description</div>
        <div>'.nl2br(htmlspecialchars($description)).'</div>
      </div>';

    $meds = '
      <div class="section">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <div style="font-weight:700;">Prescribed Medicines</div>
          <span class="pill">'.count($medicineDetails).' item(s)</span>
        </div>
        <table class="med">
          <thead>
            <tr>
              <th>Medicine</th><th>Form</th><th>Strength</th>
              <th style="text-align:center;">Pills/Day</th>
              <th style="text-align:center;">Days</th>
            </tr>
          </thead>
          <tbody>'.$medRowsHtml.'</tbody>
        </table>
      </div>';

    $sig = '
      <table width="100%" style="margin-top:12px;">
        <tr>
          <td width="60%"></td>
          <td align="right">
            <div>Physician Signature</div>
            <div class="sig">'.htmlspecialchars($sigName).'</div>
            <div class="muted" style="font-size:10px;">ID '.htmlspecialchars($doctor_public_id ?: '—').' • License '.htmlspecialchars($doctor_license ?: '—').'</div>
          </td>
        </tr>
      </table>';

    $footer = '<div class="footer">This report was generated by MediVerse. For emergencies, call local emergency services.</div>';

    $html = $css . $header . $patient . $diag . $notes . $meds . $sig . $footer;

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdfPath = $reportsDir . "/report_{$report_id}.pdf";
    $pdf->Output($pdfPath, 'F');
} catch (Throwable $e) {
    error_log('PDF error: '.$e->getMessage());
}

/* --------- Email (attach PDF) --------- */
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mediverse259@gmail.com';
    $mail->Password   = 'yrecnfqylehxregz';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('mediverse259@gmail.com', 'MediVerse');
    if (!empty($patient_email)) {
        $mail->addAddress($patient_email, $patient_name ?: '');
    }
    if ($pdfPath && file_exists($pdfPath)) {
        $mail->addAttachment($pdfPath, "Medical_Report_{$report_id}.pdf");
    }

    $viewUrl = BASE_URL . '/Doctors/report_detail.php?report_id=' . urlencode((string)$report_id);
    $mail->isHTML(true);
    $mail->Subject = 'Your Medical Report is Ready';
    $mail->Body = "
      <div style='font-family:Arial,Helvetica,sans-serif;text-align:center;'>
        <h2>🩺 MediVerse – Medical Report Ready</h2>
        <p>Hello " . htmlspecialchars($patient_name) . ",</p>
        <p>Your medical report is ready. You can view it online or open the attached PDF.</p>
        <a href='{$viewUrl}' style='background:#27ae60;color:#fff;padding:10px 15px;border-radius:6px;text-decoration:none;'>View Report</a>
        <p style='font-size:13px;color:#555;margin-top:18px;'>
          The attached PDF is password-protected.<br><strong>Password:</strong> your patient ID.
        </p>
      </div>";
    $mail->send();
} catch (Throwable $e) {
    error_log('Mail error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
}

/* Redirect cleanly */
ob_end_clean();
header("Location: view_appointments.php?report=sent&aid=".(int)$appointment_id);
exit;
