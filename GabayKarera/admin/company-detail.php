<?php
/**
 * ============================================================================
 * GABAYKARERA AI - ADMIN HR COMPANY DOSSIER & VERIFICATION CENTER
 * File: admin/company-detail.php
 * Purpose: Inspect, audit, verify, and regulate employer companies and HR representatives
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../classes/CompetencyMatcher.php';

Auth::requireAuth(['admin']);
$pdo = Database::getInstance();
$hrId = (int)($_GET['id'] ?? 0);

// ============================================================================
// 1. DATA FETCHING: HR EMPLOYER ACCOUNT & CORPORATE CREDENTIALS
// ============================================================================
$uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'hr'");
$uStmt->execute([$hrId]);
$hr = $uStmt->fetch();

if (!$hr) {
    $_SESSION['flash_error'] = "HR Employer account not found.";
    header("Location: /GabayKarera/admin/dashboard.php");
    exit;
}

// Fetch all jobs posted by this employer
$jobsStmt = $pdo->prepare("
    SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as applicant_count
    FROM jobs j
    WHERE j.employer_id = ?
    ORDER BY j.created_at DESC
");
$jobsStmt->execute([$hrId]);
$jobs = $jobsStmt->fetchAll() ?: [];

// Fetch all applications submitted to this employer's jobs
$appsStmt = $pdo->prepare("
    SELECT a.*, u.full_name as applicant_name, u.email as applicant_email, u.phone as applicant_phone, j.title as job_title
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON a.applicant_id = u.id
    WHERE j.employer_id = ?
    ORDER BY a.applied_at DESC
");
$appsStmt->execute([$hrId]);
$applications = $appsStmt->fetchAll() ?: [];

// ============================================================================
// 2. ACTION HANDLER: UPDATE COMPANY VERIFICATION & AUDIT COMPLIANCE
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_company_verification'])) {
    $verificationStatus = $_POST['company_verification_status'] ?? 'pending';
    $notes = trim($_POST['company_verification_notes'] ?? '');
    
    // Optional administrative updates to business info
    $companyName = trim($_POST['company_name'] ?? $hr['company_name']);
    $regNumber = trim($_POST['company_reg_number'] ?? ($hr['company_reg_number'] ?? ''));
    $tin = trim($_POST['company_tin'] ?? ($hr['company_tin'] ?? ''));
    $website = trim($_POST['company_website'] ?? ($hr['company_website'] ?? ''));
    $address = trim($_POST['company_address'] ?? ($hr['company_address'] ?? ''));
    $industry = trim($_POST['company_industry'] ?? ($hr['company_industry'] ?? 'Information Technology & Services'));
    $companySize = trim($_POST['company_size'] ?? ($hr['company_size'] ?? '50-200 Employees'));
    $hrDesignation = trim($_POST['hr_designation'] ?? ($hr['hr_designation'] ?? 'HR & Talent Acquisition Lead'));

    if (in_array($verificationStatus, ['pending', 'verified', 'rejected'])) {
        $uUpd = $pdo->prepare("
            UPDATE users 
            SET company_name = ?, company_reg_number = ?, company_tin = ?, company_website = ?, 
                company_address = ?, company_industry = ?, company_size = ?, hr_designation = ?,
                company_verification_status = ?, company_verification_notes = ?
            WHERE id = ?
        ");
        $uUpd->execute([
            $companyName, $regNumber, $tin, $website, 
            $address, $industry, $companySize, $hrDesignation,
            $verificationStatus, $notes, $hrId
        ]);

        $_SESSION['flash_success'] = "Employer & Company verification status updated to " . strtoupper($verificationStatus) . " successfully!";
    }

    header("Location: /GabayKarera/admin/company-detail.php?id={$hrId}");
    exit;
}

$pageTitle = "Company HR Dossier & Verification - " . htmlspecialchars($hr['company_name'] ?: $hr['full_name']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: ADMIN HR COMPANY DOSSIER CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">

    <!-- Breadcrumb -->
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="/GabayKarera/admin/dashboard.php" class="text-decoration-none text-muted small">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to User Directory
        </a>
        <span class="badge bg-dark text-white">Administrator Compliance & Verification</span>
    </div>

    <!-- Employer & Company Header Card -->
    <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-2 shadow" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-building"></i>
                </div>

                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($hr['company_name'] ?: 'Independent Employer') ?></h4>
                        <span class="badge bg-success-subtle text-success border border-success-subtle text-uppercase">HR Employer</span>
                        
                        <?php if (($hr['company_verification_status'] ?? 'pending') === 'verified'): ?>
                            <span class="badge bg-success text-white"><i class="fa-solid fa-shield-check me-1"></i> VERIFIED EMPLOYER</span>
                        <?php elseif (($hr['company_verification_status'] ?? 'pending') === 'rejected'): ?>
                            <span class="badge bg-danger text-white"><i class="fa-solid fa-triangle-exclamation me-1"></i> REJECTED / FLAGGED</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> PENDING VERIFICATION</span>
                        <?php endif; ?>
                    </div>
                    
                    <p class="text-muted mb-1">
                        <i class="fa-solid fa-user-tie text-primary me-1"></i> <strong>Authorized Representative:</strong> <?= htmlspecialchars($hr['full_name']) ?> (<?= htmlspecialchars($hr['hr_designation'] ?? 'HR Officer') ?>)
                    </p>
                    
                    <div class="text-secondary small d-flex flex-wrap gap-3">
                        <span><i class="fa-regular fa-envelope text-primary me-1"></i> <?= htmlspecialchars($hr['email']) ?></span>
                        <span><i class="fa-solid fa-phone text-primary me-1"></i> <?= htmlspecialchars($hr['phone'] ?: 'No phone recorded') ?></span>
                        <span><i class="fa-solid fa-industry text-info me-1"></i> <?= htmlspecialchars($hr['company_industry'] ?? 'Information Technology') ?></span>
                        <span><i class="fa-regular fa-calendar-check me-1"></i> Registered on <?= date('M d, Y', strtotime($hr['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Metrics -->
            <div class="text-md-end bg-light p-3 rounded-3 border">
                <small class="text-muted fw-bold d-block">Published Job Openings</small>
                <div class="h4 fw-bold text-success mb-1"><?= count($jobs) ?> Active Postings</div>
                <small class="text-muted">Total Applicants Received: <?= count($applications) ?></small>
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
        
        <!-- Left: CORPORATE CREDENTIALS & AUDIT DETAILS -->
        <div class="col-lg-7">
            
            <!-- 1. BUSINESS REGISTRATION & CORPORATE CREDENTIALS -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-file-shield text-primary me-2"></i>Official Business Registration & Legitimacy Credentials</h5>
                
                <div class="row g-3 small">
                    <div class="col-md-6">
                        <span class="text-muted d-block">Registered Entity Name:</span>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($hr['company_name'] ?: 'Not specified') ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Industry Sector:</span>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($hr['company_industry'] ?? 'Information Technology & Services') ?></strong>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">SEC / DTI / Business Permit Registration No.:</span>
                        <?php if (!empty($hr['company_reg_number'])): ?>
                            <span class="badge bg-light text-dark border font-monospace fs-7"><?= htmlspecialchars($hr['company_reg_number']) ?></span>
                        <?php else: ?>
                            <span class="text-danger fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i> Awaiting Document Submission</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">BIR Tax Identification Number (TIN):</span>
                        <?php if (!empty($hr['company_tin'])): ?>
                            <span class="badge bg-light text-dark border font-monospace fs-7"><?= htmlspecialchars($hr['company_tin']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">Not declared</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Company Workforce Size:</span>
                        <span class="text-dark"><?= htmlspecialchars($hr['company_size'] ?? '50-200 Employees') ?></span>
                    </div>
                    <div class="col-md-6">
                        <span class="text-muted d-block">Official Corporate Website:</span>
                        <?php if (!empty($hr['company_website'])): ?>
                            <a href="<?= htmlspecialchars($hr['company_website']) ?>" target="_blank" class="text-primary text-decoration-none">
                                <?= htmlspecialchars($hr['company_website']) ?> <i class="fa-solid fa-arrow-up-right-from-square small"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">No website URL recorded</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top">
                        <span class="text-muted d-block mb-1">Corporate Physical Office Address:</span>
                        <div class="p-2 bg-light rounded border text-dark">
                            <?= !empty($hr['company_address']) ? htmlspecialchars($hr['company_address']) : 'Metro Manila, Philippines (Specific office address pending update)' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. PUBLISHED JOB POSTINGS BY THIS EMPLOYER -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-briefcase text-primary me-2"></i>Job Openings Published (<?= count($jobs) ?>)</h5>
                    <span class="small text-muted">Audited for compliance</span>
                </div>

                <?php if (empty($jobs)): ?>
                    <p class="text-muted small mb-0">No job openings created by this employer yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-modern align-middle mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Job Title</th>
                                    <th>Type</th>
                                    <th>Salary Range</th>
                                    <th>Applicants</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $jobItem): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?= htmlspecialchars($jobItem['title']) ?></strong>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($jobItem['location']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($jobItem['job_type']) ?></span></td>
                                        <td class="text-success small fw-bold">₱<?= number_format($jobItem['salary_min'], 0) ?> - ₱<?= number_format($jobItem['salary_max'], 0) ?></td>
                                        <td><span class="badge bg-primary-subtle text-primary"><?= $jobItem['applicant_count'] ?> Applicants</span></td>
                                        <td>
                                            <span class="badge <?= $jobItem['status'] === 'Open' ? 'bg-success' : 'bg-secondary' ?>"><?= $jobItem['status'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. RECENT CANDIDATE APPLICATIONS RECEIVED -->
            <div class="card card-modern p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-dark"><i class="fa-solid fa-users text-primary me-2"></i>Applicants Handled by this HR (<?= count($applications) ?>)</h5>
                
                <?php if (empty($applications)): ?>
                    <p class="text-muted small mb-0">No job applications received yet.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach (array_slice($applications, 0, 5) as $appItem): ?>
                            <div class="p-2 px-3 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark small d-block"><?= htmlspecialchars($appItem['applicant_name']) ?></strong>
                                    <small class="text-muted">Applied for: <?= htmlspecialchars($appItem['job_title']) ?> &bull; <?= date('M d, Y', strtotime($appItem['applied_at'])) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary me-1"><?= CompetencyMatcher::formatScore($appItem['match_score']) ?> Fit</span>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= htmlspecialchars($appItem['status']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right: ADMIN COMPLIANCE AUDIT & VERIFICATION DECISION -->
        <div class="col-lg-5">
            
            <!-- VERIFICATION DECISION PANEL -->
            <div class="card card-modern p-4 border-0 mb-4 shadow-sm border-top border-4 border-primary">
                <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-2">
                    <i class="fa-solid fa-shield-halved text-primary fs-5"></i>
                    <h5 class="fw-bold mb-0">Admin Employer Verification</h5>
                </div>

                <form method="POST" action="/GabayKarera/admin/company-detail.php?id=<?= $hrId ?>">
                    <input type="hidden" name="action_update_company_verification" value="1">
                    
                    <!-- Verification Choice -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Verification Decision Outcome <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="company_verification_status" id="vStatVerified" value="verified" <?= (($hr['company_verification_status'] ?? 'pending') === 'verified') ? 'checked' : '' ?>>
                                <label class="btn btn-outline-success w-100 py-2 small fw-bold" for="vStatVerified">
                                    <i class="fa-solid fa-shield-check me-1"></i> Verify
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="company_verification_status" id="vStatPending" value="pending" <?= (($hr['company_verification_status'] ?? 'pending') === 'pending') ? 'checked' : '' ?>>
                                <label class="btn btn-outline-warning text-dark w-100 py-2 small fw-bold" for="vStatPending">
                                    <i class="fa-solid fa-clock me-1"></i> Pending
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="company_verification_status" id="vStatRejected" value="rejected" <?= (($hr['company_verification_status'] ?? 'pending') === 'rejected') ? 'checked' : '' ?>>
                                <label class="btn btn-outline-danger w-100 py-2 small fw-bold" for="vStatRejected">
                                    <i class="fa-solid fa-ban me-1"></i> Reject
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Administrator Audit Notes -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Administrator Audit & Compliance Remarks</label>
                        <textarea name="company_verification_notes" rows="3" class="form-control small" placeholder="Enter administrative notes (e.g., 'SEC registration verified', 'Official corporate domain confirmed', 'Compliant with DOLE hiring guidelines')..."><?= htmlspecialchars($hr['company_verification_notes'] ?? '') ?></textarea>
                    </div>

                    <!-- Editable Corporate Details for Record Keeping -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <h6 class="fw-bold text-dark small mb-2"><i class="fa-solid fa-pen-to-square me-1"></i> Verify / Update Corporate Credentials:</h6>
                        
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-1">Company / Entity Name</label>
                            <input type="text" name="company_name" class="form-control form-control-sm" value="<?= htmlspecialchars($hr['company_name'] ?? '') ?>" required>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">SEC / DTI Reg #</label>
                                <input type="text" name="company_reg_number" class="form-control form-control-sm" value="<?= htmlspecialchars($hr['company_reg_number'] ?? '') ?>" placeholder="e.g. CS2022-XXXXX">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1">BIR Tax ID (TIN)</label>
                                <input type="text" name="company_tin" class="form-control form-control-sm" value="<?= htmlspecialchars($hr['company_tin'] ?? '') ?>" placeholder="e.g. 009-XXX-XXX-000">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-1">Official Website</label>
                            <input type="url" name="company_website" class="form-control form-control-sm" value="<?= htmlspecialchars($hr['company_website'] ?? '') ?>" placeholder="https://company.com">
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-1">HR Representative Title / Designation</label>
                            <input type="text" name="hr_designation" class="form-control form-control-sm" value="<?= htmlspecialchars($hr['hr_designation'] ?? 'HR & Talent Acquisition Lead') ?>" placeholder="e.g. HR Director">
                        </div>

                        <div>
                            <label class="form-label small fw-bold mb-1">Corporate Physical Address</label>
                            <textarea name="company_address" rows="2" class="form-control form-control-sm" placeholder="Office location / address..."><?= htmlspecialchars($hr['company_address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 py-2.5">
                        <i class="fa-solid fa-check-double me-1"></i> Save Verification Outcome & Credentials
                    </button>
                </form>
            </div>

            <!-- COMPLIANCE CHECKLIST FOR ADMIN -->
            <div class="card card-modern p-4 border-0 shadow-sm ai-box">
                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-list-check text-primary me-2"></i>Admin Employer Assurance Checklist</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary mb-0">
                    <li><i class="fa-solid fa-check text-success me-1"></i> <strong>Business Authenticity:</strong> Match SEC/DTI registration number against official government records.</li>
                    <li><i class="fa-solid fa-check text-success me-1"></i> <strong>Domain Verification:</strong> Check if HR email domain matches company website (e.g., <code>@techcorp.com</code>).</li>
                    <li><i class="fa-solid fa-check text-success me-1"></i> <strong>Contact Legitimacy:</strong> Confirm authorized phone number has 11 digits and belongs to legitimate HR personnel.</li>
                    <li><i class="fa-solid fa-check text-success me-1"></i> <strong>Salary Fairness:</strong> Review posted jobs to ensure salary complies with regional wage standards.</li>
                </ul>
            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
