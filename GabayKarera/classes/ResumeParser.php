<?php
// classes/ResumeParser.php

class ResumeParser {

    private static $skillDictionary = [
        // Programming & Web
        'PHP' => 'Technical',
        'JavaScript' => 'Technical',
        'TypeScript' => 'Technical',
        'Python' => 'Technical',
        'Java' => 'Technical',
        'C++' => 'Technical',
        'C#' => 'Technical',
        'HTML5' => 'Technical',
        'CSS3' => 'Technical',
        'HTML' => 'Technical',
        'CSS' => 'Technical',
        'React' => 'Technical',
        'Vue.js' => 'Technical',
        'Angular' => 'Technical',
        'Node.js' => 'Technical',
        'Laravel' => 'Technical',
        'Django' => 'Technical',
        'Spring Boot' => 'Technical',
        'Express.js' => 'Technical',
        'Bootstrap' => 'Technical',
        'Tailwind CSS' => 'Technical',
        'RESTful APIs' => 'Technical',
        'GraphQL' => 'Technical',

        // Database & Data
        'MySQL' => 'Technical',
        'PostgreSQL' => 'Technical',
        'SQL Server' => 'Technical',
        'Oracle' => 'Technical',
        'MongoDB' => 'Technical',
        'Redis' => 'Technical',
        'SQL' => 'Technical',
        'Data Analysis' => 'Technical',
        'Data Cleaning' => 'Technical',
        'Statistical Analysis' => 'Technical',
        'Power BI' => 'Domain/Tool',
        'Tableau' => 'Domain/Tool',
        'Excel' => 'Domain/Tool',
        'Pandas' => 'Technical',
        'NumPy' => 'Technical',
        'Machine Learning' => 'Technical',

        // Cloud & DevOps & Tools
        'Git' => 'Domain/Tool',
        'GitHub' => 'Domain/Tool',
        'GitLab' => 'Domain/Tool',
        'Docker' => 'Domain/Tool',
        'Kubernetes' => 'Domain/Tool',
        'AWS' => 'Domain/Tool',
        'Azure' => 'Domain/Tool',
        'Linux' => 'Domain/Tool',
        'CI/CD' => 'Domain/Tool',
        'Jira' => 'Domain/Tool',
        'Figma' => 'Domain/Tool',
        'Adobe XD' => 'Domain/Tool',
        'Photoshop' => 'Domain/Tool',
        'UI/UX Design' => 'Technical',
        'Wireframing & Prototyping' => 'Technical',
        'User Research' => 'Technical',
        'Design Systems' => 'Technical',

        // Soft Skills
        'Problem Solving' => 'Soft Skill',
        'Communication' => 'Soft Skill',
        'Team Collaboration' => 'Soft Skill',
        'Leadership' => 'Soft Skill',
        'Time Management' => 'Soft Skill',
        'Critical Thinking' => 'Soft Skill',
        'Agile' => 'Domain/Tool',
        'Scrum' => 'Domain/Tool'
    ];

    /**
     * Extract raw text from uploaded resume file
     */
    public static function extractTextFromFile($filePath, $extension) {
        $ext = strtolower($extension);
        $text = '';

        if ($ext === 'txt') {
            $text = @file_get_contents($filePath);
        } elseif ($ext === 'docx') {
            // DOCX is a zip containing word/document.xml
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                if ($zip->open($filePath) === true) {
                    if (($index = $zip->locateName('word/document.xml')) !== false) {
                        $xmlData = $zip->getFromIndex($index);
                        $text = strip_tags($xmlData);
                    }
                    $zip->close();
                }
            }
        } elseif ($ext === 'pdf') {
            // Basic stream extraction for text in PDF
            $content = @file_get_contents($filePath);
            if ($content !== false) {
                // Strip PDF commands and extract text strings
                preg_match_all('/\((.*?)\)Tj/s', $content, $matches);
                if (!empty($matches[1])) {
                    $text = implode(' ', $matches[1]);
                } else {
                    // Fallback to extracting printable ASCII strings
                    $clean = preg_replace('/[^a-zA-Z0-9\s.,;:_@\-\/()]/', ' ', $content);
                    $words = array_filter(explode(' ', $clean), function($w) {
                        return strlen(trim($w)) > 2;
                    });
                    $text = implode(' ', array_slice($words, 0, 500));
                }
            }
        }

        return $text ?: '';
    }

    /**
     * Parse text and identify skills, experience, education
     */
    public static function analyzeCompetencies($text, $pdo, $existingApplicantData = null) {
        $cleanText = strtolower($text);
        $extractedSkills = [];

        // 1. Match skills
        foreach (self::$skillDictionary as $skill => $category) {
            $skillLower = strtolower($skill);
            // Search word boundary
            $pattern = '/\b' . preg_quote($skillLower, '/') . '\b/i';
            if (preg_match($pattern, $cleanText)) {
                // Determine proficiency based on context
                $proficiency = 'Intermediate';
                if (preg_match('/(expert|lead|senior|advanced|master)\s+(in\s+)?' . preg_quote($skillLower, '/') . '/i', $cleanText) ||
                    preg_match('/' . preg_quote($skillLower, '/') . '\s+\((expert|advanced|5\+?\s*years?)\)/i', $cleanText)) {
                    $proficiency = 'Advanced';
                } elseif (preg_match('/(beginner|basic|familiar|junior)\s+(with\s+)?' . preg_quote($skillLower, '/') . '/i', $cleanText)) {
                    $proficiency = 'Beginner';
                }

                $extractedSkills[] = [
                    'skill_name' => $skill,
                    'proficiency' => $proficiency,
                    'category' => $category,
                    'years_experience' => ($proficiency === 'Advanced' ? 3 : ($proficiency === 'Beginner' ? 1 : 2))
                ];
            }
        }

        // 2. Extract Experience Years estimate (fallback)
        $experienceYears = 1.0;
        if (!empty($existingApplicantData['profile']['experience_years'])) {
            $experienceYears = (float)$existingApplicantData['profile']['experience_years'];
        } elseif (preg_match('/(\d+(\.\d+)?)\+?\s*(years?|yrs?)\s*(of)?\s*(experience|work)/i', $cleanText, $expMatch)) {
            $experienceYears = (float)$expMatch[1];
        } elseif (preg_match('/(fresh graduate|entry level|graduate)/i', $cleanText)) {
            $experienceYears = 0.5;
        }

        // 3. Extract Education Level estimate (fallback)
        $educationLevel = "Bachelor's Degree";
        if (!empty($existingApplicantData['profile']['education_level'])) {
            $educationLevel = $existingApplicantData['profile']['education_level'];
        } elseif (preg_match('/(master|m\.s\.|mba)/i', $cleanText)) {
            $educationLevel = "Master's Degree";
        } elseif (preg_match('/(doctorate|ph\.?d)/i', $cleanText)) {
            $educationLevel = "Doctorate / PhD";
        } elseif (preg_match('/(associate|diploma)/i', $cleanText)) {
            $educationLevel = "Associate Degree / Diploma";
        }

        // Merge existing saved skills with newly extracted skills
        $mergedSkillsMap = [];
        $profRank = ['Beginner' => 1, 'Intermediate' => 2, 'Advanced' => 3, 'Expert' => 4];
        if (!empty($existingApplicantData['skills'])) {
            foreach ($existingApplicantData['skills'] as $es) {
                $k = strtolower(trim($es['skill_name'] ?? ''));
                if (!empty($k)) {
                    $mergedSkillsMap[$k] = $es;
                }
            }
        }
        foreach ($extractedSkills as $exs) {
            $k = strtolower(trim($exs['skill_name']));
            if (!isset($mergedSkillsMap[$k])) {
                $mergedSkillsMap[$k] = $exs;
            } else {
                // If extracted has higher proficiency, upgrade it
                $currProf = $mergedSkillsMap[$k]['proficiency'] ?? 'Intermediate';
                $newProf = $exs['proficiency'] ?? 'Intermediate';
                if (($profRank[$newProf] ?? 2) > ($profRank[$currProf] ?? 2)) {
                    $mergedSkillsMap[$k]['proficiency'] = $newProf;
                }
            }
        }
        $combinedSkills = array_values($mergedSkillsMap);

        // Fetch open jobs first so we can verify active postings for each career track
        require_once __DIR__ . '/CompetencyMatcher.php';
        $jobStmt = $pdo->query("SELECT * FROM jobs WHERE status = 'Open'");
        $jobs = $jobStmt->fetchAll() ?: [];

        // 4. Calculate Career Fits using combined skills
        $cpStmt = $pdo->query("SELECT * FROM career_paths");
        $careerPaths = $cpStmt->fetchAll() ?: [];
        $careerFits = [];

        $userSkillNames = array_map(function($s) {
            return strtolower($s['skill_name']);
        }, $combinedSkills);

        foreach ($careerPaths as $cp) {
            $reqSkills = array_map('trim', explode(',', $cp['core_skills']));
            $reqSkillsLower = array_map('strtolower', $reqSkills);
            $matched = [];
            $missing = [];

            foreach ($reqSkills as $rs) {
                $rsLower = strtolower($rs);
                $found = false;
                foreach ($userSkillNames as $us) {
                    if (strpos($us, $rsLower) !== false || strpos($rsLower, $us) !== false) {
                        $found = true;
                        $matched[] = $rs;
                        break;
                    }
                }
                if (!$found) {
                    $missing[] = $rs;
                }
            }

            $totalReq = max(count($reqSkills), 1);
            $matchPct = round((count($matched) / $totalReq) * 100, 1);

            // Check if there are active job postings on the platform for this career track
            $activeJobsCount = 0;
            $cpTitleLower = strtolower($cp['title']);

            foreach ($jobs as $jb) {
                $jTitle = strtolower($jb['title']);
                $jSkills = array_map('trim', explode(',', strtolower($jb['required_skills'] ?? '')));

                $isTitleMatch = (strpos($jTitle, $cpTitleLower) !== false || strpos($cpTitleLower, $jTitle) !== false);
                if (!$isTitleMatch) {
                    $cpKeywords = array_filter(explode(' ', preg_replace('/[^a-z0-9]/', ' ', $cpTitleLower)), function($w) {
                        return strlen($w) >= 4 && !in_array($w, ['with', 'from', 'lead', 'specialist', 'associate', 'junior', 'senior', 'role']);
                    });
                    foreach ($cpKeywords as $kw) {
                        if (strpos($jTitle, $kw) !== false) {
                            $isTitleMatch = true;
                            break;
                        }
                    }
                }

                $skillOverlap = count(array_intersect($reqSkillsLower, $jSkills));
                if ($isTitleMatch || $skillOverlap >= 2) {
                    $activeJobsCount++;
                }
            }

            $careerFits[] = [
                'title' => $cp['title'],
                'category' => $cp['category'],
                'match_score' => $matchPct,
                'matched_skills' => $matched,
                'missing_skills' => $missing,
                'growth_rate' => $cp['growth_rate'],
                'average_salary' => $cp['average_salary'],
                'certifications' => $cp['recommended_certifications'],
                'active_jobs_count' => $activeJobsCount
            ];
        }

        usort($careerFits, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        // 5. Calculate Job Fits against open postings using full competency profile
        $jobFits = [];

        $fullApplicantData = [
            'profile' => array_merge(
                $existingApplicantData['profile'] ?? [],
                [
                    'experience_years' => $experienceYears,
                    'education_level' => $educationLevel,
                    'raw_resume_text' => $text
                ]
            ),
            'skills' => $combinedSkills
        ];

        foreach ($jobs as $j) {
            $res = CompetencyMatcher::matchApplicantToJob($fullApplicantData, $j);
            $jobFits[] = [
                'id' => $j['id'],
                'title' => $j['title'],
                'company_name' => $j['company_name'],
                'location' => $j['location'],
                'salary_min' => $j['salary_min'],
                'salary_max' => $j['salary_max'],
                'match_score' => $res['match_score'],
                'rating_label' => $res['rating_label'],
                'matched_skills' => $res['matched_required_skills'],
                'missing_skills' => $res['missing_required_skills']
            ];
        }

        usort($jobFits, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        return [
            'extracted_skills' => $extractedSkills,
            'combined_skills' => $combinedSkills,
            'experience_years' => $experienceYears,
            'education_level' => $educationLevel,
            'career_fits' => $careerFits,
            'job_fits' => array_slice($jobFits, 0, 4)
        ];
    }
}
?>
