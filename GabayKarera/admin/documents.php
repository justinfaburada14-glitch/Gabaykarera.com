<?php
/**
 * ============================================================================
 * GABAYKARERA AI - ADMIN SYSTEM DOCUMENTS REPOSITORY
 * File: admin/documents.php
 * Purpose: Upload, manage, publish, and distribute system documents & policies
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['admin']);
$pdo = Database::getInstance();
$adminId = $_SESSION['user_id'];

// Create document upload folder if not existing
$docUploadDir = __DIR__ . '/../uploads/documents';
if (!is_dir($docUploadDir)) {
    @mkdir($docUploadDir, 0777, true);
}

// ============================================================================
// 1. ACTION HANDLER: UPLOAD NEW SYSTEM DOCUMENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_upload_document'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Policy & Compliance');
    $targetAudience = $_POST['target_audience'] ?? 'all';
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $_SESSION['flash_error'] = "Document title is required.";
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = "Please select a valid document file to upload.";
    } else {
        $fileTmp = $_FILES['document_file']['tmp_name'];
        $origName = $_FILES['document_file']['name'];
        $fileSize = $_FILES['document_file']['size'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        
        $allowedExts = ['pdf', 'docx', 'doc', 'txt', 'png', 'jpg', 'jpeg'];

        if (!in_array($ext, $allowedExts)) {
            $_SESSION['flash_error'] = "Invalid file type. Allowed formats: .pdf, .docx, .doc, .txt, .png, .jpg";
        } else {
            // Format readable file size
            if ($fileSize >= 1048576) {
                $formattedSize = number_format($fileSize / 1048576, 2) . ' MB';
            } else {
                $formattedSize = number_format($fileSize / 1024, 1) . ' KB';
            }

            $newFilename = 'doc_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $targetPath = $docUploadDir . '/' . $newFilename;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                $ins = $pdo->prepare("INSERT INTO system_documents (title, category, target_audience, file_name, file_size, file_type, description, uploaded_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $ins->execute([
                    $title,
                    $category,
                    $targetAudience,
                    $newFilename,
                    $formattedSize,
                    strtoupper($ext) . ' File',
                    $description,
                    $adminId
                ]);

                $_SESSION['flash_success'] = "Document '{$title}' uploaded and published successfully!";
            } else {
                $_SESSION['flash_error'] = "Failed to save uploaded document file on server.";
            }
        }
    }

    header("Location: /GabayKarera/admin/documents.php");
    exit;
}

// ============================================================================
// 2. ACTION HANDLER: DELETE / ARCHIVE DOCUMENT
// ============================================================================
if (isset($_GET['delete_doc'])) {
    $docId = (int)$_GET['delete_doc'];
    $stmt = $pdo->prepare("SELECT file_name FROM system_documents WHERE id = ?");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();

    if ($doc) {
        // Delete file from disk if exists
        $filePath = $docUploadDir . '/' . $doc['file_name'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        $del = $pdo->prepare("DELETE FROM system_documents WHERE id = ?");
        $del->execute([$docId]);
        $_SESSION['flash_success'] = "Document removed from system.";
    }
    header("Location: /GabayKarera/admin/documents.php");
    exit;
}

// ============================================================================
// 3. DATA FETCHING: SYSTEM DOCUMENTS LIST
// ============================================================================
$filterAudience = $_GET['audience'] ?? 'all_filter';
$searchQuery = trim($_GET['search'] ?? '');

$sql = "SELECT d.*, u.full_name as uploader_name FROM system_documents d LEFT JOIN users u ON d.uploaded_by = u.id WHERE 1=1";
$params = [];

if ($filterAudience !== 'all_filter' && in_array($filterAudience, ['all', 'applicant', 'hr'])) {
    $sql .= " AND d.target_audience = ?";
    $params[] = $filterAudience;
}

if (!empty($searchQuery)) {
    $sql .= " AND (d.title LIKE ? OR d.category LIKE ? OR d.description LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$sql .= " ORDER BY d.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();

$pageTitle = "Document Repository - System Administration";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: ADMIN DOCUMENT REPOSITORY CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">

    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center bg-white p-4 rounded-4 border mb-4 shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0"><i class="fa-solid fa-folder-open text-primary me-2"></i>System Document Repository & Policy Center</h3>
                <span class="badge bg-primary text-white">Admin Module</span>
            </div>
            <p class="text-muted mb-0">Upload, publish, manage, and verify official system documents, qualification matrices, and compliance policies.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                <i class="fa-solid fa-cloud-arrow-up me-2"></i> Upload New Document
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success card-modern p-3 mb-4 d-flex align-items-center">
            <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
            <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger card-modern p-3 mb-4 d-flex align-items-center">
            <i class="fa-solid fa-circle-exclamation fs-4 me-3 text-danger"></i>
            <div><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Filter & Search Bar -->
    <div class="card card-modern p-3 border-0 shadow-sm mb-4">
        <form method="GET" action="/GabayKarera/admin/documents.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search document title, category, or description..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="audience" class="form-select" onchange="this.form.submit()">
                    <option value="all_filter" <?= $filterAudience === 'all_filter' ? 'selected' : '' ?>>All Audiences (Public, Applicants & HR)</option>
                    <option value="all" <?= $filterAudience === 'all' ? 'selected' : '' ?>>Target Audience: Everyone (Public)</option>
                    <option value="applicant" <?= $filterAudience === 'applicant' ? 'selected' : '' ?>>Target Audience: Job Seekers Only</option>
                    <option value="hr" <?= $filterAudience === 'hr' ? 'selected' : '' ?>>Target Audience: HR Employers Only</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <a href="/GabayKarera/admin/documents.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>

    <!-- Documents Grid / List -->
    <?php if (empty($documents)): ?>
        <div class="text-center py-5 card card-modern border-0 shadow-sm">
            <i class="fa-solid fa-folder-open text-muted opacity-25 display-1 mb-3"></i>
            <h5 class="fw-bold text-dark">No Documents Found</h5>
            <p class="text-muted small">No system documents have been uploaded matching your search query.</p>
            <div>
                <button class="btn btn-primary-custom btn-sm" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                    <i class="fa-solid fa-plus me-1"></i> Upload First Document
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($documents as $doc): ?>
                <div class="col-lg-6">
                    <div class="card card-modern p-4 border-0 shadow-sm h-100 d-flex flex-column justify-content-between position-relative">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-tag me-1"></i> <?= htmlspecialchars($doc['category']) ?>
                                </span>

                                <?php
                                    $audBadges = [
                                        'all' => 'bg-info-subtle text-info border border-info-subtle',
                                        'applicant' => 'bg-success-subtle text-success border border-success-subtle',
                                        'hr' => 'bg-purple-subtle text-purple border border-purple-subtle'
                                    ];
                                    $audLabels = [
                                        'all' => 'Everyone',
                                        'applicant' => 'Job Seekers',
                                        'hr' => 'HR Employers'
                                    ];
                                ?>
                                <span class="badge <?= $audBadges[$doc['target_audience']] ?? 'bg-secondary' ?> text-uppercase" style="font-size: 0.68rem;">
                                    <i class="fa-solid fa-users me-1"></i> Audience: <?= $audLabels[$doc['target_audience']] ?? $doc['target_audience'] ?>
                                </span>
                            </div>

                            <h5 class="fw-bold text-dark mb-2">
                                <i class="fa-solid fa-file-pdf text-danger me-2"></i><?= htmlspecialchars($doc['title']) ?>
                            </h5>
                            
                            <p class="text-muted small mb-3">
                                <?= !empty($doc['description']) ? nl2br(htmlspecialchars($doc['description'])) : 'No additional description provided.' ?>
                            </p>
                        </div>

                        <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                            <div class="small text-muted">
                                <div><i class="fa-solid fa-paperclip me-1"></i> <strong><?= htmlspecialchars($doc['file_name']) ?></strong> (<?= $doc['file_size'] ?: 'N/A' ?>)</div>
                                <div><i class="fa-regular fa-clock me-1"></i> Uploaded <?= date('M d, Y', strtotime($doc['created_at'])) ?> by <?= htmlspecialchars($doc['uploader_name'] ?? 'Admin') ?></div>
                            </div>
                            <div class="d-flex gap-1">
                                <?php if (file_exists($docUploadDir . '/' . $doc['file_name'])): ?>
                                    <a href="/GabayKarera/uploads/documents/<?= htmlspecialchars($doc['file_name']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Download / View Document">
                                        <i class="fa-solid fa-download me-1"></i> View / Download
                                    </a>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark border">File missing</span>
                                <?php endif; ?>
                                
                                <a href="/GabayKarera/admin/documents.php?delete_doc=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this document from the system?');" title="Delete Document">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- ========================================================================== -->
<!-- MODAL: UPLOAD NEW SYSTEM DOCUMENT -->
<!-- ========================================================================== -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg card-modern">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload System Document & Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/GabayKarera/admin/documents.php" enctype="multipart/form-data">
                <input type="hidden" name="action_upload_document" value="1">
                <div class="modal-body p-4">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Document Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. 2026 National Competency Standards & Skill Matrix Manual" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Document Category</label>
                            <select name="category" class="form-select">
                                <option value="Policy & Compliance">Policy & Compliance</option>
                                <option value="Competency Matrix">Competency Matrix</option>
                                <option value="Qualification Standard">Qualification Standard</option>
                                <option value="HR Guidelines">HR Guidelines</option>
                                <option value="User Manual & Reference">User Manual & Reference</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Target Audience Visibility</label>
                            <select name="target_audience" class="form-select">
                                <option value="all">Everyone (Public, Applicants & HR)</option>
                                <option value="applicant">Job Seekers / Applicants Only</option>
                                <option value="hr">HR Employers Only</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Document File Upload (.pdf, .docx, .doc, .txt, .png) <span class="text-danger">*</span></label>
                            <input type="file" name="document_file" class="form-control" accept=".pdf,.docx,.doc,.txt,.png,.jpg,.jpeg" required>
                            <small class="text-muted">Supported formats: PDF documents, Word files, text manuals, and image charts.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Document Overview / Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provide a brief summary of the document's contents, regulatory references, or purpose..."></textarea>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-custom px-4"><i class="fa-solid fa-check-circle me-2"></i> Publish Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
