<?php
/**
 * ============================================================================
 * GABAYKARERA AI - GLOBAL FOOTER TEMPLATE
 * File: includes/footer.php
 * Purpose: Platform footer, credits, quick links, and client script bundles
 * ============================================================================
 */
?>
</main> <!-- End of main content wrapper -->

<!-- ========================================================================== -->
<!-- SECTION: FOOTER -->
<!-- ========================================================================== -->
<footer class="bg-white border-top mt-5 py-5 text-secondary">
    <div class="container">
        <div class="row g-4">
            
            <!-- ==================== 1. BRAND & PROBLEM STATEMENT SUMMARY ==================== -->
            <div class="col-lg-5">
                <div class="d-flex align-items-center mb-3">
                    <i class="fa-solid fa-brain me-2 text-primary fs-4"></i>
                    <span class="brand-logo">GabayKarera</span>
                    <span class="brand-badge">AI</span>
                </div>
                <p class="small text-muted pe-lg-4 mb-3">
                    <strong>GabayKarera AI: An Intelligent HR Career Recommendation and Applicant-Employer Matching with Competency Analysis.</strong>
                    Empowering students, graduates, and job seekers with AI-driven competency gap analysis, while providing HR personnel with automated, high-precision candidate matching.
                </p>
                <div class="d-flex gap-3 fs-5 text-muted">
                    <a href="#" class="text-secondary"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="text-secondary"><i class="fa-brands fa-linkedin"></i></a>
                    <a href="#" class="text-secondary"><i class="fa-brands fa-facebook"></i></a>
                </div>
            </div>

            <!-- ==================== 2. APPLICANT QUICK LINKS ==================== -->
            <div class="col-6 col-lg-2">
                <h6 class="text-dark fw-bold mb-3">For Job Seekers</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="/GabayKarera/applicant/jobs.php" class="text-decoration-none text-muted">Browse Jobs</a></li>
                    <li><a href="/GabayKarera/applicant/recommendations.php" class="text-decoration-none text-muted">AI Career Guide</a></li>
                    <li><a href="/GabayKarera/applicant/profile.php" class="text-decoration-none text-muted">Competency Profile</a></li>
                    <li><a href="/GabayKarera/applicant/applications.php" class="text-decoration-none text-muted">Application Tracker</a></li>
                </ul>
            </div>

            <!-- ==================== 3. EMPLOYER QUICK LINKS ==================== -->
            <div class="col-6 col-lg-2">
                <h6 class="text-dark fw-bold mb-3">For Employers</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="/GabayKarera/hr/dashboard.php" class="text-decoration-none text-muted">HR Dashboard</a></li>
                    <li><a href="/GabayKarera/hr/applicants.php" class="text-decoration-none text-muted">Candidate Matching</a></li>
                    <li><a href="/GabayKarera/hr/job-create.php" class="text-decoration-none text-muted">Post a Job Opening</a></li>
                    <li><a href="/GabayKarera/hr/jobs.php" class="text-decoration-none text-muted">Manage Postings</a></li>
                </ul>
            </div>

            <!-- ==================== 4. AI CAPABILITIES SUMMARY ==================== -->
            <div class="col-lg-3">
                <h6 class="text-dark fw-bold mb-3">AI Intelligence</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 text-muted">
                    <li><i class="fa-solid fa-check text-primary me-2"></i> Multi-factor Competency Scoring</li>
                    <li><i class="fa-solid fa-check text-primary me-2"></i> Skill Gap & Upskilling Roadmap</li>
                    <li><i class="fa-solid fa-check text-primary me-2"></i> Radar Chart Fit Visualizations</li>
                    <li><i class="fa-solid fa-check text-primary me-2"></i> High-Precision Applicant Matching</li>
                </ul>
            </div>
        </div>

        <hr class="my-4 text-muted opacity-25">

        <!-- ==================== 5. COPYRIGHT & DISCLAIMER ==================== -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <div>
                &copy; <?= date('Y') ?> <strong>GabayKarera AI</strong>. All rights reserved. Intelligent HR Career Guidance & Competency Matching System.
            </div>
            <div class="d-flex gap-3 mt-2 mt-md-0">
                <span>Problem Statement Addressed: Skill-Career Alignment & Candidate Screening</span>
            </div>
        </div>
    </div>
</footer>

<!-- ========================================================================== -->
<!-- SECTION: GABAY AI FLOATING CHATBOT WIDGET -->
<!-- ========================================================================== -->
<!-- Floating Launcher Button -->
<button class="gabay-chat-toggle-btn" onclick="toggleAiChat()" title="Ask Gabay AI Advisor" aria-label="Open Gabay AI Chat">
    <i class="fa-solid fa-brain"></i>
    <span class="gabay-chat-badge-pulse"></span>
</button>

<!-- Floating Chat Window (Initially Hidden) -->
<div id="gabayAiChatBox" class="gabay-chat-window shadow-lg border" style="display: none;">
    <!-- Chat Header -->
    <div class="gabay-chat-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                <i class="fa-solid fa-robot fs-6"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold text-white fs-7">Gabay AI Career Advisor</h6>
                <small class="text-white-50" style="font-size: 0.7rem;"><i class="fa-solid fa-circle text-success me-1" style="font-size: 0.5rem;"></i>Active & Ready to Guide</small>
            </div>
        </div>
        <button type="button" class="btn btn-sm text-white-50 p-0 border-0 fs-5" onclick="toggleAiChat()" title="Close chat">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Chat Messages Body -->
    <div class="gabay-chat-messages" id="gabayAiMessages">
        <!-- AI Welcome Message -->
        <div class="d-flex justify-content-start mb-2">
            <div class="bg-light text-dark p-3 rounded-3 small border" style="max-width: 85%; line-height: 1.5;">
                <div class="d-flex align-items-center gap-1 mb-1 text-primary fw-bold" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Gabay AI Advisor
                </div>
                👋 Hello! I'm your <strong>GabayKarera Intelligent Career Assistant</strong>. How can I help with your career, competency matching, or interview prep today?
            </div>
        </div>

        <!-- Quick Action Prompt Chips -->
        <div class="mb-3 px-1">
            <div class="text-muted fw-semibold mb-1" style="font-size: 0.7rem;">Recommended questions:</div>
            <button class="gabay-chat-prompt-chip" onclick="askAiPrompt('How can I improve my AI job match score?')">💡 Boost Match Score</button>
            <button class="gabay-chat-prompt-chip" onclick="askAiPrompt('What are common interview questions?')">🎯 Interview Prep</button>
            <button class="gabay-chat-prompt-chip" onclick="askAiPrompt('What is the salary benchmark in Philippines?')">💰 Salary Rates</button>
            <button class="gabay-chat-prompt-chip" onclick="askAiPrompt('How do competency gaps work?')">📊 Competency Gaps</button>
        </div>
    </div>

    <!-- Chat Input Footer -->
    <div class="p-2 bg-white border-top">
        <div class="input-group">
            <input type="text" id="gabayAiInput" class="form-control form-control-sm border-end-0 bg-light" placeholder="Ask about jobs, skills, or interview tips..." onkeypress="if(event.key === 'Enter') sendAiMessage()">
            <button class="btn btn-primary btn-sm px-3" type="button" onclick="sendAiMessage()">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================== -->
<!-- SECTION: SCRIPTS -->
<!-- ========================================================================== -->
<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom Application JS -->
<?php
$mainJsPath = __DIR__ . '/../assets/js/main.js';
$mainJsVer = file_exists($mainJsPath) ? filemtime($mainJsPath) : time();
?>
<script src="/GabayKarera/assets/js/main.js?v=<?= $mainJsVer ?>"></script>
</body>
</html>
