<?php
// classes/CompetencyMatcher.php

class CompetencyMatcher {
    
    /**
     * Compute comprehensive competency match between an applicant and a job
     * 
     * @param array $applicantData ['profile' => ..., 'skills' => [...]]
     * @param array $jobData ['required_skills' => ..., 'preferred_skills' => ..., 'experience_required' => ...]
     * @return array Multi-dimensional matching result with scores, gaps, and insights
     */
    public static function matchApplicantToJob($applicantData, $jobData) {
        $applicantSkills = $applicantData['skills'] ?? [];
        $applicantProfile = $applicantData['profile'] ?? [];
        
        // 1. Parse Job Required & Preferred Skills
        $requiredSkills = self::parseSkillString($jobData['required_skills'] ?? '');
        $preferredSkills = self::parseSkillString($jobData['preferred_skills'] ?? '');
        
        // 2. Build applicant skill map with proficiency weighting
        $appSkillMap = [];
        $proficiencyWeights = [
            'Beginner' => 0.60,
            'Intermediate' => 0.85,
            'Advanced' => 0.95,
            'Expert' => 1.00
        ];
        
        foreach ($applicantSkills as $s) {
            $sName = is_array($s) ? ($s['skill_name'] ?? '') : (string)$s;
            $sProf = is_array($s) ? ($s['proficiency'] ?? 'Intermediate') : 'Intermediate';
            $sKey = strtolower(trim($sName));
            if (!empty($sKey)) {
                $appSkillMap[$sKey] = [
                    'original' => $sName,
                    'proficiency' => $sProf,
                    'weight' => $proficiencyWeights[$sProf] ?? 0.80,
                    'category' => is_array($s) ? ($s['category'] ?? 'Technical') : 'Technical'
                ];
            }
        }

        // Also extract from raw resume text, bio, interests, work history, and certifications
        $extraText = strtolower(
            ($applicantProfile['raw_resume_text'] ?? '') . ' ' . 
            ($applicantProfile['bio'] ?? '') . ' ' . 
            ($applicantProfile['interests'] ?? '') . ' ' . 
            ($applicantProfile['work_history'] ?? '') . ' ' . 
            ($applicantProfile['certifications'] ?? '')
        );

        // 3. Evaluate Required Skills
        $matchedRequired = [];
        $missingRequired = [];
        $reqScoreSum = 0;
        $totalReqWeight = max(count($requiredSkills), 1);

        foreach ($requiredSkills as $req) {
            $reqKey = strtolower(trim($req));
            $found = false;

            // Direct map lookup
            if (isset($appSkillMap[$reqKey])) {
                $matchedRequired[] = [
                    'skill' => $appSkillMap[$reqKey]['original'],
                    'proficiency' => $appSkillMap[$reqKey]['proficiency'],
                    'type' => 'Required'
                ];
                $reqScoreSum += $appSkillMap[$reqKey]['weight'];
                $found = true;
            } else {
                // Partial string/keyword match check (for skills with >= 3 characters)
                foreach ($appSkillMap as $userKey => $val) {
                    if (strlen($userKey) >= 3 && strlen($reqKey) >= 3 && (strpos($userKey, $reqKey) !== false || strpos($reqKey, $userKey) !== false)) {
                        $matchedRequired[] = [
                            'skill' => $val['original'] . ' (related to ' . $req . ')',
                            'proficiency' => $val['proficiency'],
                            'type' => 'Required'
                        ];
                        $reqScoreSum += ($val['weight'] * 0.85);
                        $found = true;
                        break;
                    }
                }

                // Check in raw resume text if still not found
                if (!$found && !empty($reqKey) && strpos($extraText, $reqKey) !== false) {
                    $matchedRequired[] = [
                        'skill' => $req . ' (identified from resume/bio)',
                        'proficiency' => 'Intermediate',
                        'type' => 'Required'
                    ];
                    $reqScoreSum += 0.75;
                    $found = true;
                }
            }

            if (!$found && !empty($req)) {
                $missingRequired[] = $req;
            }
        }

        if (empty($requiredSkills)) {
            $requiredSkillPercentage = 100.0;
        } else {
            $requiredSkillPercentage = ($reqScoreSum / $totalReqWeight) * 100;
            $requiredSkillPercentage = min(100, max(0, $requiredSkillPercentage));
        }

        // 4. Evaluate Preferred / Nice-to-Have Skills
        $matchedPreferred = [];
        $missingPreferred = [];
        $prefBonus = 0;
        if (!empty($preferredSkills)) {
            $prefMatchCount = 0;
            foreach ($preferredSkills as $pref) {
                $prefKey = strtolower(trim($pref));
                $found = false;
                if (isset($appSkillMap[$prefKey])) {
                    $matchedPreferred[] = $appSkillMap[$prefKey]['original'];
                    $prefMatchCount++;
                    $found = true;
                } else if (strpos($extraText, $prefKey) !== false) {
                    $matchedPreferred[] = $pref;
                    $prefMatchCount++;
                    $found = true;
                }
                if (!$found && !empty($pref)) {
                    $missingPreferred[] = $pref;
                }
            }
            $prefBonus = ($prefMatchCount / count($preferredSkills)) * 10; // up to 10% bonus
        }

        // 5. Experience Alignment Score
        $reqExp = (float)($jobData['experience_required'] ?? 0.0);
        $appExp = (float)($applicantProfile['experience_years'] ?? 0.0);
        
        $expScore = 100;
        if ($reqExp > 0) {
            if ($appExp >= $reqExp) {
                $expScore = 100;
            } else {
                $expScore = max(40, ($appExp / $reqExp) * 100);
            }
        }

        // 6. Education / Domain Match
        $eduScore = 85; // baseline
        if (!empty($applicantProfile['field_of_study'])) {
            $eduScore = 95;
        }

        // 7. Weighted Composite Score
        // Skill Match (55%), Experience (25%), Education/Domain (15%), Preferred Bonus (5%)
        $compositeScore = ($requiredSkillPercentage * 0.55) + ($expScore * 0.25) + ($eduScore * 0.15) + $prefBonus;
        $compositeScore = round(min(99.5, max(15.0, $compositeScore)), 1);

        // 8. Verdict & Skill Gap Recommendations
        $verdict = self::generateVerdict($compositeScore);
        $gapRecommendations = self::generateGapRecommendations($missingRequired, $jobData['title'] ?? 'Role');

        // 9. Radar Chart Competency Dimensions (0-100 scale)
        $radarMetrics = [
            'Technical Competency' => round(min(100, $requiredSkillPercentage * 1.05)),
            'Domain Alignment' => round(min(100, $eduScore)),
            'Experience Fit' => round(min(100, $expScore)),
            'Growth Readiness' => round(min(100, max(50, 100 - (count($missingRequired) * 12)))),
            'Tool Proficiency' => round(min(100, count($applicantSkills) * 11 + 20))
        ];

        return [
            'match_score' => $compositeScore,
            'recommendation' => $verdict,
            'verdict' => $verdict,
            'rating_label' => self::getScoreBadgeLabel($compositeScore),
            'rating_badge_class' => self::getScoreBadgeClass($compositeScore),
            'skill_match_percentage' => round($requiredSkillPercentage, 1),
            'experience_score' => round($expScore, 1),
            'matched_required_skills' => $matchedRequired,
            'missing_required_skills' => $missingRequired,
            'matched_preferred_skills' => $matchedPreferred,
            'missing_preferred_skills' => $missingPreferred,
            'gap_recommendations' => $gapRecommendations,
            'radar_metrics' => $radarMetrics
        ];
    }

    /**
     * Format a numerical match score into a clean percentage string (e.g. 88.8% or 94%)
     */
    public static function formatScore($score) {
        $num = round((float)$score, 1);
        return (floor($num) == $num ? (int)$num : $num) . '%';
    }

    /**
     * Automatically synchronize all active job applications for an applicant
     * Ensures HR and the Client ALWAYS see the exact same fit percentage and radar breakdown
     */
    public static function syncApplicantApplications($pdo, $applicantId) {
        $applicantId = (int)$applicantId;
        if ($applicantId <= 0) return;

        // Fetch latest applicant profile and skills
        $pStmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
        $pStmt->execute([$applicantId]);
        $profile = $pStmt->fetch() ?: [];

        $sStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ?");
        $sStmt->execute([$applicantId]);
        $skills = $sStmt->fetchAll() ?: [];

        $applicantData = ['profile' => $profile, 'skills' => $skills];

        // Fetch all applications submitted by this applicant
        $aStmt = $pdo->prepare("
            SELECT a.id as application_id, a.job_id, j.* 
            FROM applications a 
            JOIN jobs j ON a.job_id = j.id 
            WHERE a.applicant_id = ?
        ");
        $aStmt->execute([$applicantId]);
        $userApps = $aStmt->fetchAll();

        if (empty($userApps)) return;

        $uStmt = $pdo->prepare("UPDATE applications SET match_score = ?, competency_breakdown = ? WHERE id = ?");
        foreach ($userApps as $ua) {
            $match = self::matchApplicantToJob($applicantData, $ua);
            $uStmt->execute([
                $match['match_score'],
                json_encode($match),
                $ua['application_id']
            ]);
        }
    }

    private static function parseSkillString($str) {
        if (empty($str)) return [];
        // Support JSON format or comma/newline separated
        if (strpos($str, '[') === 0) {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) return $decoded;
        }
        $parts = preg_split('/[,;\n]+/', $str);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    private static function generateVerdict($score) {
        if ($score >= 88) {
            return "Exceptional Fit — The applicant meets or exceeds core competencies and required domain expertise.";
        } elseif ($score >= 75) {
            return "Strong Match — Well-qualified candidate with high competency alignment and minimal skill gaps.";
        } elseif ($score >= 60) {
            return "Moderate Potential — Meets fundamental criteria; recommended for upskilling in a few key competencies.";
        } else {
            return "Developing Candidate — Substantial skill gaps present; foundational upskilling suggested prior to role placement.";
        }
    }

    public static function getScoreBadgeLabel($score) {
        if ($score >= 88) return 'Top Match';
        if ($score >= 75) return 'Strong Fit';
        if ($score >= 60) return 'Good Match';
        return 'Needs Upskilling';
    }

    public static function getScoreBadgeClass($score) {
        if ($score >= 88) return 'badge-success-glow';
        if ($score >= 75) return 'badge-info-glow';
        if ($score >= 60) return 'badge-warning-glow';
        return 'badge-secondary-glow';
    }

    private static function generateGapRecommendations($missingSkills, $jobTitle) {
        $recommendations = [];
        foreach ($missingSkills as $skill) {
            $recommendations[] = [
                'skill' => $skill,
                'importance' => 'High Priority',
                'action' => "Complete a project or online certification focused on {$skill} to strengthen your qualification for {$jobTitle} roles.",
                'learning_paths' => [
                    "FreeCodeCamp / Coursera: {$skill} Mastery Course",
                    "Build a portfolio project demonstrating practical use of {$skill}"
                ]
            ];
        }
        return $recommendations;
    }
}
?>
