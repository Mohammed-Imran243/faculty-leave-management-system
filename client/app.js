const API_URL = '../server/api';

// --- State Management ---
const state = {
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
    notifications: []
};

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
        // alert('Connection Error. Make sure XAMPP is running and URL is correct.');
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

    dropdown.innerHTML = '';
    if (state.notifications.length === 0) {
        dropdown.innerHTML = '<div class="notification-item">No notifications</div>';
        return;
    }

    state.notifications.forEach(n => {
        const div = document.createElement('div');
        div.className = `notification-item ${n.is_read ? '' : 'unread'}`;
        div.textContent = n.message;
        div.onclick = () => markNotificationRead(n.id);
        dropdown.appendChild(div);
    });
}

function toggleNotifications() {
    const d = document.getElementById('notif-dropdown');
    d.style.display = d.style.display === 'block' ? 'none' : 'block';
}

async function markNotificationRead(id) {
    await apiCall(`/notifications.php/${id}/read`, 'PUT');
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
        // Fetch eligible substitutes (Faculty/HoD)
        const facultyList = await apiCall('/users.php/faculty');

        // Filter out self
        const substitutes = facultyList ? facultyList.filter(u => u.id != state.user.id) : [];

        let subOptions = substitutes.map(u => `<option value="${u.id}">${u.name} (${u.department || ''})</option>`).join('');

        container.innerHTML = `
            <div class="glass-card" style="max-width: 600px; margin: 0 auto;">
                <h2>Apply for Leave</h2>
                <form onsubmit="handleApplyLeave(event)">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <select class="form-control" name="leave_type" required size="1">
                            <option value="" disabled selected>-- Select Leave Type --</option>
                            <option value="Sick">Sick Leave</option>
                            <option value="Casual">Casual Leave</option>
                            <option value="Academic">Academic Leave</option>
                            <option value="OD">On Duty (OD)</option>
                            <option value="ED">External Duty (ED)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Duration</label>
                        <div style="display:flex; gap:20px; margin-bottom:10px;">
                            <label><input type="radio" name="duration_type" value="Days" checked onchange="toggleDurationMode()"> Full Day(s)</label>
                            <label><input type="radio" name="duration_type" value="Hours" onchange="toggleDurationMode()"> Hourly</label>
                        </div>
                    </div>

                    <div id="days-input-group" class="form-group" style="display:flex; gap:10px">
                        <div style="flex:1"><label>Start Date</label><input type="date" class="form-control" name="start_date"></div>
                        <div style="flex:1"><label>End Date</label><input type="date" class="form-control" name="end_date"></div>
                    </div>

                    <div id="hours-input-group" class="form-group" style="display:none;">
                        <label>Date</label>
                        <input type="date" class="form-control" name="hourly_date" style="margin-bottom:15px">
                        
                        <label>Select Hours (Period 1-8)</label>
                        <div class="hours-grid-container">
                            <input type="hidden" name="selected_hours" id="selected-hours-input">
                            <div class="hours-grid">
                                <div class="hour-cell" onclick="toggleHour(this, 1)">1</div>
                                <div class="hour-cell" onclick="toggleHour(this, 2)">2</div>
                                <div class="hour-cell" onclick="toggleHour(this, 3)">3</div>
                                <div class="hour-cell" onclick="toggleHour(this, 4)">4</div>
                                <div class="hour-cell" onclick="toggleHour(this, 5)">5</div>
                                <div class="hour-cell" onclick="toggleHour(this, 6)">6</div>
                                <div class="hour-cell" onclick="toggleHour(this, 7)">7</div>
                                <div class="hour-cell" onclick="toggleHour(this, 8)">8</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Substitute Faculty</label>
                        <select class="form-control" name="substitute_id" required>
                            <option value="">Select Substitute...</option>
                            ${subOptions}
                        </select>
                        <small>They must accept before your leave is processed.</small>
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
            <thead><tr><th>Requester</th><th>Type</th><th>Date(s)</th><th>Reason</th><th>Action</th></tr></thead><tbody>`;

        if (reqs && reqs.length > 0) {
            reqs.forEach(r => {
                let time = r.start_date;
                if (r.start_date !== r.end_date) time += ` to ${r.end_date}`;

                html += `<tr>
                    <td data-label="Requester">${escapeHtml(r.requester_name)}</td>
                    <td data-label="Type">${escapeHtml(r.leave_type)}</td>
                    <td data-label="Date(s)">${escapeHtml(time)}</td>
                    <td data-label="Reason">${escapeHtml(r.reason)}</td>
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

    // Validation
    if (data.duration_type === 'Hours') {
        if (!data.hourly_date) { alert("Please select a date."); return; }
        if (!data.selected_hours) { alert("Please select at least one hour."); return; }
        data.start_date = data.hourly_date;
        data.end_date = data.hourly_date;
    } else {
        if (!data.start_date || !data.end_date) { alert("Please select dates."); return; }
    }

    // Format Substitutions
    if (data.substitute_id) {
        data.substitutions = [
            { substitute_id: data.substitute_id, hour: 0 }
        ];
    } else {
        alert("Please select a substitute.");
        return;
    }

    const res = await apiCall('/leaves.php/apply', 'POST', data);
    if (!res.error) {
        alert('Leave Applied! Waiting for Substitute Acceptance.');
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
function toggleDurationMode() {
    const isHourly = document.querySelector('input[name="duration_type"][value="Hours"]').checked;
    document.getElementById('days-input-group').style.display = isHourly ? 'none' : 'flex';
    document.getElementById('hours-input-group').style.display = isHourly ? 'block' : 'none';
}

let selectedHours = new Set();
function toggleHour(element, hour) {
    if (selectedHours.has(hour)) {
        selectedHours.delete(hour);
        element.classList.remove('selected');
    } else {
        selectedHours.add(hour);
        element.classList.add('selected');
    }
    document.getElementById('selected-hours-input').value = Array.from(selectedHours).sort().join(',');
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
            } catch (_) {}
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
window.toggleDurationMode = toggleDurationMode;
window.toggleHour = toggleHour;
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
    toggleDurationMode,
    toggleHour,
    showCreateUserModal,
    hideCreateUserModal,
    handleCreateUser,
    deleteUser,
    downloadPdf,
    handleSignatureUpload
};

// Init
if (window.location.pathname.includes('dashboard.html')) {
    window.handleSignatureUpload = handleSignatureUpload; // Ensure global access
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDashboard);
    else initDashboard();
}
