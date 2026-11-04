<?php
/**
 * GabayKarera AI — CompetencyMatcher test cases
 *
 * Run: C:\xampp\php\php.exe tests\CompetencyMatcherTest.php
 */

require_once __DIR__ . '/../classes/CompetencyMatcher.php';

final class CompetencyMatcherTestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): int
    {
        $this->testStrongWebDeveloperMatch();
        $this->testMissingRequiredSkillsGeneratesGaps();
        $this->testResumeTextSkillFallback();
        $this->testExperienceShortfall();
        $this->testPreferredSkillsBonus();
        $this->testFormatScoreAndBadgeTiers();
        $this->testSkillProficiencyChangeRecalculatesScore();
        $this->testWorkHistoryCertificationsExtraction();

        echo str_repeat('-', 60) . PHP_EOL;
        echo "Passed: {$this->passed}, Failed: {$this->failed}" . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }

    /** TC-CM-01: Full alignment with Web Development taxonomy (framework §3). */
    private function testStrongWebDeveloperMatch(): void
    {
        $applicant = [
            'profile' => [
                'experience_years' => 3,
                'field_of_study' => 'Computer Science',
                'bio' => '',
                'interests' => '',
                'raw_resume_text' => '',
            ],
            'skills' => [
                ['skill_name' => 'PHP', 'proficiency' => 'Expert', 'category' => 'Technical'],
                ['skill_name' => 'JavaScript', 'proficiency' => 'Advanced', 'category' => 'Technical'],
                ['skill_name' => 'MySQL', 'proficiency' => 'Intermediate', 'category' => 'Technical'],
            ],
        ];

        $job = [
            'title' => 'Junior Web Developer',
            'required_skills' => 'PHP, JavaScript, MySQL',
            'preferred_skills' => 'React, Git',
            'experience_required' => 2,
        ];

        $result = CompetencyMatcher::matchApplicantToJob($applicant, $job);

        $this->assertTrue(
            $result['match_score'] >= 75,
            'TC-CM-01: strong applicant should score at least 75 (Strong Fit tier)'
        );
        $this->assertSame('Top Match', CompetencyMatcher::getScoreBadgeLabel($result['match_score']), 'TC-CM-01: badge tier');
        $this->assertCount(0, $result['missing_required_skills'], 'TC-CM-01: no missing required skills');
        $this->assertSame(93.3, $result['skill_match_percentage'], 'TC-CM-01: weighted required skill coverage');
        $this->assertStringContainsString('Exceptional Fit', $result['verdict'], 'TC-CM-01: verdict text');
    }

    /** TC-CM-02: Applicant lacks core requirements — gaps and upskilling verdict. */
    private function testMissingRequiredSkillsGeneratesGaps(): void
    {
        $applicant = [
            'profile' => ['experience_years' => 0],
            'skills' => [
                ['skill_name' => 'Excel', 'proficiency' => 'Beginner'],
            ],
        ];

        $job = [
            'title' => 'Data Analyst',
            'required_skills' => 'Python, SQL, Power BI',
            'preferred_skills' => '',
            'experience_required' => 1,
        ];

        $result = CompetencyMatcher::matchApplicantToJob($applicant, $job);

        $this->assertCount(3, $result['missing_required_skills'], 'TC-CM-02: all three required skills missing');
        $this->assertCount(3, $result['gap_recommendations'], 'TC-CM-02: one gap recommendation per missing skill');
        $this->assertSame('Needs Upskilling', CompetencyMatcher::getScoreBadgeLabel($result['match_score']));
        $this->assertTrue(
            $result['match_score'] < 60,
            'TC-CM-02: score should fall below Good Match threshold'
        );
    }

    /** TC-CM-03: Skill detected from resume text when not listed in applicant_skills. */
    private function testResumeTextSkillFallback(): void
    {
        $applicant = [
            'profile' => [
                'experience_years' => 1,
                'raw_resume_text' => 'Built dashboards using power bi and cleaned datasets.',
            ],
            'skills' => [],
        ];

        $job = [
            'title' => 'Reporting Analyst',
            'required_skills' => 'Power BI',
            'preferred_skills' => '',
            'experience_required' => 0,
        ];

        $result = CompetencyMatcher::matchApplicantToJob($applicant, $job);

        $this->assertCount(0, $result['missing_required_skills'], 'TC-CM-03: Power BI found in resume');
        $this->assertNotEmpty($result['matched_required_skills'], 'TC-CM-03: matched entry recorded');
        $this->assertStringContainsString(
            'resume',
            strtolower($result['matched_required_skills'][0]['skill']),
            'TC-CM-03: match source indicates resume/bio'
        );
    }

    /** TC-CM-04: Experience weighting (25%) when applicant is below required years. */
    private function testExperienceShortfall(): void
    {
        $applicant = [
            'profile' => ['experience_years' => 1, 'field_of_study' => 'IT'],
            'skills' => [
                ['skill_name' => 'PHP', 'proficiency' => 'Expert'],
            ],
        ];

        $job = [
            'title' => 'Senior PHP Developer',
            'required_skills' => 'PHP',
            'preferred_skills' => '',
            'experience_required' => 5,
        ];

        $result = CompetencyMatcher::matchApplicantToJob($applicant, $job);

        $this->assertSame(40.0, $result['experience_score'], 'TC-CM-04: experience floor at 40% when underqualified');
        $this->assertTrue($result['match_score'] < 88, 'TC-CM-04: not an Exceptional Fit due to experience gap');
    }

    /** TC-CM-05: Preferred / complementary skills contribute up to 5% composite bonus. */
    private function testPreferredSkillsBonus(): void
    {
        $baseApplicant = [
            'profile' => ['experience_years' => 2, 'field_of_study' => 'CS'],
            'skills' => [
                ['skill_name' => 'PHP', 'proficiency' => 'Intermediate'],
            ],
        ];

        $job = [
            'title' => 'Web Developer',
            'required_skills' => 'PHP',
            'preferred_skills' => 'React, Git',
            'experience_required' => 1,
        ];

        $withoutPreferred = CompetencyMatcher::matchApplicantToJob($baseApplicant, $job);

        $withPreferred = CompetencyMatcher::matchApplicantToJob([
            'profile' => $baseApplicant['profile'],
            'skills' => [
                ['skill_name' => 'PHP', 'proficiency' => 'Intermediate'],
                ['skill_name' => 'React', 'proficiency' => 'Intermediate'],
                ['skill_name' => 'Git', 'proficiency' => 'Intermediate'],
            ],
        ], $job);

        $this->assertCount(2, $withPreferred['matched_preferred_skills'], 'TC-CM-05: both preferred skills matched');
        $this->assertTrue(
            $withPreferred['match_score'] > $withoutPreferred['match_score'],
            'TC-CM-05: preferred matches increase composite score'
        );
    }

    /** TC-CM-06: Presentation helpers for dashboards and HR views. */
    private function testFormatScoreAndBadgeTiers(): void
    {
        $this->assertSame('94%', CompetencyMatcher::formatScore(94.0));
        $this->assertSame('88.5%', CompetencyMatcher::formatScore(88.5));
        $this->assertSame('Top Match', CompetencyMatcher::getScoreBadgeLabel(90));
        $this->assertSame('badge-success-glow', CompetencyMatcher::getScoreBadgeClass(90));
        $this->assertSame('badge-secondary-glow', CompetencyMatcher::getScoreBadgeClass(50));
    }

    /** TC-CM-07: Verifies that changing skill proficiency directly changes the match percentage. */
    private function testSkillProficiencyChangeRecalculatesScore(): void
    {
        $job = [
            'title' => 'Backend Developer',
            'required_skills' => 'PHP, MySQL',
            'preferred_skills' => '',
            'experience_required' => 2,
        ];

        // Beginner applicant
        $beginnerApp = [
            'profile' => ['experience_years' => 2, 'field_of_study' => 'IT'],
            'skills' => [
                ['skill_name' => 'PHP', 'proficiency' => 'Beginner'],
                ['skill_name' => 'MySQL', 'proficiency' => 'Beginner'],
            ],
        ];

        // Expert applicant
        $expertApp = [
            'profile' => ['experience_years' => 2, 'field_of_study' => 'IT'],
            'skills' => [
                ['skill_name' => 'PHP', 'proficiency' => 'Expert'],
                ['skill_name' => 'MySQL', 'proficiency' => 'Expert'],
            ],
        ];

        $beginnerRes = CompetencyMatcher::matchApplicantToJob($beginnerApp, $job);
        $expertRes = CompetencyMatcher::matchApplicantToJob($expertApp, $job);

        $this->assertTrue(
            $expertRes['match_score'] > $beginnerRes['match_score'],
            'TC-CM-07: upgrading proficiency from Beginner to Expert increases match score'
        );
        $this->assertSame(60.0, $beginnerRes['skill_match_percentage'], 'TC-CM-07: beginner skills have 60% coverage');
        $this->assertSame(100.0, $expertRes['skill_match_percentage'], 'TC-CM-07: expert skills have 100% coverage');
    }

    /** TC-CM-08: Verifies that work history and certifications text are recognized by competency engine. */
    private function testWorkHistoryCertificationsExtraction(): void
    {
        $job = [
            'title' => 'DevOps Specialist',
            'required_skills' => 'Docker, AWS',
            'preferred_skills' => '',
            'experience_required' => 1,
        ];

        $appWithHistoryAndCerts = [
            'profile' => [
                'experience_years' => 2,
                'field_of_study' => 'CS',
                'raw_resume_text' => '',
                'bio' => '',
                'interests' => '',
                'work_history' => 'Managed containerized deployments with Docker across production clusters.',
                'certifications' => 'AWS Certified Solutions Architect Associate (2024)',
            ],
            'skills' => [],
        ];

        $res = CompetencyMatcher::matchApplicantToJob($appWithHistoryAndCerts, $job);

        $this->assertCount(0, $res['missing_required_skills'], 'TC-CM-08: Docker and AWS recognized from work history and certs');
        $this->assertCount(2, $res['matched_required_skills'], 'TC-CM-08: both competencies matched');
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "[PASS] {$message}" . PHP_EOL;
            return;
        }
        $this->failed++;
        echo "[FAIL] {$message}" . PHP_EOL;
    }

    private function assertSame($expected, $actual, string $message = ''): void
    {
        $label = $message !== '' ? $message : ('assertSame(' . var_export($expected, true) . ', ' . var_export($actual, true) . ')');
        if ($expected === $actual) {
            $this->passed++;
            echo "[PASS] {$label}" . PHP_EOL;
            return;
        }
        $this->failed++;
        echo "[FAIL] {$label} (expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . ')' . PHP_EOL;
    }

    private function assertCount(int $expected, array $actual, string $message): void
    {
        $this->assertSame($expected, count($actual), $message);
    }

    private function assertNotEmpty(array $actual, string $message): void
    {
        $this->assertTrue(!empty($actual), $message);
    }

    private function assertStringContainsString(string $needle, string $haystack, string $message): void
    {
        $this->assertTrue(strpos($haystack, $needle) !== false, $message);
    }
}

exit((new CompetencyMatcherTestRunner())->run());
