<?php
/**
 * ============================================================================
 * NAVIGATION BAR: CONTROLLER & NOTIFICATION BADGE COUNTER
 * File: includes/navbar.php
 * Purpose: Responsive, role-based top navigation header (Guest, Applicant, HR, Admin)
 * ============================================================================
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

/* ============================================================================
 * NAVIGATION BAR: 1. USER CONTEXT & UNREAD MESSAGES QUERY
 * ============================================================================ */
$currentUser = Auth::user();
$currentScript = $_SERVER['SCRIPT_NAME'];

$unreadCount = 0;
if ($currentUser) {
    try {
        $pdoNav = Database::getInstance();
        $navMsgStmt = $pdoNav->prepare("SELECT COUNT(*) FROM application_messages WHERE receiver_id = ? AND is_read = 0");
        $navMsgStmt->execute([$currentUser['id']]);
        $unreadCount = (int)$navMsgStmt->fetchColumn();
    } catch (Exception $e) {
        $unreadCount = 0;
    }
}
?>

<!-- ========================================================================== -->
<!-- NAVIGATION BAR: MAIN NAVBAR CONTAINER                                      -->
<!-- ========================================================================== -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top">
    <div class="container">
        
        <!-- ================================================================== -->
        <!-- NAVIGATION BAR: BRAND LOGO                                         -->
        <!-- ================================================================== -->
        <a class="navbar-brand d-flex align-items-center" href="/GabayKarera/index.php">
            <i class="fa-solid fa-brain me-2 text-primary fs-4"></i>
            <span class="brand-logo">GabayKarera</span>
            <span class="brand-badge">AI MATCH</span>
        </a>
        
        <!-- ================================================================== -->
        <!-- NAVIGATION BAR: MOBILE MENU TOGGLER                                -->
        <!-- ================================================================== -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- ================================================================== -->
        <!-- NAVIGATION BAR: COLLAPSIBLE MENU CONTENT                           -->
        <!-- ================================================================== -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                
                <!-- NAVIGATION BAR: HOME LINK (PUBLIC) -->
                <li class="nav-item">
                    <a class="nav-link <?= strpos($currentScript, 'index.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/index.php">
                        <i class="fa-solid fa-house-chimney me-1"></i> Home
                    </a>
                </li>
                
                <!-- ============================================================== -->
                <!-- NAVIGATION BAR: APPLICANT ROLE NAVIGATION LINKS                -->
                <!-- ============================================================== -->
                <?php if ($currentUser && $currentUser['role'] === 'applicant'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'applicant/dashboard.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/applicant/dashboard.php">
                            <i class="fa-solid fa-gauge-high me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'applicant/jobs.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/applicant/jobs.php">
                            <i class="fa-solid fa-briefcase me-1"></i> Matched Jobs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'applicant/recommendations.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/applicant/recommendations.php">
                            <i class="fa-solid fa-wand-magic-sparkles me-1 text-primary"></i> AI Career Guide
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'applicant/learning-hub.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/applicant/learning-hub.php">
                            <i class="fa-solid fa-graduation-cap me-1 text-primary"></i> Learning Hub
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'applicant/applications.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/applicant/applications.php">
                            <i class="fa-solid fa-file-lines me-1"></i> My Applications
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1" title="<?= $unreadCount ?> unread message(s)"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'applicant/profile.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/applicant/profile.php">
                            <i class="fa-solid fa-user-gear me-1"></i> Profile & Skills
                        </a>
                    </li>

                <!-- ============================================================== -->
                <!-- NAVIGATION BAR: HR / EMPLOYER ROLE NAVIGATION LINKS            -->
                <!-- ============================================================== -->
                <?php elseif ($currentUser && $currentUser['role'] === 'hr'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'hr/dashboard.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/hr/dashboard.php">
                            <i class="fa-solid fa-chart-line me-1"></i> HR Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'hr/applicants.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/hr/applicants.php">
                            <i class="fa-solid fa-users-viewfinder me-1"></i> Candidate Matching
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1" title="<?= $unreadCount ?> unread reply(ies)"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'hr/jobs.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/hr/jobs.php">
                            <i class="fa-solid fa-rectangle-list me-1"></i> Job Postings
                        </a>
                    </li>

                <!-- ============================================================== -->
                <!-- NAVIGATION BAR: ADMIN ROLE NAVIGATION LINKS                    -->
                <!-- ============================================================== -->
                <?php elseif ($currentUser && $currentUser['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'admin/dashboard.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/admin/dashboard.php">
                            <i class="fa-solid fa-shield-halved me-1"></i> Admin Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'admin/documents.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/admin/documents.php">
                            <i class="fa-solid fa-folder-open me-1"></i> Documents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentScript, 'admin/verifications.php') !== false ? 'active fw-bold text-primary' : '' ?>" href="/GabayKarera/admin/verifications.php">
                            <i class="fa-solid fa-file-circle-check me-1"></i> Verifications
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- ================================================================== -->
            <!-- NAVIGATION BAR: USER AUTH ACTIONS & PROFILE DROPDOWN               -->
            <!-- ================================================================== -->
            <div class="d-flex align-items-center gap-2">
                <?php if ($currentUser): ?>
                    <!-- NAVIGATION BAR: LOGGED-IN USER AVATAR & DROPDOWN MENU -->
                    <div class="dropdown">
                        <button class="btn btn-light rounded-pill border px-3 py-2 dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                            <!-- Avatar Image or First Initial Badge -->
                            <?php if (!empty($currentUser['avatar']) && $currentUser['avatar'] !== 'default_avatar.png' && file_exists(__DIR__ . '/../uploads/avatars/' . $currentUser['avatar'])): ?>
                                <img src="/GabayKarera/uploads/avatars/<?= htmlspecialchars($currentUser['avatar']) ?>" alt="Avatar" class="rounded-circle object-fit-cover" style="width: 28px; height: 28px;">
                            <?php else: ?>
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.8rem; font-weight: 700;">
                                    <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <span class="d-none d-sm-inline fw-semibold fs-7"><?= htmlspecialchars($currentUser['full_name']) ?></span>
                            <span class="badge bg-secondary-subtle text-secondary border text-uppercase" style="font-size: 0.65rem;"><?= $currentUser['role'] ?></span>
                        </button>
                        
                        <!-- NAVIGATION BAR: ROLE-SPECIFIC DROPDOWN ITEMS -->
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                            <li><h6 class="dropdown-header">Signed in as <br><strong><?= htmlspecialchars($currentUser['email']) ?></strong></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <?php if ($currentUser['role'] === 'applicant'): ?>
                                <li><a class="dropdown-item" href="/GabayKarera/applicant/profile.php"><i class="fa-regular fa-user me-2"></i>Edit Profile</a></li>
                                <li><a class="dropdown-item" href="/GabayKarera/applicant/learning-hub.php"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i>Learning Hub</a></li>
                                <li><a class="dropdown-item" href="/GabayKarera/applicant/dashboard.php"><i class="fa-solid fa-gauge me-2"></i>My Dashboard</a></li>
                            <?php elseif ($currentUser['role'] === 'hr'): ?>
                                <li><a class="dropdown-item" href="/GabayKarera/hr/dashboard.php"><i class="fa-solid fa-building me-2"></i>HR Dashboard</a></li>
                                <li><a class="dropdown-item" href="/GabayKarera/hr/job-create.php"><i class="fa-solid fa-plus me-2"></i>Post New Job</a></li>
                            <?php elseif ($currentUser['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="/GabayKarera/admin/dashboard.php"><i class="fa-solid fa-shield-halved me-2"></i>Admin Dashboard</a></li>
                                <li><a class="dropdown-item" href="/GabayKarera/admin/documents.php"><i class="fa-solid fa-folder-open me-2 text-primary"></i>Document Repository</a></li>
                                <li><a class="dropdown-item" href="/GabayKarera/admin/verifications.php"><i class="fa-solid fa-file-circle-check me-2 text-success"></i>Document Inspector</a></li>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <!-- NAVIGATION BAR: SIGN OUT ACTION -->
                            <li><a class="dropdown-item text-danger" href="/GabayKarera/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Sign Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- NAVIGATION BAR: GUEST ACTIONS (NOT LOGGED IN) -->
                    <a href="/GabayKarera/login.php" class="btn btn-outline-custom">Log In</a>
                    <a href="/GabayKarera/register.php" class="btn btn-primary-custom">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
