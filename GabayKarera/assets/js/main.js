// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips if bootstrap is loaded
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Auto-dismiss alerts after 5 seconds
    const autoAlerts = document.querySelectorAll('.alert-dismissible');
    autoAlerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Force reload on browser back/forward navigation (disable BFCache for auth consistency)
window.addEventListener('pageshow', function(event) {
    if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
        window.location.reload();
    }
});

// Password visibility toggle helper
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Helper to render Competency Radar Chart using Chart.js
function renderCompetencyRadar(canvasId, labels, dataValues, chartTitle = 'Competency Profile') {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [{
                label: chartTitle,
                data: dataValues,
                fill: true,
                backgroundColor: 'rgba(37, 99, 235, 0.2)',
                borderColor: 'rgb(37, 99, 235)',
                pointBackgroundColor: 'rgb(37, 99, 235)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgb(37, 99, 235)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    angleLines: {
                        color: 'rgba(0, 0, 0, 0.08)'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.08)'
                    },
                    suggestedMin: 0,
                    suggestedMax: 100,
                    ticks: {
                        stepSize: 20,
                        display: false
                    },
                    pointLabels: {
                        font: {
                            size: 11,
                            weight: '600',
                            family: "'Plus Jakarta Sans', sans-serif"
                        },
                        color: '#475569'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// ============================================================================
// GABAY AI CHATBOT ASSISTANT LOGIC
// ============================================================================

function toggleAiChat() {
    const chatBox = document.getElementById('gabayAiChatBox');
    if (!chatBox) return;
    if (chatBox.style.display === 'none' || chatBox.style.display === '') {
        chatBox.style.display = 'flex';
        const input = document.getElementById('gabayAiInput');
        if (input) input.focus();
    } else {
        chatBox.style.display = 'none';
    }
}

function askAiPrompt(promptText) {
    const input = document.getElementById('gabayAiInput');
    if (!input) return;
    input.value = promptText;
    sendAiMessage();
}

function sendAiMessage() {
    const input = document.getElementById('gabayAiInput');
    const messagesContainer = document.getElementById('gabayAiMessages');
    if (!input || !messagesContainer) return;

    const query = input.value.trim();
    if (query === '') return;

    // Append User Message
    const userMsgDiv = document.createElement('div');
    userMsgDiv.className = 'd-flex justify-content-end mb-2';
    userMsgDiv.innerHTML = `
        <div class="bg-primary text-white p-2 px-3 rounded-3 small" style="max-width: 80%;">
            ${escapeHtml(query)}
        </div>
    `;
    messagesContainer.appendChild(userMsgDiv);
    input.value = '';
    messagesContainer.scrollTop = messagesContainer.scrollHeight;

    // Loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'd-flex justify-content-start mb-2';
    loadingDiv.id = 'aiTypingIndicator';
    loadingDiv.innerHTML = `
        <div class="bg-light p-2 px-3 rounded-3 small border text-muted">
            <i class="fa-solid fa-brain fa-fade me-1 text-primary"></i> GabayKarera AI is thinking...
        </div>
    `;
    messagesContainer.appendChild(loadingDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;

    setTimeout(() => {
        // Remove typing indicator
        const indicator = document.getElementById('aiTypingIndicator');
        if (indicator) indicator.remove();

        // Generate AI Response
        const responseText = generateAiKnowledgeResponse(query);
        const aiMsgDiv = document.createElement('div');
        aiMsgDiv.className = 'd-flex justify-content-start mb-2';
        aiMsgDiv.innerHTML = `
            <div class="bg-light text-dark p-3 rounded-3 small border" style="max-width: 85%; line-height: 1.5;">
                <div class="d-flex align-items-center gap-1 mb-1 text-primary fw-bold" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-robot"></i> Gabay AI Advisor
                </div>
                ${responseText}
            </div>
        `;
        messagesContainer.appendChild(aiMsgDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }, 600);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function generateAiKnowledgeResponse(query) {
    const q = query.toLowerCase();

    if (q.includes('improve') || q.includes('score') || q.includes('increase') || q.includes('boost')) {
        return "<strong>How to boost your AI Match Score:</strong><br>1. <strong>Add Missing Skills:</strong> Visit our <a href='/GabayKarera/applicant/learning-hub.php' class='fw-bold'>AI Learning Hub</a> and mark newly acquired tools as Mastered.<br>2. <strong>Upload Your Resume:</strong> Ensure your PDF/DOCX resume is uploaded on your <a href='/GabayKarera/applicant/profile.php'>Profile page</a> for full competency extraction.<br>3. <strong>Update Experience:</strong> Accurately indicate your years of experience and past projects.";
    }

    if (q.includes('interview') || q.includes('question') || q.includes('prep')) {
        return "<strong>Top Interview Questions for Tech & Analytics Roles:</strong><br>• <em>'Explain a complex project you built and how you handled database or API optimization.'</em><br>• <em>'How do you identify and resolve bottlenecks in code or workflows?'</em><br>• <em>'Describe a situation where you had to quickly learn a new framework or tool.'</em><br><br>💡 <em>Tip: Structure your answers using the STAR method (Situation, Task, Action, Result).</em>";
    }

    if (q.includes('salary') || q.includes('rate') || q.includes('negotiat') || q.includes('compensation')) {
        return "<strong>Industry Salary Benchmarks (Philippines Tech/Analytics):</strong><br>• <strong>Junior Developer / Analyst:</strong> ₱35,000 – ₱50,000 / month<br>• <strong>Mid-Level Specialist:</strong> ₱55,000 – ₱85,000 / month<br>• <strong>Senior / Lead Engineer:</strong> ₱90,000 – ₱140,000+ / month<br><br><em>Always negotiate based on your proven competencies and portfolio achievements!</em>";
    }

    if (q.includes('gap') || q.includes('missing') || q.includes('learn')) {
        return "<strong>Understanding Competency Gaps:</strong><br>Our AI identifies skills that employers require which aren't yet in your profile. You can bridge these gaps by completing micro-credentials on our <a href='/GabayKarera/applicant/learning-hub.php' class='fw-bold'>Learning Hub</a> (such as Docker, AWS, React, Python, or Power BI).";
    }

    if (q.includes('hr') || q.includes('employer') || q.includes('post') || q.includes('hire')) {
        return "<strong>For HR & Employers:</strong><br>You can create job openings with required and preferred competency weights in the <a href='/GabayKarera/hr/job-create.php'>Post Job</a> section. Our AI algorithm will rank all applicants automatically by competency compatibility!";
    }

    // Default friendly assistant response
    return "Hello! I am your <strong>GabayKarera AI Career Advisor</strong>. I can assist you with:<br>• <strong>Competency Match Analysis</strong> (How to increase your job match %)<br>• <strong>Interview Preparation & Practice Questions</strong><br>• <strong>Skill Gap & Upskilling Roadmaps</strong><br>• <strong>Salary & Career Trajectory Guidance</strong><br><br>How can I help you today?";
}
