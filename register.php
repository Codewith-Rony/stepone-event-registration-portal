<?php
// Handle AJAX TR Code Validation
if (isset($_GET['validate_tr_code'])) {
    require_once __DIR__ . '/db.php';
    header('Content-Type: application/json');
    $code = strtoupper(trim($_GET['validate_tr_code']));
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE tr_code = ?");
        $stmt->execute([$code]);
        $teacher = $stmt->fetch();
        if ($teacher) {
            echo json_encode(['valid' => true, 'teacher_name' => $teacher['name']]);
        } else {
            echo json_encode(['valid' => false, 'message' => 'Invalid TR Code. Please check the code and try again.']);
        }
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/header.php';

// Fetch registration settings
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $settings_rows = $stmt->fetchAll();
    
    $settings = [];
    foreach ($settings_rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $is_reg_open = (isset($settings['registration_open']) && $settings['registration_open'] === '1');
    $upi_id = isset($settings['upi_id']) ? $settings['upi_id'] : 'teensministry@upi';
    $upi_name = isset($settings['upi_payee_name']) ? $settings['upi_payee_name'] : 'Jesus Youth Teens Kerala';
} catch (Exception $e) {
    $is_reg_open = true;
    $upi_id = 'teensministry@upi';
    $upi_name = 'Jesus Youth Teens Kerala';
}

$error = '';
$success = '';

// If registration is closed, show custom warning
if (!$is_reg_open) {
    ?>
    <div class="bg-grid"></div>
    <div class="glow-orb" style="top: 20%; left: 15%;"></div>
    <div class="form-section">
        <div class="form-container" style="max-width: 550px;">
            <div class="glass-card success-screen" style="border-color: var(--danger);">
                <div class="success-icon" style="border-color: var(--danger); color: var(--danger); background: rgba(220, 53, 69, 0.1);">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h1 class="title-sardin" style="color: var(--text-dark); font-size: 2.2rem;">Registrations Closed</h1>
                <p>We are no longer accepting new registrations for the Step One program. Please contact the ministry coordinators for any urgent inquiries.</p>
                <a href="index.php" class="btn btn-outline"><i class="fa-solid fa-house"></i> Return Home</a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}

// Form Submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tr_code = strtoupper(trim($_POST['tr_code']));
    $full_name = trim($_POST['full_name']);
    $dob = trim($_POST['dob']);
    $age = intval($_POST['age']);
    $gender = trim($_POST['gender']);
    $guardian_name = trim($_POST['guardian_name']);
    $school = trim($_POST['school']);
    $phone = trim($_POST['phone']); // Guardian/Parent Phone Number
    $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
    $parish = trim($_POST['parish']);
    $zone = trim($_POST['zone']);
    $class = trim($_POST['class']);
    $address = trim($_POST['address']);
    $payment_type = trim($_POST['payment_type']);
    $file = isset($_FILES['payment_screenshot']) ? $_FILES['payment_screenshot'] : null;
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_file_size = 5 * 1024 * 1024; // 5 MB
    
    // Basic checks
    if (empty($tr_code) || empty($full_name) || empty($dob) || empty($age) || empty($gender) || empty($guardian_name) || empty($school) || empty($phone) || empty($parish) || empty($zone) || empty($class) || empty($address) || empty($payment_type)) {
        $error = 'All fields marked with an asterisk (*) are required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Please enter a valid 10-digit Guardian/Parent phone number.';
    } else {
        $file_uploaded = ($file && $file['error'] !== UPLOAD_ERR_NO_FILE);
        $new_file_name = null;
        
        if ($file_uploaded) {
            if ($file['error'] !== 0) {
                $error = 'Error uploading payment screenshot proof.';
            } else {
                $file_name = $file['name'];
                $file_size = $file['size'];
                $file_tmp = $file['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (!in_array($file_ext, $allowed_extensions)) {
                    $error = 'Invalid file type. Only JPG, JPEG, PNG, and PDF files are accepted.';
                } elseif ($file_size > $max_file_size) {
                    $error = 'The uploaded receipt proof exceeds the 5 MB size limit.';
                } else {
                    // Create upload directory if not exists
                    $upload_dir = __DIR__ . '/uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $new_file_name = 'proof_' . uniqid('', true) . '.' . $file_ext;
                    $destination = $upload_dir . $new_file_name;
                    
                    if (!move_uploaded_file($file_tmp, $destination)) {
                        $error = 'Unable to save payment screenshot. Please check folder permissions.';
                    }
                }
            }
        }
        
        if (!$error) {
            try {
                // 1. Verify TR Code exists
                $stmt = $pdo->prepare("SELECT id, name FROM teachers WHERE tr_code = ?");
                $stmt->execute([$tr_code]);
                $teacher = $stmt->fetch();
                
                if (!$teacher) {
                    $error = 'Invalid TR Code. Please obtain a valid reference code from your supervising teacher.';
                } else {
                    $tr_id = $teacher['id'];
                    
                    // 2. Check for duplicate participant registration (same name and DOB)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE full_name = ? AND dob = ?");
                    $stmt->execute([$full_name, $dob]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'A student with this name and date of birth is already registered.';
                    } else {
                        // 3. Insert Participant
                        $stmt = $pdo->prepare("INSERT INTO participants (full_name, guardian_name, school, dob, age, gender, phone, email, parish, zone, class, address, tr_id, payment_type, payment_screenshot, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                        $stmt->execute([$full_name, $guardian_name, $school, $dob, $age, $gender, $phone, $email, $parish, $zone, $class, $address, $tr_id, $payment_type, $new_file_name]);
                        
                        $participant_id = $pdo->lastInsertId();
                        
                        $_SESSION['flash_success'] = "Registration submitted successfully! Awaiting admin approval.";
                        header("Location: confirmation.php?id=" . $participant_id);
                        exit;
                    }
                }
            } catch (Exception $e) {
                $error = 'Server Error: ' . $e->getMessage();
            }
        }
    }
}

$zones_list = [
    "Neyyatinkara", "Trivandrum", "Kollam", "Punaloor", "Kottayam", "Pala", "Changanassery", "Kanjirapally", 
    "Idukki", "Kattappana", "Kothamangalam", "Cherthala", "Alappuzha", "Ernakulam", "Angamaly", "Irinjalakuda", 
    "Thrissur", "Palakkad", "Calicut", "Mananthavady", "Thalassery", "Kannur", "Kasargod"
];
sort($zones_list);
?>

<div class="bg-grid"></div>
<div class="glow-orb" style="top: 10%; right: 10%;"></div>
<div class="glow-orb" style="bottom: 10%; left: 5%; background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);"></div>

<div class="form-section">
    <div class="form-container">
        
        <div class="form-header">
            <h2 class="title-sardin" style="color: var(--gold); font-size: 2.5rem;">Participant Registration</h2>
            <p>Complete the registration in two steps. Verify your supervising teacher's code first.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 2rem;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Step 1 Container: Teacher/TR Code Verification -->
        <div id="step1-container" class="glass-form" style="max-width: 550px; margin: 0 auto 2rem auto; padding: 2.5rem; border-radius: 20px;">
            <div class="section-title" style="margin-top: 0; color: var(--gold); font-size: 1.1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.5rem; margin-bottom: 1.5rem;">
                <i class="fa-solid fa-key"></i> Step 1: Verify Supervising Teacher
            </div>
            
            <div class="form-group">
                <label for="step1_tr_code">Teacher/TR Code <span class="required">*</span></label>
                <input type="text" id="step1_tr_code" class="form-control" style="text-transform: uppercase;" required>
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Enter the unique code provided by your supervising teacher.</small>
            </div>
            
            <div id="step1-error" class="alert alert-error" style="display: none; margin-top: 1rem; margin-bottom: 0;"></div>
            
            <button type="button" id="btnVerifyTRCode" class="btn btn-gold" style="width: 100%; margin-top: 1.5rem;"><i class="fa-solid fa-circle-check"></i> Verify & Proceed</button>
        </div>
        
        <!-- Step 2 Container: Student Details Form -->
        <div id="step2-container" style="display: none;">
            <form method="POST" action="register.php" class="glass-form" id="registerForm" enctype="multipart/form-data" onsubmit="return validateForm()">
                
                <div class="section-title" style="margin-top: 0; color: var(--gold); font-size: 1.1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.5rem; margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-user-graduate"></i> Step 2: Participant Information
                </div>
                
                <div class="form-grid">
                    <!-- 1. Code (auto-filled and read-only) -->
                    <div class="form-group">
                        <label for="tr_code">Teacher/TR Code</label>
                        <input type="text" id="tr_code" name="tr_code" class="form-control" value="<?= isset($_POST['tr_code']) ? htmlspecialchars($_POST['tr_code']) : '' ?>" readonly style="background-color: rgba(255, 255, 255, 0.05); color: var(--gold); font-weight: bold; cursor: not-allowed; text-transform: uppercase;">
                    </div>
                    
                    <!-- 2. Full Name -->
                    <div class="form-group">
                        <label for="full_name">Full Name <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter student's full name" value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>" required>
                    </div>
                    
                    <!-- 3. Date of Birth -->
                    <div class="form-group">
                        <label for="dob">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="dob" name="dob" class="form-control" value="<?= isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : '2012-01-01' ?>" required onchange="calculateAge()">
                    </div>
                    
                    <!-- 4. Age (automatically calculated) -->
                    <div class="form-group">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="age" class="form-control" placeholder="Automatically calculated" value="<?= isset($_POST['age']) ? htmlspecialchars($_POST['age']) : '' ?>" required readonly style="background-color: rgba(255, 255, 255, 0.05); cursor: not-allowed;">
                    </div>
                    
                    <!-- 5. Gender -->
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" class="form-control" required style="cursor: pointer;">
                            <option value="" disabled <?= !isset($_POST['gender']) ? 'selected' : '' ?>>Select gender</option>
                            <option value="Male" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    
                    <!-- 6. Guardian/Parent Name -->
                    <div class="form-group">
                        <label for="guardian_name">Guardian / Parent Name <span class="required">*</span></label>
                        <input type="text" id="guardian_name" name="guardian_name" class="form-control" placeholder="Enter guardian's name" value="<?= isset($_POST['guardian_name']) ? htmlspecialchars($_POST['guardian_name']) : '' ?>" required>
                    </div>

                    <!-- School Name -->
                    <div class="form-group">
                        <label for="school">School Name <span class="required">*</span></label>
                        <input type="text" id="school" name="school" class="form-control" placeholder="Enter school name" value="<?= isset($_POST['school']) ? htmlspecialchars($_POST['school']) : '' ?>" required>
                    </div>
                    
                    <!-- 7. Guardian/Parent Phone Number -->
                    <div class="form-group">
                        <label for="phone">Guardian / Parent Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="10-digit phone number" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required pattern="[0-9]{10}">
                    </div>
                    
                    <!-- 8. Email (optional) -->
                    <div class="form-group">
                        <label for="email">Email Address <span class="optional">(Optional)</span></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="student@email.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                    
                    <!-- 9. Parish & Diocese -->
                    <div class="form-group">
                        <label for="parish">Parish & Diocese <span class="required">*</span></label>
                        <input type="text" id="parish" name="parish" class="form-control" placeholder="St. Mary's Chuech, Kanjirapally - Kanjirapally Diocese" value="<?= isset($_POST['parish']) ? htmlspecialchars($_POST['parish']) : '' ?>" required>
                    </div>
                    
                    <!-- 10. Zone (Dropdown) -->
                    <div class="form-group">
                        <label for="zone">Zone <span class="required">*</span></label>
                        <select id="zone" name="zone" class="form-control" required style="cursor: pointer;">
                            <option value="" disabled <?= !isset($_POST['zone']) ? 'selected' : '' ?>>Select Zone</option>
                            <?php foreach ($zones_list as $z): ?>
                                <option value="<?= $z ?>" <?= (isset($_POST['zone']) && $_POST['zone'] === $z) ? 'selected' : '' ?>><?= $z ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- 11. Class (Dropdown 8 to 10) -->
                    <div class="form-group">
                        <label for="class">Class <span class="required">*</span></label>
                        <select id="class" name="class" class="form-control" required style="cursor: pointer;">
                            <option value="" disabled <?= !isset($_POST['class']) ? 'selected' : '' ?>>Select Class</option>
                            <option value="8" <?= (isset($_POST['class']) && $_POST['class'] === '8') ? 'selected' : '' ?>>Class 8</option>
                            <option value="9" <?= (isset($_POST['class']) && $_POST['class'] === '9') ? 'selected' : '' ?>>Class 9</option>
                            <option value="10" <?= (isset($_POST['class']) && $_POST['class'] === '10') ? 'selected' : '' ?>>Class 10</option>
                        </select>
                    </div>
                </div>
                
                <!-- 12. Address -->
                <div class="form-group" style="margin-top: 1.25rem;">
                    <label for="address">Address <span class="required">*</span></label>
                    <textarea id="address" name="address" class="form-control" rows="3" placeholder="Enter full postal address" required><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                </div>

                <!-- Payment Options -->
                <div class="section-title" style="color: var(--gold); font-size: 1.1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.5rem; margin-top: 2.5rem; margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-indian-rupee-sign"></i> Step 3: Registration Fee Payment
                </div>

                <div class="form-group" style="max-width: 400px; margin: 0 auto 2rem auto;">
                    <label for="payment_type">Select Payment Mode <span class="required">*</span></label>
                    <select id="payment_type" name="payment_type" class="form-control" onchange="togglePaymentQR()" required style="cursor: pointer;">
                        <option value="Full" <?= (!isset($_POST['payment_type']) || $_POST['payment_type'] === 'Full') ? 'selected' : '' ?>>Full Payment (₹1200)</option>
                        <option value="Advance" <?= (isset($_POST['payment_type']) && $_POST['payment_type'] === 'Advance') ? 'selected' : '' ?>>Advance Payment (₹600)</option>
                        <option value="Pay Later" <?= (isset($_POST['payment_type']) && $_POST['payment_type'] === 'Pay Later') ? 'selected' : '' ?>>Pay Later / Cash</option>
                    </select>
                </div>
                
                <h4 id="paymentQRHeader" style="font-size: 0.95rem; margin-bottom: 1rem; text-align: center; color: var(--text-dark);">Scan the QR Code to Pay</h4>
                
                <div class="payment-options-grid" style="grid-template-columns: 1fr; max-width: 400px; margin: 0 auto 2rem auto;">
                    <!-- Advance QR Code -->
                    <div id="advanceQRBox" style="display: none; background: rgba(79, 142, 247, 0.04); border: 1px solid rgba(79, 142, 247, 0.12); padding: 1.25rem; border-radius: 12px; text-align: center;">
                        <h5 style="color: var(--gold); font-weight: 700; margin-bottom: 0.75rem; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;">Advance Payment QR</h5>
                        <img class="qr-code-img" src="Assets/payment_qr_600.jpeg" alt="Advance Payment QR Code" style="border-radius: 8px; margin-bottom: 0.5rem; max-width: 150px; border: 1px solid rgba(255, 255, 255, 0.1); display: block; margin: 0 auto 0.5rem auto;">
                        <a href="Assets/payment_qr_600.jpeg" download="stepone_advance_qr_600.jpeg" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: var(--gold); background: rgba(212, 175, 55, 0.08); padding: 3px 10px; border-radius: 6px; border: 1px solid rgba(212, 175, 55, 0.15); text-decoration: none; font-weight: 600; margin-bottom: 0.5rem;"><i class="fa-solid fa-download"></i> Download QR</a>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Used for paying the advance registration fee of ₹600.</p>
                    </div>
                    
                    <!-- Full QR Code -->
                    <div id="fullQRBox" style="background: rgba(79, 142, 247, 0.04); border: 1px solid rgba(79, 142, 247, 0.12); padding: 1.25rem; border-radius: 12px; text-align: center;">
                        <h5 style="color: var(--gold); font-weight: 500; margin-bottom: 0.75rem; text-transform: uppercase; font-size: 0.65rem; letter-spacing: 0.5px;">Full Payment QR</h5>
                        <img class="qr-code-img" src="Assets/payment_qr_1200.jpeg" alt="Full Payment QR Code" style="border-radius: 8px; margin-bottom: 0.5rem; max-width: 150px; border: 1px solid rgba(255, 255, 255, 0.1); display: block; margin: 0 auto 0.5rem auto;">
                        <a href="Assets/payment_qr_1200.jpeg" download="stepone_full_qr_1200.jpeg" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: var(--gold); background: rgba(212, 175, 55, 0.08); padding: 3px 10px; border-radius: 6px; border: 1px solid rgba(212, 175, 55, 0.15); text-decoration: none; font-weight: 600; margin-bottom: 0.5rem;"><i class="fa-solid fa-download"></i> Download QR</a>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Used for paying the complete registration fee of ₹1200.</p>
                    </div>
                </div>

                <!-- UPI ID Copy Section -->
                <div id="upiIdSection" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); padding: 0.75rem 1rem; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; max-width: 400px; margin: 1rem auto;">
                    <span style="font-size: 0.85rem; color: var(--text-light); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-indian-rupee-sign" style="color: var(--gold);"></i>
                        <span>UPI ID: <code style="background: rgba(0,0,0,0.3); padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace; color: var(--gold); font-size: 0.85rem;">ronythomasthekkel-1@oksbi</code></span>
                    </span>
                    <button type="button" onclick="copyUPIID('ronythomasthekkel-1@oksbi')" style="background: transparent; border: none; color: var(--gold); cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; transition: background 0.2s;"><i class="fa-solid fa-copy"></i> Copy</button>
                </div>

                <!-- Bank Transfer Details Option -->
                <div id="bankDetailsSection" class="bank-details-box" style="background: rgba(212, 175, 55, 0.03); border: 1px solid rgba(212, 175, 55, 0.12); padding: 1.5rem; border-radius: 12px; margin: 1.5rem auto; max-width: 400px; text-align: left; position: relative; overflow: hidden;">
                    <div style="position: absolute; right: -10px; bottom: -10px; font-size: 5rem; color: rgba(212, 175, 55, 0.03); pointer-events: none;">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <h5 style="color: var(--gold); font-weight: 700; margin-bottom: 0.75rem; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 1px solid rgba(212, 175, 55, 0.15); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-building-columns"></i> Bank Account Details
                    </h5>
                    <div style="font-size: 0.8rem; line-height: 1.6; color: var(--text-light);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;"><span style="color: var(--text-muted);">Account Name:</span> <strong style="color: var(--text-dark);">RONY THOMAS</strong></div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;"><span style="color: var(--text-muted);">Account Number:</span> <strong style="color: var(--text-dark); font-family: monospace; letter-spacing: 0.5px;">0640053000006476</strong></div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;"><span style="color: var(--text-muted);">IFSC Code:</span> <strong style="color: var(--text-dark); font-family: monospace; letter-spacing: 0.5px;">SIBL0000640</strong></div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;"><span style="color: var(--text-muted);">Bank Name:</span> <strong style="color: var(--text-dark);">South Indian Bank</strong></div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;"><span style="color: var(--text-muted);">Branch Name:</span> <strong style="color: var(--text-dark);">MUNDAKAYAM</strong></div>
                    </div>
                </div>

                <div id="screenshotSection" style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Screenshot Upload -->
                    <div class="form-group">
                        <label>Upload Payment Screenshot <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">(Optional)</span></label>
                        <div class="file-upload-wrapper">
                            <div class="file-upload-box" id="uploadBox" style="padding: 1.5rem; border-radius: 12px; text-align: center; border: 2px dashed rgba(255, 255, 255, 0.15);">
                                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 1.8rem; margin-bottom: 0.5rem; color: var(--gold);"></i>
                                <div class="file-upload-text" id="uploadText" style="font-size: 0.9rem;">Click or Drag receipt here</div>
                                <div class="file-upload-hint" style="font-size: 0.75rem; color: var(--text-muted);">JPG, PNG, PDF (Max 5MB)</div>
                                <input type="file" id="payment_screenshot" name="payment_screenshot" class="file-upload-input" accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileSelected(this)">
                            </div>
                            <div class="selected-file-display" id="fileDisplay" style="display: none; margin-top: 0.75rem; font-size: 0.85rem; align-items: center; gap: 0.5rem; color: var(--success); justify-content: center;">
                                <i class="fa-solid fa-circle-check"></i> <span id="fileNameSpan"></span>
                            </div>
                        </div>
                        <!-- Important Warning/Note -->
                        <div class="alert alert-warning" style="background: rgba(240, 173, 78, 0.05); border: 1px solid rgba(240, 173, 78, 0.15); color: #f0ad4e; font-size: 0.8rem; border-radius: 10px; padding: 0.75rem 1rem; margin-top: 1rem; text-align: left; line-height: 1.4; display: flex; gap: 0.5rem; align-items: start; max-width: 400px; margin-left: auto; margin-right: auto;">
                            <i class="fa-solid fa-circle-exclamation" style="margin-top: 0.15rem; font-size: 0.95rem; color: var(--gold);"></i>
                            <div>
                                <strong>Important Note:</strong> If you are unable to upload the screenshot right now, make sure to come back to this registration section later and upload the screenshot to complete your registration.
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1rem;"><i class="fa-solid fa-check"></i> Complete Registration</button>
                
            </form>
        </div>
        
    </div>
</div>

<script>
    // Step 1: Verify TR Code AJAX execution
    document.getElementById('btnVerifyTRCode').addEventListener('click', function() {
        const codeInput = document.getElementById('step1_tr_code');
        const codeValue = codeInput.value.trim().toUpperCase();
        const errorDiv = document.getElementById('step1-error');
        
        if (!codeValue) {
            errorDiv.textContent = 'Please enter a Teacher/TR code first.';
            errorDiv.style.display = 'block';
            return;
        }
        
        showLoading('Verifying Teacher code...');
        errorDiv.style.display = 'none';
        
        fetch(`register.php?validate_tr_code=${encodeURIComponent(codeValue)}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.valid) {
                    // Populate read-only TR Code field
                    document.getElementById('tr_code').value = codeValue;
                    // Transition between Step 1 and Step 2
                    document.getElementById('step1-container').style.display = 'none';
                    document.getElementById('step2-container').style.display = 'block';
                    showToast(`Code verified! Registering under supervisor: ${data.teacher_name}`, 'success');
                } else {
                    errorDiv.textContent = data.message || 'Invalid TR Code.';
                    errorDiv.style.display = 'block';
                    showToast('TR Code verification failed.', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                errorDiv.textContent = 'A connection error occurred. Please try again.';
                errorDiv.style.display = 'block';
                showToast('Unable to connect to the server.', 'error');
            });
    });

    // Handle Drag and Drop visuals
    const uploadBox = document.getElementById('uploadBox');
    const fileInput = document.getElementById('payment_screenshot');
    const fileDisplay = document.getElementById('fileDisplay');
    const fileNameSpan = document.getElementById('fileNameSpan');
    const uploadText = document.getElementById('uploadText');

    if (uploadBox && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadBox.addEventListener(eventName, (e) => {
                e.preventDefault();
                uploadBox.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadBox.addEventListener(eventName, (e) => {
                e.preventDefault();
                uploadBox.classList.remove('dragover');
            }, false);
        });

        uploadBox.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFileSelected(fileInput);
        });
    }

    function handleFileSelected(input) {
        if (input.files && input.files.length > 0) {
            const fileName = input.files[0].name;
            fileNameSpan.textContent = fileName;
            fileDisplay.style.display = 'flex';
            uploadText.textContent = "Change selected file";
            showToast("Screenshot successfully selected!", "info");
        } else {
            fileDisplay.style.display = 'none';
            uploadText.textContent = "Click or Drag receipt here";
        }
    }

    function copyUPIID(upiId) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(upiId).then(() => {
                showToast('UPI ID copied to clipboard!', 'success');
            }).catch(err => {
                fallbackCopyText(upiId);
            });
        } else {
            fallbackCopyText(upiId);
        }
    }

    function fallbackCopyText(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showToast('UPI ID copied to clipboard!', 'success');
            } else {
                showToast('Failed to copy. Please copy manually.', 'error');
            }
        } catch (err) {
            showToast('Failed to copy. Please copy manually.', 'error');
        }
        document.body.removeChild(textArea);
    }

    // Automatically calculate age from DOB
    function calculateAge() {
        const dobInput = document.getElementById('dob').value;
        if (!dobInput) return;
        
        const dob = new Date(dobInput);
        const today = new Date();
        
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        
        document.getElementById('age').value = age >= 0 ? age : 0;
    }

    function togglePaymentQR() {
        const type = document.getElementById('payment_type').value;
        const advanceBox = document.getElementById('advanceQRBox');
        const fullBox = document.getElementById('fullQRBox');
        const qrHeader = document.getElementById('paymentQRHeader');
        const upiIdSection = document.getElementById('upiIdSection');
        const bankDetailsSection = document.getElementById('bankDetailsSection');
        const screenshotSection = document.getElementById('screenshotSection');
        
        if (type === 'Advance') {
            advanceBox.style.display = 'block';
            fullBox.style.display = 'none';
            if (qrHeader) qrHeader.style.display = 'block';
            if (upiIdSection) upiIdSection.style.display = 'flex';
            if (bankDetailsSection) bankDetailsSection.style.display = 'block';
            if (screenshotSection) screenshotSection.style.display = 'block';
        } else if (type === 'Pay Later') {
            advanceBox.style.display = 'none';
            fullBox.style.display = 'none';
            if (qrHeader) qrHeader.style.display = 'none';
            if (upiIdSection) upiIdSection.style.display = 'none';
            if (bankDetailsSection) bankDetailsSection.style.display = 'none';
            if (screenshotSection) screenshotSection.style.display = 'none';
        } else {
            advanceBox.style.display = 'none';
            fullBox.style.display = 'block';
            if (qrHeader) qrHeader.style.display = 'block';
            if (upiIdSection) upiIdSection.style.display = 'flex';
            if (bankDetailsSection) bankDetailsSection.style.display = 'block';
            if (screenshotSection) screenshotSection.style.display = 'block';
        }
    }

    // Run on DOM load to ensure initial state matches select value
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('payment_type')) {
            togglePaymentQR();
        }
        if (document.getElementById('dob')) {
            calculateAge();
        }

        // Auto-save form fields feature
        const formId = 'registerForm';
        const storageKey = 'stepone_participant_form_data';
        const form = document.getElementById(formId);
        
        // Load step 1 TR Code if saved
        const savedTRCode = localStorage.getItem('stepone_step1_tr_code');
        if (savedTRCode && document.getElementById('step1_tr_code')) {
            document.getElementById('step1_tr_code').value = savedTRCode;
        }

        if (form) {
            const savedData = localStorage.getItem(storageKey);
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    Object.keys(data).forEach(key => {
                        const element = form.elements[key];
                        if (element) {
                            if (element.type === 'checkbox') {
                                element.checked = data[key];
                            } else if (element.type === 'radio') {
                                const radio = Array.from(form.elements[key]).find(r => r.value === data[key]);
                                if (radio) radio.checked = true;
                            } else {
                                element.value = data[key];
                            }
                        }
                    });
                    if (data['dob']) calculateAge();
                    if (data['payment_type']) togglePaymentQR();
                } catch (e) {
                    console.error('Error loading saved form data', e);
                }
            }

            // Save input data to local storage on input/change events
            const saveFormHandler = function() {
                const formData = {};
                Array.from(form.elements).forEach(element => {
                    if (element.name && element.type !== 'file' && element.type !== 'password') {
                        if (element.type === 'checkbox') {
                            formData[element.name] = element.checked;
                        } else if (element.type === 'radio') {
                            if (element.checked) {
                                formData[element.name] = element.value;
                            }
                        } else {
                            formData[element.name] = element.value;
                        }
                    }
                });
                localStorage.setItem(storageKey, JSON.stringify(formData));
            };

            form.addEventListener('input', saveFormHandler);
            form.addEventListener('change', saveFormHandler);
            
            // Clear cache upon form submission
            form.onformsubmit = function() {
                localStorage.removeItem(storageKey);
                localStorage.removeItem('stepone_step1_tr_code');
            };
            form.addEventListener('submit', form.onformsubmit);
        }

        // Save step 1 TR code as well
        const step1Input = document.getElementById('step1_tr_code');
        if (step1Input) {
            step1Input.addEventListener('input', function() {
                localStorage.setItem('stepone_step1_tr_code', step1Input.value.trim().toUpperCase());
            });
        }
    });

    // Final form validations on client side
    function validateForm() {
        const age = parseInt(document.getElementById('age').value);
        if (isNaN(age) || age <= 0) {
            showToast("Please enter a valid Date of Birth.", "error");
            return false;
        }

        // Show global loading indicator
        showLoading("Registering participant...");
        return true;
    }

    // If page is loaded with POST data that had validation errors, restore step 2 state
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error): ?>
        document.getElementById('step1-container').style.display = 'none';
        document.getElementById('step2-container').style.display = 'block';
    <?php endif; ?>
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
