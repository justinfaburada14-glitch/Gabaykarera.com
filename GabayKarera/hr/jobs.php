<?php
/**
 * ============================================================================
 * GABAYKARERA AI - HR JOB POSTINGS MANAGEMENT
 * File: hr/jobs.php
 * Purpose: Manage active and closed job openings, applicant counts, and status toggles
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['hr']);
$pdo = Database::getInstance();
$hrId = $_SESSION['user_id'];

// ============================================================================
// 1. ACTION HANDLERS: STATUS TOGGLE & DELETE JOB
// ============================================================================
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $jobId = (int)$_GET['id'];
    $newStatus = $_GET['toggle_status'] === 'Closed' ? 'Closed' : 'Open';
    $tStmt = $pdo->prepare("UPDATE jobs SET status = ? WHERE id = ? AND employer_id = ?");
    $tStmt->execute([$newStatus, $jobId, $hrId]);
    $_SESSION['flash_success'] = "Job status updated to {$newStatus}.";
    header("Location: /GabayKarera/hr/jobs.php");
    exit;
}

if (isset($_GET['delete_job'])) {
    $jobId = (int)$_GET['delete_job'];
    $dStmt = $pdo->prepare("DELETE FROM jobs WHERE id = ? AND employer_id = ?");
    $dStmt->execute([$jobId, $hrId]);
    $_SESSION['flash_success'] = "Job posting deleted.";
    header("Location: /GabayKarera/hr/jobs.php");
    exit;
}

// ============================================================================
// 2. DATA FETCHING: HR'S JOB POSTINGS WITH APPLICANT COUNTS
// ============================================================================
$stmt = $pdo->prepare("
    SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as applicant_count,
           (SELECT AVG(match_score) FROM applications WHERE job_id = j.id) as avg_match
    FROM jobs j
    WHERE j.employer_id = ?
    ORDER BY j.created_at DESC
");
$stmt->execute([$hrId]);
$jobs = $stmt->fetchAll();

$pageTitle = "Manage Job Postings - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: HR JOB POSTINGS CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. PAGE HEADER ==================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-rectangle-list text-primary me-2"></i>Job Postings & Requirements</h3>
            <p class="text-muted mb-0">Define required competencies, experience thresholds, and manage your active hiring campaigns.</p>
        </div>
        <a href="/GabayKarera/hr/job-create.php" class="btn btn-primary-custom mt-3 mt-md-0">
            <i class="fa-solid fa-plus me-1"></i> Post New Job Opening
        </a>
    </div>


    <?php if (empty($jobs)): ?>
        <div class="card card-modern p-5 text-center border-0">
            <i class="fa-solid fa-briefcase fs-1 text-secondary opacity-50 mb-3"></i>
            <h5 class="fw-bold">No job postings created yet</h5>
            <p class="text-muted small">Create your first job listing to start receiving AI-matched applicants.</p>
            <a href="/GabayKarera/hr/job-create.php" class="btn btn-primary-custom mx-auto">+ Post a Job Opening</a>
        </div>
    <?php else: ?>
        <div class="card card-modern border-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Job Title & Location</th>
                            <th>Job Type</th>
                            <th>Required Skills</th>
                            <th>Applicants</th>
                            <th>Avg Match</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <?php $skillsList = array_slice(array_map('trim', explode(',', $job['required_skills'])), 0, 3); ?>
                            <tr>
                                <td>
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($job['title']) ?></h6>
                                    <small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($job['location']) ?> &bull; <?= $job['experience_required'] ?> yrs exp</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border"><?= htmlspecialchars($job['job_type']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($skillsList as $sk): ?>
                                            <span class="skill-tag py-0 px-2" style="font-size: 0.75rem;"><?= htmlspecialchars($sk) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="/GabayKarera/hr/applicants.php?job_id=<?= $job['id'] ?>" class="badge bg-primary-subtle text-primary border text-decoration-none px-3 py-2">
                                        <?= $job['applicant_count'] ?> Applicants
                                    </a>
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?= $job['avg_match'] ? CompetencyMatcher::formatScore($job['avg_match']) : 'N/A' ?></span>
                                </td>
                                <td>
                                    <?php if ($job['status'] === 'Open'): ?>
                                        <a href="/GabayKarera/hr/jobs.php?id=<?= $job['id'] ?>&toggle_status=Closed" class="badge bg-success text-decoration-none" title="Click to close posting">
                                            <i class="fa-solid fa-circle-check me-1"></i> Open
                                        </a>
                                    <?php else: ?>
                                        <a href="/GabayKarera/hr/jobs.php?id=<?= $job['id'] ?>&toggle_status=Open" class="badge bg-secondary text-decoration-none" title="Click to reopen">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Closed
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="/GabayKarera/hr/job-edit.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit Job">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="/GabayKarera/hr/applicants.php?job_id=<?= $job['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Matched Candidates">
                                            <i class="fa-solid fa-users-viewfinder"></i>
                                        </a>
                                        <a href="/GabayKarera/hr/jobs.php?delete_job=<?= $job['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this job listing?');" title="Delete Job">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
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
