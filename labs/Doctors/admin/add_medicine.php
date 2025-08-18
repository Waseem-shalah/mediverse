<?php
session_start();
require_once '../config.php';

// ✅ Only allow access if the user is logged in AND is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: ../login.php'); 
  exit();
}

// 📋 Fetch list of specializations from DB (for the multi-select dropdown)
$specs = $conn->query("SELECT id, name FROM specializations ORDER BY name");

// 🔔 Handle flash messages (success/error from process page)
$err  = $_SESSION['med_error']  ?? '';
$ok   = $_SESSION['med_success'] ?? '';
unset($_SESSION['med_error'], $_SESSION['med_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Medicine | Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container mt-5" style="max-width:900px;">
  <h2 class="mb-4">➕ Add New Medicine</h2>

  <!-- 🔴 Show error if exists -->
  <?php if ($err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <!-- 🟢 Show success message if exists -->
  <?php if ($ok): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
  <?php endif; ?>

  <!-- 📝 Medicine form -->
  <form action="add_medicine_process.php" method="post" class="row g-3">

    <!-- Brand Name -->
    <div class="col-md-6">
      <label class="form-label">Brand Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>

    <!-- Generic Name -->
    <div class="col-md-6">
      <label class="form-label">Generic Name</label>
      <input type="text" name="generic_name" class="form-control" required>
    </div>

    <!-- Dosage Form -->
    <div class="col-md-4">
      <label class="form-label">Dosage Form</label>
      <input type="text" name="dosage_form" class="form-control" placeholder="Tablet / Capsule / Cream / Injection..." required>
    </div>

    <!-- Strength -->
    <div class="col-md-4">
      <label class="form-label">Strength</label>
      <input type="text" name="strength" class="form-control" placeholder="e.g., 500 mg, 10 mg, 1%" required>
    </div>

    <!-- OTC field -->
    <div class="col-md-4">
      <label class="form-label">Over-the-Counter (OTC)?</label>
      <select name="is_otc" class="form-select" required>
        <option value="0">No (Rx only)</option>
        <option value="1">Yes</option>
      </select>
    </div>

    <!-- Prescription required field -->
    <div class="col-md-4">
      <label class="form-label">Prescription Required?</label>
      <select name="is_prescription_required" class="form-select" required>
        <option value="1">Yes (Prescription)</option>
        <option value="0">No</option>
      </select>
      <div class="form-text">Tip: Usually inverse of OTC.</div>
    </div>

    <!-- Specializations (multi-select) -->
    <div class="col-md-8">
      <label class="form-label">Specializations (one or more)</label>
      <select name="specialization_ids[]" class="form-select" multiple size="6" required>
        <?php while ($s = $specs->fetch_assoc()): ?>
          <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
        <?php endwhile; ?>
      </select>
      <div class="form-text">Hold Ctrl (Cmd on Mac) to select multiple.</div>
    </div>

    <!-- Buttons -->
    <div class="col-12">
      <button class="btn btn-primary">Save Medicine</button>
      <a href="index.php" class="btn btn-secondary ms-2">Back to Admin</a>
    </div>
  </form>
</div>
</body>
</html>
