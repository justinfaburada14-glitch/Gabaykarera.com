<?php
/**
 * ============================================================================
 * GABAYKARERA AI - APPLICANT PROFILE & AI SKILL FIT GENERATOR
 * File: applicant/profile.php
 * Purpose: Profile editor, photo/resume upload, auto-NLP extraction, and skill management
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/ResumeParser.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['applicant']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];

// Create upload directories if they don't exist
$avatarDir = __DIR__ . '/../uploads/avatars';
$resumeDir = __DIR__ . '/../uploads/resumes';
if (!is_dir($avatarDir)) @mkdir($avatarDir, 0777, true);
if (!is_dir($resumeDir)) @mkdir($resumeDir, 0777, true);

$aiAnalysisResults = null;

// ============================================================================
// 1. ACTION HANDLER: UPDATE PROFILE, AVATAR & RESUME DOCUMENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $headline = trim($_POST['headline'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $educationLevel = trim($_POST['education_level'] ?? '');
        $fieldOfStudy = trim($_POST['field_of_study'] ?? '');
        $school = trim($_POST['school'] ?? '');
        $graduationYear = !empty($_POST['graduation_year']) ? (int)$_POST['graduation_year'] : null;
        $experienceYears = (isset($_POST['experience_years']) && $_POST['experience_years'] !== '') ? (float)$_POST['experience_years'] : 0.0;
        $currentPosition = trim($_POST['current_position'] ?? '');
        $expectedSalary = !empty($_POST['expected_salary']) ? (float)$_POST['expected_salary'] : null;
        $location = trim($_POST['location'] ?? '');
        $interests = trim($_POST['interests'] ?? '');
        $rawResume = trim($_POST['raw_resume_text'] ?? '');

        // Handle Avatar Picture Upload
        $avatarFilename = null;
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['avatar_file']['tmp_name'];
            $fileName = $_FILES['avatar_file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowedExts)) {
                $avatarFilename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                if (@move_uploaded_file($fileTmp, $avatarDir . '/' . $avatarFilename)) {
                    $uAvatar = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $uAvatar->execute([$avatarFilename, $userId]);
                    $_SESSION['user_avatar'] = $avatarFilename;
                }
            }
        }

        // Handle Resume Document Upload
        $resumeFilename = null;
        $extractedResumeText = '';
        if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['resume_file']['tmp_name'];
            $fileName = $_FILES['resume_file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'docx', 'doc', 'txt'];

            if (in_array($ext, $allowedExts)) {
                $resumeFilename = 'resume_' . $userId . '_' . time() . '.' . $ext;
                if (@move_uploaded_file($fileTmp, $resumeDir . '/' . $resumeFilename)) {
                    // Extract text from uploaded resume
                    $extractedResumeText = ResumeParser::extractTextFromFile($resumeDir . '/' . $resumeFilename, $ext);
                    if (!empty($extractedResumeText)) {
                        $rawResume = $rawResume . "\n\n" . $extractedResumeText;
                    }
                }
            }
        }

        $workHistory = trim($_POST['work_history'] ?? '');
        $certifications = trim($_POST['certifications'] ?? '');
        $portfolioUrl = trim($_POST['portfolio_url'] ?? '');
        $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
        $githubUrl = trim($_POST['github_url'] ?? '');
        $careerGoals = trim($_POST['career_goals'] ?? '');
        $workPreference = trim($_POST['work_preference'] ?? '');
        $academicHonors = trim($_POST['academic_honors'] ?? '');

        // Check if profile exists
        $chkStmt = $pdo->prepare("SELECT id, resume_file FROM applicant_profiles WHERE user_id = ?");
        $chkStmt->execute([$userId]);
        $existing = $chkStmt->fetch();

        $finalResumeFile = $resumeFilename ?: ($existing['resume_file'] ?? null);

        if ($existing) {
            $uStmt = $pdo->prepare("UPDATE applicant_profiles SET headline=?, bio=?, education_level=?, field_of_study=?, school=?, graduation_year=?, experience_years=?, current_position=?, expected_salary=?, location=?, interests=?, raw_resume_text=?, resume_file=?, work_history=?, certifications=?, portfolio_url=?, linkedin_url=?, github_url=?, career_goals=?, work_preference=?, academic_honors=? WHERE user_id=?");
            $uStmt->execute([$headline, $bio, $educationLevel, $fieldOfStudy, $school, $graduationYear, $experienceYears, $currentPosition, $expectedSalary, $location, $interests, $rawResume, $finalResumeFile, $workHistory, $certifications, $portfolioUrl, $linkedinUrl, $githubUrl, $careerGoals, $workPreference, $academicHonors, $userId]);
        } else {
            $iStmt = $pdo->prepare("INSERT INTO applicant_profiles (user_id, headline, bio, education_level, field_of_study, school, graduation_year, experience_years, current_position, expected_salary, location, interests, raw_resume_text, resume_file, work_history, certifications, portfolio_url, linkedin_url, github_url, career_goals, work_preference, academic_honors) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $iStmt->execute([$userId, $headline, $bio, $educationLevel, $fieldOfStudy, $school, $graduationYear, $experienceYears, $currentPosition, $expectedSalary, $location, $interests, $rawResume, $finalResumeFile, $workHistory, $certifications, $portfolioUrl, $linkedinUrl, $githubUrl, $careerGoals, $workPreference, $academicHonors]);
        }

        // Update full name and phone in users table
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($phone) && !preg_match('/^[0-9]{11}$/', $phone)) {
            $_SESSION['flash_error'] = "Phone number must be exactly 11 digits (e.g. 09171234567).";
            header("Location: /GabayKarera/applicant/profile.php");
            exit;
        }

        if (!empty($fullName)) {
            $userUpdate = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            $userUpdate->execute([$fullName, $phone, $userId]);
            $_SESSION['user_name'] = $fullName;
        }

        // Automatically synchronize all submitted applications so HR sees the exact updated score
        CompetencyMatcher::syncApplicantApplications($pdo, $userId);

        // If auto-analysis was requested or resume was uploaded
        if (isset($_POST['run_ai_analysis']) || $resumeFilename !== null) {
            $_SESSION['flash_success'] = "Profile & resume uploaded! AI has analyzed your competencies and synchronized your match scores with HR.";
            header("Location: /GabayKarera/applicant/profile.php?analyze=1#ai-fit-section");
            exit;
        }

        $_SESSION['flash_success'] = "Profile updated successfully! AI matching scores have been recalculated and synchronized with HR.";
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Failed to update profile: " . $e->getMessage();
    }

    header("Location: /GabayKarera/applicant/profile.php");
    exit;
}

// ============================================================================
// 2. ACTION HANDLER: 1-CLICK SAVE EXTRACTED SKILLS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_extracted_skills'])) {
    $skillsToSave = $_POST['extracted_skills'] ?? [];
    $savedCount = 0;

    foreach ($skillsToSave as $sData) {
        $sName = trim($sData['name'] ?? '');
        $sProf = $sData['proficiency'] ?? 'Intermediate';
        $sCat = $sData['category'] ?? 'Technical';
        $sYrs = (int)($sData['years'] ?? 1);

        if (!empty($sName)) {
            $chk = $pdo->prepare("SELECT id, proficiency FROM applicant_skills WHERE user_id = ? AND LOWER(skill_name) = LOWER(?)");
            $chk->execute([$userId, $sName]);
            $existing = $chk->fetch();
            if (!$existing) {
                $ins = $pdo->prepare("INSERT INTO applicant_skills (user_id, skill_name, proficiency, years_experience, category) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$userId, $sName, $sProf, $sYrs, $sCat]);
                $savedCount++;
            } else {
                $upd = $pdo->prepare("UPDATE applicant_skills SET proficiency = ?, category = ?, years_experience = GREATEST(years_experience, ?) WHERE id = ?");
                $upd->execute([$sProf, $sCat, $sYrs, $existing['id']]);
                $savedCount++;
            }
        }
    }

    // Automatically sync applications with new skills
    CompetencyMatcher::syncApplicantApplications($pdo, $userId);

    $_SESSION['flash_success'] = "{$savedCount} AI-extracted competencies have been saved and synchronized with employers!";
    header("Location: /GabayKarera/applicant/profile.php#skills-section");
    exit;
}

// ============================================================================
// 3. ACTION HANDLERS: ADD / EDIT / DELETE INDIVIDUAL SKILL
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_skill'])) {
    $skillName = trim($_POST['skill_name'] ?? '');
    $proficiency = $_POST['proficiency'] ?? 'Intermediate';
    $yearsExp = (int)($_POST['years_experience'] ?? 1);
    $category = $_POST['category'] ?? 'Technical';

    if (!empty($skillName)) {
        $chk = $pdo->prepare("SELECT id FROM applicant_skills WHERE user_id = ? AND LOWER(skill_name) = LOWER(?)");
        $chk->execute([$userId, $skillName]);
        $existing = $chk->fetch();

        if (!$existing) {
            $sIns = $pdo->prepare("INSERT INTO applicant_skills (user_id, skill_name, proficiency, years_experience, category) VALUES (?, ?, ?, ?, ?)");
            $sIns->execute([$userId, $skillName, $proficiency, $yearsExp, $category]);
            
            CompetencyMatcher::syncApplicantApplications($pdo, $userId);
            $_SESSION['flash_success'] = "Skill '{$skillName}' ({$proficiency}) added! All your job match percentages have been updated.";
        } else {
            // Update existing skill proficiency and details
            $sUpd = $pdo->prepare("UPDATE applicant_skills SET proficiency = ?, years_experience = ?, category = ? WHERE id = ?");
            $sUpd->execute([$proficiency, $yearsExp, $category, $existing['id']]);
            
            CompetencyMatcher::syncApplicantApplications($pdo, $userId);
            $_SESSION['flash_success'] = "Skill '{$skillName}' updated to {$proficiency} level! Match percentages have been recalculated across all jobs.";
        }
    }
    header("Location: /GabayKarera/applicant/profile.php#skills-section");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_skill'])) {
    $skillId = (int)($_POST['skill_id'] ?? 0);
    $proficiency = $_POST['proficiency'] ?? 'Intermediate';
    $yearsExp = (int)($_POST['years_experience'] ?? 1);
    $category = $_POST['category'] ?? 'Technical';

    if ($skillId > 0) {
        $uStmt = $pdo->prepare("UPDATE applicant_skills SET proficiency = ?, years_experience = ?, category = ? WHERE id = ? AND user_id = ?");
        $uStmt->execute([$proficiency, $yearsExp, $category, $skillId, $userId]);

        CompetencyMatcher::syncApplicantApplications($pdo, $userId);
        $_SESSION['flash_success'] = "Skill updated to {$proficiency}! All your AI match percentages have been recalculated.";
    }
    header("Location: /GabayKarera/applicant/profile.php#skills-section");
    exit;
}

if (isset($_GET['delete_skill'])) {
    $skillId = (int)$_GET['delete_skill'];
    $delStmt = $pdo->prepare("DELETE FROM applicant_skills WHERE id = ? AND user_id = ?");
    $delStmt->execute([$skillId, $userId]);

    // Automatically sync applications after skill removal
    CompetencyMatcher::syncApplicantApplications($pdo, $userId);

    $_SESSION['flash_success'] = "Skill removed and application scores recomputed.";
    header("Location: /GabayKarera/applicant/profile.php#skills-section");
    exit;
}

// ============================================================================
// 4. DATA FETCHING & AI COMPETENCY RUNNER
// ============================================================================
$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();

$pStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
$pStmt->execute([$userId]);
$profile = $pStmt->fetch() ?: [];

$sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ? ORDER BY category ASC, id DESC");
$sStmt->execute([$userId]);
$skills = $sStmt->fetchAll() ?: [];

$applicantData = ['profile' => $profile, 'skills' => $skills];

// Profile completeness calculation
$completeness = 20; // baseline
if (!empty($profile['headline'])) $completeness += 15;
if (!empty($profile['education_level'])) $completeness += 15;
if (!empty($profile['experience_years']) && $profile['experience_years'] > 0) $completeness += 15;
if (!empty($profile['interests'])) $completeness += 15;
if (count($skills) >= 3) $completeness += 20;
$completeness = min(100, $completeness);

// Live top job match score calculation
$topJobMatch = 0;
$topJobTitle = '';
$openJobsStmt = $pdo->query("SELECT * FROM jobs WHERE status = 'Open' ORDER BY id DESC LIMIT 15");
$openJobsList = $openJobsStmt ? $openJobsStmt->fetchAll() : [];
foreach ($openJobsList as $oj) {
    $mRes = CompetencyMatcher::matchApplicantToJob($applicantData, $oj);
    if ($mRes['match_score'] > $topJobMatch) {
        $topJobMatch = $mRes['match_score'];
        $topJobTitle = $oj['title'];
    }
}

if (isset($_GET['analyze']) || !empty($profile['raw_resume_text'])) {
    $fullAnalysisText = ($profile['raw_resume_text'] ?? '') . ' ' . ($profile['bio'] ?? '') . ' ' . ($profile['interests'] ?? '') . ' ' . ($profile['work_history'] ?? '') . ' ' . ($profile['certifications'] ?? '');
    if (!empty($fullAnalysisText)) {
        $aiAnalysisResults = ResumeParser::analyzeCompetencies($fullAnalysisText, $pdo, $applicantData);
    }
}

$pageTitle = "My Profile & AI Skill Fit Generator - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: APPLICANT PROFILE CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    <div class="row g-4">
        
        <!-- ==================== 5. LEFT COLUMN: PROFILE CARD & AI SCANNER TIP ==================== -->
        <div class="col-lg-4">
            <div class="card card-modern p-4 border-0 text-center mb-4 shadow-sm">

                <!-- Avatar Display -->
                <div class="position-relative d-inline-block mx-auto mb-3">
                    <?php if (!empty($user['avatar']) && $user['avatar'] !== 'default_avatar.png' && file_exists($avatarDir . '/' . $user['avatar'])): ?>
                        <img src="/GabayKarera/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="rounded-circle shadow object-fit-cover border border-3 border-primary" style="width: 90px; height: 90px;">
                    <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mx-auto shadow" style="width: 90px; height: 90px; font-size: 2.2rem; font-weight: 800;">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                <p class="text-muted small mb-2"><?= !empty($profile['headline']) ? htmlspecialchars($profile['headline']) : 'Job Seeker / Candidate' ?></p>
                <div class="badge bg-light text-dark border mb-3"><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($profile['location'] ?? 'Philippines') ?></div>

                <!-- Resume File Badge -->
                <?php if (!empty($profile['resume_file']) && file_exists($resumeDir . '/' . $profile['resume_file'])): ?>
                    <div class="p-2 px-3 bg-light rounded-3 border text-start small mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fa-solid fa-file-pdf text-danger me-2"></i>
                            <strong>Uploaded Resume</strong>
                        </div>
                        <a href="/GabayKarera/uploads/resumes/<?= htmlspecialchars($profile['resume_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2">
                            View <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <hr class="my-3 text-muted opacity-25">

                <!-- Profile Readiness & Real-Time Match Metrics -->
                <div class="p-3 bg-light rounded-3 border text-start mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="fw-bold text-dark"><i class="fa-solid fa-chart-line text-primary me-1"></i> Profile Readiness</small>
                        <span class="badge bg-primary"><?= $completeness ?>%</span>
                    </div>
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $completeness ?>%" aria-valuenow="<?= $completeness ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <small class="text-muted"><i class="fa-solid fa-bullseye text-success me-1"></i> Top Job Fit:</small>
                        <strong class="text-success"><?= CompetencyMatcher::formatScore($topJobMatch) ?></strong>
                    </div>
                    <?php if (!empty($topJobTitle)): ?>
                        <small class="text-muted d-block text-truncate" style="font-size: 0.72rem;">for <?= htmlspecialchars($topJobTitle) ?></small>
                    <?php endif; ?>
                </div>

                <div class="text-start small d-flex flex-column gap-2 text-secondary">
                    <div><i class="fa-regular fa-envelope me-2 text-primary"></i> <?= htmlspecialchars($user['email']) ?></div>
                    <div><i class="fa-solid fa-phone me-2 text-primary"></i> <?= htmlspecialchars($user['phone'] ?? 'Not set') ?></div>
                    <div><i class="fa-solid fa-briefcase me-2 text-primary"></i> <?= $profile['experience_years'] ?? '0' ?> Years Experience</div>
                    <div><i class="fa-solid fa-graduation-cap me-2 text-primary"></i> <?= htmlspecialchars($profile['education_level'] ?? 'Not specified') ?></div>
                    <?php if (!empty($profile['academic_honors'])): ?>
                        <div><i class="fa-solid fa-award me-2 text-warning"></i> <span class="badge bg-warning-subtle text-dark border"><?= htmlspecialchars($profile['academic_honors']) ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($profile['work_preference'])): ?>
                        <div><i class="fa-solid fa-laptop-house me-2 text-info"></i> <span class="badge bg-info-subtle text-info border"><?= htmlspecialchars($profile['work_preference']) ?></span></div>
                    <?php endif; ?>
                </div>

                <!-- Professional Links -->
                <?php if (!empty($profile['portfolio_url']) || !empty($profile['linkedin_url']) || !empty($profile['github_url'])): ?>
                    <div class="d-flex justify-content-center gap-2 mt-3 pt-2 border-top">
                        <?php if (!empty($profile['portfolio_url'])): ?>
                            <a href="<?= htmlspecialchars($profile['portfolio_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-circle" title="Portfolio Website" style="width: 32px; height: 32px; padding: 0; line-height: 30px;"><i class="fa-solid fa-globe"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($profile['linkedin_url'])): ?>
                            <a href="<?= htmlspecialchars($profile['linkedin_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-circle" title="LinkedIn Profile" style="width: 32px; height: 32px; padding: 0; line-height: 30px;"><i class="fa-brands fa-linkedin-in"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($profile['github_url'])): ?>
                            <a href="<?= htmlspecialchars($profile['github_url']) ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-circle" title="GitHub Repo" style="width: 32px; height: 32px; padding: 0; line-height: 30px;"><i class="fa-brands fa-github"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Instant AI Generator Trigger Button -->
                <div class="mt-4 pt-2 border-top">
                    <a href="/GabayKarera/applicant/profile.php?analyze=1#ai-fit-section" class="btn btn-primary-custom w-100 py-2">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate AI Skill Fit
                    </a>
                </div>
            </div>

            <!-- AI Tip Box -->
            <div class="card card-modern p-4 border-0 ai-box">
                <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-robot text-primary me-2"></i>AI Resume Scanner</h6>
                <p class="small text-muted mb-0">
                    Upload your <strong>Resume file (PDF/DOCX)</strong> or paste your achievements. The AI will extract all your skills and show you exactly which career paths and jobs match your experience best!
                </p>
            </div>
        </div>

        <!-- Right Column: Profile Editor, File Upload & AI Results -->
        <div class="col-lg-8">
            
            <!-- AI GENERATED SKILL & CAREER FIT RESULTS (Appears when analyzed) -->
            <?php if ($aiAnalysisResults): ?>
                <div class="card card-modern p-4 border-0 mb-4 shadow-sm border-start border-5 border-success" id="ai-fit-section">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success-subtle text-success p-2 rounded-circle fs-6"><i class="fa-solid fa-brain"></i></span>
                            <div>
                                <h5 class="fw-bold mb-0 text-success">AI Competency & Career Fit Report</h5>
                                <small class="text-muted">Generated from your resume & experience background</small>
                            </div>
                        </div>
                        <span class="badge badge-success-glow fs-6">AI Analyzed</span>
                    </div>

                    <!-- 1. Top Career Pathways Where You Fit (Only shown if active job postings exist) -->
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-compass text-primary me-2"></i>Where Your Skills & Experience Fit Best:</h6>
                    <div class="row g-2 mb-4">
                        <?php 
                            $activeCareerFits = array_filter($aiAnalysisResults['career_fits'] ?? [], function($cfit) {
                                return ($cfit['active_jobs_count'] ?? 0) > 0;
                            });
                        ?>
                        <?php if (empty($activeCareerFits)): ?>
                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3 border text-center text-muted">
                                    <i class="fa-solid fa-briefcase text-secondary opacity-50 me-2"></i>
                                    <span class="small fw-semibold">No job posting right now</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($activeCareerFits, 0, 2) as $cfit): ?>
                                <?php 
                                    $fitBadge = $cfit['match_score'] >= 75 ? 'badge-success-glow' : 'badge-info-glow';
                                ?>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($cfit['title']) ?></h6>
                                                <span class="badge <?= $fitBadge ?>"><?= CompetencyMatcher::formatScore($cfit['match_score']) ?> Fit</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <small class="text-muted"><?= htmlspecialchars($cfit['category']) ?></small>
                                                <span class="badge bg-success-subtle text-success border py-0 px-2" style="font-size: 0.65rem;">
                                                    <i class="fa-solid fa-briefcase me-1"></i> <?= $cfit['active_jobs_count'] ?> active opening<?= $cfit['active_jobs_count'] > 1 ? 's' : '' ?>
                                                </span>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <small class="fw-bold text-success d-block" style="font-size: 0.75rem;">Matching Competencies:</small>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach (array_slice($cfit['matched_skills'], 0, 3) as $msk): ?>
                                                        <span class="skill-tag skill-tag-matched py-0 px-2" style="font-size: 0.7rem;"><i class="fa-solid fa-check text-success"></i> <?= htmlspecialchars($msk) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($cfit['missing_skills'])): ?>
                                                <div>
                                                    <small class="fw-bold text-danger d-block" style="font-size: 0.75rem;">Skills to Upskill:</small>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <?php foreach (array_slice($cfit['missing_skills'], 0, 2) as $miss): ?>
                                                            <span class="skill-tag skill-tag-missing py-0 px-2" style="font-size: 0.7rem;"><i class="fa-solid fa-arrow-up text-danger"></i> <?= htmlspecialchars($miss) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-3 pt-2 border-top small text-muted d-flex justify-content-between align-items-center">
                                            <span><?= htmlspecialchars($cfit['average_salary']) ?></span>
                                            <a href="/GabayKarera/applicant/jobs.php?q=<?= urlencode($cfit['title']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fw-semibold" style="font-size: 0.75rem;">
                                                View <?= $cfit['active_jobs_count'] ?> Job<?= $cfit['active_jobs_count'] > 1 ? 's' : '' ?> &rarr;
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 2. AI Extracted Skills Table with 1-Click Save -->
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-tags text-primary me-2"></i>Skills Extracted from Resume (<?= count($aiAnalysisResults['extracted_skills']) ?>):</h6>
                    
                    <?php if (!empty($aiAnalysisResults['extracted_skills'])): ?>
                        <form method="POST" action="/GabayKarera/applicant/profile.php">
                            <input type="hidden" name="save_extracted_skills" value="1">

                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php foreach ($aiAnalysisResults['extracted_skills'] as $idx => $esk): ?>
                                        <div class="badge bg-white text-dark border p-2 d-flex align-items-center gap-2">
                                            <input type="checkbox" name="extracted_skills[<?= $idx ?>][name]" value="<?= htmlspecialchars($esk['skill_name']) ?>" checked class="form-check-input mt-0">
                                            <input type="hidden" name="extracted_skills[<?= $idx ?>][proficiency]" value="<?= htmlspecialchars($esk['proficiency']) ?>">
                                            <input type="hidden" name="extracted_skills[<?= $idx ?>][category]" value="<?= htmlspecialchars($esk['category']) ?>">
                                            <input type="hidden" name="extracted_skills[<?= $idx ?>][years]" value="<?= htmlspecialchars($esk['years_experience']) ?>">
                                            <strong class="text-primary"><?= htmlspecialchars($esk['skill_name']) ?></strong>
                                            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;"><?= htmlspecialchars($esk['proficiency']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" class="btn btn-sm btn-success px-3">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Selected Skills to My Profile
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="small text-muted mb-3">No specific skills detected in the current text. Add more details or upload a complete resume file.</p>
                    <?php endif; ?>

                    <!-- 3. Immediate Matching Jobs -->
                    <?php if (!empty($aiAnalysisResults['job_fits'])): ?>
                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-briefcase text-primary me-2"></i>Top Matching Open Positions Right Now:</h6>
                        <div class="d-flex flex-column gap-2 mb-2">
                            <?php foreach ($aiAnalysisResults['job_fits'] as $jf): ?>
                                <div class="p-2 px-3 bg-white rounded border d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-dark"><?= htmlspecialchars($jf['title']) ?></strong> &bull; 
                                        <small class="text-muted"><?= htmlspecialchars($jf['company_name']) ?> (<?= htmlspecialchars($jf['location']) ?>)</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge badge-success-glow fw-bold"><?= CompetencyMatcher::formatScore($jf['match_score']) ?> Match</span>
                                        <a href="/GabayKarera/applicant/job-details.php?id=<?= $jf['id'] ?>" class="btn btn-sm btn-outline-custom py-1">View Fit & Apply</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-briefcase text-primary me-2"></i>Top Matching Open Positions:</h6>
                        <div class="p-3 bg-light rounded-3 border text-center text-muted mb-2">
                            <span class="small fw-semibold">No job posting right now</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Main Profile Form with Picture & Resume File Inputs -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-id-card text-primary me-2"></i>Personal & Educational Profile</h5>
                
                <form method="POST" action="/GabayKarera/applicant/profile.php" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="row g-3">
                        
                        <!-- Uploads Section: Picture & Resume File -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <label class="form-label small fw-bold mb-1">
                                    <i class="fa-solid fa-image text-primary me-1"></i> Profile Picture / Photo
                                </label>
                                <input type="file" name="avatar_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                                <small class="text-muted" style="font-size: 0.75rem;">Supported formats: JPG, PNG, WEBP</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <label class="form-label small fw-bold mb-1">
                                    <i class="fa-solid fa-file-arrow-up text-primary me-1"></i> Upload Resume Document
                                </label>
                                <input type="file" name="resume_file" class="form-control form-control-sm" accept=".pdf,.docx,.doc,.txt">
                                <small class="text-muted" style="font-size: 0.75rem;">PDF, DOCX, or TXT (Auto-extracts skills & experience)</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 09171234567" maxlength="11" pattern="[0-9]{11}" title="Please enter an 11-digit cellphone number (e.g. 09171234567)">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Professional Headline</label>
                            <input type="text" name="headline" class="form-control" value="<?= htmlspecialchars($profile['headline'] ?? '') ?>" placeholder="e.g. Junior Full-Stack Web Developer | CS Graduate">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Highest Level of Education</label>
                            <select name="education_level" class="form-select">
                                <?php
                                    $levels = ["Bachelor's Degree", "Associate Degree / Diploma", "Master's Degree", "Doctorate / PhD", "Senior High School", "Vocational Course"];
                                    $currEdu = $profile['education_level'] ?? "Bachelor's Degree";
                                    foreach ($levels as $lvl) {
                                        $sel = ($currEdu === $lvl) ? 'selected' : '';
                                        echo "<option value=\"{$lvl}\" {$sel}>{$lvl}</option>";
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Field of Study / Degree Program</label>
                            <input type="text" name="field_of_study" class="form-control" value="<?= htmlspecialchars($profile['field_of_study'] ?? '') ?>" placeholder="e.g. Computer Science, Information Technology">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">University / School</label>
                            <input type="text" name="school" class="form-control" value="<?= htmlspecialchars($profile['school'] ?? '') ?>" placeholder="e.g.  University of the Philippines">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Graduation Year</label>
                            <input type="number" name="graduation_year" class="form-control" value="<?= htmlspecialchars($profile['graduation_year'] ?? '') ?>" placeholder="e.g. 2024">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Academic Honors & Distinctions</label>
                            <input type="text" name="academic_honors" class="form-control" value="<?= htmlspecialchars($profile['academic_honors'] ?? '') ?>" placeholder="e.g. Magna Cum Laude, Dean's Lister, Best Capstone Project">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Years of Experience</label>
                            <input type="number" step="0.5" name="experience_years" class="form-control" value="<?= htmlspecialchars($profile['experience_years'] ?? '0') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Current / Recent Position</label>
                            <input type="text" name="current_position" class="form-control" value="<?= htmlspecialchars($profile['current_position'] ?? '') ?>" placeholder="e.g. Associate Developer / Fresh Grad">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Expected Monthly Salary (₱)</label>
                            <input type="number" step="1000" name="expected_salary" class="form-control" value="<?= htmlspecialchars($profile['expected_salary'] ?? '') ?>" placeholder="e.g. 45000">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Location / City</label>
                            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($profile['location'] ?? '') ?>" placeholder="e.g. Metro Manila, Cebu, Davao">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Work Preference & Setup</label>
                            <select name="work_preference" class="form-select">
                                <?php
                                    $prefs = ["Flexible / Open to Any", "Remote (Work From Home)", "Hybrid", "On-site / Office-based", "Day Shift", "Night / Shifting Schedule"];
                                    $currPref = $profile['work_preference'] ?? "Flexible / Open to Any";
                                    foreach ($prefs as $p) {
                                        $sel = ($currPref === $p) ? 'selected' : '';
                                        echo "<option value=\"{$p}\" {$sel}>{$p}</option>";
                                    }
                                ?>
                            </select>
                        </div>

                        <!-- CAREER & EMPLOYMENT HISTORY -->
                        <div class="col-12 mt-3">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-briefcase me-2"></i>Detailed Employment & Career Work History</h6>
                                <p class="text-muted small mb-2">List your past companies, roles, employment duration, and key responsibilities or achievements. HR and Admin review this when evaluating your applications.</p>
                                <textarea name="work_history" rows="4" class="form-control" placeholder="e.g.&#10;2023 - Present: Junior Web Developer at TechCorp Solutions (Built PHP REST APIs, optimized MySQL queries)&#10;2022 - 2023: IT Technical Support Intern at Apex Systems (Handled user tickets, server backups)"><?= htmlspecialchars($profile['work_history'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- CERTIFICATIONS & LICENSES -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-certificate me-2"></i>Professional Certifications, Licenses & Training</h6>
                                <p class="text-muted small mb-2">Include PRC licenses, civil service eligibility, cloud certificates (AWS, Azure), or course completions.</p>
                                <textarea name="certifications" rows="3" class="form-control" placeholder="e.g.&#10;- Civil Service Professional Eligibility (2024)&#10;- AWS Certified Cloud Practitioner&#10;- Google Data Analytics Professional Certificate"><?= htmlspecialchars($profile['certifications'] ?? '') ?></textarea>
                            </div>
                        </div>


                        <!-- CAREER GOALS & VISION -->
                        <div class="col-12">
                            <label class="form-label small fw-bold">Career Objectives & Vision</label>
                            <textarea name="career_goals" rows="2" class="form-control" placeholder="Briefly describe your short-term and long-term career aspirations..."><?= htmlspecialchars($profile['career_goals'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Career Interests & Focus Areas</label>
                            <input type="text" name="interests" class="form-control" value="<?= htmlspecialchars($profile['interests'] ?? '') ?>" placeholder="e.g. Web Development, Cloud Computing, Artificial Intelligence, Mobile Apps">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Professional Bio / Summary</label>
                            <textarea name="bio" rows="3" class="form-control" placeholder="Introduce yourself and your career goals..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Resume Text / Key Project Achievements (For AI Competency Extraction)</label>
                            <textarea name="raw_resume_text" rows="4" class="form-control font-monospace small" placeholder="Paste your resume summary, project descriptions, or coursework here..."><?= htmlspecialchars($profile['raw_resume_text'] ?? '') ?></textarea>
                            <small class="text-muted">The AI algorithm reads this text to identify latent skills, technologies, and domain proficiencies.</small>
                        </div>

                        <div class="col-12 mt-4 d-flex justify-content-between align-items-center">
                            <button type="submit" name="run_ai_analysis" value="1" class="btn btn-outline-custom">
                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Save & Run AI Fit Generator
                            </button>
                            <button type="submit" class="btn btn-primary-custom px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Profile Details
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Skills & Competencies Management Section -->
            <div class="card card-modern p-4 border-0" id="skills-section">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-tags text-primary me-2"></i>My Skills & Competencies</h5>

                <!-- Add Skill Form -->
                <form method="POST" action="/GabayKarera/applicant/profile.php#skills-section" class="bg-light p-3 rounded-3 border mb-4">
                    <input type="hidden" name="add_skill" value="1">
                    <h6 class="fw-bold text-dark small mb-3">+ Add a New Competency / Skill Manually</h6>
                    
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold mb-1">Skill Name</label>
                            <input type="text" name="skill_name" class="form-control form-control-sm" placeholder="e.g. Python, Figma, Docker" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Proficiency</label>
                            <select name="proficiency" class="form-select form-select-sm">
                                <option value="Beginner">Beginner (60%)</option>
                                <option value="Intermediate" selected>Intermediate (85%)</option>
                                <option value="Advanced">Advanced (95%)</option>
                                <option value="Expert">Expert (100%)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold mb-1">Category</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="Technical" selected>Technical</option>
                                <option value="Soft Skill">Soft Skill</option>
                                <option value="Domain/Tool">Domain / Tool</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary-custom w-100">
                                <i class="fa-solid fa-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Current Skills List -->
                <h6 class="fw-bold small text-muted mb-2">Registered Competencies (<?= count($skills) ?>):</h6>
                <?php if (empty($skills)): ?>
                    <p class="text-muted small">No competencies registered yet. Use the form above to add your technical and soft skills, or upload your resume to generate them automatically.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Skill / Competency</th>
                                    <th>Category</th>
                                    <th>Proficiency Level</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skills as $sk): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($sk['skill_name']) ?></strong>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;"><?= htmlspecialchars($sk['years_experience'] ?? 1) ?> yr(s) exp</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($sk['category']) ?></span>
                                        </td>
                                        <td>
                                            <form method="POST" action="/GabayKarera/applicant/profile.php#skills-section" class="d-inline-flex align-items-center gap-1">
                                                <input type="hidden" name="update_skill" value="1">
                                                <input type="hidden" name="skill_id" value="<?= $sk['id'] ?>">
                                                <input type="hidden" name="category" value="<?= htmlspecialchars($sk['category'] ?? 'Technical') ?>">
                                                <input type="hidden" name="years_experience" value="<?= htmlspecialchars($sk['years_experience'] ?? 1) ?>">
                                                <select name="proficiency" class="form-select form-select-sm py-1 px-2 border" onchange="this.form.submit()" style="font-size: 0.8rem; width: auto; font-weight: 600;" title="Change proficiency to recalculate match percentage">
                                                    <option value="Beginner" <?= $sk['proficiency'] === 'Beginner' ? 'selected' : '' ?>>Beginner (60%)</option>
                                                    <option value="Intermediate" <?= $sk['proficiency'] === 'Intermediate' ? 'selected' : '' ?>>Intermediate (85%)</option>
                                                    <option value="Advanced" <?= $sk['proficiency'] === 'Advanced' ? 'selected' : '' ?>>Advanced (95%)</option>
                                                    <option value="Expert" <?= $sk['proficiency'] === 'Expert' ? 'selected' : '' ?>>Expert (100%)</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#editSkillModal<?= $sk['id'] ?>" title="Edit Skill Details">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <a href="/GabayKarera/applicant/profile.php?delete_skill=<?= $sk['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Remove this skill from your profile?');" title="Delete Skill">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>

                                            <!-- Edit Skill Modal -->
                                            <div class="modal fade" id="editSkillModal<?= $sk['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content card-modern border-0 shadow">
                                                        <form method="POST" action="/GabayKarera/applicant/profile.php#skills-section">
                                                            <input type="hidden" name="update_skill" value="1">
                                                            <input type="hidden" name="skill_id" value="<?= $sk['id'] ?>">
                                                            <div class="modal-header border-bottom">
                                                                <h6 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Competency: <?= htmlspecialchars($sk['skill_name']) ?></h6>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body p-4 text-start">
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold">Proficiency Level</label>
                                                                    <select name="proficiency" class="form-select">
                                                                        <option value="Beginner" <?= $sk['proficiency'] === 'Beginner' ? 'selected' : '' ?>>Beginner (60% weight)</option>
                                                                        <option value="Intermediate" <?= $sk['proficiency'] === 'Intermediate' ? 'selected' : '' ?>>Intermediate (85% weight)</option>
                                                                        <option value="Advanced" <?= $sk['proficiency'] === 'Advanced' ? 'selected' : '' ?>>Advanced (95% weight)</option>
                                                                        <option value="Expert" <?= $sk['proficiency'] === 'Expert' ? 'selected' : '' ?>>Expert (100% weight)</option>
                                                                    </select>
                                                                    <small class="text-muted">Higher proficiency increases your overall match percentage for jobs requiring this skill.</small>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold">Category</label>
                                                                    <select name="category" class="form-select">
                                                                        <option value="Technical" <?= ($sk['category'] ?? '') === 'Technical' ? 'selected' : '' ?>>Technical</option>
                                                                        <option value="Soft Skill" <?= ($sk['category'] ?? '') === 'Soft Skill' ? 'selected' : '' ?>>Soft Skill</option>
                                                                        <option value="Domain/Tool" <?= ($sk['category'] ?? '') === 'Domain/Tool' ? 'selected' : '' ?>>Domain / Tool</option>
                                                                    </select>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-bold">Years of Hands-on Experience</label>
                                                                    <input type="number" name="years_experience" class="form-control" value="<?= htmlspecialchars($sk['years_experience'] ?? 1) ?>" min="0" max="40">
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer border-top bg-light">
                                                                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-sm btn-primary-custom px-3">Save & Recalculate Fits</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
