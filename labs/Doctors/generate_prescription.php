<?php
session_start();
require_once 'config.php';
require 'navbar_loggedin.php'; // top nav (logged-in version)

// ✅ Only logged-in patients can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

// ---------- Input & basic validation ----------
$pm_id = $_GET['pm_id'] ?? null;
if (!$pm_id || !ctype_digit((string)$pm_id)) {
    die("Missing prescription ID.");
}

$pm_id      = (int)$pm_id;
$patient_id = (int)$_SESSION['user_id'];

// ---------- Fetch prescription (strictly limited to this patient) ----------
// We pull the medicine information, the originating report metadata
// (diagnosis/description/created_at), and both doctor + patient info needed
// for display and signatures. If no row, we block access.
$sql = "
    SELECT 
        pm.id,
        m.name                               AS medicine_name,
        COALESCE(m.dosage_form,'')           AS dosage_form,
        COALESCE(m.strength,'')              AS strength,
        m.is_prescription_required,
        pm.pills_per_day,
        pm.duration_days,
        mr.id                                AS report_id,
        mr.diagnosis,
        mr.description,
        mr.created_at,
        d.name                                AS doctor_name,
        d.user_id                             AS doctor_public_id,
        d.license_number,
        u.name                                AS patient_name,
        u.gender,
        u.date_of_birth
    FROM prescribed_medicines pm
    JOIN medical_reports   mr ON pm.report_id = mr.id
    JOIN users             d  ON mr.doctor_id = d.id
    JOIN users             u  ON pm.patient_id = u.id
    JOIN medicines         m  ON pm.medicine_id = m.id
    WHERE pm.id = ? AND pm.patient_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) { die("Prepare failed: " . $conn->error); }
$stmt->bind_param("ii", $pm_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { $stmt->close(); die("Prescription not found."); }
$prescription = $res->fetch_assoc();
$stmt->close();

// ---------- Generate a reproducible “digital signature” label ----------
// We derive a short signature ID from doctor public id + report id + report time.
// This mirrors the technique used in your report page, so both match visually.
$signature_id = strtoupper(substr(hash(
    'sha256',
    (string)($prescription['doctor_public_id'] ?? '') . '|' .
    (string)($prescription['report_id'] ?? '')        . '|' .
    (string)($prescription['created_at'] ?? '')
), 0, 10));
$signed_when  = date('Y-m-d H:i', strtotime($prescription['created_at'] ?? 'now'));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Printable Prescription</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap for quick layout/utility classes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Decorative script font for signature line -->
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">

    <style>
        /* ========= Page Theme / Tokens ========= */
        :root{
          --ink:#111827; --muted:#64748b; --line:#e5e7eb; --brand:#2563eb; --chip:#eef2ff;
        }

        body{ background:#f6f9fc; color:var(--ink); }

        /* Main card (“sheet”) holding the printable content */
        .sheet{
          max-width:900px; margin:32px auto; background:#fff;
          border:1px solid var(--line); border-radius:14px; box-shadow:0 12px 30px rgba(2,6,23,.06);
          padding:28px;
        }

        .brand-badge{
          display:inline-flex; align-items:center; gap:.5rem; background:var(--chip);
          color:#1f2937; padding:.35rem .7rem; border-radius:999px; font-weight:700;
        }
        .h-title{ letter-spacing:.3px }
        .subtle{ color:var(--muted) }
        .kgrid .k{ color:#334155; font-weight:600; width:140px }
        .rx{ font-weight:800; font-size:22px; color:var(--brand) }
        .divider{ border-top:1px dashed var(--line); margin:16px 0 }
        .pill{
          display:inline-block; padding:.25rem .5rem; border:1px solid var(--line); background:#f8fafc;
          border-radius:999px; font-size:.85rem; color:#334155
        }
        .note{ font-size:.9rem; color:var(--muted) }
        .btn-print{ white-space:nowrap }

        /* Signature styling, scoped to this page only */
        .sigline{ height:44px; }
        .sigcap{ font-size:.85rem; color:var(--muted); margin-top:.35rem; }
        .sig-script{ font-family:'Great Vibes', cursive; font-size:36px; line-height:1; color:#111827; }
        .sig-meta{ font-size:.78rem; color:var(--muted); }

        /* Printer-friendly rules: hide UI and compact spacing */
        @media print {
            @page{ size:A4; margin:12mm }
            body{ background:#fff; }
            .sheet{ border:none; box-shadow:none; margin:0; padding:0 }
            .no-print, nav, .navbar, header, footer{ display:none !important }
            .divider{ margin:10px 0 }
        }
    </style>
</head>
<body>

<div class="container">
  <div class="sheet">
    <!-- ===== Header: brand + print/back buttons (screen only) ===== -->
    <div class="d-flex align-items-center justify-content-between pb-3 border-bottom">
      <div class="d-flex align-items-center gap-3">
        <img src="assets/images/logo.png" alt="MediVerse" style="width:44px;height:44px;object-fit:contain">
        <div>
          <div class="h5 m-0 h-title">MediVerse</div>
          <div class="subtle small">Medical Prescription</div>
        </div>
      </div>

      <!-- Right-side buttons hidden on print -->
      <div class="no-print">
        <div class="d-flex gap-2">
          <button onclick="window.print()" class="btn btn-primary btn-print">🖨️ Print</button>
          <a href="prescriptions.php" class="btn btn-outline-secondary">← Back</a>
        </div>
      </div>
    </div>

    <!-- ===== Patient block ===== -->
    <div class="row g-4 mt-2">
      <div class="col-md-6">
        <div class="brand-badge mb-2">👤 Patient</div>
        <div class="kgrid">
          <div class="d-flex"><div class="k">Name</div><div>: <?= htmlspecialchars($prescription['patient_name']) ?></div></div>
          <div class="d-flex"><div class="k">Gender</div><div>: <?= htmlspecialchars($prescription['gender'] ?: '—') ?></div></div>
          <div class="d-flex"><div class="k">Date of Birth</div><div>: <?= htmlspecialchars($prescription['date_of_birth'] ?: '—') ?></div></div>
        </div>
      </div>

      <!-- You could add a right-side “Prescription meta” panel here if needed -->
    </div>

    <div class="divider"></div>

    <!-- ===== Diagnosis (from the originating report) ===== -->
    <div class="mb-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="rx">℞</span><h5 class="m-0">Diagnosis</h5>
      </div>
      <div class="p-3 rounded" style="background:#f8fafc;border:1px solid var(--line)">
        <?= nl2br(htmlspecialchars($prescription['diagnosis'])) ?>
      </div>
    </div>

    <!-- ===== Clinical notes (physician’s description) ===== -->
    <div class="mb-3">
      <h5 class="mb-2">Clinical Notes</h5>
      <div class="p-3 rounded" style="background:#fafafa;border:1px solid var(--line)">
        <?= nl2br(htmlspecialchars($prescription['description'])) ?>
      </div>
    </div>

    <!-- ===== Medicine table (single medicine tied to this pm_id) ===== -->
    <div class="mb-2">
      <h5 class="mb-2">Prescribed Medicine</h5>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Medicine</th>
              <th>Form</th>
              <th>Strength</th>
              <th class="text-center">Times a day</th>
              <th class="text-center">Duration (Days)</th>
              <th>Type</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($prescription['medicine_name']) ?></td>
              <td><?= htmlspecialchars($prescription['dosage_form'] ?: '—') ?></td>
              <td><?= htmlspecialchars($prescription['strength'] ?: '—') ?></td>
              <td class="text-center"><?= (int)$prescription['pills_per_day'] ?></td>
              <td class="text-center"><?= (int)$prescription['duration_days'] ?></td>
              <td>
                <span class="pill">
                  <?= ((int)$prescription['is_prescription_required'] === 1) ? 'Prescription Required' : 'Over the Counter' ?>
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="note">Follow the dosage instructions precisely. Contact your doctor if side effects occur.</div>
    </div>

    <!-- ===== Doctor block ===== -->
    <div class="col-md-6">
      <div class="brand-badge mb-2">🩺 Doctor</div>
      <div class="kgrid">
        <div class="d-flex"><div class="k">Name</div><div>: <?= htmlspecialchars($prescription['doctor_name']) ?></div></div>
        <div class="d-flex"><div class="k">License #</div><div>: <?= htmlspecialchars($prescription['license_number'] ?: '—') ?></div></div>
        <div class="d-flex"><div class="k">Date Issued</div><div>: <?= htmlspecialchars(date("Y-m-d", strtotime($prescription['created_at']))) ?></div></div>
      </div>
    </div>

    <!-- ===== Signatures ===== -->
    <div class="row g-4 mt-4">
      <div class="col-md-6">
        <!-- Script-styled name for visual “signature” -->
        <div class="sig-script"><?= htmlspecialchars($prescription['doctor_name']) ?></div>
        <div class="sig-meta">
          Digitally signed • <?= htmlspecialchars($signed_when) ?>
          • Signature ID: <?= htmlspecialchars($signature_id) ?>
          <?php if (!empty($prescription['license_number'])): ?>
            • License #: <?= htmlspecialchars($prescription['license_number']) ?>
          <?php endif; ?>
        </div>
        <div class="sigcap mt-1">Doctor’s Signature</div>
      </div>
      <div class="col-md-6">
        <!-- Patient can physically sign the printout if needed -->
        <div class="sigline border-bottom"></div>
        <div class="sigcap">Patient’s Signature</div>
      </div>
    </div>

    <!-- ===== Footer actions (screen only; hidden when printing) ===== -->
    <div class="d-flex justify-content-end gap-2 mt-4 no-print">
      <button onclick="window.print()" class="btn btn-primary btn-print">🖨️ Print</button>
      <a href="prescriptions.php" class="btn btn-outline-secondary">← Back to Prescriptions</a>
    </div>
  </div>
</div>

</body>
</html>
