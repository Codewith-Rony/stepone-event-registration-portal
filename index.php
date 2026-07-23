<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Handle AJAX Request for submission and getting updated totals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'submit') {
    header('Content-Type: application/json');
    
    // Read and validate inputs
    $holy_mass = isset($_POST['holy_mass']) ? max(0, intval($_POST['holy_mass'])) : 0;
    $our_father = isset($_POST['our_father']) ? max(0, intval($_POST['our_father'])) : 0;
    $hail_mary = isset($_POST['hail_mary']) ? max(0, intval($_POST['hail_mary'])) : 0;
    $memorare = isset($_POST['memorare']) ? max(0, intval($_POST['memorare'])) : 0;
    $creed = isset($_POST['creed']) ? max(0, intval($_POST['creed'])) : 0;
    $divine_mercy = isset($_POST['divine_mercy']) ? max(0, intval($_POST['divine_mercy'])) : 0;
    $rosary = isset($_POST['rosary']) ? max(0, intval($_POST['rosary'])) : 0;
    $adoration = isset($_POST['adoration']) ? max(0, intval($_POST['adoration'])) : 0;

    // Check if at least one count is greater than 0
    $total_new = $holy_mass + $our_father + $hail_mary + $memorare + $creed + $divine_mercy + $rosary + $adoration;
    if ($total_new <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please offer at least one prayer or minute of adoration.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intercessions (holy_mass, our_father, hail_mary, memorare, creed, divine_mercy, rosary, adoration) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$holy_mass, $our_father, $hail_mary, $memorare, $creed, $divine_mercy, $rosary, $adoration]);

        // Fetch new overall totals
        $totals_stmt = $pdo->query("SELECT 
            COALESCE(SUM(holy_mass), 0) as holy_mass,
            COALESCE(SUM(our_father), 0) as our_father,
            COALESCE(SUM(hail_mary), 0) as hail_mary,
            COALESCE(SUM(memorare), 0) as memorare,
            COALESCE(SUM(creed), 0) as creed,
            COALESCE(SUM(divine_mercy), 0) as divine_mercy,
            COALESCE(SUM(rosary), 0) as rosary,
            COALESCE(SUM(adoration), 0) as adoration
            FROM intercessions");
        $totals = $totals_stmt->fetch();

        // Calculate grand total of all intercessions
        $grand_total = $totals['holy_mass'] + $totals['our_father'] + $totals['hail_mary'] + $totals['memorare'] + $totals['creed'] + $totals['divine_mercy'] + $totals['rosary'];

        echo json_encode([
            'success' => true,
            'message' => 'Your spiritual intercession has been offered! Thank you. ❤️',
            'totals' => $totals,
            'grand_total' => $grand_total
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX Request to fetch live totals
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_totals') {
    header('Content-Type: application/json');
    try {
        $totals_stmt = $pdo->query("SELECT 
            COALESCE(SUM(holy_mass), 0) as holy_mass,
            COALESCE(SUM(our_father), 0) as our_father,
            COALESCE(SUM(hail_mary), 0) as hail_mary,
            COALESCE(SUM(memorare), 0) as memorare,
            COALESCE(SUM(creed), 0) as creed,
            COALESCE(SUM(divine_mercy), 0) as divine_mercy,
            COALESCE(SUM(rosary), 0) as rosary,
            COALESCE(SUM(adoration), 0) as adoration
            FROM intercessions");
        $totals = $totals_stmt->fetch();

        $grand_total = $totals['holy_mass'] + $totals['our_father'] + $totals['hail_mary'] + $totals['memorare'] + $totals['creed'] + $totals['divine_mercy'] + $totals['rosary'];

        echo json_encode([
            'success' => true,
            'totals' => $totals,
            'grand_total' => $grand_total
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

require_once __DIR__ . '/header.php';

// Fetch current totals for initial load
try {
    $totals_stmt = $pdo->query("SELECT 
        COALESCE(SUM(holy_mass), 0) as holy_mass,
        COALESCE(SUM(our_father), 0) as our_father,
        COALESCE(SUM(hail_mary), 0) as hail_mary,
        COALESCE(SUM(memorare), 0) as memorare,
        COALESCE(SUM(creed), 0) as creed,
        COALESCE(SUM(divine_mercy), 0) as divine_mercy,
        COALESCE(SUM(rosary), 0) as rosary,
        COALESCE(SUM(adoration), 0) as adoration
        FROM intercessions");
    $totals = $totals_stmt->fetch();
    $grand_total = $totals['holy_mass'] + $totals['our_father'] + $totals['hail_mary'] + $totals['memorare'] + $totals['creed'] + $totals['divine_mercy'] + $totals['rosary'];
} catch (Exception $e) {
    $totals = [
        'holy_mass' => 0, 'our_father' => 0, 'hail_mary' => 0, 'memorare' => 0,
        'creed' => 0, 'divine_mercy' => 0, 'rosary' => 0, 'adoration' => 0
    ];
    $grand_total = 0;
}

// Fetch Media for Gallery Section
$posters = [];
$videos = [];
try {
    $stmt = $pdo->query("SELECT * FROM posters ORDER BY sort_order ASC, created_at DESC");
    $posters = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC");
    $videos = $stmt->fetchAll();
} catch (Exception $e) {
    // Fail silently
}

// Helper to extract YouTube video ID
function getYoutubeId($url) {
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)|shorts)/|youtu\.be/)([^"&?/\s]{11})%i', $url, $match)) {
        return $match[1];
    }
    return false;
}

// Helper to extract Instagram Reel code
function getInstagramCode($url) {
    if (preg_match('%instagram\.com/(?:p|reel)/([^/?#&]+)%i', $url, $match)) {
        return $match[1];
    }
    return false;
}
?>

<!-- Hero Section -->
<header class="hero-section" id="home">
    <div class="bg-grid"></div>
    <div class="glow-orb" style="top: 10%; left: 5%;"></div>
    <div class="glow-orb"
        style="bottom: 15%; right: 5%; background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);">
    </div>

    <div class="container hero-container">
        <div class="hero-content">
            <div class="hero-badge">
                Jesus Youth Kerala Teens Ministry
            </div>
            <h1 class="hero-title text-gradient">
                Step One
                <span>Step out of the Maze</span>
            </h1>
            <p class="hero-tagline">
                An inspiring 4-day residential faith encounter program exclusively for <strong>9th standard students and
                    teachers</strong>. Take your step to encounter Jesus at a young age and carry His light wherever you
                go.
            </p>

            <div class="hero-actions">
                <?php if ($is_reg_open): ?>
                    <a href="register.php" class="btn btn-gold"><i class="fa-solid fa-pen-to-square"></i> Participant
                        Registration</a>
                <?php else: ?>
                    <button class="btn btn-gold" style="opacity: 0.65; cursor: not-allowed;" disabled><i
                            class="fa-solid fa-lock"></i> Registrations Closed</button>
                <?php endif; ?>
                <a href="tr_portal.php" class="btn btn-outline"><i class="fa-solid fa-chalkboard-user"></i> Teachers
                 Portal</a>
            </div>

            <div class="hero-details-row">
                <div class="hero-detail-item">
                    <div class="hero-detail-icon"><i class="fa-regular fa-calendar-days"></i></div>
                    <div class="hero-detail-info">
                        <h4>Dates</h4>
                        <p>August 22 - 25</p>
                    </div>
                </div>

                <div class="hero-detail-item">
                    <div class="hero-detail-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <div class="hero-detail-info">
                        <h4>Venue</h4>
                        <p>Viswa Jyothi Public School, Angamaly</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-blob"></div>
            <div class="hero-img-container">
                <?php if (file_exists(__DIR__ . '/Assets/Object.png')): ?>
                    <img class="hero-image" src="Assets/Object.png" alt="Decorative Christian Graphic" draggable="false">
                <?php else: ?>
                    <!-- Fallback placeholder graphic -->
                    <i class="fa-solid fa-fire-flame-curved"
                        style="font-size: 15rem; color: rgba(212, 175, 55, 0.4); filter: drop-shadow(0 0 30px rgba(212, 175, 55, 0.2));"></i>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- About Section -->
<section class="section" id="about" style="background: rgba(255, 255, 255, 0.01);">
    <div class="glow-orb" style="top: 50%; left: 50%; transform: translate(-50%, -50%);"></div>
    <div class="container">
        <div class="about-grid">
            <div class="about-visual" style="text-align: center; position: relative;">
                <?php if (file_exists(__DIR__ . '/Assets/step one logo new 1 no bg.png')): ?>
                    <img src="Assets/step%20one%20logo%20new%201%20no%20bg.png" alt="Step One Large Logo"
                        style="max-width: 80%; height: auto; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.4));">
                <?php else: ?>
                    <div class="title-groovezilla" style="font-size: 4rem; color: var(--gold);">Step One</div>
                <?php endif; ?>
            </div>

            <div class="about-info">
                <span class="section-subtitle">About The Program</span>
                <h2 class="section-title">Encountering Christ at the <span>Step One</span> </h2>
                <p class="about-text" style="margin-top: 1rem;">
                    Step One is a flagship initiative of the <strong>Jesus Youth Kerala Teens Ministry</strong>.
                    Designed especially for 9th-grade students and their mentoring teachers, this 4-day residential
                    experience provides a dynamic atmosphere to connect deeply with Christian virtues and personal
                    faith.
                </p>
                <p class="about-text">
                    Through structured interaction, sacramental encounters, and joyful fellowship, participants are
                    guided to experience the living presence of Jesus and grow confident in proclaiming His gospel in
                    their schools, parishes, and communities.
                </p>

                <ul class="highlight-list">
                    <li class="highlight-item"><i class="fa-solid fa-circle-check"></i> Exclusively for 9th Standard
                        Students</li>
                    <li class="highlight-item"><i class="fa-solid fa-circle-check"></i> Special Tracks for Accompanying
                        Teachers</li>
                    <li class="highlight-item"><i class="fa-solid fa-circle-check"></i> Interactive, Youthful &
                        Faith-Centered Environment</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Objectives Section -->
<section class="section" id="objectives">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Our Vision</span>
            <h2 class="section-title">Core <span>Objectives</span></h2>
        </div>

        <div class="card-grid">
            <!-- Objective 1 -->
            <div class="glass-card">
                <div class="card-icon"><i class="fa-solid fa-heart-pulse"></i></div>
                <h3 class="card-title">Personal Encounter</h3>
                <p class="card-desc">Providing teens and teachers a dedicated space to personally experience God's love
                    and begin a meaningful relationship with Jesus Christ.</p>
            </div>

            <!-- Objective 2 -->
            <div class="glass-card">
                <div class="card-icon"><i class="fa-solid fa-users"></i></div>
                <h3 class="card-title">Christian Fellowship</h3>
                <p class="card-desc">Fostering a healthy and energetic community of young believers where they can share
                    faith, ask questions, and build lasting friendships.</p>
            </div>

            <!-- Objective 3 -->
            <div class="glass-card">
                <div class="card-icon"><i class="fa-solid fa-bullhorn"></i></div>
                <h3 class="card-title">Proclaim Him</h3>
                <p class="card-desc">Equipping teens and teachers with tools, confidence, and testimony to boldly
                    represent and share Christ in their daily environments.</p>
            </div>
        </div>
    </div>
</section>

<!-- Highlights Section -->
<section class="section" id="highlights" style="background: rgba(255, 255, 255, 0.01);">
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">What to Expect</span>
            <h2 class="section-title">Camp <span>Highlights</span></h2>
        </div>

        <div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
            <!-- Highlight 1 -->
            <div class="glass-card" style="padding: 2rem;">
                <div class="card-icon" style="width: 50px; height: 50px; font-size: 1.4rem;"><i
                        class="fa-solid fa-guitar"></i></div>
                <h3 class="card-title" style="font-size: 1.2rem;">Praise & Worship</h3>
                <p class="card-desc" style="font-size: 0.9rem;">Immersive musical adoration and praise led by the Jesus
                    Youth Teens band to lift our hearts and minds.</p>
            </div>

            <!-- Highlight 2 -->
            <div class="glass-card" style="padding: 2rem;">
                <div class="card-icon" style="width: 50px; height: 50px; font-size: 1.4rem;"><i
                        class="fa-solid fa-comments"></i></div>
                <h3 class="card-title" style="font-size: 1.2rem;">Inspiring Talks</h3>
                <p class="card-desc" style="font-size: 0.9rem;">Dynamic presentations and life testimonies from
                    experienced leaders on navigating teens' lives with Christian values.</p>
            </div>

            <!-- Highlight 3 -->
            <div class="glass-card" style="padding: 2rem;">
                <div class="card-icon" style="width: 50px; height: 50px; font-size: 1.4rem;"><i
                        class="fa-solid fa-fire"></i></div>
                <h3 class="card-title" style="font-size: 1.2rem;">Holy Sacraments</h3>
                <p class="card-desc" style="font-size: 0.9rem;">Daily celebration of Holy Mass, Eucharistic Adoration,
                    and opportunities for sacramental confession.</p>
            </div>

            <!-- Highlight 4 -->
            <div class="glass-card" style="padding: 2rem;">
                <div class="card-icon" style="width: 50px; height: 50px; font-size: 1.4rem;"><i
                        class="fa-solid fa-gamepad"></i></div>
                <h3 class="card-title" style="font-size: 1.2rem;">Games & Activities</h3>
                <p class="card-desc" style="font-size: 0.9rem;">Fun group outdoor activities, bonding exercises, and
                    creative challenges that build team spirit.</p>
            </div>
        </div>
    </div>
</section>

<!-- Media Gallery Section -->
<section class="section" id="media" style="background: rgba(255, 255, 255, 0.01);">
    <div class="glow-orb" style="top: 20%; right: 5%; background: radial-gradient(circle, rgba(212, 175, 55, 0.08) 0%, transparent 70%);"></div>
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Catch the Spirit</span>
            <h2 class="section-title">Media <span>Gallery</span></h2>
            <p style="max-width: 600px; margin: 1rem auto 0 auto; color: var(--text-light); font-size: 1.05rem;">
                Explore our official event posters and inspirational video highlights.
            </p>
        </div>

        <div class="gallery-tabs" style="display: flex; justify-content: center; gap: 1rem; margin-bottom: 2.5rem;">
            <button class="gallery-tab-btn active" id="mainTabPosters" onclick="switchHomepageMediaTab('Posters')">
                <i class="fa-solid fa-images"></i> Posters
            </button>
            <button class="gallery-tab-btn" id="mainTabVideos" onclick="switchHomepageMediaTab('Videos')">
                <i class="fa-solid fa-circle-play"></i> Videos
            </button>
        </div>

        <!-- Posters Subsection -->
        <div id="homepageMediaPosters">
            <?php if (empty($posters)): ?>
                <div style="text-align: center; padding: 4rem 0; color: #bfaed6;">
                    <i class="fa-regular fa-image" style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.4; color: var(--gold);"></i>
                    <h3>No Posters Uploaded</h3>
                    <p style="margin-top: 0.5rem; font-size: 0.95rem;">Keep an eye out for updates!</p>
                </div>
            <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($posters as $p): ?>
                        <div class="poster-card">
                            <div class="poster-img-wrapper" onclick="openPosterLightbox('uploads/posters/<?= htmlspecialchars($p['filename']) ?>', '<?= htmlspecialchars(addslashes($p['title'])) ?>')">
                                <img class="poster-img" src="uploads/posters/<?= htmlspecialchars($p['filename']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                                <div class="poster-overlay">
                                    <button class="btn-preview"><i class="fa-solid fa-magnifying-glass-plus"></i> View Larger</button>
                                </div>
                            </div>
                            <div class="poster-info">
                                <h4 class="poster-title"><?= htmlspecialchars($p['title']) ?></h4>
                                <div class="poster-meta">
                                    <span class="poster-date"><i class="fa-regular fa-calendar"></i> <?= date("M d, Y", strtotime($p['created_at'])) ?></span>
                                    <a class="btn-download" href="uploads/posters/<?= htmlspecialchars($p['filename']) ?>" download="<?= htmlspecialchars($p['title']) ?>"><i class="fa-solid fa-download"></i> Download</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Videos Subsection -->
        <div id="homepageMediaVideos" style="display: none;">
            <?php if (empty($videos)): ?>
                <div style="text-align: center; padding: 4rem 0; color: #bfaed6;">
                    <i class="fa-solid fa-video-slash" style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.4; color: var(--gold);"></i>
                    <h3>No Videos Available</h3>
                    <p style="margin-top: 0.5rem; font-size: 0.95rem;">Camp video highlights will appear here.</p>
                </div>
            <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($videos as $v): 
                        $yt_id = getYoutubeId($v['url']);
                        $ig_code = getInstagramCode($v['url']);
                        $thumb_url = '';
                        if ($v['platform'] === 'youtube' && $yt_id) {
                            $thumb_url = "https://img.youtube.com/vi/{$yt_id}/mqdefault.jpg";
                        }
                    ?>
                        <div class="video-card">
                            <?php if ($v['platform'] === 'youtube' && $yt_id): ?>
                                <div class="video-thumbnail-wrapper" onclick="playVideo('youtube', '<?= $yt_id ?>', '<?= htmlspecialchars(addslashes($v['title'])) ?>')">
                                    <span class="platform-badge youtube"><i class="fa-brands fa-youtube"></i> YouTube</span>
                                    <img class="video-thumbnail" src="<?= $thumb_url ?>" alt="<?= htmlspecialchars($v['title']) ?>" loading="lazy">
                                    <div class="video-play-btn"><i class="fa-solid fa-play"></i></div>
                                </div>
                            <?php elseif ($v['platform'] === 'instagram' && $ig_code): ?>
                                <div class="video-thumbnail-wrapper" onclick="playVideo('instagram', '<?= $ig_code ?>', '<?= htmlspecialchars(addslashes($v['title'])) ?>')">
                                    <span class="platform-badge instagram"><i class="fa-brands fa-instagram"></i> Reel</span>
                                    <div class="video-thumbnail" style="background: linear-gradient(135deg, #8a2387 0%, #e94057 50%, #f27121 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%; position: absolute;">
                                        <i class="fa-brands fa-instagram" style="font-size: 2.5rem; color: rgba(255, 255, 255, 0.4); margin-bottom: 0.5rem;"></i>
                                        <span style="font-size: 0.7rem; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 1px;">Watch Instagram Reel</span>
                                    </div>
                                    <div class="video-play-btn"><i class="fa-solid fa-play"></i></div>
                                </div>
                            <?php else: ?>
                                <div class="video-thumbnail-wrapper" onclick="window.open('<?= htmlspecialchars($v['url']) ?>', '_blank')">
                                    <span class="platform-badge" style="background: var(--primary-light);"><i class="fa-solid fa-link"></i> Link</span>
                                    <div class="video-thumbnail" style="background: linear-gradient(135deg, #1d0933 0%, #3a1168 100%); display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; position: absolute;">
                                        <i class="fa-solid fa-link" style="font-size: 2.5rem; color: rgba(255,255,255,0.2);"></i>
                                    </div>
                                    <div class="video-play-btn"><i class="fa-solid fa-external-link"></i></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="video-info">
                                <h4 class="video-title"><?= htmlspecialchars($v['title']) ?></h4>
                                <div class="video-meta">
                                    <span class="poster-date"><i class="fa-regular fa-calendar"></i> Added: <?= date("M d, Y", strtotime($v['created_at'])) ?></span>
                                    <?php if ($v['platform'] === 'youtube'): ?>
                                        <i class="fa-brands fa-youtube video-platform-icon youtube"></i>
                                    <?php else: ?>
                                        <i class="fa-brands fa-instagram video-platform-icon instagram"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Intercession Section -->
<section class="section" id="intercession">
    <div class="glow-orb" style="bottom: 10%; left: 10%;"></div>
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Spiritual Bouquet</span>
            <h2 class="section-title">Spiritual <span>Intercession Box</span></h2>
            <p style="max-width: 700px; margin: 1rem auto 0 auto; color: var(--text-light); font-size: 1.05rem;">
                Support the upcoming <strong>Step One</strong> Program. Submit your spiritual gifts anonymously to join our live collective prayer wall.
            </p>
        </div>

        <div class="intercession-grid">
            <!-- Left Column: Counter Offering Form -->
            <div class="intercession-form-col">
                <div class="glass-card">
                    <h3 class="card-title text-gold" style="border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                        <i class="fa-solid fa-heart-circle-plus"></i> Offer Spiritual Gifts
                    </h3>
                    
                    <form id="intercessionForm" onsubmit="submitIntercession(event)">
                        <div class="counters-list">
                            <!-- Mass Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-solid fa-church"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Holy Mass</span>
                                        <span class="counter-sub">Offered for camp intentions</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('holy_mass', 'minus')"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_holy_mass" name="holy_mass" value="0" min="0" readonly>
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('holy_mass', 'plus')"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Our Father Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-solid fa-hands-praying"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Our Father</span>
                                        <span class="counter-sub">Lord's Prayer offered</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('our_father', 'minus')"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_our_father" name="our_father" value="0" min="0" readonly>
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('our_father', 'plus')"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Hail Mary Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-solid fa-person-praying"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Hail Mary</span>
                                        <span class="counter-sub">Angelic Salutation</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('hail_mary', 'minus')"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_hail_mary" name="hail_mary" value="0" min="0" readonly>
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('hail_mary', 'plus')"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Memorare Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-solid fa-shield-heart"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Memorare</span>
                                        <span class="counter-sub">To the Blessed Mother</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('memorare', 'minus')"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_memorare" name="memorare" value="0" min="0" readonly>
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('memorare', 'plus')"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Creed Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-solid fa-book-open"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Creed</span>
                                        <span class="counter-sub">Profession of Faith</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('creed', 'minus')"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_creed" name="creed" value="0" min="0" readonly>
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('creed', 'plus')"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Divine Mercy Chaplet Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-solid fa-heart-pulse"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Divine Mercy Rosary</span>
                                        <span class="counter-sub">Chaplet of Divine Mercy</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('divine_mercy', 'minus')"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_divine_mercy" name="divine_mercy" value="0" min="0" readonly>
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('divine_mercy', 'plus')"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Rosary Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-solid fa-circle-nodes"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Rosary</span>
                                        <span class="counter-sub">Holy Rosaries prayed</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('rosary', 'minus')"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_rosary" name="rosary" value="0" min="0" readonly>
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('rosary', 'plus')"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Adoration Row -->
                            <div class="counter-row">
                                <div class="counter-info-col">
                                    <div class="counter-icon"><i class="fa-regular fa-clock"></i></div>
                                    <div class="counter-details">
                                        <span class="counter-name">Adoration (minutes)</span>
                                        <span class="counter-sub">Time in Eucharistic presence</span>
                                    </div>
                                </div>
                                <div class="counter-control-col">
                                    <button type="button" class="ctrl-btn btn-minus" onclick="adjustIntercessionCounter('adoration', 'minus', 5)"><i class="fa-solid fa-minus"></i></button>
                                    <input type="number" id="input_adoration" name="adoration" class="editable" value="0" min="0" onchange="validateAdorationInput(this)">
                                    <button type="button" class="ctrl-btn btn-plus" onclick="adjustIntercessionCounter('adoration', 'plus', 5)"><i class="fa-solid fa-plus"></i></button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" id="btnSubmitOffer" class="btn btn-gold" style="width: 100%; margin-top: 2.2rem; font-size: 1.1rem; padding: 1.1rem; border-radius: 50px;">
                            Offer Intercession ❤️
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Live Collective Totals -->
            <div class="intercession-totals-col">
                <!-- Grand Total Card -->
                <div class="grand-total-card glass-card">
                    <div class="gt-content">
                        <span class="gt-label">Spiritual Bouquet Grand Total</span>
                        <h2 class="gt-number num-font text-gradient" id="total_grand"><?= number_format($grand_total) ?></h2>
                        <span class="gt-sub">Total intercessions offered collectively</span>
                    </div>
                    <div class="gt-icon">
                        <i class="fa-solid fa-heart" style="color: var(--gold); font-size: 3rem; filter: drop-shadow(0 0 10px rgba(212,175,55,0.4));"></i>
                    </div>
                </div>

                <!-- Live Individual Totals -->
                <div class="totals-grid">
                    <!-- Holy Mass -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Holy Mass</h4>
                            <div class="stat-number num-font" id="total_holy_mass"><?= number_format($totals['holy_mass']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-church"></i></div>
                    </div>

                    <!-- Our Father -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Our Father</h4>
                            <div class="stat-number num-font" id="total_our_father"><?= number_format($totals['our_father']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-hands-praying"></i></div>
                    </div>

                    <!-- Hail Mary -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Hail Mary</h4>
                            <div class="stat-number num-font" id="total_hail_mary"><?= number_format($totals['hail_mary']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-person-praying"></i></div>
                    </div>

                    <!-- Memorare -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Memorare</h4>
                            <div class="stat-number num-font" id="total_memorare"><?= number_format($totals['memorare']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-shield-heart"></i></div>
                    </div>

                    <!-- Creed -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Creed</h4>
                            <div class="stat-number num-font" id="total_creed"><?= number_format($totals['creed']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-book-open"></i></div>
                    </div>

                    <!-- Divine Mercy -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Divine Mercy</h4>
                            <div class="stat-number num-font" id="total_divine_mercy"><?= number_format($totals['divine_mercy']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-heart-pulse"></i></div>
                    </div>

                    <!-- Rosary -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Rosary</h4>
                            <div class="stat-number num-font" id="total_rosary"><?= number_format($totals['rosary']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-circle-nodes"></i></div>
                    </div>

                    <!-- Adoration minutes -->
                    <div class="stat-card">
                        <div class="stat-info">
                            <h4>Adoration Minutes</h4>
                            <div class="stat-number num-font" id="total_adoration"><?= number_format($totals['adoration']) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fa-regular fa-clock"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- LIGHTBOX / MEDIA PLAYBACK MODAL FOR HOMEPAGE -->
<div class="lightbox-modal" id="homepageLightbox" onclick="closeLightboxOnBg(event)">
    <div class="lightbox-content">
        <button class="lightbox-close" onclick="closeHomepageLightbox()">&times;</button>
        <img class="lightbox-img" id="lightboxImage" style="display: none;" src="" alt="">
        <div class="video-player-container" id="lightboxVideoContainer" style="display: none;">
            <iframe class="video-player-iframe" id="lightboxIframe" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
        </div>
        <h4 class="lightbox-title" id="lightboxMediaTitle"></h4>
    </div>
</div>

<!-- Contact Section -->
<section class="section" id="contact">
    <div class="glow-orb" style="bottom: 5%; left: 5%;"></div>
    <div class="container">
        <div class="section-header">
            <span class="section-subtitle">Get in Touch</span>
            <h2 class="section-title">Contact <span>Us</span></h2>
        </div>

        <div class="contact-grid" style="grid-template-columns: 1fr; max-width: 600px; margin: 0 auto;">
            <div class="contact-details-area">
                <h3 style="font-size: 1.8rem; margin-bottom: 1.5rem; color: var(--gold); text-align: center;">Have Questions?</h3>
                <p style="color: var(--text-light); margin-bottom: 2rem; text-align: center;">Our coordinators are here to assist you with
                    registration, venue navigation, and program queries.</p>

                <div class="contact-info-list">
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="contact-info-text">
                            <h4>Venue Address</h4>
                            <p>Viswa Jyothi Public School, Angamaly, Ernakulam, Kerala</p>
                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-info-icon"><i class="fa-solid fa-phone"></i></div>
                        <div class="contact-info-text">
                            <h4>Helpline Phone</h4>
                            <p><a href="tel:+91953541140">+91 953541140 - Dafna D'cruz(General Coordinator)</a></p>
                            <p><a href="tel:+916282928325">+91 6282928325 - Mary Ann (Mobilization Coordinator)</a></p>
                            <p><a href="tel:+916282928325">+91 7907894676 - Akhil Tomy (Teens Team Coordinator)</a></p>

                        </div>
                    </div>

                    <div class="contact-info-item">
                        <div class="contact-info-icon"><i class="fa-solid fa-envelope"></i></div>
                        <div class="contact-info-text">
                            <h4>Email Queries</h4>
                            <p><a
                                     href="mailto:keralateensministry@gmail.com">keralateensministry@gmail.com</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Additional Custom Embedded Styles for Media & Intercession -->
<style>
.gallery-tabs {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 2.5rem;
}
.gallery-tab-btn {
    padding: 0.8rem 2.2rem;
    font-family: 'Outfit', sans-serif;
    font-size: 1.05rem;
    font-weight: 600;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.gallery-tab-btn.active {
    background: linear-gradient(90deg, var(--gold), #ecd06c);
    color: var(--text-dark);
    box-shadow: 0 5px 20px rgba(212, 175, 55, 0.35);
    border: none;
}
.gallery-tab-btn:not(.active) {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--white);
    backdrop-filter: blur(5px);
}
.gallery-tab-btn:not(.active):hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
}
.poster-card, .video-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    display: flex;
    flex-direction: column;
}
.poster-card:hover, .video-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
    border-color: rgba(212, 175, 55, 0.25);
}
.poster-img-wrapper {
    position: relative;
    padding-top: 125%;
    overflow: hidden;
    cursor: pointer;
    background: rgba(0, 0, 0, 0.2);
}
.poster-img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
}
.poster-card:hover .poster-img {
    transform: scale(1.08);
}
.poster-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to top, rgba(16, 6, 28, 0.9) 0%, rgba(16, 6, 28, 0.2) 60%, transparent 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 1.5rem;
}
.poster-card:hover .poster-overlay {
    opacity: 1;
}
.btn-preview {
    background: linear-gradient(90deg, var(--gold), #ecd06c);
    color: var(--text-dark);
    border: none;
    padding: 0.7rem 1.5rem;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.25);
    transition: transform 0.3s ease;
}
.btn-preview:hover {
    transform: scale(1.05);
}
.poster-info, .video-info {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}
.poster-title, .video-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--white);
    margin-bottom: 0.5rem;
    line-height: 1.3;
}
.poster-meta, .video-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}
.poster-date {
    color: #9d8db3;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.btn-download {
    color: var(--gold);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    transition: color 0.3s ease;
}
.btn-download:hover {
    color: #fff;
}
.video-thumbnail-wrapper {
    position: relative;
    padding-top: 56.25%;
    overflow: hidden;
    cursor: pointer;
    background: #000;
}
.video-thumbnail {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1), filter 0.3s ease;
}
.video-card:hover .video-thumbnail {
    transform: scale(1.06);
    filter: brightness(0.7);
}
.video-play-btn {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.9);
    width: 60px;
    height: 60px;
    background: radial-gradient(circle, var(--gold) 0%, #b8962b 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-dark);
    font-size: 1.4rem;
    box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 2;
}
.video-card:hover .video-play-btn {
    transform: translate(-50%, -50%) scale(1.1);
    box-shadow: 0 10px 25px rgba(212, 175, 55, 0.6);
}
.platform-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    padding: 0.4rem 0.8rem;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    z-index: 2;
    text-transform: uppercase;
}
.platform-badge.youtube {
    background: #ef4444;
    color: var(--white);
}
.platform-badge.instagram {
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
    color: var(--white);
}
.video-platform-icon {
    font-size: 1.2rem;
}
.video-platform-icon.youtube {
    color: #ef4444;
}
.video-platform-icon.instagram {
    color: #ec4899;
}

/* Lightbox Modal */
.lightbox-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(16, 6, 28, 0.95);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 2rem;
    animation: fadeIn 0.3s ease;
}
.lightbox-content {
    position: relative;
    max-width: 900px;
    width: 100%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.lightbox-img {
    max-width: 100%;
    max-height: 75vh;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.lightbox-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--white);
    margin-top: 1.5rem;
    text-align: center;
}
.lightbox-close {
    position: absolute;
    top: -2.5rem;
    right: 0;
    background: none;
    border: none;
    color: var(--white);
    font-size: 2.2rem;
    cursor: pointer;
    transition: transform 0.2s;
}
.lightbox-close:hover {
    transform: scale(1.1);
    color: var(--gold);
}
.video-player-container {
    width: 100%;
    aspect-ratio: 16/9;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.video-player-iframe {
    width: 100%;
    height: 100%;
    border: none;
}

/* Intercession styling */
.intercession-grid {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 3rem;
    align-items: start;
    margin-top: 2rem;
}
@media (max-width: 992px) {
    .intercession-grid {
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }
}
.counters-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.counter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 14px;
    transition: all 0.3s ease;
}
.counter-row:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(212, 175, 55, 0.2);
}
.counter-info-col {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.counter-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    background: rgba(138, 70, 255, 0.15);
    border: 1px solid rgba(138, 70, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gold);
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}
.counter-row:hover .counter-icon {
    background: var(--gold);
    color: #120b24;
    border-color: var(--gold);
}
.counter-details {
    display: flex;
    flex-direction: column;
}
.counter-name {
    font-size: 1rem;
    font-weight: 700;
    color: var(--white);
}
.counter-sub {
    font-size: 0.75rem;
    color: var(--text-muted);
}
.counter-control-col {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.ctrl-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-light);
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}
.ctrl-btn:hover {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(212, 175, 55, 0.1);
    transform: scale(1.1);
}
.ctrl-btn:active {
    transform: scale(0.95);
}
.counter-control-col input {
    width: 50px;
    border: none;
    background: transparent;
    text-align: center;
    color: var(--white);
    font-size: 1.15rem;
    font-weight: 700;
    outline: none;
}
.counter-control-col input.editable {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 0.25rem 0.5rem;
    width: 80px;
    text-align: center;
}
.counter-control-col input.editable:focus {
    border-color: var(--gold);
    background: rgba(255, 255, 255, 0.1);
}
.grand-total-card {
    background: linear-gradient(135deg, rgba(92, 36, 179, 0.15) 0%, rgba(212, 175, 55, 0.05) 100%);
    border: 1px solid rgba(212, 175, 55, 0.25);
    padding: 2.2rem;
    border-radius: 24px;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 30px rgba(92, 36, 179, 0.15);
    position: relative;
    overflow: hidden;
}
.gt-content {
    display: flex;
    flex-direction: column;
    z-index: 1;
}
.gt-label {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
}
.gt-number {
    font-size: 3.5rem;
    font-weight: 800;
    line-height: 1.1;
    margin: 0.5rem 0;
    text-shadow: 0 0 15px rgba(212, 175, 55, 0.3);
}
.gt-sub {
    font-size: 0.85rem;
    color: var(--text-light);
}
.gt-icon {
    z-index: 1;
}
.totals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.25rem;
}
.totals-grid .stat-card {
    padding: 1.25rem 1.5rem;
    border-radius: 14px;
}
.totals-grid .stat-info .stat-number {
    font-size: 1.8rem;
}

@media (max-width: 768px) {
    .gallery-grid {
        display: flex !important;
        overflow-x: auto !important;
        scroll-snap-type: x mandatory;
        gap: 1.5rem;
        padding-bottom: 1.5rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }

    .gallery-grid::-webkit-scrollbar {
        height: 6px;
    }

    .gallery-grid::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 10px;
    }

    .gallery-grid::-webkit-scrollbar-thumb {
        background: rgba(212, 175, 55, 0.4);
        border-radius: 10px;
    }

    .gallery-grid::-webkit-scrollbar-thumb:hover {
        background: rgba(212, 175, 55, 0.6);
    }

    .gallery-grid .poster-card, 
    .gallery-grid .video-card {
        flex: 0 0 280px !important;
        scroll-snap-align: start;
        margin-bottom: 0 !important;
    }
}
</style>

<!-- Unified Script for Media Gallery and Intercession -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ----------------------------------------------------
    // Faith Particle System
    // ----------------------------------------------------
    const particlesArray = [];
    const colors = ['#d4af37', '#ab7eff', '#ff477e', '#ffffff', '#ffb703'];
    const icons = [
        'fa-solid fa-heart',
        'fa-regular fa-heart',
        'fa-solid fa-cross',
        'fa-solid fa-dove',
        'fa-solid fa-fire-flame-curved',
        'fa-solid fa-star'
    ];

    window.createFaithParticle = function(x, y) {
        const particleEl = document.createElement('div');
        particleEl.className = 'faith-particle';
        
        const iconClass = icons[Math.floor(Math.random() * icons.length)];
        const icon = document.createElement('i');
        icon.className = iconClass;
        particleEl.appendChild(icon);

        const color = colors[Math.floor(Math.random() * colors.length)];
        const size = (0.8 + Math.random() * 1.3).toFixed(2);
        
        particleEl.style.color = color;
        particleEl.style.fontSize = `${size}rem`;
        particleEl.style.filter = `drop-shadow(0 0 8px ${color}aa)`;
        particleEl.style.left = '0px';
        particleEl.style.top = '0px';
        particleEl.style.transform = `translate3d(${x}px, ${y}px, 0)`;

        document.body.appendChild(particleEl);

        const angle = -Math.PI / 2 + (Math.random() - 0.5) * (Math.PI / 1.5);
        const speed = 4 + Math.random() * 8;
        
        const vx = Math.cos(angle) * speed;
        const vy = Math.sin(angle) * speed - 2.5;

        particlesArray.push({
            element: particleEl,
            x: x,
            y: y,
            vx: vx,
            vy: vy,
            gravity: 0.22 + Math.random() * 0.12,
            rotation: Math.random() * 360,
            vRotation: -8 + Math.random() * 16,
            scale: 1,
            decay: 0.007 + Math.random() * 0.007,
            opacity: 1,
            opacityDecay: 0.009 + Math.random() * 0.009
        });

        if (particlesArray.length === 1) {
            requestAnimationFrame(updateParticlesLoop);
        }
    };

    function updateParticlesLoop() {
        for (let i = particlesArray.length - 1; i >= 0; i--) {
            const p = particlesArray[i];
            
            p.x += p.vx;
            p.y += p.vy;
            p.vy += p.gravity;
            p.rotation += p.vRotation;
            p.scale -= p.decay;
            p.opacity -= p.opacityDecay;

            if (p.opacity <= 0 || p.scale <= 0) {
                p.element.remove();
                particlesArray.splice(i, 1);
            } else {
                p.element.style.transform = `translate3d(${p.x}px, ${p.y}px, 0) rotate(${p.rotation}deg) scale(${p.scale})`;
                p.element.style.opacity = p.opacity;
            }
        }

        if (particlesArray.length > 0) {
            requestAnimationFrame(updateParticlesLoop);
        }
    }

    // Bind click event to Hero Visual container
    const heroVisualArea = document.querySelector('.hero-visual');
    if (heroVisualArea) {
        heroVisualArea.setAttribute('title', 'Click to receive a blessing!');
        heroVisualArea.addEventListener('click', function(e) {
            const heroImg = heroVisualArea.querySelector('.hero-image') || heroVisualArea.querySelector('.fa-fire-flame-curved');
            if (heroImg) {
                heroImg.classList.remove('pop-active');
                void heroImg.offsetWidth;
                heroImg.classList.add('pop-active');
            }

            const rect = heroVisualArea.getBoundingClientRect();
            const clickX = e.clientX || (rect.left + rect.width / 2);
            const clickY = e.clientY || (rect.top + rect.height / 2);

            const particleCount = 20 + Math.floor(Math.random() * 15);
            for (let i = 0; i < particleCount; i++) {
                window.createFaithParticle(clickX, clickY);
            }
        });
    }

    // ----------------------------------------------------
    // Media Gallery Switch Tab
    // ----------------------------------------------------
    window.switchHomepageMediaTab = function(tab) {
        const postersBtn = document.getElementById('mainTabPosters');
        const videosBtn = document.getElementById('mainTabVideos');
        const postersSub = document.getElementById('homepageMediaPosters');
        const videosSub = document.getElementById('homepageMediaVideos');
        
        if (tab === 'Posters') {
            postersSub.style.display = 'block';
            videosSub.style.display = 'none';
            postersBtn.classList.add('active');
            videosBtn.classList.remove('active');
        } else {
            postersSub.style.display = 'none';
            videosSub.style.display = 'block';
            videosBtn.classList.add('active');
            postersBtn.classList.remove('active');
        }
    };
    
    // Global link hook for navigation dropdown
    window.switchMainMediaTab = function(tab) {
        window.switchHomepageMediaTab(tab);
    };

    // ----------------------------------------------------
    // Lightbox / Media Viewer
    // ----------------------------------------------------
    window.openPosterLightbox = function(src, title) {
        const modal = document.getElementById('homepageLightbox');
        const img = document.getElementById('lightboxImage');
        const video = document.getElementById('lightboxVideoContainer');
        const titleEl = document.getElementById('lightboxMediaTitle');
        
        img.src = src;
        img.style.display = 'block';
        video.style.display = 'none';
        titleEl.textContent = title;
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.playVideo = function(platform, code, title) {
        const modal = document.getElementById('homepageLightbox');
        const img = document.getElementById('lightboxImage');
        const videoContainer = document.getElementById('lightboxVideoContainer');
        const iframe = document.getElementById('lightboxIframe');
        const titleEl = document.getElementById('lightboxMediaTitle');
        
        img.style.display = 'none';
        videoContainer.style.display = 'block';
        
        if (platform === 'youtube') {
            iframe.src = `https://www.youtube.com/embed/${code}?autoplay=1&rel=0`;
        } else if (platform === 'instagram') {
            iframe.src = `https://www.instagram.com/reel/${code}/embed/`;
        }
        
        titleEl.textContent = title;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeHomepageLightbox = function() {
        const modal = document.getElementById('homepageLightbox');
        const iframe = document.getElementById('lightboxIframe');
        iframe.src = '';
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    window.closeLightboxOnBg = function(e) {
        if (e.target.id === 'homepageLightbox') {
            window.closeHomepageLightbox();
        }
    };

    // ----------------------------------------------------
    // Intercessions Counter & Submit Form Logic
    // ----------------------------------------------------
    window.adjustIntercessionCounter = function(field, direction, step = 1) {
        const input = document.getElementById('input_' + field);
        if (!input) return;
        let val = parseInt(input.value) || 0;
        if (direction === 'plus') {
            input.value = val + step;
        } else if (direction === 'minus') {
            input.value = Math.max(0, val - step);
        }
    };

    window.validateAdorationInput = function(input) {
        let val = parseInt(input.value);
        if (isNaN(val) || val < 0) {
            input.value = 0;
        } else {
            input.value = val;
        }
    };

    window.submitIntercession = function(e) {
        e.preventDefault();
        
        let totalOffered = 0;
        const fields = ['holy_mass', 'our_father', 'hail_mary', 'memorare', 'creed', 'divine_mercy', 'rosary', 'adoration'];
        const data = new FormData();
        
        fields.forEach(field => {
            const val = parseInt(document.getElementById('input_' + field).value) || 0;
            data.append(field, val);
            totalOffered += val;
        });
        
        if (totalOffered <= 0) {
            showToast("Please offer at least one prayer or minute of adoration.", "error");
            return;
        }
        
        showLoading("Offering intercession...");
        
        fetch('index.php?action=submit', {
            method: 'POST',
            body: data
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                
                // Explode particles centered on submit button
                const submitBtn = document.getElementById('btnSubmitOffer');
                const rect = submitBtn.getBoundingClientRect();
                const x = rect.left + rect.width / 2;
                const y = rect.top + rect.height / 2;
                
                const count = 30 + Math.floor(Math.random() * 20);
                for (let i = 0; i < count; i++) {
                    window.createFaithParticle(x, y);
                }
                
                // Reset form inputs
                fields.forEach(field => {
                    document.getElementById('input_' + field).value = 0;
                });
                
                // Update live counters
                window.updateLiveTotals(data.totals, data.grand_total);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(err => {
            hideLoading();
            showToast("Connection error occurred. Please try again.", "error");
        });
    };

    window.updateLiveTotals = function(totals, grandTotal) {
        const grandEl = document.getElementById('total_grand');
        window.animateNumberUpdate(grandEl, grandTotal);
        
        for (const [key, val] of Object.entries(totals)) {
            const el = document.getElementById('total_' + key);
            if (el) {
                window.animateNumberUpdate(el, val);
            }
        }
    };

    window.animateNumberUpdate = function(element, targetValue) {
        const currentValue = parseInt(element.textContent.replace(/,/g, '')) || 0;
        if (currentValue === targetValue) return;
        
        element.classList.remove('pulse-update');
        void element.offsetWidth;
        element.classList.add('pulse-update');
        
        const duration = 600;
        const startTime = performance.now();
        
        function update(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeProgress = progress * (2 - progress);
            
            const currentValueNow = Math.round(currentValue + (targetValue - currentValue) * easeProgress);
            element.textContent = currentValueNow.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = targetValue.toLocaleString();
            }
        }
        
        requestAnimationFrame(update);
    };

    // Background polling for changes every 10 seconds
    setInterval(() => {
        fetch('index.php?action=get_totals')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.updateLiveTotals(data.totals, data.grand_total);
            }
        })
        .catch(err => {
            // Fail silently during background polling
        });
    }, 10000);
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>