<?php
require_once __DIR__ . '/header.php';

$participant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($participant_id <= 0) {
    header('Location: index.php');
    exit;
}

try {
    // Fetch participant details along with supervising teacher details
    $stmt = $pdo->prepare("
        SELECT p.*, t.name as teacher_name, t.tr_code 
        FROM participants p 
        JOIN teachers t ON p.tr_id = t.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$participant_id]);
    $p = $stmt->fetch();
    
    if (!$p) {
        $_SESSION['flash_error'] = "Registration record not found.";
        header('Location: index.php');
        exit;
    }

} catch (Exception $e) {
    die("Error retrieving registration details: " . $e->getMessage());
}
?>

<div class="bg-grid"></div>
<div class="glow-orb" style="top: 20%; left: 15%;"></div>
<div class="glow-orb" style="bottom: 15%; right: 10%; background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);"></div>

<div class="form-section">
    <div class="form-container" style="max-width: 650px;">
        
        <div class="glass-card success-screen">
            
            <div class="success-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745; border-color: #28a745;">
                <i class="fa-solid fa-check"></i>
            </div>
            
            <h1 class="title-sardin" style="color: var(--gold); font-size: 2.5rem;">Registration Submitted</h1>
            <p>Thank you, <strong><?= htmlspecialchars($p['full_name']) ?></strong>! Your registration for the Step One program has been successfully submitted.</p>
            
            <!-- Creative Progress Timeline Component -->
            <div class="progress-timeline">
                <div class="timeline-step completed">
                    <div class="timeline-icon">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div class="timeline-step-info">
                        <h4>Level 1</h4>
                        <p>Registration Submitted</p>
                    </div>
                </div>
                <div class="timeline-connector completed"></div>
                <div class="timeline-step pending">
                    <div class="timeline-icon" style="font-size: 1.25rem;">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="timeline-step-info">
                        <h4>Level 2</h4>
                        <p>See you at Venue</p>
                    </div>
                </div>
            </div>
            
            <div class="receipt-card" style="margin-top: 2rem;">
                <h3 style="font-size: 1.1rem; color: var(--gold); text-transform: uppercase; letter-spacing: 1.5px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 0.75rem; margin-bottom: 1rem;"><i class="fa-solid fa-receipt"></i> Registration Receipt</h3>
                
                <div class="receipt-row">
                    <span class="receipt-label">Participant ID</span>
                    <span class="receipt-value">#<?= sprintf("%04d", $p['id']) ?></span>
                </div>
                
                <div class="receipt-row">
                    <span class="receipt-label">Full Name</span>
                    <span class="receipt-value"><?= htmlspecialchars($p['full_name']) ?></span>
                </div>
                
                <div class="receipt-row">
                    <span class="receipt-label">Age / Gender</span>
                    <span class="receipt-value"><?= htmlspecialchars($p['age']) ?> Yrs / <?= htmlspecialchars($p['gender']) ?></span>
                </div>
                
                <div class="receipt-row">
                    <span class="receipt-label">Contact Phone</span>
                    <span class="receipt-value"><?= htmlspecialchars($p['phone']) ?></span>
                </div>
                
                <div class="receipt-row">
                    <span class="receipt-label">Parish / Diocese</span>
                    <span class="receipt-value"><?= htmlspecialchars($p['parish']) ?></span>
                </div>
                
                <div class="receipt-row">
                    <span class="receipt-label">Supervising Teacher</span>
                    <span class="receipt-value"><?= htmlspecialchars($p['teacher_name']) ?> (<?= htmlspecialchars($p['tr_code']) ?>)</span>
                </div>
                
                <div class="receipt-row">
                    <span class="receipt-label">School</span>
                    <span class="receipt-value"><?= htmlspecialchars($p['school'] ?? '') ?></span>
                </div>
                
                <div class="receipt-row">
                    <span class="receipt-label">Payment Mode</span>
                    <span class="receipt-value"><?= htmlspecialchars($p['payment_type'] ?? 'Full') ?></span>
                </div>
            </div>
            
            <div class="alert alert-success" style="background: rgba(79, 142, 247, 0.08); border-color: rgba(79, 142, 247, 0.2); color: var(--primary-dark); text-align: center; font-size: 0.95rem; line-height: 1.5; margin-bottom: 2rem; margin-top: 1.5rem;">
                <i class="fa-solid fa-circle-info"></i> Your supervisor will coordinate the travel and venue guidelines soon. Please save this screen or take a screenshot for reference.
            </div>
            
            <div style="display: flex; justify-content: center;">
                <a href="index.php" class="btn btn-primary"><i class="fa-solid fa-house"></i> Home</a>
            </div>
            
        </div>
        
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
