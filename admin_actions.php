<?php
session_start();
require_once __DIR__ . '/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'super_admin';

// Determine the requested action
$requested_action = '';
if (isset($_GET['export'])) {
    $requested_action = 'export';
} elseif (isset($_POST['action'])) {
    $requested_action = $_POST['action'];
} elseif (isset($_GET['action'])) {
    $requested_action = $_GET['action'];
}

// Restricted actions for Media Admin
$media_allowed_actions = ['add_poster', 'edit_poster', 'delete_poster', 'reorder_posters', 'add_video', 'edit_video', 'delete_video'];

if ($admin_role === 'media_admin') {
    if (!in_array($requested_action, $media_allowed_actions)) {
        header('HTTP/1.1 403 Forbidden');
        exit('Forbidden: Media Admin does not have permission to perform this action.');
    }
}

// ----------------------------------------------------
// 1. Export Data to CSV
// ----------------------------------------------------
if (isset($_GET['export']) && ($_GET['export'] === 'participants' || $_GET['export'] === 'teachers')) {
    $type = $_GET['export'];
    $filename = $type . "_export_" . date("Ymd_His") . ".csv";
    
    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'participants') {
        // Headers for participants csv
        fputcsv($output, ['ID', 'Full Name', 'Guardian Name', 'School', 'DOB', 'Age', 'Gender', 'Guardian Phone', 'Email', 'Parish', 'Zone', 'Class', 'Address', 'Supervising Teacher', 'TR Code', 'Payment Type', 'Payment Status', 'Registration Date']);
        
        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.full_name, p.guardian_name, p.school, p.dob, p.age, p.gender, p.phone, p.email, p.parish, 
                       p.zone, p.class, p.address, t.name as teacher_name, t.tr_code, p.payment_type, p.payment_status, p.created_at 
                FROM participants p 
                JOIN teachers t ON p.tr_id = t.id 
                ORDER BY p.id ASC
            ");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                fputcsv($output, $row);
            }
        } catch (Exception $e) {
            fputcsv($output, ['Error loading data: ' . $e->getMessage()]);
        }
    } else {
        // Headers for teachers csv
        fputcsv($output, ['ID', 'Full Name', 'Email', 'Phone', 'School', 'Parish', 'Gender', 'Married', 'DOB', 'Age', 'Address', 'Prior Experience', 'Zone', 'TR Code', 'Student Count', 'Payment Type', 'Payment Status', 'Registered Date']);
        
        try {
            $stmt = $pdo->prepare("
                SELECT t.id, t.name, t.email, t.phone, t.school, t.parish, t.gender, t.married, t.dob, t.age, t.address, 
                       t.prior_experience, t.zone, t.tr_code, 
                       (SELECT COUNT(*) FROM participants WHERE tr_id = t.id) as student_count, 
                       t.payment_type, t.payment_status, t.created_at 
                FROM teachers t 
                ORDER BY t.id ASC
            ");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                fputcsv($output, $row);
            }
        } catch (Exception $e) {
            fputcsv($output, ['Error loading data: ' . $e->getMessage()]);
        }
    }
    
    fclose($output);
    exit;
}

// ----------------------------------------------------
// 2. AJAX Status Toggle (Approve / Reject Payments)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payment') {
    header('Content-Type: application/json');
    
    $p_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    if ($p_id <= 0 || !in_array($status, ['Approved', 'Rejected', 'Pending'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters provided.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE participants SET payment_status = ? WHERE id = ?");
        $stmt->execute([$status, $p_id]);
        
        echo json_encode(['success' => true, 'message' => "Payment status successfully updated to {$status}."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_teacher_payment') {
    header('Content-Type: application/json');
    
    $t_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    if ($t_id <= 0 || !in_array($status, ['Approved', 'Rejected', 'Pending'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters provided.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE teachers SET payment_status = ? WHERE id = ?");
        $stmt->execute([$status, $t_id]);
        
        echo json_encode(['success' => true, 'message' => "Teacher payment status successfully updated to {$status}."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ----------------------------------------------------
// AJAX Delete Participant (after confirmation)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_participant') {
    header('Content-Type: application/json');
    
    $p_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $admin_password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
    
    if ($p_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid participant ID.']);
        exit;
    }
    
    $admin_id = $_SESSION['admin_id'] ?? 0;
    if ($admin_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized action. Please log in first.']);
        exit;
    }
    
    if (empty($admin_password)) {
        echo json_encode(['success' => false, 'message' => 'Admin password is required to delete.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_hash = $stmt->fetchColumn();
        
        if (!$admin_hash || !password_verify($admin_password, $admin_hash)) {
            echo json_encode(['success' => false, 'message' => 'Verification failed. Incorrect admin password.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT payment_screenshot FROM participants WHERE id = ?");
        $stmt->execute([$p_id]);
        $screenshot = $stmt->fetchColumn();
        if ($screenshot) {
            $filepath = __DIR__ . '/uploads/' . $screenshot;
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM participants WHERE id = ?");
        $stmt->execute([$p_id]);
        
        echo json_encode(['success' => true, 'message' => "Participant successfully deleted."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ----------------------------------------------------
// AJAX Delete Teacher (after confirmation)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_teacher') {
    header('Content-Type: application/json');
    
    $t_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $admin_password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
    
    if ($t_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid teacher ID.']);
        exit;
    }
    
    $admin_id = $_SESSION['admin_id'] ?? 0;
    if ($admin_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized action. Please log in first.']);
        exit;
    }
    
    if (empty($admin_password)) {
        echo json_encode(['success' => false, 'message' => 'Admin password is required to delete.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_hash = $stmt->fetchColumn();
        
        if (!$admin_hash || !password_verify($admin_password, $admin_hash)) {
            echo json_encode(['success' => false, 'message' => 'Verification failed. Incorrect admin password.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT payment_screenshot FROM teachers WHERE id = ?");
        $stmt->execute([$t_id]);
        $teacher_screenshot = $stmt->fetchColumn();
        if ($teacher_screenshot) {
            $filepath = __DIR__ . '/uploads/' . $teacher_screenshot;
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }

        $stmt = $pdo->prepare("SELECT payment_screenshot FROM participants WHERE tr_id = ?");
        $stmt->execute([$t_id]);
        $screenshots = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($screenshots as $screenshot) {
            if ($screenshot) {
                $filepath = __DIR__ . '/uploads/' . $screenshot;
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
            }
        }

        $stmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$t_id]);
        
        echo json_encode(['success' => true, 'message' => "Teacher and all supervised participants successfully deleted."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ----------------------------------------------------
// 3. AJAX Registration Switch (Open / Close)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_registration') {
    header('Content-Type: application/json');
    
    $status = isset($_POST['status']) ? trim($_POST['status']) : '0';
    $status_val = ($status === '1') ? '1' : '0';
    
    try {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'registration_open'");
        $stmt->execute([$status_val]);
        
        $msg = ($status_val === '1') ? 'Registrations are now open.' : 'Registrations are now closed.';
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to toggle registrations: ' . $e->getMessage()]);
        exit;
    }
}

// ----------------------------------------------------
// 4. POST Update Settings (UPI ID & Payee Name)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $upi_id = trim($_POST['upi_id']);
    $upi_payee = trim($_POST['upi_payee_name']);
    
    if (empty($upi_id) || empty($upi_payee)) {
        $_SESSION['flash_error'] = "UPI settings fields cannot be empty.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'upi_id'");
            $stmt->execute([$upi_id]);
            
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'upi_payee_name'");
            $stmt->execute([$upi_payee]);
            
            $_SESSION['flash_success'] = "Payment settings updated successfully.";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Failed to update settings: " . $e->getMessage();
        }
    }
    
    header('Location: admin_dashboard.php');
    exit;
}

// Helper to parse YouTube and Instagram links
function parseVideoLink($url) {
    $url = trim($url);
    $platform = '';
    
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)|shorts)/|youtu\.be/)([^"&?/\s]{11})%i', $url, $match)) {
        $platform = 'youtube';
    } elseif (preg_match('%instagram\.com/(?:p|reel)/([^/?#&]+)%i', $url, $match)) {
        $platform = 'instagram';
    }
    
    return $platform;
}

// ----------------------------------------------------
// 5. Posters Management Actions
// ----------------------------------------------------

// Add Poster
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_poster') {
    $title = trim($_POST['title']);
    
    if (empty($title)) {
        $_SESSION['flash_error'] = "Poster title is required.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    if (!isset($_FILES['poster']) || $_FILES['poster']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = "Please select a valid poster file to upload.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    $file = $_FILES['poster'];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_exts)) {
        $_SESSION['flash_error'] = "Invalid file format. Accepted formats: JPG, JPEG, PNG, WEBP.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    // Max 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        $_SESSION['flash_error'] = "File size exceeds 5 MB limit.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    // Create folders
    $upload_dir = __DIR__ . '/uploads/posters/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $new_filename = 'poster_' . uniqid() . '.' . $file_ext;
    $dest_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        try {
            $stmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM posters");
            $next_order = $stmt->fetchColumn() + 1;
            
            $stmt = $pdo->prepare("INSERT INTO posters (title, filename, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$title, $new_filename, $next_order]);
            
            $_SESSION['flash_success'] = "Poster added successfully!";
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Failed to save uploaded file.";
    }
    
    header('Location: admin_dashboard.php');
    exit;
}

// Edit Poster
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_poster') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = trim($_POST['title']);
    
    if ($id <= 0 || empty($title)) {
        $_SESSION['flash_error'] = "Invalid poster parameters.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    try {
        // Fetch current poster
        $stmt = $pdo->prepare("SELECT filename FROM posters WHERE id = ?");
        $stmt->execute([$id]);
        $current_filename = $stmt->fetchColumn();
        
        if (!$current_filename) {
            $_SESSION['flash_error'] = "Poster not found.";
            header('Location: admin_dashboard.php');
            exit;
        }
        
        $new_filename = $current_filename;
        
        // If new file is uploaded
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['poster'];
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($file_ext, $allowed_exts) && $file['size'] <= 5 * 1024 * 1024) {
                $upload_dir = __DIR__ . '/uploads/posters/';
                $new_filename = 'poster_' . uniqid() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $dest_path)) {
                    // Delete old file
                    $old_file_path = $upload_dir . $current_filename;
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
            } else {
                $_SESSION['flash_error'] = "Invalid file format or size exceeds 5 MB.";
                header('Location: admin_dashboard.php');
                exit;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE posters SET title = ?, filename = ? WHERE id = ?");
        $stmt->execute([$title, $new_filename, $id]);
        $_SESSION['flash_success'] = "Poster updated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
    }
    
    header('Location: admin_dashboard.php');
    exit;
}

// Delete Poster
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_poster') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid poster ID.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT filename FROM posters WHERE id = ?");
        $stmt->execute([$id]);
        $filename = $stmt->fetchColumn();
        
        if ($filename) {
            $file_path = __DIR__ . '/uploads/posters/' . $filename;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM posters WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Poster deleted successfully.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Reorder Posters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder_posters') {
    header('Content-Type: application/json');
    $ids = isset($_POST['ids']) ? $_POST['ids'] : [];
    
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        $order = 1;
        foreach ($ids as $id) {
            $stmt = $pdo->prepare("UPDATE posters SET sort_order = ? WHERE id = ?");
            $stmt->execute([$order, intval($id)]);
            $order++;
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Poster order updated successfully.']);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ----------------------------------------------------
// 6. Videos Management Actions
// ----------------------------------------------------

// Add Video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_video') {
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    
    if (empty($title) || empty($url)) {
        $_SESSION['flash_error'] = "Video title and URL are required.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    $platform = parseVideoLink($url);
    if (empty($platform)) {
        $_SESSION['flash_error'] = "Invalid link. Please provide a valid YouTube or Instagram Reel link.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO videos (title, url, platform) VALUES (?, ?, ?)");
        $stmt->execute([$title, $url, $platform]);
        $_SESSION['flash_success'] = "Video link added successfully!";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
    }
    
    header('Location: admin_dashboard.php');
    exit;
}

// Edit Video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_video') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    
    if ($id <= 0 || empty($title) || empty($url)) {
        $_SESSION['flash_error'] = "Video parameters are invalid.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    $platform = parseVideoLink($url);
    if (empty($platform)) {
        $_SESSION['flash_error'] = "Invalid link. Please provide a valid YouTube or Instagram Reel link.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE videos SET title = ?, url = ?, platform = ? WHERE id = ?");
        $stmt->execute([$title, $url, $platform, $id]);
        $_SESSION['flash_success'] = "Video link updated successfully!";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
    }
    
    header('Location: admin_dashboard.php');
    exit;
}

// Delete Video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_video') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid video ID.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Video deleted successfully.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// ----------------------------------------------------
// 7. Admins Management Actions (Super Admin only)
// ----------------------------------------------------

// Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    if ($admin_role !== 'super_admin') {
        $_SESSION['flash_error'] = "Access denied. Only Super Admin can manage users.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = trim($_POST['role']);
    
    if (empty($username) || empty($password) || !in_array($role, ['super_admin', 'media_admin'])) {
        $_SESSION['flash_error'] = "All admin fields are required.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    try {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hashed, $role]);
        $_SESSION['flash_success'] = "Admin user added successfully!";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Database error or username already exists: " . $e->getMessage();
    }
    
    header('Location: admin_dashboard.php');
    exit;
}

// Delete Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    header('Content-Type: application/json');
    if ($admin_role !== 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'Access denied. Only Super Admin can manage users.']);
        exit;
    }
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
        exit;
    }
    
    if ($id === $_SESSION['admin_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own logged-in account.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'User account deleted successfully.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// If no matched action, redirect back
header('Location: admin_dashboard.php');
exit;
?>
