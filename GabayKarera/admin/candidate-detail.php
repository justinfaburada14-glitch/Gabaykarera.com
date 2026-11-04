<?php
/**
 * ============================================================================
 * GABAYKARERA AI - ADMIN CANDIDATE CAREER & BACKGROUND INSPECTOR
 * File: admin/candidate-detail.php
 * Purpose: Full background, employment history, qualifications, and activity dossier for Admin
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['admin']);
$pdo = Database::getInstance();
$candidateId = (int)($_GET['id'] ?? 0);

// ============================================================================
// 1. DATA FETCHING: USER, PROFILE, SKILLS & APPLICATIONS
// ============================================================================
$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$candidateId]);
$candidate = $uStmt->fetch();

if (!$candidate) {
    $_SESSION['flash_error'] = "Candidate account not found.";
    header("Location: /GabayKarera/admin/dashboard.php");
    exit;
}

// Auto-sync application scores to latest competencies
CompetencyMatcher::syncApplicantApplications($pdo, $candidateId);

$pStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
$pStmt->execute([$candidateId]);
$profile = $pStmt->fetch() ?: [];

// Skills
$sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ? ORDER BY category, skill_name");
$sStmt->execute([$candidateId]);
$skills = $sStmt->fetchAll() ?: [];

// Job Applications
$aStmt = $pdo->prepare("
    SELECT a.*, j.title as job_title, j.company_name, j.location, j.job_type 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.applicant_id = ? 
    ORDER BY a.applied_at DESC
");
$aStmt->execute([$candidateId]);
$applications = $aStmt->fetchAll() ?: [];

// Document Verification Record
$vStmt = $pdo->prepare("SELECT * FROM document_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$vStmt->execute([$candidateId]);
$verification = $vStmt->fetch();

// ============================================================================
// 2. ACTION HANDLER: QUICK ADMIN VERIFICATION / STATUS UPDATE
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_quick_verify'])) {
    $newStatus = $_POST['verification_status'] ?? 'pending';
    $notes = trim($_POST['admin_notes'] ?? '');
    $adminId = $_SESSION['user_id'];

    if ($verification) {
        $uVer = $pdo->prepare("UPDATE document_verifications SET verification_status = ?, admin_notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
        $uVer->execute([$newStatus, $notes, $adminId, $verification['id']]);
    } else {
        $docTitle = $candidate['full_name'] . ' - Candidate Dossier';
        $filePath = !empty($profile['resume_file']) ? 'uploads/resumes/' . $profile['resume_file'] : 'profile_text';
        $iVer = $pdo->prepare("INSERT INTO document_verifications (user_id, document_type, document_title, file_path, verification_status, admin_notes, verified_by, verified_at) VALUES (?, 'Career Profile & Resume', ?, ?, ?, ?, ?, NOW())");
        $iVer->execute([$candidateId, $docTitle, $filePath, $newStatus, $notes, $adminId]);
    }

    $_SESSION['flash_success'] = "Candidate verification status updated to " . strtoupper($newStatus) . "!";
    header("Location: /GabayKarera/admin/candidate-detail.php?id={$candidateId}");
    exit;
}

$pageTitle = "Candidate Career & Background Dossier - " . htmlspecialchars($candidate['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: ADMIN CANDIDATE DOSSIER CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">

    <!-- Breadcrumb -->
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="/GabayKarera/admin/dashboard.php" class="text-decoration-none text-muted small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to User Directory
        </a>
        <a href="/GabayKarera/admin/verifications.php" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-file-circle-check me-1"></i> Document Inspector
        </a>
    </div>

    <!-- Candidate Header Card -->
    <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <!-- Avatar -->
                <?php if (!empty($candidate['avatar']) && $candidate['avatar'] !== 'default_avatar.png' && file_exists(__DIR__ . '/../uploads/avatars/' . $candidate['avatar'])): ?>
                    <img src="/GabayKarera/uploads/avatars/<?= htmlspecialchars($candidate['avatar']) ?>" alt="Avatar" class="rounded-circle shadow object-fit-cover border border-3 border-primary" style="width: 80px; height: 80px;">
                <?php else: ?>
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-2 shadow" style="width: 80px; height: 80px;">
                        <?= strtoupper(substr($candidate['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($candidate['full_name']) ?></h4>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-uppercase"><?= $candidate['role'] ?></span>
                        
                        <?php if ($verification && $verification['verification_status'] === 'verified'): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-shield-check me-1"></i> Verified Candidate</span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle text-dark"><i class="fa-solid fa-clock me-1"></i> Pending Verification</span>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-muted mb-1"><?= !empty($profile['headline']) ? htmlspecialchars($profile['headline']) : 'Registered Job Seeker' ?></p>
                    
                    <div class="text-secondary small d-flex flex-wrap gap-3 mb-2">
                        <span><i class="fa-regular fa-envelope text-primary me-1"></i> <?= htmlspecialchars($candidate['email']) ?></span>
                        <span><i class="fa-solid fa-phone text-primary me-1"></i> <?= htmlspecialchars($candidate['phone'] ?? 'No phone recorded') ?></span>
                        <span><i class="fa-solid fa-location-dot text-danger me-1"></i> <?= htmlspecialchars($profile['location'] ?? 'Philippines') ?></span>
                        <span><i class="fa-regular fa-calendar-check me-1"></i> Member since <?= date('M d, Y', strtotime($candidate['created_at'])) ?></span>
                    </div>

                    <!-- External Links & Badges -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php if (!empty($profile['academic_honors'])): ?>
                            <span class="badge bg-warning-subtle text-dark border"><i class="fa-solid fa-award text-warning me-1"></i> <?= htmlspecialchars($profile['academic_honors']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($profile['work_preference'])): ?>
                            <span class="badge bg-info-subtle text-info border"><i class="fa-solid fa-laptop-house me-1"></i> <?= htmlspecialchars($profile['work_preference']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($profile['portfolio_url'])): ?>
                            <a href="<?= htmlspecialchars($profile['portfolio_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;"><i class="fa-solid fa-globe me-1"></i> Portfolio</a>
                        <?php endif; ?>
                        <?php if (!empty($profile['linkedin_url'])): ?>
                            <a href="<?= htmlspecialchars($profile['linkedin_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;"><i class="fa-brands fa-linkedin me-1"></i> LinkedIn</a>
                        <?php endif; ?>
                        <?php if (!empty($profile['github_url'])): ?>
                            <a href="<?= htmlspecialchars($profile['github_url']) ?>" target="_blank" class="btn btn-sm btn-outline-dark py-0 px-2" style="font-size: 0.75rem;"><i class="fa-brands fa-github me-1"></i> GitHub</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="text-md-end bg-light p-3 rounded-3 border">
                <small class="text-muted fw-bold d-block">Platform Applications</small>
                <div class="h4 fw-bold text-primary mb-1"><?= count($applications) ?> Submissions</div>
                <small class="text-muted">Skills Registered: <?= count($skills) ?></small>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success card-modern p-3 mb-4 d-flex align-items-center">
            <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i>
            <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Left: COMPREHENSIVE CAREER & BACKGROUND DETAILS -->
        <div class="col-lg-7">
            
            <!-- 1. ACADEMIC BACKGROUND & EDUCATION -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-graduation-cap text-primary me-2"></i>Academic Background & Educational Qualifications</h5>
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <span class="text-muted d-block">Highest Degree Level:</span>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($profile['education_level'] ?: 'Not specified') ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Field of Study / Degree Program:</span>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($profile['field_of_study'] ?: 'General') ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">School / University:</span>
                        <span class="text-dark"><?= htmlspecialchars($profile['school'] ?: 'Not specified') ?></span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block">Graduation Year:</span>
                        <span class="text-dark"><?= htmlspecialchars($profile['graduation_year'] ?: 'N/A') ?></span>
                    </div>
                    <div class="col-md-3">
                        <span class="text-muted d-block">Honors & Awards:</span>
                        <?php if (!empty($profile['academic_honors'])): ?>
                            <span class="badge bg-warning text-dark"><?= htmlspecialchars($profile['academic_honors']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">None recorded</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. EMPLOYMENT & CAREER WORK HISTORY -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-briefcase text-primary me-2"></i>Career & Employment Work History</h5>
                    <span class="badge bg-light text-secondary border"><?= $profile['experience_years'] ?? '0' ?> Years Experience</span>
                </div>

                <div class="row g-2 small mb-3">
                    <div class="col-md-6">
                        <span class="text-muted d-block">Current / Recent Position:</span>
                        <strong class="text-dark"><?= htmlspecialchars($profile['current_position'] ?: 'Job Candidate') ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Expected Monthly Salary:</span>
                        <strong class="text-success"><?= !empty($profile['expected_salary']) ? '₱' . number_format($profile['expected_salary'], 0) : 'Negotiable' ?></strong>
                    </div>
                </div>

                <?php if (!empty($profile['work_history'])): ?>
                    <div class="p-3 bg-light rounded-3 border small text-dark" style="line-height: 1.6; white-space: pre-line;">
                        <?= htmlspecialchars($profile['work_history']) ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No past employment history narrative provided.</p>
                <?php endif; ?>
            </div>

            <!-- 3. CERTIFICATIONS & PROFESSIONAL LICENSES -->
            <?php if (!empty($profile['certifications'])): ?>
                <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                    <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-certificate text-warning me-2"></i>Certifications, Licenses & Training</h5>
                    <div class="p-3 bg-light rounded-3 border small text-dark" style="line-height: 1.6; white-space: pre-line;">
                        <?= htmlspecialchars($profile['certifications']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 4. CAREER VISION & BIO -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-compass text-primary me-2"></i>Career Vision & Professional Bio</h5>
                
                <?php if (!empty($profile['career_goals'])): ?>
                    <div class="mb-3">
                        <small class="fw-bold text-secondary d-block mb-1">Career Objectives:</small>
                        <div class="p-3 bg-light rounded-3 border small text-dark" style="line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($profile['career_goals'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profile['bio'])): ?>
                    <div>
                        <small class="fw-bold text-secondary d-block mb-1">Professional Bio:</small>
                        <div class="p-3 bg-light rounded-3 border small text-dark" style="line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($profile['bio'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 5. REGISTERED SKILL PORTFOLIO -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-tags text-primary me-2"></i>Registered Competency Skills (<?= count($skills) ?>)</h5>
                <?php if (empty($skills)): ?>
                    <p class="text-muted small mb-0">No competencies registered.</p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($skills as $sk): ?>
                            <span class="skill-tag">
                                <strong><?= htmlspecialchars($sk['skill_name']) ?></strong>
                                <small class="text-muted">(<?= htmlspecialchars($sk['proficiency']) ?> &bull; <?= $sk['years_experience'] ?> yr)</small>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right: RESUME ATTACHMENT, VERIFICATION OVERSIGHT & APPLICATION HISTORY -->
        <div class="col-lg-5">
            
            <!-- ADMIN VERIFICATION CONTROL BOX -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm border-top border-4 border-primary">
                <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-2">
                    <i class="fa-solid fa-shield-halved text-primary fs-5"></i>
                    <h5 class="fw-bold mb-0">Admin Verification Control</h5>
                </div>

                <form method="POST" action="/GabayKarera/admin/candidate-detail.php?id=<?= $candidateId ?>">
                    <input type="hidden" name="action_quick_verify" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Verification Decision Status</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="verification_status" id="vStatVerified" value="verified" <?= ($verification && $verification['verification_status'] === 'verified') ? 'checked' : '' ?>>
                                <label class="btn btn-outline-success w-100 py-2 small fw-bold" for="vStatVerified">Verified</label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="verification_status" id="vStatPending" value="pending" <?= (!$verification || $verification['verification_status'] === 'pending') ? 'checked' : '' ?>>
                                <label class="btn btn-outline-warning text-dark w-100 py-2 small fw-bold" for="vStatPending">Pending</label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="verification_status" id="vStatRejected" value="rejected" <?= ($verification && $verification['verification_status'] === 'rejected') ? 'checked' : '' ?>>
                                <label class="btn btn-outline-danger w-100 py-2 small fw-bold" for="vStatRejected">Revision</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Admin Verification Notes / Feedback</label>
                        <textarea name="admin_notes" rows="3" class="form-control small" placeholder="Enter administrative notes regarding educational background, resume verification, etc..."><?= htmlspecialchars($verification['admin_notes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-2">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Verification Status
                    </button>
                </form>
            </div>

            <!-- RESUME DOCUMENT ATTACHMENT -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-pdf text-danger me-2"></i>Uploaded Resume File</h5>
                </div>

                <?php if (!empty($profile['resume_file']) && file_exists(__DIR__ . '/../uploads/resumes/' . $profile['resume_file'])): ?>
                    <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong class="text-dark small d-block"><?= htmlspecialchars($profile['resume_file']) ?></strong>
                            <small class="text-muted">Stored in uploads/resumes/</small>
                        </div>
                        <a href="/GabayKarera/uploads/resumes/<?= htmlspecialchars($profile['resume_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-download me-1"></i> View / Download
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-3">No separate resume document file uploaded.</p>
                <?php endif; ?>

                <?php if (!empty($profile['raw_resume_text'])): ?>
                    <div>
                        <label class="form-label small fw-bold text-secondary">Parsed Resume & Achievement Text:</label>
                        <div class="p-3 bg-light rounded-3 border small font-monospace text-secondary" style="max-height: 200px; overflow-y: auto; line-height: 1.5;">
                            <?= nl2br(htmlspecialchars($profile['raw_resume_text'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PLATFORM JOB APPLICATIONS HISTORY -->
            <div class="card card-modern p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-file-lines text-primary me-2"></i>Applications Submitted (<?= count($applications) ?>)</h5>
                
                <?php if (empty($applications)): ?>
                    <p class="text-muted small mb-0">Candidate has not submitted any applications yet.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($applications as $appItem): ?>
                            <div class="p-3 border rounded-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold text-dark mb-0 small"><?= htmlspecialchars($appItem['job_title']) ?></h6>
                                    <span class="badge bg-primary"><?= CompetencyMatcher::formatScore($appItem['match_score']) ?> Fit</span>
                                </div>
                                <div class="text-muted small mb-2">
                                    <i class="fa-regular fa-building me-1"></i> <?= htmlspecialchars($appItem['company_name']) ?> &bull; <?= date('M d, Y', strtotime($appItem['applied_at'])) ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($appItem['status']) ?></span>
                                    <?php if (!empty($appItem['cover_note'])): ?>
                                        <small class="text-muted" title="<?= htmlspecialchars($appItem['cover_note']) ?>"><i class="fa-solid fa-message me-1"></i> Cover Note</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
