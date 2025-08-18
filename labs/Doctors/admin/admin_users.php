<?php
session_start();
require_once '../config.php';
include '../navbar_loggedin.php'; 

// ✅ Only admins can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

// ✅ Handle user actions (Block/Unblock or Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_user_id'])) {
        // 🔄 Toggle user active/blocked state
        $uid = (int)$_POST['toggle_user_id'];
        $new = $_POST['current_state'] === '1' ? 0 : 1; // flip 1→0 or 0→1
        $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new, $uid);
        $stmt->execute();

    } elseif (isset($_POST['delete_user_id'])) {
        // ❌ Permanently delete user
        $uid = (int)$_POST['delete_user_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
    }

    // 🔄 Redirect after action (avoids re-submission)
    header("Location: admin_users.php");
    exit;
}

// 📋 Get all users except admins
$usersQ = $conn->query("SELECT id, username, email, role, is_active FROM users WHERE role <> 'admin' ORDER BY id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users | MediVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h3>👥 All Users</h3>

  <!-- 📊 Users table -->
  <table class="table table-striped align-middle mt-3">
    <thead>
      <tr>
        <th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($u = $usersQ->fetch_assoc()): ?>
        <tr>
          <!-- ℹ️ User basic info -->
          <td><?= $u['id'] ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= $u['role'] ?></td>
          <td><?= $u['is_active'] ? 'Active' : 'Blocked' ?></td>

          <!-- 🔧 Actions: Block/Unblock & Delete -->
          <td class="d-flex gap-2">
            <!-- Toggle Block/Unblock -->
            <form method="POST" style="margin:0;">
              <input type="hidden" name="toggle_user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="current_state" value="<?= $u['is_active'] ?>">
              <button class="btn btn-sm btn-<?= $u['is_active'] ? 'warning' : 'success' ?>">
                <?= $u['is_active'] ? 'Block' : 'Unblock' ?>
              </button>
            </form>

            <!-- Delete User -->
            <form method="POST" style="margin:0;">
              <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-danger" onclick="return confirm('Delete user #<?= $u['id'] ?>?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
