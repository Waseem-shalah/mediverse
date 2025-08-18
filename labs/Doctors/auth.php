<?php
// Start the session if it isn't already active.
// Many apps include this helper from multiple places, so guard it.
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * _abs_url(string $rel): string
 * Build a fully-qualified (absolute) URL from a relative path.
 * - Respects HTTPS if the current request is over TLS.
 * - Works even when the app lives in a subfolder (e.g., /labs/Doctors).
 * - Safe for redirects and links.
 *
 * Examples:
 *   _abs_url('login.php')     -> https://example.com/labs/Doctors/login.php
 *   _abs_url('/login.php')    -> https://example.com/login.php
 */
function _abs_url($rel) {
  // Detect scheme (http vs https)
  $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $scheme = $https ? 'https' : 'http';

  // Host header provided by the web server (e.g., example.com:8080)
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

  // Base path of the current script (directory only, no filename)
  // e.g., if SCRIPT_NAME = /labs/Doctors/some/page.php -> base = /labs/Doctors/some
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');

  // Normalize base if it's just a root slash
  if ($base === '/' || $base === '\\') $base = '';

  // Ensure $rel starts with a slash so concatenation is predictable
  if ($rel && $rel[0] !== '/') $rel = '/' . $rel;

  // Return: scheme://host + base + /relative
  return $scheme . '://' . $host . $base . $rel;
}

/**
 * _go(string $rel): never
 * Redirect to a relative path (converted to an absolute URL) and exit.
 * - Uses Location header when possible.
 * - Includes HTML/JS fallback if headers were already sent.
 */
function _go($rel) {
  $url = _abs_url($rel);

  // Primary redirect (HTTP 302). Safe and standard.
  header('Location: ' . $url, true, 302);

  // Fallback if headers were already sent earlier:
  // - <meta> refresh for older browsers
  // - JS location.replace for modern browsers
  echo '<!doctype html>'
     . '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '">'
     . '<script>location.replace(' . json_encode($url) . ');</script>';
  exit();
}

/**
 * require_auth(?string $needRole = null): void
 * Gatekeeper for protected pages.
 * - Call at the very top of any file that requires a logged-in user.
 * - Optionally enforce a specific role (e.g., 'patient', 'doctor', 'admin').
 *
 * Behavior:
 * - If no session user_id => send to login.
 * - If $needRole is provided and doesn't match the user's role => send to login.
 *
 * Notes:
 * - Role comparison is case-insensitive.
 * - If you need multiple roles, pass a substring you expect to match, or
 *   enhance this function to accept arrays (kept simple here).
 *
 * Usage:
 *   require_auth();              // any logged-in user
 *   require_auth('patient');     // only patients
 *   require_auth('doctor');      // only doctors
 *   require_auth('admin');       // only admins
 */
function require_auth($needRole = null) {
  // Not logged in? Go to login
  if (empty($_SESSION['user_id'])) _go('login.php');

  // Normalize role string from session
  $role = strtolower(trim((string)($_SESSION['role'] ?? '')));

  // If a specific role is required, ensure it matches
  if ($needRole && stripos($role, $needRole) === false) _go('login.php');
}
