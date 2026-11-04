<?php
/**
 * ============================================================================
 * GABAYKARERA AI - DUAL-ROLE REGISTRATION
 * File: register.php
 * Purpose: Registration for Job Seekers (with skills) and HR Employers (with company)
 * ============================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = Database::getInstance();

// ============================================================================
// 1. SESSION GUARD (Redirect if already logged in)
// ============================================================================
if (Auth::isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'applicant';
    if ($role === 'hr') header("Location: /GabayKarera/hr/dashboard.php");
    elseif ($role === 'admin') header("Location: /GabayKarera/admin/dashboard.php");
    else header("Location: /GabayKarera/applicant/dashboard.php");
    exit;
}

$errors = [];
$selectedRole = $_GET['role'] ?? 'applicant';
if (!in_array($selectedRole, ['applicant', 'hr'])) {
    $selectedRole = 'applicant';
}

// ============================================================================
// 2. FORM SUBMISSION & USER CREATION HANDLER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'applicant';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    
    // Applicant specific fields
    $educationLevel = trim($_POST['education_level'] ?? '');
    $fieldOfStudy = trim($_POST['field_of_study'] ?? '');
    $experienceYears = (float)($_POST['experience_years'] ?? 0);
    $skillsInput = trim($_POST['skills_input'] ?? '');
    $interests = trim($_POST['interests'] ?? '');

    // Validation
    if (empty($fullName)) $errors[] = "Full Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid Email address is required.";
    if (strlen($password) < 6 || strlen($password) > 10) $errors[] = "Password must be between 6 and 10 characters long.";
    if (!empty($phone) && !preg_match('/^[0-9]{11}$/', $phone)) $errors[] = "Cellphone number must be exactly 11 digits (e.g. 09171234567).";
    if ($role === 'hr' && empty($companyName)) $errors[] = "Company Name is required for HR registration.";

    // Check if email already exists
    if (empty($errors)) {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            $errors[] = "An account with this email already exists.";
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, company_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $email, $hashedPassword, $role, $phone, $companyName]);
            $userId = $pdo->lastInsertId();

            if ($role === 'applicant') {
                // Create applicant profile
                $pStmt = $pdo->prepare("INSERT INTO applicant_profiles (user_id, education_level, field_of_study, experience_years, interests, bio) VALUES (?, ?, ?, ?, ?, ?)");
                $pStmt->execute([$userId, $educationLevel, $fieldOfStudy, $experienceYears, $interests, "Profile of " . $fullName]);

                // Insert skills if provided
                if (!empty($skillsInput)) {
                    $skills = array_filter(array_map('trim', explode(',', $skillsInput)));
                    $sStmt = $pdo->prepare("INSERT INTO applicant_skills (user_id, skill_name, proficiency, years_experience) VALUES (?, ?, 'Intermediate', 1)");
                    foreach ($skills as $s) {
                        $sStmt->execute([$userId, $s]);
                    }
                }
            }

            $pdo->commit();

            // Auto-login
            Auth::login([
                'id' => $userId,
                'full_name' => $fullName,
                'email' => $email,
                'role' => $role,
                'company_name' => $companyName
            ]);

            $_SESSION['flash_success'] = "Welcome to GabayKarera AI! Your account has been registered successfully.";
            if ($role === 'hr') {
                header("Location: /GabayKarera/hr/dashboard.php");
            } else {
                header("Location: /GabayKarera/applicant/dashboard.php");
            }
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}

$pageTitle = "Register - GabayKarera AI";
require_once __DIR__ . '/includes/header.php';
?>

<!-- ========================================================================== -->
<!-- SECTION: REGISTRATION CONTAINER -->
<!-- ========================================================================== -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card card-modern p-4 p-md-5 border-0 shadow-lg">
                
                <!-- Card Header -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary p-3 rounded-circle mb-2">
                        <i class="fa-solid fa-user-plus fs-3"></i>
                    </div>
                    <h3 class="fw-bold mb-1">Create Your Account</h3>
                    <p class="text-muted small">Join GabayKarera AI for smart career guidance and applicant-employer matching</p>
                </div>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger card-modern p-3 mb-4">
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Role Tab Selector -->
                <div class="row g-2 mb-4 text-center">
                    <div class="col-6">
                        <button type="button" class="btn w-100 py-3 rounded-3 border role-select-btn <?= $selectedRole === 'applicant' ? 'btn-primary shadow-sm fw-bold' : 'btn-light' ?>" id="btnRoleApplicant" onclick="setRole('applicant')">
                            <i class="fa-solid fa-user-graduate d-block fs-4 mb-1"></i>
                            <span>Job Seeker / Applicant</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn w-100 py-3 rounded-3 border role-select-btn <?= $selectedRole === 'hr' ? 'btn-primary shadow-sm fw-bold' : 'btn-light' ?>" id="btnRoleHr" onclick="setRole('hr')">
                            <i class="fa-solid fa-building-user d-block fs-4 mb-1"></i>
                            <span>HR / Employer</span>
                        </button>
                    </div>
                </div>

                <!-- Registration Form -->
                <form method="POST" action="/GabayKarera/register.php">
                    <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($selectedRole) ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control py-2" placeholder="e.g. Juan Dela Cruz" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control py-2" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="regPassword" class="form-control py-2" placeholder="6 to 10 characters" maxlength="10" required>
                                <button class="btn btn-outline-secondary border bg-light text-muted" type="button" onclick="togglePasswordVisibility('regPassword', 'regEyeIcon')" title="Toggle password visibility">
                                    <i class="fa-solid fa-eye" id="regEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control py-2" placeholder="e.g. 09171234567" maxlength="11" pattern="[0-9]{11}" title="Please enter an 11-digit cellphone number (e.g. 09171234567)" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>

                        <!-- HR Specific Section -->
                        <div class="col-12" id="hrFields" style="<?= $selectedRole === 'hr' ? 'display: block;' : 'display: none;' ?>">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-building me-2"></i> Company Information</h6>
                                <div>
                                    <label class="form-label small fw-bold">Company / Organization Name <span class="text-danger">*</span></label>
                                    <input type="text" name="company_name" class="form-control py-2" placeholder="e.g. TechCorp Solutions Inc." value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Applicant Specific Section -->
                        <div class="col-12" id="applicantFields" style="<?= $selectedRole === 'applicant' ? 'display: block;' : 'display: none;' ?>">
                            <div class="p-3 bg-light rounded-3 border">
                                <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-wand-magic-sparkles me-2"></i> Initial Competency Profile (Optional)</h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Highest Education Level</label>
                                        <select name="education_level" class="form-select py-2">
                                            <option value="Bachelor's Degree">Bachelor's Degree</option>
                                            <option value="Associate Degree / Diploma">Associate Degree / Diploma</option>
                                            <option value="Master's Degree">Master's Degree</option>
                                            <option value="Doctorate / PhD">Doctorate / PhD</option>
                                            <option value="Senior High School">Senior High School</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Field of Study / Course</label>
                                        <input type="text" name="field_of_study" class="form-control py-2" placeholder="e.g. Computer Science, IT, Business" value="<?= htmlspecialchars($_POST['field_of_study'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Years of Work Experience</label>
                                        <input type="number" step="0.5" name="experience_years" class="form-control py-2" placeholder="e.g. 1.5" value="<?= htmlspecialchars($_POST['experience_years'] ?? '0') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Career Interests</label>
                                        <input type="text" name="interests" class="form-control py-2" placeholder="e.g. Web Dev, AI, Data Analysis" value="<?= htmlspecialchars($_POST['interests'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Your Core Skills (Comma-separated)</label>
                                        <input type="text" name="skills_input" class="form-control py-2" placeholder="e.g. PHP, JavaScript, MySQL, Python, UI Design" value="<?= htmlspecialchars($_POST['skills_input'] ?? '') ?>">
                                        <small class="text-muted">These will be used for your initial AI Career & Job match analysis.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary-custom w-100 py-3 fs-6">
                                <i class="fa-solid fa-check-circle me-2"></i> Complete Registration
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Footer Switcher -->
                <div class="text-center mt-4 text-muted small">
                    Already have an account? <a href="/GabayKarera/login.php" class="text-primary fw-bold text-decoration-none">Log In here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================== -->
<!-- SECTION: CLIENT SCRIPT (ROLE TOGGLE) -->
<!-- ========================================================================== -->
<script>
function setRole(role) {
    document.getElementById('roleInput').value = role;
    const btnApp = document.getElementById('btnRoleApplicant');
    const btnHr = document.getElementById('btnRoleHr');
    const hrFields = document.getElementById('hrFields');
    const appFields = document.getElementById('applicantFields');

    if (role === 'applicant') {
        btnApp.classList.remove('btn-light');
        btnApp.classList.add('btn-primary', 'shadow-sm', 'fw-bold');
        btnHr.classList.remove('btn-primary', 'shadow-sm', 'fw-bold');
        btnHr.classList.add('btn-light');
        hrFields.style.display = 'none';
        appFields.style.display = 'block';
    } else {
        btnHr.classList.remove('btn-light');
        btnHr.classList.add('btn-primary', 'shadow-sm', 'fw-bold');
        btnApp.classList.remove('btn-primary', 'shadow-sm', 'fw-bold');
        btnApp.classList.add('btn-light');
        hrFields.style.display = 'block';
        appFields.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

