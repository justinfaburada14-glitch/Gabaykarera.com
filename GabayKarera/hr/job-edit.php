<?php
/**
 * ============================================================================
 * GABAYKARERA AI - HR JOB EDITING & REQUIREMENTS UPDATER
 * File: hr/job-edit.php
 * Purpose: Form to modify job criteria, salary range, status, and skill weights
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['hr']);
$pdo = Database::getInstance();
$hrId = $_SESSION['user_id'];
$jobId = (int)($_GET['id'] ?? 0);

// ============================================================================
// 1. DATA FETCHING: EXISTING JOB POSTING
// ============================================================================
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND employer_id = ?");
$stmt->execute([$jobId, $hrId]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['flash_error'] = "Job not found.";
    header("Location: /GabayKarera/hr/jobs.php");
    exit;
}

$errors = [];

// ============================================================================
// 2. ACTION HANDLER: SAVE JOB EDITS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $jobType = $_POST['job_type'] ?? 'Full-Time';
    $experienceRequired = (float)($_POST['experience_required'] ?? 0.0);
    $educationRequired = trim($_POST['education_required'] ?? 'Bachelor\'s Degree');
    $salaryMin = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
    $salaryMax = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
    $description = trim($_POST['description'] ?? '');
    $requiredSkills = trim($_POST['required_skills'] ?? '');
    $preferredSkills = trim($_POST['preferred_skills'] ?? '');
    $status = $_POST['status'] ?? 'Open';

    if (empty($title)) $errors[] = "Job Title is required.";
    if (empty($companyName)) $errors[] = "Company Name is required.";
    if (empty($location)) $errors[] = "Location is required.";
    if (empty($description)) $errors[] = "Job Description is required.";
    if (empty($requiredSkills)) $errors[] = "At least one required skill is needed.";

    if (empty($errors)) {
        $uStmt = $pdo->prepare("UPDATE jobs SET title=?, company_name=?, department=?, location=?, job_type=?, experience_required=?, education_required=?, salary_min=?, salary_max=?, description=?, required_skills=?, preferred_skills=?, status=? WHERE id=? AND employer_id=?");
        $uStmt->execute([$title, $companyName, $department, $location, $jobType, $experienceRequired, $educationRequired, $salaryMin, $salaryMax, $description, $requiredSkills, $preferredSkills, $status, $jobId, $hrId]);

        $_SESSION['flash_success'] = "Job posting updated successfully.";
        header("Location: /GabayKarera/hr/jobs.php");
        exit;
    }
}

$pageTitle = "Edit Job - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: EDIT JOB POSTING CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    <div class="row justify-content-center">

        <div class="col-lg-9">
            
            <div class="card card-modern p-4 p-md-5 border-0 shadow-lg">
                <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                    <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-pen-to-square"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0">Edit Job Opening</h4>
                        <small class="text-muted">Modify job details and competencies</small>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger card-modern p-3 mb-4">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/GabayKarera/hr/job-edit.php?id=<?= $job['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Job Position Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($job['title']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Open" <?= $job['status'] === 'Open' ? 'selected' : '' ?>>Open (Active)</option>
                                <option value="Closed" <?= $job['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($job['company_name']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Department</label>
                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($job['department']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Work Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($job['location']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Job Type</label>
                            <select name="job_type" class="form-select">
                                <?php foreach (['Full-Time', 'Part-Time', 'Contract', 'Internship', 'Remote'] as $jt): ?>
                                    <option value="<?= $jt ?>" <?= $job['job_type'] === $jt ? 'selected' : '' ?>><?= $jt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Experience Required (Years)</label>
                            <input type="number" step="0.5" name="experience_required" class="form-control" value="<?= htmlspecialchars($job['experience_required']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Minimum Education</label>
                            <input type="text" name="education_required" class="form-control" value="<?= htmlspecialchars($job['education_required']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Min Salary (₱)</label>
                            <input type="number" step="1000" name="salary_min" class="form-control" value="<?= htmlspecialchars($job['salary_min']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Max Salary (₱)</label>
                            <input type="number" step="1000" name="salary_max" class="form-control" value="<?= htmlspecialchars($job['salary_max']) ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Job Description</label>
                            <textarea name="description" rows="5" class="form-control" required><?= htmlspecialchars($job['description']) ?></textarea>
                        </div>

                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <label class="form-label small fw-bold">Required Competencies (Comma-separated)</label>
                                <input type="text" name="required_skills" class="form-control mb-3" value="<?= htmlspecialchars($job['required_skills']) ?>" required>

                                <label class="form-label small fw-bold">Preferred / Nice-to-Have Skills</label>
                                <input type="text" name="preferred_skills" class="form-control" value="<?= htmlspecialchars($job['preferred_skills']) ?>">
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <a href="/GabayKarera/hr/jobs.php" class="btn btn-light border me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary-custom px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
