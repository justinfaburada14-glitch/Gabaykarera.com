<?php
// config/setup.php

function runDatabaseSetup($pdo) {
    // Disable foreign key checks during setup
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Users Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(150) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('applicant', 'hr', 'admin') NOT NULL DEFAULT 'applicant',
            `phone` VARCHAR(30) NULL,
            `company_name` VARCHAR(150) NULL,
            `company_reg_number` VARCHAR(100) NULL,
            `company_tin` VARCHAR(50) NULL,
            `company_website` VARCHAR(255) NULL,
            `company_address` TEXT NULL,
            `company_industry` VARCHAR(100) NULL DEFAULT 'Information Technology & Services',
            `company_size` VARCHAR(50) NULL DEFAULT '50-200 Employees',
            `hr_designation` VARCHAR(100) NULL DEFAULT 'HR & Talent Acquisition Lead',
            `company_verification_status` ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
            `company_verification_notes` TEXT NULL,
            `avatar` VARCHAR(255) DEFAULT 'default_avatar.png',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Applicant Profiles Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `applicant_profiles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL UNIQUE,
            `headline` VARCHAR(200) NULL,
            `bio` TEXT NULL,
            `education_level` VARCHAR(100) NULL,
            `field_of_study` VARCHAR(150) NULL,
            `school` VARCHAR(150) NULL,
            `graduation_year` INT NULL,
            `experience_years` DECIMAL(4,1) DEFAULT 0.0,
            `current_position` VARCHAR(150) NULL,
            `expected_salary` DECIMAL(10,2) NULL,
            `location` VARCHAR(150) NULL,
            `interests` TEXT NULL,
            `raw_resume_text` TEXT NULL,
            `resume_file` VARCHAR(255) NULL,
            `work_history` TEXT NULL,
            `certifications` TEXT NULL,
            `portfolio_url` VARCHAR(255) NULL,
            `linkedin_url` VARCHAR(255) NULL,
            `github_url` VARCHAR(255) NULL,
            `career_goals` TEXT NULL,
            `work_preference` VARCHAR(100) NULL,
            `academic_honors` VARCHAR(255) NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Skills Library & Applicant Skills
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `skills` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE,
            `category` ENUM('Technical', 'Soft Skill', 'Domain/Tool') DEFAULT 'Technical'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `applicant_skills` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `skill_name` VARCHAR(100) NOT NULL,
            `proficiency` ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Intermediate',
            `years_experience` INT DEFAULT 1,
            `category` VARCHAR(50) DEFAULT 'Technical',
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Jobs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `jobs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `employer_id` INT NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `company_name` VARCHAR(150) NOT NULL,
            `department` VARCHAR(100) NULL,
            `location` VARCHAR(150) NOT NULL,
            `job_type` ENUM('Full-Time', 'Part-Time', 'Contract', 'Internship', 'Remote') DEFAULT 'Full-Time',
            `experience_required` DECIMAL(4,1) DEFAULT 0.0,
            `education_required` VARCHAR(100) DEFAULT 'Bachelor\'s Degree',
            `salary_min` DECIMAL(10,2) NULL,
            `salary_max` DECIMAL(10,2) NULL,
            `description` TEXT NOT NULL,
            `required_skills` TEXT NOT NULL, /* JSON or Comma-separated list */
            `preferred_skills` TEXT NULL,
            `status` ENUM('Open', 'Closed', 'Draft') DEFAULT 'Open',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`employer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Applications Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `applications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `job_id` INT NOT NULL,
            `applicant_id` INT NOT NULL,
            `match_score` DECIMAL(5,2) DEFAULT 0.00,
            `competency_breakdown` TEXT NULL, /* JSON storing skill match, exp match, gaps */
            `status` ENUM('Submitted', 'Reviewing', 'Shortlisted', 'Interview Scheduled', 'Rejected', 'Hired') DEFAULT 'Submitted',
            `cover_note` TEXT NULL,
            `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`applicant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 6. Career Pathways Table (For AI Recommendations)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `career_paths` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `category` VARCHAR(100) NOT NULL,
            `description` TEXT NOT NULL,
            `core_skills` TEXT NOT NULL, /* comma separated */
            `average_salary` VARCHAR(100) NULL,
            `growth_rate` VARCHAR(50) NULL,
            `recommended_certifications` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 7. Application Messages & Decision Communication Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `application_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `application_id` INT NOT NULL,
            `sender_id` INT NOT NULL,
            `receiver_id` INT NOT NULL,
            `message_type` ENUM('Status Update', 'Message', 'Interview Invitation', 'Job Offer', 'Decision Notice') DEFAULT 'Message',
            `subject` VARCHAR(255) NOT NULL,
            `body` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Seed Initial Data
    seedInitialData($pdo);
}

function seedInitialData($pdo) {
    // Check if demo users exist
    $check = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($check > 0) {
        return; // already seeded
    }

    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);

    // Insert Default Users
    // 1. Admin
    // 2. HR (TechCorp Global & FinEdge Solutions)
    // 3. Applicants (Juan Dela Cruz - Software Engineer, Maria Santos - Data Analyst, Carlo Gomez - UI/UX Designer)
    $users = [
        [1, 'System Administrator', 'admin@gabaykarera.com', $defaultPassword, 'admin', '09171234567', 'GabayKarera Administration'],
        [2, 'Sarah Jenkins (HR Director)', 'hr@techcorp.com', $defaultPassword, 'hr', '09181112233', 'TechCorp Solutions'],
        [3, 'Mark Rivera (Talent Acquisition)', 'hr@finedge.com', $defaultPassword, 'hr', '09192223344', 'FinEdge Analytics'],
        [4, 'Juan Dela Cruz', 'applicant@gabaykarera.com', $defaultPassword, 'applicant', '09175556677', ''],
        [5, 'Maria Santos', 'maria.santos@gmail.com', $defaultPassword, 'applicant', '09187778899', ''],
        [6, 'Carlo Gomez', 'carlo.gomez@gmail.com', $defaultPassword, 'applicant', '09190001122', ''],
    ];

    $stmt = $pdo->prepare("INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `phone`, `company_name`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->execute($user);
    }

    // Insert Applicant Profiles
    $profiles = [
        [
            4,
            'Full Stack Web Developer & Software Engineer',
            'Enthusiastic Computer Science graduate passionate about building modern web applications, RESTful APIs, and responsive frontends. Experienced in PHP, JavaScript, MySQL, and Python.',
            'Bachelor of Science in Computer Science',
            'Computer Science',
            'Polytechnic University of the Philippines',
            2024,
            1.5,
            'Junior Web Developer',
            45000.00,
            'Metro Manila, Philippines',
            'Web Development, Cloud Computing, Artificial Intelligence, Agile Methodologies',
            'Experienced in full-stack web development using PHP, MySQL, JavaScript, HTML5, CSS3, Git, REST APIs, and Python. Built multiple university capstone and freelance projects.'
        ],
        [
            5,
            'Data Analyst & Business Intelligence Specialist',
            'Detail-oriented Data Analyst skilled in Python, SQL, Tableau, Power BI, and statistical modeling. Passionate about transforming raw data into actionable business insights.',
            'Bachelor of Science in Information Technology',
            'Data Science & Analytics',
            'University of Santo Tomas',
            2023,
            2.0,
            'Associate Data Analyst',
            50000.00,
            'Quezon City, Philippines',
            'Data Visualization, Machine Learning, Business Analytics, Predictive Modeling',
            'Proficient in Python, Pandas, NumPy, SQL, PostgreSQL, Power BI, Tableau, Excel, Data Cleaning, and Statistical Analysis.'
        ],
        [
            6,
            'UI/UX Designer & Product Specialist',
            'Creative UI/UX Designer with a strong foundation in wireframing, high-fidelity prototyping in Figma, user research, and usability testing.',
            'Bachelor of Fine Arts in Information Design',
            'Multimedia Arts & UI/UX',
            'Ateneo de Manila University',
            2024,
            1.0,
            'Junior UX Designer',
            40000.00,
            'Taguig / BGC, Philippines',
            'User Experience, Interaction Design, Design Systems, Mobile UI, User Testing',
            'Experienced in Figma, Adobe XD, Wireframing, Prototyping, Usability Testing, CSS3, Design Systems, and Responsive Design.'
        ]
    ];

    $profileStmt = $pdo->prepare("INSERT INTO `applicant_profiles` (`user_id`, `headline`, `bio`, `education_level`, `field_of_study`, `school`, `graduation_year`, `experience_years`, `current_position`, `expected_salary`, `location`, `interests`, `raw_resume_text`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($profiles as $p) {
        $profileStmt->execute($p);
    }

    // Insert Applicant Skills
    $applicantSkills = [
        // Juan Dela Cruz
        [4, 'PHP', 'Advanced', 2, 'Technical'],
        [4, 'JavaScript', 'Advanced', 2, 'Technical'],
        [4, 'MySQL', 'Intermediate', 2, 'Technical'],
        [4, 'HTML5/CSS3', 'Expert', 3, 'Technical'],
        [4, 'Git & GitHub', 'Advanced', 2, 'Domain/Tool'],
        [4, 'RESTful APIs', 'Intermediate', 1, 'Technical'],
        [4, 'Python', 'Beginner', 1, 'Technical'],
        [4, 'Problem Solving', 'Advanced', 3, 'Soft Skill'],
        [4, 'Team Collaboration', 'Advanced', 3, 'Soft Skill'],
        [4, 'Agile / Scrum', 'Intermediate', 1, 'Domain/Tool'],

        // Maria Santos
        [5, 'Python', 'Expert', 3, 'Technical'],
        [5, 'SQL', 'Expert', 3, 'Technical'],
        [5, 'Power BI', 'Advanced', 2, 'Domain/Tool'],
        [5, 'Tableau', 'Intermediate', 2, 'Domain/Tool'],
        [5, 'Data Cleaning', 'Expert', 2, 'Technical'],
        [5, 'Statistical Analysis', 'Advanced', 2, 'Technical'],
        [5, 'Excel Advanced', 'Expert', 3, 'Domain/Tool'],
        [5, 'Critical Thinking', 'Advanced', 3, 'Soft Skill'],
        [5, 'Business Communication', 'Advanced', 3, 'Soft Skill'],

        // Carlo Gomez
        [6, 'Figma', 'Expert', 3, 'Domain/Tool'],
        [6, 'UI/UX Design', 'Expert', 2, 'Technical'],
        [6, 'Wireframing & Prototyping', 'Expert', 2, 'Technical'],
        [6, 'User Research', 'Advanced', 2, 'Technical'],
        [6, 'Design Systems', 'Advanced', 1, 'Technical'],
        [6, 'HTML5/CSS3', 'Intermediate', 1, 'Technical'],
        [6, 'Adobe Creative Suite', 'Intermediate', 2, 'Domain/Tool'],
        [6, 'Empathy & Communication', 'Expert', 3, 'Soft Skill'],
    ];

    $skillStmt = $pdo->prepare("INSERT INTO `applicant_skills` (`user_id`, `skill_name`, `proficiency`, `years_experience`, `category`) VALUES (?, ?, ?, ?, ?)");
    foreach ($applicantSkills as $as) {
        $skillStmt->execute($as);
    }

    // Insert Career Paths (AI Recommender Reference)
    $careerPaths = [
        [
            'Full Stack Web Developer',
            'Software & Web Development',
            'Builds front-end and back-end architecture for responsive web applications, manages database queries, and designs secure APIs.',
            'PHP, JavaScript, MySQL, HTML5/CSS3, Git & GitHub, RESTful APIs, Node.js, React',
            '₱45,000 - ₱95,000 / month',
            '+22% (High Demand)',
            'AWS Certified Developer, Certified Web Developer, Meta Full-Stack Certificate'
        ],
        [
            'Data Analyst / BI Specialist',
            'Data Science & Analytics',
            'Extracts, cleans, analyzes, and visualizes complex datasets to help business leaders make data-backed strategic decisions.',
            'Python, SQL, Power BI, Tableau, Statistical Analysis, Data Cleaning, Excel Advanced',
            '₱40,000 - ₱85,000 / month',
            '+28% (Very High Demand)',
            'Google Data Analytics Professional, Microsoft Power BI Certified (PL-300)'
        ],
        [
            'UI/UX & Product Designer',
            'Design & User Experience',
            'Creates intuitive user journeys, wireframes, interactive prototypes, and design systems for web and mobile digital products.',
            'Figma, UI/UX Design, Wireframing & Prototyping, User Research, Design Systems, HTML5/CSS3',
            '₱40,000 - ₱80,000 / month',
            '+18% (Growing)',
            'Google UX Design Certificate, Nielsen Norman Group UX Certification'
        ],
        [
            'Cloud & DevOps Engineer',
            'Infrastructure & Cloud',
            'Automates continuous integration and deployment pipelines, monitors scalable cloud infrastructure, and enhances server reliability.',
            'Linux, Docker, Kubernetes, AWS, CI/CD, Git & GitHub, Python, Bash Scripting',
            '₱60,000 - ₱130,000 / month',
            '+30% (High Demand)',
            'AWS Solutions Architect, CKA Kubernetes Administrator'
        ],
        [
            'Cybersecurity Analyst',
            'Information Security',
            'Protects enterprise systems and data networks against security breaches, conducts vulnerability assessments, and enforces compliance.',
            'Network Security, Penetration Testing, Python, Linux, Incident Response, Cryptography',
            '₱55,000 - ₱110,000 / month',
            '+33% (Critical Demand)',
            'CompTIA Security+, CEH (Certified Ethical Hacker), CISSP'
        ]
    ];

    $cpStmt = $pdo->prepare("INSERT INTO `career_paths` (`title`, `category`, `description`, `core_skills`, `average_salary`, `growth_rate`, `recommended_certifications`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($careerPaths as $cp) {
        $cpStmt->execute($cp);
    }

    // Insert Jobs
    $jobs = [
        [
            2,
            'Junior Full Stack Developer',
            'TechCorp Solutions',
            'Engineering',
            'BGC, Taguig (Hybrid)',
            'Full-Time',
            1.0,
            'Bachelor\'s Degree in Computer Science / IT',
            38000.00,
            50000.00,
            'We are seeking an enthusiastic Junior Full Stack Developer to join our core product team. You will build and maintain dynamic web applications, integrate REST APIs, and optimize database queries. Great mentorship and career growth opportunities.',
            'PHP, JavaScript, MySQL, HTML5/CSS3, Git & GitHub',
            'RESTful APIs, Docker, Agile / Scrum',
            'Open'
        ],
        [
            2,
            'Senior Backend PHP / API Engineer',
            'TechCorp Solutions',
            'Engineering',
            'BGC, Taguig (Hybrid)',
            'Full-Time',
            4.0,
            'Bachelor\'s Degree in Computer Science / IT',
            80000.00,
            120000.00,
            'Looking for an experienced Backend Engineer to architect high-throughput APIs, microservices, and secure relational database architectures.',
            'PHP, MySQL, RESTful APIs, Git & GitHub, Docker, Linux',
            'Redis, AWS, Unit Testing, CI/CD',
            'Open'
        ],
        [
            3,
            'Associate Data Analyst',
            'FinEdge Analytics',
            'Business Intelligence',
            'Makati City (Remote-friendly)',
            'Full-Time',
            1.5,
            'Bachelor\'s Degree in Data Science / IT / Statistics',
            45000.00,
            60000.00,
            'Join our financial analytics squad! You will develop automated Power BI reports, conduct statistical querying via SQL and Python, and assist executive stakeholders with KPI dashboards.',
            'Python, SQL, Power BI, Statistical Analysis, Data Cleaning',
            'Tableau, Excel Advanced, Machine Learning',
            'Open'
        ],
        [
            2,
            'UI/UX Product Designer',
            'TechCorp Solutions',
            'Product & Design',
            'BGC, Taguig (Remote)',
            'Full-Time',
            1.0,
            'Bachelor\'s Degree in Design / Multimedia / IT',
            40000.00,
            55000.00,
            'Create delightful user experiences for thousands of daily active users! Work alongside product managers and engineers to build component libraries and test prototypes in Figma.',
            'Figma, UI/UX Design, Wireframing & Prototyping, User Research, Design Systems',
            'HTML5/CSS3, Adobe Creative Suite, Usability Testing',
            'Open'
        ],
        [
            3,
            'Frontend React & UI Developer',
            'FinEdge Analytics',
            'Engineering',
            'Makati City (Hybrid)',
            'Full-Time',
            2.0,
            'Bachelor\'s Degree in IT / Computer Science',
            50000.00,
            75000.00,
            'Build responsive, high-performance dashboards with modern JavaScript and design systems. Collaborate closely with our UX and backend teams.',
            'JavaScript, HTML5/CSS3, Git & GitHub, RESTful APIs',
            'React, Figma, TypeScript, Tailwind CSS',
            'Open'
        ]
    ];

    $jobStmt = $pdo->prepare("INSERT INTO `jobs` (`employer_id`, `title`, `company_name`, `department`, `location`, `job_type`, `experience_required`, `education_required`, `salary_min`, `salary_max`, `description`, `required_skills`, `preferred_skills`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($jobs as $j) {
        $jobStmt->execute($j);
    }

    // Insert Sample Applications
    $applications = [
        [
            1, // Job 1: Junior Full Stack Developer
            4, // Juan Dela Cruz
            92.50,
            json_encode([
                'skill_match' => 95,
                'exp_match' => 100,
                'matched_skills' => ['PHP', 'JavaScript', 'MySQL', 'HTML5/CSS3', 'Git & GitHub'],
                'missing_skills' => ['Docker'],
                'overall_verdict' => 'Highly Qualified Candidate with strong core competency alignment.'
            ]),
            'Shortlisted',
            'I am very excited to apply for the Junior Full Stack Developer position at TechCorp. My background in PHP, MySQL, and JavaScript aligns strongly with the requirements.'
        ],
        [
            3, // Job 3: Associate Data Analyst
            5, // Maria Santos
            96.00,
            json_encode([
                'skill_match' => 100,
                'exp_match' => 100,
                'matched_skills' => ['Python', 'SQL', 'Power BI', 'Statistical Analysis', 'Data Cleaning'],
                'missing_skills' => ['Machine Learning'],
                'overall_verdict' => 'Exceptional Match with comprehensive analytical and tool competency.'
            ]),
            'Interview Scheduled',
            'I have 2 years of hands-on data analytics experience in SQL, Python, and Power BI dashboards. I look forward to contributing to FinEdge Analytics.'
        ],
        [
            4, // Job 4: UI/UX Product Designer
            6, // Carlo Gomez
            90.00,
            json_encode([
                'skill_match' => 95,
                'exp_match' => 90,
                'matched_skills' => ['Figma', 'UI/UX Design', 'Wireframing & Prototyping', 'User Research', 'Design Systems'],
                'missing_skills' => ['Adobe Creative Suite'],
                'overall_verdict' => 'Strong design foundation and user-centered prototyping proficiency.'
            ]),
            'Reviewing',
            'I love crafting clean and accessible design systems in Figma. My portfolio showcases user research and interactive design flows.'
        ]
    ];

    $appStmt = $pdo->prepare("INSERT INTO `applications` (`job_id`, `applicant_id`, `match_score`, `competency_breakdown`, `status`, `cover_note`) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($applications as $app) {
        $appStmt->execute($app);
    }
}
?>
