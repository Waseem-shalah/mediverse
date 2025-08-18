<?php
// Doctor/doctor_chats.php
// Purpose: Show a doctor's chat requests with simple client-side filters.
// Note: Only brief human comments added; logic/markup unchanged.

session_start();
require_once '../config.php';
require '../navbar_loggedin.php';

// Gate: doctor-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    die("Unauthorized access.");
}

$doctor_id = (int)$_SESSION['user_id'];

// Fetch all chats addressed to this doctor with patient names
$stmt = $conn->prepare("
    SELECT c.id AS chat_id, c.status, u.name AS patient_name 
    FROM chats c 
    JOIN users u ON c.patient_id = u.id 
    WHERE c.doctor_id = ?
    ORDER BY c.id DESC
");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$chats = [];
$counts = ['pending'=>0,'accepted'=>0,'closed'=>0,'other'=>0];

// Aggregate counts by status + collect rows
while ($row = $result->fetch_assoc()) {
    $status = strtolower(trim((string)$row['status']));
    if (!isset($counts[$status])) { $counts['other']++; } else { $counts[$status]++; }
    $chats[] = $row;
}
$stmt->close();

$total = count($chats);

// Small helper to map status -> Bootstrap badge class
function badgeClass($s) {
    $s = strtolower(trim((string)$s));
    return $s === 'accepted' ? 'success' : ($s === 'pending' ? 'warning' : 'secondary');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Doctor Chats | MediVerse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Light styling to match the app look */
        body{ background:#f6f9fc; }
        .page-wrap{ max-width: 1000px; }
        .card-shadow{ box-shadow:0 10px 30px rgba(2,6,23,.06); }
        .chip{
          display:inline-flex; align-items:center; gap:.45rem;
          background:#eef2ff; color:#3730a3;
          border-radius:999px; padding:.35rem .7rem; font-weight:700; font-size:.9rem;
        }
        .chip-muted{ background:#f1f5f9; color:#0f172a; }
        .list-group-item{ border-left:0;border-right:0; }
        .patient{ font-weight:600; color:#0f172a; }
        .subtle{ color:#64748b; font-size:.925rem; }
        .filters .form-label{ font-weight:600; color:#334155; }
        .empty{ padding:42px 16px; color:#64748b; }
    </style>
</head>
<body>

<div class="container page-wrap py-4">

  <!-- Header with totals and quick counters -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <h3 class="m-0">💬 Patient Chat Requests</h3>
      <span class="chip"><i class="bi bi-chat-dots"></i> <?= (int)$total ?> total</span>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <span class="chip chip-muted"><i class="bi bi-hourglass-split"></i> Pending: <?= (int)$counts['pending'] ?></span>
      <span class="chip chip-muted"><i class="bi bi-check2-circle"></i> Accepted: <?= (int)$counts['accepted'] ?></span>
      <span class="chip chip-muted"><i class="bi bi-archive"></i> Closed: <?= (int)$counts['closed'] ?></span>
    </div>
  </div>

  <!-- Client-side filters only (name + status) -->
  <div class="card card-shadow mb-3">
    <div class="card-header"><i class="bi bi-funnel me-2"></i>Filters</div>
    <div class="card-body">
      <form class="row g-3 filters" onsubmit="return false;">
        <div class="col-sm-6 col-md-6">
          <label class="form-label">Patient name</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-search"></i></span>
            <input type="text" id="q" class="form-control" placeholder="e.g. John Doe">
          </div>
        </div>
        <div class="col-sm-6 col-md-4">
          <label class="form-label">Status</label>
          <select id="status" class="form-select">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="accepted">Accepted</option>
            <option value="closed">Closed</option>
          </select>
        </div>
        <div class="col-sm-12 col-md-2 d-flex align-items-end gap-2">
          <button id="apply" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Apply</button>
          <button id="reset" class="btn btn-outline-secondary w-100">Reset</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Chats list (or empty state) -->
  <div class="card card-shadow">
    <?php if (empty($chats)): ?>
      <div class="card-body text-center empty">
        <div class="display-6 mb-2">🕊️</div>
        No chat requests at the moment.
      </div>
    <?php else: ?>
      <ul id="chatList" class="list-group list-group-flush">
        <?php foreach ($chats as $chat): ?>
          <?php
            $pname = htmlspecialchars($chat['patient_name']);
            $status = strtolower(trim((string)$chat['status']));
            $statusText = ucfirst($status ?: 'unknown');
            $badge = badgeClass($status);
          ?>
          <li class="list-group-item d-flex justify-content-between align-items-center"
              data-name="<?= $pname ?>"
              data-status="<?= htmlspecialchars($status) ?>">
            <div class="me-3">
              <div class="patient"><?= $pname ?></div>
              <div class="subtle">Status:
                <span class="badge bg-<?= $badge ?>"><?= $statusText ?></span>
              </div>
            </div>
            <a href="doctor_chat_window.php?chat_id=<?= (int)$chat['chat_id'] ?>"
               class="btn btn-outline-primary btn-sm">
               <i class="bi bi-chat-left-text me-1"></i>Open Chat
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <div id="noMatch" class="card-body text-center empty d-none">
        <i class="bi bi-search mb-2" style="font-size:1.6rem;"></i><br>
        No chats match your filters.
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
/* Simple local filtering: by patient name and status */
(function(){
  const qEl = document.getElementById('q');
  const sEl = document.getElementById('status');
  const apply = document.getElementById('apply');
  const reset = document.getElementById('reset');
  const list = document.getElementById('chatList');
  const noMatch = document.getElementById('noMatch');

  // Apply filters by toggling each <li>'s display
  function applyFilters(){
    if (!list) return;
    const q = (qEl.value || '').trim().toLowerCase();
    const st = (sEl.value || '').toLowerCase();

    let shown = 0;
    [...list.children].forEach(li=>{
      if (!(li instanceof HTMLElement)) return;
      const name = (li.dataset.name || '').toLowerCase();
      const status = (li.dataset.status || '').toLowerCase();

      let ok = true;
      if (q) ok = name.includes(q);          // filter by name substring
      if (ok && st) ok = (status === st);    // filter by exact status

      li.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });

    if (noMatch) noMatch.classList.toggle('d-none', shown !== 0);
  }

  // Reset fields and re-apply
  function resetFilters(){
    if (qEl) qEl.value = '';
    if (sEl) sEl.value = '';
    applyFilters();
  }

  // Wire events (buttons and live typing/change)
  if (apply) apply.addEventListener('click', applyFilters);
  if (reset) reset.addEventListener('click', resetFilters);
  if (qEl) qEl.addEventListener('input', applyFilters);
  if (sEl) sEl.addEventListener('change', applyFilters);
})();
</script>

</body>
</html>
