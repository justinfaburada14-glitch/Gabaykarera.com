<?php
/**
 * ============================================================================
 * GABAYKARERA AI - APPLICANT LEARNING & UPSKILLING HUB
 * File: applicant/learning-hub.php
 * Purpose: Curated learning paths, micro-credentials, and skill gap roadmaps
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';
require_once __DIR__ . '/../classes/CareerRecommender.php';

Auth::requireAuth(['applicant']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];

// Handle 1-Click Master Skill Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_learned_skill'])) {
    $skillName = trim($_POST['skill_name'] ?? '');
    $proficiency = $_POST['proficiency'] ?? 'Intermediate';
    $category = $_POST['category'] ?? 'Technical';

    if (!empty($skillName)) {
        $chk = $pdo->prepare("SELECT id FROM applicant_skills WHERE user_id = ? AND LOWER(skill_name) = LOWER(?)");
        $chk->execute([$userId, $skillName]);
        if (!$chk->fetch()) {
            $ins = $pdo->prepare("INSERT INTO applicant_skills (user_id, skill_name, proficiency, years_experience, category) VALUES (?, ?, ?, 1, ?)");
            $ins->execute([$userId, $skillName, $proficiency, $category]);
            CompetencyMatcher::syncApplicantApplications($pdo, $userId);
            $_SESSION['flash_success'] = "🎉 Congratulations! '{$skillName}' has been added to your competency profile and your job match scores have increased!";
        } else {
            // Update proficiency if already present
            $upd = $pdo->prepare("UPDATE applicant_skills SET proficiency = ? WHERE user_id = ? AND LOWER(skill_name) = LOWER(?)");
            $upd->execute([$proficiency, $userId, $skillName]);
            CompetencyMatcher::syncApplicantApplications($pdo, $userId);
            $_SESSION['flash_success'] = "Competency '{$skillName}' updated to {$proficiency} level! Match scores have been recalculated.";
        }
    }
    header("Location: /GabayKarera/applicant/learning-hub.php");
    exit;
}

// Fetch user profile and skills
$pStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
$pStmt->execute([$userId]);
$profile = $pStmt->fetch() ?: [];

$sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ?");
$sStmt->execute([$userId]);
$userSkills = $sStmt->fetchAll() ?: [];
$userSkillNames = array_map(function($s) {
    return strtolower(trim($s['skill_name']));
}, $userSkills);

// Get career recommendations to extract top missing skills
$recommendations = CareerRecommender::recommendCareers($pdo, $userId);

// Fetch open jobs to extract high-demand skills
$jobStmt = $pdo->query("SELECT required_skills FROM jobs WHERE status = 'Open'");
$allReqSkills = [];
while ($row = $jobStmt->fetch()) {
    $parts = array_map('trim', explode(',', $row['required_skills']));
    foreach ($parts as $p) {
        if (!empty($p)) $allReqSkills[$p] = ($allReqSkills[$p] ?? 0) + 1;
    }
}
arsort($allReqSkills);

// Curated comprehensive course library database
$courseLibrary = [
    'Docker' => [
        'category' => 'Cloud & DevOps',
        'difficulty' => 'Intermediate',
        'estimated_time' => '10 Hours',
        'description' => 'Master containerization, Dockerfiles, multi-container applications with Docker Compose, and microservice deployment.',
        'courses' => [
            ['title' => 'Docker for Developers (FreeCodeCamp)', 'provider' => 'YouTube / FreeCodeCamp', 'url' => 'https://www.youtube.com', 'type' => 'Free Video Course'],
            ['title' => 'Docker & Kubernetes: The Practical Guide', 'provider' => 'Udemy / Coursera', 'url' => 'https://www.coursera.org', 'type' => 'Interactive Course']
        ],
        'project_idea' => 'Containerize a PHP & MySQL web application and deploy it locally with docker-compose.'
    ],
    'AWS' => [
        'category' => 'Cloud Computing',
        'difficulty' => 'Intermediate to Advanced',
        'estimated_time' => '25 Hours',
        'description' => 'Learn core cloud architecture including EC2, S3, RDS, Lambda serverless, and IAM security.',
        'courses' => [
            ['title' => 'AWS Certified Cloud Practitioner Essentials', 'provider' => 'AWS Skill Builder', 'url' => 'https://aws.amazon.com/training', 'type' => 'Free Official Course'],
            ['title' => 'AWS for Beginners', 'provider' => 'Coursera', 'url' => 'https://www.coursera.org', 'type' => 'Certification Path']
        ],
        'project_idea' => 'Host a dynamic website on an AWS EC2 instance connected to an Amazon RDS database.'
    ],
    'React' => [
        'category' => 'Frontend Development',
        'difficulty' => 'Beginner to Intermediate',
        'estimated_time' => '20 Hours',
        'description' => 'Build fast, responsive Single Page Applications (SPAs) with components, hooks, state management, and API integrations.',
        'courses' => [
            ['title' => 'React 18 Full Course - Beginner to Pro', 'provider' => 'FreeCodeCamp', 'url' => 'https://www.freecodecamp.org', 'type' => 'Free Curriculum'],
            ['title' => 'Meta Front-End Developer Certificate', 'provider' => 'Coursera / Meta', 'url' => 'https://www.coursera.org', 'type' => 'Professional Certificate']
        ],
        'project_idea' => 'Build an interactive job board with search filters and real-time state updates in React.'
    ],
    'Python' => [
        'category' => 'Programming & Data',
        'difficulty' => 'Beginner',
        'estimated_time' => '15 Hours',
        'description' => 'Develop general-purpose scripting, automated workflows, backend APIs, and data science notebooks.',
        'courses' => [
            ['title' => 'Python for Everybody Specialization', 'provider' => 'University of Michigan / Coursera', 'url' => 'https://www.coursera.org', 'type' => 'Specialization'],
            ['title' => 'Scientific Computing with Python', 'provider' => 'FreeCodeCamp', 'url' => 'https://www.freecodecamp.org', 'type' => 'Free Certificate']
        ],
        'project_idea' => 'Write a web scraper that extracts job postings and performs text analytics.'
    ],
    'Power BI' => [
        'category' => 'Data Analytics & BI',
        'difficulty' => 'Beginner to Intermediate',
        'estimated_time' => '12 Hours',
        'description' => 'Create interactive business intelligence dashboards, DAX queries, and KPI reports for management.',
        'courses' => [
            ['title' => 'Microsoft Power BI Data Analyst (PL-300)', 'provider' => 'Microsoft Learn', 'url' => 'https://learn.microsoft.com', 'type' => 'Official Learning Path'],
            ['title' => 'Power BI for Business Analytics', 'provider' => 'edX', 'url' => 'https://www.edx.org', 'type' => 'Free Course']
        ],
        'project_idea' => 'Build an executive sales & HR metrics dashboard with drill-down charts in Power BI.'
    ],
    'Figma' => [
        'category' => 'UI/UX Design',
        'difficulty' => 'Beginner to Intermediate',
        'estimated_time' => '10 Hours',
        'description' => 'Design user-centric UI layouts, high-fidelity prototypes, wireframes, and collaborative design systems.',
        'courses' => [
            ['title' => 'Google UX Design Professional Certificate', 'provider' => 'Google / Coursera', 'url' => 'https://www.coursera.org', 'type' => 'Industry Certificate'],
            ['title' => 'Figma UI/UX Design Essentials', 'provider' => 'YouTube / Figma Official', 'url' => 'https://www.youtube.com', 'type' => 'Free Tutorials']
        ],
        'project_idea' => 'Design a full mobile app UI kit with components, auto-layout, and interactive click-through prototype.'
    ],
    'RESTful APIs' => [
        'category' => 'Backend Engineering',
        'difficulty' => 'Intermediate',
        'estimated_time' => '8 Hours',
        'description' => 'Design scalable HTTP endpoints, JSON data payloads, authentication with JWT, and API documentation.',
        'courses' => [
            ['title' => 'REST API Design & Best Practices', 'provider' => 'FreeCodeCamp', 'url' => 'https://www.freecodecamp.org', 'type' => 'Free Guide'],
            ['title' => 'Building Secure RESTful Web APIs in PHP', 'provider' => 'Coursera', 'url' => 'https://www.coursera.org', 'type' => 'Hands-on Lab']
        ],
        'project_idea' => 'Build a REST API in PHP that handles user registration, token authentication, and CRUD operations.'
    ],
    'Git & GitHub' => [
        'category' => 'Version Control & Tools',
        'difficulty' => 'Beginner',
        'estimated_time' => '6 Hours',
        'description' => 'Collaborate effectively with branch workflows, pull requests, merge conflict resolutions, and CI/CD actions.',
        'courses' => [
            ['title' => 'Version Control with Git', 'provider' => 'Atlassian / Coursera', 'url' => 'https://www.coursera.org', 'type' => 'Free Course'],
            ['title' => 'GitHub Foundations Certification Prep', 'provider' => 'GitHub Skills', 'url' => 'https://skills.github.com', 'type' => 'Interactive Labs']
        ],
        'project_idea' => 'Publish your portfolio project on GitHub with a clean README, issues, and branch protection.'
    ]
];

// Determine skills needed by user
$personalizedGaps = [];
foreach ($recommendations as $rec) {
    foreach ($rec['missing_skills'] as $msk) {
        $cleanMsk = trim($msk);
        if (!in_array(strtolower($cleanMsk), $userSkillNames) && !isset($personalizedGaps[$cleanMsk])) {
            $personalizedGaps[$cleanMsk] = $rec['title'];
        }
    }
}

$pageTitle = "AI Learning & Upskilling Hub - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: APPLICANT LEARNING & UPSKILLING HUB -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. HERO BANNER ==================== -->
    <div class="card card-modern p-4 p-md-5 border-0 text-white mb-4 shadow-lg" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="badge bg-white text-primary fw-bold px-3 py-2 rounded-pill mb-3">
                    <i class="fa-solid fa-graduation-cap me-1 text-warning"></i> AI Career Growth & Upskilling Roadmap
                </div>
                <h2 class="fw-bold mb-2">Turn Competency Gaps into Career Strengths</h2>
                <p class="lead opacity-90 mb-0" style="font-size: 1.05rem;">
                    GabayKarera AI has mapped personalized micro-learning paths for your target careers. Complete any skill below and click <strong>"Mark as Mastered"</strong> to instantly increase your job match scores!
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <div class="bg-white text-dark p-3 rounded-4 shadow d-inline-block text-center" style="min-width: 180px;">
                    <div class="display-6 fw-bold text-primary mb-0"><?= count($userSkills) ?></div>
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Competencies Mastered</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== 2. PERSONALIZED SKILL GAPS CALLOUT ==================== -->
    <?php if (!empty($personalizedGaps)): ?>
        <div class="card card-modern p-4 border-0 mb-4 shadow-sm border-start border-5 border-warning bg-white">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-bullseye text-warning fs-4"></i>
                <h5 class="fw-bold mb-0 text-dark">High-Impact Skills Needed for Your Top Career Targets</h5>
            </div>
            <p class="small text-muted mb-3">
                Learning these competencies will provide the highest boost to your match rating across your recommended career paths:
            </p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (array_slice($personalizedGaps, 0, 5, true) as $gapSkill => $targetRole): ?>
                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle p-2 px-3 fs-7">
                        <i class="fa-solid fa-arrow-trend-up text-danger me-1"></i> <strong><?= htmlspecialchars($gapSkill) ?></strong>
                        <small class="text-muted ms-1">(for <?= htmlspecialchars($targetRole) ?>)</small>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ==================== 3. CURATED LEARNING MODULES GRID ==================== -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-book-bookmark text-primary me-2"></i>Curated Learning Modules & Upskilling Paths</h5>
        <span class="text-muted small">Free resources, project challenges & certifications</span>
    </div>

    <div class="row g-4">
        <?php foreach ($courseLibrary as $skillName => $data): ?>
            <?php
                $isAlreadyLearned = in_array(strtolower($skillName), $userSkillNames);
            ?>
            <div class="col-lg-6">
                <div class="card card-modern h-100 p-4 border-0 shadow-sm d-flex flex-column justify-content-between <?= $isAlreadyLearned ? 'border-top border-4 border-success' : '' ?>">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-light text-secondary border mb-1"><?= htmlspecialchars($data['category']) ?></span>
                                <h4 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                                    <?= htmlspecialchars($skillName) ?>
                                    <?php if ($isAlreadyLearned): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-7"><i class="fa-solid fa-check-circle me-1"></i> Mastered</span>
                                    <?php endif; ?>
                                </h4>
                            </div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="fa-regular fa-clock me-1"></i> <?= htmlspecialchars($data['estimated_time']) ?>
                            </span>
                        </div>

                        <p class="text-secondary small mb-3">
                            <?= htmlspecialchars($data['description']) ?>
                        </p>

                        <!-- Recommended Resources -->
                        <h6 class="fw-bold small text-dark mb-2"><i class="fa-solid fa-graduation-cap text-primary me-1"></i> Recommended Online Courses:</h6>
                        <div class="d-flex flex-column gap-2 mb-3">
                            <?php foreach ($data['courses'] as $c): ?>
                                <div class="p-2 px-3 bg-light rounded-3 border d-flex justify-content-between align-items-center small">
                                    <div>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($c['title']) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($c['provider']) ?> &bull; <?= htmlspecialchars($c['type']) ?></small>
                                    </div>
                                    <a href="<?= htmlspecialchars($c['url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2">
                                        Open <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Practical Project Challenge -->
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <small class="fw-bold text-primary d-block mb-1"><i class="fa-solid fa-laptop-code me-1"></i> Practical Portfolio Challenge:</small>
                            <small class="text-secondary"><?= htmlspecialchars($data['project_idea']) ?></small>
                        </div>
                    </div>

                    <!-- 1-Click Master Button Form -->
                    <div class="pt-3 border-top mt-2">
                        <form method="POST" action="/GabayKarera/applicant/learning-hub.php" class="d-flex align-items-center justify-content-between gap-2">
                            <input type="hidden" name="add_learned_skill" value="1">
                            <input type="hidden" name="skill_name" value="<?= htmlspecialchars($skillName) ?>">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($data['category']) ?>">
                            
                            <div class="d-flex align-items-center gap-2">
                                <label class="small text-muted fw-semibold text-nowrap">Proficiency:</label>
                                <select name="proficiency" class="form-select form-select-sm" style="width: 130px;">
                                    <option value="Intermediate" selected>Intermediate</option>
                                    <option value="Advanced">Advanced</option>
                                    <option value="Expert">Expert</option>
                                    <option value="Beginner">Beginner</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-sm <?= $isAlreadyLearned ? 'btn-outline-success' : 'btn-primary-custom' ?> px-3">
                                <i class="fa-solid fa-check me-1"></i> <?= $isAlreadyLearned ? 'Update Level' : 'Mark as Mastered' ?>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
