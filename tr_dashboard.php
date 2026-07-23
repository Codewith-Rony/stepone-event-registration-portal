<?php
require_once __DIR__ . '/header.php';

// Prevent access if not logged in as teacher
if (!isset($_SESSION['teacher_logged_in'])) {
    $_SESSION['flash_error'] = "Access denied. Please log in first.";
    header('Location: tr_portal.php');
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

try {
    // Fetch teacher profile info
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        // Log out if teacher record no longer exists
        header('Location: logout.php');
        exit;
    }

    // Fetch registered participants for this teacher
    $stmt = $pdo->prepare("SELECT * FROM participants WHERE tr_id = ? ORDER BY created_at DESC");
    $stmt->execute([$teacher_id]);
    $participants = $stmt->fetchAll();
    
    $participant_count = count($participants);

} catch (Exception $e) {
    die("Error loading dashboard details: " . $e->getMessage());
}
?>

<div class="portal-container">
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-top">
            <div style="font-family: 'Groovezilla', sans-serif; font-size: 1.5rem; color: var(--gold); margin-bottom: 2rem; text-align: center;">
                TR DASHBOARD
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="tr_dashboard.php" class="sidebar-link active"><i class="fa-solid fa-gauge"></i> Overview</a></li>
                <li><a href="index.php" class="sidebar-link"><i class="fa-solid fa-house"></i> View Website</a></li>
            </ul>
        </div>
        
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($teacher['name'], 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <h5><?= htmlspecialchars($teacher['name']) ?></h5>
                <p>Teacher Supervisor</p>
            </div>
            <a href="logout.php" style="color: var(--danger); margin-left: auto;" title="Sign Out"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="portal-content">
        
        <!-- Mobile Tab Navigation Selector -->
        <div class="mobile-portal-nav">
            <label for="mobileNavSelect" style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; display: block;">Navigation Menu</label>
            <select id="mobileNavSelect" onchange="if(this.value==='Website'){window.location.href='index.php';}else if(this.value==='Logout'){window.location.href='logout.php';}else{window.location.href=this.value;}" class="form-control mobile-nav-select" style="cursor: pointer; background-color: rgba(30, 27, 41, 0.95); border-color: rgba(255,255,255,0.15);">
                <option value="tr_dashboard.php" selected>Overview / Dashboard</option>
                <option value="index.php">View Website</option>
                <option value="Logout">Logout (Sign Out)</option>
            </select>
        </div>
        
        <div class="portal-header">
            <div class="portal-title-area">
                <h2>Welcome, <?= htmlspecialchars($teacher['name']) ?>!</h2>
                <p>Track your student registrations and copy your unique TR reference code.</p>
            </div>
            
            <div class="switch-wrapper">
                <span class="badge <?= $is_reg_open ? 'badge-approved' : 'badge-rejected' ?>" style="font-size: 0.85rem;">
                    <i class="fa-solid <?= $is_reg_open ? 'fa-door-open' : 'fa-lock' ?>"></i>
                    Registrations: <?= $is_reg_open ? 'OPEN' : 'CLOSED' ?>
                </span>
            </div>
        </div>

        <div class="dashboard-quick-actions" style="align-items: start;">
            
            <!-- TR Code Display Card -->
            <div class="tr-code-card" style="max-width: 100%; margin-bottom: 0; width: 100%;">
                <h3 style="font-size: 1.1rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 1px;">Your Reference TR Code</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Give this code to your 9th standard students for registration.</p>
                
                <div class="tr-code-value" id="trCodeValue"><?= htmlspecialchars($teacher['tr_code']) ?></div>
                
                <button class="tr-code-copy-btn" onclick="copyTRCode()"><i class="fa-solid fa-copy"></i> Copy Code</button>
            </div>
            
            <!-- Statistics Card -->
            <div class="glass-card" style="padding: 2rem; width: 100%;">
                <h3 style="font-size: 1.1rem; color: var(--gold); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem;">Registered Count</h3>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.95rem; font-weight: 600;">Registered Participants</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: var(--gold);"><?= $participant_count ?></span>
                </div>
                
                <div class="alert alert-success" style="margin: 0; padding: 0.75rem 1rem; font-size: 0.85rem; background: rgba(40,167,69,0.06); border-color: rgba(40,167,69,0.15); color: #72e28f; margin-top: 1rem;">
                    <i class="fa-solid fa-circle-info"></i> Share your TR Code above to register unlimited participants under your supervision.
                </div>
            </div>
            
        </div>

        <!-- Registered Students List -->
        <div class="section-header" style="text-align: left; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.4rem; font-weight: 800; color: var(--text-dark);"><i class="fa-solid fa-users"></i> Registered Students</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Students who completed registration using your TR Code.</p>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>School</th>
                        <th>Parish</th>
                        <th>Phone Number</th>
                        <th>Gender</th>
                        <th>Payment Mode</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($participant_count > 0): ?>
                        <?php $idx = 1; foreach ($participants as $p): ?>
                            <tr>
                                <td><?= $idx++ ?></td>
                                <td style="font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($p['full_name']) ?></td>
                                <td><?= htmlspecialchars($p['school'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['parish']) ?></td>
                                <td><?= htmlspecialchars($p['phone']) ?></td>
                                <td><?= htmlspecialchars($p['gender']) ?></td>
                                <td>
                                    <span class="badge" style="border: 1px solid var(--gold); color: var(--gold); background: transparent; font-size: 0.8rem; font-weight: 600; padding: 2px 6px; border-radius: 4px; display: inline-block;">
                                        <?= htmlspecialchars($p['payment_type'] ?? 'Full') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status = $p['payment_status'];
                                    if ($status === 'Approved') {
                                        echo '<span class="badge badge-approved"><i class="fa-solid fa-circle-check"></i> Approved</span>';
                                    } elseif ($status === 'Rejected') {
                                        echo '<span class="badge badge-rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected</span>';
                                    } else {
                                        echo '<span class="badge badge-pending"><i class="fa-solid fa-hourglass-half"></i> Pending</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 3rem 0;">
                                <i class="fa-solid fa-users-slash" style="font-size: 2.5rem; color: rgba(255,255,255,0.1); margin-bottom: 1rem; display: block;"></i>
                                No students registered under your code yet. Share your code to start.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </main>

</div>

<script>
    function copyTRCode() {
        const trCode = document.getElementById('trCodeValue').innerText;
        navigator.clipboard.writeText(trCode).then(() => {
            showToast('TR Code copied to clipboard!', 'success');
        }).catch(err => {
            showToast('Failed to copy code. Please copy manually.', 'error');
        });
    }
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
