<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Fetch registration status
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'registration_open'");
    $stmt->execute();
    $reg_status = $stmt->fetchColumn();
    $is_reg_open = ($reg_status === '1');
} catch (Exception $e) {
    $is_reg_open = true; // Fallback if settings query fails
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step One - Step out of the Maze | Jesus Youth Kerala Teens Ministry</title>
    <!-- SEO Meta Tags -->
    <meta name="description" content="Step One - Step out of the Maze is a 4-day residential program for 9th standard students and teachers organized by Jesus Youth Kerala Teens Ministry. August 22 to August 25 at Viswa Jyothi Public School, Angamaly.">
    <meta name="keywords" content="Step One, Step out of the boat, Jesus Youth, Kerala Teens Ministry, Catholic Youth, Teens Camp, Viswa Jyothi Angamaly">
    <meta name="author" content="Jesus Youth Teens Ministry">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Favicon Logo -->
    <link rel="icon" type="image/png" href="Assets/favicon.png">
</head>
<body>

    <!-- Loading Indicator Overlay -->
    <div class="loading-overlay" id="globalLoadingOverlay">
        <div class="spinner"></div>
        <p style="color: var(--gold); font-weight: 600;" id="loadingText">Processing...</p>
    </div>

    <!-- Navigation Bar -->
    <nav class="navbar" id="mainNavbar">
        <div class="container nav-container">
            <a href="index.php" class="logo-link">
                <?php if (file_exists(__DIR__ . '/Assets/step one logo new 1 no bg.png')): ?>
                    <img class="nav-logo-img" src="Assets/step%20one%20logo%20new%201%20no%20bg.png" alt="Step One Logo">
                <?php endif; ?>
                <div class="logo-text">
                    Step One
                    <span class="logo-sub">JY Teens Kerala</span>
                </div>
            </a>
            
            <button class="nav-toggle" id="navToggleBtn" aria-label="Toggle Navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            
            <ul class="nav-links" id="navLinksMenu">
                <li class="nav-item"><a href="index.php#home">Home</a></li>
                <li class="nav-item"><a href="index.php#about">About</a></li>
                <li class="nav-item"><a href="index.php#highlights">Highlights</a></li>
                <!-- <li class="nav-item"><a href="index.php#contact">Contact</a></li> -->
                <li class="nav-item"><a href="index.php#media">Gallery</a></li>
                <li class="nav-item"><a href="index.php#intercession">Prayer Wall</a></li>
                
                <?php if (isset($_SESSION['admin_logged_in'])): ?>
                    <li class="nav-item"><a href="admin_dashboard.php" class="nav-btn"><i class="fa-solid fa-gauge"></i> Admin Panel</a></li>
                    <li class="nav-item"><a href="logout.php" style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                <?php elseif (isset($_SESSION['teacher_logged_in'])): ?>
                    <li class="nav-item"><a href="tr_dashboard.php" class="nav-btn"><i class="fa-solid fa-user-tie"></i> Teacher Panel</a></li>
                    <li class="nav-item"><a href="logout.php" style="color: var(--danger);"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a href="tr_portal.php"><i class="fa-solid fa-chalkboard-user"></i> Teachers Portal</a></li>
                    <li class="nav-item">
                        <?php if ($is_reg_open): ?>
                            <a href="register.php" class="nav-btn"><i class="fa-solid fa-user-plus" ></i> Register Now</a>
                        <?php else: ?>
                            <span class="badge badge-rejected" style="font-size: 0.9rem; padding: 0.5rem 1rem;"><i class="fa-solid fa-lock"></i> Registrations Closed</span>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
