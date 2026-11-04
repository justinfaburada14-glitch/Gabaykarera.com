<?php
/**
 * ============================================================================
 * GABAYKARERA AI - APPLICANT DASHBOARD
 * File: applicant/dashboard.php
 * Purpose: Profile readiness, top AI matched jobs, recruiter notices, and gap advisory
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';
require_once __DIR__ . '/../classes/CareerRecommender.php';

Auth::requireAuth(['applicant']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];

// Automatically synchronize submitted applications with current competencies
CompetencyMatcher::syncApplicantApplications($pdo, $userId);

// ============================================================================
// 1. DATA FETCHING: PROFILE & SKILLS
// ============================================================================
$profileStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch() ?: [];

$skillStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ?");
$skillStmt->execute([$userId]);
$skills = $skillStmt->fetchAll() ?: [];

// ============================================================================
// 2. DATA FETCHING: APPLICATIONS & RECENT NOTICES
// ============================================================================
$appStmt = $pdo->prepare("SELECT a.*, j.title, j.company_name, j.location, j.job_type FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.applicant_id = ? ORDER BY a.applied_at DESC");
$appStmt->execute([$userId]);
$myApplications = $appStmt->fetchAll();

$recentMsgStmt = $pdo->prepare("
    SELECT m.*, j.title as job_title, j.company_name
    FROM application_messages m
    JOIN applications a ON m.application_id = a.id
    JOIN jobs j ON a.job_id = j.id
    WHERE m.receiver_id = ?
    ORDER BY m.sent_at DESC
    LIMIT 3
");
$recentMsgStmt->execute([$userId]);
$recentMessages = $recentMsgStmt->fetchAll();

// Fetch System Documents for Applicants
$sysDocsStmt = $pdo->query("SELECT * FROM system_documents WHERE target_audience IN ('all', 'applicant') AND status = 'active' ORDER BY created_at DESC LIMIT 3");
$sysDocs = $sysDocsStmt ? $sysDocsStmt->fetchAll() : [];

// ============================================================================
// 3. AI COMPUTATION: LIVE JOB MATCHING & SKILL GAPS
// ============================================================================
$jobStmt = $pdo->query("SELECT * FROM jobs WHERE status = 'Open' ORDER BY id DESC");
$allJobs = $jobStmt->fetchAll();

$matchedJobs = [];
$applicantData = ['profile' => $profile, 'skills' => $skills];

foreach ($allJobs as $job) {
    $matchResult = CompetencyMatcher::matchApplicantToJob($applicantData, $job);
    $job['ai_match'] = $matchResult;
    $matchedJobs[] = $job;
}

// Sort jobs by match score descending
usort($matchedJobs, function($a, $b) {
    return $b['ai_match']['match_score'] <=> $a['ai_match']['match_score'];
});

// Calculate Profile Completeness
$completeness = 20; // baseline
if (!empty($profile['headline'])) $completeness += 15;
if (!empty($profile['education_level'])) $completeness += 15;
if (!empty($profile['experience_years']) && $profile['experience_years'] > 0) $completeness += 15;
if (!empty($profile['interests'])) $completeness += 15;
if (count($skills) >= 3) $completeness += 20;
$completeness = min(100, $completeness);

// Extract top missing skills across high-interest jobs
$topMissingSkills = [];
foreach (array_slice($matchedJobs, 0, 5) as $mj) {
    foreach ($mj['ai_match']['missing_required_skills'] as $msk) {
        $topMissingSkills[$msk] = ($topMissingSkills[$msk] ?? 0) + 1;
    }
}

$pageTitle = "Applicant Dashboard - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: APPLICANT DASHBOARD CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. WELCOME HEADER BANNER ==================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0">Hello, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h3>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Job Seeker</span>
            </div>
            <p class="text-muted mb-0">
                <?= !empty($profile['headline']) ? htmlspecialchars($profile['headline']) : 'Complete your competency profile to receive high-precision AI job matches.' ?>
            </p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a href="/GabayKarera/applicant/profile.php" class="btn btn-outline-custom">
                <i class="fa-solid fa-user-pen me-1"></i> Edit Profile
            </a>
            <a href="/GabayKarera/applicant/recommendations.php" class="btn btn-primary-custom">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> AI Career Guide
            </a>
        </div>
    </div>

    <!-- ==================== 1B. APPLICANT SYSTEM GUIDELINES & GETTING STARTED ==================== -->
    <div class="card card-modern p-4 border-0 mb-4 bg-white shadow-sm border-start border-4 border-info">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-info-subtle text-info p-2 rounded-3">
                    <i class="fa-solid fa-lightbulb fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">How to Use GabayKarera AI (Quick Guidelines)</h5>
                    <small class="text-muted">Follow these 4 steps to maximize your job matches and career growth</small>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#applicantGuideCollapse" aria-expanded="true">
                <i class="fa-solid fa-chevron-down"></i> Toggle Guide
            </button>
        </div>

        <div class="collapse show" id="applicantGuideCollapse">
            <div class="row g-3 pt-2">
                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">1</span>
                                <span class="fw-bold small text-dark">Profile & Resume</span>
                            </div>
                            <p class="small text-muted mb-3">Fill out your degree, work history, and at least 3 skills with proficiency levels to reach 100% readiness.</p>
                        </div>
                        <a href="/GabayKarera/applicant/profile.php" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fa-solid fa-user-pen me-1"></i> Update Profile
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">2</span>
                                <span class="fw-bold small text-dark">AI Job Matching</span>
                            </div>
                            <p class="small text-muted mb-3">Browse open positions ranked by our AI algorithm. Check missing vs. matched skills before applying.</p>
                        </div>
                        <a href="/GabayKarera/applicant/jobs.php" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fa-solid fa-briefcase me-1"></i> Browse Jobs
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">3</span>
                                <span class="fw-bold small text-dark">Upskill & Roadmap</span>
                            </div>
                            <p class="small text-muted mb-3">Bridge competency gaps with curated courses, salary projections, and career progression pathways.</p>
                        </div>
                        <a href="/GabayKarera/applicant/learning-hub.php" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fa-solid fa-graduation-cap me-1"></i> Learning Hub
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">4</span>
                                <span class="fw-bold small text-dark">Apply & Message HR</span>
                            </div>
                            <p class="small text-muted mb-3">Track your application pipeline, review recruiter feedback, and respond to interview invites.</p>
                        </div>
                        <a href="/GabayKarera/applicant/applications.php" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fa-solid fa-file-lines me-1"></i> My Applications
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($recentMessages)): ?>
        <div class="card card-modern p-4 border-0 mb-4 border-start border-5 border-primary bg-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bell text-primary me-2"></i>Recent Employer Notices & Updates (<?= count($recentMessages) ?>)</h6>
                <a href="/GabayKarera/applicant/applications.php" class="small text-decoration-none fw-bold">View All in Applications &rarr;</a>
            </div>
            <div class="d-flex flex-column gap-2 mt-2">
                <?php foreach ($recentMessages as $rm): ?>
                    <div class="p-2 px-3 bg-light rounded border small d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge <?= $rm['message_type'] === 'Job Offer' ? 'bg-success' : 'bg-primary' ?> me-2"><?= htmlspecialchars($rm['message_type']) ?></span>
                            <strong><?= htmlspecialchars($rm['subject']) ?></strong> &bull; <span class="text-muted"><?= htmlspecialchars($rm['company_name']) ?></span>
                        </div>
                        <a href="/GabayKarera/applicant/applications.php" class="btn btn-sm btn-link p-0 text-decoration-none">Read Notice</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== 3. METRIC STAT BOXES ==================== -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-bullseye"></i></div>
                <div>
                    <h4 class="fw-bold mb-0">
                        <?= !empty($matchedJobs) ? CompetencyMatcher::formatScore($matchedJobs[0]['ai_match']['match_score']) : '0%' ?>
                    </h4>
                    <small class="text-muted">Top Job Match</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-green"><i class="fa-solid fa-briefcase"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= count($matchedJobs) ?></h4>
                    <small class="text-muted">Open Positions</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-purple"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= count($myApplications) ?></h4>
                    <small class="text-muted">Applied Jobs</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-amber"><i class="fa-solid fa-gauge-high"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $completeness ?>%</h4>
                    <small class="text-muted">Profile Score</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== 4. MAIN DASHBOARD CONTENT GRID ==================== -->
    <div class="row g-4">
        
        <!-- ==================== 4A. LEFT COLUMN: TOP MATCHED JOBS & RECENT APPS ==================== -->
        <div class="col-lg-8">
            
            <!-- AI Top Matched Jobs Card -->
            <div class="card card-modern p-4 border-0 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-sparkles text-primary me-2"></i>AI Top Matched Opportunities</h5>
                        <small class="text-muted">Ranked in real-time by competency overlap & experience alignment</small>
                    </div>
                    <a href="/GabayKarera/applicant/jobs.php" class="btn btn-sm btn-light border">View All (<?= count($matchedJobs) ?>)</a>
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php foreach (array_slice($matchedJobs, 0, 3) as $mj): ?>
                        <?php 
                            $matchScore = $mj['ai_match']['match_score'];
                            $scoreClass = $matchScore >= 80 ? 'match-high' : ($matchScore >= 65 ? 'match-med' : 'match-low');
                        ?>
                        <div class="p-3 border rounded-3 bg-white hover-shadow transition">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="match-score-circle <?= $scoreClass ?> flex-shrink-0">
                                        <span class="fs-6"><?= CompetencyMatcher::formatScore($matchScore) ?></span>
                                        <small style="font-size: 0.6rem;">MATCH</small>
                                    </div>
                                    <div>
                                        <span class="badge bg-light text-secondary border mb-1"><?= htmlspecialchars($mj['job_type']) ?></span>
                                        <h6 class="fw-bold mb-1">
                                            <a href="/GabayKarera/applicant/job-details.php?id=<?= $mj['id'] ?>" class="text-dark text-decoration-none hover-primary">
                                                <?= htmlspecialchars($mj['title']) ?>
                                            </a>
                                        </h6>
                                        <div class="text-muted small mb-2">
                                            <i class="fa-regular fa-building me-1"></i> <?= htmlspecialchars($mj['company_name']) ?> &bull; 
                                            <i class="fa-solid fa-location-dot ms-1 me-1"></i> <?= htmlspecialchars($mj['location']) ?>
                                        </div>
                                        <div>
                                            <span class="small text-muted me-1">Matched:</span>
                                            <?php foreach (array_slice($mj['ai_match']['matched_required_skills'], 0, 3) as $msk): ?>
                                                <span class="skill-tag skill-tag-matched py-0 px-2" style="font-size: 0.75rem;"><i class="fa-solid fa-check text-success"></i> <?= htmlspecialchars($msk['skill']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-sm-end flex-shrink-0">
                                    <div class="fw-bold text-success mb-2" style="font-size: 0.9rem;">
                                        ₱<?= number_format($mj['salary_min'], 0) ?> - ₱<?= number_format($mj['salary_max'], 0) ?>
                                    </div>
                                    <a href="/GabayKarera/applicant/job-details.php?id=<?= $mj['id'] ?>" class="btn btn-sm btn-primary-custom">
                                        View Match Fit <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Applications Section -->
            <div class="card card-modern p-4 border-0">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-signature text-secondary me-2"></i>My Recent Applications</h5>
                    <a href="/GabayKarera/applicant/applications.php" class="btn btn-sm btn-light border">View All</a>
                </div>

                <?php if (empty($myApplications)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-regular fa-folder-open fs-1 text-secondary opacity-50 mb-2"></i>
                        <p class="mb-1">You haven't submitted any job applications yet.</p>
                        <a href="/GabayKarera/applicant/jobs.php" class="btn btn-sm btn-outline-custom mt-2">Explore Jobs & Apply</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Match Score</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($myApplications, 0, 3) as $app): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($app['title']) ?></span>
                                        </td>
                                        <td class="text-muted"><?= htmlspecialchars($app['company_name']) ?></td>
                                        <td>
                                            <span class="badge badge-success-glow"><?= CompetencyMatcher::formatScore($app['match_score']) ?> Fit</span>
                                        </td>
                                        <td>
                                            <?php
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
                                            <span class="badge <?= $sClass ?>"><?= htmlspecialchars($app['status']) ?></span>
                                        </td>
                                        <td class="text-muted small"><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== 4B. RIGHT COLUMN: SKILLS & GAP ADVISORY ==================== -->
        <div class="col-lg-4">
            <!-- Competency Summary Widget -->
            <div class="card card-modern p-4 border-0 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-code me-2 text-primary"></i>My Registered Skills (<?= count($skills) ?>)</h6>
                    <a href="/GabayKarera/applicant/profile.php" class="btn btn-sm btn-link text-decoration-none p-0">Manage</a>
                </div>

                <?php if (empty($skills)): ?>
                    <p class="small text-muted mb-2">No skills registered yet. Add your competencies to trigger AI matching.</p>
                    <a href="/GabayKarera/applicant/profile.php" class="btn btn-sm btn-primary-custom w-100">+ Add Skills</a>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <?php foreach ($skills as $sk): ?>
                            <span class="skill-tag">
                                <strong><?= htmlspecialchars($sk['skill_name']) ?></strong>
                                <small class="text-muted">(<?= htmlspecialchars($sk['proficiency']) ?>)</small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="p-3 bg-light rounded-3 border mt-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="fw-bold text-secondary">Profile Strength</small>
                        <small class="fw-bold text-primary"><?= $completeness ?>%</small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $completeness ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- AI Skill Gap Advisory -->
            <div class="card card-modern p-4 border-0 ai-box">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-lightbulb text-warning fs-5"></i>
                    <h6 class="fw-bold mb-0 text-dark">AI Upskilling Advisory</h6>
                </div>
                <p class="small text-muted mb-3">
                    Acquiring these in-demand competencies will boost your match percentage across high-paying openings:
                </p>

                <?php if (!empty($topMissingSkills)): ?>
                    <ul class="list-unstyled d-flex flex-column gap-2 small mb-3">
                        <?php foreach (array_slice($topMissingSkills, 0, 4) as $skillName => $freq): ?>
                            <li class="d-flex justify-content-between align-items-center bg-white p-2 rounded border">
                                <span class="fw-semibold text-danger"><i class="fa-solid fa-circle-arrow-up me-1"></i> <?= htmlspecialchars($skillName) ?></span>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">High Demand</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="p-2 bg-white rounded border small text-muted mb-3">
                        Great work! You have strong coverage of core requirements for currently open roles.
                    </div>
                <?php endif; ?>

                <a href="/GabayKarera/applicant/recommendations.php" class="btn btn-sm btn-outline-primary w-100 fw-bold">
                    View Complete Career Roadmap <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>

            <!-- Official System Documents & Guidelines -->
            <?php if (!empty($sysDocs)): ?>
                <div class="card card-modern p-4 border-0 mt-4">
                    <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-2">
                        <i class="fa-solid fa-folder-open text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">System Documents & Guidelines</h6>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($sysDocs as $sd): ?>
                            <div class="p-2 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold text-dark small text-truncate" style="max-width: 190px;"><?= htmlspecialchars($sd['title']) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($sd['category']) ?> &bull; <?= $sd['file_size'] ?></small>
                                </div>
                                <a href="/GabayKarera/uploads/documents/<?= htmlspecialchars($sd['file_name']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1 px-2" style="font-size: 0.75rem;" title="View / Download Document">
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

