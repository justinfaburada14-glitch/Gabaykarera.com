<?php
/**
 * ============================================================================
 * GABAYKARERA AI - PUBLIC LANDING PAGE
 * File: index.php
 * Purpose: Hero showcase, problem statement, live AI match preview, and jobs
 * ============================================================================
 */

require_once __DIR__ . '/config/database.php';
$pdo = Database::getInstance();

$pageTitle = "GabayKarera AI - Intelligent HR Career Recommendation & Competency Matching";
require_once __DIR__ . '/includes/header.php';

// Fetch recent open jobs
$jobStmt = $pdo->query("SELECT j.*, u.full_name as hr_name FROM jobs j JOIN users u ON j.employer_id = u.id WHERE j.status = 'Open' ORDER BY j.created_at DESC LIMIT 4");
$recentJobs = $jobStmt->fetchAll();

// Count system stats
$totalJobs = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'Open'")->fetchColumn();
$totalApplicants = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'applicant'")->fetchColumn();
$totalMatches = $pdo->query("SELECT COUNT(*) FROM applications WHERE match_score >= 80")->fetchColumn();
?>

<!-- ========================================================================== -->
<!-- SECTION 1: HERO BANNER & LIVE AI MATCHING PREVIEW -->
<!-- ========================================================================== -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            
            <!-- Left Hero Pitch -->
            <div class="col-lg-7">
                <div class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill fw-semibold mb-3">
                    <i class="fa-solid fa-sparkles me-1 text-warning"></i> AI-Powered Competency Matching Platform
                </div>
                <h1 class="display-4 fw-extrabold text-white mb-3" style="line-height: 1.15;">
                  GabayKarera AI: <span class="text-info">An Intelligent HR-Based Career Recommendation and Applicant-Employer Matching System</span>
                </h1>
                <p class="lead text-light opacity-75 mb-4">
                    Eliminate guesswork in your job hunt. GabayKarera AI analyzes your skills, experience, and education to match you with suitable jobs, highlight skill gaps, and help HR find qualified talent effortlessly.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/GabayKarera/register.php" class="btn btn-primary btn-lg px-4 py-3 fw-bold rounded-pill shadow-lg">
                        <i class="fa-solid fa-user-plus me-2"></i> Create Free Profile
                    </a>
                    <a href="/GabayKarera/login.php" class="btn btn-outline-light btn-lg px-4 py-3 fw-bold rounded-pill">
                        <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In to Account
                    </a>
                </div>
            </div>

            <!-- Right Interactive AI Match Preview Card -->
            <div class="col-lg-5">
                <div class="card card-modern p-4 border-0 shadow-lg bg-white text-dark">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success-subtle text-success p-2 rounded-circle"><i class="fa-solid fa-robot"></i></span>
                            <div>
                                <h6 class="mb-0 fw-bold">Live AI Matching Preview</h6>
                                <small class="text-muted">Multi-factor Competency Engine</small>
                            </div>
                        </div>
                        <span class="badge badge-success-glow">99.9% Match</span>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark">Full Stack Web Developer</span>
                            <span class="badge bg-primary text-white">Full-Time</span>
                        </div>
                        <small class="text-muted d-block mb-2"><i class="fa-regular fa-building me-1"></i> TechCorp Global &bull; BGC, Taguig</small>
                        
                        <div class="mb-2">
                            <small class="fw-semibold text-secondary d-block mb-1">Competency Overlap:</small>
                            <span class="skill-tag skill-tag-matched"><i class="fa-solid fa-check text-success"></i> PHP</span>
                            <span class="skill-tag skill-tag-matched"><i class="fa-solid fa-check text-success"></i> JavaScript</span>
                            <span class="skill-tag skill-tag-matched"><i class="fa-solid fa-check text-success"></i> MySQL</span>
                            <span class="skill-tag skill-tag-missing"><i class="fa-solid fa-arrow-up text-danger"></i> Docker (Upskill)</span>
                        </div>
                    </div>

                    <div class="row g-2 text-center small">
                        <div class="col-4">
                            <div class="p-2 border rounded-2 bg-white">
                                <div class="fw-bold text-primary">100%</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Skills Fit</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded-2 bg-white">
                                <div class="fw-bold text-success">92%</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Experience</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded-2 bg-white">
                                <div class="fw-bold text-warning">A+</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Domain Fit</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- SECTION 2: PLATFORM STATS & METRICS -->
<!-- ========================================================================== -->
<section class="py-4 border-bottom bg-white">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-md-4">
                <div class="stat-box justify-content-center">
                    <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-briefcase"></i></div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?= $totalJobs ?>+ Active</h3>
                        <p class="text-muted small mb-0">Verified Job Opportunities</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box justify-content-center">
                    <div class="stat-icon stat-icon-green"><i class="fa-solid fa-users"></i></div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?= $totalApplicants ?>+ Profiles</h3>
                        <p class="text-muted small mb-0">Job Seekers & Graduates</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box justify-content-center">
                    <div class="stat-icon stat-icon-purple"><i class="fa-solid fa-bullseye"></i></div>
                    <div class="text-start">
                        <h3 class="fw-bold mb-0"><?= $totalMatches ?> Matches</h3>
                        <p class="text-muted small mb-0">High-Precision AI Match Scores</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- SECTION 3: PROBLEM STATEMENT & VALUE PROPOSITION -->
<!-- ========================================================================== -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <h6 class="text-primary fw-bold text-uppercase">Addressing Real-World Recruitment Challenges</h6>
            <h2 class="fw-bold">How GabayKarera AI Solves Job-Mismatch</h2>
            <p class="text-muted">
                Bridging the gap between job seekers who struggle to identify matching careers and HR teams overwhelmed by mismatched applications.
            </p>
        </div>

        <div class="row g-4">
            <!-- Job Seeker Benefits -->
            <div class="col-md-6">
                <div class="card card-modern h-100 p-4 border-start border-4 border-primary">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-user-graduate"></i></div>
                        <div>
                            <h5 class="fw-bold mb-0">For Job Seekers & Students</h5>
                            <small class="text-muted">Personalized Career Trajectory</small>
                        </div>
                    </div>
                    <ul class="list-unstyled d-flex flex-column gap-3 text-secondary mb-0">
                        <li class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-primary mt-1"></i>
                            <div><strong>AI Competency Profile:</strong> Evaluate your skills, experience, and educational background in one smart portfolio.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-primary mt-1"></i>
                            <div><strong>Career Match Scoring:</strong> Receive real-time percentage fit scores for every job opening to apply with confidence.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-primary mt-1"></i>
                            <div><strong>Actionable Skill Gap Roadmap:</strong> Instantly discover missing competencies and receive concrete recommendations on courses and certifications to upskill.</div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- HR Employer Benefits -->
            <div class="col-md-6">
                <div class="card card-modern h-100 p-4 border-start border-4 border-success">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon stat-icon-green"><i class="fa-solid fa-building-user"></i></div>
                        <div>
                            <h5 class="fw-bold mb-0">For HR Personnel & Employers</h5>
                            <small class="text-muted">Automated Screening & Candidate Ranking</small>
                        </div>
                    </div>
                    <ul class="list-unstyled d-flex flex-column gap-3 text-secondary mb-0">
                        <li class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-success mt-1"></i>
                            <div><strong>Automated Candidate Ranking:</strong> Instantly view applicants ranked by competency compatibility with your specific job criteria.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-success mt-1"></i>
                            <div><strong>Detailed Competency Breakdown:</strong> Examine deep radar comparisons across technical skills, experience depth, and domain knowledge.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fa-solid fa-circle-check text-success mt-1"></i>
                            <div><strong>Empowered Decision Making:</strong> Save hours of manual resume review while retaining full hiring discretion and interview workflows.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- SECTION 4: FEATURED JOB OPENINGS -->
<!-- ========================================================================== -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Featured Job Opportunities</h2>
                <p class="text-muted mb-0">Explore positions actively hiring with AI competency matching</p>
            </div>
            <a href="/GabayKarera/applicant/jobs.php" class="btn btn-outline-custom mt-3 mt-md-0">
                View All Jobs <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($recentJobs as $job): ?>
                <?php
                    $reqSkills = array_slice(array_map('trim', explode(',', $job['required_skills'])), 0, 4);
                ?>
                <div class="col-md-6 col-lg-6">
                    <div class="card card-modern card-interactive h-100 p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-primary-subtle text-primary mb-2"><?= htmlspecialchars($job['job_type']) ?></span>
                                    <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($job['title']) ?></h5>
                                    <div class="text-muted small">
                                        <i class="fa-regular fa-building me-1"></i> <?= htmlspecialchars($job['company_name']) ?> &bull; 
                                        <i class="fa-solid fa-location-dot ms-1 me-1"></i> <?= htmlspecialchars($job['location']) ?>
                                    </div>
                                </div>
                                <div class="bg-light p-2 rounded text-end">
                                    <div class="fw-bold text-success" style="font-size: 0.95rem;">
                                        ₱<?= number_format($job['salary_min'], 0) ?> - ₱<?= number_format($job['salary_max'], 0) ?>
                                    </div>
                                    <small class="text-muted">/ month</small>
                                </div>
                            </div>

                            <p class="text-secondary small line-clamp-2 mb-3">
                                <?= htmlspecialchars(substr($job['description'], 0, 140)) ?>...
                            </p>

                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Required Competencies:</small>
                                <?php foreach ($reqSkills as $sk): ?>
                                    <span class="skill-tag"><i class="fa-solid fa-tag text-primary" style="font-size: 0.7rem;"></i> <?= htmlspecialchars($sk) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
                            <span class="small text-muted"><i class="fa-regular fa-clock me-1"></i> Posted <?= date('M d, Y', strtotime($job['created_at'])) ?></span>
                            <a href="/GabayKarera/applicant/job-details.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-primary-custom">
                                View Competency Fit <i class="fa-solid fa-chevron-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========================================================================== -->
<!-- SECTION 5: CALL TO ACTION -->
<!-- ========================================================================== -->
<section class="py-5 text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);">
    <div class="container text-center py-4">
        <h2 class="fw-bold mb-3">Ready to Discover Your Ideal Career Path?</h2>
        <p class="lead opacity-75 max-w-600 mx-auto mb-4">
            Join GabayKarera AI today to evaluate your skills, find high-match job opportunities, and bridge skill gaps.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="/GabayKarera/register.php" class="btn btn-light btn-lg px-5 py-3 fw-bold text-primary rounded-pill shadow">
                Get Started as a Job Seeker
            </a>
            <a href="/GabayKarera/register.php?role=hr" class="btn btn-outline-light btn-lg px-5 py-3 fw-bold rounded-pill">
                Register as an Employer / HR
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
