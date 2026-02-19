<?php
// Staff Login Page - Separate from customer login
require_once 'Security.php';
require_once 'CSRF.php';

Security::initSession();

// Redirect if already logged in as staff
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'customer') {
        // Customer tried to access staff login while logged in - send to customer area
        header('Location: onlineordering.php');
    } else {
        header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<title>Staff Login - Calloway Pharmacy</title>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<?php echo CSRF::getTokenMeta(); ?>
<style>
  :root {
    --primary-color: #0a74da;
    --primary-dark: #094a92;
    --secondary-color: #27ae60;
    --text-color: #222;
    --bg-color: rgba(254, 254, 255, 0.95);
    --bg-color-rgb: 254, 254, 255;
    --input-border: #a9d1ff;
    --label-color: #78aaff;
    --shadow-color: rgba(10, 116, 218, 0.4);
    --overlay-gradient: rgba(10, 116, 218, 0.5);
    --animation-duration: 0.3s;
    --header-bg: rgba(9, 74, 146, 0.9);
    --header-bg-rgb: 9, 74, 146;
    --dropdown-bg: white;
    --dropdown-text: #333;
    --dropdown-hover: #f0f7ff;
    --dropdown-shadow: rgba(0, 0, 0, 0.1);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  [data-theme="dark"] {
    --primary-color: #3b82f6;
    --primary-dark: #1e64c8;
    --secondary-color: #10b981;
    --text-color: #e2e8f0;
    --bg-color: rgba(15, 23, 42, 0.95);
    --bg-color-rgb: 15, 23, 42;
    --input-border: #3b82f6;
    --label-color: #60a5fa;
    --shadow-color: rgba(59, 130, 246, 0.4);
    --overlay-gradient: rgba(15, 23, 42, 0.7);
    --header-bg: rgba(15, 23, 42, 0.9);
    --header-bg-rgb: 15, 23, 42;
    --dropdown-bg: #1e293b;
    --dropdown-text: #e2e8f0;
    --dropdown-hover: #334155;
    --dropdown-shadow: rgba(0, 0, 0, 0.3);
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  }

  body {
    display: flex;
    flex-direction: row;
    min-height: 100vh;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: var(--text-color);
    -webkit-font-smoothing: antialiased;
    position: relative;
    overflow-x: hidden;
    transition: var(--transition-smooth);
  }

  .login-side {
    width: 25%;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: var(--bg-color);
    position: relative;
    z-index: 10;
    padding: 2rem;
    padding-bottom: 5rem;
    box-sizing: border-box;
    overflow-y: auto;
    transition: var(--transition-smooth);
  }

  .image-side {
    width: 75%;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
  }

  .wallpaper {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    background-size: cover;
    background-position: center;
  }

  .wallpaper1 { background-image: url('wallpaper1.jpg'); z-index: 2; animation: fadeTopImage 16s infinite; }
  .wallpaper2 { background-image: url('wallpaper2.jpg'); z-index: 1; }

  @keyframes fadeTopImage {
    0%, 45% { opacity: 1; }
    50%, 95% { opacity: 0; }
    100% { opacity: 1; }
  }

  .login-side::after {
    content: '';
    position: absolute;
    top: 0; right: -50px; width: 50px; height: 100%;
    background: linear-gradient(to right, var(--bg-color), transparent);
    z-index: 5;
    transition: var(--transition-smooth);
  }

  header {
    position: fixed;
    top: 0; left: 0; width: 100%;
    background-color: var(--header-bg);
    color: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    z-index: 1000;
    animation: slideDown var(--animation-duration) ease-out;
    transition: var(--transition-smooth);
    backdrop-filter: blur(10px);
  }

  @keyframes slideDown {
    from { transform: translateY(-100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }

  .container {
    width: 100%;
    max-width: 320px;
    padding: 2rem;
    position: relative;
    background: transparent;
    border-radius: 20px;
    box-sizing: border-box;
    overflow: visible;
    z-index: 10;
    animation: fadeInUp var(--animation-duration) ease-out;
    transition: var(--transition-smooth);
  }

  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  h2 {
    text-align: center;
    color: var(--primary-color);
    margin-bottom: 2.4rem;
    font-weight: 700;
    font-size: 2rem;
    user-select: none;
    position: relative;
    transition: var(--transition-smooth);
  }

  h2:after {
    content: '';
    position: absolute;
    bottom: -10px; left: 50%; transform: translateX(-50%);
    width: 60px; height: 4px;
    background: var(--primary-color);
    border-radius: 2px;
    transition: var(--transition-smooth);
  }

  form { position: relative; z-index: 10; }

  .form-group {
    position: relative;
    margin-bottom: 2.4rem;
    animation: slideUp var(--animation-duration) ease-out;
    animation-fill-mode: both;
    transition: var(--transition-smooth);
  }

  .form-group:nth-child(2) { animation-delay: 0.1s; }

  @keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  input {
    width: 100%;
    padding: 1.2rem;
    border-radius: 15px;
    border: 2.5px solid var(--input-border);
    background: var(--bg-color);
    font-size: 1.1rem;
    color: var(--primary-dark);
    outline: none;
    transition: var(--transition-smooth);
    box-shadow: 0 3px 10px rgba(10, 116, 218, 0.1);
  }

  input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 15px var(--shadow-color);
    transform: translateY(-2px);
  }

  label {
    position: absolute;
    color: var(--label-color);
    font-weight: 600;
    left: 18px; top: 1.2rem;
    font-size: 1rem;
    pointer-events: none;
    background: var(--bg-color);
    padding: 0 8px;
    transition: var(--transition-smooth);
    user-select: none;
    border-radius: 8px;
  }

  input:focus + label,
  input:not(:placeholder-shown) + label {
    top: -0.7rem; left: 12px;
    font-size: 0.8rem;
    color: var(--primary-color);
    font-weight: 700;
    box-shadow: 0 0 10px var(--shadow-color);
  }

  button {
    width: 100%;
    background: var(--primary-color);
    color: white;
    padding: 1.2rem 0;
    font-size: 1.2rem;
    font-weight: 800;
    border-radius: 40px;
    box-shadow: 0 8px 20px var(--shadow-color);
    overflow: hidden;
    position: relative;
    border: none;
    cursor: pointer;
    user-select: none;
    transition: var(--transition-smooth);
    margin-top: 1rem;
    animation: slideUp var(--animation-duration) ease-out;
    animation-delay: 0.2s;
    animation-fill-mode: both;
    transform: translateZ(0);
  }

  button:hover {
    background: var(--primary-dark);
    box-shadow: 0 12px 30px var(--shadow-color);
    transform: translateY(-3px);
  }

  button:active {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px var(--shadow-color);
  }

  button .ripple {
    position: absolute;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 50%;
    transform: scale(0);
    animation: ripple 0.4s linear;
    pointer-events: none;
  }

  @keyframes ripple { to { transform: scale(4); opacity: 0; } }

  .pill {
    position: absolute;
    border-radius: 9999px;
    opacity: 0.75;
    box-shadow: 0 6px 20px var(--shadow-color), inset 0 0 8px rgba(255, 255, 255, 0.6);
    transition: var(--transition-smooth);
    animation: floatPill 8s ease-in-out infinite alternate;
  }

  @keyframes floatPill {
    0% { transform: translateY(0) rotate(var(--rotation)); }
    100% { transform: translateY(-15px) rotate(var(--rotation)); }
  }

  .pill.capsule1 {
    width: 170px; height: 40px;
    background: var(--primary-color);
    box-shadow: 0 6px 22px var(--shadow-color), inset 0 0 14px 4px rgba(255, 255, 255, 0.85), inset -4px 0 4px var(--shadow-color);
    top: 10px; left: -52px;
    --rotation: 18deg;
    transform: rotate(var(--rotation));
  }

  .pill.capsule1::before {
    content: "";
    position: absolute;
    top: 6px; left: 5px;
    width: 70px; height: 24px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.4);
    pointer-events: none;
  }

  .pill.capsule2 {
    width: 110px; height: 30px;
    background: var(--primary-dark);
    box-shadow: 0 5px 14px var(--shadow-color), inset 0 0 10px 2px rgba(255, 255, 255, 0.7), inset 3px 0 3px var(--shadow-color);
    bottom: -30px; right: 20px;
    --rotation: -14deg;
    transform: rotate(var(--rotation));
    animation-delay: 1.5s;
  }

  .pill.capsule2::before {
    content: "";
    position: absolute;
    top: 5px; right: 6px;
    width: 55px; height: 18px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.5);
    pointer-events: none;
  }

  .pill.capsule3 {
    width: 170px; height: 40px;
    background: var(--primary-dark);
    box-shadow: 0 6px 22px var(--shadow-color), inset 0 0 12px 3px rgba(255, 255, 255, 0.8), inset -6px 0 8px var(--shadow-color);
    bottom: 80px; left: -55px;
    --rotation: -22deg;
    transform: rotate(var(--rotation));
    animation-delay: 2.2s;
  }

  .container:hover .pill { opacity: 0.9; filter: brightness(1.1); }

  footer {
    position: fixed;
    bottom: 0; left: 0; width: 100%;
    padding: 1rem;
    text-align: center;
    font-size: 0.9rem;
    color: #ffffff;
    z-index: 10;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
    background-color: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(5px);
    transition: var(--transition-smooth);
  }

  .modal {
    display: none;
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: var(--bg-color);
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    text-align: center;
    z-index: 2000;
    transition: var(--transition-smooth);
    backdrop-filter: blur(10px);
  }

  .modal p { font-size: 1.2rem; font-weight: bold; margin-bottom: 1.5rem; }
  .modal-success p { color: var(--primary-color); }
  .modal-error p { color: #d9534f; }

  .modal button {
    margin-top: 0.5rem;
    padding: 0.5rem 1rem;
    width: auto;
    min-width: 100px;
    font-size: 1rem;
    border-radius: 6px;
    position: relative;
    overflow: hidden;
  }

  .modal-success button { background: var(--primary-color); }
  .modal-error button { background: #d9534f; }

  [data-theme="dark"] body { background-color: #1a202c; }

  .staff-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(10, 116, 218, 0.1);
    color: var(--primary-color);
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-bottom: 1rem;
    text-align: center;
    justify-content: center;
    width: fit-content;
    margin-left: auto;
    margin-right: auto;
  }

  .register-note {
    text-align: center;
    color: var(--label-color);
    font-size: 0.82rem;
    margin-top: 0.8rem;
    position: relative;
    z-index: 10;
  }

  .register-note a {
    color: var(--primary-color);
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
  }

  .register-note a:hover { text-decoration: underline; }

  .dropdown { position: relative; display: inline-block; }
  .dropdown-toggle {
    background: none; border: none; color: white;
    font-size: 1.5rem; cursor: pointer; padding: 0.5rem;
    border-radius: 50%; transition: background-color 0.3s ease;
  }
  .dropdown-toggle:hover { background-color: rgba(255, 255, 255, 0.1); }
  .dropdown-menu {
    position: absolute; right: 0; top: 100%;
    background-color: var(--dropdown-bg);
    min-width: 180px; border-radius: 8px;
    box-shadow: 0 3px 15px var(--dropdown-shadow);
    opacity: 0; visibility: hidden;
    transform: translateY(-10px);
    transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s;
    z-index: 1000; overflow: hidden;
  }
  .dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
  .dropdown-item {
    display: flex; align-items: center; padding: 0.8rem 1.2rem;
    color: var(--dropdown-text); text-decoration: none;
    transition: background-color 0.2s; cursor: pointer;
  }
  .dropdown-item:hover { background-color: var(--dropdown-hover); }
  .dropdown-item i { margin-right: 0.5rem; width: 20px; text-align: center; font-size: 1rem; }
  .divider { height: 1px; margin: 0.3rem 0; background-color: rgba(0, 0, 0, 0.1); }
  [data-theme="dark"] .divider { background-color: rgba(255, 255, 255, 0.1); }

  .preload { display: none; background-image: url('wallpaper1.jpg'), url('wallpaper2.jpg'); }

  @media (max-width: 768px) {
    body { flex-direction: column; }
    .login-side, .image-side { width: 100%; }
    .login-side { min-height: calc(100vh - 60px); padding-bottom: 5rem; }
    .image-side { display: none; }
    footer { position: fixed; height: 60px; }
  }

  @media (max-width: 400px) {
    .container { max-width: 90vw; padding: 2rem 1.5rem 2.5rem 1.5rem; margin-left: 5%; }
  }
</style>
</head>
<body>
  <div class="preload"></div>
  <header>
    <div style="font-size: 1.5rem; font-weight: bold; color: white;">Calloway Pharmacy — Staff Portal</div>
    <div class="dropdown">
      <button class="dropdown-toggle" id="menu-toggle" aria-label="Menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="5" cy="12" r="2" fill="currentColor"/>
          <circle cx="12" cy="12" r="2" fill="currentColor"/>
          <circle cx="19" cy="12" r="2" fill="currentColor"/>
        </svg>
      </button>
      <div class="dropdown-menu" id="dropdown-menu">
        <div class="dropdown-item" id="theme-light">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
          <span>Light Mode</span>
        </div>
        <div class="dropdown-item" id="theme-dark">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
          <span>Dark Mode</span>
        </div>
        <div class="divider"></div>
        <a href="login.php" class="dropdown-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span>Customer Login</span>
        </a>
      </div>
    </div>
  </header>

  <div class="login-side">
    <div class="container" role="form" aria-labelledby="login-title">
      <h2 id="login-title" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1rem;">
        <img src="logo.png" alt="Calloway Pharmacy Logo" style="height: 200px;">
      </h2>

      <div class="staff-badge">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        Staff Access Only
      </div>

      <form onsubmit="handleStaffLogin(event)" novalidate>
        <?php echo CSRF::getTokenField(); ?>
        <div class="form-group">
          <input id="username" name="username" type="text" placeholder=" " required autocomplete="username" aria-required="true" />
          <label for="username">Username</label>
        </div>
        <div class="form-group">
          <input id="password" name="password" type="password" placeholder=" " required autocomplete="current-password" aria-required="true" />
          <label for="password">Password</label>
        </div>
        <button type="submit" aria-label="Staff Log In button">Staff Log In</button>
      </form>
      <p class="register-note" style="margin-top: 1.2rem;">Customer? <a href="login.php">Log in here &rarr;</a></p>

      <!-- Capsule pills -->
      <div aria-hidden="true" class="pill capsule1"></div>
      <div aria-hidden="true" class="pill capsule2"></div>
      <div aria-hidden="true" class="pill capsule3"></div>
    </div>
  </div>

  <div class="image-side">
    <div class="wallpaper wallpaper1"></div>
    <div class="wallpaper wallpaper2"></div>
  </div>

  <div id="login-success-modal" class="modal modal-success">
    <p>Login Successful!</p>
    <button onclick="redirectToDashboard()">Okay</button>
  </div>

  <div id="login-error-modal" class="modal modal-error">
    <p>Invalid credentials or unauthorized.</p>
    <button onclick="closeErrorModal()">Okay</button>
  </div>

  <footer>
    &copy; 2025 Calloway Pharmacy. All rights reserved.
  </footer>

  <script>
    // Dropdown
    const menuToggle = document.getElementById('menu-toggle');
    const dropdownMenu = document.getElementById('dropdown-menu');
    menuToggle.addEventListener('click', () => dropdownMenu.classList.toggle('show'));
    document.addEventListener('click', (e) => {
      if (!menuToggle.contains(e.target) && !dropdownMenu.contains(e.target)) dropdownMenu.classList.remove('show');
    });

    // Theme
    const htmlEl = document.documentElement;
    document.getElementById('theme-light').addEventListener('click', () => { setTheme('light'); dropdownMenu.classList.remove('show'); });
    document.getElementById('theme-dark').addEventListener('click', () => { setTheme('dark'); dropdownMenu.classList.remove('show'); });
    function setTheme(t) { htmlEl.setAttribute('data-theme', t); localStorage.setItem('calloway_theme', t); }
    const saved = localStorage.getItem('calloway_theme');
    if (saved) htmlEl.setAttribute('data-theme', saved);

    // CSRF
    function getCsrfToken() {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    // Modals
    function redirectToDashboard() {
      document.getElementById('login-success-modal').style.display = 'none';
      window.location.href = 'index.php';
    }
    function closeErrorModal() {
      document.getElementById('login-error-modal').style.display = 'none';
    }
    function showErrorModal(msg) {
      const m = document.getElementById('login-error-modal');
      m.querySelector('p').textContent = msg;
      m.style.display = 'block';
    }

    // Staff Login
    async function handleStaffLogin(e) {
      e.preventDefault();
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value.trim();
      const btn = e.target.querySelector('button[type="submit"]');

      if (!username || !password) { showErrorModal('Please enter both username and password'); return; }

      btn.disabled = true;
      btn.textContent = 'Logging in...';

      try {
        const res = await fetch('login_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
          body: JSON.stringify({ username, password, login_type: 'admin' })
        });
        const data = await res.json();

        if (data.success) {
          document.getElementById('login-success-modal').style.display = 'block';
        } else {
          showErrorModal(data.message || 'Invalid credentials or unauthorized.');
        }
      } catch (err) {
        console.error('Login error:', err);
        showErrorModal('An error occurred. Please try again.');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Staff Log In';
      }
    }
  </script>
  <script src="security.js"></script>
</body>
</html>
