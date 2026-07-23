<?php
require_once __DIR__ . '/header.php';

// Redirect if already logged in
if (isset($_SESSION['teacher_logged_in'])) {
    header('Location: tr_dashboard.php');
    exit;
}
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
$success = '';
$active_tab = 'login'; // 'login' or 'register'

// Fetch UPI settings
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $settings_rows = $stmt->fetchAll();
    
    $settings = [];
    foreach ($settings_rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $upi_id = isset($settings['upi_id']) ? $settings['upi_id'] : 'teensministry@upi';
    $upi_name = isset($settings['upi_payee_name']) ? $settings['upi_payee_name'] : 'Jesus Youth Teens Kerala';
} catch (Exception $e) {
    $upi_id = 'teensministry@upi';
    $upi_name = 'Jesus Youth Teens Kerala';
}

$zone_map = [
    "Neyyatinkara" => "NTA",
    "Trivandrum" => "TVM",
    "Kollam" => "KLM",
    "Punaloor" => "PNR",
    "Kottayam" => "KTM",
    "Pala" => "PAL",
    "Changanassery" => "CHN",
    "Kanjirapally" => "KPLY",
    "Idukki" => "IDK",
    "Kattappana" => "KTP",
    "Kothamangalam" => "KTG",
    "Cherthala" => "CRT",
    "Alappuzha" => "ALP",
    "Ernakulam" => "EKM",
    "Angamaly" => "ANG",
    "Irinjalakuda" => "OKK",
    "Thrissur" => "TSR",
    "Palakkad" => "PKD",
    "Calicut" => "CLT",
    "Mananthavady" => "MNT",
    "Thalassery" => "TLS",
    "Kannur" => "KNR",
    "Kasargod" => "KSG"
];
ksort($zone_map);
$zones_list = array_keys($zone_map);

// Function to generate a unique TR Code based on Zone
function generateTRCode($pdo, $zone) {
    global $zone_map;
    $prefix = isset($zone_map[$zone]) ? $zone_map[$zone] : 'TR';
    
    $stmt = $pdo->prepare("SELECT tr_code FROM teachers WHERE tr_code LIKE ? ORDER BY tr_code DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $latest = $stmt->fetchColumn();
    
    if ($latest) {
        $num_str = substr($latest, strlen($prefix));
        $num = intval($num_str);
        $next_num = $num + 1;
    } else {
        $next_num = 1;
    }
    
    $new_code = $prefix . sprintf('%03d', $next_num);
    
    // Safety check to ensure uniqueness
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE tr_code = ?");
        $stmt->execute([$new_code]);
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        $next_num++;
        $new_code = $prefix . sprintf('%03d', $next_num);
    }
    
    return $new_code;
}

// Handle Teacher Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $active_tab = 'register';
    
    $name = trim($_POST['tr_name']);
    $email = !empty(trim($_POST['tr_email'])) ? trim($_POST['tr_email']) : null;
    $phone = trim($_POST['tr_phone']);
    $school = trim($_POST['tr_school']);
    $parish = trim($_POST['tr_parish']);
    $gender = trim($_POST['tr_gender']);
    $married = trim($_POST['tr_married']);
    $dob = trim($_POST['tr_dob']);
    $age = intval($_POST['tr_age']);
    $address = trim($_POST['tr_address']);
    $prior_experience = trim($_POST['tr_experience']);
    $zone = trim($_POST['tr_zone']);
    $payment_type = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : 'Full';
    
    $file = isset($_FILES['payment_screenshot']) ? $_FILES['payment_screenshot'] : null;
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $max_file_size = 5 * 1024 * 1024; // 5 MB
    
    if (empty($name) || empty($phone) || empty($school) || empty($parish) || empty($gender) || empty($married) || empty($dob) || empty($age) || empty($address) || empty($prior_experience) || empty($zone) || empty($payment_type)) {
        $error = 'All fields marked with an asterisk (*) are required.';
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } elseif ($age < 20) {
        $error = 'You must be at least 20 years old to register.';
    } else {
        try {
            // Backend age verification double check
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $calculated_age = $today->diff($birthDate)->y;
            if ($calculated_age < 20) {
                throw new Exception('You must be at least 20 years old to register.');
            }
            // Check for duplicate Email (if provided)
            if ($email) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('This email address is already registered.');
                }
            }
            
            // Check for duplicate Phone
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('This phone number is already registered.');
            }
            
            $file_uploaded = ($file && $file['error'] !== UPLOAD_ERR_NO_FILE);
            $new_file_name = null;
            
            if ($file_uploaded) {
                if ($file['error'] !== 0) {
                    throw new Exception('Error uploading payment screenshot proof.');
                }
                // File Upload validation and processing
                $file_name = $file['name'];
                $file_size = $file['size'];
                $file_tmp = $file['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (!in_array($file_ext, $allowed_extensions)) {
                    throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and PDF files are accepted.');
                } elseif ($file_size > $max_file_size) {
                    throw new Exception('The uploaded file exceeds the 5 MB size limit.');
                }
                
                $upload_dir = __DIR__ . '/uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_file_name = 'tr_proof_' . uniqid('', true) . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (!move_uploaded_file($file_tmp, $destination)) {
                    throw new Exception('Unable to save payment screenshot. Please check folder permissions.');
                }
            }
            
            // Generate Username & Password
            // Password: first name (lowercase) + YOB (e.g. john1990)
            $names = explode(' ', trim($name));
            $first_name = strtolower($names[0]);
            $yob = date('Y', strtotime($dob));
            $password_plain = $first_name . $yob;
            $hashed_pass = password_hash($password_plain, PASSWORD_DEFAULT);
            
            // Generate Zone-based TR Code
            $tr_code = generateTRCode($pdo, $zone);
            
            // Insert Teacher
            $stmt = $pdo->prepare("INSERT INTO teachers (name, email, phone, school, parish, gender, married, dob, age, address, prior_experience, zone, payment_type, tr_code, password, payment_screenshot) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $school, $parish, $gender, $married, $dob, $age, $address, $prior_experience, $zone, $payment_type, $tr_code, $hashed_pass, $new_file_name]);
            
            $teacher_id = $pdo->lastInsertId();
            
            $_SESSION['teacher_logged_in'] = true;
            $_SESSION['teacher_id'] = $teacher_id;
            $_SESSION['teacher_name'] = $name;
            $_SESSION['teacher_code'] = $tr_code;
            
            // Set message to display credentials
            $_SESSION['flash_success'] = "Account created! For future logins: Username: " . ($email ? $email : $phone) . " | Password: " . $password_plain;
            header('Location: tr_dashboard.php');
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle Teacher Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $active_tab = 'login';
    
    $username = trim($_POST['login_username']); // Email or Phone
    $password = trim($_POST['login_password']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in both email/phone number and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ? OR phone = ?");
            $stmt->execute([$username, $username]);
            $teacher = $stmt->fetch();
            
            if ($teacher && password_verify($password, $teacher['password'])) {
                $_SESSION['teacher_logged_in'] = true;
                $_SESSION['teacher_id'] = $teacher['id'];
                $_SESSION['teacher_name'] = $teacher['name'];
                $_SESSION['teacher_code'] = $teacher['tr_code'];
                
                $_SESSION['flash_success'] = "Logged in successfully! Welcome back, " . htmlspecialchars($teacher['name']);
                header('Location: tr_dashboard.php');
                exit;
            } else {
                $error = 'Invalid Username or Password.';
            }
        } catch (Exception $e) {
            $error = 'Login failed: ' . $e->getMessage();
        }
    }
}
?>

<div class="bg-grid"></div>
<div class="glow-orb" style="top: 15%; right: 10%;"></div>
<div class="glow-orb" style="bottom: 10%; left: 5%; background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);"></div>

<div class="form-section">
    <div class="form-container">
        
        <div class="form-header">
            <h2 class="title-sardin" style="color: var(--gold);">Teachers Portal</h2>
            <p>Access your Step One dashboard to view registrations and manage participants.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 2rem;"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="glass-form" style="padding-top: 1.5rem;">
            <!-- Tab Switchers -->
            <div style="display: flex; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 2.5rem; justify-content: center; gap: 2rem;">
                <button type="button" id="tabBtnLogin" class="btn btn-outline" style="border: none; background: transparent; padding: 1rem 2rem; border-radius: 0; color: <?= $active_tab === 'login' ? 'var(--gold)' : 'var(--text-muted)' ?>; font-weight: 700; border-bottom: 3px solid <?= $active_tab === 'login' ? 'var(--gold)' : 'transparent' ?>;">
                    <i class="fa-solid fa-right-to-bracket"></i> Teacher Sign In
                </button>
                <button type="button" id="tabBtnRegister" class="btn btn-outline" style="border: none; background: transparent; padding: 1rem 2rem; border-radius: 0; color: <?= $active_tab === 'register' ? 'var(--gold)' : 'var(--text-muted)' ?>; font-weight: 700; border-bottom: 3px solid <?= $active_tab === 'register' ? 'var(--gold)' : 'transparent' ?>;">
                    <i class="fa-solid fa-user-plus"></i> Create Account
                </button>
            </div>
            
            <!-- Login Form -->
            <div id="loginFormSection" style="display: <?= $active_tab === 'login' ? 'block' : 'none' ?>;">
                <form method="POST" action="tr_portal.php" onsubmit="showLoading('Signing you in...')">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="login_username">Email Address or Phone Number</label>
                        <input type="text" id="login_username" name="login_username" class="form-control" placeholder="name@email.com or 10-digit number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="login_password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn btn-gold" style="width: 100%; margin-top: 1.5rem;"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
                </form>
            </div>
            
            <!-- Registration Form -->
            <div id="registerFormSection" style="display: <?= $active_tab === 'register' ? 'block' : 'none' ?>;">
                <form method="POST" action="tr_portal.php" enctype="multipart/form-data" id="trRegisterForm" onsubmit="return validateTRForm()">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-grid">
                        <!-- Full Name -->
                        <div class="form-group">
                            <label for="tr_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="tr_name" name="tr_name" class="form-control" placeholder="Enter full name" value="<?= isset($_POST['tr_name']) ? htmlspecialchars($_POST['tr_name']) : '' ?>" required>
                        </div>
                        
                        <!-- Email (optional) -->
                        <div class="form-group">
                            <label for="tr_email">Email Address <span class="optional">(Optional)</span></label>
                            <input type="email" id="tr_email" name="tr_email" class="form-control" placeholder="name@school.com" value="<?= isset($_POST['tr_email']) ? htmlspecialchars($_POST['tr_email']) : '' ?>" autocomplete="email">
                        </div>
                        
                        <!-- Phone Number -->
                        <div class="form-group">
                            <label for="tr_phone">Phone Number <span class="required">*</span></label>
                            <input type="tel" id="tr_phone" name="tr_phone" class="form-control" placeholder="10-digit number" value="<?= isset($_POST['tr_phone']) ? htmlspecialchars($_POST['tr_phone']) : '' ?>" required pattern="[0-9]{10}">
                        </div>
                        
                        <!-- School -->
                        <div class="form-group">
                            <label for="tr_school">School <span class="required">*</span></label>
                            <input type="text" id="tr_school" name="tr_school" class="form-control" placeholder="School name" value="<?= isset($_POST['tr_school']) ? htmlspecialchars($_POST['tr_school']) : '' ?>" required>
                        </div>
                        
                        <!-- Parish -->
                        <div class="form-group">
                            <label for="tr_parish">Parish <span class="required">*</span></label>
                            <input type="text" id="tr_parish" name="tr_parish" class="form-control" placeholder="Parish name" value="<?= isset($_POST['tr_parish']) ? htmlspecialchars($_POST['tr_parish']) : '' ?>" required>
                        </div>
                        
                        <!-- Gender -->
                        <div class="form-group">
                            <label for="tr_gender">Gender <span class="required">*</span></label>
                            <select id="tr_gender" name="tr_gender" class="form-control" required style="cursor: pointer;">
                                <option value="" disabled <?= !isset($_POST['tr_gender']) ? 'selected' : '' ?>>Select gender</option>
                                <option value="Male" <?= (isset($_POST['tr_gender']) && $_POST['tr_gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= (isset($_POST['tr_gender']) && $_POST['tr_gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>

                        <!-- Married -->
                        <div class="form-group">
                            <label for="tr_married">Are you married? <span class="required">*</span></label>
                            <select id="tr_married" name="tr_married" class="form-control" required style="cursor: pointer;">
                                <option value="" disabled <?= !isset($_POST['tr_married']) ? 'selected' : '' ?>>Select status</option>
                                <option value="Yes" <?= (isset($_POST['tr_married']) && $_POST['tr_married'] === 'Yes') ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= (isset($_POST['tr_married']) && $_POST['tr_married'] === 'No') ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        
                        <!-- DOB -->
                        <div class="form-group">
                            <label for="tr_dob">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="tr_dob" name="tr_dob" class="form-control" value="<?= isset($_POST['tr_dob']) ? htmlspecialchars($_POST['tr_dob']) : '' ?>" required onchange="calculateTRAge()">
                        </div>
                        
                        <!-- Age (auto-calculated - hidden from public view) -->
                        <div class="form-group" style="display: none;">
                            <label for="tr_age">Age</label>
                            <input type="number" id="tr_age" name="tr_age" class="form-control" placeholder="Automatically calculated" value="<?= isset($_POST['tr_age']) ? htmlspecialchars($_POST['tr_age']) : '' ?>" required readonly style="background-color: rgba(255, 255, 255, 0.05); cursor: not-allowed;">
                        </div>
                        
                        <!-- Prior Experience -->
                        <div class="form-group">
                            <label for="tr_experience">Prior JY Experience <span class="required">*</span></label>
                            <select id="tr_experience" name="tr_experience" class="form-control" required style="cursor: pointer;">
                                <option value="" disabled <?= !isset($_POST['tr_experience']) ? 'selected' : '' ?>>Prior Jesus Youth experience?</option>
                                <option value="Yes" <?= (isset($_POST['tr_experience']) && $_POST['tr_experience'] === 'Yes') ? 'selected' : '' ?>>Yes</option>
                                <option value="No" <?= (isset($_POST['tr_experience']) && $_POST['tr_experience'] === 'No') ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                        
                        <!-- Zone -->
                        <div class="form-group">
                            <label for="tr_zone">Zone <span class="required">*</span></label>
                            <select id="tr_zone" name="tr_zone" class="form-control" required style="cursor: pointer;">
                                <option value="" disabled <?= !isset($_POST['tr_zone']) ? 'selected' : '' ?>>Select Zone</option>
                                <?php foreach ($zones_list as $z): ?>
                                    <option value="<?= $z ?>" <?= (isset($_POST['tr_zone']) && $_POST['tr_zone'] === $z) ? 'selected' : '' ?>><?= $z ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Address -->
                    <div class="form-group" style="margin-top: 1.25rem;">
                        <label for="tr_address">Address <span class="required">*</span></label>
                        <textarea id="tr_address" name="tr_address" class="form-control" rows="3" placeholder="Enter full postal address" required><?= isset($_POST['tr_address']) ? htmlspecialchars($_POST['tr_address']) : '' ?></textarea>
                    </div>
                    
                    <!-- UPI Fee Section -->
                    <div class="section-title" style="color: var(--gold); font-size: 1.1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding-bottom: 0.5rem; margin-top: 2.5rem; margin-bottom: 1.5rem;">
                        <i class="fa-solid fa-indian-rupee-sign"></i> Registration Fee Payment
                    </div>
                    
                     <div class="form-group" style="max-width: 400px; margin: 0 auto 2rem auto;">
                        <label for="payment_type">Select Payment Mode <span class="required">*</span></label>
                        <select id="payment_type" name="payment_type" class="form-control" onchange="togglePaymentQR()" required style="cursor: pointer;">
                            <option value="Full" <?= (!isset($_POST['payment_type']) || $_POST['payment_type'] === 'Full') ? 'selected' : '' ?>>Full Payment (₹1500)</option>
                            <option value="Advance" <?= (isset($_POST['payment_type']) && $_POST['payment_type'] === 'Advance') ? 'selected' : '' ?>>Advance Payment (₹750)</option>
                            <option value="Pay Later" <?= (isset($_POST['payment_type']) && $_POST['payment_type'] === 'Pay Later') ? 'selected' : '' ?>>Pay Later</option>
                        </select>
                    </div>
                    
                    <h4 id="paymentQRHeader" style="font-size: 0.95rem; margin-bottom: 1rem; text-align: center; color: var(--text-dark);">Scan the QR Code to Pay</h4>
                    
                    <div class="payment-options-grid" style="grid-template-columns: 1fr; max-width: 400px; margin: 0 auto 2rem auto;">
                        <!-- Advance QR Code -->
                        <div id="advanceQRBox" style="display: none; background: rgba(79, 142, 247, 0.04); border: 1px solid rgba(79, 142, 247, 0.12); padding: 1.25rem; border-radius: 12px; text-align: center;">
                            <h5 style="color: var(--gold); font-weight: 700; margin-bottom: 0.75rem; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;">Advance Payment QR</h5>
                            <img class="qr-code-img" src="Assets/payment_qr_750.jpeg" alt="Advance Payment QR Code" style="border-radius: 8px; margin-bottom: 0.5rem; max-width: 150px; border: 1px solid rgba(255, 255, 255, 0.1); display: block; margin: 0 auto 0.5rem auto;">
                            <a href="Assets/payment_qr_750.jpeg" download="stepone_teacher_advance_qr_750.jpeg" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: var(--gold); background: rgba(212, 175, 55, 0.08); padding: 3px 10px; border-radius: 6px; border: 1px solid rgba(212, 175, 55, 0.15); text-decoration: none; font-weight: 600; margin-bottom: 0.5rem;"><i class="fa-solid fa-download"></i> Download QR</a>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Used for paying the advance registration fee of ₹750.</p>
                        </div>
                        
                        <!-- Full QR Code -->
                        <div id="fullQRBox" style="background: rgba(79, 142, 247, 0.04); border: 1px solid rgba(79, 142, 247, 0.12); padding: 1.25rem; border-radius: 12px; text-align: center;">
                            <h5 style="color: var(--gold); font-weight: 700; margin-bottom: 0.75rem; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;">Full Payment QR</h5>
                            <img class="qr-code-img" src="Assets/payment_qr_1500.jpeg" alt="Full Payment QR Code" style="border-radius: 8px; margin-bottom: 0.5rem; max-width: 150px; border: 1px solid rgba(255, 255, 255, 0.1); display: block; margin: 0 auto 0.5rem auto;">
                            <a href="Assets/payment_qr_1500.jpeg" download="stepone_teacher_full_qr_1500.jpeg" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; color: var(--gold); background: rgba(212, 175, 55, 0.08); padding: 3px 10px; border-radius: 6px; border: 1px solid rgba(212, 175, 55, 0.15); text-decoration: none; font-weight: 600; margin-bottom: 0.5rem;"><i class="fa-solid fa-download"></i> Download QR</a>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Used for paying the complete registration fee of ₹1500.</p>
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
                    
                    <div id="screenshotSection" class="form-group">
                        <label>Upload Payment Receipt / Proof <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal;">(Optional)</span></label>
                        <div class="file-upload-wrapper">
                            <div class="file-upload-box" id="uploadBox">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <div class="file-upload-text" id="uploadText">Click or Drag receipt here</div>
                                <div class="file-upload-hint">Accepted Formats: JPG, JPEG, PNG, PDF (Max 5MB)</div>
                                <input type="file" id="payment_screenshot" name="payment_screenshot" class="file-upload-input" accept=".jpg,.jpeg,.png,.pdf" onchange="handleFileSelected(this)">
                            </div>
                            <div class="selected-file-display" id="fileDisplay">
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
                    
                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 1.5rem; background: rgba(255, 255, 255, 0.05); padding: 1rem; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.1);">
                        <i class="fa-solid fa-key"></i> <strong>Note on Credentials:</strong> Password is automatically generated as your <code>first name (lowercase) + year of birth</code> (e.g. <code>john1990</code>). Use this password and your Email/Phone to login next time.
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem;"><i class="fa-solid fa-user-plus"></i> Create Teacher Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const tabBtnLogin = document.getElementById('tabBtnLogin');
    const tabBtnRegister = document.getElementById('tabBtnRegister');
    const loginFormSection = document.getElementById('loginFormSection');
    const registerFormSection = document.getElementById('registerFormSection');

    tabBtnLogin.addEventListener('click', () => {
        loginFormSection.style.display = 'block';
        registerFormSection.style.display = 'none';
        tabBtnLogin.style.color = 'var(--gold)';
        tabBtnLogin.style.borderBottom = '3px solid var(--gold)';
        tabBtnRegister.style.color = 'var(--text-muted)';
        tabBtnRegister.style.borderBottom = '3px solid transparent';
    });

    tabBtnRegister.addEventListener('click', () => {
        loginFormSection.style.display = 'none';
        registerFormSection.style.display = 'block';
        tabBtnRegister.style.color = 'var(--gold)';
        tabBtnRegister.style.borderBottom = '3px solid var(--gold)';
        tabBtnLogin.style.color = 'var(--text-muted)';
        tabBtnLogin.style.borderBottom = '3px solid transparent';
    });

    function calculateTRAge() {
        const dobInput = document.getElementById('tr_dob').value;
        if (!dobInput) return;
        
        const dob = new Date(dobInput);
        const today = new Date();
        
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        
        document.getElementById('tr_age').value = age >= 0 ? age : 0;
    }

    function handleFileSelected(input) {
        const fileDisplay = document.getElementById('fileDisplay');
        const fileNameSpan = document.getElementById('fileNameSpan');
        const uploadBox = document.getElementById('uploadBox');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeInMB = file.size / (1024 * 1024);
            
            if (sizeInMB > 5) {
                showToast("File size exceeds 5MB limit. Please compress and re-upload.", "error");
                input.value = '';
                fileDisplay.style.display = 'none';
                return;
            }
            
            fileNameSpan.textContent = file.name + ' (' + sizeInMB.toFixed(2) + ' MB)';
            fileDisplay.style.display = 'flex';
            uploadBox.style.borderColor = 'var(--success)';
        } else {
            fileDisplay.style.display = 'none';
            uploadBox.style.borderColor = 'rgba(255, 255, 255, 0.15)';
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

    // Drag and drop visual cues
    const uploadBox = document.getElementById('uploadBox');
    if (uploadBox) {
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadBox.addEventListener(eventName, e => {
                e.preventDefault();
                uploadBox.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadBox.addEventListener(eventName, e => {
                e.preventDefault();
                uploadBox.classList.remove('dragover');
            }, false);
        });
    }

    function validateTRForm() {
        const age = parseInt(document.getElementById('tr_age').value);
        
        if (isNaN(age) || age < 20) {
            showToast("You must be at least 20 years old to register.", "error");
            return false;
        }

        showLoading("Registering account...");
        return true;
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

    // If page is loaded with POST data that had validation errors, restore active tab state
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error && $active_tab === 'register'): ?>
        loginFormSection.style.display = 'none';
        registerFormSection.style.display = 'block';
        tabBtnRegister.style.color = 'var(--gold)';
        tabBtnRegister.style.borderBottom = '3px solid var(--gold)';
        tabBtnLogin.style.color = 'var(--text-muted)';
        tabBtnLogin.style.borderBottom = '3px solid transparent';
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', function() {
        const formId = 'trRegisterForm';
        const storageKey = 'stepone_teacher_form_data';
        const form = document.getElementById(formId);

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
                    if (data['tr_dob']) calculateTRAge();
                    if (data['payment_type']) togglePaymentQR();
                } catch (e) {
                    console.error('Error loading saved form data', e);
                }
            }

            if (document.getElementById('payment_type')) {
                togglePaymentQR();
            }

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
            
            form.addEventListener('submit', function() {
                localStorage.removeItem(storageKey);
            });
        }
    });
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
