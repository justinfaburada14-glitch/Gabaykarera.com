<?php
/**
 * ============================================================================
 * GABAYKARERA AI - SYSTEM ADMINISTRATION PANEL
 * File: admin/dashboard.php
 * Purpose: Platform-wide user management, system metrics, and career framework oversight
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['admin']);
$pdo = Database::getInstance();

// ============================================================================
// 1. ACTION HANDLER: DELETE USER ACCOUNT
// ============================================================================
if (isset($_GET['delete_user'])) {
    $delId = (int)$_GET['delete_user'];
    if ($delId !== (int)$_SESSION['user_id']) {
        $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $del->execute([$delId]);
        $_SESSION['flash_success'] = "User account removed.";
    } else {
        $_SESSION['flash_error'] = "You cannot delete your own admin account.";
    }
    header("Location: /GabayKarera/admin/dashboard.php");
    exit;
}

// ============================================================================
// 2. DATA FETCHING: PLATFORM METRICS, USERS & CAREER FRAMEWORKS
// ============================================================================
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalApplicants = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'applicant'")->fetchColumn();
$totalHr = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'hr'")->fetchColumn();
$totalJobs = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$totalApplications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();

// Document Metrics
$totalDocs = $pdo->query("SELECT COUNT(*) FROM system_documents")->fetchColumn();
$pendingVerifications = $pdo->query("SELECT COUNT(*) FROM document_verifications WHERE verification_status = 'pending'")->fetchColumn();
$recentDocs = $pdo->query("SELECT d.*, u.full_name as uploader FROM system_documents d LEFT JOIN users u ON d.uploaded_by = u.id ORDER BY d.created_at DESC LIMIT 4")->fetchAll();

$usersStmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM applications WHERE applicant_id = u.id) as app_count,
           (SELECT COUNT(*) FROM jobs WHERE employer_id = u.id) as jobs_posted
    FROM users u
    ORDER BY u.created_at DESC
");
$allUsers = $usersStmt->fetchAll();

$cpStmt = $pdo->query("SELECT * FROM career_paths ORDER BY id ASC");
$careerPaths = $cpStmt->fetchAll();

$pageTitle = "System Administration - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: ADMIN DASHBOARD CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    
    <!-- ==================== 1. PAGE HEADER ==================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0"><i class="fa-solid fa-shield-halved text-primary me-2"></i>System Administration & Oversight</h3>
                <span class="badge bg-dark text-white">Administrator</span>
            </div>
            <p class="text-muted mb-0">Platform metrics, user directory, document verification, and AI competency framework oversight.</p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a href="/GabayKarera/admin/documents.php" class="btn btn-outline-primary">
                <i class="fa-solid fa-folder-open me-1"></i> Document Repository
            </a>
            <a href="/GabayKarera/admin/verifications.php" class="btn btn-primary-custom">
                <i class="fa-solid fa-file-circle-check me-1"></i> Verify Documents
                <?php if ($pendingVerifications > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill ms-1"><?= $pendingVerifications ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- ==================== 1B. ADMINISTRATIVE MANAGEMENT OVERVIEW ==================== -->
    <div class="card card-modern p-4 border-0 mb-4 bg-white shadow-sm border-start border-4 border-primary">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
            <div class="d-flex align-items-start gap-3">
                <div class="bg-primary text-white p-3 rounded-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; min-width: 50px;">
                    <i class="fa-solid fa-gears fs-4"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <h4 class="fw-bold mb-0 text-dark">Administrative Management</h4>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small">Core System Feature</span>
                    </div>
                    <p class="text-secondary mb-3" style="font-size: 0.95rem; line-height: 1.6;">
                        The <strong>administrative management feature</strong> allows administrators to manage user accounts, system records, and other relevant information. In <strong>GabayKarera AI</strong>, it supports the organization and maintenance of system data needed for recruitment and career recommendation activities.
                    </p>

                    <!-- Feature Pillars Breakdown -->
                    <div class="row g-2 pt-1">
                        <div class="col-md-4">
                            <div class="p-2.5 px-3 bg-light rounded-3 border d-flex align-items-center gap-2">
                                <i class="fa-solid fa-users-gear text-primary fs-5"></i>
                                <div>
                                    <strong class="d-block text-dark small">User Accounts</strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">Manage applicants, employers, roles & credentials</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2.5 px-3 bg-light rounded-3 border d-flex align-items-center gap-2">
                                <i class="fa-solid fa-database text-success fs-5"></i>
                                <div>
                                    <strong class="d-block text-dark small">System Records</strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">Maintain compliance documents, audits & verifications</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2.5 px-3 bg-light rounded-3 border d-flex align-items-center gap-2">
                                <i class="fa-solid fa-brain text-purple fs-5" style="color: #6f42c1;"></i>
                                <div>
                                    <strong class="d-block text-dark small">Recruitment & AI Data</strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">Career frameworks, job records & match matrices</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== 1C. ADMIN SYSTEM GOVERNANCE GUIDELINES ==================== -->
    <div class="card card-modern p-4 border-0 mb-4 bg-white shadow-sm border-start border-4 border-dark">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-dark-subtle text-dark p-2 rounded-3">
                    <i class="fa-solid fa-lightbulb fs-5"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">System Administrator Governance & Verification Checklist</h5>
                    <small class="text-muted">4-pillar guide to auditing employers, verifying talent, and maintaining platform integrity</small>
                </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#adminGuideCollapse" aria-expanded="true">
                <i class="fa-solid fa-chevron-down"></i> Toggle Guide
            </button>
        </div>

        <div class="collapse show" id="adminGuideCollapse">
            <div class="row g-3 pt-2">
                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">1</span>
                                <span class="fw-bold small text-dark">Audit HR Companies</span>
                            </div>
                            <p class="small text-muted mb-3">Inspect SEC/DTI permits, BIR TIN, physical address, and HR representative credentials via <strong>Company Dossier</strong>.</p>
                        </div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-center py-1.5"><i class="fa-solid fa-building-circle-check me-1"></i> Anti-Scam Protection</span>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">2</span>
                                <span class="fw-bold small text-dark">Candidate Dossiers</span>
                            </div>
                            <p class="small text-muted mb-3">Audit applicant qualifications, resumes, academic background, and verify submitted certificates via <strong>Career Dossier</strong>.</p>
                        </div>
                        <a href="/GabayKarera/admin/verifications.php" class="btn btn-sm btn-outline-dark w-100">
                            <i class="fa-solid fa-file-circle-check me-1"></i> Document Inspector
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">3</span>
                                <span class="fw-bold small text-dark">Publish Policies</span>
                            </div>
                            <p class="small text-muted mb-3">Upload and distribute recruitment advisories, DOLE fair hiring standards, and competency guides in the <strong>Document Repository</strong>.</p>
                        </div>
                        <a href="/GabayKarera/admin/documents.php" class="btn btn-sm btn-outline-dark w-100">
                            <i class="fa-solid fa-folder-open me-1"></i> Document Repository
                        </a>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">4</span>
                                <span class="fw-bold small text-dark">Security & Privacy</span>
                            </div>
                            <p class="small text-muted mb-3">Enforce Republic Act 10173 (Data Privacy Act), monitor system user metrics, and deactivate unauthorized or fraudulent accounts.</p>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle text-center py-1.5"><i class="fa-solid fa-lock me-1"></i> RA 10173 Compliant</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-users"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalUsers ?></h4>
                    <small class="text-muted">Total Accounts</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box stat-icon-green">
                <div class="stat-icon stat-icon-green"><i class="fa-solid fa-user-graduate"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalApplicants ?></h4>
                    <small class="text-muted">Job Seekers</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-purple"><i class="fa-solid fa-folder-tree"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $totalDocs ?></h4>
                    <small class="text-muted">System Documents</small>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-box">
                <div class="stat-icon stat-icon-amber"><i class="fa-solid fa-clipboard-check"></i></div>
                <div>
                    <h4 class="fw-bold mb-0"><?= $pendingVerifications ?></h4>
                    <small class="text-muted">Pending Verifications</small>
                </div>
            </div>
        </div>
    </div>

    <!-- User Accounts Table -->
    <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
        <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-address-book text-primary me-2"></i>User Management Directory</h5>

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Organization / Context</th>
                        <th>Activity</th>
                        <th>Created At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $roleBadges = [
                                        'applicant' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'hr' => 'bg-success-subtle text-success border border-success-subtle',
                                        'admin' => 'bg-dark text-white'
                                    ];
                                    $rbClass = $roleBadges[$u['role']] ?? 'bg-secondary';
                                ?>
                                <span class="badge <?= $rbClass ?> text-uppercase" style="font-size: 0.7rem;"><?= $u['role'] ?></span>
                            </td>
                            <td>
                                <span class="small text-secondary fw-semibold"><?= !empty($u['company_name']) ? htmlspecialchars($u['company_name']) : 'Individual Candidate' ?></span>
                                <?php if ($u['role'] === 'hr'): ?>
                                    <?php if (($u['company_verification_status'] ?? 'pending') === 'verified'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.65rem;" title="Verified Employer"><i class="fa-solid fa-shield-check"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle ms-1" style="font-size: 0.65rem;" title="Pending Admin Audit"><i class="fa-solid fa-clock"></i> Pending Audit</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="small text-muted">
                                    <?= $u['role'] === 'hr' ? $u['jobs_posted'] . ' Jobs Posted' : $u['app_count'] . ' Applications' ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td class="text-end">
                                <?php if ($u['role'] === 'applicant'): ?>
                                    <a href="/GabayKarera/admin/candidate-detail.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="View Full Career & Background Dossier">
                                        <i class="fa-solid fa-id-card me-1"></i> Career Dossier
                                    </a>
                                <?php elseif ($u['role'] === 'hr'): ?>
                                    <a href="/GabayKarera/admin/company-detail.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="Inspect & Verify Company & HR Credentials">
                                        <i class="fa-solid fa-building-circle-check me-1"></i> Company Dossier
                                    </a>
                                <?php endif; ?>
                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <a href="/GabayKarera/admin/dashboard.php?delete_user=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this user?');" title="Delete User">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Current Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System Documents Overview Card -->
    <div class="card card-modern p-4 border-0 mb-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-folder-open text-primary me-2"></i>Published System Documents & Regulations</h5>
            <a href="/GabayKarera/admin/documents.php" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-gear me-1"></i> Manage Repository</a>
        </div>

        <?php if (empty($recentDocs)): ?>
            <p class="text-muted small mb-0">No system documents have been published yet.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($recentDocs as $rd): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1" style="font-size: 0.68rem;"><?= htmlspecialchars($rd['category']) ?></span>
                                <h6 class="fw-bold text-dark mb-1 text-truncate"><?= htmlspecialchars($rd['title']) ?></h6>
                                <p class="text-secondary small mb-2 text-truncate"><?= htmlspecialchars($rd['description'] ?: 'Official system document.') ?></p>
                            </div>
                            <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                                <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.65rem;"><?= $rd['file_type'] ?></span>
                                <a href="/GabayKarera/uploads/documents/<?= htmlspecialchars($rd['file_name']) ?>" target="_blank" class="btn btn-sm btn-link p-0 text-decoration-none small">
                                    <i class="fa-solid fa-download me-1"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Career Pathways Reference Table -->
    <div class="card card-modern p-4 border-0 shadow-sm">
        <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-route text-primary me-2"></i>AI Career Frameworks & Skill Taxonomies</h5>
        
        <div class="row g-3">
            <?php foreach ($careerPaths as $cp): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="badge bg-white text-secondary border mb-1"><?= htmlspecialchars($cp['category']) ?></span>
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($cp['title']) ?></h6>
                            <p class="text-secondary small mb-2"><?= htmlspecialchars($cp['description']) ?></p>
                            <small class="fw-semibold text-primary d-block mb-1">Core Competency Taxonomy:</small>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <?php foreach (explode(',', $cp['core_skills']) as $csk): ?>
                                    <span class="skill-tag py-0 px-2" style="font-size: 0.7rem;"><?= htmlspecialchars(trim($csk)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="pt-2 border-top small text-muted">
                            <div><strong>Benchmark:</strong> <?= htmlspecialchars($cp['average_salary']) ?></div>
                            <div><strong>Growth:</strong> <?= htmlspecialchars($cp['growth_rate']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
