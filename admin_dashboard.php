<?php
require_once __DIR__ . '/header.php';

// Prevent access if not logged in as Admin
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['flash_error'] = "Access denied. Please log in first.";
    header('Location: admin_login.php');
    exit;
}

$role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'super_admin';

// Initialize stats and data to default values
$total_teachers = 0;
$total_participants = 0;
$pending_teachers = 0;
$approved_teachers = 0;
$rejected_teachers = 0;
$settings = [];
$is_reg_open = false;
$upi_id = 'teensministry@upi';
$upi_payee = 'Jesus Youth Teens Kerala';
$participants = [];
$teachers = [];
$i_grand_total = 0;
$posters = [];
$videos = [];
$admins_list = [];

try {
    // Media & video elements are fetched for both roles
    $stmt = $pdo->query("SELECT * FROM posters ORDER BY sort_order ASC, created_at DESC");
    $posters = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC");
    $videos = $stmt->fetchAll();

    if ($role === 'super_admin') {
        // Fetch Admin Stats
        $stmt = $pdo->query("SELECT COUNT(*) FROM teachers");
        $total_teachers = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM participants");
        $total_participants = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE payment_status = 'Pending'");
        $pending_teachers = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE payment_status = 'Approved'");
        $approved_teachers = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM teachers WHERE payment_status = 'Rejected'");
        $rejected_teachers = $stmt->fetchColumn();

        // Fetch Settings
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings_rows = $stmt->fetchAll();
        foreach ($settings_rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $is_reg_open = (isset($settings['registration_open']) && $settings['registration_open'] === '1');
        $upi_id = isset($settings['upi_id']) ? $settings['upi_id'] : 'teensministry@upi';
        $upi_payee = isset($settings['upi_payee_name']) ? $settings['upi_payee_name'] : 'Jesus Youth Teens Kerala';

        // Fetch Participants list with Teacher context
        $stmt = $pdo->query("
            SELECT p.*, t.name as teacher_name, t.tr_code 
            FROM participants p 
            JOIN teachers t ON p.tr_id = t.id 
            ORDER BY p.created_at DESC
        ");
        $participants = $stmt->fetchAll();

        // Fetch Teachers list with Participant counts
        $stmt = $pdo->query("
            SELECT t.*, (SELECT COUNT(*) FROM participants WHERE tr_id = t.id) as student_count 
            FROM teachers t 
            ORDER BY t.created_at DESC
        ");
        $teachers = $stmt->fetchAll();

        // Fetch Intercession Totals
        $intercessions_stmt = $pdo->query("SELECT 
            COALESCE(SUM(holy_mass), 0) as holy_mass,
            COALESCE(SUM(our_father), 0) as our_father,
            COALESCE(SUM(hail_mary), 0) as hail_mary,
            COALESCE(SUM(memorare), 0) as memorare,
            COALESCE(SUM(creed), 0) as creed,
            COALESCE(SUM(divine_mercy), 0) as divine_mercy,
            COALESCE(SUM(rosary), 0) as rosary,
            COALESCE(SUM(adoration), 0) as adoration
            FROM intercessions");
        $i_totals = $intercessions_stmt->fetch();
        $i_grand_total = $i_totals['holy_mass'] + $i_totals['our_father'] + $i_totals['hail_mary'] + $i_totals['memorare'] + $i_totals['creed'] + $i_totals['divine_mercy'] + $i_totals['rosary'];

        // Fetch Admins List
        $stmt = $pdo->query("SELECT id, username, role, created_at FROM admins ORDER BY username ASC");
        $admins_list = $stmt->fetchAll();
    }
} catch (Exception $e) {
    die("Database query error: " . $e->getMessage());
}
?>

<div class="portal-container">
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-top">
            <div style="font-family: 'Groovezilla', sans-serif; font-size: 1.5rem; color: var(--gold); margin-bottom: 2rem; text-align: center;">
                ADMIN SYSTEM
            </div>
            
            <ul class="sidebar-menu">
                <?php if ($role === 'super_admin'): ?>
                    <li><a href="#" class="sidebar-link active" id="sideBtnOverview"><i class="fa-solid fa-chart-line"></i> Dashboard</a></li>
                    <li><a href="#" class="sidebar-link" id="sideBtnParticipants"><i class="fa-solid fa-users"></i> Participants (<?= $total_participants ?>)</a></li>
                    <li><a href="#" class="sidebar-link" id="sideBtnTeachers"><i class="fa-solid fa-chalkboard-user"></i> Teachers (<?= $total_teachers ?>)</a></li>
                    <li><a href="#" class="sidebar-link" id="sideBtnIntercessions"><i class="fa-solid fa-heart"></i> Intercessions (<?= number_format($i_grand_total) ?>)</a></li>
                    <li><a href="#" class="sidebar-link" id="sideBtnMedia"><i class="fa-solid fa-images"></i> Media Management</a></li>
                    <li><a href="#" class="sidebar-link" id="sideBtnSettings"><i class="fa-solid fa-sliders"></i> Settings</a></li>
                    <li><a href="#" class="sidebar-link" id="sideBtnUsers"><i class="fa-solid fa-user-gear"></i> User Management</a></li>
                <?php else: ?>
                    <li><a href="#" class="sidebar-link active" id="sideBtnMedia"><i class="fa-solid fa-images"></i> Media Management</a></li>
                <?php endif; ?>
                <li><a href="index.php" class="sidebar-link"><i class="fa-solid fa-house"></i> View Website</a></li>
            </ul>
        </div>
        
        <div class="sidebar-user">
            <div class="sidebar-user-avatar" style="background: var(--primary-light); color: var(--white);">
                <?= strtoupper(substr($_SESSION['admin_username'], 0, 2)) ?>
            </div>
            <div class="sidebar-user-info">
                <h5><?= htmlspecialchars($_SESSION['admin_username']) ?></h5>
                <p><?= $role === 'super_admin' ? 'Super Admin' : 'Media Admin' ?></p>
            </div>
            <a href="logout.php" style="color: var(--danger); margin-left: auto;" title="Sign Out"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="portal-content">
        
        <!-- Mobile Tab Navigation Selector -->
        <div class="mobile-portal-nav">
            <label for="mobileNavSelect" style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Navigation Menu</label>
            <select id="mobileNavSelect" onchange="if(this.value==='Website'){window.location.href='index.php';}else{activateTab(this.value);}" class="form-control mobile-nav-select" style="cursor: pointer; background-color: rgba(30, 27, 41, 0.95); border-color: rgba(255,255,255,0.15);">
                <?php if ($role === 'super_admin'): ?>
                    <option value="Overview">Dashboard</option>
                    <option value="Participants">Participants (<?= $total_participants ?>)</option>
                    <option value="Teachers">Teachers (<?= $total_teachers ?>)</option>
                    <option value="Intercessions">Intercessions (<?= number_format($i_grand_total) ?>)</option>
                    <option value="Media">Media Management</option>
                    <option value="Settings">Settings</option>
                    <option value="Users">User Management</option>
                <?php else: ?>
                    <option value="Media">Media Management</option>
                <?php endif; ?>
                <option value="Website">View Website</option>
            </select>
        </div>

        <!-- Dashboard Header -->
        <div class="portal-header">
            <?php if ($role === 'super_admin'): ?>
                <div class="portal-title-area">
                    <h2>Admin Control Center</h2>
                    <p>Review metrics, verify teacher fee screenshots, and toggle registration portals.</p>
                </div>
                
                <div class="switch-wrapper">
                    <label for="regToggleSwitch">Registration Portal</label>
                    <label class="switch">
                        <input type="checkbox" id="regToggleSwitch" <?= $is_reg_open ? 'checked' : '' ?> onchange="toggleRegistrations(this)">
                        <span class="slider"></span>
                    </label>
                </div>
            <?php else: ?>
                <div class="portal-title-area">
                    <h2>Media Management Panel</h2>
                    <p>Upload and organize faith-inspired event posters and video links.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ----------------------------------------------------
             SECTION 1: OVERVIEW METRICS
             ---------------------------------------------------- -->
        <div id="sectionOverview">
            <div class="stats-grid">
                <!-- Stat 1: Total Participants -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Total Participants</h4>
                        <div class="stat-number"><?= $total_participants ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: var(--primary-light);"><i class="fa-solid fa-users"></i></div>
                </div>
                
                <!-- Stat 2: Total Teachers -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Total Teachers (TR)</h4>
                        <div class="stat-number"><?= $total_teachers ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--text-dark); background: var(--gold);"><i class="fa-solid fa-chalkboard-user"></i></div>
                </div>
                
                <!-- Stat 3: Approved -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Approved Teachers</h4>
                        <div class="stat-number" style="color: #4df37f;"><?= $approved_teachers ?></div>
                    </div>
                    <div class="stat-icon" style="color: #4df37f; background: rgba(40,167,69,0.1);"><i class="fa-solid fa-circle-check"></i></div>
                </div>
                
                <!-- Stat 4: Pending -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Pending Verification</h4>
                        <div class="stat-number" style="color: var(--warning);"><?= $pending_teachers ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--warning); background: rgba(255,193,7,0.1);"><i class="fa-solid fa-hourglass-half"></i></div>
                </div>
            </div>

            <!-- Quick Action Links -->
            <div class="dashboard-quick-actions">
                <div class="glass-card" style="padding: 2rem; border-left: 4px solid var(--gold);">
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--gold);"><i class="fa-solid fa-download"></i> Quick Data Exports</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">Download complete camp registrations in CSV / MS Excel compatible files.</p>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="admin_actions.php?export=participants" class="btn btn-primary btn-sm"><i class="fa-solid fa-users"></i> Export Participants CSV</a>
                        <a href="admin_actions.php?export=teachers" class="btn btn-outline btn-sm"><i class="fa-solid fa-chalkboard-user"></i> Export Teachers CSV</a>
                    </div>
                </div>
                
                <div class="glass-card" style="padding: 2rem; border-left: 4px solid var(--primary-light);">
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.5rem; color: var(--text-dark);"><i class="fa-solid fa-gears"></i> Gateway Information</h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">Payment QR targets: <strong><?= htmlspecialchars($upi_id) ?></strong> (<?= htmlspecialchars($upi_payee) ?>)</p>
                    <button onclick="activateTab('Settings')" class="btn btn-outline btn-sm" style="border-color: var(--primary-light); color: var(--primary-light);"><i class="fa-solid fa-pen-to-square"></i> Modify Details</button>
                </div>
            </div>
        </div>

        <!-- ----------------------------------------------------
             SECTION 2: PARTICIPANTS TAB
             ---------------------------------------------------- -->
        <div id="sectionParticipants" style="display: none;">
            <div class="portal-header" style="margin-bottom: 1.5rem;">
                <div class="portal-title-area">
                    <h3>Camp Registrations</h3>
                    <p>Search and filter students, download details, and verify reference codes.</p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="admin_actions.php?export=participants" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export List (CSV)</a>
                </div>
            </div>

            <!-- Table Filters -->
            <div class="table-filter-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="participantSearchInput" class="form-control" placeholder="Search by Student Name, Phone, Parish, TR Code, Zone..." onkeyup="filterParticipantsTable()">
                </div>
                
                <div class="filter-group">
                    <label for="participantStatusFilterSelect" style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Payment Status:</label>
                    <select id="participantStatusFilterSelect" class="filter-select" onchange="filterParticipantsTable()">
                        <option value="ALL">All Statuses</option>
                        <option value="PENDING">Pending</option>
                        <option value="APPROVED">Approved</option>
                        <option value="REJECTED">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="participantsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student Details</th>
                            <th>School</th>
                            <th>Guardian Details</th>
                            <th>Parish & Diocese</th>
                            <th>Zone</th>
                            <th>Class</th>
                            <th>Address</th>
                            <th>Supervisor</th>
                            <th>Payment Mode</th>
                            <th>Receipt</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($participants) > 0): ?>
                            <?php foreach ($participants as $p): ?>
                                <tr data-name="<?= strtolower($p['full_name']) ?>" 
                                    data-phone="<?= strtolower($p['phone']) ?>" 
                                    data-parish="<?= strtolower($p['parish']) ?>" 
                                    data-trcode="<?= strtolower($p['tr_code']) ?>"
                                    data-zone="<?= strtolower($p['zone']) ?>"
                                    data-status="<?= strtoupper($p['payment_status']) ?>">
                                    
                                    <td>#<?= sprintf("%04d", $p['id']) ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($p['full_name']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                            <span><?= htmlspecialchars($p['age']) ?> Yrs (<?= htmlspecialchars($p['gender']) ?>)</span>
                                            <?php if (!empty($p['email'])): ?>
                                                <span style="margin: 0 5px;">|</span>
                                                <span><?= htmlspecialchars($p['email']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><div style="font-weight: 600; color: var(--text-light);"><?= htmlspecialchars($p['school'] ?? '') ?></div></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-light);"><?= htmlspecialchars($p['guardian_name']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($p['phone']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($p['parish']) ?></td>
                                    <td><?= htmlspecialchars($p['zone']) ?></td>
                                    <td>Class <?= htmlspecialchars($p['class']) ?></td>
                                    <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($p['address']) ?>"><?= htmlspecialchars($p['address']) ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--gold);"><?= htmlspecialchars($p['tr_code']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($p['teacher_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge" style="border: 1px solid var(--gold); color: var(--gold); background: transparent; font-size: 0.8rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; display: inline-block;">
                                            <?= htmlspecialchars($p['payment_type'] ?? 'Full') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['payment_screenshot'])): ?>
                                            <button class="action-btn action-btn-info" onclick="openLightbox('uploads/<?= $p['payment_screenshot'] ?>', '<?= htmlspecialchars($p['full_name']) ?> - Receipt Proof')" title="View Payment Receipt">
                                                <i class="fa-solid fa-receipt"></i>
                                            </button>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-status-cell">
                                        <?php 
                                        $p_status = $p['payment_status'];
                                        if ($p_status === 'Approved') {
                                            echo '<span class="badge badge-approved"><i class="fa-solid fa-circle-check"></i> Approved</span>';
                                        } elseif ($p_status === 'Rejected') {
                                            echo '<span class="badge badge-rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected</span>';
                                        } else {
                                            echo '<span class="badge badge-pending"><i class="fa-solid fa-hourglass-half"></i> Pending</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <button class="action-btn action-btn-success" onclick="updateParticipantPaymentStatus(<?= $p['id'] ?>, 'Approved', this)" title="Approve Participant">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button class="action-btn action-btn-danger" onclick="updateParticipantPaymentStatus(<?= $p['id'] ?>, 'Rejected', this)" title="Reject Participant">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                            <button class="action-btn action-btn-danger" style="background-color: #dc3545; border-color: #dc3545; color: white;" onclick="deleteParticipant(<?= $p['id'] ?>, this)" title="Delete Participant">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" style="text-align: center; color: var(--text-muted); padding: 4rem 0;">
                                    <i class="fa-solid fa-users-slash" style="font-size: 3rem; color: rgba(0,0,0,0.05); margin-bottom: 1rem; display: block;"></i>
                                    No participants registered yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ----------------------------------------------------
             SECTION 3: TEACHERS TAB
             ---------------------------------------------------- -->
        <div id="sectionTeachers" style="display: none;">
            <div class="portal-header" style="margin-bottom: 1.5rem;">
                <div class="portal-title-area">
                    <h3>Teacher Supervisors (TR)</h3>
                    <p>Registered supervisors who coordinate student groups, showing payment screenshots and approvals.</p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="admin_actions.php?export=teachers" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export List (CSV)</a>
                </div>
            </div>

            <div class="table-filter-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="teacherSearchInput" class="form-control" placeholder="Search by Name, Phone, Parish, TR Code..." onkeyup="filterTeachersTable()">
                </div>
                
                <div class="filter-group">
                    <label for="teacherStatusFilterSelect" style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Payment Status:</label>
                    <select id="teacherStatusFilterSelect" class="filter-select" onchange="filterTeachersTable()">
                        <option value="ALL">All Statuses</option>
                        <option value="PENDING">Pending</option>
                        <option value="APPROVED">Approved</option>
                        <option value="REJECTED">Rejected</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="custom-table" id="teachersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Teacher Details</th>
                            <th>Contact / Address</th>
                            <th>School & Parish</th>
                            <th>Zone</th>
                            <th>Camp Experience</th>
                            <th>TR Code</th>
                            <th>Students</th>
                            <th>Payment Type</th>
                            <th>Receipt</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($teachers) > 0): ?>
                            <?php foreach ($teachers as $t): ?>
                                <tr data-name="<?= strtolower($t['name']) ?>" 
                                    data-phone="<?= strtolower($t['phone']) ?>" 
                                    data-parish="<?= strtolower($t['parish']) ?>" 
                                    data-trcode="<?= strtolower($t['tr_code']) ?>"
                                    data-status="<?= strtoupper($t['payment_status']) ?>">
                                    
                                    <td>#<?= sprintf("%03d", $t['id']) ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($t['name']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                            <span><?= htmlspecialchars($t['age']) ?> Yrs / <?= htmlspecialchars($t['gender']) ?> / Married: <?= htmlspecialchars($t['married'] ?? 'No') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($t['phone']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($t['email']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; font-style: italic; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($t['address']) ?>"><?= htmlspecialchars($t['address']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-light);"><?= htmlspecialchars($t['school']) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($t['parish']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($t['zone']) ?></td>
                                    <td style="text-align: center; font-weight: 600; color: <?= $t['prior_experience'] === 'Yes' ? 'var(--gold)' : 'var(--text-muted)' ?>;"><?= htmlspecialchars($t['prior_experience']) ?></td>
                                    <td style="font-weight: 800; color: var(--gold); letter-spacing: 1px;"><?= htmlspecialchars($t['tr_code']) ?></td>
                                    <td style="text-align: center; font-weight: 700;"><?= $t['student_count'] ?></td>
                                    <td>
                                        <span class="badge" style="border: 1px solid var(--gold); color: var(--gold); background: transparent; font-size: 0.8rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; display: inline-block;">
                                            <?= htmlspecialchars($t['payment_type'] ?? 'Full') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($t['payment_screenshot'])): ?>
                                            <button class="action-btn action-btn-info" onclick="openLightbox('uploads/<?= $t['payment_screenshot'] ?>', '<?= htmlspecialchars($t['name']) ?> - Receipt Proof')" title="View Payment Receipt">
                                                <i class="fa-solid fa-receipt"></i>
                                            </button>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.8rem;">No file</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tr-status-cell">
                                        <?php 
                                        $status = $t['payment_status'];
                                        if ($status === 'Approved') {
                                            echo '<span class="badge badge-approved"><i class="fa-solid fa-circle-check"></i> Approved</span>';
                                        } elseif ($status === 'Rejected') {
                                            echo '<span class="badge badge-rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected</span>';
                                        } else {
                                            echo '<span class="badge badge-pending"><i class="fa-solid fa-hourglass-half"></i> Pending</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-btn-group">
                                            <button class="action-btn action-btn-success" onclick="updateTeacherPaymentStatus(<?= $t['id'] ?>, 'Approved', this)" title="Approve Teacher">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button class="action-btn action-btn-danger" onclick="updateTeacherPaymentStatus(<?= $t['id'] ?>, 'Rejected', this)" title="Reject Teacher">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                            <button class="action-btn action-btn-danger" style="background-color: #dc3545; border-color: #dc3545; color: white;" onclick="deleteTeacher(<?= $t['id'] ?>, this)" title="Delete Teacher">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" style="text-align: center; color: var(--text-muted); padding: 4rem 0;">
                                    <i class="fa-solid fa-chalkboard-user" style="font-size: 3rem; color: rgba(255,255,255,0.08); margin-bottom: 1rem; display: block;"></i>
                                    No teachers registered yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ----------------------------------------------------
             SECTION 3.5: INTERCESSIONS TAB
             ---------------------------------------------------- -->
        <div id="sectionIntercessions" style="display: none;">
            <div class="portal-header" style="margin-bottom: 1.5rem;">
                <div class="portal-title-area">
                    <h3>Spiritual Bouquets & Intercessions</h3>
                    <p>Live aggregates of anonymous offerings submitted by users on the platform.</p>
                </div>
            </div>

            <!-- Dashboard Grid for Intercessions -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card" style="background: linear-gradient(135deg, rgba(138, 70, 255, 0.1) 0%, rgba(212, 175, 55, 0.05) 100%); border: 1px solid rgba(212, 175, 55, 0.25);">
                    <div class="stat-info">
                        <h4>Grand Total Prayers Offered</h4>
                        <div class="stat-number" style="color: var(--gold); font-size: 2.2rem; font-weight: 800;"><?= number_format($i_grand_total) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--gold); background: rgba(212, 175, 55, 0.15);"><i class="fa-solid fa-heart"></i></div>
                </div>
            </div>

            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <!-- Holy Mass -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Holy Mass</h4>
                        <div class="stat-number"><?= number_format($i_totals['holy_mass']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-solid fa-church"></i></div>
                </div>

                <!-- Our Father -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Our Father</h4>
                        <div class="stat-number"><?= number_format($i_totals['our_father']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-solid fa-hands-praying"></i></div>
                </div>

                <!-- Hail Mary -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Hail Mary</h4>
                        <div class="stat-number"><?= number_format($i_totals['hail_mary']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-solid fa-person-praying"></i></div>
                </div>

                <!-- Memorare -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Memorare</h4>
                        <div class="stat-number"><?= number_format($i_totals['memorare']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-solid fa-shield-heart"></i></div>
                </div>

                <!-- Creed -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Creed</h4>
                        <div class="stat-number"><?= number_format($i_totals['creed']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-solid fa-book-open"></i></div>
                </div>

                <!-- Divine Mercy Rosary -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Divine Mercy Rosary</h4>
                        <div class="stat-number"><?= number_format($i_totals['divine_mercy']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-solid fa-heart-pulse"></i></div>
                </div>

                <!-- Rosary -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Rosary</h4>
                        <div class="stat-number"><?= number_format($i_totals['rosary']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-solid fa-circle-nodes"></i></div>
                </div>

                <!-- Adoration Minutes -->
                <div class="stat-card">
                    <div class="stat-info">
                        <h4>Adoration Minutes</h4>
                        <div class="stat-number"><?= number_format($i_totals['adoration']) ?></div>
                    </div>
                    <div class="stat-icon" style="color: var(--white); background: rgba(138, 70, 255, 0.15);"><i class="fa-regular fa-clock"></i></div>
                </div>
            </div>
        </div>

        <!-- ----------------------------------------------------
             SECTION 4: SETTINGS TAB
             ---------------------------------------------------- -->
        <div id="sectionSettings" style="display: none;">
            <div class="portal-header" style="margin-bottom: 1.5rem;">
                <div class="portal-title-area">
                    <h3>Portal Settings</h3>
                    <p>Update payment gateway configurations and control camp registrations.</p>
                </div>
            </div>

            <div class="glass-form" style="max-width: 600px; margin: 0;">
                <form method="POST" action="admin_actions.php" onsubmit="showLoading('Updating configuration...')">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-group">
                        <label for="upi_id">Teens Ministry UPI ID</label>
                        <input type="text" id="upi_id" name="upi_id" class="form-control" value="<?= htmlspecialchars($upi_id) ?>" required>
                        <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">UPI ID linked to the QR Code (e.g. teensministry@upi).</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="upi_payee_name">UPI Payee Display Name</label>
                        <input type="text" id="upi_payee_name" name="upi_payee_name" class="form-control" value="<?= htmlspecialchars($upi_payee) ?>" required>
                        <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Account holder name shown in UPI apps on scan.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1.5rem;"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
                </form>
            </div>
        </div>

        <!-- ----------------------------------------------------
             SECTION 5: MEDIA MANAGEMENT TAB
             ---------------------------------------------------- -->
        <div id="sectionMedia" style="display: none;">
            <div class="portal-header" style="margin-bottom: 1.5rem;">
                <div class="portal-title-area">
                    <h3>Media Gallery Management</h3>
                    <p>Upload and arrange posters, or add links to YouTube and Instagram Reels videos.</p>
                </div>
            </div>

            <!-- Mini tab switch -->
            <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
                <button class="btn btn-gold" id="btnMediaPosters" onclick="switchMediaTab('Posters')"><i class="fa-solid fa-image"></i> Posters</button>
                <button class="btn btn-primary" id="btnMediaVideos" onclick="switchMediaTab('Videos')" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: var(--white);"><i class="fa-solid fa-video"></i> Videos</button>
            </div>

            <!-- Posters Management Sub-Tab -->
            <div id="mediaSubPosters">
                <div class="media-layout-grid">
                    <!-- Add Poster Card -->
                    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
                        <h4 style="color: var(--gold); margin-bottom: 1.25rem;"><i class="fa-solid fa-plus"></i> Add New Poster</h4>
                        <form method="POST" action="admin_actions.php" enctype="multipart/form-data" onsubmit="showLoading('Uploading poster...')">
                            <input type="hidden" name="action" value="add_poster">
                            
                            <div class="form-group">
                                <label for="poster_title">Poster Title</label>
                                <input type="text" id="poster_title" name="title" class="form-control" placeholder="e.g. Step One Camp Poster" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Upload Poster Image</label>
                                <div class="file-upload-wrapper">
                                    <div class="file-upload-box" id="posterUploadBox">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                        <div class="file-upload-text">Drag & drop or click</div>
                                        <div class="file-upload-hint">JPG, PNG, WEBP (Max 5MB)</div>
                                        <input type="file" name="poster" class="file-upload-input" accept="image/jpeg,image/jpg,image/png,image/webp" required onchange="handlePosterSelect(this)">
                                    </div>
                                    <div class="selected-file-display" id="posterFileDisplay"></div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem;"><i class="fa-solid fa-upload"></i> Upload Poster</button>
                        </form>
                    </div>

                    <!-- Posters List & Reordering -->
                    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
                        <h4 style="color: var(--gold); margin-bottom: 0.5rem;"><i class="fa-solid fa-sort"></i> Manage & Reorder Posters</h4>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Drag and drop posters to reorder their layout in the public gallery.</p>
                        
                        <?php if (empty($posters)): ?>
                            <div style="text-align: center; padding: 3rem 0; color: var(--text-muted);">
                                <i class="fa-regular fa-image" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No posters uploaded yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="posters-drag-list" id="postersDragList" style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php foreach ($posters as $p): ?>
                                    <div class="drag-item" draggable="true" data-id="<?= $p['id'] ?>" style="display: flex; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 0.75rem 1rem; border-radius: 10px; cursor: grab; transition: var(--transition);">
                                        <div style="color: var(--text-muted); margin-right: 1rem; cursor: grab;"><i class="fa-solid fa-grip-vertical"></i></div>
                                        <img src="uploads/posters/<?= htmlspecialchars($p['filename']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; margin-right: 1rem; border: 1px solid rgba(255,255,255,0.1);">
                                        <div style="flex-grow: 1;">
                                            <h5 style="margin: 0; color: var(--white); font-size: 0.95rem;"><?= htmlspecialchars($p['title']) ?></h5>
                                            <small style="color: var(--text-muted); font-size: 0.75rem;">Uploaded: <?= date("M d, Y", strtotime($p['created_at'])) ?></small>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn-action btn-action-edit" onclick="openEditPosterModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>')" title="Edit Title"><i class="fa-solid fa-pencil"></i></button>
                                            <button class="btn-action btn-action-reject" onclick="deletePoster(<?= $p['id'] ?>)" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Videos Management Sub-Tab -->
            <div id="mediaSubVideos" style="display: none;">
                <div class="media-layout-grid">
                    <!-- Add Video Card -->
                    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
                        <h4 style="color: var(--gold); margin-bottom: 1.25rem;"><i class="fa-solid fa-plus"></i> Add New Video Link</h4>
                        <form method="POST" action="admin_actions.php" onsubmit="showLoading('Adding video link...')">
                            <input type="hidden" name="action" value="add_video">
                            
                            <div class="form-group">
                                <label for="video_title">Video Title</label>
                                <input type="text" id="video_title" name="title" class="form-control" placeholder="e.g. Camp Highlights Day 1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="video_url">YouTube or Instagram Reel URL</label>
                                <input type="url" id="video_url" name="url" class="form-control" placeholder="https://www.youtube.com/watch?v=... or https://instagram.com/reel/..." required>
                                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Supports YouTube Video, Shorts, and Instagram Reels.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem;"><i class="fa-solid fa-link"></i> Add Video</button>
                        </form>
                    </div>

                    <!-- Videos List Table -->
                    <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
                        <h4 style="color: var(--gold); margin-bottom: 1.25rem;"><i class="fa-solid fa-video"></i> Manage Video Links</h4>
                        
                        <?php if (empty($videos)): ?>
                            <div style="text-align: center; padding: 3rem 0; color: var(--text-muted);">
                                <i class="fa-solid fa-video-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No video links added yet.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="portal-table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Platform</th>
                                            <th>URL</th>
                                            <th style="text-align: right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($videos as $v): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--white);"><?= htmlspecialchars($v['title']) ?></td>
                                                <td>
                                                    <?php if ($v['platform'] === 'youtube'): ?>
                                                        <span class="badge badge-approved" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;"><i class="fa-brands fa-youtube"></i> YouTube</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-approved" style="background: rgba(236, 72, 153, 0.15); color: #ec4899;"><i class="fa-brands fa-instagram"></i> Instagram</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="font-size: 0.85rem;"><a href="<?= htmlspecialchars($v['url']) ?>" target="_blank" style="color: var(--gold); text-decoration: none;"><?= htmlspecialchars(substr($v['url'], 0, 45)) . (strlen($v['url']) > 45 ? '...' : '') ?></a></td>
                                                <td style="text-align: right;">
                                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                                        <button class="btn-action btn-action-edit" onclick="openEditVideoModal(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['title'])) ?>', '<?= htmlspecialchars(addslashes($v['url'])) ?>')" title="Edit"><i class="fa-solid fa-pencil"></i></button>
                                                        <button class="btn-action btn-action-reject" onclick="deleteVideo(<?= $v['id'] ?>)" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ----------------------------------------------------
             SECTION 6: USER MANAGEMENT TAB (Super Admin Only)
             ---------------------------------------------------- -->
        <?php if ($role === 'super_admin'): ?>
        <div id="sectionUsers" style="display: none;">
            <div class="portal-header" style="margin-bottom: 1.5rem;">
                <div class="portal-title-area">
                    <h3>User Accounts Management</h3>
                    <p>Create and manage system administrator accounts.</p>
                </div>
            </div>

            <div class="grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- Add User Card -->
                <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
                    <h4 style="color: var(--gold); margin-bottom: 1.25rem;"><i class="fa-solid fa-user-plus"></i> Add New Admin</h4>
                    <form method="POST" action="admin_actions.php" onsubmit="showLoading('Creating admin account...')">
                        <input type="hidden" name="action" value="add_admin">
                        
                        <div class="form-group">
                            <label for="admin_username">Username</label>
                            <input type="text" id="admin_username" name="username" class="form-control" required autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_password">Password</label>
                            <input type="password" id="admin_password" name="password" class="form-control" required autocomplete="new-password">
                        </div>

                        <div class="form-group">
                            <label for="admin_role">System Role</label>
                            <select id="admin_role" name="role" class="form-control" required>
                                <option value="super_admin">Super Administrator (Full Access)</option>
                                <option value="media_admin">Media Administrator (restricted access)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem;"><i class="fa-solid fa-save"></i> Create Admin</button>
                    </form>
                </div>

                <!-- Users List -->
                <div class="glass-card" style="padding: 1.5rem; border-radius: 16px;">
                    <h4 style="color: var(--gold); margin-bottom: 1.25rem;"><i class="fa-solid fa-users-gear"></i> Administrator Accounts</h4>
                    <div style="overflow-x: auto;">
                        <table class="portal-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins_list as $user): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--white);"><?= htmlspecialchars($user['username']) ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'super_admin'): ?>
                                                <span class="badge badge-approved" style="background: rgba(212, 175, 55, 0.15); color: var(--gold);"><i class="fa-solid fa-crown"></i> Super Admin</span>
                                            <?php else: ?>
                                                <span class="badge badge-approved" style="background: rgba(138, 70, 255, 0.15); color: var(--primary-light);"><i class="fa-solid fa-images"></i> Media Admin</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.85rem;"><?= date("M d, Y H:i", strtotime($user['created_at'])) ?></td>
                                        <td style="text-align: right;">
                                            <?php if ($user['id'] !== $_SESSION['admin_id']): ?>
                                                <button class="btn-action btn-action-reject" onclick="deleteAdmin(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['username'])) ?>')" title="Delete Account"><i class="fa-solid fa-trash"></i></button>
                                            <?php else: ?>
                                                <span style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">Current Session</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </main>

</div>

<!-- ----------------------------------------------------
     EDIT POSTER MODAL
     ---------------------------------------------------- -->
<div class="modal-overlay" id="editPosterModal" onclick="closeEditPosterModalOnBg(event)" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Edit Poster Title</h3>
            <button class="modal-close" onclick="closeEditPosterModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <form method="POST" action="admin_actions.php" enctype="multipart/form-data" onsubmit="showLoading('Updating poster...')">
                <input type="hidden" name="action" value="edit_poster">
                <input type="hidden" name="id" id="edit_poster_id">
                
                <div class="form-group">
                    <label for="edit_poster_title">Poster Title</label>
                    <input type="text" id="edit_poster_title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Replace Poster Image (Optional)</label>
                    <input type="file" name="poster" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp">
                    <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Leave blank to keep the current poster image.</small>
                </div>
                
                <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem;"><i class="fa-solid fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>

<!-- ----------------------------------------------------
     EDIT VIDEO MODAL
     ---------------------------------------------------- -->
<div class="modal-overlay" id="editVideoModal" onclick="closeEditVideoModalOnBg(event)" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Edit Video Link</h3>
            <button class="modal-close" onclick="closeEditVideoModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <form method="POST" action="admin_actions.php" onsubmit="showLoading('Updating video link...')">
                <input type="hidden" name="action" value="edit_video">
                <input type="hidden" name="id" id="edit_video_id">
                
                <div class="form-group">
                    <label for="edit_video_title">Video Title</label>
                    <input type="text" id="edit_video_title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_video_url">YouTube or Instagram Reel URL</label>
                    <input type="url" id="edit_video_url" name="url" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem;"><i class="fa-solid fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>

<!-- ----------------------------------------------------
     LIGHTBOX / PREVIEW MODAL
     ---------------------------------------------------- -->
<div class="modal-overlay" id="lightboxModal" onclick="closeLightboxOnBg(event)">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 id="lightboxTitle">Payment Screenshot</h3>
            <button class="modal-close" onclick="closeLightbox()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 1rem;">
            <div id="lightboxFileContainer"></div>
        </div>
    </div>
</div>

<script>
    // ----------------------------------------------------
    // Tab switching scripts
    // ----------------------------------------------------
    const sections = ['Overview', 'Participants', 'Teachers', 'Settings', 'Intercessions', 'Media', 'Users'];
    
    sections.forEach(sec => {
        const sideBtn = document.getElementById(`sideBtn${sec}`);
        if (sideBtn) {
            sideBtn.addEventListener('click', (e) => {
                e.preventDefault();
                activateTab(sec);
            });
        }
    });

    function activateTab(tabName) {
        sections.forEach(sec => {
            const el = document.getElementById(`section${sec}`);
            const btn = document.getElementById(`sideBtn${sec}`);
            if (el) el.style.display = (sec === tabName) ? 'block' : 'none';
            if (btn) {
                if (sec === tabName) btn.classList.add('active');
                else btn.classList.remove('active');
            }
        });
    }

    // Set default active tab based on role
    const currentRole = '<?= $role ?>';
    if (currentRole === 'media_admin') {
        activateTab('Media');
    } else {
        activateTab('Overview');
    }

    // ----------------------------------------------------
    // Toggle registration switch via AJAX
    // ----------------------------------------------------
    function toggleRegistrations(checkbox) {
        const status = checkbox.checked ? '1' : '0';
        
        showLoading("Updating portal status...");
        
        const formData = new FormData();
        formData.append('action', 'toggle_registration');
        formData.append('status', status);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'error');
                checkbox.checked = !checkbox.checked; // Revert checkbox state
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Network error occurred.", "error");
            checkbox.checked = !checkbox.checked; // Revert checkbox state
        });
    }

    // ----------------------------------------------------
    // Update teacher status (Approve/Reject) via AJAX
    // ----------------------------------------------------
    function updateTeacherPaymentStatus(id, status, buttonEl) {
        if (!confirm(`Are you sure you want to mark Teacher #${id} payment as ${status}?`)) return;
        
        showLoading(`Marking payment as ${status}...`);
        
        const formData = new FormData();
        formData.append('action', 'update_teacher_payment');
        formData.append('id', id);
        formData.append('status', status);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                
                const row = buttonEl.closest('tr');
                const statusCell = row.querySelector('.tr-status-cell');
                
                row.setAttribute('data-status', status.toUpperCase());
                
                if (status === 'Approved') {
                    statusCell.innerHTML = '<span class="badge badge-approved"><i class="fa-solid fa-circle-check"></i> Approved</span>';
                } else if (status === 'Rejected') {
                    statusCell.innerHTML = '<span class="badge badge-rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected</span>';
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Network error. Status not updated.", "error");
        });
    }

    // ----------------------------------------------------
    // Update participant status (Approve/Reject) via AJAX
    // ----------------------------------------------------
    function updateParticipantPaymentStatus(id, status, buttonEl) {
        if (!confirm(`Are you sure you want to mark Participant #${id} payment as ${status}?`)) return;
        
        showLoading(`Marking payment as ${status}...`);
        
        const formData = new FormData();
        formData.append('action', 'update_payment');
        formData.append('id', id);
        formData.append('status', status);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                
                const row = buttonEl.closest('tr');
                const statusCell = row.querySelector('.p-status-cell');
                
                row.setAttribute('data-status', status.toUpperCase());
                
                if (status === 'Approved') {
                    statusCell.innerHTML = '<span class="badge badge-approved"><i class="fa-solid fa-circle-check"></i> Approved</span>';
                } else if (status === 'Rejected') {
                    statusCell.innerHTML = '<span class="badge badge-rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected</span>';
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Network error. Status not updated.", "error");
        });
    }

    // ----------------------------------------------------
    // Delete participant via AJAX (after confirmation & password verification)
    // ----------------------------------------------------
    function deleteParticipant(id, buttonEl) {
        if (!confirm(`WARNING: Are you sure you want to permanently delete Participant #${id}? This action cannot be undone.`)) return;
        
        const adminPassword = prompt("SECURITY CHECK: Please enter your Admin Password to confirm deletion:");
        if (adminPassword === null) return;
        if (adminPassword.trim() === "") {
            showToast("Deletion cancelled. Password cannot be empty.", "error");
            return;
        }
        
        showLoading("Verifying password and deleting participant...");
        
        const formData = new FormData();
        formData.append('action', 'delete_participant');
        formData.append('id', id);
        formData.append('admin_password', adminPassword);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                const row = buttonEl.closest('tr');
                if (row) {
                    row.style.transition = 'all 0.5s ease';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                    }, 500);
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Network error. Participant not deleted.", "error");
        });
    }

    // ----------------------------------------------------
    // Delete teacher via AJAX (after confirmation & password verification)
    // ----------------------------------------------------
    function deleteTeacher(id, buttonEl) {
        if (!confirm(`WARNING: Deleting this teacher will also permanently delete all participants registered under their TR code.\n\nAre you sure you want to permanently delete Teacher #${id}? This action cannot be undone.`)) return;
        
        const adminPassword = prompt("SECURITY CHECK: Deletion cascades to participants. Please enter your Admin Password to confirm deletion:");
        if (adminPassword === null) return;
        if (adminPassword.trim() === "") {
            showToast("Deletion cancelled. Password cannot be empty.", "error");
            return;
        }
        
        showLoading("Verifying password and deleting teacher...");
        
        const formData = new FormData();
        formData.append('action', 'delete_teacher');
        formData.append('id', id);
        formData.append('admin_password', adminPassword);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                const row = buttonEl.closest('tr');
                if (row) {
                    row.style.transition = 'all 0.5s ease';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                    }, 500);
                }
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Network error. Teacher not deleted.", "error");
        });
    }

    // ----------------------------------------------------
    // Lightbox modal scripts (handles PNG/JPG/PDF receipt preview)
    // ----------------------------------------------------
    function openLightbox(filePath, title) {
        const modal = document.getElementById('lightboxModal');
        const modalTitle = document.getElementById('lightboxTitle');
        const fileContainer = document.getElementById('lightboxFileContainer');
        
        modalTitle.textContent = title;
        fileContainer.innerHTML = '';
        
        const extension = filePath.split('.').pop().toLowerCase();
        
        if (extension === 'pdf') {
            fileContainer.innerHTML = `<iframe src="${filePath}" style="width:100%; height:550px; border:none; border-radius: 8px;"></iframe>`;
        } else {
            fileContainer.innerHTML = `<img src="${filePath}" class="lightbox-img" alt="Payment Proof Screen">`;
        }
        
        modal.style.display = 'flex';
    }

    function closeLightbox() {
        document.getElementById('lightboxModal').style.display = 'none';
        document.getElementById('lightboxFileContainer').innerHTML = ''; // Clear contents
    }

    function closeLightboxOnBg(e) {
        if (e.target.id === 'lightboxModal') {
            closeLightbox();
        }
    }

    // ----------------------------------------------------
    // Client-side search and status filter for Participants
    // ----------------------------------------------------
    function filterParticipantsTable() {
        const query = document.getElementById('participantSearchInput').value.toLowerCase();
        const selectedStatus = document.getElementById('participantStatusFilterSelect').value;
        const rows = document.querySelectorAll('#participantsTable tbody tr');
        
        rows.forEach(row => {
            if (row.cells.length === 1) return;
            
            const name = row.getAttribute('data-name');
            const phone = row.getAttribute('data-phone');
            const parish = row.getAttribute('data-parish');
            const trcode = row.getAttribute('data-trcode');
            const zone = row.getAttribute('data-zone');
            const status = row.getAttribute('data-status');
            
            const matchesQuery = name.includes(query) || phone.includes(query) || parish.includes(query) || trcode.includes(query) || zone.includes(query);
            const matchesStatus = (selectedStatus === 'ALL') || (status === selectedStatus);
            
            if (matchesQuery && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // ----------------------------------------------------
    // Client-side search and status filter for Teachers
    // ----------------------------------------------------
    function filterTeachersTable() {
        const query = document.getElementById('teacherSearchInput').value.toLowerCase();
        const selectedStatus = document.getElementById('teacherStatusFilterSelect').value;
        const rows = document.querySelectorAll('#teachersTable tbody tr');
        
        rows.forEach(row => {
            if (row.cells.length === 1) return;
            
            const name = row.getAttribute('data-name');
            const phone = row.getAttribute('data-phone');
            const parish = row.getAttribute('data-parish');
            const trcode = row.getAttribute('data-trcode');
            const status = row.getAttribute('data-status');
            
            const matchesQuery = name.includes(query) || phone.includes(query) || parish.includes(query) || trcode.includes(query);
            const matchesStatus = (selectedStatus === 'ALL') || (status === selectedStatus);
            
            if (matchesQuery && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // ----------------------------------------------------
    // Media Management Scripting
    // ----------------------------------------------------
    window.switchMediaTab = function(tab) {
        const postersBtn = document.getElementById('btnMediaPosters');
        const videosBtn = document.getElementById('btnMediaVideos');
        const postersSub = document.getElementById('mediaSubPosters');
        const videosSub = document.getElementById('mediaSubVideos');
        
        if (tab === 'Posters') {
            postersSub.style.display = 'block';
            videosSub.style.display = 'none';
            postersBtn.className = 'btn btn-gold';
            postersBtn.style.background = '';
            postersBtn.style.border = '';
            postersBtn.style.color = '';
            videosBtn.className = 'btn btn-primary';
            videosBtn.style.background = 'rgba(255,255,255,0.05)';
            videosBtn.style.border = '1px solid rgba(255,255,255,0.1)';
            videosBtn.style.color = 'var(--white)';
        } else {
            postersSub.style.display = 'none';
            videosSub.style.display = 'block';
            videosBtn.className = 'btn btn-gold';
            videosBtn.style.background = '';
            videosBtn.style.border = '';
            videosBtn.style.color = '';
            postersBtn.className = 'btn btn-primary';
            postersBtn.style.background = 'rgba(255,255,255,0.05)';
            postersBtn.style.border = '1px solid rgba(255,255,255,0.1)';
            postersBtn.style.color = 'var(--white)';
        }
    };

    window.handlePosterSelect = function(input) {
        const display = document.getElementById('posterFileDisplay');
        if (input.files && input.files[0]) {
            display.textContent = "Selected: " + input.files[0].name;
            display.style.display = 'flex';
        } else {
            display.textContent = "";
            display.style.display = 'none';
        }
    };

    // Edit Poster Modal
    window.openEditPosterModal = function(id, title) {
        document.getElementById('edit_poster_id').value = id;
        document.getElementById('edit_poster_title').value = title;
        document.getElementById('editPosterModal').style.display = 'flex';
    };

    window.closeEditPosterModal = function() {
        document.getElementById('editPosterModal').style.display = 'none';
    };

    window.closeEditPosterModalOnBg = function(e) {
        if (e.target.id === 'editPosterModal') {
            closeEditPosterModal();
        }
    };

    // Delete Poster
    window.deletePoster = function(id) {
        if (!confirm("Are you sure you want to permanently delete this poster? This cannot be undone.")) return;
        
        showLoading("Deleting poster...");
        const formData = new FormData();
        formData.append('action', 'delete_poster');
        formData.append('id', id);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Failed to delete poster due to network error.", "error");
        });
    };

    // Edit Video Modal
    window.openEditVideoModal = function(id, title, url) {
        document.getElementById('edit_video_id').value = id;
        document.getElementById('edit_video_title').value = title;
        document.getElementById('edit_video_url').value = url;
        document.getElementById('editVideoModal').style.display = 'flex';
    };

    window.closeEditVideoModal = function() {
        document.getElementById('editVideoModal').style.display = 'none';
    };

    window.closeEditVideoModalOnBg = function(e) {
        if (e.target.id === 'editVideoModal') {
            closeEditVideoModal();
        }
    };

    // Delete Video
    window.deleteVideo = function(id) {
        if (!confirm("Are you sure you want to delete this video link?")) return;
        
        showLoading("Deleting video link...");
        const formData = new FormData();
        formData.append('action', 'delete_video');
        formData.append('id', id);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Failed to delete video due to network error.", "error");
        });
    };

    // Delete Admin User
    window.deleteAdmin = function(id, username) {
        if (!confirm(`Are you sure you want to delete administrator account "${username}"?`)) return;
        
        showLoading("Deleting admin account...");
        const formData = new FormData();
        formData.append('action', 'delete_admin');
        formData.append('id', id);
        
        fetch('admin_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Failed to delete user account due to network error.", "error");
        });
    };

    // Drag and Drop Sorting for Posters
    document.addEventListener('DOMContentLoaded', () => {
        const dragList = document.getElementById('postersDragList');
        if (!dragList) return;
        
        let dragSrcEl = null;
        
        const items = dragList.querySelectorAll('.drag-item');
        items.forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('drop', handleDrop);
            item.addEventListener('dragend', handleDragEnd);
        });
        
        function handleDragStart(e) {
            this.style.opacity = '0.4';
            dragSrcEl = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        }
        
        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }
        
        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (dragSrcEl !== this) {
                // Swap the inner HTML and dataset IDs
                const tempHTML = this.innerHTML;
                const tempID = this.getAttribute('data-id');
                
                this.innerHTML = dragSrcEl.innerHTML;
                this.setAttribute('data-id', dragSrcEl.getAttribute('data-id'));
                
                dragSrcEl.innerHTML = tempHTML;
                dragSrcEl.setAttribute('data-id', tempID);
                
                // Save new order to database
                saveNewOrder();
            }
            return false;
        }
        
        function handleDragEnd(e) {
            this.style.opacity = '1';
            items.forEach(item => {
                item.style.opacity = '1';
            });
        }
        
        function saveNewOrder() {
            const currentItems = dragList.querySelectorAll('.drag-item');
            const ids = Array.from(currentItems).map(item => item.getAttribute('data-id'));
            
            showLoading("Updating poster sequence...");
            
            const formData = new FormData();
            formData.append('action', 'reorder_posters');
            ids.forEach(id => formData.append('ids[]', id));
            
            fetch('admin_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                hideLoading();
                showToast("Failed to save reorder state.", "error");
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
