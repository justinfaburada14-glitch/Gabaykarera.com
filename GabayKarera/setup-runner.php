<?php
// setup-runner.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/setup.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $dbDsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $db = new PDO($dbDsn, DB_USER, DB_PASS, $options);
    
    runDatabaseSetup($db);
    
    $msg = "Database `gabaykarera_db` and all seed data initialized successfully!";
} catch (Exception $e) {
    $msg = "Error during setup: " . $e->getMessage();
}

$pageTitle = "Database Setup - GabayKarera AI";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="card card-modern p-5 border-0 shadow">
                <i class="fa-solid fa-database fs-1 text-primary mb-3"></i>
                <h4 class="fw-bold mb-2">Database Initialization</h4>
                <div class="alert alert-success card-modern mb-4">
                    <?= htmlspecialchars($msg) ?>
                </div>
                <div class="d-flex justify-content-center gap-3">
                    <a href="/GabayKarera/index.php" class="btn btn-outline-custom">Go to Homepage</a>
                    <a href="/GabayKarera/login.php" class="btn btn-primary-custom">Log In Now</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
