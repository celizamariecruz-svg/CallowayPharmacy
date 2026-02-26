<?php
// Initialize security for login page
require_once 'Security.php';
require_once 'CSRF.php';

Security::initSession();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'customer') {
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
<title>Login - Calloway Pharmacy</title>
<!-- Favicon -->
<link rel="icon" type="image/png" href="logo-removebg-preview.png">
<link rel="shortcut icon" type="image/png" href="logo-removebg-preview.png">
<!-- CSRF Token Meta Tag -->
<?php echo CSRF::getTokenMeta(); ?>
<style>
  /* Global styling */
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
    -moz-osx-font-smoothing: grayscale;
    position: relative;
    overflow-x: hidden;
    transition: var(--transition-smooth);
  }

  /* Left side - Login section */
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
    padding-bottom: 5rem; /* Add padding for footer */
    box-sizing: border-box;
    overflow-y: auto;
    transition: var(--transition-smooth);
  }

  /* Right side - Image section */
  .image-side {
    width: 75%;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
  }

  .wallpaper {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
  }

  .wallpaper1 {
    background-image: url('wallpaper1.jpg');
    z-index: 2;
    animation: fadeTopImage 16s infinite;
  }

  .wallpaper2 {
    background-image: url('wallpaper2.jpg');
    z-index: 1;
  }

  @keyframes fadeTopImage {
    0%, 45% {
      opacity: 1;
    }
    50%, 95% {
      opacity: 0;
    }
    100% {
      opacity: 1;
    }
  }

  /* Adjust the login side gradient fade */
  .login-side::after {
    content: '';
    position: absolute;
    top: 0;
    right: -50px;
    width: 50px;
    height: 100%;
    background: linear-gradient(to right, var(--bg-color), transparent);
    z-index: 5;
    transition: var(--transition-smooth);
  }

  header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
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
    from {
      transform: translateY(-100%);
      opacity: 0;
    }
    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  .back-button {
    background: white;
    color: var(--primary-dark);
    text-decoration: none;
    font-weight: 700;
    padding: 0.6rem 1.2rem;
    border-radius: 30px;
    transition: all 0.3s ease;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
    z-index: 1;
  }

  .back-button:before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: var(--primary-color);
    transition: transform 0.3s ease;
    z-index: -1;
    border-radius: 30px;
  }

  .back-button:hover {
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
  }

  .back-button:hover:before {
    transform: translateX(100%);
  }

  /* Dropdown menu */
  .dropdown {
    position: relative;
    display: inline-block;
  }

  .dropdown-toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background-color 0.3s ease;
  }

  .dropdown-toggle:hover {
    background-color: rgba(255, 255, 255, 0.1);
  }

  .dropdown-menu {
    position: absolute;
    right: 0;
    top: 100%;
    background-color: var(--dropdown-bg);
    min-width: 180px;
    border-radius: 8px;
    box-shadow: 0 3px 15px var(--dropdown-shadow);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s;
    z-index: 1000;
    overflow: hidden;
  }

  .dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  .dropdown-item {
    display: flex;
    align-items: center;
    padding: 0.8rem 1.2rem;
    color: var(--dropdown-text);
    text-decoration: none;
    transition: background-color 0.2s;
    cursor: pointer;
  }

  .dropdown-item:hover {
    background-color: var(--dropdown-hover);
  }

  .dropdown-item i {
    margin-right: 0.5rem;
    width: 20px;
    text-align: center;
    font-size: 1rem;
  }

  .divider {
    height: 1px;
    margin: 0.3rem 0;
    background-color: rgba(0, 0, 0, 0.1);
  }

  [data-theme="dark"] .divider {
    background-color: rgba(255, 255, 255, 0.1);
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
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
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
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 4px;
    background: var(--primary-color);
    border-radius: 2px;
    transition: var(--transition-smooth);
  }

  form {
    position: relative;
    z-index: 10;
  }

  .form-group {
    position: relative;
    margin-bottom: 2.4rem;
    animation: slideUp var(--animation-duration) ease-out;
    animation-fill-mode: both;
    transition: var(--transition-smooth);
  }

  .form-group:nth-child(2) {
    animation-delay: 0.1s;
  }

  @keyframes slideUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  input {
    width: 100%;
    padding: 1.2rem 1.2rem 1.2rem 1.2rem;
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
    left: 18px;
    top: 1.2rem;
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
    top: -0.7rem;
    left: 12px;
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

  /* Ripple effect */
  button .ripple {
    position: absolute;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 50%;
    transform: scale(0);
    animation: ripple 0.4s linear;
    pointer-events: none;
  }

  @keyframes ripple {
    to {
      transform: scale(4);
      opacity: 0;
    }
  }

  /* High quality capsule pills with realistic gradients and shine */
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

  /* Pill capsules with two halves: left half white glossy, right half blue glossy */
  .pill.capsule1 {
    width: 170px;
    height: 40px;
    background: var(--primary-color);
    background-repeat: no-repeat;
    box-shadow: 0 6px 22px var(--shadow-color), inset 0 0 14px 4px rgba(255, 255, 255, 0.85), inset -4px 0 4px var(--shadow-color);
    top: 10px;
    left: -52px;
    --rotation: 18deg;
    transform: rotate(var(--rotation));
    animation-delay: 0s;
  }

  /* White half highlight gloss shine */
  .pill.capsule1::before {
    content: "";
    position: absolute;
    top: 6px;
    left: 5px;
    width: 70px;
    height: 24px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.4);
    pointer-events: none;
  }

  .pill.capsule2 {
    width: 110px;
    height: 30px;
    background: var(--primary-dark);
    box-shadow: 0 5px 14px var(--shadow-color), inset 0 0 10px 2px rgba(255, 255, 255, 0.7), inset 3px 0 3px var(--shadow-color);
    bottom: -30px;
    right: 20px;
    --rotation: -14deg;
    transform: rotate(var(--rotation));
    animation-delay: 1.5s;
  }

  .pill.capsule2::before {
    content: "";
    position: absolute;
    top: 5px;
    right: 6px;
    width: 55px;
    height: 18px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.5);
    pointer-events: none;
  }

  .pill.capsule3 {
    width: 170px;
    height: 40px;
    background: var(--primary-dark);
    box-shadow: 0 6px 22px var(--shadow-color), inset 0 0 12px 3px rgba(255, 255, 255, 0.8), inset -6px 0 8px var(--shadow-color);
    bottom: 80px;
    left: -55px;
    --rotation: -22deg;
    transform: rotate(var(--rotation));
    animation-delay: 2.2s;
  }
  
  .container:hover .pill {
    opacity: 0.9;
    filter: brightness(1.1);
  }

  footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
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

  /* Responsive tweaks */
  @media (max-width: 768px) {
    body {
      flex-direction: column;
    }
    
    .login-side, .image-side {
      width: 100%;
    }
    
    .login-side {
      min-height: calc(100vh - 60px); /* Subtract footer height */
      padding-bottom: 5rem; /* Add padding for footer */
    }
    
    .image-side {
      display: none;
    }

    footer {
      position: fixed;
      height: 60px;
    }
  }

  @media (max-width: 400px) {
    .container {
      max-width: 90vw;
      padding: 2rem 1.5rem 2.5rem 1.5rem;
      margin-left: 5%;
    }
  }

  /* Tab Switcher */
  .tab-switcher {
    display: flex;
    margin-bottom: 1.8rem;
    background: rgba(10, 116, 218, 0.08);
    border-radius: 14px;
    padding: 4px;
    position: relative;
    z-index: 10;
  }

  .tab-btn {
    flex: 1;
    padding: 0.7rem 0;
    border: none;
    background: transparent;
    color: var(--label-color);
    font-weight: 700;
    font-size: 0.95rem;
    cursor: pointer;
    border-radius: 11px;
    transition: var(--transition-smooth);
    position: relative;
    z-index: 2;
  }

  .tab-btn.active {
    background: var(--primary-color);
    color: white;
    box-shadow: 0 4px 12px var(--shadow-color);
  }

  .tab-btn:not(.active):hover {
    color: var(--primary-color);
  }

  .tab-panel {
    display: none;
  }

  .tab-panel.active {
    display: block;
    animation: fadeInUp 0.3s ease-out;
  }

  .register-note {
    text-align: center;
    color: var(--text-light, var(--label-color));
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

  .register-note a:hover {
    text-decoration: underline;
  }

  /* Modal styling with dark mode support */
  .modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
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

  .modal p {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 1.5rem;
  }

  .modal-success p {
    color: var(--primary-color);
  }

  .modal-error p {
    color: #d9534f;
  }

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

  .modal-success button {
    background: var(--primary-color);
  }

  .modal-error button {
    background: #d9534f;
  }

  [data-theme="dark"] body {
    background-color: #1a202c;
  }

  /* Add this at the beginning of the style section */
  .preload {
    display: none;
    background-image: url('wallpaper1.jpg'), url('wallpaper2.jpg');
  }
</style>
</head>
<body>
  <div class="preload"></div>
  <header>
    <div style="font-size: 1.5rem; font-weight: bold; color: white;">Calloway Pharmacy</div>
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
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"></circle>
            <line x1="12" y1="1" x2="12" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="23"></line>
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
            <line x1="1" y1="12" x2="3" y2="12"></line>
            <line x1="21" y1="12" x2="23" y2="12"></line>
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
          </svg>
          <span>Light Mode</span>
        </div>
        <div class="dropdown-item" id="theme-dark">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <span>Dark Mode</span>
        </div>
        <div class="divider"></div>
        <a href="index.php" class="dropdown-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          <span>Home</span>
        </a>
      </div>
    </div>
  </header>

  <div class="login-side">
    <div class="container" role="form" aria-labelledby="login-title">
      <h2 id="login-title" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 1rem;">
        <img src="logo-removebg-preview.png" alt="Calloway Pharmacy Logo" style="height: 200px; filter: drop-shadow(0 0 6px rgba(255,255,255,0.8));">
      </h2>

      <!-- Tab Switcher -->
      <div class="tab-switcher">
        <button class="tab-btn active" id="tab-login-btn" onclick="switchTab('login')">Login</button>
        <button class="tab-btn" id="tab-register-btn" onclick="switchTab('register')">Register</button>
      </div>

      <!-- LOGIN TAB -->
      <div class="tab-panel active" id="tab-login">
        <form onsubmit="handleLoginSubmit(event)" novalidate>
          <?php echo CSRF::getTokenField(); ?>
          <div class="form-group">
            <input id="username" name="username" type="text" placeholder=" " required autocomplete="username" aria-required="true" />
            <label for="username">Username</label>
          </div>
          <div class="form-group">
            <input id="password" name="password" type="password" placeholder=" " required autocomplete="current-password" aria-required="true" />
            <label for="password">Password</label>
          </div>
          <button type="submit" aria-label="Log In button">Log In</button>
        </form>
        <p class="register-note" style="margin-bottom:0.3rem;"><a href="#" onclick="openForgotModal(event)" style="color:var(--primary-color);font-weight:600;">Forgot Password?</a></p>
        <p class="register-note">Don't have an account? <a onclick="switchTab('register')">Register here</a></p>
        
        <div style="margin-top: 1rem; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 1rem;">
            <button type="button" onclick="window.location.href='onlineordering.php'" style="background: transparent; border: 2px solid var(--primary-color); color: var(--primary-color); margin-top: 0; padding: 0.8rem; font-size: 1rem;">
                Browse Online Shop as Guest
            </button>
        </div>
      </div>

      <!-- REGISTER TAB -->
      <div class="tab-panel" id="tab-register">
        <form onsubmit="handleRegisterSubmit(event)" novalidate>
          <?php echo CSRF::getTokenField(); ?>
          <div class="form-group">
            <input id="reg-fullname" name="full_name" type="text" placeholder=" " required />
            <label for="reg-fullname">Full Name</label>
          </div>
          <div class="form-group">
            <input id="reg-username" name="username" type="text" placeholder=" " required autocomplete="username" />
            <label for="reg-username">Username</label>
          </div>
          <div class="form-group">
            <input id="reg-email" name="email" type="email" placeholder=" " required autocomplete="email" />
            <label for="reg-email">Email</label>
          </div>
          <div class="form-group">
            <input id="reg-password" name="password" type="password" placeholder=" " required autocomplete="new-password" />
            <label for="reg-password">Password</label>
            <small style="display:block; margin-top:0.3rem; font-size:0.75rem; color:var(--text-light); opacity:0.8;">Min 8 chars, 1 uppercase letter, 1 special character</small>
          </div>
          <button type="submit" aria-label="Register button">Register</button>
        </form>
        <p class="register-note">Already have an account? <a onclick="switchTab('login')">Login here</a></p>
      </div>

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
      <button onclick="redirectToIndex()">Okay</button>
  </div>

  <div id="login-error-modal" class="modal modal-error">
      <p>Invalid username or password. Please try again.</p>
      <button onclick="closeErrorModal()">Okay</button>
  </div>

  <div id="register-success-modal" class="modal modal-success">
      <p>Registration Successful! You can now login.</p>
      <button onclick="closeRegisterSuccessModal()">Okay</button>
  </div>

  <div id="register-error-modal" class="modal modal-error">
      <p id="register-error-msg">Registration failed.</p>
      <button onclick="document.getElementById('register-error-modal').style.display='none'">Okay</button>
  </div>

  <!-- Email Verification Modal -->
  <div id="verify-email-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;max-width:420px;width:92%;padding:2.2rem;box-shadow:0 16px 48px rgba(0,0,0,0.22);text-align:center;position:relative;">
      <button onclick="closeVerifyModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
      <div style="font-size:2.5rem;margin-bottom:0.5rem;">&#9993;</div>
      <h3 style="color:#1e3a5f;margin-bottom:0.4rem;font-size:1.2rem;">Verify Your Email</h3>
      <p style="color:#64748b;font-size:0.88rem;margin-bottom:1rem;">Enter the 6-digit code sent to your email to activate your account.</p>
      <div id="verify-code-hint" style="display:none;background:#fef3c7;color:#92400e;padding:0.6rem 1rem;border-radius:10px;font-size:0.85rem;font-weight:600;margin-bottom:1rem;"></div>
      <div id="verify-msg" style="margin-bottom:0.5rem;"></div>
      <form onsubmit="handleVerifyCode(event)" id="verify-form">
        <input type="text" id="verify-code-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000"
          style="width:100%;text-align:center;font-size:1.8rem;font-weight:800;letter-spacing:10px;padding:0.8rem;border:2px solid #cbd5e1;border-radius:12px;margin-bottom:1rem;box-sizing:border-box;font-family:monospace;" required>
        <button type="submit" id="verify-submit-btn"
          style="width:100%;padding:0.8rem;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-weight:700;font-size:1rem;border:none;border-radius:12px;cursor:pointer;">Verify & Activate</button>
      </form>
      <div style="margin-top:1rem;">
        <button onclick="resendVerifyCode()" id="resend-verify-btn" style="background:none;border:none;color:#2563eb;cursor:pointer;font-size:0.85rem;font-weight:600;text-decoration:underline;">Resend Code</button>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal -->
  <div id="forgot-password-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;max-width:420px;width:90%;padding:2.2rem;box-shadow:0 12px 40px rgba(0,0,0,0.18);text-align:center;position:relative;">
      <button onclick="closeForgotModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#64748b;">&times;</button>
      <h3 style="color:#1e3a5f;margin-bottom:0.5rem;font-size:1.25rem;">Forgot Password?</h3>
      <p style="color:#64748b;font-size:0.9rem;margin-bottom:1.3rem;">Enter your username or email address and we'll send a reset link to your registered email.</p>
      <div id="forgot-msg"></div>
      <form onsubmit="handleForgotSubmit(event)" id="forgot-form">
        <input type="text" id="forgot-email" placeholder="Enter username or email" required style="width:100%;padding:0.75rem 1rem;border:1.5px solid #cbd5e1;border-radius:10px;font-size:1rem;margin-bottom:1rem;box-sizing:border-box;">
        <button type="submit" id="forgot-btn" style="width:100%;padding:0.8rem;background:#2563eb;color:#fff;font-weight:700;font-size:1rem;border:none;border-radius:10px;cursor:pointer;">Send Reset Link</button>
      </form>
    </div>
  </div>

  <footer>
      &copy; 2025 Calloway Pharmacy. All rights reserved.
  </footer>

  <script>
    // Dropdown menu functionality
    const menuToggle = document.getElementById('menu-toggle');
    const dropdownMenu = document.getElementById('dropdown-menu');
    
    menuToggle.addEventListener('click', function() {
      dropdownMenu.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!menuToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.remove('show');
      }
    });
    
    // Theme switching
    const htmlElement = document.documentElement;
    const lightThemeBtn = document.getElementById('theme-light');
    const darkThemeBtn = document.getElementById('theme-dark');
    
    lightThemeBtn.addEventListener('click', function() {
      setTheme('light');
      dropdownMenu.classList.remove('show');
    });
    
    darkThemeBtn.addEventListener('click', function() {
      setTheme('dark');
      dropdownMenu.classList.remove('show');
    });
    
    function setTheme(theme) {
      htmlElement.setAttribute('data-theme', theme);
      localStorage.setItem('calloway_theme', theme);
    }
    
    // Check for saved theme preference on page load
    const savedTheme = localStorage.getItem('calloway_theme');
    if (savedTheme) {
      htmlElement.setAttribute('data-theme', savedTheme);
    }

    // Ripple effect on login button
    const btn = document.querySelector('button[type="submit"]');
    btn.addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      ripple.classList.add('ripple');
      this.appendChild(ripple);

      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      ripple.style.width = ripple.style.height = size + 'px';

      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      ripple.style.left = x + 'px';
      ripple.style.top = y + 'px';

      ripple.addEventListener('animationend', () => {
        ripple.remove();
      });
    });

    // ─── Tab Switching ───
    function switchTab(tab) {
      document.getElementById('tab-login').classList.remove('active');
      document.getElementById('tab-register').classList.remove('active');
      document.getElementById('tab-login-btn').classList.remove('active');
      document.getElementById('tab-register-btn').classList.remove('active');
      document.getElementById('tab-' + tab).classList.add('active');
      document.getElementById('tab-' + tab + '-btn').classList.add('active');
    }

    // ─── Reward QR from POS receipt ───
    // If the URL has ?tab=register&reward=RWD-XXXX, auto-switch to register tab
    // and save the reward code so we can auto-redeem it after login
    (function() {
      const urlParams = new URLSearchParams(window.location.search);
      const tabParam = urlParams.get('tab');
      const rewardParam = urlParams.get('reward') || urlParams.get('reward_qr');
      if (tabParam === 'register') {
        switchTab('register');
      }
      if (rewardParam) {
        localStorage.setItem('calloway_pending_reward', rewardParam);
        // Show reward banner at top of form
        const banner = document.createElement('div');
        banner.id = 'reward-banner';
        banner.innerHTML = '<span style="font-size:1.3rem;">🎁</span> <strong>Reward QR Detected!</strong><br><span style="font-size:0.85rem;">Register or log in to claim your loyalty points.</span>';
        banner.style.cssText = 'background:linear-gradient(135deg,#fef3c7,#fde68a);border:2px solid #f59e0b;color:#92400e;padding:0.8rem 1rem;border-radius:12px;margin-bottom:1rem;text-align:center;font-size:0.95rem;animation:slideUp 0.3s ease-out;';
        const container = document.querySelector('.container');
        if (container) container.insertBefore(banner, container.children[1]);
      }
    })();

    // ─── Modal functionality ───
    let loginRedirectUrl = 'dashboard.php'; // default; overridden by role

    function redirectToIndex() {
      document.getElementById('login-success-modal').style.display = 'none';
      window.location.href = loginRedirectUrl;
    }

    function closeErrorModal() {
      document.getElementById('login-error-modal').style.display = 'none';
    }

    function closeRegisterSuccessModal() {
      document.getElementById('register-success-modal').style.display = 'none';
      switchTab('login');
    }

    // Get CSRF Token from meta tag
    function getCsrfToken() {
      const meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    // ─── LOGIN Handler ───
    async function handleLoginSubmit(e) {
      e.preventDefault();
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value.trim();
      const submitButton = e.target.querySelector('button[type="submit"]');

      if (!username || !password) {
        showErrorModal('Please enter both username and password');
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = 'Logging in...';

      try {
        const csrfToken = getCsrfToken();
        const response = await fetch('login_handler.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
          // Check for pending reward QR from POS receipt
          const pendingReward = localStorage.getItem('calloway_pending_reward');
          
          // Redirect based on role — unified login for staff & customers
          if (data.role_name === 'customer') {
            if (pendingReward) {
              localStorage.removeItem('calloway_pending_reward');
              loginRedirectUrl = 'loyalty_qr.php?auto_redeem=' + encodeURIComponent(pendingReward);
            } else {
              loginRedirectUrl = 'onlineordering.php';
            }
          } else {
            // Staff / admin / cashier → dashboard
            loginRedirectUrl = 'dashboard.php';
          }
          document.getElementById('login-success-modal').style.display = 'block';
        } else if (data.needs_verification) {
          // Unverified email — show verification modal
          window._verifyEmail = data.email || '';
          document.getElementById('verify-code-hint').style.display = 'none';
          document.getElementById('verify-email-modal').style.display = 'flex';
        } else {
          showErrorModal(data.message || 'Invalid username or password');
        }
      } catch (error) {
        console.error('Login error:', error);
        showErrorModal('An error occurred. Please try again.');
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Log In';
      }
    }

    // ─── REGISTER Handler ───
    async function handleRegisterSubmit(e) {
      e.preventDefault();
      const fullName = document.getElementById('reg-fullname').value.trim();
      const username = document.getElementById('reg-username').value.trim();
      const email    = document.getElementById('reg-email').value.trim();
      const password = document.getElementById('reg-password').value.trim();
      const submitButton = e.target.querySelector('button[type="submit"]');

      if (!fullName || !username || !email || !password) {
        showRegisterError('All fields are required.');
        return;
      }

      if (password.length < 8) {
        showRegisterError('Password must be at least 8 characters.');
        return;
      }

      if (!/[A-Z]/.test(password)) {
        showRegisterError('Password must contain at least one uppercase letter.');
        return;
      }

      if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
        showRegisterError('Password must contain at least one special character.');
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = 'Registering...';

      try {
        const response = await fetch('register_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ full_name: fullName, username, email, password })
        });

        const data = await response.json();

        if (data.success) {
          if (data.needs_verification) {
            // Show verification modal
            window._verifyEmail = data.user_email || email;
            if (data.email_hint) {
              document.getElementById('verify-code-hint').textContent = 'Your code: ' + data.email_hint;
              document.getElementById('verify-code-hint').style.display = 'block';
            } else {
              document.getElementById('verify-code-hint').style.display = 'none';
            }
            document.getElementById('verify-email-modal').style.display = 'flex';
            e.target.reset();
          } else {
            document.getElementById('register-success-modal').style.display = 'block';
            e.target.reset();
          }
        } else {
          showRegisterError(data.message || 'Registration failed.');
        }
      } catch (error) {
        console.error('Register error:', error);
        showRegisterError('An error occurred. Please try again.');
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Register';
      }
    }

    function showErrorModal(message) {
      const errorModal = document.getElementById('login-error-modal');
      errorModal.querySelector('p').textContent = message;
      errorModal.style.display = 'block';
    }

    function showRegisterError(message) {
      document.getElementById('register-error-msg').textContent = message;
      document.getElementById('register-error-modal').style.display = 'block';
    }

    // ─── FORGOT PASSWORD ───
    function openForgotModal(e) {
      if (e) e.preventDefault();
      document.getElementById('forgot-msg').innerHTML = '';
      document.getElementById('forgot-email').value = '';
      document.getElementById('forgot-form').style.display = 'block';
      const m = document.getElementById('forgot-password-modal');
      m.style.display = 'flex';
    }
    function closeForgotModal() {
      document.getElementById('forgot-password-modal').style.display = 'none';
    }
    // close on backdrop click
    document.getElementById('forgot-password-modal').addEventListener('click', function(e) {
      if (e.target === this) closeForgotModal();
    });

    async function handleForgotSubmit(e) {
      e.preventDefault();
      const identifier = document.getElementById('forgot-email').value.trim();
      const btn = document.getElementById('forgot-btn');
      const msgDiv = document.getElementById('forgot-msg');

      if (!identifier) { msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.6rem;border-radius:8px;font-size:0.9rem;">Please enter your username or email.</p>'; return; }

      btn.disabled = true;
      btn.textContent = 'Sending...';
      msgDiv.innerHTML = '';

      try {
        const res = await fetch('forgot_password_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ identifier })
        });
        const data = await res.json();
        if (data.success) {
          msgDiv.innerHTML = '<p style="color:#166534;background:#dcfce7;padding:0.6rem;border-radius:8px;font-size:0.9rem;">' + data.message + '</p>';
          document.getElementById('forgot-form').style.display = 'none';
        } else {
          msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.6rem;border-radius:8px;font-size:0.9rem;">' + data.message + '</p>';
        }
      } catch (err) {
        console.error('Forgot password error:', err);
        msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.6rem;border-radius:8px;font-size:0.9rem;">Something went wrong. Please try again.</p>';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Send Reset Link';
      }
    }

    // ─── Email Verification Functions ───
    function closeVerifyModal() {
      document.getElementById('verify-email-modal').style.display = 'none';
      document.getElementById('verify-msg').innerHTML = '';
    }

    async function handleVerifyCode(e) {
      e.preventDefault();
      const code = document.getElementById('verify-code-input').value.trim();
      const btn = document.getElementById('verify-submit-btn');
      const msgDiv = document.getElementById('verify-msg');

      if (!code || code.length !== 6) {
        msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.5rem;border-radius:8px;font-size:0.85rem;">Please enter a 6-digit code.</p>';
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Verifying...';
      msgDiv.innerHTML = '';

      try {
        const res = await fetch('verify_email.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: window._verifyEmail, code: code })
        });
        const raw = await res.text();
        let data;
        try {
          data = JSON.parse(raw);
        } catch (parseErr) {
          throw new Error('Server returned an invalid response. Please try again.');
        }
        if (data.success) {
          msgDiv.innerHTML = '<p style="color:#166534;background:#dcfce7;padding:0.5rem;border-radius:8px;font-size:0.85rem;">' + data.message + '</p>';
          setTimeout(() => {
            closeVerifyModal();
            // Switch to login tab
            document.getElementById('loginTab').click();
          }, 1500);
        } else {
          let debugMsg = data.debug ? '<br><small style="color:#666;font-family:monospace;">' + data.debug + '</small>' : '';
          msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.5rem;border-radius:8px;font-size:0.85rem;">' + data.message + debugMsg + '</p>';
        }
      } catch (err) {
        msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.5rem;border-radius:8px;font-size:0.85rem;">Something went wrong. Please try again.<br><small style="color:#666;font-family:monospace;">' + (err.message || 'Verification failed') + '</small></p>';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Verify & Activate';
      }
    }

    async function resendVerifyCode() {
      const btn = document.getElementById('resend-verify-btn');
      const msgDiv = document.getElementById('verify-msg');
      btn.disabled = true;
      btn.textContent = 'Sending...';

      try {
        const res = await fetch('verify_email.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: window._verifyEmail, action: 'resend' })
        });
        const raw = await res.text();
        let data;
        try {
          data = JSON.parse(raw);
        } catch (parseErr) {
          throw new Error('Server returned an invalid response. Please try again.');
        }
        if (data.success) {
          let msg = data.message;
          if (data.email_hint) {
            document.getElementById('verify-code-hint').textContent = 'Your code: ' + data.email_hint;
            document.getElementById('verify-code-hint').style.display = 'block';
          }
          msgDiv.innerHTML = '<p style="color:#166534;background:#dcfce7;padding:0.5rem;border-radius:8px;font-size:0.85rem;">' + msg + '</p>';
        } else {
          msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.5rem;border-radius:8px;font-size:0.85rem;">' + data.message + '</p>';
        }
      } catch (err) {
        msgDiv.innerHTML = '<p style="color:#991b1b;background:#fee2e2;padding:0.5rem;border-radius:8px;font-size:0.85rem;">Something went wrong.</p>';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Resend Code';
      }
    }
  </script>

  <!-- Security JavaScript -->
  <script src="security.js"></script>

</body>
</html>