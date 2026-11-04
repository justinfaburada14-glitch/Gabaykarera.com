<?php
/**
 * ============================================================================
 * GABAYKARERA AI - HR / EMPLOYER DASHBOARD
 * File: hr/dashboard.php
 * Purpose: Candidate screening overview, recruitment metrics, and active job postings
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['hr']);
$pdo = Database::getInstance();
$hrId = $_SESSION['user_id'];

// ============================================================================
// 1. DATA FETCHING: RECRUITMENT METRICS & STATS
// ============================================================================
$jobsCount = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ?");
$jobsCount->execute([$hrId]);
$totalJobs = $jobsCount->fetchColumn();

$openJobsCount = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'Open'");
$openJobsCount->execute([$hrId]);
$totalOpenJobs = $openJobsCount->fetchColumn();

$appsCount = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.employer_id = ?");
$appsCount->execute([$hrId]);
$totalApplicants = $appsCount->fetchColumn();

$highMatchCount = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.employer_id = ? AND a.match_score >= 85");
$highMatchCount->execute([$hrId]);
$totalHighMatch = $highMatchCount->fetchColumn();

// ============================================================================
// 2. DATA FETCHING: RECENT CANDIDATE SUBMISSIONS & ACTIVE POSTINGS
// ============================================================================
$recentAppsStmt = $pdo->prepare("
    SELECT a.*, u.full_name as applicant_name, u.email as applicant_email, j.title as job_title, p.headline, p.experience_years, p.education_level
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.applicant_id = u.id
    LEFT JOIN applicant_profiles p ON u.id = p.user_id
    WHERE j.employer_id = ?
    ORDER BY a.match_score DESC, a.applied_at DESC
    LIMIT 6
");
$recentAppsStmt->execute([$hrId]);
$recentApplicants = $recentAppsStmt->fetchAll();

$activeJobsStmt = $pdo->prepare("
    SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as app_count
    FROM jobs j
    WHERE j.employer_id = ?
    ORDER BY j.created_at DESC
    LIMIT 4
");
$activeJobsStmt->execute([$hrId]);
$activeJobs = $activeJobsStmt->fetchAll();

// Fetch System Documents for HR
$hrDocsStmt = $pdo->query("SELECT * FROM system_documents WHERE target_audience IN ('all', 'hr') AND status = 'active' ORDER BY created_at DESC LIMIT 3");
$hrDocs = $hrDocsStmt ? $hrDocsStmt->fetchAll() : [];

$pageTitle = "HR Dashboard - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: HR DASHBOARD CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. WELCOME HEADER BANNER ==================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h3>
                <span class="badge bg-success-subtle text-success border border-success-subtle">HR Personnel</span>
            </div>
            <p class="text-muted mb-0">
                <?= !empty($_SESSION['user_company']) ? htmlspecialchars($_SESSION['user_company']) : 'Talent Acquisition & Competency Screening Portal' ?>
            </p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a href="/GabayKarera/hr/job-create.php" class="btn btn-primary-custom">
                <i class="fa-solid fa-plus me-1"></i> Post New Job Opening
            </a>
            <a href="/GabayKarera/hr/applicants.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-users-viewfinder me-1"></i> Review Candidates
            </a>
        </div>
    </div>

    <!-- ==================== 1B. HR SYSTEM GUIDELINES & RECRUITMENT WORKFLOW ==================== -->
    <div class="card card-modern p-4 border-0 mb-4 bg-white shadow-sm border-start border-4 border-success">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-success-subtle text-success p-2 rounded-3">
                    <i class="fa-solid fa-lightbulb fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">HR Recruiter Quick Guidelines & Workflow</h5>
                    <small class="text-muted">4-step guide to finding, screening, and hiring verified talent</small>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#hrGuideCollapse" aria-expanded="true">
                <i class="fa-solid fa-chevron-down"></i> Toggle Guide
            </button>
        </div>

        <div class="collapse show" id="hrGuideCollapse">
            <div class="row g-3 pt-2">
                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">1</span>
                                <span class="fw-bold small text-dark">Company Legitimacy</span>
                            </div>
                            <p class="small text-muted mb-3">Ensure your SEC/DTI registration and Tax ID are up to date to receive the green <strong>Verified Employer</strong> shield.</p>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle text-center py-1.5"><i class="fa-solid fa-shield-check me-1"></i> Admin Audited</span>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">2</span>
                                <span class="fw-bold small text-dark">Post Openings</span>
                            </div>
                            <p class="small text-muted mb-3">Define clear required skills, experience levels, and salary ranges to enable real-time competency scoring.</p>
                        </div>
                        <a href="/GabayKarera/hr/job-create.php" class="btn btn-sm btn-outline-success w-100">
                            <i class="fa-solid fa-plus me-1"></i> Post New Job
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">3</span>
                                <span class="fw-bold small text-dark">Screen Matched Talent</span>
                            </div>
                            <p class="small text-muted mb-3">Filter incoming candidate applications sorted by AI Match Score percentage to fast-track top candidates.</p>
                        </div>
                        <a href="/GabayKarera/hr/applicants.php" class="btn btn-sm btn-outline-success w-100">
                            <i class="fa-solid fa-users-viewfinder me-1"></i> View Candidates
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">4</span>
                                <span class="fw-bold small text-dark">Engage & Interview</span>
                            </div>
                            <p class="small text-muted mb-3">Open candidate dossiers to review gap breakdowns, schedule interview calls, and send official offer notices.</p>
                        </div>
                        <a href="/GabayKarera/hr/jobs.php" class="btn btn-sm btn-outline-success w-100">
                            <i class="fa-solid fa-rectangle-list me-1"></i> Job Postings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== 2. RECRUITMENT METRIC STAT BOXES ==================== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-briefcase"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalOpenJobs ?></h4>
                    <small class="text-muted">Active Job Openings</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-green"><i class="fa-solid fa-users"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalApplicants ?></h4>
                    <small class="text-muted">Total Applicants</small>
                </div>
            </div>
        </div>


        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-purple"><i class="fa-solid fa-star"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalHighMatch ?></h4>
                    <small class="text-muted">High Match (85%+)</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-amber"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalJobs ?></h4>
                    <small class="text-muted">Total Postings</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- AI Matched Applicants Pipeline -->
        <div class="col-lg-8">
            <div class="card card-modern p-4 border-0 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-brain text-primary me-2"></i>AI Candidate Match Pipeline</h5>
                        <small class="text-muted">Candidates automatically ranked by competency alignment score</small>
                    </div>
                    <a href="/GabayKarera/hr/applicants.php" class="btn btn-sm btn-light border">View All Candidates</a>
                </div>

                <?php if (empty($recentApplicants)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-users-slash fs-1 text-secondary opacity-50 mb-2"></i>
                        <p class="mb-0">No candidate applications received yet for your job postings.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($recentApplicants as $app): ?>
                            <?php
                                $score = $app['match_score'];
                                $scoreClass = $score >= 85 ? 'match-high' : ($score >= 70 ? 'match-med' : 'match-low');
                            ?>
                            <div class="p-3 border rounded-3 bg-white hover-shadow transition">
                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="match-score-circle <?= $scoreClass ?> flex-shrink-0">
                                            <span class="fs-6"><?= CompetencyMatcher::formatScore($score) ?></span>
                                            <small style="font-size: 0.55rem;">MATCH</small>
                                        </div>

                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($app['applicant_name']) ?></h6>
                                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($app['status']) ?></span>
                                            </div>
                                            <small class="text-primary fw-semibold d-block mb-1">
                                                Applied for: <?= htmlspecialchars($app['job_title']) ?>
                                            </small>
                                            <div class="text-muted small">
                                                <i class="fa-solid fa-graduation-cap me-1"></i> <?= htmlspecialchars($app['education_level'] ?? 'Degree') ?> &bull;
                                                <i class="fa-solid fa-briefcase ms-1 me-1"></i> <?= $app['experience_years'] ?? 0 ?> yrs exp
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-sm-end flex-shrink-0">
                                        <a href="/GabayKarera/hr/applicant-detail.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-primary-custom">
                                            Review Competencies <i class="fa-solid fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: My Job Postings -->
        <div class="col-lg-4">
            <div class="card card-modern p-4 border-0 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-rectangle-list text-primary me-2"></i>My Job Postings (<?= count($activeJobs) ?>)</h6>
                    <a href="/GabayKarera/hr/job-create.php" class="btn btn-sm btn-primary-custom py-1 px-2">+ Post</a>
                </div>

                <?php if (empty($activeJobs)): ?>
                    <p class="small text-muted mb-3">No job openings created yet.</p>
                    <a href="/GabayKarera/hr/job-create.php" class="btn btn-sm btn-outline-custom w-100">+ Create First Job</a>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($activeJobs as $j): ?>
                            <div class="p-3 border rounded-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($j['title']) ?></h6>
                                    <span class="badge bg-success"><?= htmlspecialchars($j['status']) ?></span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($j['location']) ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="badge bg-primary-subtle text-primary"><?= $j['app_count'] ?> Applicants</span>
                                    <a href="/GabayKarera/hr/applicants.php?job_id=<?= $j['id'] ?>" class="small text-decoration-none fw-bold">
                                        View Pool &rarr;
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="/GabayKarera/hr/jobs.php" class="btn btn-sm btn-outline-secondary w-100">Manage All Postings</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- HR Advisory Card -->
            <div class="card card-modern p-4 border-0 ai-box mb-4">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-shield-halved text-success me-2"></i>Objective Hiring Assistance</h6>
                <p class="small text-muted mb-0">
                    GabayKarera AI serves as a powerful competency scoring tool to eliminate manual resume screening fatigue. <strong>Final interview decisions and hiring choices remain solely at the discretion of HR personnel.</strong>
                </p>
            </div>

            <!-- Official System Documents & Compliance Policy -->
            <?php if (!empty($hrDocs)): ?>
                <div class="card card-modern p-4 border-0">
                    <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-folder-open text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">System Documents & Compliance</h6>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($hrDocs as $hd): ?>
                            <div class="p-2 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark small text-truncate" style="max-width: 190px;"><?= htmlspecialchars($hd['title']) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($hd['category']) ?> &bull; <?= $hd['file_size'] ?></small>
                                </div>
                                <a href="/GabayKarera/uploads/documents/<?= htmlspecialchars($hd['file_name']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 0.75rem;" title="View / Download Document">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
