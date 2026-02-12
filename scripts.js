// Theme Toggle
document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    const icon = themeToggle.querySelector('i');
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
    
    themeToggle.addEventListener('click', () => {
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeIcon(newTheme);
        
        // Add animation
        themeToggle.classList.add('rotate');
        setTimeout(() => themeToggle.classList.remove('rotate'), 500);
    });
    
    function updateThemeIcon(theme) {
        icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
});

// Table Enhancements
document.querySelectorAll('table').forEach(table => {
    // Add table container if not present
    if (!table.parentElement.classList.contains('table-container')) {
        const container = document.createElement('div');
        container.className = 'table-container';
        table.parentNode.insertBefore(container, table);
        container.appendChild(table);
    }
    
    // Add hover effect to rows
    table.querySelectorAll('tr').forEach(row => {
        row.addEventListener('mouseenter', () => row.classList.add('hover'));
        row.addEventListener('mouseleave', () => row.classList.remove('hover'));
    });
});

// Form Enhancements
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', e => {
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }
    });
});

// Search Input Enhancement
document.querySelectorAll('input[type="search"]').forEach(input => {
    const wrapper = document.createElement('div');
    wrapper.className = 'search-wrapper';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    
    const icon = document.createElement('i');
    icon.className = 'fas fa-search search-icon';
    wrapper.appendChild(icon);
    
    input.addEventListener('input', () => {
        wrapper.classList.toggle('has-value', input.value.length > 0);
    });
});

// Card Animations
document.querySelectorAll('.card').forEach(card => {
    card.classList.add('animate-slide-in');
});

// Add loading animation
document.querySelectorAll('.loading').forEach(element => {
    const spinner = document.createElement('div');
    spinner.className = 'spinner';
    element.appendChild(spinner);
});

// Add tooltip functionality
document.querySelectorAll('[data-tooltip]').forEach(element => {
    element.addEventListener('mouseenter', e => {
        const tooltip = element.getAttribute('data-tooltip');
        if (!tooltip) return;
        
        const tooltipEl = document.createElement('div');
        tooltipEl.className = 'tooltip';
        tooltipEl.textContent = tooltip;
        document.body.appendChild(tooltipEl);
        
        const rect = element.getBoundingClientRect();
        tooltipEl.style.left = rect.left + (rect.width / 2) - (tooltipEl.offsetWidth / 2) + 'px';
        tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 10 + 'px';
        
        setTimeout(() => tooltipEl.classList.add('show'), 10);
    });
    
    element.addEventListener('mouseleave', () => {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.classList.remove('show');
            setTimeout(() => tooltip.remove(), 200);
        }
    });
}); 