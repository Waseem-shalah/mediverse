<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized.");
}

$adminId = (int)$_SESSION['user_id'];

require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
Run once if you don't have these columns:
ALTER TABLE users
  ADD COLUMN is_blocked TINYINT(1) DEFAULT 0,
  ADD COLUMN block_reason TEXT NULL,
  ADD COLUMN blocked_by INT NULL,
  ADD COLUMN user_deleted TINYINT(1) DEFAULT 0,
  ADD COLUMN delete_reason TEXT NULL,
  ADD COLUMN deleted_by INT NULL;
*/

/**
 * Build styled HTML email body for user status changes.
 */
function buildEmailBody(string $username, string $action, ?string $reason, string $btnText, string $btnUrl): string {
    $accent = match($action) {
        'blocked'  => '#dc3545',
        'deleted'  => '#6c757d',
        'restored' => '#0d6efd',
        default    => '#198754'
    };
    $noticeBg = match($action) {
        'blocked'  => '#f8d7da',
        'deleted'  => '#e2e3e5',
        'restored' => '#cfe2ff',
        default    => '#d1e7dd'
    };
    $reasonHtml = $reason ? "<blockquote style='background: {$noticeBg}; padding: 10px; border-left: 5px solid {$accent}; margin: 12px 0;'>{$reason}</blockquote>" : "";

    return "
      <div style='font-family: Arial, sans-serif; background-color: #f5f7fa; padding: 30px;'>
        <div style='max-width: 620px; margin: auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 6px 18px rgba(0,0,0,0.08);'>
          <div style='background-color: #0d6efd; padding: 18px; text-align: center;'>
            <h2 style='color: white; margin: 0;'>MediVerse</h2>
          </div>
          <div style='padding: 22px 24px;'>
            <p>Hello <strong>{$username}</strong>,</p>
            <p>Your MediVerse account has been <strong style='color: {$accent}; text-transform: capitalize;'>{$action}</strong>.</p>
            {$reasonHtml}
            <div style='text-align: center; margin: 22px 0;'>
              <a href='{$btnUrl}' style='background-color: {$accent}; color: white; padding: 12px 22px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>{$btnText}</a>
            </div>
            <p style='font-size: 13px; color: #6c757d; margin: 20px 0 0;'>If the button doesn’t work, copy &amp; paste this link:<br>
              <span style='color:#0d6efd'>{$btnUrl}</span>
            </p>
          </div>
        </div>
      </div>
    ";
}

/**
 * Send a notification email to a specific user by ID.
 * (SMTP creds should ideally come from env/config, not hardcoded.)
 */
function sendUserEmailById(mysqli $conn, int $userId, string $subject, string $htmlBody): void {
    $smtpUser = 'mediverse259@gmail.com';
    $smtpPass = 'yrecnfqylehxregz'; // Gmail App Password

    $stmt = $conn->prepare("SELECT email, username, name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($email, $username, $name);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) return;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@mediverse.com', 'MediVerse');
        $mail->addAddress($email, $name ?: $username);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
    } catch (Exception $e) {
        error_log("Email error to {$email}: {$mail->ErrorInfo}");
    }
}

// ====== Actions (block / unblock / delete / restore) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // BLOCK
    if (isset($_POST['block_user_id'])) {
        $uid    = (int)$_POST['block_user_id'];
        $reason = trim($_POST['block_reason'] ?? '');
        if ($uid > 0 && $reason !== '') {
            $stmt = $conn->prepare("UPDATE users SET is_active = 0, is_blocked = 1, block_reason = ?, blocked_by = ?, user_deleted = 0 WHERE id = ?");
            $stmt->bind_param("sii", $reason, $adminId, $uid);
            $stmt->execute();
            $stmt->close();

            $stmtU = $conn->prepare("SELECT username FROM users WHERE id=?");
            $stmtU->bind_param("i", $uid);
            $stmtU->execute();
            $stmtU->bind_result($uname);
            $stmtU->fetch();
            $stmtU->close();

            $html = buildEmailBody($uname ?: 'User', 'blocked', $reason, 'Contact Support', 'http://localhost/labs/Doctors/contact.php');
            sendUserEmailById($conn, $uid, "Your MediVerse Account Has Been Blocked", $html);
        }
        header("Location: users.php"); exit;
    }

    // UNBLOCK
    if (isset($_POST['unblock_user_id'])) {
        $uid = (int)$_POST['unblock_user_id'];
        if ($uid > 0) {
            $stmt = $conn->prepare("UPDATE users SET is_active = 1 , is_blocked = 0, block_reason = NULL, blocked_by = NULL WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();

            $stmtU = $conn->prepare("SELECT username FROM users WHERE id=?");
            $stmtU->bind_param("i", $uid);
            $stmtU->execute();
            $stmtU->bind_result($uname);
            $stmtU->fetch();
            $stmtU->close();

            $html = buildEmailBody($uname ?: 'User', 'unblocked', null, 'Login to MediVerse', 'http://localhost/labs/Doctors/login.php');
            sendUserEmailById($conn, $uid, "Your MediVerse Account Has Been Unblocked", $html);
        }
        header("Location: users.php"); exit;
    }

    // DELETE
    if (isset($_POST['delete_user_id'])) {
        $uid    = (int)$_POST['delete_user_id'];
        $reason = trim($_POST['delete_reason'] ?? '');
        if ($uid > 0 && $reason !== '') {
            $stmt = $conn->prepare("UPDATE users SET is_active = 0 ,user_deleted = 1, delete_reason = ?, deleted_by = ?, is_blocked = 0 WHERE id = ?");
            $stmt->bind_param("sii", $reason, $adminId, $uid);
            $stmt->execute();
            $stmt->close();

            $stmtU = $conn->prepare("SELECT username FROM users WHERE id=?");
            $stmtU->bind_param("i", $uid);
            $stmtU->execute();
            $stmtU->bind_result($uname);
            $stmtU->fetch();
            $stmtU->close();

            $html = buildEmailBody($uname ?: 'User', 'deleted', $reason, 'Contact Support', 'http://localhost/labs/Doctors/contact.php');
            sendUserEmailById($conn, $uid, "Your MediVerse Account Has Been Deleted", $html);
        }
        header("Location: users.php"); exit;
    }

    // RESTORE
    if (isset($_POST['restore_user_id'])) {
        $uid = (int)$_POST['restore_user_id'];
        if ($uid > 0) {
            $stmt = $conn->prepare("UPDATE users SET is_active = 1, user_deleted = 0, delete_reason = NULL, deleted_by = NULL WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();

            $stmtU = $conn->prepare("SELECT username FROM users WHERE id=?");
            $stmtU->bind_param("i", $uid);
            $stmtU->execute();
            $stmtU->bind_result($uname);
            $stmtU->fetch();
            $stmtU->close();

            $html = buildEmailBody($uname ?: 'User', 'restored', null, 'Login to MediVerse', 'http://localhost/labs/Doctors/login.php');
            sendUserEmailById($conn, $uid, "Your MediVerse Account Has Been Restored", $html);
        }
        header("Location: users.php"); exit;
    }
}

// Fetch users (includes public user_id and who performed actions)
$usersQ = $conn->query("
    SELECT 
        u.id,                          -- internal ID (for actions)
        u.user_id AS public_id,        -- public ID to display
        u.username, u.name AS full_name, u.email, u.role,
        u.is_blocked, u.user_deleted, u.block_reason, u.delete_reason,
        u.blocked_by, u.deleted_by,
        ab.username  AS blocked_by_name,
        dbu.username AS deleted_by_name
    FROM users u
    LEFT JOIN users ab  ON ab.id  = u.blocked_by
    LEFT JOIN users dbu ON dbu.id = u.deleted_by
    WHERE u.role <> 'admin'
    ORDER BY u.id
");

// Buffer rows and collect role filter options
$rows = [];
$rolesSet = [];
while ($row = $usersQ->fetch_assoc()) {
    $rows[] = $row;
    $rolesSet[$row['role']] = true;
}
$roles = array_keys($rolesSet);
sort($roles);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Manage Users | MediVerse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{ --bg:#ffffff; --ink:#0f172a; --muted:#64748b; --line:#e5e7eb; }
    body{ background:#ffffff; color:var(--ink); }

    .page-wrap{ max-width:1100px; }
    .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); border:1px solid #e9ecef; }
    .chip{
      display:inline-flex; align-items:center; gap:.45rem;
      background:#eef2ff; color:#3730a3; border-radius:999px;
      padding:.35rem .7rem; font-weight:700; font-size:.9rem;
    }
    .table thead th{ background:#f8fafc; }
    .status-note{ color:var(--muted); font-size:.85rem }
    .actions{ display:flex; gap:.5rem; flex-wrap:wrap }
    .reason-label{ font-weight:600; color:#334155 }
    .filters .form-label{ font-weight:600; color:#334155 }
    .count-badge{ background:#e0e7ff; color:#3730a3; font-weight:700; }
  </style>
</head>
<body>
<?php include '../navbar_loggedin.php'; ?>

<div class="container page-wrap my-4">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="mb-0">👥 Manage Users</h3>
      <span class="chip"><i class="bi bi-shield-lock me-1"></i>MediVerse</span>
    </div>
  </div>

  <!-- Users card -->
  <div class="card card-shadow">
    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-people me-1"></i> <strong>All Users</strong>
      </div>
      <span class="badge count-badge" id="matchCount"></span>
    </div>

    <!-- Filters -->
    <div class="px-3 pt-3 filters">
      <div class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label" for="filterName">Search by name / username / email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="filterName" type="text" class="form-control" placeholder="e.g. John, j_doe, john@mail.com">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="filterRole">Role</label>
          <select id="filterRole" class="form-select">
            <option value="">All roles</option>
            <?php foreach ($roles as $role): ?>
              <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars(ucfirst($role)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-grid">
          <button id="resetFilters" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters</button>
        </div>
      </div>
      <hr class="mt-3 mb-0">
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="usersTable">
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th>Username</th>
              <th>Email</th>
              <th style="width:120px">Role</th>
              <th style="width:220px">Status</th>
              <th style="width:180px">Who</th>
              <th style="width:280px">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $u): ?>
            <?php
              $rowId = (int)$u['id']; // internal ID for actions
              $statusHtml = '';
              $whoHtml = '<small>—</small>';

              if ((int)$u['user_deleted'] === 1) {
                $statusHtml = "<span class='badge bg-danger'>Deleted</span>";
                if (!empty($u['delete_reason'])) {
                  $statusHtml .= "<div class='status-note mt-1'>Reason: " . htmlspecialchars($u['delete_reason']) . "</div>";
                }
                if (!empty($u['deleted_by_name'])) {
                  $whoHtml = "<small>Deleted by: " . htmlspecialchars($u['deleted_by_name']) . "</small>";
                }
              } elseif ((int)$u['is_blocked'] === 1) {
                $statusHtml = "<span class='badge bg-warning text-dark'>Blocked</span>";
                if (!empty($u['block_reason'])) {
                  $statusHtml .= "<div class='status-note mt-1'>Reason: " . htmlspecialchars($u['block_reason']) . "</div>";
                }
                if (!empty($u['blocked_by_name'])) {
                  $whoHtml = "<small>Blocked by: " . htmlspecialchars($u['blocked_by_name']) . "</small>";
                }
              } else {
                $statusHtml = "<span class='badge bg-success'>Active</span>";
              }

              $fullName = trim((string)$u['full_name']);
              $fullNameHtml = $fullName ? "<div class='text-muted small'>".$fullName."</div>" : "";
            ?>
            <tr
              data-role="<?= htmlspecialchars($u['role']) ?>"
              data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>"
              data-fullname="<?= htmlspecialchars(strtolower($fullName)) ?>"
              data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>"
            >
              <!-- Show PUBLIC ID (users.user_id) here -->
              <td class="text-muted"><?= htmlspecialchars((string)($u['public_id'] ?? '—')) ?></td>

              <td class="fw-semibold">
                <?= htmlspecialchars($u['username']) ?>
                <?= $fullNameHtml ?>
              </td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($u['role']) ?></span></td>
              <td><?= $statusHtml ?></td>
              <td><?= $whoHtml ?></td>
              <td>
                <div class="actions">
                  <?php if (!(int)$u['user_deleted'] && !(int)$u['is_blocked']): ?>
                    <!-- Block -->
                    <form id="form_block_<?= $rowId ?>" method="POST" class="m-0">
                      <input type="hidden" name="block_user_id" value="<?= $rowId ?>">
                      <input type="hidden" id="block_reason_<?= $rowId ?>" name="block_reason">
                      <button type="button"
                              class="btn btn-warning btn-sm"
                              data-bs-toggle="modal"
                              data-bs-target="#reasonModal"
                              data-action="block"
                              data-user="<?= $rowId ?>"
                              data-username="<?= htmlspecialchars($u['username']) ?>">
                        <i class="bi bi-slash-circle me-1"></i>Block
                      </button>
                    </form>
                  <?php elseif ((int)$u['is_blocked'] && !(int)$u['user_deleted']): ?>
                    <!-- Unblock -->
                    <form method="POST" class="m-0">
                      <input type="hidden" name="unblock_user_id" value="<?= $rowId ?>">
                      <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Unblock</button>
                    </form>
                  <?php endif; ?>

                  <?php if (!(int)$u['user_deleted']): ?>
                    <!-- Delete -->
                    <form id="form_delete_<?= $rowId ?>" method="POST" class="m-0">
                      <input type="hidden" name="delete_user_id" value="<?= $rowId ?>">
                      <input type="hidden" id="delete_reason_<?= $rowId ?>" name="delete_reason">
                      <button type="button"
                              class="btn btn-danger btn-sm"
                              data-bs-toggle="modal"
                              data-bs-target="#reasonModal"
                              data-action="delete"
                              data-user="<?= $rowId ?>"
                              data-username="<?= htmlspecialchars($u['username']) ?>">
                        <i class="bi bi-trash me-1"></i>Delete
                      </button>
                    </form>
                  <?php else: ?>
                    <!-- Restore -->
                    <form method="POST" class="m-0">
                      <input type="hidden" name="restore_user_id" value="<?= $rowId ?>">
                      <button class="btn btn-primary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Reason Modal (shared for Block/Delete) -->
<div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reasonModalTitle">Reason</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><span class="reason-label" id="reasonUser">@user</span></div>
        <label for="reasonTextarea" class="form-label">Please enter a reason (required)</label>
        <textarea id="reasonTextarea" class="form-control" rows="4" placeholder="Describe the reason..." required></textarea>
        <div class="form-text">This reason will be saved and emailed to the user.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="reasonSubmitBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  // ===== Reason Modal (Block/Delete) =====
  const modalEl    = document.getElementById('reasonModal');
  const titleEl    = document.getElementById('reasonModalTitle');
  const userEl     = document.getElementById('reasonUser');
  const textareaEl = document.getElementById('reasonTextarea');
  const submitBtn  = document.getElementById('reasonSubmitBtn');

  let currentAction = null; // 'block' | 'delete'
  let currentUserId = null; // internal id
  let currentUserNm = '';   // username (for display)

  modalEl.addEventListener('show.bs.modal', (ev) => {
    const btn = ev.relatedTarget;
    currentAction = btn.getAttribute('data-action');
    currentUserId = btn.getAttribute('data-user');
    currentUserNm = btn.getAttribute('data-username') || ('#' + currentUserId);

    titleEl.textContent = (currentAction === 'block') ? 'Block User' : 'Delete User';
    userEl.textContent  = 'User: ' + currentUserNm;
    textareaEl.value    = '';
    setTimeout(() => textareaEl.focus(), 200);
  });

  submitBtn.addEventListener('click', () => {
    const reason = textareaEl.value.trim();
    if (!reason) {
      textareaEl.focus();
      return;
    }
    if (currentAction === 'block') {
      const hid = document.getElementById('block_reason_' + currentUserId);
      const frm = document.getElementById('form_block_' + currentUserId);
      if (hid && frm) { hid.value = reason; frm.submit(); }
    } else if (currentAction === 'delete') {
      const hid = document.getElementById('delete_reason_' + currentUserId);
      const frm = document.getElementById('form_delete_' + currentUserId);
      if (hid && frm) { hid.value = reason; frm.submit(); }
    }
  });

  // ===== Client-side filtering (by role + text) =====
  const nameInput = document.getElementById('filterName');
  const roleSel   = document.getElementById('filterRole');
  const table     = document.getElementById('usersTable');
  const rows      = Array.from(table.querySelectorAll('tbody tr'));
  const countBadge= document.getElementById('matchCount');
  const resetBtn  = document.getElementById('resetFilters');

  function normalize(s){ return (s || '').toString().trim().toLowerCase(); }

  function applyFilters(){
    const q = normalize(nameInput.value);
    const role = roleSel.value;

    let shown = 0;
    rows.forEach(tr => {
      const r  = tr.getAttribute('data-role') || '';
      const un = tr.getAttribute('data-username') || '';
      const fn = tr.getAttribute('data-fullname') || '';
      const em = tr.getAttribute('data-email') || '';

      const roleOk = !role || r === role;
      const nameOk = !q || un.includes(q) || fn.includes(q) || em.includes(q);

      if (roleOk && nameOk) { tr.classList.remove('d-none'); shown++; }
      else { tr.classList.add('d-none'); }
    });

    countBadge.textContent = shown + ' match' + (shown === 1 ? '' : 'es');
  }

  nameInput.addEventListener('input', applyFilters);
  roleSel.addEventListener('change', applyFilters);
  resetBtn.addEventListener('click', () => { nameInput.value = ''; roleSel.value = ''; applyFilters(); });

  applyFilters();
})();
</script>
</body>
</html>
