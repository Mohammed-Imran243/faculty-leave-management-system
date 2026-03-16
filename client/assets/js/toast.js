// assets/js/toast.js

/**
 * Custom Vanilla JS Toast Notification System
 * Displays a non-blocking toast notification in the top-right corner.
 * 
 * @param {string} type - 'success', 'error', 'warning', or 'info'
 * @param {string} message - The text body of the toast
 */
window.showToast = function (type, message) {
    // 1. Ensure the toast container exists in the DOM
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    // 2. Map types to FontAwesome icons
    let iconClass = 'fa-info-circle';
    if (type === 'success') iconClass = 'fa-check-circle';
    if (type === 'error') iconClass = 'fa-times-circle';
    if (type === 'warning') iconClass = 'fa-exclamation-triangle';

    // 3. Create the Toast Component
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    toast.innerHTML = `
        <i class="fa ${iconClass} toast-icon"></i>
        <span class="toast-msg">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;

    // 4. Append to container (Stacks them vertically)
    container.appendChild(toast);

    // 5. Trigger slideIn animation by ensuring reflow, then auto-remove
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // 6. Auto dismiss after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        toast.classList.add('hide');
        // Remove from DOM after fadeOut animation (0.3s)
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};
