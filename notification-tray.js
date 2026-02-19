/**
 * Notification Tray - JS logic
 * Polls notification_api.php and renders a dropdown panel from the bell icon.
 */
(function() {
    let notifPanelOpen = false;
    let notifData = [];

    // ─── Create DOM elements ───
    function ensureDOM() {
        if (document.getElementById('notifPanel')) return;

        // Overlay (click to close)
        const overlay = document.createElement('div');
        overlay.id = 'notifOverlay';
        overlay.className = 'notif-panel-overlay';
        overlay.addEventListener('click', closeNotifPanel);
        document.body.appendChild(overlay);

        // Panel
        const panel = document.createElement('div');
        panel.id = 'notifPanel';
        panel.className = 'notif-panel';
        panel.innerHTML = `
            <div class="notif-panel-header">
                <span><i class="fas fa-bell"></i> Notifications <span class="notif-count-label" id="notifCountLabel">0</span></span>
                <div class="notif-panel-actions">
                    <button class="notif-mark-read-all" id="notifMarkReadAllBtn" onclick="markAllNotificationsRead()">Mark all read</button>
                    <button class="notif-panel-close" onclick="closeNotifPanel()"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="notif-panel-body" id="notifPanelBody">
                <div class="notif-empty"><i class="fas fa-check-circle"></i>No notifications</div>
            </div>
        `;
        document.body.appendChild(panel);
    }

    // ─── Toggle Panel ───
    window.toggleNotifPanel = function() {
        ensureDOM();
        const panel = document.getElementById('notifPanel');
        const overlay = document.getElementById('notifOverlay');
        if (notifPanelOpen) {
            panel.classList.remove('visible');
            overlay.classList.remove('visible');
            notifPanelOpen = false;
        } else {
            panel.classList.add('visible');
            overlay.classList.add('visible');
            notifPanelOpen = true;
            fetchNotifications('list');
        }
    };

    window.closeNotifPanel = function() {
        const panel = document.getElementById('notifPanel');
        const overlay = document.getElementById('notifOverlay');
        if (panel) panel.classList.remove('visible');
        if (overlay) overlay.classList.remove('visible');
        notifPanelOpen = false;
    };

    // ─── Fetch Notifications ───
    function fetchNotifications(mode) {
        const action = mode || 'count';
        fetch('notification_api.php?action=' + action)
            .then(r => r.json())
            .then(data => {
                updateBadge(data.count || 0);
                if (action === 'list' && data.notifications) {
                    notifData = data.notifications;
                    renderPanel(data.notifications);
                }
            })
            .catch(() => {});
    }

    function postAction(action, payload) {
        return fetch('notification_api.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload || {})
        }).then(r => r.json());
    }

    // ─── Update badge count ───
    function updateBadge(count) {
        const badge = document.getElementById('notifBadge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // ─── Render panel items ───
    function renderPanel(items) {
        const body = document.getElementById('notifPanelBody');
        const label = document.getElementById('notifCountLabel');
        const markAllBtn = document.getElementById('notifMarkReadAllBtn');
        if (!body) return;

        if (label) label.textContent = items.length;

        if (items.length === 0) {
            if (markAllBtn) markAllBtn.style.display = 'none';
            body.innerHTML = '<div class="notif-empty"><i class="fas fa-check-circle"></i>All clear — no alerts!</div>';
            return;
        }

        if (markAllBtn) markAllBtn.style.display = 'inline-block';

        // Group by type
        const groups = { low_stock: [], expiry: [], pending_order: [] };
        items.forEach(n => {
            if (groups[n.type]) groups[n.type].push(n);
        });

        let html = '';
        const sections = [
            { key: 'pending_order', label: 'Pending Orders' },
            { key: 'low_stock', label: 'Stock Alerts' },
            { key: 'expiry', label: 'Expiry Alerts' }
        ];

        sections.forEach(sec => {
            const list = groups[sec.key];
            if (!list || list.length === 0) return;
            html += `<div style="padding:0.4rem 1rem 0.15rem;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-light,#6b7280);">${sec.label}</div>`;
            list.forEach(n => {
                const safeLink = n.link || '#';
                const nKey = n.notification_key || '';
                html += `<div class="notif-item" onclick="openNotificationLink('${safeLink.replace(/'/g, "\\'")}', '${nKey.replace(/'/g, "\\'")}')">
                    <div class="notif-item-icon" style="background:${n.color || '#6b7280'}">
                        <i class="fas ${n.icon || 'fa-bell'}"></i>
                    </div>
                    <div class="notif-item-content">
                        <div class="notif-item-title-row">
                            <div class="notif-item-title">${n.title}</div>
                            <button class="notif-item-read-btn" onclick="markNotificationRead(event, '${nKey.replace(/'/g, "\\'")}')" title="Mark as read">Mark as read</button>
                        </div>
                        <div class="notif-item-msg">${n.message}</div>
                    </div>
                </div>`;
            });
        });

        body.innerHTML = html;
    }

    window.markNotificationRead = function(event, notificationKey) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        if (!notificationKey) return;
        postAction('mark_read', { notification_key: notificationKey })
            .then(() => {
                notifData = notifData.filter(n => n.notification_key !== notificationKey);
                renderPanel(notifData);
                updateBadge(notifData.length);
            })
            .catch(() => {});
    };

    window.markAllNotificationsRead = function() {
        const keys = notifData.map(n => n.notification_key).filter(Boolean);
        if (keys.length === 0) return;
        postAction('mark_all_read', { notification_keys: keys })
            .then(() => {
                notifData = [];
                renderPanel(notifData);
                updateBadge(0);
            })
            .catch(() => {});
    };

    window.openNotificationLink = function(link, notificationKey) {
        if (notificationKey) {
            postAction('mark_read', { notification_key: notificationKey }).catch(() => {});
        }
        closeNotifPanel();
        if (link && link !== '#') {
            window.location.href = link;
        }
    };

    // ─── Init: poll count every 60s ───
    function init() {
        // Only if bell button exists (staff only)
        if (!document.getElementById('notifBellBtn')) return;
        fetchNotifications('count');
        setInterval(() => fetchNotifications('count'), 60000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
