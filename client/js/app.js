const API_URL = 'http://localhost:8081/faculty-leave-management-system-cr/server/api';
const BASE_URL = 'http://localhost:8081/faculty-leave-management-system-cr/server';

// --- safeStorage Helper ---
const safeStorage = {
    getItem: (key) => {
        try { return localStorage.getItem(key); }
        catch (e) { console.warn(`Storage access blocked for ${key}`); return null; }
    },
    setItem: (key, value) => {
        try { localStorage.setItem(key, value); }
        catch (e) { console.warn(`Failed to save ${key} to storage`); }
    },
    clear: () => {
        try { localStorage.clear(); }
        catch (e) { console.warn('Failed to clear storage'); }
    }
};

const state = {
    user: JSON.parse(safeStorage.getItem('user')) || null,
    token: safeStorage.getItem('token') || null,
    csrf: safeStorage.getItem('csrf') || null,
    notifications: [],
    currentView: null,
    cache: {},
    facultyCache: null,
    usersPage: 1
};



// --- Helpers ---
function isTeachingRole(role) {
    if (!role) return false;
    const r = role.toLowerCase();
    return ['faculty', 'assistant professor (ap)', 'associate professor', 'professor'].includes(r);
}

function isAdministrativeRole(role) {
    if (!role) return false;
    const r = role.toLowerCase();
    return ['hod', 'principal', 'officer', 'admin'].includes(r);
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * showToast - Displays a non-blocking toast notification
 * @param {string} message - Message to display
 * @param {string} type - 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icons[type] || icons.info}"></i>
        </div>
        <div class="toast-content">${escapeHtml(message)}</div>
        <button class="toast-close" onclick="this.parentElement.classList.add('removing'); setTimeout(() => this.parentElement.remove(), 300);">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(toast);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// Make globally accessible for index.html context
window.showToast = showToast;

// --- API Helper ---
// --- API Helper ---
async function apiCall(endpoint, method = 'GET', body = null) {
    const headers = { 'Content-Type': 'application/json' };
    if (state.token) headers['Authorization'] = `Bearer ${state.token}`;

    if (state.csrf && ['POST', 'PUT', 'DELETE'].includes(method.toUpperCase())) {
        headers['X-CSRF-Token'] = state.csrf;
    }

    console.log(`[DEBUG] Fetching: ${API_URL}${endpoint}`);

    try {
        const response = await fetch(`${API_URL}${endpoint}`, {
            method,
            headers,
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null
        });

        // Handle Non-OK with JSON body
        if (response.status === 401 || response.status === 403) {
            const errData = await response.json().catch(() => ({ error: 'Unauthorized' }));
            if (!window.location.pathname.endsWith('index.html') && !endpoint.includes('/login')) {
                logout();
            }
            return errData;
        }

        // Try parsing JSON
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('[CRITICAL] API Response was not JSON:', text);
            throw new Error('Server returned HTML/Invalid JSON. Check Console.');
        }

    } catch (error) {
        console.error('API Error:', error);
        return { error: error.message };
    }
}

// --- Auth Functions ---
async function login(username, password) {
    try {
        const data = await apiCall('/auth.php/login', 'POST', { username, password });
        if (data && data.token) {
            state.token = data.token;
            state.user = data.user;
            state.csrf = data.csrf_token;

            safeStorage.setItem('token', data.token);
            safeStorage.setItem('user', JSON.stringify(data.user));
            if (data.csrf_token) safeStorage.setItem('csrf', data.csrf_token);

            window.location.href = 'dashboard.html';
        } else {
            showToast('Login Failed: ' + (data?.error || 'Unknown Error'), 'error');
        }
    } catch (e) {
        showToast("System Error: " + e.message, 'error');
    }
}

function logout() {
    safeStorage.clear();
    state.token = null;
    state.user = null;
    window.location.href = 'index.html';
}

// --- Dashboard Logic ---
function initDashboard() {
    if (!state.token) {
        window.location.href = 'index.html';
        return;
    }

    // Set Header Info
    const userNameEl = document.getElementById('user-name');
    const userRoleEl = document.getElementById('user-role');
    if (userNameEl) userNameEl.textContent = state.user.name;
    if (userRoleEl) userRoleEl.textContent = state.user.role.toUpperCase();

    // Init Notifications
    fetchNotifications();
    setInterval(fetchNotifications, 30000); // Poll every 30s

    // Render Sidebar
    setupMobileSidebar();
    renderSidebar();

    // Render Default View
    const role = state.user.role.toLowerCase();
    renderView(role === 'admin' ? 'users' : (isTeachingRole(role) ? 'leaves' : 'analytics'));
}

function setupMobileSidebar() {
    const toggleBtn = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('sidebar-overlay');
    const sidebar = document.getElementById('sidebar');

    if (toggleBtn && overlay && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
}

// Global toggle for inline onclick usage
window.toggleSidebar = function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
};

async function fetchNotifications() {
    const notifs = await apiCall('/notifications.php');
    if (notifs && Array.isArray(notifs)) {
        state.notifications = notifs;
        updateNotificationUI();
    }
}

function updateNotificationUI() {
    const badge = document.getElementById('notif-badge');
    const list = document.getElementById('notif-list');

    if (!badge || !list) return; // Silent return if not on dashboard

    const unreadCount = state.notifications.filter(n => !n.is_read).length;
    badge.textContent = unreadCount;
    badge.style.display = unreadCount > 0 ? 'flex' : 'none';

    list.innerHTML = '';
    if (state.notifications.length === 0) {
        list.innerHTML = '<div class="empty-notif">🔔 You\'re all caught up!</div>';
        return;
    }

    state.notifications.forEach(n => {
        const div = document.createElement('div');
        div.className = `notification-item ${n.is_read ? '' : 'unread'}`;
        div.innerHTML = `
            <div class="notification-content">
                <p>${n.message}</p>
                <small>${n.created_at || ''}</small>
            </div>
        `;
        div.onclick = () => markNotificationRead(n.id);
        list.appendChild(div);
    });
}

function toggleNotifications() {
    const d = document.getElementById('notif-dropdown');
    d.classList.toggle('active');
    // Simple toggle logic
    if (d.style.display === 'block') {
        d.style.display = 'none';
    } else {
        d.style.display = 'block';
    }
}

async function markNotificationRead(id) {
    await apiCall(`/notifications.php/${id}/read`, 'PUT');
    fetchNotifications();
}

function renderSidebar() {
    const role = state.user.role;
    const menu = document.getElementById('sidebar-menu');
    if (!menu) return; // Silent return if sidebar is not in DOM
    menu.innerHTML = '';

    const items = [];
    items.push({ id: 'analytics', label: 'Dashboard', icon: 'fa-th-large' });

    if (isTeachingRole(role)) {
        items.push({ id: 'apply', label: 'Apply Leave', icon: 'fa-paper-plane' });
        items.push({ id: 'leaves', label: 'My History', icon: 'fa-history' });
        items.push({ id: 'substitutions', label: 'Substitutions', icon: 'fa-exchange-alt' });
        items.push({ id: 'apply-permission', label: 'Permission', icon: 'fa-clock' });
        items.push({ id: 'apply-outpass', label: 'Outpass', icon: 'fa-door-open' });
    }

    if (isAdministrativeRole(role)) {
        items.push({ id: 'approvals', label: 'Approvals', icon: 'fa-check-double' });
    }

    if (role.toLowerCase() === 'admin') {
        items.push({ id: 'users', label: 'Manage Users', icon: 'fa-users-cog' });
        items.push({ id: 'rules', label: 'Leave Policy', icon: 'fa-user-shield' });
    }

    items.push({ id: 'profile', label: 'My Profile', icon: 'fa-user-circle' });

    items.forEach(item => {
        const a = document.createElement('a');
        a.href = '#';
        a.className = 'nav-item';
        a.dataset.navId = item.id;
        a.innerHTML = `<i class="fas ${item.icon}"></i> <span>${item.label}</span>`;
        a.onclick = (e) => {
            e.preventDefault();
            renderView(item.id);
            // Hide sidebar on mobile if active
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.getElementById('sidebar-overlay').classList.remove('active');
            }
        };
        menu.appendChild(a);
    });

    state.sidebarRendered = true;
}

function updateSidebarHighlight(viewId) {
    document.querySelectorAll('.nav-item').forEach(el => {
        if (el.dataset.navId === viewId) {
            el.classList.add('active');
        } else {
            el.classList.remove('active');
        }
    });
}

async function renderView(viewId) {
    if (state.currentView === viewId && arguments.length === 1) {
        return; // Prevent duplicate rendering if already active, unless parameters are provided
    }
    state.currentView = viewId;
    updateSidebarHighlight(viewId);
    const role = state.user.role.toLowerCase();

    // Update Top Header Title Dynamically
    const titleEl = document.getElementById('current-view-title');
    if (titleEl) {
        const titles = {
            'analytics': 'Dashboard Overview', 'users': 'User Management', 'rules': 'System Policies',
            'apply': 'Apply for Leave', 'leaves': 'My History', 'substitutions': 'Substitutions',
            'apply-permission': 'Request Permission', 'apply-outpass': 'Request Outpass', 'approvals': 'Approvals',
            'profile': 'My Profile'
        };
        titleEl.textContent = titles[viewId] || 'Dashboard';
    }

    const container = document.getElementById('content-area');
    const showLoader = () => {
        container.innerHTML = `<div style="display:flex; justify-content:center; align-items:center; height:200px; color:var(--text-muted);">
                                 <i class="fa fa-spinner fa-spin fa-2x" style="margin-right:10px;"></i> Loading...
                               </div>`;
    };

    // -- Analytics Dashboard --
    if (viewId === 'analytics') {
        let stats = state.cache['analytics'];
        if (!stats) {
            showLoader();
            stats = await apiCall('/analytics.php');
            if (stats) state.cache['analytics'] = stats; // Cache the response
        }

        if (!stats) return;

        let html = ``;

        if (isTeachingRole(role)) {
            const balance = stats.leave_balance || {};
            html += `
            <div class="stats-grid animate-fade-in">
                <div class="stat-card blue">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info">
                        <span>Casual Leave Balance</span>
                        <h3 id="cl-balance">${balance.casual_leave?.remaining || 0}</h3>
                        <small>of ${balance.casual_leave?.total || 1.5} days</small>
                    </div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-info">
                        <span>Permissions Used</span>
                        <h3>${balance.permissions?.used || 0}</h3>
                        <small>Limit: ${balance.permissions?.limit || 2}/mo</small>
                    </div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                    <div class="stat-info">
                        <span>Outpasses Used</span>
                        <h3>${balance.outpasses?.used || 0}</h3>
                        <small>Limit: ${balance.outpasses?.limit || 2}/mo</small>
                    </div>
                </div>
                <div class="stat-card red">
                    <div class="stat-icon"><i class="fas fa-history"></i></div>
                    <div class="stat-info">
                        <span>Total Requests</span>
                        <h3>${stats.total_leaves}</h3>
                        <small>Approved/Pending</small>
                    </div>
                </div>
            </div>

            <div class="dashboard-sections">
                <div class="left-section" style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="chart-card">
                        <div class="section-header">
                            <h3>Monthly Leave Trends</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                    </div>

                    <div class="calendar-card">
                        <div class="section-header">
                            <h3>My Leave Calendar</h3>
                            <button class="btn btn-primary btn-sm" onclick="renderView('apply')">+ New Request</button>
                        </div>
                        <div id="calendar"></div>
                    </div>
                </div>

                <div class="right-section" style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="chart-card" style="min-height: 400px;">
                        <div class="section-header">
                            <h3>Leave Type Distribution</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="leaveDistributionChart"></canvas>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <h3>Pending Requests Status</h3>
                            <a href="#" class="view-all" onclick="renderView('leaves')">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr><th>Type</th><th>Date</th><th>Status</th></tr>
                                </thead>
                                <tbody>`;

            if (stats.recent_leaves && stats.recent_leaves.length > 0) {
                stats.recent_leaves.slice(0, 5).forEach(l => {
                    const statusClass = l.status.toLowerCase();
                    html += `
                        <tr>
                            <td data-label="Type"><strong>${escapeHtml(l.leave_type)}</strong></td>
                            <td data-label="Date">${l.start_date}</td>
                            <td data-label="Status"><span class="status-badge status-${statusClass}">${l.status}</span></td>
                        </tr>`;
                });
            } else {
                html += `<tr><td colspan="3" style="text-align:center; padding:20px;">No pending requests found.</td></tr>`;
            }

            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-header"><h3>Substitution Status</h3></div>
                        <div class="sub-list">`;

            if (stats.substitution_requests && stats.substitution_requests.length > 0) {
                stats.substitution_requests.forEach(s => {
                    html += `
                        <div class="sub-item">
                            <div class="sub-faculty">${escapeHtml(s.substitute_faculty)}</div>
                            <div class="sub-status status-${s.status.toLowerCase()}">${s.status}</div>
                        </div>`;
                });
            } else {
                html += `<p style="text-align:center; color:var(--text-muted);">No pending substitutions.</p>`;
            }

            html += `
                        </div>
                    </div>
                </div>
            </div>`;

        } else if (role === 'hod') {
            const currentDept = state.user.department || '';
            html += `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #E0E7FF; color: #4F46E5;">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.total_faculty || 0}</div>
                    <div class="stat-label">Total Faculty</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.leaves_today || 0}</div>
                    <div class="stat-label">Leaves Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #FEE2E2; color: #B91C1C;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.pending_approvals || 0}</div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #DCFCE7; color: #059669;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.permissions_used || 0}</div>
                    <div class="stat-label">Permissions Used</div>
                </div>
            </div>

            <div class="dashboard-sections animate-fade-in">
                <div class="left-section" style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="chart-card">
                        <div class="section-header">
                            <h3>Monthly Dept Trends</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                    </div>

                    <div class="calendar-card">
                        <div class="section-header">
                            <h3>Department Coverage</h3>
                        </div>
                        <div id="calendar"></div>
                    </div>
                </div>

                <div class="right-section" style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="chart-card" style="min-height: 400px;">
                        <div class="section-header">
                            <h3>Leave Distribution (${currentDept})</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="leaveDistributionChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Pending Dept Requests</h3>
                            <a href="#" onclick="renderView('approvals'); return false;" class="btn btn-secondary btn-sm">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Faculty</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>`;

            if (stats.pending_requests && stats.pending_requests.length > 0) {
                stats.pending_requests.forEach(r => {
                    html += `
                        <tr>
                            <td data-label="Faculty"><strong>${escapeHtml(r.faculty_name)}</strong></td>
                            <td data-label="Type">${escapeHtml(r.leave_type)}</td>
                            <td data-label="Date">${r.leave_date}</td>
                            <td data-label="Status"><span class="badge badge-pending">${escapeHtml(r.status)}</span></td>
                        </tr>`;
                });
            } else {
                html += `<tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-muted);">No pending requests.</td></tr>`;
            }
            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid-3col">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Faculty Activity</h3></div>
                    <div style="padding:16px;">
                        <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:10px;">`;
            if (stats.faculty_activity && stats.faculty_activity.length > 0) {
                stats.faculty_activity.forEach(u => {
                    html += `<li style="display:flex; justify-content:space-between;"><span>${escapeHtml(u.name)}</span> <strong>${u.leave_count}</strong></li>`;
                });
            } else {
                html += `<li style="color:var(--text-muted);">No activity data.</li>`;
            }
            html += `</ul></div></div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Today's Leaves</h3></div>
                    <div style="padding:16px;">
                        <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:10px;">`;
            if (stats.todays_leave_list && stats.todays_leave_list.length > 0) {
                stats.todays_leave_list.forEach(u => {
                    html += `<li><i class="fas fa-user-clock" style="margin-right:8px; color:var(--primary);"></i> ${escapeHtml(u.faculty_name)}</li>`;
                });
            } else {
                html += `<li style="color:var(--text-muted);">No leaves today.</li>`;
            }
            html += `</ul></div></div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Substitution Status</h3></div>
                    <div class="table-responsive">
                        <table style="font-size: 0.85rem;">
                            <tbody>`;
            if (stats.substitution_status && stats.substitution_status.length > 0) {
                stats.substitution_status.slice(0, 5).forEach(s => {
                    const statusClass = s.status.toLowerCase();
                    html += `<tr><td data-label="Faculty">${escapeHtml(s.faculty_name)}</td><td data-label="Status"><span class="badge badge-${statusClass}">${s.status}</span></td></tr>`;
                });
            } else {
                html += `<tr><td style="color:var(--text-muted);">No substitution items.</td></tr>`;
            }
            html += `</tbody></table></div></div>
            </div>`;

        } else if (role === 'principal' || role === 'admin') {
            html += `
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #FEE2E2; color: #B91C1C;">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.leaves_today || 0}</div>
                    <div class="stat-label">Overall Leaves Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
                            <i class="fas fa-hourglass-start"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.pending_approvals || 0}</div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #E0E7FF; color: #4F46E5;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.permissions_used || 0}</div>
                    <div class="stat-label">Permissions Month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: #DCFCE7; color: #059669;">
                            <i class="fas fa-door-open"></i>
                        </div>
                    </div>
                    <div class="stat-value">${stats.outpasses_used || 0}</div>
                    <div class="stat-label">Outpasses Month</div>
                </div>
            </div>

            <div class="dashboard-sections animate-fade-in">
                <div class="left-section" style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="chart-card">
                        <div class="section-header">
                            <h3>Monthly Leave Trends (Global)</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="monthlyTrendsChart"></canvas>
                        </div>
                    </div>

                    <div class="calendar-card">
                        <div class="section-header">
                            <h3>Global Faculty Coverage</h3>
                        </div>
                        <div id="calendar"></div>
                    </div>
                </div>

                <div class="right-section" style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="chart-card" style="min-height: 400px;">
                        <div class="section-header">
                            <h3>Departmental Distribution</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="leaveDistributionChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Pending Approvals Breakdown</h3></div>
                        <div style="padding: 16px;">
                            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:16px;">
                                <li style="display:flex; justify-content:space-between; align-items:center;">
                                    <span>Substitution Pending</span>
                                    <span class="badge badge-pending">${stats.pending_breakdown?.substitution || 0}</span>
                                </li>
                                <li style="display:flex; justify-content:space-between; align-items:center;">
                                    <span>HoD Approval Pending</span>
                                    <span class="badge badge-pending">${stats.pending_breakdown?.hod || 0}</span>
                                </li>
                                <li style="display:flex; justify-content:space-between; align-items:center;">
                                    <span>Principal Approval Pending</span>
                                    <span class="badge badge-pending">${stats.pending_breakdown?.principal || 0}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 24px;">
                <div class="card-header"><h3 class="card-title">Faculty Leave Summary</h3></div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Name</th><th>Department</th><th>Total Requests</th><th>Status</th></tr>
                        </thead>
                        <tbody>`;
            if (stats.individual_records && stats.individual_records.length > 0) {
                stats.individual_records.slice(0, 10).forEach(u => {
                    html += `<tr>
                        <td data-label="Name"><strong>${escapeHtml(u.name)}</strong></td>
                        <td data-label="Dept">${escapeHtml(u.department)}</td>
                        <td data-label="Total">${u.total_requests}</td>
                        <td data-label="Status"><span class="badge badge-secondary">Active</span></td>
                    </tr>`;
                });
            }
            html += `</tbody></table></div></div>`;
        }

        container.innerHTML = html;

        if (viewId === 'analytics') {
            setTimeout(() => {
                initCharts(stats, role);
                initCalendar(stats, role);
            }, 100);
        }
        return;
    }

    // -- Admin: Users --
    if (viewId === 'users' && role === 'admin') {
        const page = arguments[1] || 1;
        const search = arguments[2] || '';
        const dept = arguments[3] || '';

        showLoader();
        const response = await apiCall(`/users.php?page=${page}&limit=20&search=${encodeURIComponent(search)}&dept=${encodeURIComponent(dept)}`);
        if (!response || !response.users) return;

        const users = response.users;
        const totalPages = response.total_pages;

        let html = `
            <div class="card">
                <div class="card-header" style="flex-wrap: wrap; gap: 15px;">
                    <h3 class="card-title">User Management (${response.total} Users)</h3>
                    <div class="search-filters" style="display: flex; gap: 10px; flex-grow: 1; align-items: center;">
                        <input type="text" id="adminUserSearch" class="form-control" placeholder="Search by name or code..." value="${escapeHtml(search)}" style="max-width: 250px;">
                        <select id="adminUserDept" class="form-control" style="max-width: 150px;">
                            <option value="">All Departments</option>
                            <option value="CSE" ${dept === 'CSE' ? 'selected' : ''}>CSE</option>
                            <option value="ECE" ${dept === 'ECE' ? 'selected' : ''}>ECE</option>
                            <option value="MECH" ${dept === 'ME' ? 'selected' : ''}>MECH</option>
                            <option value="CIVIL" ${dept === 'CIVIL' ? 'selected' : ''}>CIVIL</option>
                            <option value="AI&DS" ${dept === 'AI&DS' ? 'selected' : ''}>AI&DS</option>
                            <option value="EEE" ${dept === 'EEE' ? 'selected' : ''}>EEE</option>
                            <option value="MCA" ${dept === 'MCA' ? 'selected' : ''}>MCA</option>
                            <option value="IT" ${dept === 'IT' ? 'selected' : ''}>IT</option>
                            <option value="MBA" ${dept === 'MBA' ? 'selected' : ''}>MBA</option>
                            <option value="ME" ${dept === 'ME' ? 'selected' : ''}>ME</option>
                        </select>
                        <button class="btn btn-secondary btn-sm" onclick="filterAdminUsers()">Search</button>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="showCreateUserModal()">+ Add User</button>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Emp Code</th>
                                <th>Designation</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>`;

        if (users.length === 0) {
            html += `<tr><td colspan="5" style="text-align:center; padding: 20px;">No users found.</td></tr>`;
        }

        users.forEach(u => {
            html += `
                <tr>
                    <td data-label="Faculty Name"><strong>${escapeHtml(u.name)}</strong></td>
                    <td data-label="Emp Code">${escapeHtml(u.employee_code || '-')}</td>
                    <td data-label="Designation">${escapeHtml(u.designation || '-')}</td>
                    <td data-label="Role"><span class="badge badge-secondary">${escapeHtml(u.role)}</span></td>
                    <td data-label="Action">
                        <div style="display:flex; gap:5px;">
                            <button class="btn btn-sm btn-primary" onclick="editUser(${u.id}, ${page})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-secondary" onclick="deleteUser(${u.id}, ${page})" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`;
        });
        html += `</tbody></table></div>`;

        // Pagination Controls
        if (totalPages > 1) {
            html += `
                <div class="pagination" style="margin-top: 20px; display: flex; justify-content: center; gap: 10px; padding: 10px;">
                    <button class="btn btn-sm ${page <= 1 ? 'btn-disabled' : 'btn-secondary'}" 
                            ${page <= 1 ? 'disabled' : ''} 
                            onclick="renderView('users', ${page - 1}, '${search}', '${dept}')">
                        <i class="fas fa-chevron-left"></i> Prev
                    </button>
                    <span style="align-self: center; font-weight: 600;">Page ${page} of ${totalPages}</span>
                    <button class="btn btn-sm ${page >= totalPages ? 'btn-disabled' : 'btn-secondary'}" 
                            ${page >= totalPages ? 'disabled' : ''} 
                            onclick="renderView('users', ${page + 1}, '${search}', '${dept}')">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>`;
        }

        html += `</div>`;
        container.innerHTML = html;

        // Add event listener for Enter key on search input
        document.getElementById('adminUserSearch').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') filterAdminUsers();
        });

        return;
    }

    // -- Admin: Leave Policy Settings --
    if (viewId === 'rules' && role === 'admin') {
        showLoader();
        const rules = await apiCall('/rules.php');
        if (!rules) return;

        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Leave Policy Configuration</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Rule</th>
                                <th>Period</th>
                                <th>Value</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>`;

        rules.forEach(r => {
            const isTimeRule = r.rule_name.includes('_time') || r.rule_name.includes('_start') || r.rule_name.includes('_end');
            let displayValue = Math.floor(r.rule_value);
            let inputHtml = ``;

            if (isTimeRule) {
                const timeStr = displayValue.toString().padStart(4, '0');
                const formattedTime = timeStr.slice(0, 2) + ':' + timeStr.slice(2);
                inputHtml = `<input type="time" id="rule-val-${r.id}" class="form-control" value="${formattedTime}" data-is-time="true" style="width:120px; padding:4px 8px;">`;
            } else {
                inputHtml = `<input type="number" id="rule-val-${r.id}" class="form-control" value="${displayValue}" style="width:80px; padding:4px 8px;">`;
            }

            html += `
                <tr>
                    <td data-label="Rule">
                        <strong>${escapeHtml(r.rule_name)}</strong><br>
                        <small class="text-muted">${escapeHtml(r.description || '')}</small>
                    </td>
                    <td data-label="Period">${escapeHtml(r.rule_period)}</td>
                    <td data-label="Value">
                        ${inputHtml}
                    </td>
                    <td data-label="Action">
                        <button class="btn btn-sm btn-primary" onclick="updateRule(${r.id})">Save</button>
                    </td>
                </tr>`;
        });
        html += `</tbody></table></div></div>`;
        container.innerHTML = html;
        return;
    }

    // -- Faculty: Apply Leave --
    if (viewId === 'apply') {
        if (!state.facultyCache) {
            showLoader();
            // Fetch eligible substitutes (Faculty/HoD)
            const facultyList = await apiCall('/users.php/faculty');

            // Filter out self
            const substitutes = facultyList ? facultyList.filter(u => u.id != state.user.id) : [];
            state.facultyCache = substitutes.map(u => `<option value="${u.id}">${u.name} (${u.department || ''})</option>`).join('');
        }

        window.cachedSubOptions = state.facultyCache;

        container.innerHTML = `
            <div class="glass-card" style="padding: 32px; max-width: 800px; margin: 0 auto;">
                <h2 style="margin-bottom: 24px; color: var(--primary);"><i class="fa fa-file-pen" style="margin-right: 8px;"></i> Apply for Leave</h2>
                <form onsubmit="handleApplyLeave(event)" class="form-grid">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select class="form-control" name="leave_type" required size="1">
                            <option value="" disabled selected>-- Select Leave Type --</option>
                            <option value="Sick">Sick Leave</option>
                            <option value="Casual">Casual Leave</option>
                            <option value="Academic">Academic Leave</option>
                            <option value="OD">Permission for Other Duty</option>
                            <option value="ED">Permission for Other Duty</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required onchange="calculateDaysAndShowSubstitutes()">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required onchange="calculateDaysAndShowSubstitutes()">
                    </div>

                    <div id="substitution-section" class="form-group full-width" style="display: none; background: rgba(79, 70, 229, 0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(79, 70, 229, 0.2);">
                        <h4 style="margin-top: 0; margin-bottom: 10px; color: var(--primary);">Hour-wise Substitutes</h4>
                        <p style="font-size: 0.85em; color: var(--text-muted); margin-bottom: 15px;">For leaves 5 days or less, you can assign substitutes per hour. If more than 5 days, skip this.</p>
                        <div id="substitution-days-container"></div>
                    </div>

                    <div class="form-group full-width">
                        <label>Reason</label>
                        <textarea class="form-control" name="reason" placeholder="Explain the reason for your leave..." required></textarea>
                    </div>
                    
                    <div class="form-group full-width" style="margin-top: 10px;">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-paper-plane"></i> Submit Request</button>
                    </div>
                </form>
            </div>
        `;
        return;
    }

    // -- Faculty: Permission --
    if (viewId === 'apply-permission') {
        showLoader();
        const historyRes = await apiCall('/permissions.php/my');

        let html = `
            <div class="card" style="max-width: 800px; margin: 0 auto 30px auto;">
                <div class="card-header">
                    <h3 class="card-title">Apply for Permission</h3>
                </div>
                <div style="padding: 24px;">
                    <form onsubmit="handleApplyPermission(event)" class="form-grid">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="permission_date" class="form-control" required>
                        </div>
                        <div class="grid-2col">
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Briefly specify the reason..." required></textarea>
                        </div>
                        <div class="form-group full-width" style="margin-top: 10px;">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card" style="max-width: 800px; margin: 0 auto;">
                <div class="card-header">
                    <h3 class="card-title">My Permission History</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>`;

        if (historyRes && historyRes.length > 0) {
            historyRes.forEach(p => {
                const statusClass = p.status.toLowerCase().replace(' ', '-');
                const pdfLink = p.status === 'Approved' ? `<button class="btn btn-sm btn-secondary" onclick="downloadPermissionPdf(${p.id})"><i class="fas fa-file-pdf"></i></button>` : '-';
                html += `
                    <tr>
                        <td data-label="Date">${p.permission_date}</td>
                        <td data-label="Time">${formatTimeTo12Hour(p.start_time)} - ${formatTimeTo12Hour(p.end_time)}</td>
                        <td data-label="Status"><span class="badge badge-${statusClass}">${p.status}</span></td>
                        <td data-label="PDF">${pdfLink}</td>
                    </tr>`;
            });
        } else {
            html += `<tr><td colspan="4" style="text-align:center; padding:24px; color:var(--text-muted);">No permissions found.</td></tr>`;
        }

        html += `</tbody></table></div></div>`;
        container.innerHTML = html;
        return;
    }

    // -- Faculty: Outpass --
    if (viewId === 'apply-outpass') {
        showLoader();
        const historyRes = await apiCall('/outpasses.php/my');

        container.innerHTML = `
            <div class="card" style="max-width: 800px; margin: 0 auto 30px auto;">
                <div class="card-header">
                    <h3 class="card-title">Request Outpass</h3>
                </div>
                <div style="padding: 24px;">
                    <form onsubmit="handleApplyOutpass(event)" class="form-grid">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="outpass_date" class="form-control" required>
                        </div>
                        <div class="grid-2col">
                            <div class="form-group">
                                <label>Out Time</label>
                                <input type="time" name="out_time" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>In Time (Expected)</label>
                                <input type="time" name="in_time" class="form-control">
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Reason and destination..." required></textarea>
                        </div>
                        <div class="form-group full-width" style="margin-top: 10px;">
                            <button type="submit" class="btn btn-primary btn-block">Submit Outpass</button>
                        </div>
                    </form>
                </div>
            </div>`;
        return;
    }

    // -- Faculty: My History --
    if (viewId === 'leaves') {
        showLoader();
        const leaves = await apiCall('/leaves.php/my-leaves');

        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Personal Leave History</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Date Range</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>PDF</th>
                            </tr>
                        </thead>
                        <tbody>`;

        if (leaves && leaves.length > 0) {
            leaves.forEach(l => {
                const hStatus = l.hod_status.toLowerCase();
                const pStatus = l.principal_status.toLowerCase();
                const pdfLink = l.principal_status === 'Approved' ? `<button class="btn btn-sm btn-secondary" onclick="downloadPdf(${l.id})"><i class="fas fa-file-pdf"></i></button>` : '-';

                html += `
                    <tr>
                        <td data-label="Type"><strong>${escapeHtml(l.leave_type)}</strong></td>
                        <td data-label="Date Range">${l.start_date} → ${l.end_date}</td>
                        <td data-label="Reason" class="text-muted">${escapeHtml(l.reason)}</td>
                        <td data-label="Status">
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <span class="badge badge-${hStatus}" style="font-size:0.7rem;">HOD: ${l.hod_status}</span>
                                <span class="badge badge-${pStatus}" style="font-size:0.7rem;">Principal: ${l.principal_status}</span>
                            </div>
                        </td>
                        <td data-label="PDF">${pdfLink}</td>
                    </tr>`;
            });
        } else {
            html += `<tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">No history records.</td></tr>`;
        }
        html += `</tbody></table></div></div>`;
        container.innerHTML = html;
        return;
    }

    // -- Faculty: Substitutions --
    if (viewId === 'substitutions') {
        showLoader();
        const substitutions = await apiCall('/leaves.php/substitutions/pending');

        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Substitution Requests</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>Date</th>
                                <th>Hour</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>`;

        if (substitutions && substitutions.length > 0) {
            substitutions.forEach(s => {
                html += `
                    <tr>
                        <td data-label="Faculty"><strong>${escapeHtml(s.faculty_name)}</strong></td>
                        <td data-label="Date">${s.leave_date}</td>
                        <td data-label="Hour">Hour ${s.hour}</td>
                        <td data-label="Action">
                            <div style="display:flex; gap:8px;">
                                <button class="btn btn-sm btn-primary" onclick="actionSubstitution(${s.id}, 'ACCEPTED')">Accept</button>
                                <button class="btn btn-sm btn-secondary" onclick="actionSubstitution(${s.id}, 'REJECTED')">Reject</button>
                            </div>
                        </td>
                    </tr>`;
            });
        } else {
            html += `<tr><td colspan="4" style="text-align:center; padding:32px; color:var(--text-muted);">No pending substitution requests.</td></tr>`;
        }
        html += `</tbody></table></div></div>`;
        container.innerHTML = html;
        return;
    }

    // -- Approvals (HoD / Principal) --
    if (viewId === 'approvals') {
        showLoader();
        let endpoint = '';
        if (role === 'hod') endpoint = '/leaves.php/pending/hod';
        else if (role === 'principal') endpoint = '/leaves.php/pending/principal';

        const leaves = await apiCall(endpoint);

        let html = `
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Pending Leave Approvals</h3>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>Leave Details</th>
                                <th>Substitutes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>`;

        if (leaves && leaves.length > 0) {
            leaves.forEach(l => {
                html += `
                    <tr>
                        <td data-label="Faculty">
                            <strong>${escapeHtml(l.faculty_name)}</strong><br>
                            <small class="text-muted">${escapeHtml(l.department)}</small>
                        </td>
                        <td data-label="Leave Details">
                            <strong style="color:var(--primary);">${escapeHtml(l.leave_type)}</strong><br>
                            <small>${l.start_date} to ${l.end_date}</small>
                        </td>
                        <td data-label="Substitutes"><small>${escapeHtml(l.substitutions_v2 || 'None')}</small></td>
                        <td data-label="Action">
                            <div style="display:flex; gap:8px;">
                                <button class="btn btn-sm btn-primary" onclick="approveLeave(${l.id})">Approve</button>
                                <button class="btn btn-sm btn-secondary" onclick="rejectLeave(${l.id})">Reject</button>
                            </div>
                        </td>
                    </tr>`;
            });
        } else {
            html += `<tr><td colspan="4" style="text-align:center; padding:32px; color:var(--text-muted);">No pending approvals.</td></tr>`;
        }
        html += `</tbody></table></div></div>`;
        container.innerHTML = html;
        return;
    }

    if (viewId === 'profile') {
        showLoader();
        const me = await apiCall('/users.php/me');

        if (!me) {
            container.innerHTML = `<div class="error-state">Failed to load profile data.</div>`;
            return;
        }

        container.innerHTML = `
            <div class="profile-container">
                <div class="profile-header-card animate-fade-in">
                    <div class="profile-avatar-large">
                        ${me.name.charAt(0)}
                    </div>
                    <div class="profile-title">
                        <h2>${escapeHtml(me.name)}</h2>
                        <span class="role-badge-large">${escapeHtml(me.role.toUpperCase())}</span>
                    </div>
                </div>

                <div class="profile-grid">
                    <div class="profile-card animate-fade-in" style="animation-delay: 0.1s;">
                        <div class="card-header">
                            <i class="fas fa-user-circle"></i>
                            <h3>Personal Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-id-badge"></i></div>
                                <div class="info-content">
                                    <label>Employee Code</label>
                                    <p>${escapeHtml(me.employee_code || 'N/A')}</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-building"></i></div>
                                <div class="info-content">
                                    <label>Department</label>
                                    <p>${escapeHtml(me.department || 'N/A')}</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-briefcase"></i></div>
                                <div class="info-content">
                                    <label>Designation</label>
                                    <p>${escapeHtml(me.designation || 'N/A')}</p>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-envelope"></i></div>
                                <div class="info-content">
                                    <label>Email Address</label>
                                    <p>${escapeHtml(me.email || 'N/A')}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>`;
        return;
    }
}


// --- Handlers ---
async function handleApplyLeave(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    // Validation
    if (!data.start_date || !data.end_date) {
        showToast("Please select dates.", "warning");
        return;
    }

    // Calculate days for UI validation mapping
    const start = new Date(data.start_date);
    const end = new Date(data.end_date);

    // Sunday Validation
    if (start.getDay() === 0 || end.getDay() === 0) {
        showToast("Leave cannot start or end on a Sunday.", "warning");
        return;
    }

    const timeDiff = end.getTime() - start.getTime();
    const totalDays = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;

    // Format Substitutions based on duration
    data.substitutions = [];
    let hasSelectedSubstitute = false;

    // Check all keys for substitution data
    Object.keys(data).forEach(key => {
        if (key.startsWith('substitute_hour_')) {
            const sid = data[key];
            if (sid) {
                // Extract hour and date from key: substitute_hour_${h}_date_${isoDate}
                const parts = key.split('_');
                const hour = parts[2];
                const date = parts[4];
                data.substitutions.push({ substitute_id: sid, hour: hour, date: date });
                hasSelectedSubstitute = true;
            }
            delete data[key];
        }
    });

    const startD = new Date(data.start_date);
    const endD = new Date(data.end_date);
    const diffDays = Math.ceil((endD - startD) / (1000 * 60 * 60 * 24)) + 1;

    // For 5 days or less, ensure at least one substitute is picked
    if (diffDays <= 5 && !hasSelectedSubstitute) {
        showToast("Please select at least one hourly substitute for leaves 5 days or less.", "warning");
        return;
    }


    // If > 5 days, substitution array is just empty.
    const res = await apiCall('/leaves.php/apply', 'POST', data);
    if (!res.error) {
        showToast('Leave Applied! Waiting for Substitute Acceptance.', 'success');
        delete state.cache['analytics']; // Purge cache
        renderView('leaves');
    } else {
        showToast(res.error, 'error');
    }
}

async function actionSubstitution(id, status) {
    const verb = status === 'ACCEPTED' ? 'accept' : 'reject';
    if (!confirm(`Are you sure you want to ${verb} this request?`)) return;
    const res = await apiCall(`/leaves.php/substitutions/${id}/respond`, 'PUT', { status });
    if (!res.error) {
        delete state.cache['analytics']; // Purge cache
        renderView('substitutions');
        fetchNotifications();
    } else {
        showToast(res.error, 'error');
    }
}

async function approveLeave(id, status) {
    let role = state.user.role;
    let endpoint = '';
    if (role === 'hod') endpoint = `/leaves.php/${id}/approve/hod`;
    if (role === 'principal') endpoint = `/leaves.php/${id}/approve/principal`;

    const res = await apiCall(endpoint, 'PUT', { status });
    if (!res.error) {
        delete state.cache['analytics']; // Purge cache
        renderView('approvals');
    } else {
        showToast(res.error, 'error');
    }
}

// --- Helpers ---

function showCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'flex';
}

function hideCreateUserModal() {
    document.getElementById('createUserModal').style.display = 'none';
    const form = document.getElementById('userForm');
    if (form) form.reset();

    // Reset to Create mode
    document.getElementById('userModalTitle').textContent = 'Create New User';
    document.getElementById('formMode').value = 'create';
    document.getElementById('editUserId').value = '';
    document.getElementById('userSubmitBtn').textContent = 'Create User';
    document.getElementById('employee_code').readOnly = false;
    document.getElementById('passwordHelp').style.display = 'none';
    document.getElementById('password_input').required = true;
}

window.editUser = async function (userId, page) {
    state.usersPage = page;

    // Show modal
    document.getElementById('createUserModal').style.display = 'flex';
    document.getElementById('userModalTitle').textContent = 'Update User';
    document.getElementById('formMode').value = 'update';
    document.getElementById('editUserId').value = userId;
    document.getElementById('userSubmitBtn').textContent = 'Update User';
    document.getElementById('employee_code').readOnly = true;
    document.getElementById('passwordHelp').style.display = 'block';
    document.getElementById('password_input').required = false;

    // Fetch user details - since response is now paginated, we search for the user
    // To be safe, we can fetch all or just use the current page data if they are there.
    // For now, let's just fetch the current page.
    const response = await apiCall(`/users.php?page=${page}&limit=20`);
    if (!response || !response.users) return;
    const user = response.users.find(u => u.id == userId);


    if (user) {
        document.getElementById('employee_code').value = user.employee_code || '';
        document.getElementById('user_name_input').value = user.name || '';
        document.getElementById('username_input').value = user.username || '';
        document.getElementById('role_input').value = user.role || '';
        document.getElementById('department_input').value = user.department || '';
    }
};

async function handleCreateUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    const mode = data.mode || 'create';
    let res;

    if (mode === 'update') {
        data.action = 'update';
        res = await apiCall('/manage_user.php', 'POST', data);
    } else {
        // If username is empty, we don't have email anymore to fall back to in backend smoothly without changes
        // But the form says username is required. So we just send data as is.
        // The implementation plan says "remove email from data object"
        delete data.email;
        res = await apiCall('/users.php/create', 'POST', data);
    }

    if (res && !res.error) {
        showToast(mode === 'update' ? 'User updated successfully!' : 'User created successfully!', 'success');
        hideCreateUserModal();
        renderView('users', state.usersPage); // Refresh user list on current page
        e.target.reset(); // Clear form
    } else {
        showToast(res?.error || 'Operation failed', 'error');
    }
}


async function deleteUser(id, page) {
    if (!confirm('Delete this user?')) return;
    const res = await apiCall(`/users.php/${id}`, 'DELETE');
    if (!res.error) {
        showToast('User deleted', 'success');
        renderView('users', page);
    } else {
        showToast(res.error, 'error');
    }
}

window.filterAdminUsers = function () {
    const search = document.getElementById('adminUserSearch').value;
    const dept = document.getElementById('adminUserDept').value;
    renderView('users', 1, search, dept);
};


async function downloadPdf(id) {
    console.log(`[DEBUG] downloadPdf called for ID: ${id}`);
    if (!state.token) {
        console.error('[DEBUG] No token found');
        return;
    }

    // Direct Open Method - Bypass Blob/Fetch entirely
    // This relies on the browser to handle the file download/display
    // We attach the token to the URL so the server can validate it (if we change server to accept GET token)
    // BUT generate_pdf.php expects Bearer header.

    // WORKAROUND: We will use the fetch to get the blob, but then immediately 
    // try to open it as a straightforward Object URL without any fancy anchor tag stuff first.
    // If that fails, we will try the "download" attribute approach again but simpler.
    if (!state.token) return;
    try {
        const response = await fetch(`${API_URL}/generate_pdf.php?id=${id}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${state.token}`
            }
        });

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Leave_Application_${id}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } else {
            const err = await response.text();
            showToast('Failed to download PDF: ' + err, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Download failed', 'error');
    }
}

async function updateRule(id) {
    const valInput = document.getElementById(`rule-val-${id}`);
    if (!valInput) return;

    let newVal = valInput.value;
    if (valInput.dataset.isTime === 'true') {
        newVal = parseInt(newVal.replace(':', ''), 10);
    }

    if (newVal === '' || isNaN(newVal)) {
        showToast("Please enter a valid value", "warning");
        return;
    }

    if (!confirm('Update this policy rule?')) return;

    const res = await apiCall(`/rules.php/${id}`, 'PUT', { rule_value: parseFloat(newVal) });
    if (!res || res.error) {
        showToast(res?.error || "Failed to update rule. Invalid CSRF token or server error.", "error");
    } else {
        showToast("Rule updated successfully!", "success");
        renderView('rules');
    }
}

function calculateDaysAndShowSubstitutes() {
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const substitutionSection = document.getElementById('substitution-section');

    if (!startInput || !endInput || !substitutionSection) return;

    const startDate = new Date(startInput.value);
    const endDate = new Date(endInput.value);

    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
        substitutionSection.style.display = 'none';
        return;
    }

    // Calculate inclusive days difference
    const diffTime = endDate.getTime() - startDate.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    const container = document.getElementById('substitution-days-container');

    if (diffDays >= 1 && diffDays <= 5) {
        substitutionSection.style.display = 'block';
        if (container) {
            let html = '';
            for (let d = 0; d < diffDays; d++) {
                let dayDate = new Date(startDate);
                dayDate.setDate(startDate.getDate() + d);
                // Format nicely
                let dateStr = dayDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

                html += `<div style="margin-bottom: 20px;">
                            <h5 style="margin-top:0; margin-bottom:10px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; color: var(--text-color);">Day ${d + 1} (${dateStr})</h5>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">`;
                for (let h = 1; h <= 8; h++) {
                    const isoDate = dayDate.toISOString().split('T')[0];
                    html += `   <div>
                                    <label style="font-size: 0.85em;">Hour ${h}</label>
                                    <select class="form-control" name="substitute_hour_${h}_date_${isoDate}" style="padding: 5px; font-size: 0.9em;">
                                        <option value="">-- Select --</option>
                                        ${window.cachedSubOptions || ''}
                                    </select>
                                </div>`;
                }
                html += `   </div>
                         </div>`;
            }
            container.innerHTML = html;
        }
    } else {
        substitutionSection.style.display = 'none';
        if (container) container.innerHTML = '';
    }
}

function formatTimeTo12Hour(timeString) {
    if (!timeString) return '';
    let [hours, minutes] = timeString.split(':');
    hours = parseInt(hours, 10);
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const strHours = hours < 10 ? '0' + hours : hours;
    return `${strHours}:${minutes} ${ampm}`;
}

async function handleApplyPermission(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Hardcoded time validation removed to support dynamic leave rules from admin settings. 
    // Backend handles precise validation and returns friendly error messages.

    showToast('Submitting permission request...', 'info');
    const res = await apiCall('/permissions.php/apply', 'POST', data);
    if (!res.error) {
        showToast('Permission requested successfully!', 'success');
        renderView('apply-permission');
    } else {
        showToast(res.error, 'error');
    }
}

async function handleApplyOutpass(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    if (!data.out_time) {
        showToast("Please specify your Out Time.", "warning");
        return;
    }

    showToast('Submitting outpass request...', 'info');
    const res = await apiCall('/outpasses.php/apply', 'POST', data);
    if (!res.error) {
        showToast('Outpass requested successfully!', 'success');
        renderView('apply-outpass');
    } else {
        showToast(res.error, 'error');
    }
}

async function downloadPermissionPdf(id) {
    showNotification('Generating Permission PDF...', 'info');
    try {
        const response = await fetch(`${API_URL}/generate_permission_pdf.php?id=${id}`, {
            headers: { 'Authorization': `Bearer ${safeStorage.getItem('token')}` }
        });
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Permission_${id}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
        } else {
            const err = await response.text();
            showToast('Failed: ' + err, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Error: ' + e.message, 'error');
    }
}

async function downloadOutpassPdf(id) {
    showNotification('Generating Outpass PDF...', 'info');
    try {
        const response = await fetch(`${API_URL}/generate_outpass_pdf.php?id=${id}`, {
            headers: { 'Authorization': `Bearer ${safeStorage.getItem('token')}` }
        });
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Outpass_${id}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
        } else {
            const err = await response.text();
            showToast('Failed: ' + err, 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Error: ' + e.message, 'error');
    }
}

function showNotification(msg, type) {
    console.log(`[Notification - ${type}]: ${msg}`);
}

window.downloadPdf = downloadPdf;
window.downloadPermissionPdf = downloadPermissionPdf;
window.downloadOutpassPdf = downloadOutpassPdf;
window.handleApplyPermission = handleApplyPermission;
window.handleApplyOutpass = handleApplyOutpass;
window.updateRule = updateRule;
window.calculateDaysAndShowSubstitutes = calculateDaysAndShowSubstitutes;

// Export to Global
window.login = login;
window.logout = logout;
window.initDashboard = initDashboard;
window.toggleNotifications = toggleNotifications;
window.handleApplyLeave = handleApplyLeave;
window.actionSubstitution = actionSubstitution;
window.approveLeave = approveLeave;
window.renderView = renderView;

window.showCreateUserModal = showCreateUserModal;
window.hideCreateUserModal = hideCreateUserModal;
window.handleCreateUser = handleCreateUser;
window.deleteUser = deleteUser;

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('active');
    if (overlay) {
        overlay.classList.toggle('active');
    }
}
window.toggleSidebar = toggleSidebar;

window.loadDepartmentDrilldown = async function (dept) {
    const container = document.getElementById('drilldown-modal-container');
    if (!container) return;

    // Show Loading Schema
    container.innerHTML = `
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(3px);">
        <div class="glass-card" style="background: var(--card-bg, #fff); padding: 30px; border-radius: 12px; max-width: 800px; width: 90%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <button onclick="document.getElementById('drilldown-modal-container').style.display='none'" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
            <h2 style="margin-top: 0; color: var(--primary);"><i class="fa fa-building" style="margin-right: 10px;"></i> ${escapeHtml(dept)} Department Leaves</h2>
            <p style="text-align:center; padding: 20px;"><i class="fa fa-spinner fa-spin"></i> Loading records...</p>
        </div>
    </div>`;
    container.style.display = 'block';

    const data = await apiCall(`/analytics.php?department=${encodeURIComponent(dept)}`);
    if (!data || !data.department_drilldown) {
        container.querySelector('div.glass-card').innerHTML = `
            <button onclick="document.getElementById('drilldown-modal-container').style.display='none'" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #e74c3c;">&times;</button>
            <h2 style="margin-top: 0; color: #e74c3c;">Error</h2>
            <p>Failed to load data for this department.</p>
        `;
        return;
    }

    let html = `<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; background: rgba(0,0,0,0.03); padding: 15px; border-radius: 8px;">
                    <div style="text-align: center;"><strong>Total</strong><br><span style="font-size: 1.5rem;">${data.drilldown_stats.total}</span></div>
                    <div style="text-align: center; color: #f39c12;"><strong>Pending</strong><br><span style="font-size: 1.5rem;">${data.drilldown_stats.pending}</span></div>
                    <div style="text-align: center; color: #2ecc71;"><strong>Approved</strong><br><span style="font-size: 1.5rem;">${data.drilldown_stats.approved}</span></div>
                    <div style="text-align: center; color: #e74c3c;"><strong>Rejected</strong><br><span style="font-size: 1.5rem;">${data.drilldown_stats.rejected}</span></div>
                </div>`;

    if (data.department_drilldown.length === 0) {
        html += `<p style="text-align: center; opacity: 0.6; padding: 30px;">No leave requests found for this month.</p>`;
    } else {
        html += `<div class="table-container"><table>
                    <thead><tr><th>Faculty Name</th><th>Leave Type</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>`;
        data.department_drilldown.forEach(r => {
            let badgeClass = 'status-pending';
            if (r.status === 'Approved') badgeClass = 'status-approved';
            if (r.status === 'Rejected') badgeClass = 'status-rejected';

            html += `<tr>
                        <td data-label="Faculty Name"><strong>${escapeHtml(r.faculty_name)}</strong></td>
                        <td data-label="Leave Type"><i class="fa fa-tag" style="opacity:0.5; margin-right:5px;"></i> ${escapeHtml(r.leave_type)}</td>
                        <td data-label="Date">${escapeHtml(r.leave_date)}</td>
                        <td data-label="Status"><span class="status-badge ${badgeClass}">${escapeHtml(r.status)}</span></td>
                     </tr>`;
        });
        html += `</tbody></table></div>`;
    }

    container.querySelector('div.glass-card').innerHTML = `
        <button onclick="document.getElementById('drilldown-modal-container').style.display='none'" style="position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: var(--primary);"><i class="fa fa-building" style="margin-right: 10px;"></i> ${escapeHtml(dept)} Department Leaves <small style="font-size: 0.5em; color: var(--text-muted); font-weight: normal;">(This Month)</small></h2>
            <button class="btn btn-primary btn-sm" onclick="downloadAuditReport('${escapeHtml(dept)}')"><i class="fas fa-file-pdf"></i> PDF Report</button>
        </div>
        ${html}
    `;
};

async function downloadAuditReport(dept) {
    showToast('Generating Audit Report...', 'info');
    try {
        const response = await fetch(`${API_URL}/generate_report.php?department=${encodeURIComponent(dept)}`, {
            headers: { 'Authorization': `Bearer ${state.token}` }
        });
        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Leave_Audit_${dept.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
            showToast('Report downloaded successfully', 'success');
        } else {
            const err = await response.json();
            showToast('Failed: ' + (err.error || 'Unknown error'), 'error');
        }
    } catch (e) {
        console.error(e);
        showToast('Error generating report', 'error');
    }
}
window.downloadAuditReport = downloadAuditReport;

/**
 * Chart.js Initialization
 */
function initCharts(data, role) {
    const ctxTrend = document.getElementById('monthlyTrendsChart');
    const ctxDist = document.getElementById('leaveDistributionChart');

    if (ctxTrend) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthlyData = new Array(12).fill(0);

        if (data.monthly_trends) {
            data.monthly_trends.forEach(t => {
                const mIdx = parseInt(t.month) - 1;
                if (mIdx >= 0 && mIdx < 12) monthlyData[mIdx] = t.count;
            });
        }

        new Chart(ctxTrend, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Leaves Taken',
                    data: monthlyData,
                    backgroundColor: 'rgba(14, 165, 233, 0.6)',
                    borderColor: '#0ea5e9',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        padding: 12,
                        backgroundColor: 'rgba(30, 41, 59, 0.9)',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: window.innerWidth < 768 ? 10 : 12 } }
                    },
                    x: {
                        ticks: { font: { size: window.innerWidth < 768 ? 9 : 12 } }
                    }
                }
            }
        });
    }

    if (ctxDist) {
        let labelData = [];
        let valueData = [];
        const colors = ['#0ea5e9', '#6366f1', '#10b981', '#f59e0b', '#ef4444'];

        const distSource = isTeachingRole(role)
            ? data.distribution
            : (role === 'hod' ? data.department_distribution : data.dept_stats);

        if (distSource) {
            distSource.forEach(d => {
                labelData.push(d.leave_type || d.department || 'Other');
                valueData.push(d.count);
            });
        }

        new Chart(ctxDist, {
            type: 'doughnut',
            data: {
                labels: labelData,
                datasets: [{
                    data: valueData,
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: window.innerWidth < 768 ? 'bottom' : 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: window.innerWidth < 768 ? 11 : 13 }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }
}

/**
 * FullCalendar Initialization
 */
function initCalendar(data, role) {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    let events = [];

    if (role === 'hod' && data.department_coverage) {
        events = data.department_coverage.map(l => ({
            title: `${l.name} (${l.leave_type})`,
            start: l.start_date,
            end: l.end_date ? new Date(new Date(l.end_date).getTime() + 86400000).toISOString().split('T')[0] : l.start_date,
            className: `event-${l.hod_status?.toLowerCase() || 'approved'}`
        }));
    } else if (data.full_leave_calendar) {
        events = data.full_leave_calendar.map(l => ({
            title: `${l.type}: ${l.details}`,
            start: l.start,
            end: l.end ? new Date(new Date(l.end).getTime() + 86400000).toISOString().split('T')[0] : l.start,
            className: `event-${l.status}`
        }));
    } else if (data.global_coverage) {
        events = data.global_coverage.map(l => ({
            title: `${l.name} - ${l.department}`,
            start: l.start_date,
            end: l.end_date ? new Date(new Date(l.end_date).getTime() + 86400000).toISOString().split('T')[0] : l.start_date,
            className: 'event-approved'
        }));
    }

    const isMobile = window.innerWidth < 768;
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: isMobile ? 'listMonth' : 'dayGridMonth',
        headerToolbar: {
            left: isMobile ? 'prev,next' : 'prev,next today',
            center: 'title',
            right: isMobile ? 'listMonth' : 'dayGridMonth,timeGridWeek'
        },
        events: events,
        height: 'auto',
        eventClick: function (info) {
            showToast(info.event.title, 'info');
        },
        dayMaxEvents: true,
        handleWindowResize: true
    });

    calendar.render();
}

export {
    login,
    logout,
    initDashboard,
    toggleNotifications,
    handleApplyLeave,
    actionSubstitution,
    approveLeave,
    showCreateUserModal,
    hideCreateUserModal,
    handleCreateUser,
    handleUpdatePassword,
    deleteUser,
    downloadPdf,
    calculateDaysAndShowSubstitutes
};

async function handleUpdatePassword(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    if (data.new_password.length < 6) {
        showToast("New password must be at least 6 characters.", "warning");
        return;
    }

    const res = await apiCall('/change_password.php', 'POST', data);
    if (!res.error) {
        showToast("Password updated successfully!", "success");
        e.target.reset();
    } else {
        showToast(res.error, "error");
    }
}

// Init
if (window.location.pathname.includes('dashboard.html')) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDashboard);
    else initDashboard();
}
