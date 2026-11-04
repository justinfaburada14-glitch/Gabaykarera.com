<?php
/**
 * ============================================================================
 * GABAYKARERA AI - APPLICANT JOB DETAILS & MATCH BREAKDOWN
 * File: applicant/job-details.php
 * Purpose: Full job description, radar fit, competency gap analysis, and 1-click application
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['applicant']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];
$jobId = (int)($_GET['id'] ?? 0);

// ============================================================================
// 1. DATA FETCHING: JOB SPECIFICATIONS & APPLICANT PROFILE
// ============================================================================
$stmt = $pdo->prepare("SELECT j.*, u.full_name as employer_name, u.email as employer_email, u.company_verification_status FROM jobs j JOIN users u ON j.employer_id = u.id WHERE j.id = ?");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['flash_error'] = "Job posting not found.";
    header("Location: /GabayKarera/applicant/jobs.php");
    exit;
}

$pStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
$pStmt->execute([$userId]);
$profile = $pStmt->fetch() ?: [];

$sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ?");
$sStmt->execute([$userId]);
$skills = $sStmt->fetchAll() ?: [];

$applicantData = ['profile' => $profile, 'skills' => $skills];

// ============================================================================
// 2. AI COMPETENCY SCORING ENGINE
// ============================================================================
$aiMatch = CompetencyMatcher::matchApplicantToJob($applicantData, $job);

// Check if user has already applied
$appCheck = $pdo->prepare("SELECT * FROM applications WHERE job_id = ? AND applicant_id = ?");
$appCheck->execute([$jobId, $userId]);
$existingApplication = $appCheck->fetch();

// Automatically synchronize match score and competency breakdown with latest skills
if ($existingApplication) {
    $liveScore = (float)$aiMatch['match_score'];
    $storedScore = (float)$existingApplication['match_score'];
    if (abs($liveScore - $storedScore) > 0.01) {
        $breakdownJson = json_encode($aiMatch);
        $syncStmt = $pdo->prepare("UPDATE applications SET match_score = ?, competency_breakdown = ? WHERE id = ?");
        $syncStmt->execute([$liveScore, $breakdownJson, $existingApplication['id']]);
        $existingApplication['match_score'] = $liveScore;
        $existingApplication['competency_breakdown'] = $breakdownJson;
    }
}

// ============================================================================
// 3. ACTION HANDLERS: SUBMIT & UPDATE APPLICATION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_application'])) {
        if ($existingApplication) {
            $_SESSION['flash_error'] = "You have already applied for this position. You can update your pitch below.";
        } else {
            $coverNote = trim($_POST['cover_note'] ?? '');
            $score = $aiMatch['match_score'];
            $breakdownJson = json_encode($aiMatch);

            $insApp = $pdo->prepare("INSERT INTO applications (job_id, applicant_id, match_score, competency_breakdown, status, cover_note) VALUES (?, ?, ?, ?, 'Submitted', ?)");
            $insApp->execute([$jobId, $userId, $score, $breakdownJson, $coverNote]);

            $_SESSION['flash_success'] = "Application submitted successfully! Your AI Competency Score of " . CompetencyMatcher::formatScore($score) . " has been transmitted to HR.";
            header("Location: /GabayKarera/applicant/job-details.php?id={$jobId}");
            exit;
        }
    } elseif (isset($_POST['update_application']) && $existingApplication) {
        $coverNote = trim($_POST['cover_note'] ?? '');
        $score = $aiMatch['match_score'];
        $breakdownJson = json_encode($aiMatch);

        $uApp = $pdo->prepare("UPDATE applications SET match_score = ?, competency_breakdown = ?, cover_note = ? WHERE id = ?");
        $uApp->execute([$score, $breakdownJson, $coverNote, $existingApplication['id']]);

        $_SESSION['flash_success'] = "Application updated! Your latest AI Competency Score of " . CompetencyMatcher::formatScore($score) . " and updated pitch were sent to HR.";
        header("Location: /GabayKarera/applicant/job-details.php?id={$jobId}");
        exit;
    }
}

$pageTitle = htmlspecialchars($job['title']) . " - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: JOB DETAILS & COMPETENCY FIT CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. BREADCRUMB / BACK LINK ==================== -->
    <div class="mb-3">
        <a href="/GabayKarera/applicant/jobs.php" class="text-decoration-none text-muted small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to All Jobs
        </a>
    </div>

    <!-- ==================== 2. MAIN JOB & AI MATCH GRID ==================== -->
    <div class="row g-4">
        
        <!-- Left: Job Description & Overview -->
        <div class="col-lg-7">
            <div class="card card-modern p-4 border-0 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="badge bg-primary-subtle text-primary mb-2"><?= htmlspecialchars($job['job_type']) ?></span>
                        <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($job['title']) ?></h3>
                        <div class="text-muted d-flex align-items-center flex-wrap gap-2">
                            <span><i class="fa-regular fa-building me-1"></i> <strong><?= htmlspecialchars($job['company_name']) ?></strong></span>
                            <?php if (($job['company_verification_status'] ?? 'pending') === 'verified'): ?>
                                <span class="badge bg-success text-white" style="font-size: 0.7rem;"><i class="fa-solid fa-shield-check me-1"></i> Verified Employer</span>
                            <?php endif; ?>
                            <span>&bull; <i class="fa-solid fa-location-dot ms-1 me-1"></i> <?= htmlspecialchars($job['location']) ?></span>
                        </div>
                    </div>
                </div>

                <div class="row g-2 p-3 bg-light rounded-3 border my-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Offered Monthly Salary</small>
                        <strong class="text-success fs-5">₱<?= number_format($job['salary_min'], 0) ?> - ₱<?= number_format($job['salary_max'], 0) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Experience Required</small>
                        <strong class="text-dark fs-6"><?= $job['experience_required'] ?>+ Years Required</strong>
                    </div>
                </div>

                <h5 class="fw-bold mb-2">Job Description & Responsibilities</h5>
                <div class="text-secondary mb-4" style="line-height: 1.7; white-space: pre-line;">
                    <?= htmlspecialchars($job['description']) ?>
                </div>


                <h6 class="fw-bold mb-2">Required Core Competencies:</h6>
                <div class="mb-3">
                    <?php 
                        $reqSkills = array_map('trim', explode(',', $job['required_skills']));
                        foreach ($reqSkills as $sk): 
                    ?>
                        <span class="skill-tag"><i class="fa-solid fa-tag text-primary" style="font-size: 0.75rem;"></i> <?= htmlspecialchars($sk) ?></span>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($job['preferred_skills'])): ?>
                    <h6 class="fw-bold mb-2">Preferred / Nice-to-Have Skills:</h6>
                    <div class="mb-3">
                        <?php 
                            $prefSkills = array_map('trim', explode(',', $job['preferred_skills']));
                            foreach ($prefSkills as $psk): 
                        ?>
                            <span class="skill-tag bg-light text-muted"><i class="fa-regular fa-star text-warning" style="font-size: 0.75rem;"></i> <?= htmlspecialchars($psk) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Application Action Box -->
            <div class="card card-modern p-4 border-0">
                <?php if ($existingApplication): ?>
                    <div class="alert alert-info p-3 rounded-3 mb-3 border-0 shadow-sm">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa-solid fa-circle-check fs-4 me-2 text-primary"></i>
                            <div>
                                <strong class="text-dark">Application Active & Transmitted to HR</strong>
                                <small class="text-muted d-block">Submitted on <?= date('F d, Y', strtotime($existingApplication['applied_at'])) ?></small>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top">
                            <span class="badge bg-primary px-3 py-1.5 fw-bold">Status: <?= htmlspecialchars($existingApplication['status']) ?></span>
                            <span class="badge badge-success-glow px-3 py-1.5 fw-bold"><i class="fa-solid fa-bolt me-1"></i> <?= CompetencyMatcher::formatScore($existingApplication['match_score']) ?> Synchronized Fit</span>
                        </div>
                        <?php if (!empty($existingApplication['cover_note'])): ?>
                            <div class="mt-2 pt-2 border-top small text-secondary">
                                <strong>Your Transmitted Pitch:</strong> "<?= htmlspecialchars($existingApplication['cover_note']) ?>"
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Toggle to update cover note -->
                    <button class="btn btn-sm btn-outline-primary w-100 py-2 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#updatePitchCollapse">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Update Cover Note / Pitch to HR
                    </button>

                    <div class="collapse" id="updatePitchCollapse">
                        <form method="POST" action="/GabayKarera/applicant/job-details.php?id=<?= $job['id'] ?>" class="p-3 bg-light rounded-3 border mt-2">
                            <input type="hidden" name="update_application" value="1">
                            <label class="form-label small fw-bold">Updated Cover Note / Pitch to Employer</label>
                            <textarea name="cover_note" rows="3" class="form-control small mb-2" placeholder="Update your introduction or explain your new competencies..."><?= htmlspecialchars($existingApplication['cover_note'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-sm btn-primary-custom w-100">
                                <i class="fa-solid fa-arrows-rotate me-1"></i> Transmit Updated Pitch (<?= CompetencyMatcher::formatScore($aiMatch['match_score']) ?> Fit)
                            </button>
                        </form>
                    </div>

                    <div class="text-center pt-2">
                        <a href="/GabayKarera/applicant/applications.php" class="small text-decoration-none fw-semibold">
                            <i class="fa-solid fa-comments me-1"></i> View Recruiter Communications & Messages &rarr;
                        </a>
                    </div>
                <?php else: ?>
                    <h5 class="fw-bold mb-2">Apply for this Position</h5>
                    <p class="text-muted small mb-3">Your complete profile, career history, registered skills, and AI Match Score (<strong><?= CompetencyMatcher::formatScore($aiMatch['match_score']) ?></strong>) will be submitted directly to the HR hiring team.</p>

                    <!-- Profile & Career Background Summary Box -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-1">
                            <small class="fw-bold text-primary"><i class="fa-solid fa-id-card me-1"></i> Your Transmitted Career Background:</small>
                            <a href="/GabayKarera/applicant/profile.php" class="text-decoration-none small text-muted" target="_blank"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Profile</a>
                        </div>
                        <div class="row g-2 small text-secondary">
                            <div class="col-sm-6">
                                <span><i class="fa-solid fa-graduation-cap text-primary me-1"></i> <?= htmlspecialchars($profile['education_level'] ?: 'Not set') ?></span>
                                <?php if (!empty($profile['field_of_study'])): ?>
                                    <div class="text-muted ps-3"><?= htmlspecialchars($profile['field_of_study']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <span><i class="fa-solid fa-briefcase text-primary me-1"></i> <?= $profile['experience_years'] ?? '0' ?> yrs experience</span>
                                <?php if (!empty($profile['current_position'])): ?>
                                    <div class="text-muted ps-3"><?= htmlspecialchars($profile['current_position']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 mt-1">
                                <?php if (!empty($profile['certifications'])): ?>
                                    <span class="badge bg-warning-subtle text-dark border me-1"><i class="fa-solid fa-certificate text-warning"></i> Certifications Listed</span>
                                <?php endif; ?>
                                <?php if (!empty($profile['work_preference'])): ?>
                                    <span class="badge bg-info-subtle text-info border me-1"><i class="fa-solid fa-laptop-house"></i> <?= htmlspecialchars($profile['work_preference']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($profile['resume_file'])): ?>
                                    <span class="badge bg-success-subtle text-success border"><i class="fa-solid fa-file-pdf"></i> Resume Attached</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="/GabayKarera/applicant/job-details.php?id=<?= $job['id'] ?>">
                        <input type="hidden" name="submit_application" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Cover Note / Message to Employer (Optional)</label>
                            <textarea name="cover_note" rows="3" class="form-control" placeholder="Introduce yourself, highlight your past experience, or explain why your competencies match this role..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary-custom w-100 py-3 fs-6">
                            <i class="fa-solid fa-paper-plane me-2"></i> Submit Application with AI Match Profile
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: AI Competency Analysis & Gap Roadmap -->
        <div class="col-lg-5">
            <div class="card card-modern p-4 border-0 mb-4 sticky-top" style="top: 80px;">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-brain text-primary me-2"></i>AI Competency Fit</h5>
                    <span class="badge <?= $aiMatch['rating_badge_class'] ?>"><?= $aiMatch['rating_label'] ?></span>
                </div>

                <!-- Match Score Circular Display -->
                <div class="text-center py-3 bg-light rounded-3 mb-3 border">
                    <div class="display-4 fw-extrabold text-primary mb-0">
                        <?= CompetencyMatcher::formatScore($aiMatch['match_score']) ?>
                    </div>
                    <small class="text-muted fw-bold">Overall Competency Fit</small>
                    <p class="small text-secondary px-3 mt-2 mb-0">
                        <?= htmlspecialchars($aiMatch['verdict']) ?>
                    </p>
                </div>

                <!-- Radar Chart -->
                <div class="mb-3" style="height: 220px; position: relative;">
                    <canvas id="jobCompetencyRadar"></canvas>
                </div>

                <!-- Overlap & Gaps -->
                <div class="mb-3">
                    <h6 class="fw-bold small text-success mb-2">
                        <i class="fa-solid fa-check-circle me-1"></i> Matched Competencies (<?= count($aiMatch['matched_required_skills']) ?>):
                    </h6>
                    <?php if (!empty($aiMatch['matched_required_skills'])): ?>
                        <div class="mb-3">
                            <?php foreach ($aiMatch['matched_required_skills'] as $m): ?>
                                <span class="skill-tag skill-tag-matched"><i class="fa-solid fa-check text-success"></i> <?= htmlspecialchars($m['skill']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted mb-2">No required skills currently match your profile.</p>
                    <?php endif; ?>

                    <?php if (!empty($aiMatch['missing_required_skills'])): ?>
                        <h6 class="fw-bold small text-danger mb-2">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Missing Competencies (<?= count($aiMatch['missing_required_skills']) ?>):
                        </h6>
                        <div class="mb-3">
                            <?php foreach ($aiMatch['missing_required_skills'] as $miss): ?>
                                <span class="skill-tag skill-tag-missing"><i class="fa-solid fa-circle-plus text-danger"></i> <?= htmlspecialchars($miss) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actionable Gap Recommendations -->
                <?php if (!empty($aiMatch['gap_recommendations'])): ?>
                    <div class="p-3 bg-white rounded-3 border ai-box">
                        <h6 class="fw-bold small text-dark mb-2"><i class="fa-solid fa-book-open-reader text-primary me-1"></i> Suggested Upskilling Roadmap:</h6>
                        <ul class="list-unstyled d-flex flex-column gap-2 small mb-0 text-secondary">
                            <?php foreach (array_slice($aiMatch['gap_recommendations'], 0, 2) as $gap): ?>
                                <li>
                                    <strong><?= htmlspecialchars($gap['skill']) ?>:</strong> <?= htmlspecialchars($gap['action']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    renderCompetencyRadar(
        'jobCompetencyRadar',
        <?= json_encode(array_keys($aiMatch['radar_metrics'])) ?>,
        <?= json_encode(array_values($aiMatch['radar_metrics'])) ?>,
        'Competency Overlap'
    );
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
