<?php
/**
 * ============================================================================
 * GABAYKARERA AI - APPLICANT APPLICATIONS & TWO-WAY MESSAGING
 * File: applicant/applications.php
 * Purpose: Application status tracker, employer notice alerts, and 1-click replies
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['applicant']);
$pdo = Database::getInstance();
$userId = $_SESSION['user_id'];

// Automatically synchronize match scores with latest profile competencies
CompetencyMatcher::syncApplicantApplications($pdo, $userId);

// ============================================================================
// 1. ACTION HANDLER: APPLICANT REPLY TO RECRUITER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_applicant_reply'])) {
    $appId = (int)($_POST['application_id'] ?? 0);
    $employerId = (int)($_POST['employer_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? 'Response from Applicant');
    $body = trim($_POST['reply_body'] ?? '');

    if (!empty($body) && $appId > 0 && $employerId > 0) {
        // Verify application belongs to this applicant
        $vStmt = $pdo->prepare("SELECT id FROM applications WHERE id = ? AND applicant_id = ?");
        $vStmt->execute([$appId, $userId]);
        if ($vStmt->fetch()) {
            $msgStmt = $pdo->prepare("
                INSERT INTO application_messages (application_id, sender_id, receiver_id, message_type, subject, body, is_read) 
                VALUES (?, ?, ?, 'Message', ?, ?, 0)
            ");
            $msgStmt->execute([$appId, $userId, $employerId, $subject, $body]);
            $_SESSION['flash_success'] = "Your reply was successfully sent to the employer!";
        }
    } else {
        $_SESSION['flash_error'] = "Please write a message before sending your reply.";
    }
    header("Location: /GabayKarera/applicant/applications.php");
    exit;
}

// ============================================================================
// 2. DATA FETCHING: APPLICATIONS & TWO-WAY MESSAGE THREADS
// ============================================================================
$stmt = $pdo->prepare("
    SELECT a.*, j.title, j.company_name, j.location, j.job_type, j.salary_min, j.salary_max, j.employer_id,
           (SELECT COUNT(*) FROM application_messages WHERE application_id = a.id) as message_count
    FROM applications a 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.applicant_id = ? 
    ORDER BY a.applied_at DESC
");
$stmt->execute([$userId]);
$applications = $stmt->fetchAll();

$appIds = array_column($applications, 'id');
$messagesByApp = [];
if (!empty($appIds)) {
    $inClause = implode(',', array_fill(0, count($appIds), '?'));
    $mStmt = $pdo->prepare("
        SELECT m.*, u.full_name as sender_name, u.role as sender_role, u.company_name as sender_company
        FROM application_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.application_id IN ($inClause)
        ORDER BY m.sent_at ASC
    ");
    $mStmt->execute($appIds);
    $allMsgs = $mStmt->fetchAll();
    foreach ($allMsgs as $msg) {
        $messagesByApp[$msg['application_id']][] = $msg;
    }
}

$pageTitle = "My Applications & Recruiter Communications - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: APPLICANT APPLICATIONS TRACKER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. PAGE HEADER ==================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-file-lines text-primary me-2"></i>My Applications & Recruiter Communications</h3>
            <p class="text-muted mb-0">Track your application pipeline, review interview invitations or job offers, and reply directly to employers.</p>
        </div>
        <a href="/GabayKarera/applicant/jobs.php" class="btn btn-primary-custom mt-3 mt-md-0">
            <i class="fa-solid fa-magnifying-glass me-1"></i> Browse More Jobs
        </a>
    </div>

    <!-- ==================== 2. APPLICATION LIST OR EMPTY STATE ==================== -->
    <?php if (empty($applications)): ?>
        <div class="card card-modern p-5 text-center border-0">
            <i class="fa-solid fa-file-circle-question fs-1 text-secondary opacity-50 mb-3"></i>
            <h5 class="fw-bold">No applications submitted yet</h5>
            <p class="text-muted small">Explore matched job listings and submit applications with your AI competency profile.</p>
            <a href="/GabayKarera/applicant/jobs.php" class="btn btn-outline-custom mx-auto">Explore Matched Jobs</a>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-4">
            <?php foreach ($applications as $app): ?>
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
                    $appMsgs = $messagesByApp[$app['id']] ?? [];
                ?>
                <div class="card card-modern p-4 border-0 shadow-sm <?= $app['status'] === 'Hired' ? 'border-start border-5 border-success' : ($app['status'] === 'Interview Scheduled' ? 'border-start border-5 border-warning' : '') ?>">
                    
                    <!-- Special Status Callouts -->
                    <?php if ($app['status'] === 'Hired'): ?>
                        <div class="alert alert-success d-flex align-items-center mb-3 p-3 rounded-3 shadow-sm border-0">
                            <i class="fa-solid fa-trophy fs-3 me-3 text-success"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-success">🎉 Congratulations! You have received a Job Offer!</h6>
                                <small>The employer has marked your status as <strong>Hired</strong> and sent an official notice below. You can send an acceptance reply directly.</small>
                            </div>
                        </div>
                    <?php elseif ($app['status'] === 'Interview Scheduled'): ?>
                        <div class="alert alert-warning d-flex align-items-center mb-3 p-3 rounded-3 shadow-sm border-0">
                            <i class="fa-solid fa-calendar-check fs-3 me-3 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">📅 Interview Invitation Received!</h6>
                                <small>The hiring team has scheduled an interview for this position. Please reply below to confirm your attendance or request a reschedule.</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($app['job_type']) ?></span>
                                <span class="badge <?= $sClass ?> px-3 py-1 rounded-pill fw-bold">Status: <?= htmlspecialchars($app['status']) ?></span>
                                <span class="badge badge-success-glow fw-bold"><?= CompetencyMatcher::formatScore($app['match_score']) ?> AI Fit</span>
                            </div>
                            <h4 class="fw-bold mb-1 text-dark">
                                <a href="/GabayKarera/applicant/job-details.php?id=<?= $app['job_id'] ?>" class="text-dark text-decoration-none hover-primary">
                                    <?= htmlspecialchars($app['title']) ?>
                                </a>
                            </h4>
                            <div class="text-muted small">
                                <i class="fa-regular fa-building me-1"></i> <strong><?= htmlspecialchars($app['company_name']) ?></strong> &bull;
                                <i class="fa-solid fa-location-dot ms-1 me-1"></i> <?= htmlspecialchars($app['location']) ?> &bull;
                                <i class="fa-regular fa-clock ms-1 me-1"></i> Applied on <?= date('M d, Y', strtotime($app['applied_at'])) ?>
                            </div>
                        </div>

                        <div class="text-md-end flex-shrink-0">
                            <a href="/GabayKarera/applicant/job-details.php?id=<?= $app['job_id'] ?>" class="btn btn-sm btn-outline-custom">
                                View Job & Competency Fit <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <!-- HR Communications & Two-Way Message Log -->
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-comments text-primary me-2"></i>Recruiter Communications & Messages (<?= count($appMsgs) ?>)
                            </h6>
                            <button class="btn btn-sm btn-primary-custom py-1 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#replyForm_<?= $app['id'] ?>">
                                <i class="fa-solid fa-reply me-1"></i> Reply / Send Message
                            </button>
                        </div>

                        <!-- Message History Thread -->
                        <?php if (empty($appMsgs)): ?>
                            <p class="small text-muted mb-0">Your application is currently under review with the employer. Any notices or interview invitations will appear here, and you can reply directly.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2 mt-2 mb-3">
                                <?php foreach ($appMsgs as $m): ?>
                                    <?php
                                        $isFromMe = ((int)$m['sender_id'] === (int)$userId);
                                        $typeBadges = [
                                            'Job Offer' => 'bg-success text-white',
                                            'Interview Invitation' => 'bg-warning text-dark',
                                            'Decision Notice' => 'bg-danger text-white',
                                            'Status Update' => 'bg-info text-dark',
                                            'Message' => 'bg-primary text-white'
                                        ];
                                        $mClass = $typeBadges[$m['message_type']] ?? 'bg-secondary text-white';
                                    ?>
                                    <div class="p-3 rounded-3 border <?= $isFromMe ? 'bg-primary-subtle border-primary-subtle ms-4' : 'bg-white me-4' ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= $mClass ?>"><?= htmlspecialchars($m['message_type']) ?></span>
                                                <strong class="small <?= $isFromMe ? 'text-primary' : 'text-dark' ?>">
                                                    <?= $isFromMe ? 'You (Applicant)' : htmlspecialchars($m['sender_name']) . ' (Employer)' ?>
                                                </strong>
                                                &bull; <span class="small text-muted"><?= htmlspecialchars($m['subject']) ?></span>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y h:i A', strtotime($m['sent_at'])) ?></small>
                                        </div>
                                        <div class="text-secondary small mt-2" style="white-space: pre-line; line-height: 1.6;">
                                            <?= htmlspecialchars($m['body']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Interactive Reply Form (Collapsible) -->
                        <div class="collapse mt-3" id="replyForm_<?= $app['id'] ?>">
                            <div class="card p-3 border shadow-sm bg-white">
                                <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Send Reply to <?= htmlspecialchars($app['company_name']) ?></h6>
                                
                                <!-- Quick Reply Preset Buttons -->
                                <div class="mb-2 d-flex flex-wrap gap-1">
                                    <small class="text-muted fw-bold d-block w-100 mb-1">Quick Response Presets:</small>
                                    <button type="button" class="btn btn-sm btn-outline-success py-1" onclick="setReplyText('<?= $app['id'] ?>', 'confirm')">
                                        ✅ Confirm Interview Attendance
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning py-1" onclick="setReplyText('<?= $app['id'] ?>', 'reschedule')">
                                        🔄 Request Reschedule
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-1" onclick="setReplyText('<?= $app['id'] ?>', 'accept_offer')">
                                        🏆 Accept Job Offer
                                    </button>
                                </div>

                                <form method="POST" action="/GabayKarera/applicant/applications.php">
                                    <input type="hidden" name="send_applicant_reply" value="1">
                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                    <input type="hidden" name="employer_id" value="<?= $app['employer_id'] ?>">

                                    <div class="mb-2">
                                        <label class="form-label small fw-bold mb-1">Subject</label>
                                        <input type="text" name="subject" id="replySubject_<?= $app['id'] ?>" class="form-control form-control-sm" value="Re: Application for <?= htmlspecialchars($app['title']) ?>" required>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small fw-bold mb-1">Your Message / Reply</label>
                                        <textarea name="reply_body" id="replyBody_<?= $app['id'] ?>" rows="3" class="form-control form-control-sm" placeholder="Write your response to the employer here..." required></textarea>
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-sm btn-light border me-1" data-bs-toggle="collapse" data-bs-target="#replyForm_<?= $app['id'] ?>">Cancel</button>
                                        <button type="submit" class="btn btn-sm btn-primary-custom px-3">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Send Reply
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function setReplyText(appId, type) {
    const subjectEl = document.getElementById('replySubject_' + appId);
    const bodyEl = document.getElementById('replyBody_' + appId);
    if (!subjectEl || !bodyEl) return;

    if (type === 'confirm') {
        subjectEl.value = "Interview Attendance Confirmed";
        bodyEl.value = "Dear Hiring Team,\n\nThank you for the interview invitation. I am writing to confirm my attendance for the scheduled interview. I look forward to speaking with your team and discussing how my competencies align with the position.\n\nBest regards,\n<?= htmlspecialchars($_SESSION['user_name']) ?>";
    } else if (type === 'reschedule') {
        subjectEl.value = "Interview Reschedule Request";
        bodyEl.value = "Dear Hiring Team,\n\nThank you for the interview invitation. Unfortunately, I have a prior commitment at the proposed time. Would it be possible to reschedule to an alternative time or date? I appreciate your flexibility.\n\nBest regards,\n<?= htmlspecialchars($_SESSION['user_name']) ?>";
    } else if (type === 'accept_offer') {
        subjectEl.value = "Job Offer Acceptance";
        bodyEl.value = "Dear Hiring Team,\n\nI am thrilled and honored to formally accept your job offer. Thank you for this wonderful opportunity. I look forward to receiving the onboarding details and joining the team.\n\nSincerely,\n<?= htmlspecialchars($_SESSION['user_name']) ?>";
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
