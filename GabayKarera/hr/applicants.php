<?php
/**
 * ============================================================================
 * GABAYKARERA AI - HR CANDIDATE SCREENING & MATCH RANKING
 * File: hr/applicants.php
 * Purpose: Candidate screening table, status filter, and quick application updater
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['hr']);
$pdo = Database::getInstance();
$hrId = $_SESSION['user_id'];

// ============================================================================
// 1. FILTER INPUTS & JOB DROPDOWN OPTIONS
// ============================================================================
$filterJobId = (int)($_GET['job_id'] ?? 0);
$filterStatus = trim($_GET['status'] ?? '');

// Fetch jobs for filter dropdown
$jobsStmt = $pdo->prepare("SELECT id, title FROM jobs WHERE employer_id = ? ORDER BY title ASC");
$jobsStmt->execute([$hrId]);
$hrJobs = $jobsStmt->fetchAll();

// ============================================================================
// 2. ACTION HANDLER: QUICK STATUS UPDATE
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appId = (int)$_POST['app_id'];
    $newStatus = $_POST['status'];
    
    // Verify application belongs to this HR's job
    $vStmt = $pdo->prepare("SELECT a.id FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = ? AND j.employer_id = ?");
    $vStmt->execute([$appId, $hrId]);
    if ($vStmt->fetch()) {
        $uStmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $uStmt->execute([$newStatus, $appId]);
        $_SESSION['flash_success'] = "Applicant status updated to '{$newStatus}'.";
    }
    header("Location: /GabayKarera/hr/applicants.php" . ($filterJobId ? "?job_id={$filterJobId}" : ''));
    exit;
}

// ============================================================================
// 3. DATA FETCHING: APPLICANTS RANKED BY AI MATCH SCORE
// ============================================================================
$sql = "
    SELECT a.*, u.full_name as applicant_name, u.email as applicant_email, u.phone as applicant_phone,
           j.title as job_title, j.id as job_id, p.headline, p.experience_years, p.education_level, p.field_of_study
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.applicant_id = u.id
    LEFT JOIN applicant_profiles p ON u.id = p.user_id
    WHERE j.employer_id = ?
";
$params = [$hrId];

if ($filterJobId > 0) {
    $sql .= " AND j.id = ?";
    $params[] = $filterJobId;
}

if (!empty($filterStatus)) {
    $sql .= " AND a.status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY a.match_score DESC, a.applied_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

$pageTitle = "Candidate Screening & AI Matching - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0"><i class="fa-solid fa-users-viewfinder text-primary me-2"></i>AI Candidate Match Ranking</h3>
            </div>
            <p class="text-muted mb-0">Applicants scored and ranked by multi-dimensional competency fit to accelerate your screening process.</p>
        </div>
        <a href="/GabayKarera/hr/job-create.php" class="btn btn-primary-custom mt-3 mt-md-0">
            <i class="fa-solid fa-plus me-1"></i> Post New Opening
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="card card-modern p-3 border-0 mb-4 shadow-sm">
        <form method="GET" action="/GabayKarera/hr/applicants.php" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold mb-1">Filter by Job Posting</label>
                <select name="job_id" class="form-select">
                    <option value="0">All Job Openings (<?= count($hrJobs) ?>)</option>
                    <?php foreach ($hrJobs as $hj): ?>
                        <option value="<?= $hj['id'] ?>" <?= $filterJobId == $hj['id'] ? 'selected' : '' ?>><?= htmlspecialchars($hj['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-bold mb-1">Filter by Hiring Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['Submitted', 'Reviewing', 'Shortlisted', 'Interview Scheduled', 'Hired', 'Rejected'] as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary-custom w-100">
                    <i class="fa-solid fa-filter me-1"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Applicants Table -->
    <?php if (empty($applicants)): ?>
        <div class="card card-modern p-5 text-center border-0">
            <i class="fa-solid fa-user-xmark fs-1 text-secondary opacity-50 mb-3"></i>
            <h5 class="fw-bold">No candidate applications found</h5>
            <p class="text-muted small">No candidates match your current filter parameters.</p>
            <a href="/GabayKarera/hr/applicants.php" class="btn btn-outline-custom mx-auto">Reset Filters</a>
        </div>
    <?php else: ?>
        <div class="card card-modern border-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Applied Position</th>
                            <th>AI Match Score</th>
                            <th>Experience & Degree</th>
                            <th>Hiring Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applicants as $app): ?>
                            <?php
                                $score = $app['match_score'];
                                $badgeClass = $score >= 85 ? 'badge-success-glow' : ($score >= 70 ? 'badge-info-glow' : 'badge-warning-glow');
                                $statusClasses = [
                                    'Submitted' => 'bg-secondary',
                                    'Reviewing' => 'bg-info text-dark',
                                    'Shortlisted' => 'bg-primary',
                                    'Interview Scheduled' => 'bg-warning text-dark',
                                    'Hired' => 'bg-success',
                                    'Rejected' => 'bg-danger'
                                ];
                                $sClass = $statusClasses[$app['status']] ?? 'bg-secondary';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                            <?= strtoupper(substr($app['applicant_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-0">
                                                <a href="/GabayKarera/hr/applicant-detail.php?id=<?= $app['id'] ?>" class="text-dark text-decoration-none hover-primary">
                                                    <?= htmlspecialchars($app['applicant_name']) ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted"><?= htmlspecialchars($app['applicant_email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($app['job_title']) ?></span>
                                    <small class="text-muted d-block"><?= date('M d, Y', strtotime($app['applied_at'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?> fs-6 px-3 py-1">
                                        <i class="fa-solid fa-bolt me-1"></i> <?= CompetencyMatcher::formatScore($score) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-dark fw-semibold d-block"><?= $app['experience_years'] ?? 0 ?> Years Experience</small>
                                    <small class="text-muted"><?= htmlspecialchars($app['education_level'] ?? 'Not specified') ?></small>
                                </td>
                                <td>
                                    <form method="POST" action="/GabayKarera/hr/applicants.php<?= $filterJobId ? "?job_id={$filterJobId}" : '' ?>" class="d-inline">
                                        <input type="hidden" name="update_status" value="1">
                                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 150px; font-size: 0.8rem; font-weight: 600;">
                                            <?php foreach (['Submitted', 'Reviewing', 'Shortlisted', 'Interview Scheduled', 'Hired', 'Rejected'] as $st): ?>
                                                <option value="<?= $st ?>" <?= $app['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <a href="/GabayKarera/hr/applicant-detail.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-primary-custom">
                                        Inspect Profile <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
