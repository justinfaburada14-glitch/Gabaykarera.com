<?php
/**
 * ============================================================================
 * LOGIN: CONTROLLER & AUTHENTICATION LOGIC
 * File: login.php
 * Purpose: Secure user login, session initialization, and 30-day "Remember Me"
 * ============================================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = Database::getInstance();

/* ============================================================================
 * LOGIN: 1. SESSION GUARD (Redirect already logged-in users)
 * ============================================================================ */
if (Auth::isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'applicant';
    if ($role === 'hr') header("Location: /GabayKarera/hr/dashboard.php");
    elseif ($role === 'admin') header("Location: /GabayKarera/admin/dashboard.php");
    else header("Location: /GabayKarera/applicant/dashboard.php");
    exit;
}

$rememberedEmail = $_COOKIE['gabaykarera_remember_email'] ?? '';
$error = '';

/* ============================================================================
 * LOGIN: 2. CREDENTIAL VERIFICATION & FORM SUBMISSION HANDLER
 * ============================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify password hash
        if ($user && password_verify($password, $user['password'])) {
            // Handle 30-day "Remember Me" persistent cookie
            if ($remember) {
                setcookie('gabaykarera_remember_email', $email, time() + (86400 * 30), "/");
            } else {
                setcookie('gabaykarera_remember_email', '', time() - 3600, "/");
            }

            // Start authenticated session
            Auth::login($user);
            $_SESSION['flash_success'] = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
            
            // Redirect based on user role
            if ($user['role'] === 'hr') {
                header("Location: /GabayKarera/hr/dashboard.php");
            } elseif ($user['role'] === 'admin') {
                header("Location: /GabayKarera/admin/dashboard.php");
            } else {
                header("Location: /GabayKarera/applicant/dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid email address or password.";
        }
    }
}

$pageTitle = "Login & User Guidelines - GabayKarera AI";
require_once __DIR__ . '/includes/header.php';
?>

<!-- ========================================================================== -->
<!-- LOGIN: USER INTERFACE & LOGIN FORM CONTAINER                               -->
<!-- ========================================================================== -->
<div class="container py-5">
    <div class="row g-4 align-items-stretch">
        
        <!-- ==================== LEFT COLUMN: LOGIN CARD ==================== -->
        <div class="col-lg-5 col-md-12">
            <div class="card card-modern p-4 p-md-5 border-0 shadow-lg h-100 d-flex flex-column justify-content-between">
                <div>
                    <!-- LOGIN: CARD HEADER & LOGO -->
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary p-3 rounded-circle mb-2">
                            <i class="fa-solid fa-arrow-right-to-bracket fs-3"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Welcome Back</h3>
                        <p class="text-muted small mb-0">Sign in to your GabayKarera AI account</p>
                    </div>

                    <!-- LOGIN: ERROR ALERT NOTIFICATION -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger card-modern p-3 mb-4 d-flex align-items-center">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
                            <span class="small"><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- LOGIN: INPUT FORM -->
                    <form method="POST" action="/GabayKarera/login.php" id="loginForm">
                        <!-- LOGIN: EMAIL INPUT -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-regular fa-envelope text-muted"></i></span>
                                <input type="email" name="email" id="loginEmail" class="form-control py-2" placeholder="name@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? $rememberedEmail) ?>">
                            </div>
                        </div>

                        <!-- LOGIN: PASSWORD INPUT (MAX 10 CHARS WITH EYE TOGGLE) -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold mb-1">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                                <input type="password" name="password" id="loginPassword" class="form-control py-2" placeholder="Max 10 characters" maxlength="10" required>
                                <button class="btn btn-outline-secondary border bg-light text-muted" type="button" onclick="togglePasswordVisibility('loginPassword', 'loginEyeIcon')" title="Toggle password visibility">
                                    <i class="fa-solid fa-eye" id="loginEyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- LOGIN: REMEMBER ME & FORGOT PASSWORD -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe" <?= (!empty($rememberedEmail) || isset($_POST['remember'])) ? 'checked' : '' ?>>
                                <label class="form-check-label small text-secondary" for="rememberMe">
                                    Remember me
                                </label>
                            </div>
                            <a href="javascript:void(0)" class="small text-decoration-none text-muted" onclick="alert('Please contact your system administrator to reset your password.');">
                                Forgot password?
                            </a>
                        </div>

                        <!-- LOGIN: SUBMIT BUTTON -->
                        <button type="submit" class="btn btn-primary-custom w-100 py-2.5 fs-6 mb-3">
                            <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
                        </button>
                    </form>
                </div>

                <!-- LOGIN: REGISTRATION LINK FOOTER -->
                <div class="text-center text-muted small pt-3 border-top mt-3">
                    Don't have an account? <a href="/GabayKarera/register.php" class="text-primary fw-bold text-decoration-none">Create an account</a>
                </div>
            </div>
        </div>

        <!-- ==================== RIGHT COLUMN: USER GUIDELINES PANEL ==================== -->
        <div class="col-lg-7 col-md-12">
            <div class="card card-modern p-4 p-md-5 border-0 shadow-lg h-100 bg-white">
                
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="bg-primary-subtle text-primary p-2 rounded-3">
                        <i class="fa-solid fa-book-open-reader fs-4"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0">System User Guidelines</h4>
                        <small class="text-muted">How to navigate and utilize GabayKarera AI by Role</small>
                    </div>
                </div>

                <!-- Guidelines Role Tabs Navigation -->
                <ul class="nav nav-pills mb-4 gap-2 bg-light p-1.5 rounded-3" id="guidelinesTab" role="tablist">
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link active w-100 py-2 small fw-bold text-center" id="applicant-tab" data-bs-toggle="pill" data-bs-target="#applicant-guide" type="button" role="tab">
                            <i class="fa-regular fa-user me-1"></i> Applicant Guide
                        </button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link w-100 py-2 small fw-bold text-center" id="hr-tab" data-bs-toggle="pill" data-bs-target="#hr-guide" type="button" role="tab">
                            <i class="fa-solid fa-building me-1"></i> HR / Employer Guide
                        </button>
                   
                </ul>

                <!-- Guidelines Tab Content -->
                <div class="tab-content flex-grow-1" id="guidelinesTabContent">
                    
                    <!-- TAB 1: APPLICANT GUIDELINES -->
                    <div class="tab-pane fade show active" id="applicant-guide" role="tabpanel">
                        <div class="p-3 bg-primary-subtle border border-primary-subtle rounded-3 mb-3">
                            <h6 class="fw-bold text-primary mb-1"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> For Job Seekers & Graduates</h6>
                            <p class="small text-secondary mb-0">GabayKarera connects you to ideal jobs using automated AI competency matching, gap analysis, and career roadmaps.</p>
                        </div>

                        <div class="timeline-steps">
                            <div class="d-flex mb-3">
                                <div class="badge bg-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">1</div>
                                <div>
                                    <div class="fw-bold small text-dark">Build Your Profile & Add Skills</div>
                                    <p class="text-muted small mb-0">Fill out your degree, experience, and add at least 3 skills with proficiency levels (Beginner to Expert) to boost your AI match accuracy.</p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="badge bg-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">2</div>
                                <div>
                                    <div class="fw-bold small text-dark">Explore AI Matched Jobs</div>
                                    <p class="text-muted small mb-0">Jobs are sorted by real-time match percentages. Open any job to see which skills you match and which skills you are missing.</p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="badge bg-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">3</div>
                                <div>
                                    <div class="fw-bold small text-dark">Upskill via Learning Hub & Career Guide</div>
                                    <p class="text-muted small mb-0">Bridge your competency gaps by exploring curated courses, industry certificates, and salary trends for recommended careers.</p>
                                </div>
                            </div>
                            <div class="d-flex">
                                <div class="badge bg-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">4</div>
                                <div>
                                    <div class="fw-bold small text-dark">Apply & Communicate Directly with HR</div>
                                    <p class="text-muted small mb-0">Submit 1-click applications and reply directly to employer interview invitations via the "My Applications" messaging tab.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: HR / EMPLOYER GUIDELINES -->
                    <div class="tab-pane fade" id="hr-guide" role="tabpanel">
                        <div class="p-3 bg-success-subtle border border-success-subtle rounded-3 mb-3">
                            <h6 class="fw-bold text-success mb-1"><i class="fa-solid fa-briefcase me-1"></i> For Employers & Talent Acquisition</h6>
                            <p class="small text-secondary mb-0">Automate talent screening, evaluate candidate competency breakdowns, and engage qualified candidates faster.</p>
                        </div>

                        <div class="timeline-steps">
                            <div class="d-flex mb-3">
                                <div class="badge bg-success rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">1</div>
                                <div>
                                    <div class="fw-bold small text-dark">Complete Company Verification</div>
                                    <p class="text-muted small mb-0">Provide your SEC/DTI registration, Tax ID (TIN), and official headquarters address to earn the official <strong>Verified Employer</strong> badge.</p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="badge bg-success rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">2</div>
                                <div>
                                    <div class="fw-bold small text-dark">Post Openings with Required Skills</div>
                                    <p class="text-muted small mb-0">Define specific technical, soft, and domain competencies along with salary brackets to trigger instant applicant-to-job matching.</p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="badge bg-success rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">3</div>
                                <div>
                                    <div class="fw-bold small text-dark">Screen Matched Candidates</div>
                                    <p class="text-muted small mb-0">Inspect applicants ranked by AI Match Score. Review their complete career background, education, and side-by-side competency radar fit.</p>
                                </div>
                            </div>
                            <div class="d-flex">
                                <div class="badge bg-success rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; min-width: 28px;">4</div>
                                <div>
                                    <div class="fw-bold small text-dark">Dispatch Interview Notices & Messages</div>
                                    <p class="text-muted small mb-0">Change candidate statuses (Interviewing, Shortlisted, Hired) and send interview invitations directly through the applicant thread.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                  

     

<?php require_once __DIR__ . '/includes/footer.php'; ?>
