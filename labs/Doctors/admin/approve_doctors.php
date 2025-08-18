<?php
session_start();
require_once '../config.php';

// ✅ Only allow access if logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

// ✅ If admin submits "Approve" for a doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_doctor_id'])) {
    $did = (int)$_POST['approve_doctor_id']; // Doctor ID to approve

    // 🔍 Check if doctor already exists in doctors table
    $chk = $conn->prepare("SELECT 1 FROM doctors WHERE id = ?");
    $chk->bind_param("i", $did);
    $chk->execute();
    $chk_result = $chk->get_result();

    // 📌 If doctor row does not exist yet → insert it
    if ($chk_result->num_rows === 0) {
        // 🔍 Fetch doctor info from users table
        $u = $conn->prepare("SELECT name, license_number, specialization_id, email, phone, password FROM users WHERE id = ?");
        $u->bind_param("i", $did);
        $u->execute();
        $u->bind_result($name, $lic, $spec, $email, $phone, $pwd);
        $u->fetch();
        $u->close();

        // ➕ Insert doctor into doctors table
        $insDoc = $conn->prepare("INSERT INTO doctors (id, name, license_number, specialization_id, email, phone, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insDoc->bind_param("ississs", $did, $name, $lic, $spec, $email, $phone, $pwd);
        $insDoc->execute();
    }

    // ✅ Mark doctor as approved
    $stmt = $conn->prepare("UPDATE doctors SET is_approved = 1 WHERE id = ?");
    $stmt->bind_param("i", $did);
    $stmt->execute();

    // 🔄 Redirect back to approve_doctors page
    header("Location: approve_doctors.php");
    exit;
}

// 📊 Get all pending doctors (not approved yet)
$pendingDocsQ = $conn->query("
    SELECT u.id, u.username, d.license_number, s.name AS specialization
      FROM doctors d
      JOIN users u ON u.id = d.id
      JOIN specializations s ON s.id = d.specialization_id
     WHERE d.is_approved = 0
     ORDER BY u.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Approve Doctors | MediVerse Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container my-5">
  <h2>🩺 Approve Doctors</h2>
  <hr>
  <!-- 📋 Table of pending doctors -->
  <table class="table table-bordered">
    <thead>
      <tr><th>ID</th><th>Username</th><th>License #</th><th>Specialization</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php while ($d = $pendingDocsQ->fetch_assoc()): ?>
      <tr>
        <td><?= $d['id'] ?></td>
        <td><?= htmlspecialchars($d['username']) ?></td>
        <td><?= htmlspecialchars($d['license_number']) ?></td>
        <td><?= htmlspecialchars($d['specialization']) ?></td>
        <td>
          <!-- ✅ Approve button (submits POST with doctor ID) -->
          <form method="POST" style="margin:0;">
            <input type="hidden" name="approve_doctor_id" value="<?= $d['id'] ?>">
            <button class="btn btn-sm btn-primary">Approve</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
