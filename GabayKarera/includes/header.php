<?php
/**
 * ============================================================================
 * GABAYKARERA AI - GLOBAL HEADER TEMPLATE
 * File: includes/header.php
 * Purpose: HTML5 document head, meta tags, CDNs, cache control, flash alerts
 * ============================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------------
// 1. SECURITY & CACHE PREVENTION HEADERS (Prevents back-button cache exposure)
// ----------------------------------------------------------------------------
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

$pageTitle = $pageTitle ?? 'GabayKarera AI - Intelligent Career & Applicant Matching';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ==================== META TAGS ==================== -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- ==================== FONTS & ICONS ==================== -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- ==================== FRAMEWORKS & LIBRARIES ==================== -->
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js for Competency Radar Visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- ==================== CUSTOM STYLES ==================== -->
    <?php
    $customCssPath = __DIR__ . '/../assets/css/custom.css';
    $customCssVer = file_exists($customCssPath) ? filemtime($customCssPath) : time();
    ?>
    <link rel="stylesheet" href="/GabayKarera/assets/css/custom.css?v=<?= $customCssVer ?>">
</head>
<body>

<!-- ==================== NAVIGATION BAR COMPONENT ==================== -->
<?php require_once __DIR__ . '/navbar.php'; ?>

<!-- ==================== MAIN CONTENT WRAPPER ==================== -->
<main>

<!-- ==================== FLASH NOTIFICATIONS & ALERTS ==================== -->
<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show card-modern shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i>
            <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="container mt-3">
        <div class="alert alert-danger alert-dismissible fade show card-modern shadow-sm border-0 d-flex align-items-center" role="alert">
            <i class="fa-solid fa-triangle-exclamation fs-5 me-2 text-danger"></i>
            <div><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
