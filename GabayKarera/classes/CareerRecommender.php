<?php
// classes/CareerRecommender.php

class CareerRecommender {
    
    /**
     * Recommends top career paths based on applicant skills, education, and interests
     */
    public static function recommendCareers($pdo, $userId) {
        // Fetch applicant data
        $stmt = $pdo->prepare("SELECT * FROM applicant_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch() ?: [];

        $skillStmt = $pdo->prepare("SELECT * FROM applicant_skills WHERE user_id = ?");
        $skillStmt->execute([$userId]);
        $skills = $skillStmt->fetchAll() ?: [];

        // Fetch all career pathways
        $cpStmt = $pdo->query("SELECT * FROM career_paths");
        $careerPaths = $cpStmt->fetchAll();

        $userSkillNames = array_map(function($s) {
            return strtolower(trim($s['skill_name']));
        }, $skills);

        $userInterests = strtolower($profile['interests'] ?? '');
        $userBio = strtolower($profile['bio'] ?? '');
        $userResume = strtolower($profile['raw_resume_text'] ?? '');
        $userHistory = strtolower($profile['work_history'] ?? '');
        $userCerts = strtolower($profile['certifications'] ?? '');
        $userExtraText = $userInterests . ' ' . $userBio . ' ' . $userResume . ' ' . $userHistory . ' ' . $userCerts;

        // Build skill lookup map with proficiency weighting
        $appSkillMap = [];
        $profWeights = ['Beginner' => 0.65, 'Intermediate' => 0.85, 'Advanced' => 0.95, 'Expert' => 1.0];
        foreach ($skills as $s) {
            $sName = is_array($s) ? ($s['skill_name'] ?? '') : (string)$s;
            $sProf = is_array($s) ? ($s['proficiency'] ?? 'Intermediate') : 'Intermediate';
            $sKey = strtolower(trim($sName));
            if (!empty($sKey)) {
                $appSkillMap[$sKey] = [
                    'original' => $sName,
                    'proficiency' => $sProf,
                    'weight' => $profWeights[$sProf] ?? 0.85
                ];
            }
        }

        $recommendations = [];

        foreach ($careerPaths as $cp) {
            $requiredSkills = array_map('trim', explode(',', $cp['core_skills']));
            $reqScoreSum = 0;
            $matchedSkillsList = [];
            $missingSkillsList = [];

            foreach ($requiredSkills as $req) {
                $reqLower = strtolower($req);
                $found = false;

                // 1. Direct or partial registered skill match
                foreach ($appSkillMap as $usKey => $sInfo) {
                    if ($usKey === $reqLower || (strlen($usKey) >= 3 && strlen($reqLower) >= 3 && (strpos($usKey, $reqLower) !== false || strpos($reqLower, $usKey) !== false))) {
                        $found = true;
                        $matchedSkillsList[] = $req . ' (' . $sInfo['proficiency'] . ')';
                        $reqScoreSum += $sInfo['weight'];
                        break;
                    }
                }

                // 2. Fallback: scan resume, work history, certifications, bio, and interests
                if (!$found) {
                    if (strpos($userExtraText, $reqLower) !== false) {
                        $matchedSkillsList[] = $req . ' (Resume/Experience)';
                        $reqScoreSum += 0.75;
                        $found = true;
                    }
                }

                if (!$found) {
                    $missingSkillsList[] = $req;
                }
            }

            $totalReq = max(count($requiredSkills), 1);
            $skillMatchPct = ($reqScoreSum / $totalReq) * 100;
            
            // Relevancy boost based on interests or field of study
            $interestBoost = 0;
            $fieldOfStudy = strtolower($profile['field_of_study'] ?? '');
            if (strpos($userInterests, strtolower($cp['category'])) !== false || strpos($userInterests, strtolower($cp['title'])) !== false) {
                $interestBoost += 6;
            }
            if (!empty($fieldOfStudy) && (strpos($fieldOfStudy, strtolower($cp['category'])) !== false || strpos(strtolower($cp['description']), $fieldOfStudy) !== false)) {
                $interestBoost += 4;
            }

            $finalScore = min(98.5, max(15.0, round($skillMatchPct + $interestBoost, 1)));

            $recommendations[] = [
                'id' => $cp['id'],
                'title' => $cp['title'],
                'category' => $cp['category'],
                'description' => $cp['description'],
                'average_salary' => $cp['average_salary'],
                'growth_rate' => $cp['growth_rate'],
                'recommended_certifications' => $cp['recommended_certifications'],
                'match_score' => $finalScore,
                'matched_skills' => $matchedSkillsList,
                'missing_skills' => $missingSkillsList,
                'total_skills_count' => count($requiredSkills)
            ];
        }

        // Sort by match score descending
        usort($recommendations, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        return $recommendations;
    }
}
?>
