<?php
/**
 * ============================================================================
 * GABAYKARERA AI - APPLICANT JOB EXPLORER & MATCH SCORING
 * File: applicant/jobs.php
 * Purpose: Real-time candidate-to-job matching, filters, and competency gap previews
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['applicant']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];

// ============================================================================
// 1. DATA FETCHING: APPLICANT PROFILE & SKILLS
// ============================================================================
$pStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
$pStmt->execute([$userId]);
$profile = $pStmt->fetch() ?: [];

$sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ?");
$sStmt->execute([$userId]);
$skills = $sStmt->fetchAll() ?: [];

$applicantData = ['profile' => $profile, 'skills' => $skills];

// ============================================================================
// 2. QUERY JOBS WITH SEARCH & FILTER PARAMETERS
// ============================================================================
$searchQuery = trim($_GET['q'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$minMatch = (float)($_GET['min_match'] ?? 0);

$sql = "SELECT j.*, u.full_name as employer_name, u.company_verification_status FROM jobs j JOIN users u ON j.employer_id = u.id WHERE j.status = 'Open'";
$params = [];

if (!empty($searchQuery)) {
    $sql .= " AND (j.title LIKE ? OR j.company_name LIKE ? OR j.description LIKE ? OR j.required_skills LIKE ?)";
    $term = "%{$searchQuery}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($filterType)) {
    $sql .= " AND j.job_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY j.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// ============================================================================
// 3. AI SCORING: COMPUTE REAL-TIME MATCH SCORES
// ============================================================================
$processedJobs = [];
foreach ($jobs as $job) {
    $matchResult = CompetencyMatcher::matchApplicantToJob($applicantData, $job);
    $job['ai_match'] = $matchResult;
    
    if ($minMatch > 0 && $matchResult['match_score'] < $minMatch) {
        continue;
    }
    $processedJobs[] = $job;
}

// Sort by match score descending
usort($processedJobs, function($a, $b) {
    return $b['ai_match']['match_score'] <=> $a['ai_match']['match_score'];
});

$pageTitle = "Explore Jobs & AI Match Scores - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: APPLICANT JOB EXPLORER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. SEARCH & FILTER BAR ==================== -->
    <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
        <form method="GET" action="/GabayKarera/applicant/jobs.php">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5 col-md-6">
                    <label class="form-label small fw-bold mb-1">Search Position, Skill, or Employer</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="q" class="form-control py-2" placeholder="e.g. PHP, React, Data Analyst, TechCorp" value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                </div>

                <div class="col-lg-3 col-md-3 col-6">
                    <label class="form-label small fw-bold mb-1">Job Type</label>
                    <select name="type" class="form-select py-2">
                        <option value="">All Types</option>
                        <option value="Full-Time" <?= $filterType === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                        <option value="Part-Time" <?= $filterType === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                        <option value="Contract" <?= $filterType === 'Contract' ? 'selected' : '' ?>>Contract</option>
                        <option value="Internship" <?= $filterType === 'Internship' ? 'selected' : '' ?>>Internship</option>
                        <option value="Remote" <?= $filterType === 'Remote' ? 'selected' : '' ?>>Remote</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small fw-bold mb-1">Min Match %</label>
                    <select name="min_match" class="form-select py-2">
                        <option value="0" <?= $minMatch == 0 ? 'selected' : '' ?>>Any Match</option>
                        <option value="75" <?= $minMatch == 75 ? 'selected' : '' ?>>75%+ Strong</option>
                        <option value="85" <?= $minMatch == 85 ? 'selected' : '' ?>>85%+ Top Match</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-12">
                    <button type="submit" class="btn btn-primary-custom w-100 py-2">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ==================== 2. MATCHED JOB LISTINGS ==================== -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">
            Available Positions (<?= count($processedJobs) ?>)
        </h5>
        <span class="text-muted small">Real-time match scoring applied</span>
    </div>


    <!-- Job Cards List -->
    <?php if (empty($processedJobs)): ?>
        <div class="card card-modern p-5 text-center border-0">
            <i class="fa-solid fa-folder-open fs-1 text-secondary opacity-50 mb-3"></i>
            <h5 class="fw-bold">No job openings found</h5>
            <p class="text-muted small">Try broadening your search query or adjusting the minimum match filter.</p>
            <a href="/GabayKarera/applicant/jobs.php" class="btn btn-outline-custom mx-auto">Reset Filters</a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($processedJobs as $job): ?>
                <?php
                    $matchScore = $job['ai_match']['match_score'];
                    $scoreClass = $matchScore >= 80 ? 'match-high' : ($matchScore >= 65 ? 'match-med' : 'match-low');
                    $reqSkills = array_map('trim', explode(',', $job['required_skills']));
                ?>
                <div class="card card-modern p-4 border-0 hover-shadow transition">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <!-- Match Score Meter -->
                            <div class="match-score-circle <?= $scoreClass ?> flex-shrink-0">
                                <span class="fs-5"><?= CompetencyMatcher::formatScore($matchScore) ?></span>
                                <small style="font-size: 0.55rem;">AI FIT</small>
                            </div>

                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-light text-secondary border"><?= htmlspecialchars($job['job_type']) ?></span>
                                    <span class="badge <?= $job['ai_match']['rating_badge_class'] ?>"><?= $job['ai_match']['rating_label'] ?></span>
                                </div>

                                <h5 class="fw-bold mb-1">
                                    <a href="/GabayKarera/applicant/job-details.php?id=<?= $job['id'] ?>" class="text-dark text-decoration-none hover-primary">
                                        <?= htmlspecialchars($job['title']) ?>
                                    </a>
                                </h5>

                                <div class="text-muted small mb-2 d-flex align-items-center flex-wrap gap-2">
                                    <span><i class="fa-regular fa-building me-1"></i> <strong><?= htmlspecialchars($job['company_name']) ?></strong></span>
                                    <?php if (($job['company_verification_status'] ?? 'pending') === 'verified'): ?>
                                        <span class="badge bg-success text-white" style="font-size: 0.65rem;" title="Verified by Administration"><i class="fa-solid fa-shield-check me-1"></i> Verified</span>
                                    <?php endif; ?>
                                    <span>&bull; <i class="fa-solid fa-location-dot ms-1 me-1"></i> <?= htmlspecialchars($job['location']) ?> &bull;</span>
                                    <span><i class="fa-solid fa-briefcase ms-1 me-1"></i> <?= $job['experience_required'] ?> yrs exp req.</span>
                                </div>

                                <p class="text-secondary small line-clamp-2 mb-2">
                                    <?= htmlspecialchars(substr($job['description'], 0, 150)) ?>...
                                </p>

                                <div>
                                    <small class="text-muted fw-semibold me-1">Matched Skills:</small>
                                    <?php if (!empty($job['ai_match']['matched_required_skills'])): ?>
                                        <?php foreach (array_slice($job['ai_match']['matched_required_skills'], 0, 4) as $msk): ?>
                                            <span class="skill-tag skill-tag-matched py-0 px-2" style="font-size: 0.75rem;"><i class="fa-solid fa-check text-success"></i> <?= htmlspecialchars($msk['skill']) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">None matched yet</span>
                                    <?php endif; ?>

                                    <?php if (!empty($job['ai_match']['missing_required_skills'])): ?>
                                        <small class="text-muted fw-semibold ms-2 me-1">Gaps:</small>
                                        <?php foreach (array_slice($job['ai_match']['missing_required_skills'], 0, 2) as $msk): ?>
                                            <span class="skill-tag skill-tag-missing py-0 px-2" style="font-size: 0.75rem;"><i class="fa-solid fa-arrow-up text-danger"></i> <?= htmlspecialchars($msk) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-md-end flex-shrink-0">
                            <div class="fw-bold text-success mb-2" style="font-size: 1.05rem;">
                                ₱<?= number_format($job['salary_min'], 0) ?> - ₱<?= number_format($job['salary_max'], 0) ?>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Monthly Compensation</small>
                            </div>
                            <a href="/GabayKarera/applicant/job-details.php?id=<?= $job['id'] ?>" class="btn btn-primary-custom px-3">
                                View Competency Fit & Apply <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
