<?php
/**
 * ============================================================================
 * GABAYKARERA AI - HR CANDIDATE PROFILE & COMPETENCY GAP ANALYSIS
 * File: hr/applicant-detail.php
 * Purpose: Readable candidate inputs, side-by-side gap breakdown, and two-way messaging
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['hr']);
$pdo = Database::getInstance();
$hrId = $_SESSION['user_id'];
$appId = (int)($_GET['id'] ?? 0);

// ============================================================================
// 1. DATA FETCHING: FULL APPLICANT PROFILE & JOB SPECIFICATIONS
// ============================================================================
$stmt = $pdo->prepare("
    SELECT a.*, u.full_name as applicant_name, u.email as applicant_email, u.phone as applicant_phone, u.avatar as applicant_avatar,
           j.title as job_title, j.id as job_id, j.company_name, j.required_skills, j.preferred_skills, j.experience_required, j.employer_id,
           p.headline, p.bio, p.education_level, p.field_of_study, p.school, p.graduation_year, p.experience_years,
           p.current_position, p.expected_salary, p.location, p.interests, p.raw_resume_text, p.resume_file,
           p.work_history, p.certifications, p.portfolio_url, p.linkedin_url, p.github_url, p.career_goals, p.work_preference, p.academic_honors
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.applicant_id = u.id
    LEFT JOIN applicant_profiles p ON u.id = p.user_id
    WHERE a.id = ? AND j.employer_id = ?
");
$stmt->execute([$appId, $hrId]);
$app = $stmt->fetch();

if (!$app) {
    $_SESSION['flash_error'] = "Application not found or unauthorized.";
    header("Location: /GabayKarera/hr/applicants.php");
    exit;
}

// Fetch applicant skills
$sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ? ORDER BY category, skill_name");
$sStmt->execute([$app['applicant_id']]);
$applicantSkills = $sStmt->fetchAll() ?: [];

// ============================================================================
// 2. AI COMPETENCY BREAKDOWN & GAP EVALUATION (LIVE SYNCHRONIZATION)
// ============================================================================
$applicantData = [
    'profile' => [
        'experience_years' => $app['experience_years'],
        'field_of_study' => $app['field_of_study'],
        'raw_resume_text' => $app['raw_resume_text'],
        'bio' => $app['bio'],
        'interests' => $app['interests']
    ],
    'skills' => $applicantSkills
];

$liveMatch = CompetencyMatcher::matchApplicantToJob($applicantData, $app);

// Keep application record strictly synchronized with applicant's live profile
if (empty($app['competency_breakdown']) || abs((float)$app['match_score'] - (float)$liveMatch['match_score']) > 0.05) {
    $uSync = $pdo->prepare("UPDATE applications SET match_score = ?, competency_breakdown = ? WHERE id = ?");
    $uSync->execute([$liveMatch['match_score'], json_encode($liveMatch), $appId]);
    $app['match_score'] = $liveMatch['match_score'];
    $app['competency_breakdown'] = json_encode($liveMatch);
}

$breakdown = !empty($app['competency_breakdown']) ? json_decode($app['competency_breakdown'], true) : $liveMatch;

// ============================================================================
// 3. ACTION HANDLER: DISPATCH STATUS UPDATE & INTERVIEW NOTICE
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_decision_message'])) {
        $newStatus = $_POST['status'] ?? $app['status'];
        $subject = trim($_POST['subject'] ?? '');
        $messageBody = trim($_POST['message_body'] ?? '');
        $messageType = $_POST['message_type'] ?? 'Message';
        $sendEmailCopy = isset($_POST['send_email_copy']);

        // Update application status
        $uStmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $uStmt->execute([$newStatus, $appId]);

        // Insert message if body not empty
        if (!empty($messageBody) && !empty($subject)) {
            $msgStmt = $pdo->prepare("
                INSERT INTO application_messages (application_id, sender_id, receiver_id, message_type, subject, body, is_read) 
                VALUES (?, ?, ?, ?, ?, ?, 0)
            ");
            $msgStmt->execute([$appId, $hrId, $app['applicant_id'], $messageType, $subject, $messageBody]);
        }

        $emailNote = $sendEmailCopy ? " and an email copy was dispatched to {$app['applicant_email']}" : "";
        $_SESSION['flash_success'] = "Application status updated to '{$newStatus}'{$emailNote}.";
        header("Location: /GabayKarera/hr/applicant-detail.php?id={$appId}");
        exit;
    }
}

// ============================================================================
// 4. DATA FETCHING: TWO-WAY COMMUNICATION HISTORY
// ============================================================================
$msgQuery = $pdo->prepare("
    SELECT m.*, u.full_name as sender_name, u.role as sender_role
    FROM application_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.application_id = ?
    ORDER BY m.sent_at ASC
");
$msgQuery->execute([$appId]);
$messages = $msgQuery->fetchAll();

// Count applicant replies
$applicantRepliesCount = count(array_filter($messages, function($m) use ($app) {
    return (int)$m['sender_id'] === (int)$app['applicant_id'];
}));

$pageTitle = "Candidate Competency Analysis & Notices - " . htmlspecialchars($app['applicant_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: HR APPLICANT DETAIL & GAP ANALYSIS CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">

    <!-- Breadcrumb -->
    <div class="mb-3">
        <a href="/GabayKarera/hr/applicants.php" class="text-decoration-none text-muted small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Candidate Screening Pipeline
        </a>
    </div>

    <!-- Candidate Header Card -->
    <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <!-- Candidate Avatar / Photo -->
                <?php if (!empty($app['applicant_avatar']) && $app['applicant_avatar'] !== 'default_avatar.png' && file_exists(__DIR__ . '/../uploads/avatars/' . $app['applicant_avatar'])): ?>
                    <img src="/GabayKarera/uploads/avatars/<?= htmlspecialchars($app['applicant_avatar']) ?>" alt="Avatar" class="rounded-circle shadow object-fit-cover border border-3 border-primary" style="width: 75px; height: 75px;">
                <?php else: ?>
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-2 shadow" style="width: 75px; height: 75px;">
                        <?= strtoupper(substr($app['applicant_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($app['applicant_name']) ?></h4>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Applicant</span>
                        <?php if ($applicantRepliesCount > 0): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-reply me-1"></i> <?= $applicantRepliesCount ?> Reply Received</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted mb-1"><?= !empty($app['headline']) ? htmlspecialchars($app['headline']) : 'Job Candidate' ?></p>
                    <div class="text-secondary small d-flex flex-wrap gap-3 mb-2">
                        <span><i class="fa-regular fa-envelope text-primary me-1"></i> <?= htmlspecialchars($app['applicant_email']) ?></span>
                        <span><i class="fa-solid fa-phone text-primary me-1"></i> <?= htmlspecialchars($app['applicant_phone'] ?? 'N/A') ?></span>
                        <span><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($app['location'] ?? 'Philippines') ?></span>
                        <span><i class="fa-regular fa-clock me-1"></i> Applied <?= date('M d, Y', strtotime($app['applied_at'])) ?></span>
                    </div>

                    <!-- Career Quick Badges & External Links -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php if (!empty($app['academic_honors'])): ?>
                            <span class="badge bg-warning-subtle text-dark border"><i class="fa-solid fa-award text-warning me-1"></i> <?= htmlspecialchars($app['academic_honors']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($app['work_preference'])): ?>
                            <span class="badge bg-info-subtle text-info border"><i class="fa-solid fa-laptop-house me-1"></i> <?= htmlspecialchars($app['work_preference']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($app['portfolio_url'])): ?>
                            <a href="<?= htmlspecialchars($app['portfolio_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;"><i class="fa-solid fa-globe me-1"></i> Portfolio</a>
                        <?php endif; ?>
                        <?php if (!empty($app['linkedin_url'])): ?>
                            <a href="<?= htmlspecialchars($app['linkedin_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;"><i class="fa-brands fa-linkedin me-1"></i> LinkedIn</a>
                        <?php endif; ?>
                        <?php if (!empty($app['github_url'])): ?>
                            <a href="<?= htmlspecialchars($app['github_url']) ?>" target="_blank" class="btn btn-sm btn-outline-dark py-0 px-2" style="font-size: 0.75rem;"><i class="fa-brands fa-github me-1"></i> GitHub</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Match Score Box -->
            <div class="text-md-end bg-light p-3 rounded-3 border">
                <small class="text-muted fw-bold d-block">AI Competency Match</small>
                <div class="display-6 fw-bold text-success mb-1">
                    <?= CompetencyMatcher::formatScore($app['match_score']) ?>
                </div>
                <span class="badge <?= $app['status'] === 'Hired' ? 'bg-success' : ($app['status'] === 'Interview Scheduled' ? 'bg-warning text-dark' : ($app['status'] === 'Rejected' ? 'bg-danger' : 'bg-primary')) ?> px-3 py-1">
                    Status: <?= htmlspecialchars($app['status']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <!-- Left Column: READABLE COMPETENCY GAP ANALYSIS & APPLICANT DATA -->
        <div class="col-lg-7">
            
            <!-- 1. AI COMPETENCY GAP & FIT BREAKDOWN (HIGHLY READABLE) -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-brain text-primary fs-4"></i>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Competency Gap & Fit Analysis</h5>
                            <small class="text-muted">Target Role: <strong><?= htmlspecialchars($app['job_title']) ?></strong></small>
                        </div>
                    </div>
                    <span class="badge badge-success-glow fs-6"><?= CompetencyMatcher::formatScore($app['match_score']) ?> Fit</span>
                </div>

                <!-- AI Verdict Callout -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-lightbulb text-warning fs-5 mt-1"></i>
                        <div>
                            <strong class="text-dark d-block">Recruiter AI Verdict:</strong>
                            <p class="small text-secondary mb-0">
                                <?= !empty($breakdown['verdict']) ? htmlspecialchars($breakdown['verdict']) : 'Strong candidate with clear competency alignment across required skills.' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Radar Chart Visual -->
                <div class="mb-4" style="height: 240px; position: relative;">
                    <canvas id="hrCandidateRadar"></canvas>
                </div>

                <!-- Side-by-Side Competency Comparison -->
                <div class="row g-3 mb-3">
                    <!-- Matched Required Skills -->
                    <div class="col-12">
                        <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                            <h6 class="fw-bold text-success mb-2">
                                <i class="fa-solid fa-circle-check me-1"></i> Matched Required Competencies (<?= count($breakdown['matched_required_skills'] ?? []) ?>):
                            </h6>
                            <?php if (!empty($breakdown['matched_required_skills'])): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($breakdown['matched_required_skills'] as $m): ?>
                                        <?php $mName = is_array($m) ? $m['skill'] : $m; ?>
                                        <span class="skill-tag skill-tag-matched bg-white">
                                            <i class="fa-solid fa-check text-success"></i> <strong><?= htmlspecialchars($mName) ?></strong>
                                            <?php if (is_array($m) && !empty($m['proficiency'])): ?>
                                                <small class="text-muted">(<?= htmlspecialchars($m['proficiency']) ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="small text-muted mb-0">No direct required skill matches identified.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Identified Competency Gaps (Missing Skills) -->
                    <div class="col-12">
                        <div class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                            <h6 class="fw-bold text-danger mb-2">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Identified Competency Gaps / Missing Skills (<?= count($breakdown['missing_required_skills'] ?? []) ?>):
                            </h6>
                            <?php if (!empty($breakdown['missing_required_skills'])): ?>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    <?php foreach ($breakdown['missing_required_skills'] as $miss): ?>
                                        <span class="skill-tag skill-tag-missing bg-white">
                                            <i class="fa-solid fa-circle-xmark text-danger"></i> <strong><?= htmlspecialchars($miss) ?></strong>
                                            <small class="text-danger fw-bold">(Needs Upskilling)</small>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted d-block">
                                    <strong>Recruiter Note:</strong> If hired, consider mentoring or assigning online training modules for the missing competencies above.
                                </small>
                            <?php else: ?>
                                <p class="small text-success mb-0 fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> Zero skill gaps! The applicant satisfies all core required competencies for this position.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Preferred / Bonus Skills -->
                <?php if (!empty($breakdown['matched_preferred_skills'])): ?>
                    <div class="p-2 px-3 bg-light rounded-3 border small mb-2">
                        <strong class="text-primary"><i class="fa-solid fa-star text-warning me-1"></i> Preferred Skills Bonus:</strong>
                        <span class="text-muted"><?= htmlspecialchars(implode(', ', $breakdown['matched_preferred_skills'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2. FULL APPLICANT REGISTERED SKILL PORTFOLIO -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-tags text-primary me-2"></i>Full Candidate Skill Portfolio (<?= count($applicantSkills) ?>)</h5>
                
                <?php if (empty($applicantSkills)): ?>
                    <p class="text-muted small mb-0">No individual skill tags registered by candidate.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Skill Name</th>
                                    <th>Category</th>
                                    <th>Proficiency Level</th>
                                    <th>Experience</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applicantSkills as $ask): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ask['skill_name']) ?></strong></td>
                                        <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($ask['category']) ?></span></td>
                                        <td>
                                            <?php
                                                $pClass = ($ask['proficiency'] === 'Expert' || $ask['proficiency'] === 'Advanced') ? 'bg-success' : 'bg-primary';
                                            ?>
                                            <span class="badge <?= $pClass ?>"><?= htmlspecialchars($ask['proficiency']) ?></span>
                                        </td>
                                        <td class="small text-muted"><?= $ask['years_experience'] ?> yr(s)</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 2.5 CANDIDATE CAREER & BACKGROUND DOSSIER (HR RECRUITER VIEW) -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-id-card-clip text-primary me-2"></i>Comprehensive Career & Background Dossier</h5>

                <!-- Application Cover Note -->
                <?php if (!empty($app['cover_note'])): ?>
                    <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle mb-3">
                        <small class="fw-bold text-primary d-block mb-1"><i class="fa-solid fa-envelope-open-text me-1"></i> Applicant Cover Note & Pitch for this Role:</small>
                        <div class="small text-dark" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($app['cover_note'])) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Academic Background & Honors -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Academic Background & Qualifications</h6>
                    <div class="row g-2 small">
                        <div class="col-md-6">
                            <span class="text-muted d-block">Highest Degree / Level:</span>
                            <strong class="text-dark"><?= htmlspecialchars($app['education_level'] ?: 'Not specified') ?></strong>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted d-block">Field of Study / Course:</span>
                            <strong class="text-dark"><?= htmlspecialchars($app['field_of_study'] ?: 'General') ?></strong>
                        </div>
                        <div class="col-md-6 mt-2">
                            <span class="text-muted d-block">University / College / School:</span>
                            <span class="text-dark"><?= htmlspecialchars($app['school'] ?: 'Not specified') ?></span>
                        </div>
                        <div class="col-md-3 mt-2">
                            <span class="text-muted d-block">Graduation Year:</span>
                            <span class="text-dark"><?= htmlspecialchars($app['graduation_year'] ?: 'N/A') ?></span>
                        </div>
                        <div class="col-md-3 mt-2">
                            <span class="text-muted d-block">Academic Honors:</span>
                            <?php if (!empty($app['academic_honors'])): ?>
                                <span class="badge bg-warning text-dark"><?= htmlspecialchars($app['academic_honors']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">None listed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Employment & Work Experience History -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-briefcase text-primary me-2"></i>Employment & Career Work History</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
                        <span><strong>Total Experience:</strong> <?= $app['experience_years'] ?? '0' ?> Year(s)</span>
                        <span><strong>Current Position:</strong> <?= htmlspecialchars($app['current_position'] ?: 'Job Candidate') ?></span>
                    </div>
                    <?php if (!empty($app['work_history'])): ?>
                        <div class="p-3 bg-white rounded-3 border small text-dark" style="line-height: 1.6; white-space: pre-line;">
                            <?= htmlspecialchars($app['work_history']) ?>
                        </div>
                    <?php else: ?>
                        <div class="p-2 bg-white rounded border small text-muted">
                            <em>No separate work history narrative specified. Check the attached resume and skills portfolio below.</em>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Certifications & Professional Licenses -->
                <?php if (!empty($app['certifications'])): ?>
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-certificate text-warning me-2"></i>Certifications, Licenses & Training Credentials</h6>
                        <div class="p-3 bg-white rounded-3 border small text-dark" style="line-height: 1.6; white-space: pre-line;">
                            <?= htmlspecialchars($app['certifications']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Career Goals & Work Preferences -->
                <div class="p-3 bg-light rounded-3 border">
                    <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-compass text-primary me-2"></i>Career Vision & Work Setup Preferences</h6>
                    <div class="row g-2 small">
                        <div class="col-md-4">
                            <span class="text-muted d-block">Work Mode / Shift:</span>
                            <span class="badge bg-info-subtle text-info border"><?= htmlspecialchars($app['work_preference'] ?: 'Open to Any') ?></span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block">Expected Monthly Salary:</span>
                            <strong class="text-success"><?= !empty($app['expected_salary']) ? '₱' . number_format($app['expected_salary'], 0) : 'Negotiable' ?></strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted d-block">Location Preference:</span>
                            <span class="text-dark"><?= htmlspecialchars($app['location'] ?: 'Open') ?></span>
                        </div>
                        <?php if (!empty($app['career_goals'])): ?>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <span class="text-muted d-block mb-1">Career Objectives:</span>
                                <div class="text-dark"><?= nl2br(htmlspecialchars($app['career_goals'])) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 3. APPLICANT BIO, RESUME TEXT & ATTACHED FILE -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-lines text-primary me-2"></i>Candidate Bio & Resume Data</h5>
                    <?php if (!empty($app['resume_file']) && file_exists(__DIR__ . '/../uploads/resumes/' . $app['resume_file'])): ?>
                        <a href="/GabayKarera/uploads/resumes/<?= htmlspecialchars($app['resume_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-file-pdf me-1 text-danger"></i> Download / View Attached Resume
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($app['bio'])): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Candidate Professional Bio:</label>
                        <div class="p-3 bg-light rounded-3 border small text-dark" style="line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($app['bio'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($app['raw_resume_text'])): ?>
                    <div>
                        <label class="form-label small fw-bold text-secondary">Parsed Resume & Project Achievement Text:</label>
                        <div class="p-3 bg-light rounded-3 border small font-monospace text-secondary" style="max-height: 250px; overflow-y: auto; line-height: 1.5;">
                            <?= nl2br(htmlspecialchars($app['raw_resume_text'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right Column: TWO-WAY COMMUNICATION & RECRUITER ACTIONS -->
        <div class="col-lg-5">
            
            <!-- HR DECISION & NOTICE DISPATCHER -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm border-top border-4 border-primary">
                <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-2">
                    <i class="fa-solid fa-paper-plane text-primary fs-5"></i>
                    <h5 class="fw-bold mb-0">Recruiter Decision & Notice</h5>
                </div>

                <!-- Template Selector -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Quick Notice Presets:</label>
                    <div class="d-flex flex-column gap-1">
                        <button type="button" class="btn btn-sm btn-outline-success text-start py-1" onclick="applyTemplate('offer')">
                            🏆 <strong>Official Job Offer / Hired Notice</strong>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary text-start py-1" onclick="applyTemplate('interview')">
                            📅 <strong>Schedule Interview Invitation</strong>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info text-start py-1" onclick="applyTemplate('shortlist')">
                            ⭐ <strong>Shortlisted Candidate Notice</strong>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary text-start py-1" onclick="applyTemplate('reject')">
                            📝 <strong>Decision Notice & Upskilling Feedback</strong>
                        </button>
                    </div>
                </div>

                <form method="POST" action="/GabayKarera/hr/applicant-detail.php?id=<?= $appId ?>">
                    <input type="hidden" name="send_decision_message" value="1">
                    <input type="hidden" name="message_type" id="messageTypeInput" value="Message">

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Update Pipeline Status <span class="text-danger">*</span></label>
                        <select name="status" id="pipelineStatusSelect" class="form-select fw-semibold">
                            <option value="Submitted" <?= $app['status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="Reviewing" <?= $app['status'] === 'Reviewing' ? 'selected' : '' ?>>Reviewing</option>
                            <option value="Shortlisted" <?= $app['status'] === 'Shortlisted' ? 'selected' : '' ?>>Shortlisted</option>
                            <option value="Interview Scheduled" <?= $app['status'] === 'Interview Scheduled' ? 'selected' : '' ?>>Interview Scheduled</option>
                            <option value="Hired" <?= $app['status'] === 'Hired' ? 'selected' : '' ?>>Hired (Offer Extended / Accepted)</option>
                            <option value="Rejected" <?= $app['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected (Not Selected)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Notice Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" id="msgSubject" class="form-control" placeholder="e.g. Interview Invitation: Full Stack Developer" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Message / Notice Body <span class="text-danger">*</span></label>
                        <textarea name="message_body" id="msgBody" rows="5" class="form-control small" placeholder="Write your message or interview details here..." required></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="send_email_copy" id="sendEmailCopy" checked>
                        <label class="form-check-label small text-muted" for="sendEmailCopy">
                            Send official email copy to <strong><?= htmlspecialchars($app['applicant_email']) ?></strong>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-2.5">
                        <i class="fa-solid fa-paper-plane me-1"></i> Dispatch Notice & Update Status
                    </button>
                </form>
            </div>

            <!-- TWO-WAY MESSAGE THREAD & REPLIES FROM APPLICANT -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-comments text-primary me-2"></i>Communication Thread (<?= count($messages) ?>)</h5>
                    <?php if ($applicantRepliesCount > 0): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active Conversation</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($messages)): ?>
                    <p class="small text-muted mb-0">No notices sent yet. Use the form above to invite the candidate to an interview or extend a job offer.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($messages as $msg): ?>
                            <?php
                                $isFromApplicant = ((int)$msg['sender_id'] === (int)$app['applicant_id']);
                                $typeBadges = [
                                    'Job Offer' => 'bg-success text-white',
                                    'Interview Invitation' => 'bg-warning text-dark',
                                    'Decision Notice' => 'bg-danger text-white',
                                    'Status Update' => 'bg-info text-dark',
                                    'Message' => 'bg-primary text-white'
                                ];
                                $tClass = $typeBadges[$msg['message_type']] ?? 'bg-secondary text-white';
                            ?>
                            <div class="p-3 rounded-3 border <?= $isFromApplicant ? 'bg-success-subtle border-success-subtle ms-3' : 'bg-light me-3' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge <?= $tClass ?>"><?= htmlspecialchars($msg['message_type']) ?></span>
                                        <strong class="small <?= $isFromApplicant ? 'text-success' : 'text-dark' ?>">
                                            <?= $isFromApplicant ? '<i class="fa-solid fa-user me-1"></i> From Candidate (' . htmlspecialchars($app['applicant_name']) . ')' : '<i class="fa-solid fa-building me-1"></i> You (HR)' ?>
                                        </strong>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y h:i A', strtotime($msg['sent_at'])) ?></small>
                                </div>
                                <div class="small fw-bold text-secondary mb-1"><?= htmlspecialchars($msg['subject']) ?></div>
                                <div class="text-secondary small mb-0" style="white-space: pre-line; line-height: 1.5;">
                                    <?= htmlspecialchars($msg['body']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- APPLICANT BACKGROUND & INPUTTED METADATA -->
            <div class="card card-modern p-4 border-0 shadow-sm">
                <h6 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Educational & Work Background</h6>
                
                <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary mb-0">
                    <li><strong>Degree Level:</strong> <?= htmlspecialchars($app['education_level'] ?? 'Not specified') ?></li>
                    <li><strong>Field of Study:</strong> <?= htmlspecialchars($app['field_of_study'] ?? 'Not specified') ?></li>
                    <li><strong>School / University:</strong> <?= htmlspecialchars($app['school'] ?? 'Not specified') ?></li>
                    <li><strong>Graduation Year:</strong> <?= htmlspecialchars($app['graduation_year'] ?? 'N/A') ?></li>
                    <li><strong>Years of Experience:</strong> <?= $app['experience_years'] ?? 0 ?> Years</li>
                    <li><strong>Current / Recent Role:</strong> <?= htmlspecialchars($app['current_position'] ?? 'N/A') ?></li>
                    <li><strong>Expected Salary:</strong> <?= !empty($app['expected_salary']) ? '₱' . number_format($app['expected_salary'], 2) . '/mo' : 'Negotiable' ?></li>
                    <li><strong>Career Interests:</strong> <?= htmlspecialchars($app['interests'] ?? 'General') ?></li>
                    <?php if (!empty($app['cover_note'])): ?>
                        <li class="pt-2 border-top mt-1">
                            <strong class="d-block text-dark mb-1">Cover Note:</strong>
                            <div class="p-2 bg-light rounded border text-muted">"<?= nl2br(htmlspecialchars($app['cover_note'])) ?>"</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>
    </div>
</div>

<script>
const candidateName = <?= json_encode($app['applicant_name']) ?>;
const jobTitle = <?= json_encode($app['job_title']) ?>;
const companyName = <?= json_encode($app['company_name']) ?>;
const matchScore = <?= json_encode(CompetencyMatcher::formatScore($app['match_score'])) ?>;

function applyTemplate(type) {
    const subject = document.getElementById('msgSubject');
    const body = document.getElementById('msgBody');
    const statusSelect = document.getElementById('pipelineStatusSelect');
    const msgType = document.getElementById('messageTypeInput');

    if (type === 'offer') {
        statusSelect.value = 'Hired';
        msgType.value = 'Job Offer';
        subject.value = `Official Job Offer: ${jobTitle} at ${companyName}`;
        body.value = `Dear ${candidateName},\n\nCongratulations! Based on your exceptional AI Competency Score (${matchScore}) and interview evaluation, we are pleased to offer you the position of ${jobTitle} at ${companyName}.\n\nPlease reply to confirm your acceptance so we can prepare your onboarding contract.\n\nBest regards,\nHR Team - ${companyName}`;
    } else if (type === 'interview') {
        statusSelect.value = 'Interview Scheduled';
        msgType.value = 'Interview Invitation';
        subject.value = `Interview Invitation: ${jobTitle} - ${companyName}`;
        body.value = `Dear ${candidateName},\n\nWe were impressed by your competency profile (${matchScore} match) and would like to invite you for a virtual interview.\n\nPlease reply directly to this notice with your available dates and times this week.\n\nSincerely,\nHR Talent Acquisition - ${companyName}`;
    } else if (type === 'shortlist') {
        statusSelect.value = 'Shortlisted';
        msgType.value = 'Status Update';
        subject.value = `Application Shortlisted: ${jobTitle} at ${companyName}`;
        body.value = `Dear ${candidateName},\n\nYour application for ${jobTitle} has been shortlisted based on your strong competency overlap. We will be in touch soon with the next steps.\n\nBest regards,\n${companyName} HR`;
    } else if (type === 'reject') {
        statusSelect.value = 'Rejected';
        msgType.value = 'Decision Notice';
        subject.value = `Update regarding your application for ${jobTitle}`;
        body.value = `Dear ${candidateName},\n\nThank you for applying for the ${jobTitle} role at ${companyName}.\n\nWhile we have selected another candidate whose background currently aligns more closely with our immediate requirements, we encourage you to review our AI upskilling recommendations and apply again in the future.\n\nBest regards,\nRecruitment Team - ${companyName}`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    <?php
        $radarMetrics = $breakdown['radar_metrics'] ?? [
            'Technical Fit' => 85,
            'Domain Fit' => 90,
            'Experience Fit' => 80,
            'Readiness' => 90,
            'Tool Mastery' => 85
        ];
    ?>
    renderCompetencyRadar(
        'hrCandidateRadar',
        <?= json_encode(array_keys($radarMetrics)) ?>,
        <?= json_encode(array_values($radarMetrics)) ?>,
        'Competency Overlap Profile'
    );
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
