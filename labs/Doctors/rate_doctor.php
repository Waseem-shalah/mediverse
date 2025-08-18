<?php
session_start();
require_once "config.php";

/* 1) Gate: only logged-in patients can rate */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}
$user_id = (int)$_SESSION['user_id']; // internal users.id

/* 2) Get appointment_id from querystring and validate */
if (empty($_GET['appointment_id']) || !ctype_digit($_GET['appointment_id'])) {
    die("Invalid appointment.");
}
$appointment_id = (int)$_GET['appointment_id'];

/* 3) Ensure this appointment belongs to the patient and is completed */
$stmt = $conn->prepare("
    SELECT doctor_id, status
    FROM appointments
    WHERE id = ? AND patient_id = ?
");
if (!$stmt) {
    die("DB error (prepare appointments): " . $conn->error);
}
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    $stmt->close();
    die("Appointment not found.");
}
$appt = $res->fetch_assoc();
$stmt->close();

if ($appt['status'] !== 'completed') {
    die("You can only rate after a completed visit.");
}

/* 4) Handle POST: validate factors, prevent double rating with a transaction */
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $factors = ['wait_time','service','communication','facilities'];
    $values  = [];

    // Each factor must be an int 1..5
    foreach ($factors as $f) {
        $v = filter_input(
            INPUT_POST,
            $f,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 5]]
        );
        if ($v === false) {
            $error = "Please rate all categories with values between 1 and 5.";
            break;
        }
        $values[$f] = (int)$v;
    }

    $comment = trim($_POST['comment'] ?? '');

    if (!$error) {
        $avg = round(array_sum($values) / count($values), 2); // overall score

        try {
            $conn->begin_transaction();

            // Lock any existing rating rows for this appointment (race-safe)
            $lock = $conn->prepare("SELECT id FROM ratings WHERE appointment_id = ? FOR UPDATE");
            if (!$lock) {
                $conn->rollback();
                die("DB error (prepare lock): " . $conn->error);
            }
            $lock->bind_param("i", $appointment_id);
            $lock->execute();
            $locked = $lock->get_result();
            $lock->close();

            // If a rating exists, abort and roll back
            if ($locked->num_rows > 0) {
                $conn->rollback();
                die("You have already rated this appointment.");
            }

            // Insert the new rating
            $sql = "
                INSERT INTO `ratings`
                    (`appointment_id`, `doctor_id`, `patient_id`,
                     `rating`, `wait_time`, `service`, `communication`, `facilities`,
                     `comment`, `rated_at`)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            $ins = $conn->prepare($sql);
            if (!$ins) {
                $conn->rollback();
                die("DB error (prepare insert rating): " . $conn->error);
            }

            $doctor_internal_id = (int)$appt['doctor_id']; // internal users.id

            $ins->bind_param(
                "iiidiiiis",
                $appointment_id,
                $doctor_internal_id,
                $user_id,
                $avg,
                $values['wait_time'],
                $values['service'],
                $values['communication'],
                $values['facilities'],
                $comment
            );

            if (!$ins->execute()) {
                $err = $ins->error;
                $ins->close();
                $conn->rollback();
                die("Failed to save rating: " . $err);
            }
            $ins->close();

            $conn->commit();

            // Back to the appointments list with a success flag
            header("Location: my_appointments.php?rated=1");
            exit();

        } catch (Throwable $e) {
            if ($conn->errno) { $conn->rollback(); }
            die("Unexpected error while saving rating.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Rate Your Doctor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    /* Small, touch-friendly star rater */
    .rating-card { max-width: 760px; }
    .pill { display:inline-block; padding:.25rem .6rem; border-radius:999px; background:#f1f3f5; font-size:.85rem; }
    .factor { margin-bottom: 1rem; }
    .stars { display:inline-flex; gap:.25rem; cursor:pointer; user-select:none; }
    .star { font-size:1.75rem; line-height:1; transition:transform .1s ease-in-out; }
    .star:hover { transform: scale(1.1); }
    .star.inactive { color:#adb5bd; }
    .star.active { color:#f1c40f; }
    .star-btn { background:transparent; border:0; padding:0; margin:0; }
    .star-btn:focus-visible { outline:2px solid #0d6efd; border-radius:4px; }
    .overall-box { font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="bg-light">
  <?php include "navbar_loggedin.php"; ?>

  <div class="container py-5">
    <div class="card shadow-sm rating-card mx-auto">
      <div class="card-body">
        <h3 class="card-title mb-1">Rate Your Visit</h3>
        <p class="text-muted mb-4">Your feedback helps us improve care.</p>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- The form posts back to the same script -->
        <form method="POST" id="rateForm" novalidate>
          <div class="mb-3">
            <span class="pill">Required</span>
            <span class="text-muted ms-2">1 = Poor · 5 = Excellent</span>
          </div>

          <!-- Hidden inputs hold the selected star values -->
          <input type="hidden" name="wait_time" id="wait_time" required>
          <input type="hidden" name="service" id="service" required>
          <input type="hidden" name="communication" id="communication" required>
          <input type="hidden" name="facilities" id="facilities" required>

          <div class="factor">
            <label class="form-label d-block">Wait Time</label>
            <div class="stars" role="radiogroup" aria-label="Wait Time" data-target="wait_time"></div>
          </div>
          <div class="factor">
            <label class="form-label d-block">Service / Bedside Manner</label>
            <div class="stars" role="radiogroup" aria-label="Service" data-target="service"></div>
          </div>
          <div class="factor">
            <label class="form-label d-block">Communication &amp; Explanations</label>
            <div class="stars" role="radiogroup" aria-label="Communication" data-target="communication"></div>
          </div>
          <div class="factor">
            <label class="form-label d-block">Facilities &amp; Cleanliness</label>
            <div class="stars" role="radiogroup" aria-label="Facilities" data-target="facilities"></div>
          </div>

          <div class="mb-3">
            <label for="comment" class="form-label">Comment (optional)</label>
            <textarea name="comment" id="comment" rows="4" class="form-control" placeholder="Anything that could help improve future visits..."></textarea>
          </div>

          <div class="d-flex align-items-center justify-content-between">
            <div class="overall-box">
              <span class="text-muted">Calculated overall:</span>
              <strong id="overallPreview">—</strong>
            </div>
            <div class="d-flex gap-2">
              <a href="my_appointments.php" class="btn btn-outline-secondary">Cancel</a>
              <button class="btn btn-primary">Submit Rating</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Lightweight star rater with keyboard support and live overall preview
    const FACTORS = ['wait_time','service','communication','facilities'];

    function buildStars(container, name) {
      for (let i = 1; i <= 5; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'star-btn';
        btn.setAttribute('role', 'radio');
        btn.setAttribute('aria-checked', 'false');
        btn.setAttribute('aria-label', `${i} star${i>1?'s':''}`);
        btn.dataset.value = String(i);

        const span = document.createElement('span');
        span.className = 'star inactive';
        span.textContent = '★';
        btn.appendChild(span);

        // Hover shows preview; click selects
        btn.addEventListener('mouseenter', () => paint(container, i));
        btn.addEventListener('mouseleave', () => paint(container, getSelected(container)));
        btn.addEventListener('click', () => select(container, name, i));

        // Keyboard arrows/space/enter
        btn.addEventListener('keydown', (e) => {
          const key = e.key;
          let current = getSelected(container) || 0;
          if (key === 'ArrowRight' || key === 'ArrowUp') {
            e.preventDefault();
            current = Math.min(5, current + 1);
            select(container, name, current, true);
            container.querySelector(`[data-value="${current}"]`).focus();
          } else if (key === 'ArrowLeft' || key === 'ArrowDown') {
            e.preventDefault();
            current = Math.max(1, current - 1);
            select(container, name, current, true);
            container.querySelector(`[data-value="${current}"]`).focus();
          } else if (key === ' ' || key === 'Enter') {
            e.preventDefault();
            const v = parseInt(btn.dataset.value, 10);
            select(container, name, v, true);
          }
        });

        container.appendChild(btn);
      }
      paint(container, 0);
    }

    // Paint stars according to value and update ARIA state
    function paint(container, value) {
      container.querySelectorAll('.star').forEach((el, idx) => {
        const v = idx + 1;
        el.classList.toggle('active', v <= value);
        el.classList.toggle('inactive', v > value);
      });
      container.querySelectorAll('[role="radio"]').forEach((btn, idx) => {
        const v = idx + 1;
        btn.setAttribute('aria-checked', v === value ? 'true' : 'false');
      });
    }

    function getSelected(container) {
      const checked = container.querySelector('[role="radio"][aria-checked="true"]');
      return checked ? parseInt(checked.dataset.value, 10) : 0;
    }

    // Select a value for a factor and update hidden input + overall preview
    function select(container, name, value, silent=false) {
      paint(container, value);
      const hidden = document.getElementById(name);
      hidden.value = String(value);
      if (!silent) updateAvgPreview();
    }

    // Show average when all four factors are set
    function updateAvgPreview() {
      const nums = FACTORS.map(f => parseInt(document.getElementById(f).value, 10)).filter(v => !Number.isNaN(v));
      const overall = document.getElementById('overallPreview');
      if (nums.length === FACTORS.length) {
        const avg = nums.reduce((a,b)=>a+b,0) / nums.length;
        overall.textContent = avg.toFixed(2);
      } else {
        overall.textContent = '—';
      }
    }

    // Initialize all star widgets
    document.querySelectorAll('.stars').forEach(starsEl => {
      const target = starsEl.dataset.target;
      buildStars(starsEl, target);
    });

    // Ensure all hidden inputs are set before submitting
    document.getElementById('rateForm').addEventListener('submit', (e) => {
      for (const f of FACTORS) {
        if (!document.getElementById(f).value) {
          e.preventDefault();
          alert('Please rate all categories.');
          return;
        }
      }
    });
  </script>
</body>
</html>
