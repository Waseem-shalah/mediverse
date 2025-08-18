<?php
// navbar_public.php — public navbar with same look/feel as the logged-in one

// 1) Base path for your app (so links/logos work even from subfolders)
$APP_BASE = '/labs/Doctors';

// Helper to build app-relative URLs
function app_url(string $path=''): string {
  global $APP_BASE;
  return rtrim($APP_BASE, '/') . '/' . ltrim($path, '/');
}
$logo_src = app_url('assets/images/logo.png');

// 2) "Active" link helper based on current script name
$current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
function is_active($file) {
  global $current;
  return $current === basename($file) ? ' aria-current="page"' : '';
}
?>
<style>
  /* Theme tokens (light + dark via media query below) */
  :root{
    --bg: rgba(255,255,255,.65);
    --bg-dark: rgba(17,24,39,.65);
    --fg: #0f172a;
    --fg-subtle:#475569;
    --chip:#0ea5e9;
    --chip-hover:#0284c7;
    --danger:#ef4444;
    --ring: rgba(59,130,246,.35);
  }

  /* Sticky, blurred, gradient nav container */
  .mv-navbar{position:sticky;top:0;z-index:1000;}
  .mv-nav{
    backdrop-filter: saturate(160%) blur(12px);
    -webkit-backdrop-filter: saturate(160%) blur(12px);
    background: linear-gradient(90deg,#60a5fa20,#22d3ee20), var(--bg);
    border-bottom: 1px solid rgba(2,6,23,.08);
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 16px; gap:12px;
    font-family: 'Segoe UI', system-ui, -apple-system, Roboto, sans-serif;
  }

  /* Brand block (logo + title) */
  .mv-brand{display:flex; align-items:center; gap:10px; text-decoration:none; color: var(--fg);}
  .mv-logo{height:28px; width:auto; border-radius:6px; box-shadow:0 2px 8px rgba(2,6,23,.08)}
  .mv-title{font-weight:800; font-size:20px; letter-spacing:.2px}

  /* Right side: links + guest capsule */
  .mv-right{display:flex; align-items:center; gap:10px}
  .mv-links{display:flex; align-items:center; gap:8px; flex-wrap:wrap}

  /* “Chip” buttons used as nav links */
  .mv-chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:999px;
    background:linear-gradient(180deg,#e0f2fe,#e0f2fe00);
    color:#0c4a6e; border:1px solid #bae6fd;
    text-decoration:none; font-weight:700; font-size:14px;
    transition: transform .12s ease, background .2s ease, box-shadow .2s ease, color .12s ease;
    white-space:nowrap;
  }
  .mv-chip:hover{transform: translateY(-1px); box-shadow:0 6px 18px rgba(2,6,23,.08)}
  .mv-chip.logout{ background:linear-gradient(180deg,#fee2e2,#fee2e200); color:#7f1d1d; border-color:#fecaca }

  /* Guest capsule (matches logged-in nav visual) */
  .mv-profile{
    display:flex; align-items:center; gap:10px; padding:6px 10px;
    border-radius:999px; border:1px solid rgba(2,6,23,.08);
    background: rgba(255,255,255,.55);
  }
  .mv-avatar{
    width:28px; height:28px; border-radius:50%;
    display:grid; place-items:center; font-weight:800; font-size:14px;
    background:linear-gradient(135deg,#38bdf8,#6366f1);
    color:white; box-shadow:0 2px 10px rgba(2,6,23,.15);
  }
  .mv-name{font-weight:700; color:var(--fg); font-size:14px}
  .mv-role{color:var(--fg-subtle); font-size:12px}

  /* Mobile menu toggle (checkbox hack) */
  .mv-toggle{display:none}
  .mv-menu-btn{
    display:none; border:1px solid rgba(2,6,23,.1);
    background:white; border-radius:10px; padding:8px 10px; font-weight:700;
  }

  /* Responsive: collapse links under a toggle below 960px */
  @media (max-width: 960px){
    .mv-menu-btn{display:inline-flex; gap:8px; align-items:center}
    .mv-right{flex-wrap:wrap}
    .mv-links{display:none; width:100%; padding:8px 0; border-top:1px dashed rgba(2,6,23,.12); margin-top:8px}
    .mv-toggle:checked ~ .mv-right .mv-links{display:flex}
  }

  /* Keyboard focus ring for accessibility */
  .mv-chip:focus-visible, .mv-menu-btn:focus-visible, .mv-brand:focus-visible{
    outline: 3px solid var(--ring);
    outline-offset: 2px;
  }

  /* Dark mode polish */
  @media (prefers-color-scheme: dark){
    :root{ --bg: var(--bg-dark); --fg:#e5e7eb; --fg-subtle:#94a3b8 }
    .mv-nav{ border-bottom-color: rgba(255,255,255,.08) }
    .mv-profile{ background: rgba(17,24,39,.45); border-color: rgba(255,255,255,.08) }
    .mv-chip{ background:linear-gradient(180deg,#0b3b50,#0b3b5000); color:#e0f2fe; border-color:#164e63 }
    .mv-chip.logout{ background:linear-gradient(180deg,#4c1d1d,#4c1d1d00); color:#fecaca; border-color:#7f1d1d }
  }
</style>

<div class="mv-navbar">
  <nav class="mv-nav">
    <!-- Brand -->
    <a class="mv-brand" href="<?= htmlspecialchars(app_url('index.php')) ?>">
      <img class="mv-logo" src="<?= htmlspecialchars($logo_src) ?>" alt="MediVerse logo">
      <span class="mv-title">MediVerse</span>
    </a>

    <!-- Mobile toggle (controls visibility of .mv-links) -->
    <input id="mvNavToggle" class="mv-toggle" type="checkbox" aria-label="Toggle menu">
    <label class="mv-menu-btn" for="mvNavToggle" aria-controls="mvLinks">☰ Menu</label>

    <div class="mv-right">
      <!-- Primary nav (chips). aria-current is set on the active page -->
      <div id="mvLinks" class="mv-links" aria-label="Primary navigation">
        <a class="mv-chip" href="<?= htmlspecialchars(app_url('index.php')) ?>"<?= is_active('index.php') ?>><span class="ic">🏠</span> Home</a>
        <a class="mv-chip" href="<?= htmlspecialchars(app_url('about.php')) ?>"<?= is_active('about.php') ?>><span class="ic">ℹ️</span> About</a>
        <a class="mv-chip" href="<?= htmlspecialchars(app_url('contact.php')) ?>"<?= is_active('contact.php') ?>><span class="ic">✉️</span> Contact</a>
        <a class="mv-chip" href="<?= htmlspecialchars(app_url('login.php')) ?>"<?= is_active('login.php') ?>><span class="ic">🔑</span> Login</a>
        <a class="mv-chip" href="<?= htmlspecialchars(app_url('register.php')) ?>"<?= is_active('register.php') ?>><span class="ic">📝</span> Register</a>
        <a class="mv-chip" href="<?= htmlspecialchars(app_url('doctor_apply.php')) ?>"<?= is_active('doctor_apply.php') ?>><span class="ic">🩺</span> Apply as Doctor</a>
      </div>

      <!-- Guest capsule (keeps consistent layout with logged-in nav) -->
      <div class="mv-profile" aria-label="Account">
        <div class="mv-avatar" aria-hidden="true">G</div>
        <div>
          <div class="mv-name">Guest</div>
          <div class="mv-role">Public</div>
        </div>
      </div>
    </div>
  </nav>
</div>
