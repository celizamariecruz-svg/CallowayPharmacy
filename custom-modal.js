/**
 * Custom Modal System - Replaces native alert() and confirm() dialogs
 * Calloway Pharmacy IMS
 * 
 * Usage:
 *   await customAlert('Title', 'Message', 'info');
 *   const ok = await customConfirm('Title', 'Message', 'warning');
 *   if (!ok) return;
 */

(function () {
    'use strict';

    let modalContainer = null;

    function ensureContainer() {
        if (!document.body) return; // Body not ready yet
        if (modalContainer && document.body.contains(modalContainer)) return;
        modalContainer = document.createElement('div');
        modalContainer.id = 'customModalContainer';
        document.body.appendChild(modalContainer);
    }

    // Pre-create container once DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureContainer);
    } else {
        ensureContainer();
    }

    const ICONS = {
        warning: '⚠️',
        danger: '🗑️',
        info: 'ℹ️',
        success: '✅',
        question: '❓',
        prescription: '💊',
        order: '📦',
        logout: '🚪',
        backup: '💾',
        error: '❌'
    };

    /**
     * Show a custom styled alert (replaces window.alert)
     * @param {string} title
     * @param {string} message
     * @param {string} type - warning|danger|info|success|error
     * @returns {Promise<void>}
     */
    window.customAlert = function (title, message, type = 'info') {
        return new Promise((resolve) => {
            ensureContainer();

            const iconType = (type === 'error') ? 'danger' : type;
            const icon = ICONS[type] || ICONS[iconType] || ICONS.info;

            const overlay = document.createElement('div');
            overlay.className = 'cmodal-overlay';
            overlay.innerHTML = `
                <div class="cmodal-box">
                    <div class="cmodal-icon">
                        <div class="cmodal-icon-circle ${iconType}">${icon}</div>
                    </div>
                    <div class="cmodal-body">
                        <h3 class="cmodal-title">${escapeHTML(title)}</h3>
                        <p class="cmodal-message">${escapeHTML(message)}</p>
                    </div>
                    <div class="cmodal-actions">
                        <button class="cmodal-btn cmodal-btn-confirm ${iconType}" id="cmodalOkBtn">OK</button>
                    </div>
                </div>
            `;

            modalContainer.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('active'));

            const okBtn = overlay.querySelector('#cmodalOkBtn');
            okBtn.focus();

            function close() {
                overlay.classList.remove('active');
                setTimeout(() => { overlay.remove(); }, 200);
                resolve();
            }

            okBtn.addEventListener('click', close);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
            overlay.addEventListener('keydown', (e) => { if (e.key === 'Escape' || e.key === 'Enter') close(); });
        });
    };

    /**
     * Show a custom styled confirm dialog (replaces window.confirm)
     * @param {string} title
     * @param {string} message
     * @param {string} type - warning|danger|info|success
     * @param {object} opts - { confirmText, cancelText, confirmStyle }
     * @returns {Promise<boolean>}
     */
    window.customConfirm = function (title, message, type = 'warning', opts = {}) {
        return new Promise((resolve) => {
            ensureContainer();

            const iconType = (type === 'error') ? 'danger' : type;
            const icon = ICONS[type] || ICONS[iconType] || ICONS.warning;
            const confirmText = opts.confirmText || 'Confirm';
            const cancelText = opts.cancelText || 'Cancel';
            const btnStyle = opts.confirmStyle || iconType;

            const overlay = document.createElement('div');
            overlay.className = 'cmodal-overlay';
            overlay.innerHTML = `
                <div class="cmodal-box">
                    <div class="cmodal-icon">
                        <div class="cmodal-icon-circle ${iconType}">${icon}</div>
                    </div>
                    <div class="cmodal-body">
                        <h3 class="cmodal-title">${escapeHTML(title)}</h3>
                        <p class="cmodal-message">${escapeHTML(message)}</p>
                    </div>
                    <div class="cmodal-actions">
                        <button class="cmodal-btn cmodal-btn-cancel" id="cmodalCancelBtn">${escapeHTML(cancelText)}</button>
                        <button class="cmodal-btn cmodal-btn-confirm ${btnStyle}" id="cmodalConfirmBtn">${escapeHTML(confirmText)}</button>
                    </div>
                </div>
            `;

            modalContainer.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('active'));

            const confirmBtn = overlay.querySelector('#cmodalConfirmBtn');
            const cancelBtn = overlay.querySelector('#cmodalCancelBtn');
            confirmBtn.focus();

            function close(result) {
                overlay.classList.remove('active');
                setTimeout(() => { overlay.remove(); }, 200);
                resolve(result);
            }

            confirmBtn.addEventListener('click', () => close(true));
            cancelBtn.addEventListener('click', () => close(false));
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
            overlay.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') close(false);
            });
        });
    };

    window.customPrompt = function (title, message, type = 'info', opts = {}) {
        return new Promise((resolve) => {
            ensureContainer();

            const iconType = (type === 'error') ? 'danger' : type;
            const icon = ICONS[type] || ICONS[iconType] || ICONS.info;
            const confirmText = opts.confirmText || 'Continue';
            const cancelText = opts.cancelText || 'Cancel';
            const placeholder = opts.placeholder || '';
            const defaultValue = opts.defaultValue || '';
            const inputType = opts.inputType || 'text';

            const overlay = document.createElement('div');
            overlay.className = 'cmodal-overlay';
            overlay.innerHTML = `
                <div class="cmodal-box">
                    <div class="cmodal-icon">
                        <div class="cmodal-icon-circle ${iconType}">${icon}</div>
                    </div>
                    <div class="cmodal-body">
                        <h3 class="cmodal-title">${escapeHTML(title)}</h3>
                        <p class="cmodal-message">${escapeHTML(message)}</p>
                        <div class="cmodal-input-wrap">
                            <input id="cmodalPromptInput" class="cmodal-input" type="${escapeHTML(inputType)}" placeholder="${escapeHTML(placeholder)}" value="${escapeHTML(defaultValue)}" />
                        </div>
                    </div>
                    <div class="cmodal-actions">
                        <button class="cmodal-btn cmodal-btn-cancel" id="cmodalPromptCancel">${escapeHTML(cancelText)}</button>
                        <button class="cmodal-btn cmodal-btn-confirm ${iconType}" id="cmodalPromptConfirm">${escapeHTML(confirmText)}</button>
                    </div>
                </div>
            `;

            modalContainer.appendChild(overlay);
            requestAnimationFrame(() => overlay.classList.add('active'));

            const input = overlay.querySelector('#cmodalPromptInput');
            const confirmBtn = overlay.querySelector('#cmodalPromptConfirm');
            const cancelBtn = overlay.querySelector('#cmodalPromptCancel');

            setTimeout(() => {
                input.focus();
                input.select();
            }, 0);

            function close(result) {
                overlay.classList.remove('active');
                setTimeout(() => { overlay.remove(); }, 200);
                resolve(result);
            }

            confirmBtn.addEventListener('click', () => {
                const value = input.value.trim();
                if (!value) {
                    input.focus();
                    return;
                }
                close(value);
            });
            cancelBtn.addEventListener('click', () => close(null));
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(null); });
            overlay.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') close(null);
                if (e.key === 'Enter') {
                    e.preventDefault();
                    confirmBtn.click();
                }
            });
        });
    };

    /**
     * Quick confirm wrapper for inline HTML use (e.g., onsubmit, onclick)
     * Since inline handlers need sync returns, we intercept the form/button.
     */
    window.customFormConfirm = function (formOrEvent, title, message, type = 'warning') {
        if (formOrEvent && formOrEvent.preventDefault) formOrEvent.preventDefault();
        const form = (formOrEvent && formOrEvent.target) ? formOrEvent.target : formOrEvent;
        customConfirm(title, message, type, { confirmText: 'Yes, Continue', cancelText: 'No, Go Back' })
            .then((ok) => {
                if (ok && form && form.submit) {
                    // Temporarily bypass confirm for the actual submit
                    form._skipConfirm = true;
                    form.submit();
                }
            });
        return false;
    };

    function escapeHTML(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

})();
