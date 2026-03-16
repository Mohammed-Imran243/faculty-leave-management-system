<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Module</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/vendor/font-awesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/alerts.js"></script>
    <script src="../assets/js/toast.js"></script>
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
        <!-- Sidebar -->
        <div class="sidebar" id="static-sidebar">
            <div class="brand">CAHCET Faculty</div>
            <!-- Navigation Links -->
            <div id="sidebar-menu"></div>
            <div style="flex:1"></div>
            <button class="logout-btn" onclick="window.location.href='index.html'">Sign Out</button>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div style="display:flex; align-items:center">
                    <button class="mobile-menu-btn" onclick="document.getElementById('static-sidebar').classList.toggle('active')">
                        <i class="fa fa-bars"></i>
                    </button>
                    <div id="notification-wrapper" style="position:relative">
                        <span class="notification-bell text-gray-600" style="margin-right:15px; position:relative;" title="Pending Actionable Requests" onclick="handleAlertClick()">
                            <i class="fa fa-inbox" style="color: #ef4444;"></i><span id="alerts-badge" class="notification-badge" style="display:none; background:#ef4444;">0</span>
                        </span>
                        <span class="notification-bell text-gray-600" onclick="toggleNotifications()"><i class="fa fa-bell" style="color: #fbbf24;"></i><span id="notif-badge" class="notification-badge">0</span></span>
                        <div id="notif-dropdown" class="notification-dropdown"></div>
                    </div>
                </div>

                <h3>Permission Requests</h3>
                <div class="user-info">
                    <span id="user-name" style="font-weight:600">Faculty User</span>
                    <span id="user-role" class="status-badge" style="background:#e2e8f0; margin-left:8px">FACULTY</span>
                </div>
            </div>

            <!-- Content Area -->
            <div id="content-area">
                <div class="module-wrapper">
                    <div class="centered-card">
                        <h2>Apply for Permission</h2>
                        <form action="#" method="POST">
                            <div class="form-group">
                                <label for="permission_date">Date</label>
                                <input type="date" id="permission_date" name="permission_date" class="form-control" required>
                            </div>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 150px;">
                                    <label for="start_time">Start Time</label>
                                    <input type="time" id="start_time" name="start_time" class="form-control" required>
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px;">
                                    <label for="end_time">End Time</label>
                                    <input type="time" id="end_time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="reason">Reason (Max 250 characters)</label>
                                <textarea id="reason" name="reason" class="form-control" rows="3" maxlength="250" placeholder="Briefly specify the reason..." required></textarea>
                            </div>
                            
                            <button type="submit" class="btn">Submit Request</button>
                        </form>
                    </div>

                    <!-- History Table Data -->
                    <div class="centered-card" style="max-width: 800px;">
                        <h2>My Permission History</h2>
                        <div class="table-container">
                            <table id="permissionsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="5" style="text-align:center;">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Validation -->
    <script type="text/javascript">
        window.isModulePage = true;
        window.currentModule = 'permission';
    </script>
    <script type="module">
        import { initDashboard, toggleNotifications, handleAlertClick, toggleSidebar } from '../assets/js/app.js?v=1.2';
        
        // Export toggle functions to global scope for HTML clicks
        window.toggleNotifications = toggleNotifications;
        window.handleAlertClick = handleAlertClick;
        window.toggleSidebar = toggleSidebar;
        
        // Initialize layout and notifications
        initDashboard();
        
        // Use the same dynamic URL logic from ../assets/js/app.js
        const getBaseUrl = () => {
            const path = window.location.pathname;
            if (window.location.port === '5500' || window.location.port === '5501') {
                const clientIndex = path.indexOf('/client/');
                if (clientIndex !== -1) {
                    const backendRoot = path.substring(0, clientIndex);
                    return 'http://localhost' + backendRoot + '/server/api';
                }
            }
            const clientIndex = path.indexOf('/client/');
            if (clientIndex !== -1) {
                return window.location.origin + path.substring(0, clientIndex) + '/server/api';
            }
            return '../server/api';
        };

        const API_URL = getBaseUrl();
        const token = localStorage.getItem('token');
        const user = JSON.parse(localStorage.getItem('user'));

        if (!token) {
            window.location.href = 'index.html';
        } else {
            document.getElementById('user-name').textContent = user.name;
            document.getElementById('user-role').textContent = user.role.toUpperCase();
        }

        const form = document.querySelector('form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // EXACT TIME VALIDATION (UX LEVEL)
            const startStr = data.start_time;
            const endStr = data.end_time;
            
            let isValid = false;
            // Strict match check
            if ((startStr === '09:30' && endStr === '10:30') || (startStr === '16:30' && endStr === '17:30')) {
                isValid = true;
            }

            if (!isValid && user.role !== 'admin') {
                showError("Permission is strictly allowed only for 09:30 AM - 10:30 AM OR 04:30 PM - 05:30 PM.");
                return;
            }

            try {
                const csrfToken = localStorage.getItem('csrf_token');
                const headers = {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                };
                if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

                const response = await fetch(`${API_URL}/permissions.php/apply`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (response.ok && !result.error) {
                    showSuccess('Permission requested successfully!');
                    form.reset();
                    loadPermissions();
                } else {
                    showError(result.error || 'Server error occurred.');
                }
            } catch (err) {
                console.error(err);
                showError("Failed to connect to the server.");
            }
        });

        // Load History
        function formatTimeTo12Hour(timeString) {
            if (!timeString) return '';
            // Expects "HH:MM" or "HH:MM:SS"
            let [hours, minutes] = timeString.split(':');
            hours = parseInt(hours, 10);
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            const strHours = hours < 10 ? '0' + hours : hours;
            return `${strHours}:${minutes} ${ampm}`;
        }

        async function loadPermissions() {
            try {
                const response = await fetch(`${API_URL}/permissions.php/my`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await response.json();
                
                const tbody = document.querySelector('#permissionsTable tbody');
                tbody.innerHTML = '';
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No permissions found.</td></tr>';
                    return;
                }
                
                data.forEach(p => {
                    let statusClass = 'status-pending';
                    let displayStatus = p.status;
                    if (p.status === 'Approved') statusClass = 'status-approved';
                    else if (p.status === 'Rejected') statusClass = 'status-rejected';
                    else if (p.status.includes('Pending')) {
                        statusClass = 'status-pending';
                        displayStatus = p.status.replace('_', ' ');
                    }
                    
                    let actionHtml = '-';
                    if (p.status === 'Approved') {
                        // Download PDF Button
                        const downloadUrl = `${API_URL}/generate_permission_pdf.php?id=${p.id}`;
                        // We must fetch it as blob to pass the Authorization header, OR just use window.location if token isn't strictly needed in headers
                        // Since generate_permission_pdf uses strict JWT bearer, we must fetch as blob and download.
                        actionHtml = `<button class="btn" style="padding:0.4rem 0.8rem; font-size:0.875rem;" onclick="downloadPDF(${p.id})"><i class="fa fa-download"></i> PDF</button>`;
                    }
                    
                    const tr = document.createElement('tr');
                    const formattedStartTime = formatTimeTo12Hour(p.start_time.substring(0,5));
                    const formattedEndTime = formatTimeTo12Hour(p.end_time.substring(0,5));
                    
                    tr.innerHTML = `
                        <td data-label="Date">${p.permission_date}</td>
                        <td data-label="Time">${formattedStartTime} - ${formattedEndTime}</td>
                        <td data-label="Reason">${p.reason}</td>
                        <td data-label="Status"><span class="status-badge ${statusClass}">${displayStatus}</span></td>
                        <td data-label="Action">${actionHtml}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                console.error("Failed to load permissions", err);
            }
        }

        window.downloadPDF = async function(id) {
            showLoading('Generating Permission PDF...');
            try {
                const response = await fetch(`${API_URL}/generate_permission_pdf.php?id=${id}`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                if (!response.ok) {
                    closeLoading();
                    const err = await response.json();
                    showError("Error: " + (err.error || "Could not download PDF"));
                    return;
                }
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `Permission_${id}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                closeLoading();
            } catch(e) {
                closeLoading();
                console.error("Error downloading PDF", e);
                showError("Failed to download PDF");
            }
        };

        // Init load
        loadPermissions();
    </script>
</body>
</html>
