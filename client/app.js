// Dynamic API URL for robustness
const getBaseUrl = () => {
    // If running solely from client folder, go up one level
    // Expected structure: .../client/index.html
    const path = window.location.pathname;

    // Logic for VS Code Live Server (usually port 5500 or 5501)
    if (window.location.port === '5500' || window.location.port === '5501') {
        const clientIndex = path.indexOf('/client/');
        if (clientIndex !== -1) {
            // Assume Backend is standard Apache on Port 80
            // We strip /client/ to get the root Project Folder name
            const backendRoot = path.substring(0, clientIndex);
            return 'http://localhost' + backendRoot + '/server/api';
        }
    }

    const clientIndex = path.indexOf('/client/');
    if (clientIndex !== -1) {
        return window.location.origin + path.substring(0, clientIndex) + '/server/api';
    }
    // Fallback if not inside /client/
    return '../server/api';
};
const API_URL = getBaseUrl();

// --- State Management ---
const state = {
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
    notifications: []
};

let availableSubstitutes = []; // Cache for dynamic rendering

// --- Helpers ---
function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// --- API Helper ---
async function apiCall(endpoint, method = 'GET', body = null) {
    const headers = { 'Content-Type': 'application/json' };
    if (state.token) headers['Authorization'] = `Bearer ${state.token}`;

    try {
        const response = await fetch(`${API_URL}${endpoint}`, {
            method,
            headers,
            body: body ? JSON.stringify(body) : null
        });

        if (response.status === 401 || response.status === 403) {
            const errData = await response.json().catch(() => ({ error: 'Unauthorized' }));
            // Only redirect if not already on index and not just a failed login
            if (!window.location.pathname.endsWith('index.html') && !endpoint.includes('/login')) {
                logout();
            }
            return errData;
        }

        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        if (!navigator.onLine) {
            alert('You are offline. Please check your internet connection.');
        } else {
            // Only alert if we haven't already alerted recently to avoid spam
            if (!window.hasAlertedConnectionError) {
                alert('Cannot connect to server. Please ensure XAMPP Apache is running and you are connected to localhost.');
                window.hasAlertedConnectionError = true;
                setTimeout(() => window.hasAlertedConnectionError = false, 10000);
            }
        }
        return null;
    }
}

// --- Auth Functions ---
async function login(username, password) {
    try {
        const data = await apiCall('/auth.php/login', 'POST', { username, password });
        if (data && data.token) {
            state.token = data.token;
            state.user = data.user;
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
            window.location.href = 'dashboard.html';
        } else {
            alert('Login Failed: ' + (data?.error || 'Unknown Error'));
        }
    } catch (e) {
        alert("System Error: " + e.message);
    }
}

function logout() {
    localStorage.clear();
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
    document.getElementById('user-name').textContent = state.user.name;
    document.getElementById('user-role').textContent = state.user.role.toUpperCase();

    // Init Notifications
    fetchNotifications();
    setInterval(fetchNotifications, 30000); // Poll every 30s

    // Render Sidebar
    renderSidebar();

    // Render Default View
    renderView(state.user.role === 'admin' ? 'users' : 'leaves');
}

async function fetchNotifications() {
    const notifs = await apiCall('/notifications.php/');
    if (notifs) {
        state.notifications = notifs;
        updateNotificationUI();
    }
}

function updateNotificationUI() {
    const badge = document.getElementById('notif-badge');
    const dropdown = document.getElementById('notif-dropdown');

    const unreadCount = state.notifications.filter(n => !n.is_read).length;
    badge.textContent = unreadCount;
    badge.style.display = unreadCount > 0 ? 'block' : 'none';

    // Header
    let html = `
        <div class="notification-header">
            <h3>Notifications</h3>
            ${unreadCount > 0 ? '<button class="clear-btn" onclick="markAllRead()">Mark all read</button>' : ''}
        </div>
        <div class="notification-list">
    `;

    if (state.notifications.length === 0) {
        html += '<div class="notification-empty"><i class="fa fa-bell-slash" style="font-size: 24px; margin-bottom: 10px; color: #cbd5e1; display: block;"></i>No notifications</div>';
    } else {
        state.notifications.forEach(n => {
            html += `
                <div class="notification-item ${n.is_read ? '' : 'unread'}" onclick="markNotificationRead(${n.id})">
                    <div class="notif-msg">${escapeHtml(n.message)}</div>
                    <div class="notif-time">${n.created_at ? new Date(n.created_at).toLocaleString() : ''}</div>
                </div>
            `;
        });
    }

    html += '</div>';
    dropdown.innerHTML = html;
}

function toggleNotifications(event) {
    if (event) event.stopPropagation(); // Prevent immediate close by document listener
    const d = document.getElementById('notif-dropdown');
    d.classList.toggle('active');
}

// Close Dropdown on Click Outside
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('notif-dropdown');
    const wrapper = document.getElementById('notification-wrapper');
    if (dropdown && dropdown.classList.contains('active')) {
        if (!wrapper.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    }
});

async function markNotificationRead(id) {
    await apiCall(`/notifications.php/${id}/read`, 'PUT');
    fetchNotifications();
}

async function markAllRead() {
    await apiCall('/notifications.php/read-all', 'PUT');
    fetchNotifications();
}

function renderSidebar() {
    const role = state.user.role;
    const menu = document.getElementById('sidebar-menu');
    menu.innerHTML = '';

    const items = [];

    if (role === 'admin') {
        items.push({ id: 'users', label: 'Manage Users', icon: '<i class="fa fa-users"></i>' });
    }

    if (role === 'faculty') {
        items.push({ id: 'apply', label: 'Apply Leave', icon: '<i class="fa fa-file-pen"></i>' });
        items.push({ id: 'leaves', label: 'My History', icon: '<i class="fa fa-calendar-days"></i>' });
        items.push({ id: 'substitutions', label: 'Substitution Requests', icon: '<i class="fa fa-rotate"></i>' });
    }

    if (role === 'hod' || role === 'principal' || role === 'admin') {
        items.push({ id: 'approvals', label: 'Pending Approvals', icon: '<i class="fa fa-clipboard-check"></i>' });
        // Signature Upload
        items.push({ id: 'signature', label: 'My Signature', icon: '<i class="fa fa-file-signature"></i>' });
    }

    if (role === 'principal' || role === 'admin') {
        items.push({ id: 'analytics', label: 'Analytics', icon: '<i class="fa fa-chart-line"></i>' });
    }

    items.forEach(item => {
        const div = document.createElement('div');
        div.className = 'nav-item';
        div.innerHTML = `<span>${item.icon}</span> ${item.label}`;
        div.onclick = () => {
            renderView(item.id);
            // Close sidebar on mobile if open
            const sidebar = document.querySelector('.sidebar');
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        };
        menu.appendChild(div);
    });
}

async function renderView(viewId) {
    const container = document.getElementById('content-area');
    container.innerHTML = 'Loading...';

    // -- Admin: Users --
    if (viewId === 'users' && state.user.role === 'admin') {
        const users = await apiCall('/users.php/');
        if (!users) return;

        let html = `
            <div class="header">
                <h2>User Management</h2>
                <button class="btn" onclick="showCreateUserModal()" style="width: auto;">+ Create User</button>
            </div>
            <div class="table-container">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Dept</th><th>Action</th></tr></thead>
                <tbody>
        `;
        users.forEach(u => {
            html += `<tr>
                <td data-label="Name">${escapeHtml(u.name)}</td>
                <td data-label="Email">${escapeHtml(u.email)}</td>
                <td data-label="Role"><span class="status-badge status-approved">${escapeHtml(u.role)}</span></td>
                <td data-label="Dept">${escapeHtml(u.department || '-')}</td>
                <td data-label="Action">
                    <button class="logout-btn" style="color:red; border-color:red" onclick="deleteUser(${u.id})">Delete</button>
                </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
        return;
    }

    // -- Faculty: Apply Leave --
    if (viewId === 'apply') {
        const facultyList = await apiCall('/users.php/faculty');
        availableSubstitutes = facultyList ? facultyList.filter(u => u.id != state.user.id) : [];

        container.innerHTML = `
            <div class="glass-card" style="max-width: 800px; margin: 0 auto;">
                <h2>Apply for Leave</h2>
                <form onsubmit="handleApplyLeave(event)">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select class="form-control" name="leave_type" required>
                            <option value="" disabled selected>-- Select Leave Type --</option>
                            <option value="Sick">Sick Leave</option>
                            <option value="Casual">Casual Leave</option>
                            <option value="Academic">Academic Leave</option>
                            <option value="OD">On Duty (OD)</option>
                            <option value="ED">External Duty (ED)</option>
                        </select>
                    </div>

                    <input type="hidden" name="duration_type" value="Days">

                    <div class="form-group" style="display:flex; gap:10px">
                        <div style="flex:1">
                            <label>Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required onchange="updateLeaveDuration()">
                        </div>
                        <div style="flex:1">
                            <label>End Date</label>
                            <input type="date" class="form-control" name="end_date" id="end_date" required onchange="updateLeaveDuration()">
                        </div>
                    </div>

                    <div id="substitution-container" style="margin-top:20px; border-top:1px solid #eee; padding-top:20px; display:none;">
                        <!-- Dynamic Content -->
                    </div>

                    <div class="form-group">
                        <label>Reason</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn">Submit Request</button>
                </form>
            </div>
        `;
        return;
    }

    // -- Faculty: Substitutions --
    if (viewId === 'substitutions') {
        const reqs = await apiCall('/leaves.php/substitutions/pending');
        let html = `<h2>Substitution Requests</h2><p>Requests assigned to you.</p><div class="table-container"><table>
            <thead><tr><th>Requester</th><th>Type</th><th>Date</th><th>Period</th><th>Reason</th><th>Action</th></tr></thead><tbody>`;

        if (reqs && reqs.length > 0) {
            reqs.forEach(r => {
                // Use specific substitution date and period
                html += `<tr>
                    <td data-label="Requester">${escapeHtml(r.requester_name)}</td>
                    <td data-label="Type">${escapeHtml(r.leave_type)}</td>
                    <td data-label="Date">${escapeHtml(r.date)}</td>
                    <td data-label="Period">P${escapeHtml(r.hour_slot)}</td>
                    <td data-label="Reason">${escapeHtml(r.reason)}</td>
                    <td data-label="Action">
                    <td data-label="Action">
                        <div style="display:flex; gap:10px; justify-content: flex-end;">
                            <button class="btn" style="width:auto; background:green; padding:8px 15px;" onclick="actionSubstitution(${r.id}, 'ACCEPTED')">Accept</button>
                            <button class="btn" style="width:auto; background:red; padding:8px 15px;" onclick="actionSubstitution(${r.id}, 'REJECTED')">Reject</button>
                        </div>
                    </td>
                </tr>`;
            });
        } else {
            html += '<tr><td colspan="5" style="text-align:center">No pending requests</td></tr>';
        }
        html += '</tbody></table></div>';
        container.innerHTML = html;
        return;
    }

    // -- Faculty: My History --
    if (viewId === 'leaves') {
        const leaves = await apiCall('/leaves.php/my-leaves');

        let html = `<h2>My Leave History</h2><br><div class="table-container"><table>
            <thead><tr><th>Type</th><th>Start</th><th>End</th><th>Reason</th><th>HoD Status</th><th>Principal Status</th><th>Action</th><th>PDF</th></tr></thead><tbody>`;

        if (leaves) leaves.forEach(l => {
            let timeInfo = `${l.start_date} to ${l.end_date}`;
            if (l.duration_type === 'Hours') {
                timeInfo = `${l.start_date} (Hours: ${l.selected_hours})`;
            }

            let actionHtml = '-';
            let pdfLink = '-';

            if (l.principal_status === 'Approved') {
                pdfLink = `<i class="fa fa-envelope" style="cursor:pointer; color:#4f46e5; font-size:1.2em" title="Download PDF" onclick="downloadPdf(${l.id})"></i>`;
            }

            html += `<tr>
                <td data-label="Type">${escapeHtml(l.leave_type)}</td>
                <td data-label="Start">${escapeHtml(timeInfo)}</td>
                <td data-label="End">${escapeHtml(l.end_date)}</td>
                <td data-label="Reason">${escapeHtml(l.reason)}</td>
                <td data-label="HoD Status"><span class="status-badge status-${l.hod_status.toLowerCase()}">${l.hod_status}</span></td>
                <td data-label="Princ Status"><span class="status-badge status-${l.principal_status.toLowerCase()}">${l.principal_status}</span></td>
                <td data-label="Action">${actionHtml}</td>
                <td data-label="PDF" style="text-align:center">${pdfLink}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
        return;
    }

    // -- Approvals (HoD / Principal) --
    if (viewId === 'approvals') {
        let endpoint = '';
        if (state.user.role === 'hod') endpoint = '/leaves.php/pending/hod';
        else if (state.user.role === 'principal') endpoint = '/leaves.php/pending/principal';

        const leaves = await apiCall(endpoint);

        let html = `<h2>Pending Approvals</h2><br><div class="table-container"><table>
            <thead><tr><th>Faculty</th><th>Dept</th><th>Type</th><th>Substitutions</th><th>Reason</th><th>Action</th></tr></thead><tbody>`;

        if (leaves && leaves.length > 0) {
            leaves.forEach(l => {
                let timeInfo = `${l.start_date} to ${l.end_date}`;
                if (l.duration_type === 'Hours') {
                    timeInfo = `${l.start_date} (Hours: ${l.selected_hours})`;
                }

                // Format substitutions if present (HoD specific mostly)
                let subHtml = '-';
                if (l.substitutions && l.substitutions.length > 0) {
                    subHtml = l.substitutions.map(s => `<div>${s.sub_name}: <b style="color:${s.status == 'ACCEPTED' ? 'green' : 'orange'}">${s.status}</b></div>`).join('');
                }

                html += `<tr>
                    <td data-label="Faculty">${escapeHtml(l.user_name || l.name)}</td>
                    <td data-label="Dept">${escapeHtml(l.department)}</td>
                    <td data-label="Type">${escapeHtml(l.leave_type)}</td>
                    <td data-label="Substitutions"><small>${subHtml}</small></td>
                    <td data-label="Reason">${escapeHtml(l.reason)}</td>
                    <td data-label="Action">
                         <div style="display:flex; gap:10px; justify-content: flex-end;">
                            <button class="btn" style="width:auto; padding:8px 15px; background:green" onclick="approveLeave(${l.id}, 'Approved')">✓</button>
                            <button class="btn" style="width:auto; padding:8px 15px; background:red" onclick="approveLeave(${l.id}, 'Rejected')">✗</button>
                        </div>
                    </td>
                </tr>`;
            });
        } else {
            html += '<tr><td colspan="6" style="text-align:center">No pending requests</td></tr>';
        }
        html += '</tbody></table></div>';
        container.innerHTML = html;
        return;
    }

    // -- Signature Upload --
    if (viewId === 'signature') {
        container.innerHTML = `
            <div class="glass-card" style="max-width: 500px; margin: 0 auto; text-align: center;">
                <h2>Digital Signature</h2>
                <p>Upload a PNG/JPG check image of your signature (Max 2MB). This will be stamped on approved leaves.</p>
                <br>
                <div id="sig-preview" style="margin-bottom: 20px;">
                    <!-- Preview Here -->
                    <div style="border: 2px dashed #ccc; padding: 20px; color: #aaa;">No Signature Uploaded</div>
                </div>
                
                <form onsubmit="handleSignatureUpload(event)">
                    <input type="file" name="signature" accept="image/*" required style="margin-bottom: 15px;">
                    <br>
                    <button type="submit" class="btn">Upload / Update</button>
                </form>
            </div>
        `;
        return;
    }

    // -- Analytics --
    if (viewId === 'analytics') {
        const stats = await apiCall('/analytics.php');
        if (!stats) return;

        let deptRows = stats.dept_stats.map(d => `
            <div style="margin-bottom: 10px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <strong>${d.department || 'Unknown'}</strong>
                    <span>${d.count}</span>
                </div>
                <div style="background:#ddd; height:10px; border-radius:5px; overflow:hidden;">
                    <div style="background:#4f46e5; height:100%; width:${Math.min(d.count * 10, 100)}%;"></div>
                </div>
            </div>
        `).join('');

        if (stats.dept_stats.length === 0) deptRows = '<p>No leaves recorded this month.</p>';

        container.innerHTML = `
            <h2>Dashboard Analytics</h2>
            <br>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="glass-card" style="text-align:center; padding: 20px;">
                    <h3 style="color:#4f46e5; font-size: 2em; margin:0;">${stats.leaves_today}</h3>
                    <p>Leaves Today</p>
                </div>
                <div class="glass-card" style="text-align:center; padding: 20px;">
                    <h3 style="color:#eab308; font-size: 2em; margin:0;">${stats.pending_approvals}</h3>
                    <p>Pending Approvals</p>
                </div>
            </div>

            <div class="glass-card">
                <h3>Department-wise Leaves (This Month)</h3>
                <br>
                ${deptRows}
            </div>
        `;
        return;
    }
}

async function handleSignatureUpload(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    // Manual fetch because apiCall handles JSON body by default
    try {
        const token = localStorage.getItem('token');
        const res = await fetch(API_URL + '/upload_signature.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });
        const data = await res.json();

        if (res.ok) {
            alert('Signature Uploaded!');
            renderView('signature'); // Refresh
        } else {
            alert(data.error || 'Upload Failed');
        }
    } catch (err) {
        alert('Upload Error: ' + err.message);
    }
}

// --- Handlers ---
async function handleApplyLeave(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    // Basic Date Validation
    if (!data.start_date || !data.end_date) {
        alert("Please select both Start and End dates.");
        return;
    }

    const start = new Date(data.start_date);
    const end = new Date(data.end_date);

    if (end < start) {
        alert("End Date cannot be before Start Date.");
        return;
    }

    const diffTime = Math.abs(end - start);
    const totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

    // Substitution Collection & Validation
    const substitutions = [];

    if (totalDays <= 4) {
        for (let d = 1; d <= totalDays; d++) {
            for (let p = 1; p <= 8; p++) {
                const subId = document.getElementById(`sub_d${d}_p${p}`).value;

                // Optional Substitution: Only process if a substitute is selected
                if (subId) {
                    if (subId == state.user.id) {
                        alert(`You cannot substitute for yourself (Day ${d}, Period ${p}).`);
                        return;
                    }
                    // Map to 1-32 index (Day 1: 1-8, Day 2: 9-16, etc.)
                    const hourIndex = ((d - 1) * 8) + p;
                    substitutions.push({ substitute_id: subId, hour: hourIndex });
                }
            }
        }
    }

    data.substitutions = substitutions;

    const res = await apiCall('/leaves.php/apply', 'POST', data);
    if (!res.error) {
        alert('Leave Applied Successfully!');
        renderView('leaves');
    } else {
        alert(res.error);
    }
}

async function actionSubstitution(id, status) {
    const verb = status === 'ACCEPTED' ? 'accept' : 'reject';
    if (!confirm(`Are you sure you want to ${verb} this request?`)) return;
    const res = await apiCall(`/leaves.php/substitutions/${id}/respond`, 'PUT', { status });
    if (!res.error) {
        renderView('substitutions');
        fetchNotifications();
    } else {
        alert(res.error);
    }
}

async function approveLeave(id, status) {
    let role = state.user.role;
    let endpoint = '';
    if (role === 'hod') endpoint = `/leaves.php/${id}/approve/hod`;
    if (role === 'principal') endpoint = `/leaves.php/${id}/approve/principal`;

    const res = await apiCall(endpoint, 'PUT', { status });
    if (!res.error) {
        if (res.pdf_url && role === 'principal') {
            downloadPdf(id);
        }
        renderView('approvals');
    } else {
        alert(res.error);
    }
}

// --- Helpers ---
function updateLeaveDuration() {
    const startStr = document.getElementById('start_date').value;
    const endStr = document.getElementById('end_date').value;
    const container = document.getElementById('substitution-container');

    if (!startStr || !endStr) {
        container.style.display = 'none';
        return;
    }

    const start = new Date(startStr);
    const end = new Date(endStr);

    if (end < start) {
        alert("End Date cannot be before Start Date."); // Pop-up alert as requested
        document.getElementById('end_date').value = ''; // Reset invalid date
        container.style.display = 'none';
        return;
    }

    const diffTime = Math.abs(end - start);
    const totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

    let subOptions = availableSubstitutes.map(u => `<option value="${u.id}">${u.name} (${u.department || ''})</option>`).join('');
    let defaultOptions = `<option value="">Select Substitute...</option>${subOptions}`;

    let html = '';

    if (totalDays > 4) {
        html = `
            <div style="padding:15px; background:#eef2ff; border-radius:8px; border:1px solid #c7d2fe; color:#3730a3">
                <i class="fa fa-info-circle"></i> 
                For leave exceeding 4 days, hour-wise substitution is not required.
            </div>
        `;
    } else {
        // Render periods for each day
        for (let d = 1; d <= totalDays; d++) {
            html += `<h3>Day ${d} – Class Substitution</h3>`;
            html += renderPeriodRows(d, defaultOptions);
            if (d < totalDays) html += '<br>';
        }
    }

    container.innerHTML = html;
    container.style.display = 'block';
}

function renderPeriodRows(dayNum, options) {
    let rows = `
        <table style="width:100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9em;">
            <thead>
                <tr style="background: #f3f4f6; text-align: left;">
                    <th style="padding: 8px; border: 1px solid #e5e7eb; width: 60px;">Period</th>
                    <th style="padding: 8px; border: 1px solid #e5e7eb;">Substitute Faculty</th>
                </tr>
            </thead>
            <tbody>
    `;

    for (let i = 1; i <= 8; i++) {
        rows += `
            <tr>
                <td data-label="Period" style="padding: 8px; border: 1px solid #e5e7eb; font-weight: bold; text-align: center;">P${i}</td>
                <td data-label="Substitute Faculty" style="padding: 8px; border: 1px solid #e5e7eb;">
                    <select class="form-control" id="sub_d${dayNum}_p${i}" style="width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 4px;">
                        ${options}
                    </select>
                </td>
            </tr>
        `;
    }

    rows += `</tbody></table>`;
    return rows;
}

function showCreateUserModal() { document.getElementById('createUserModal').classList.add('active'); }
function hideCreateUserModal() { document.getElementById('createUserModal').classList.remove('active'); }

async function handleCreateUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    const res = await apiCall('/users.php/create', 'POST', data);
    if (!res.error) {
        alert('User Created!');
        hideCreateUserModal();
        renderView('users');
    } else {
        alert(res.error);
    }
}

async function deleteUser(id) {
    if (!confirm('Delete this user?')) return;
    await apiCall(`/users.php/${id}`, 'DELETE');
    renderView('users');
}

async function downloadPdf(id) {
    if (!state.token) return;
    try {
        const response = await fetch(`${API_URL}/generate_pdf.php?id=${id}`, {
            method: 'GET',
            headers: { 'Authorization': `Bearer ${state.token}` }
        });

        if (response.ok) {
            const data = await response.arrayBuffer();
            const blob = new Blob([data], { type: 'application/pdf' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Leave_Application_${id}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } else {
            const text = await response.text();
            let msg = text;
            try {
                const json = JSON.parse(text);
                msg = json.message || json.error || text;
            } catch (_) { }
            alert('Failed to download PDF: ' + (msg || 'Unknown error'));
        }
    } catch (e) {
        console.error(e);
        alert('Download failed. Check your connection.');
    }
}

// Export to Global
window.downloadPdf = downloadPdf;

// Export to Global
window.login = login;
window.logout = logout;
window.initDashboard = initDashboard;
window.toggleNotifications = toggleNotifications;
window.handleApplyLeave = handleApplyLeave;
window.actionSubstitution = actionSubstitution;
window.approveLeave = approveLeave;
window.updateLeaveDuration = updateLeaveDuration;
window.toggleHour = null; // Removed
window.toggleDurationMode = null; // Removed
window.showCreateUserModal = showCreateUserModal;
window.hideCreateUserModal = hideCreateUserModal;
window.handleCreateUser = handleCreateUser;
window.deleteUser = deleteUser;

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
}
window.toggleSidebar = toggleSidebar;

export {
    login,
    logout,
    initDashboard,
    toggleNotifications,
    handleApplyLeave,
    actionSubstitution,
    approveLeave,
    updateLeaveDuration,
    showCreateUserModal,
    hideCreateUserModal,
    handleCreateUser,
    deleteUser,
    downloadPdf,
    handleSignatureUpload,
    markAllRead
};

window.markAllRead = markAllRead;

// Init
if (window.location.pathname.includes('dashboard.html')) {
    window.handleSignatureUpload = handleSignatureUpload; // Ensure global access
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDashboard);
    else initDashboard();
}
