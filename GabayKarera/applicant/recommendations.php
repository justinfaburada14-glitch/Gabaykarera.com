<?php
/**
 * ============================================================================
 * GABAYKARERA AI - APPLICANT AI CAREER RECOMMENDATIONS & RADAR
 * File: applicant/recommendations.php
 * Purpose: Career pathway suitability index, radar chart metrics, and gap upskilling
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';
require_once __DIR__ . '/../classes/CareerRecommender.php';

Auth::requireAuth(['applicant']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];

// ============================================================================
// 1. DATA FETCHING: CAREER RECOMMENDATIONS & PROFILE METRICS
// ============================================================================
$recommendations = CareerRecommender::recommendCareers($pdo, $userId);

$pStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
$pStmt->execute([$userId]);
$profile = $pStmt->fetch() ?: [];

$sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ?");
$sStmt->execute([$userId]);
$skills = $sStmt->fetchAll() ?: [];

// ============================================================================
// 2. RADAR CHART DATA VECTOR GENERATION
// ============================================================================
$topRec = $recommendations[0] ?? null;
$radarLabels = ['Core Tech Skills', 'Domain Alignment', 'Practical Experience', 'Learning Agility', 'Tool Mastery'];

$profWeights = ['Beginner' => 60, 'Intermediate' => 85, 'Advanced' => 95, 'Expert' => 100];
$avgProf = 75;
if (!empty($skills)) {
    $sum = 0;
    foreach ($skills as $sk) {
        $sum += $profWeights[$sk['proficiency'] ?? 'Intermediate'] ?? 75;
    }
    $avgProf = round($sum / count($skills));
}

$radarData = [
    min(100, max(25, round(($avgProf * 0.6) + (count($skills) * 5)))),
    !empty($profile['field_of_study']) ? 92 : 65,
    min(100, max(30, round(((float)($profile['experience_years'] ?? 0) * 20) + 40))),
    min(100, max(60, 75 + count($skills) * 2)),
    min(100, max(30, round(($avgProf * 0.5) + (count($skills) * 6))))
];

$pageTitle = "AI Career Recommendations & Competency Analysis - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: APPLICANT AI CAREER GUIDE CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. PAGE HEADER BANNER ==================== -->
    <div class="bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                <i class="fa-solid fa-brain me-1 text-primary"></i> AI Competency Analysis Engine
            </span>
        </div>
        <h3 class="fw-bold mb-1">Intelligent Career Guidance & Pathway Recommendations</h3>
        <p class="text-muted mb-0">
            Based on your registered competencies, educational background, and interests, our AI system has evaluated industry career tracks to recommend your highest-potential roles.
        </p>
    </div>

    <!-- ==================== 2. MAIN CAREER & RADAR GRID ==================== -->
    <div class="row g-4">
        
        <!-- Left: Competency Radar Chart & Upskilling Summary -->
        <div class="col-lg-4">
            <div class="card card-modern p-4 border-0 mb-4">
                <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-chart-pie text-primary me-2"></i>My Competency Profile</h6>
                
                <div style="height: 280px; position: relative;">
                    <canvas id="competencyRadarChart"></canvas>
                </div>

                <div class="small text-muted text-center mt-2">
                    Multi-dimensional analysis of your technical, experiential, and domain attributes.
                </div>
            </div>

            <div class="card card-modern p-4 border-0 ai-box">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-lightbulb text-warning me-2"></i>Career Trajectory Advice</h6>
                <p class="small text-muted mb-3">
                    <?php if ($topRec): ?>
                        Your primary competency profile is strongly positioned for <strong><?= htmlspecialchars($topRec['title']) ?></strong> roles. Focus on mastering recommended certifications below to maximize your interview conversion rate.
                    <?php else: ?>
                        Add more skills to your profile to generate detailed career roadmap advice.
                    <?php endif; ?>
                </p>
                <a href="/GabayKarera/applicant/jobs.php" class="btn btn-sm btn-primary-custom w-100">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Browse Matching Jobs
                </a>
            </div>
        </div>

        <!-- Right: Ranked Career Recommendations -->
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Recommended Career Pathways (<?= count($recommendations) ?>)</h5>
                <span class="text-muted small">Sorted by Competency Suitability Index</span>
            </div>

            <div class="d-flex flex-column gap-4">
                <?php foreach ($recommendations as $index => $rec): ?>
                    <?php
                        $score = $rec['match_score'];
                        $badgeClass = $score >= 80 ? 'badge-success-glow' : ($score >= 60 ? 'badge-info-glow' : 'badge-warning-glow');
                        $progressColor = $score >= 80 ? 'bg-success' : ($score >= 60 ? 'bg-primary' : 'bg-warning');
                    ?>
                    <div class="card card-modern p-4 border-0">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-3 mb-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">

                                    <span class="badge bg-light text-secondary border">Rank #<?= $index + 1 ?> &bull; <?= htmlspecialchars($rec['category']) ?></span>
                                </div>
                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($rec['title']) ?></h5>
                                <p class="text-secondary small mb-0"><?= htmlspecialchars($rec['description']) ?></p>
                            </div>

                            <div class="text-sm-end flex-shrink-0">
                                <span class="badge <?= $badgeClass ?> fs-6 px-3 py-2"><?= CompetencyMatcher::formatScore($score) ?> Suitability</span>
                                <div class="progress mt-2" style="height: 6px; width: 120px;">
                                    <div class="progress-bar <?= $progressColor ?>" role="progressbar" style="width: <?= (float)$score ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Industry Data Benchmarks -->
                        <div class="row g-2 mb-3 bg-light p-3 rounded-3 border small">
                            <div class="col-md-6">
                                <strong class="text-secondary">Average Industry Salary:</strong>
                                <span class="text-dark fw-bold d-block"><?= htmlspecialchars($rec['average_salary']) ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong class="text-secondary">Job Market Demand:</strong>
                                <span class="text-success fw-bold d-block"><?= htmlspecialchars($rec['growth_rate']) ?></span>
                            </div>
                        </div>

                        <!-- Matched vs Missing Competencies -->
                        <div class="mb-3">
                            <div class="mb-2">
                                <small class="fw-bold text-success d-block mb-1">
                                    <i class="fa-solid fa-circle-check me-1"></i> Matched Competencies (<?= count($rec['matched_skills']) ?>):
                                </small>
                                <?php if (!empty($rec['matched_skills'])): ?>
                                    <?php foreach ($rec['matched_skills'] as $msk): ?>
                                        <span class="skill-tag skill-tag-matched"><i class="fa-solid fa-check text-success"></i> <?= htmlspecialchars($msk) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small">No direct match recorded yet.</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($rec['missing_skills'])): ?>
                                <div class="mt-2">
                                    <small class="fw-bold text-danger d-block mb-1">
                                        <i class="fa-solid fa-arrow-trend-up me-1"></i> Missing Competencies to Upskill (<?= count($rec['missing_skills']) ?>):
                                    </small>
                                    <?php foreach ($rec['missing_skills'] as $msk): ?>
                                        <span class="skill-tag skill-tag-missing"><i class="fa-solid fa-circle-plus text-danger"></i> <?= htmlspecialchars($msk) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recommended Certifications -->
                        <?php if (!empty($rec['recommended_certifications'])): ?>
                            <div class="p-2 border rounded-2 bg-white small mb-3">
                                <i class="fa-solid fa-award text-warning me-1"></i> <strong>Recommended Credentials:</strong> 
                                <span class="text-muted"><?= htmlspecialchars($rec['recommended_certifications']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="/GabayKarera/applicant/jobs.php?q=<?= urlencode($rec['title']) ?>" class="btn btn-sm btn-outline-custom">
                                Explore <?= htmlspecialchars($rec['title']) ?> Openings <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    renderCompetencyRadar(
        'competencyRadarChart',
        <?= json_encode($radarLabels) ?>,
        <?= json_encode($radarData) ?>,
        'Competency Score'
    );
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
