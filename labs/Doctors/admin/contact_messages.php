<?php
session_start();
require_once '../config.php';

// ✅ Security check: only admins can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

/* ✅ Step 1: Purge messages that were already replied to */
$deletedCount = 0;
if ($del = $conn->prepare("DELETE FROM contact_messages WHERE status = 'replied'")) {
    $del->execute();
    $deletedCount = $del->affected_rows; // how many rows got deleted
    $del->close();
}

/* ✅ Step 2: Fetch remaining messages (only 'new' and non-replied ones) */
$contactsQ = $conn->query("
    SELECT id, name, email, subject, message, status, created_at
    FROM contact_messages
    ORDER BY created_at DESC
");
if ($contactsQ === false) {
    die("SQL error: " . htmlspecialchars($conn->error));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Messages | MediVerse Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- ✅ Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* ✅ Custom styling */
    :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
    body{ background:#ffffff; color:var(--ink); }
    .page-wrap{ max-width: 1100px; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3; border-radius:999px;
      padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .table thead th{ background:#f8fafc; }
    .msg-cell{ max-width: 380px; white-space: pre-wrap; }
    .count-badge{ background:#e0e7ff; color:#3730a3; font-weight:700; }
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">
  <!-- ✅ Page header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">📨 Contact Messages</h3>
      <span class="chip"><i class="bi bi-envelope-paper me-1"></i>MediVerse</span>
    </div>
  </div>

  <!-- ✅ Success alert if any old replied messages were deleted -->
  <?php if ($deletedCount > 0): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Deleted <?= (int)$deletedCount ?> replied message<?= $deletedCount === 1 ? '' : 's' ?>.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- ✅ Main contact messages card -->
  <div class="card card-shadow">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
      <strong>Inbox</strong>
      <!-- ✅ Show how many messages remain -->
      <span class="badge count-badge">
        <?php
          $remaining = $contactsQ->num_rows ?? 0;
          echo (int)$remaining . ' message' . ($remaining === 1 ? '' : 's');
        ?>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:80px;">ID</th>
            <th style="width:180px;">From</th>
            <th>Subject</th>
            <th style="min-width:360px;">Message</th>
            <th style="width:160px;">Received</th>
            <th style="width:140px;">Status</th>
            <th style="width:140px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <!-- ✅ If no messages, show empty inbox -->
          <?php if ($contactsQ->num_rows === 0): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                <i class="bi bi-inbox mb-2 d-block" style="font-size:1.6rem;"></i>
                No messages.
              </td>
            </tr>
          <?php else: ?>
            <!-- ✅ Otherwise, loop through messages -->
            <?php while ($c = $contactsQ->fetch_assoc()): ?>
              <tr>
                <!-- Message ID -->
                <td class="text-muted">#<?= (int)$c['id'] ?></td>
                
                <!-- Sender info -->
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($c['name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($c['email']) ?></div>
                </td>
                
                <!-- Subject -->
                <td><?= htmlspecialchars($c['subject']) ?></td>
                
                <!-- Message body -->
                <td class="msg-cell"><?= nl2br(htmlspecialchars($c['message'])) ?></td>
                
                <!-- Date received -->
                <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($c['created_at']))) ?></td>
                
                <!-- Status badge -->
                <td>
                  <?php if ($c['status'] === 'new'): ?>
                    <span class="badge bg-secondary">New</span>
                  <?php else: ?>
                    <span class="badge bg-light text-dark"><?= htmlspecialchars(ucfirst($c['status'])) ?></span>
                  <?php endif; ?>
                </td>
                
                <!-- Action buttons -->
                <td>
                  <?php if ($c['status'] === 'new'): ?>
                    <a href="../reply_contact.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">
                      <i class="bi bi-reply-fill me-1"></i>Reply
                    </a>
                  <?php else: ?>
                    &ndash;
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ✅ Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
