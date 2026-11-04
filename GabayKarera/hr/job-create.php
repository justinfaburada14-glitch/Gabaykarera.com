<?php
/**
 * ============================================================================
 * GABAYKARERA AI - HR JOB CREATION & CRITERIA BUILDER
 * File: hr/job-create.php
 * Purpose: Form to publish new jobs with required/preferred skills for AI matching
 * ============================================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAuth(['hr']);
$pdo = Database::getInstance();
$hrId = $_SESSION['user_id'];

// Fetch HR company name
$uStmt = $pdo->prepare("SELECT company_name FROM users WHERE id = ?");
$uStmt->execute([$hrId]);
$defaultCompany = $uStmt->fetchColumn() ?: 'TechCorp Solutions';

$errors = [];

// ============================================================================
// 1. ACTION HANDLER: JOB CREATION SUBMISSION
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

    if (empty($title)) $errors[] = "Job Title is required.";
    if (empty($companyName)) $errors[] = "Company Name is required.";
    if (empty($location)) $errors[] = "Location is required.";
    if (empty($description)) $errors[] = "Job Description is required.";
    if (empty($requiredSkills)) $errors[] = "At least one required skill is needed for AI matching.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO jobs (employer_id, title, company_name, department, location, job_type, experience_required, education_required, salary_min, salary_max, description, required_skills, preferred_skills, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open')");
        $stmt->execute([$hrId, $title, $companyName, $department, $location, $jobType, $experienceRequired, $educationRequired, $salaryMin, $salaryMax, $description, $requiredSkills, $preferredSkills]);

        $_SESSION['flash_success'] = "Job posting '{$title}' created successfully! AI competency matching is now active.";
        header("Location: /GabayKarera/hr/jobs.php");
        exit;
    }
}

$pageTitle = "Post New Job Opening - GabayKarera AI";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: CREATE JOB OPENING CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <div class="card card-modern p-4 p-md-5 border-0 shadow-lg">
                <div class="d-flex align-items-center gap-3 mb-4 border-bottom pb-3">
                    <div class="stat-icon stat-icon-blue"><i class="fa-solid fa-file-circle-plus"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0">Create New Job Opening</h4>
                        <small class="text-muted">Specify required competencies for accurate AI applicant scoring</small>
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

                <form method="POST" action="/GabayKarera/hr/job-create.php">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Job Position Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Senior Backend Engineer" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Department</label>
                            <input type="text" name="department" class="form-control" placeholder="e.g. Engineering, Marketing" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Company / Employer Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($_POST['company_name'] ?? $defaultCompany) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Work Location <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. BGC, Taguig / Remote / Hybrid" required value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Job Employment Type</label>
                            <select name="job_type" class="form-select">
                                <option value="Full-Time">Full-Time</option>
                                <option value="Part-Time">Part-Time</option>
                                <option value="Contract">Contract</option>
                                <option value="Internship">Internship</option>
                                <option value="Remote">Remote</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Required Experience (Years)</label>
                            <input type="number" step="0.5" name="experience_required" class="form-control" placeholder="e.g. 2.0" value="<?= htmlspecialchars($_POST['experience_required'] ?? '1.0') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Minimum Education</label>
                            <input type="text" name="education_required" class="form-control" value="Bachelor's Degree" placeholder="e.g. Bachelor's Degree">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Minimum Salary (₱/month)</label>
                            <input type="number" step="1000" name="salary_min" class="form-control" placeholder="e.g. 40000" value="<?= htmlspecialchars($_POST['salary_min'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Maximum Salary (₱/month)</label>
                            <input type="number" step="1000" name="salary_max" class="form-control" placeholder="e.g. 65000" value="<?= htmlspecialchars($_POST['salary_max'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">Detailed Job Description & Responsibilities <span class="text-danger">*</span></label>
                            <textarea name="description" rows="5" class="form-control" placeholder="Describe the duties, role expectations, and company culture..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Competency & Skill Weights -->
                        <div class="col-12">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>AI Matching Competency Requirements</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Required Core Skills (Comma-separated) <span class="text-danger">*</span></label>
                                    <input type="text" name="required_skills" class="form-control" placeholder="e.g. PHP, MySQL, JavaScript, Git, REST APIs" required value="<?= htmlspecialchars($_POST['required_skills'] ?? '') ?>">
                                    <small class="text-muted">The AI algorithm matches applicant skill tags directly against this list.</small>
                                </div>

                                <div>
                                    <label class="form-label small fw-bold">Preferred / Nice-to-Have Skills (Optional)</label>
                                    <input type="text" name="preferred_skills" class="form-control" placeholder="e.g. Docker, AWS, React, Agile" value="<?= htmlspecialchars($_POST['preferred_skills'] ?? '') ?>">
                                    <small class="text-muted">Candidates with these skills receive bonus competency rating points.</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <a href="/GabayKarera/hr/jobs.php" class="btn btn-light border me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary-custom px-4">
                                <i class="fa-solid fa-check me-1"></i> Publish Job Opening
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
