<?php
require_once __DIR__ . '/header.php';

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_dashboard.php');
    exit;
}
if (isset($_SESSION['teacher_logged_in'])) {
    header('Location: tr_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['admin_username']);
    $password = $_POST['admin_password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = empty($admin['role']) ? 'super_admin' : $admin['role'];
                
                $_SESSION['flash_success'] = "Welcome back, Admin!";
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>

<div class="bg-grid"></div>
<div class="glow-orb" style="top: 15%; right: 10%; background: radial-gradient(circle, rgba(119, 60, 227, 0.15) 0%, transparent 70%);"></div>
<div class="glow-orb" style="bottom: 10%; left: 5%;"></div>

<div class="form-section">
    <div class="form-container" style="max-width: 480px;">
        
        <div class="form-header">
            <h2 class="title-sardin" style="color: var(--gold); font-size: 2.2rem;">Admin Console</h2>
            <p>Authorized access only. Sign in to manage registrations and view dashboard statistics.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 2rem;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="admin_login.php" class="glass-form" onsubmit="showLoading('Authenticating Admin...')">
            
            <div class="form-group">
                <label for="admin_username">Admin Username</label>
                <div style="position: relative;">
                    <input type="text" id="admin_username" name="admin_username" class="form-control" placeholder="Enter username" required autocomplete="username" style="padding-left: 2.75rem;">
                    <i class="fa-solid fa-user" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="admin_password">Password</label>
                <div style="position: relative;">
                    <input type="password" id="admin_password" name="admin_password" class="form-control" placeholder="••••••••" required autocomplete="current-password" style="padding-left: 2.75rem;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                </div>
            </div>
            
            <button type="submit" class="btn btn-gold" style="width: 100%;"><i class="fa-solid fa-key"></i> Authenticate</button>
            
        </form>
        
        <p style="text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--text-muted);">
            Are you a Teacher? <a href="tr_portal.php" style="color: var(--gold); text-decoration: none; font-weight: 600;">Sign in to Teachers Portal</a>
        </p>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
