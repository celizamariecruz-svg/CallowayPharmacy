// ═══ Sidebar & Theme Toggle ═══

// --- Sidebar ---
function isMobile() {
  return window.innerWidth < 769;
}

function toggleSidebar() {
  if (!isMobile()) return; // Desktop: sidebar is always visible
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (sidebar && overlay) {
    const isOpen = sidebar.classList.contains('open');
    if (isOpen) {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    } else {
      sidebar.classList.add('open');
      overlay.classList.add('active');
    }
  }
}

function closeSidebar() {
  if (!isMobile()) return; // Desktop: sidebar stays open
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (sidebar) sidebar.classList.remove('open');
  if (overlay) overlay.classList.remove('active');
}

// Close sidebar on Escape key (mobile only)
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeSidebar();
});

// --- Theme Toggle ---
function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  const next = current === 'dark' ? 'light' : 'dark';
  setTheme(next);
}

function setTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('calloway_theme', theme);
  updateThemeIcon(theme);
}

function updateThemeIcon(theme) {
  const icon = document.getElementById('themeIcon');
  if (icon) {
    icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  }
}

// Apply saved theme on load
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const sidebarNav = document.querySelector('.sidebar-nav');
  const overlay = document.getElementById('sidebarOverlay');
  if (sidebar) sidebar.classList.add('open');
  if (overlay) overlay.classList.remove('active');
  if (sidebar) sidebar.scrollTop = 0;
  if (sidebarNav) sidebarNav.scrollTop = 0;

  // Desktop: sidebar must not scroll
  if (window.innerWidth >= 769 && sidebar) {
    const blockScroll = function(e) {
      e.preventDefault();
    };
    sidebar.addEventListener('wheel', blockScroll, { passive: false });
    sidebar.addEventListener('touchmove', blockScroll, { passive: false });
  }

  const savedTheme = localStorage.getItem('calloway_theme') || 'light';
  document.documentElement.setAttribute('data-theme', savedTheme);
  updateThemeIcon(savedTheme);

  // Legacy support: old pages that still have #theme-light / #theme-dark buttons
  const lightBtn = document.getElementById('theme-light');
  const darkBtn = document.getElementById('theme-dark');
  if (lightBtn) lightBtn.addEventListener('click', function() { setTheme('light'); });
  if (darkBtn) darkBtn.addEventListener('click', function() { setTheme('dark'); });

  // Legacy support: old menu-toggle dropdown
  const menuToggle = document.getElementById('menu-toggle');
  const dropdownMenu = document.getElementById('dropdown-menu');
  if (menuToggle && dropdownMenu) {
    menuToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdownMenu.classList.toggle('show');
    });
    document.addEventListener('click', function(e) {
      if (menuToggle && dropdownMenu && !menuToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.remove('show');
      }
    });
  }
});