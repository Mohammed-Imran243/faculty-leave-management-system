<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outpass Module</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/alerts.js"></script>
    <script src="../assets/js/toast.js"></script>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
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

                <h3>Outpass Requests</h3>
                <div class="user-info">
                    <span id="user-name" style="font-weight:600">Faculty User</span>
                    <span id="user-role" class="status-badge" style="background:#e2e8f0; margin-left:8px">FACULTY</span>
                </div>
            </div>

            <!-- Content Area -->
            <div id="content-area">
                <div class="module-wrapper">
                    <div class="centered-card">
                        <h2>Request Outpass</h2>
                        <form action="#" method="POST">
                            <div class="form-group">
                                <label for="outpass_date">Date</label>
                                <input type="date" id="outpass_date" name="outpass_date" class="form-control" required>
                            </div>

                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 150px;">
                                    <label for="out_time">Out Time</label>
                                    <input type="time" id="out_time" name="out_time" class="form-control" required>
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px;">
                                    <label for="in_time">In Time (Expected/Actual)</label>
                                    <input type="time" id="in_time" name="in_time" class="form-control">
                                    <small style="color:var(--text-muted); display:block; margin-top:0.25rem;">Leave blank if not returning today.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="reason">Reason for Outpass</label>
                                <textarea id="reason" name="reason" class="form-control" rows="4" placeholder="State your reason and destination..." required></textarea>
                            </div>

                            <button type="submit" class="btn">Submit Outpass</button>
                        </form>
                    </div>

                    <!-- History Table Data -->
                    <div class="centered-card" style="max-width: 800px;">
                        <h2>My Outpasses</h2>
                        <div class="table-container">
                            <table id="outpassTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Exit Time</th>
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
        window.currentModule = 'outpass';
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

            // UX Validation checking if they put *something* in
            if(!data.out_time) {
                 showError("Please specify your Out Time.");
                 return;
            }

            try {
                const csrfToken = localStorage.getItem('csrf_token');
                const headers = {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                };
                if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

                const response = await fetch(`${API_URL}/outpasses.php/apply`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (response.ok && !result.error) {
                    showSuccess('Outpass requested successfully!');
                    form.reset();
                    loadOutpasses();
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

        async function loadOutpasses() {
            try {
                const response = await fetch(`${API_URL}/outpasses.php/my`, {
                    headers: { 'Authorization': `Bearer ${token}` }
                });
                const data = await response.json();
                
                const tbody = document.querySelector('#outpassTable tbody');
                tbody.innerHTML = '';
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No outpasses found.</td></tr>';
                    return;
                }
                
                data.forEach(o => {
                    let statusClass = 'status-pending';
                    let displayStatus = o.status;
                    if (o.status === 'Approved') statusClass = 'status-approved';
                    else if (o.status === 'Rejected') statusClass = 'status-rejected';
                    else if (o.status.includes('Pending')) {
                        statusClass = 'status-pending';
                        displayStatus = o.status.replace('_', ' ');
                    }
                    
                    let actionHtml = '-';
                    if (o.status === 'Approved') {
                        actionHtml = `<button class="btn" style="padding:0.4rem 0.8rem; font-size:0.875rem;" onclick="downloadPDF(${o.id})"><i class="fa fa-download"></i> PDF</button>`;
                    }
                    
                    const tr = document.createElement('tr');
                    const formattedExitTime = formatTimeTo12Hour(o.out_time.substring(0,5));
                    
                    tr.innerHTML = `
                        <td data-label="Date">${o.outpass_date}</td>
                        <td data-label="Exit Time">${formattedExitTime}</td>
                        <td data-label="Reason">${o.reason}</td>
                        <td data-label="Status"><span class="status-badge ${statusClass}">${displayStatus}</span></td>
                        <td data-label="Action">${actionHtml}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                console.error("Failed to load outpasses", err);
            }
        }

        window.downloadPDF = async function(id) {
            showLoading('Generating Outpass PDF...');
            try {
                const response = await fetch(`${API_URL}/generate_outpass_pdf.php?id=${id}`, {
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
                a.download = `Outpass_${id}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                closeLoading();
            } catch (err) {
                closeLoading();
                console.error(err);
                showError("Failed to download PDF.");
            }
        };

        // Init load
        loadOutpasses();
    </script>
</body>
</html>
