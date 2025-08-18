<?php
// Step 1: public registration form (no DOB here — collected in step 2)

session_start();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Register | MediVerse</title>
  <!-- Bootstrap for quick styling -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-5">
  <h2 class="mb-4 text-center">Step 1: Create Account</h2>

  <?php if (!empty($_SESSION['register_error'])): ?>
    <!-- One-time server-side error from the previous attempt -->
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['register_error']) ?></div>
    <?php unset($_SESSION['register_error']); ?>
  <?php endif; ?>

  <?php
  // Refill fields if user was bounced back here by validation
  $old = $_SESSION['reg_data'] ?? [];
  ?>

  <!-- Submit to step 2 handler (register_more.php) -->
  <form id="step1-form" action="register_more.php" method="post" class="w-50 mx-auto" novalidate>
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input type="text" name="name" class="form-control" required
             value="<?= htmlspecialchars($old['name'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required
             value="<?= htmlspecialchars($old['username'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required
             value="<?= htmlspecialchars($old['email'] ?? '') ?>">
    </div>

    <!-- Phone must start with 05 and be exactly 10 digits -->
    <div class="mb-3">
      <label class="form-label">Phone</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        class="form-control"
        placeholder="05XXXXXXXX"
        inputmode="numeric"
        maxlength="10"
        pattern="^05\d{8}$"
        title="Enter 10 digits: 05 followed by 8 digits (e.g., 05XXXXXXXX)."
        value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
        required
      >
      <div class="form-text">Format: <strong>05XXXXXXXX</strong> (10 digits).</div>
      <small id="phoneTip" class="text-muted d-block mt-1"></small>
    </div>

    <!-- Government ID: exactly 9 digits -->
    <div class="mb-3">
      <label class="form-label">ID Number (9 digits)</label>
      <input
        type="text"
        name="user_id"
        class="form-control"
        required
        inputmode="numeric"
        pattern="\d{9}"
        minlength="9"
        maxlength="9"
        placeholder="e.g., 012345678"
        value="<?= htmlspecialchars($old['user_id'] ?? '') ?>"
        oninput="this.value=this.value.replace(/\D/g,'').slice(0,9)">
      <div class="form-text">Must be exactly 9 digits.</div>
    </div>

    <!-- Password + show/hide toggle -->
    <div class="mb-2">
      <label class="form-label" for="password">Password</label>
      <div class="input-group">
        <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
        <button type="button" class="btn btn-outline-secondary" id="togglePw"
                aria-label="Show password" aria-pressed="false">Show</button>
      </div>
    </div>

    <!-- Confirm password + show/hide toggle -->
    <div class="mb-2">
      <label class="form-label" for="password2">Confirm Password</label>
      <div class="input-group">
        <input type="password" id="password2" name="password2" class="form-control" required autocomplete="new-password">
        <button type="button" class="btn btn-outline-secondary" id="togglePw2"
                aria-label="Show confirm password" aria-pressed="false">Show</button>
      </div>
      <small id="matchTip" class="text-muted d-block mt-1"></small>
    </div>

    <!-- Simple strength meter -->
    <div class="mb-3">
      <div class="progress" role="progressbar" aria-label="Password strength">
        <div id="pwBar" class="progress-bar" style="width:0%"></div>
      </div>
      <small id="pwTip" class="text-muted d-block mt-1">
        Use at least 8 characters with uppercase, lowercase, number, and symbol.
      </small>
    </div>

    <button type="submit" id="continueBtn" class="btn btn-primary w-100">Continue</button>
  </form>
</div>

<script>
/* Minimal client-side UX helpers: phone normalization, password strength/match, toggles */
(function () {
  const pw    = document.getElementById('password');
  const pw2   = document.getElementById('password2');
  const bar   = document.getElementById('pwBar');
  const tip   = document.getElementById('pwTip');
  const mtip  = document.getElementById('matchTip');
  const form  = document.getElementById('step1-form');

  const btn   = document.getElementById('togglePw');
  const btn2  = document.getElementById('togglePw2');

  const phone = document.getElementById('phone');
  const phoneTip = document.getElementById('phoneTip');

  // Keep only digits, ensure prefix 05, cap at 10 digits
  function normalizePhone(v) {
    v = (v || '').replace(/\D/g,'');
    if (!v.startsWith('05')) {
      v = '05' + v.replace(/^0+/, '').replace(/^5?/, '');
    }
    return v.slice(0, 10);
  }

  // Prefill with 05 for convenience
  if (!phone.value) phone.value = '05';
  phone.addEventListener('focus', () => { if (!phone.value) phone.value = '05'; });

  // Live phone validation hint
  phone.addEventListener('input', () => {
    const cur = phone.value;
    const norm = normalizePhone(cur);
    if (cur !== norm) phone.value = norm;

    const ok = /^05\d{8}$/.test(phone.value);
    phoneTip.className = 'd-block mt-1 ' + (ok ? 'text-muted' : 'text-danger');
    phoneTip.textContent = ok ? '' : 'Phone must be 10 digits: 05 followed by 8 digits.';
  });

  // Password policy regex: upper + lower + number + symbol, min 8 chars
  function strongRegex() {
    return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/;
  }
  // Normalize for comparison (remove zero-width chars, normalize unicode)
  function normForCompare(s) {
    if (!s) return '';
    s = s.replace(/[\u200B-\u200D\u2060\uFEFF]/g, '');
    if (typeof s.normalize === 'function') s = s.normalize('NFC');
    return s;
  }
  // Lightweight strength scoring (just for UI)
  function scorePassword(p) {
    if (!p) return 0;
    let score = 0;
    if (p.length >= 8)  score += 1;
    if (p.length >= 12) score += 1;
    if (/[a-z]/.test(p)) score += 1;
    if (/[A-Z]/.test(p)) score += 1;
    if (/\d/.test(p))    score += 1;
    if (/[^A-Za-z0-9]/.test(p)) score += 1;
    if (!/(.)\1{2,}/.test(p)) score += 1;
    return Math.min(score, 7);
  }
  function updateBar() {
    const s = scorePassword(pw.value);
    const pct = Math.round((s / 7) * 100);
    bar.style.width = pct + '%';
    bar.classList.remove('bg-danger', 'bg-warning', 'bg-success');
    if (pct < 45) { bar.classList.add('bg-danger');  bar.textContent = 'Weak'; }
    else if (pct < 75) { bar.classList.add('bg-warning'); bar.textContent = 'Medium'; }
    else { bar.classList.add('bg-success'); bar.textContent = 'Strong'; }
    updateMatch();
  }
  function updateMatch() {
    const match = normForCompare(pw.value) === normForCompare(pw2.value) && pw.value.length > 0;
    if (pw2.value.length === 0) {
      mtip.className = 'text-muted d-block mt-1'; mtip.textContent = '';
    } else if (match) {
      mtip.className = 'text-success d-block mt-1'; mtip.textContent = 'Passwords match.';
    } else {
      mtip.className = 'text-danger d-block mt-1'; mtip.textContent = 'Passwords do not match.';
    }
  }
  // Toggle password visibility
  function toggle(el, btn){
    const show = el.type === 'password';
    el.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Hide' : 'Show';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    el.focus({ preventScroll: true });
  }
  btn.addEventListener('click',  () => toggle(pw,  btn));
  btn2.addEventListener('click', () => toggle(pw2, btn2));

  pw.addEventListener('input', updateBar);
  pw2.addEventListener('input', updateMatch);
  updateBar();

  // Final client-side checks before submitting to the server
  form.addEventListener('submit', function (e) {
    // Phone format
    phone.value = normalizePhone(phone.value);
    if (!/^05\d{8}$/.test(phone.value)) {
      e.preventDefault();
      phone.focus();
      phoneTip.className = 'text-danger d-block mt-1';
      phoneTip.textContent = 'Phone must be 10 digits: 05 followed by 8 digits.';
      return;
    }
    // Password policy + match
    const p  = normForCompare(pw.value);
    const p2 = normForCompare(pw2.value);
    if (!strongRegex().test(p)) {
      e.preventDefault();
      tip.classList.remove('text-muted');
      tip.classList.add('text-danger');
      tip.textContent = 'Password too weak: use at least 8 chars, include uppercase, lowercase, number, and symbol.';
      pw.focus();
      return;
    }
    if (p !== p2) {
      e.preventDefault();
      mtip.className = 'text-danger d-block mt-1';
      mtip.textContent = 'Passwords do not match.';
      pw2.focus();
      return;
    }
    // Send normalized values to server
    pw.value  = p;
    pw2.value = p2;
  });
})();
</script>

</body>
</html>
