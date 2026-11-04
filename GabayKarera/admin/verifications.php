<?php
/**
 * ============================================================================
 * GABAYKARERA AI - ADMIN DOCUMENT VERIFICATION & COMPLIANCE INSPECTOR
 * File: admin/verifications.php
 * Purpose: Inspect, review, verify, and validate candidate resumes & credentials
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['admin']);
$pdo = Database::getInstance();
$adminId = $_SESSION['user_id'];

// ============================================================================
// 1. ACTION HANDLER: UPDATE VERIFICATION STATUS & REVIEWER NOTES
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_verification'])) {
    $verId = (int)($_POST['verification_id'] ?? 0);
    $status = $_POST['verification_status'] ?? 'pending';
    $notes = trim($_POST['admin_notes'] ?? '');

    if ($verId > 0 && in_array($status, ['pending', 'verified', 'rejected'])) {
        $stmt = $pdo->prepare("UPDATE document_verifications SET verification_status = ?, admin_notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $notes, $adminId, $verId]);

        $_SESSION['flash_success'] = "Document verification status updated to " . strtoupper($status) . ".";
    }

    header("Location: /GabayKarera/admin/verifications.php");
    exit;
}

// ============================================================================
// 2. ACTION HANDLER: AUTO-CREATE VERIFICATION RECORD FOR APPLICANT RESUME
// ============================================================================
if (isset($_GET['create_verification_for_applicant'])) {
    $appUserId = (int)$_GET['create_verification_for_applicant'];
    
    // Check if user exists and has resume
    $uStmt = $pdo->prepare("SELECT u.full_name, p.resume_file FROM users u LEFT JOIN applicant_profiles p ON u.id = p.user_id WHERE u.id = ?");
    $uStmt->execute([$appUserId]);
    $uData = $uStmt->fetch();

    if ($uData) {
        $resFile = $uData['resume_file'] ?: 'resume_profile_' . $appUserId . '.txt';
        $docTitle = $uData['full_name'] . ' - Candidate Resume Document';
        
        $chk = $pdo->prepare("SELECT id FROM document_verifications WHERE user_id = ? AND document_title = ?");
        $chk->execute([$appUserId, $docTitle]);
        if (!$chk->fetch()) {
            $ins = $pdo->prepare("INSERT INTO document_verifications (user_id, document_type, document_title, file_path, verification_status, admin_notes) VALUES (?, 'Resume Document', ?, ?, 'pending', 'Created for candidate credential review.')");
            $ins->execute([$appUserId, $docTitle, 'uploads/resumes/' . $resFile]);
            $_SESSION['flash_success'] = "Document verification request created for " . htmlspecialchars($uData['full_name']);
        }
    }
    header("Location: /GabayKarera/admin/verifications.php");
    exit;
}

// ============================================================================
// 3. DATA FETCHING: VERIFICATION REQUESTS LIST & STATS
// ============================================================================
$statusFilter = $_GET['status_filter'] ?? 'all';

$sql = "
    SELECT v.*, u.full_name, u.email, u.phone, u.role, p.education_level, p.field_of_study, p.experience_years, p.resume_file
    FROM document_verifications v
    JOIN users u ON v.user_id = u.id
    LEFT JOIN applicant_profiles p ON u.id = p.user_id
    WHERE 1=1
";
$params = [];

if (in_array($statusFilter, ['pending', 'verified', 'rejected'])) {
    $sql .= " AND v.verification_status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY v.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$verifications = $stmt->fetchAll();

// Counts for stat metrics
$totalVerifications = $pdo->query("SELECT COUNT(*) FROM document_verifications")->fetchColumn();
$pendingVerifications = $pdo->query("SELECT COUNT(*) FROM document_verifications WHERE verification_status = 'pending'")->fetchColumn();
$approvedVerifications = $pdo->query("SELECT COUNT(*) FROM document_verifications WHERE verification_status = 'verified'")->fetchColumn();
$rejectedVerifications = $pdo->query("SELECT COUNT(*) FROM document_verifications WHERE verification_status = 'rejected'")->fetchColumn();

// Fetch unverified applicants with resume uploads who don't have verification entries yet
$unverifiedApplicantsStmt = $pdo->query("
    SELECT u.id, u.full_name, u.email, p.resume_file, p.education_level
    FROM users u
    JOIN applicant_profiles p ON u.id = p.user_id
    WHERE u.role = 'applicant'
    AND u.id NOT IN (SELECT user_id FROM document_verifications)
");
$unverifiedApplicants = $unverifiedApplicantsStmt->fetchAll();

$pageTitle = "Document Verification & Inspection - Admin";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: DOCUMENT VERIFICATION CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">

    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0"><i class="fa-solid fa-file-circle-check text-primary me-2"></i>Candidate Document Verification & Inspection</h3>
                <span class="badge bg-primary text-white">Admin Oversight</span>
            </div>
            <p class="text-muted mb-0">Review candidate resume uploads, verify educational credentials, and grant verified competency badges.</p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success card-modern p-3 mb-4 d-flex align-items-center">
            <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
            <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- Stat Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-files"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalVerifications ?></h4>
                    <small class="text-muted">Total Submissions</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-amber"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $pendingVerifications ?></h4>
                    <small class="text-muted">Pending Review</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-green"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $approvedVerifications ?></h4>
                    <small class="text-muted">Verified & Approved</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon text-danger bg-danger-subtle"><i class="fa-solid fa-circle-xmark"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $rejectedVerifications ?></h4>
                    <small class="text-muted">Revision Requested</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Applicants Awaiting Verification Dispatcher -->
    <?php if (!empty($unverifiedApplicants)): ?>
        <div class="card card-modern p-4 border-0 shadow-sm mb-4 bg-primary-subtle border-primary-subtle">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-primary mb-0"><i class="fa-solid fa-user-clock me-2"></i>Candidates Registered with Resumes (Ready for Verification)</h6>
                <span class="badge bg-primary"><?= count($unverifiedApplicants) ?> Ready</span>
            </div>
            <div class="row g-2">
                <?php foreach ($unverifiedApplicants as $ua): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="p-3 bg-white rounded-3 border d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold text-dark small"><?= htmlspecialchars($ua['full_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($ua['education_level'] ?: 'Applicant Profile') ?></div>
                            </div>
                            <a href="/GabayKarera/admin/verifications.php?create_verification_for_applicant=<?= $ua['id'] ?>" class="btn btn-sm btn-primary py-1 px-2" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-plus me-1"></i> Add to Inspector
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <ul class="nav nav-pills mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'all' ? 'active fw-bold' : 'bg-white border text-secondary' ?>" href="/GabayKarera/admin/verifications.php?status_filter=all">
                All Documents (<?= $totalVerifications ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'pending' ? 'active fw-bold bg-warning text-dark' : 'bg-white border text-secondary' ?>" href="/GabayKarera/admin/verifications.php?status_filter=pending">
                <i class="fa-solid fa-clock me-1"></i> Pending Review (<?= $pendingVerifications ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'verified' ? 'active fw-bold bg-success' : 'bg-white border text-secondary' ?>" href="/GabayKarera/admin/verifications.php?status_filter=verified">
                <i class="fa-solid fa-circle-check me-1"></i> Verified (<?= $approvedVerifications ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === 'rejected' ? 'active fw-bold bg-danger' : 'bg-white border text-secondary' ?>" href="/GabayKarera/admin/verifications.php?status_filter=rejected">
                <i class="fa-solid fa-circle-xmark me-1"></i> Revision Requested (<?= $rejectedVerifications ?>)
            </a>
        </li>
    </ul>

    <!-- Verification Table -->
    <div class="card card-modern p-4 border-0 shadow-sm">
        <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-list-check text-primary me-2"></i>Document Verification Submissions</h5>

        <?php if (empty($verifications)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-clipboard-check text-muted opacity-25 display-1 mb-3"></i>
                <h5 class="fw-bold text-dark">No Verification Records Found</h5>
                <p class="text-muted small">No candidate documents match the selected filter status.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Candidate / User</th>
                            <th>Document Title & Type</th>
                            <th>Education & Experience</th>
                            <th>Verification Status</th>
                            <th>Reviewer Notes</th>
                            <th class="text-end">Inspector Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verifications as $v): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                            <?= strtoupper(substr($v['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($v['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($v['email']) ?> | <?= htmlspecialchars($v['phone'] ?: 'No phone') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($v['document_title']) ?></div>
                                    <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.7rem;"><?= htmlspecialchars($v['document_type']) ?></span>
                                </td>
                                <td>
                                    <div class="small text-secondary"><?= htmlspecialchars($v['education_level'] ?: 'N/A') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($v['field_of_study'] ?: '') ?> (<?= $v['experience_years'] ?: '0' ?> yrs exp)</small>
                                </td>
                                <td>
                                    <?php if ($v['verification_status'] === 'verified'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-shield-check me-1"></i> VERIFIED</span>
                                    <?php elseif ($v['verification_status'] === 'rejected'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-triangle-exclamation me-1"></i> REJECTED</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle text-dark px-2 py-1"><i class="fa-solid fa-clock me-1"></i> PENDING REVIEW</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= !empty($v['admin_notes']) ? htmlspecialchars($v['admin_notes']) : 'No reviewer notes added.' ?></small>
                                </td>
                                <td class="text-end">
                                    <a href="/GabayKarera/admin/candidate-detail.php?id=<?= $v['user_id'] ?>" class="btn btn-sm btn-outline-secondary me-1" title="View Full Career & Background Dossier">
                                        <i class="fa-solid fa-id-card me-1"></i> Dossier
                                    </a>
                                    <button class="btn btn-sm btn-primary-custom" data-bs-toggle="modal" data-bs-target="#verifyModal<?= $v['id'] ?>">
                                        <i class="fa-solid fa-user-check me-1"></i> Inspect & Verify
                                    </button>
                                </td>
                            </tr>

                            <!-- MODAL FOR INSPECT & VERIFY -->
                            <div class="modal fade" id="verifyModal<?= $v['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content border-0 shadow-lg card-modern text-start">
                                        <div class="modal-header border-bottom bg-light">
                                            <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-file-circle-check me-2"></i>Inspect Document: <?= htmlspecialchars($v['document_title']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST" action="/GabayKarera/admin/verifications.php">
                                            <input type="hidden" name="action_update_verification" value="1">
                                            <input type="hidden" name="verification_id" value="<?= $v['id'] ?>">

                                            <div class="modal-body p-4">
                                                
                                                <!-- Candidate Info Summary Box -->
                                                <div class="p-3 bg-light rounded-3 border mb-4">
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <small class="text-muted d-block">Candidate Name:</small>
                                                            <strong class="text-dark"><?= htmlspecialchars($v['full_name']) ?></strong>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <small class="text-muted d-block">Email Address & Phone:</small>
                                                            <span class="text-dark small"><?= htmlspecialchars($v['email']) ?> (<?= htmlspecialchars($v['phone'] ?: 'N/A') ?>)</span>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <small class="text-muted d-block">Education & Field:</small>
                                                            <span class="text-dark small"><?= htmlspecialchars($v['education_level'] ?: 'N/A') ?> - <?= htmlspecialchars($v['field_of_study'] ?: 'N/A') ?></span>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <small class="text-muted d-block">Candidate Profile & Dossier:</small>
                                                            <a href="/GabayKarera/admin/candidate-detail.php?id=<?= $v['user_id'] ?>" target="_blank" class="btn btn-sm btn-outline-info py-0 px-2 mt-1">
                                                                <i class="fa-solid fa-id-card me-1"></i> Open Full Career Dossier <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                                            </a>
                                                        </div>
                                                        <div class="col-md-6 mt-2">
                                                            <small class="text-muted d-block">Resume File Attachment:</small>
                                                            <?php if (!empty($v['resume_file']) && file_exists(__DIR__ . '/../uploads/resumes/' . $v['resume_file'])): ?>
                                                                <a href="/GabayKarera/uploads/resumes/<?= htmlspecialchars($v['resume_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2 mt-1">
                                                                    <i class="fa-solid fa-file-arrow-down me-1"></i> Open Resume File (<?= htmlspecialchars($v['resume_file']) ?>)
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary text-white mt-1">Profile Resume Text Verified</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Verification Status Choice -->
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Select Verification Outcome Status <span class="text-danger">*</span></label>
                                                    <div class="row g-2">
                                                        <div class="col-4">
                                                            <input type="radio" class="btn-check" name="verification_status" id="vStatVerified<?= $v['id'] ?>" value="verified" <?= $v['verification_status'] === 'verified' ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-success w-100 py-2 fw-bold" for="vStatVerified<?= $v['id'] ?>">
                                                                <i class="fa-solid fa-circle-check me-1"></i> Approve & Verify
                                                            </label>
                                                        </div>
                                                        <div class="col-4">
                                                            <input type="radio" class="btn-check" name="verification_status" id="vStatPending<?= $v['id'] ?>" value="pending" <?= $v['verification_status'] === 'pending' ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-warning text-dark w-100 py-2 fw-bold" for="vStatPending<?= $v['id'] ?>">
                                                                <i class="fa-solid fa-clock me-1"></i> Pending Review
                                                            </label>
                                                        </div>
                                                        <div class="col-4">
                                                            <input type="radio" class="btn-check" name="verification_status" id="vStatRejected<?= $v['id'] ?>" value="rejected" <?= $v['verification_status'] === 'rejected' ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-danger w-100 py-2 fw-bold" for="vStatRejected<?= $v['id'] ?>">
                                                                <i class="fa-solid fa-circle-xmark me-1"></i> Request Revision
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Admin Reviewer Notes -->
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold">Admin Inspector Reviewer Notes / Verification Feedback</label>
                                                    <textarea name="admin_notes" class="form-control" rows="3" placeholder="Enter notes or feedback regarding candidate credentials (e.g. 'Verified against university records', 'Document clear and approved')..."><?= htmlspecialchars($v['admin_notes'] ?? '') ?></textarea>
                                                </div>

                                            </div>
                                            <div class="modal-footer border-top bg-light">
                                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary-custom px-4"><i class="fa-solid fa-floppy-disk me-2"></i> Save Verification Outcome</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
