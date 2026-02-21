<?php
/**
 * AI Performance Management System
 * Provides AI-powered insights, analysis, and recommendations for performance reviews
 */

require_once 'ai_config.php';

// Database connection
if (!isset($conn)) {
    require_once 'dp.php';
}

/**
 * Generate AI Performance Insights for an employee
 */
function generatePerformanceInsights($employee_id, $review_cycle_id = null) {
    global $conn;
    
    try {
        // Get employee data
        $stmt = $conn->prepare("
            SELECT ep.*, pi.first_name, pi.last_name, jr.title, d.department_name
            FROM employee_profiles ep
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            LEFT JOIN departments d ON jr.department = d.department_name
            WHERE ep.employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            return ['error' => 'Employee not found'];
        }
        
        // Get performance metrics
        $stmt = $conn->prepare("
            SELECT * FROM performance_reviews 
            WHERE employee_id = ? 
            ".($review_cycle_id ? "AND review_cycle_id = ?" : "")."
            ORDER BY review_date DESC LIMIT 5
        ");
        $params = [$employee_id];
        if ($review_cycle_id) $params[] = $review_cycle_id;
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get 360 feedback if available
        $stmt = $conn->prepare("
            SELECT AVG(JSON_EXTRACT(responses, '$.leadership')) as leadership,
                   AVG(JSON_EXTRACT(responses, '$.communication')) as communication,
                   AVG(JSON_EXTRACT(responses, '$.teamwork')) as teamwork,
                   AVG(JSON_EXTRACT(responses, '$.problem_solving')) as problem_solving,
                   AVG(JSON_EXTRACT(responses, '$.work_quality')) as work_quality
            FROM feedback_responses fr
            LEFT JOIN feedback_requests freq ON fr.request_id = freq.request_id
            WHERE freq.employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get goals data
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_goals,
                   SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_goals,
                   AVG(progress) as avg_progress
            FROM goals
            WHERE employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $goals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Build AI prompt
        $prompt = buildPerformanceInsightPrompt($employee, $reviews, $feedback, $goals);
        
        // Call AI
        if (AI_PROVIDER === 'mock') {
            logAPIUsage('mock', 'performance_insights');
            return generateMockPerformanceInsights($employee, $reviews, $feedback, $goals);
        } elseif (AI_PROVIDER === 'gemini') {
            $result = callGeminiPerformance($prompt);
            if (isset($result['success']) && $result['success']) {
                logAPIUsage('gemini', 'performance_insights');
            }
            return $result;
        } else {
            $result = callOpenAIPerformance($prompt);
            if (isset($result['success']) && $result['success']) {
                logAPIUsage('openai', 'performance_insights');
            }
            return $result;
        }
        
    } catch (PDOException $e) {
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Generate AI-powered review feedback suggestions
 */
function generateReviewFeedback($employee_id, $review_type = 'general') {
    global $conn;
    
    try {
        // Get employee performance history
        $stmt = $conn->prepare("
            SELECT ep.*, pi.first_name, pi.last_name, jr.title
            FROM employee_profiles ep
            LEFT JOIN personal_information pi ON ep.personal_info_id = pi.personal_info_id
            LEFT JOIN job_roles jr ON ep.job_role_id = jr.job_role_id
            WHERE ep.employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent performance metrics
        $stmt = $conn->prepare("
            SELECT AVG(CAST(overall_rating AS DECIMAL(3,1))) as avg_rating,
                   STDDEV(CAST(overall_rating AS DECIMAL(3,1))) as rating_variance
            FROM performance_reviews
            WHERE employee_id = ?
            AND review_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        ");
        $stmt->execute([$employee_id]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get competency assessment
        $stmt = $conn->prepare("
            SELECT ec.competency_id, c.competency_name, 
                   AVG(ec.rating) as avg_rating
            FROM employee_competencies ec
            LEFT JOIN competencies c ON ec.competency_id = c.competency_id
            WHERE ec.employee_id = ?
            GROUP BY ec.competency_id, c.competency_name
            ORDER BY avg_rating DESC
        ");
        $stmt->execute([$employee_id]);
        $competencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $prompt = buildFeedbackPrompt($employee, $metrics, $competencies, $review_type);
        
        // Call AI
        if (AI_PROVIDER === 'mock') {
            logAPIUsage('mock', 'review_feedback');
            return generateMockReviewFeedback($employee, $metrics, $competencies);
        } elseif (AI_PROVIDER === 'gemini') {
            $result = callGeminiPerformance($prompt);
            if (isset($result['success']) && $result['success']) {
                logAPIUsage('gemini', 'review_feedback');
            }
            return $result;
        } else {
            $result = callOpenAIPerformance($prompt);
            if (isset($result['success']) && $result['success']) {
                logAPIUsage('openai', 'review_feedback');
            }
            return $result;
        }
        
    } catch (PDOException $e) {
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Predict performance trajectory
 */
function predictPerformanceTrend($employee_id) {
    global $conn;
    
    try {
        // Get performance history (last 24 months)
        $stmt = $conn->prepare("
            SELECT review_date, CAST(overall_rating AS DECIMAL(3,1)) as rating
            FROM performance_reviews
            WHERE employee_id = ?
            AND review_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
            ORDER BY review_date ASC
        ");
        $stmt->execute([$employee_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($history) < 2) {
            return ['status' => 'insufficient_data', 'message' => 'Not enough data for trend prediction'];
        }
        
        // Calculate trend
        $ratings = array_map(function($h) { return $h['rating']; }, $history);
        $first_third = array_slice($ratings, 0, ceil(count($ratings)/3));
        $last_third = array_slice($ratings, floor(2*count($ratings)/3));
        
        $first_avg = count($first_third) > 0 ? array_sum($first_third) / count($first_third) : 0;
        $last_avg = count($last_third) > 0 ? array_sum($last_third) / count($last_third) : 0;
        
        $trend = $last_avg > $first_avg ? 'improving' : ($last_avg < $first_avg ? 'declining' : 'stable');
        $trend_percentage = abs($last_avg - $first_avg);
        
        return [
            'trend' => $trend,
            'change_percentage' => round($trend_percentage, 1),
            'current_average' => round($last_avg, 1),
            'previous_average' => round($first_avg, 1),
            'data_points' => count($history)
        ];
        
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Analyze competency gaps
 */
function analyzeCompetencyGaps($employee_id, $job_role_id = null) {
    global $conn;
    
    try {
        if (!$job_role_id) {
            $stmt = $conn->prepare("SELECT job_role_id FROM employee_profiles WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $job_role_id = $result['job_role_id'] ?? null;
        }
        
        // Get required competencies for role
        $stmt = $conn->prepare("
            SELECT rc.competency_id, c.competency_name, rc.required_level
            FROM role_competencies rc
            LEFT JOIN competencies c ON rc.competency_id = c.competency_id
            WHERE rc.job_role_id = ?
            ORDER BY rc.required_level DESC
        ");
        $stmt->execute([$job_role_id]);
        $required = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get employee competencies
        $stmt = $conn->prepare("
            SELECT ec.competency_id, ec.rating
            FROM employee_competencies ec
            WHERE ec.employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $employee_comps = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $employee_comps[$row['competency_id']] = $row['rating'];
        }
        
        // Calculate gaps
        $gaps = [];
        foreach ($required as $req) {
            $current_rating = $employee_comps[$req['competency_id']] ?? 0;
            $gap = max(0, $req['required_level'] - $current_rating);
            
            if ($gap > 0) {
                $gaps[] = [
                    'competency' => $req['competency_name'],
                    'required_level' => $req['required_level'],
                    'current_level' => $current_rating,
                    'gap' => $gap,
                    'priority' => $gap >= 2 ? 'high' : 'medium'
                ];
            }
        }
        
        // Sort by gap size
        usort($gaps, function($a, $b) { return $b['gap'] <=> $a['gap']; });
        
        return [
            'gaps' => $gaps,
            'gap_count' => count($gaps),
            'high_priority_gaps' => count(array_filter($gaps, fn($g) => $g['priority'] === 'high'))
        ];
        
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Generate development recommendations
 */
function generateDevelopmentRecommendations($employee_id, $competency_gaps = null) {
    global $conn;
    
    try {
        if (!$competency_gaps) {
            $competency_gaps = analyzeCompetencyGaps($employee_id);
        }
        
        if (isset($competency_gaps['error'])) {
            return $competency_gaps;
        }
        
        // Get available training programs
        $stmt = $conn->prepare("
            SELECT ts.session_id, ts.title, ts.description, ts.start_date
            FROM training_sessions ts
            WHERE ts.start_date >= CURDATE()
            ORDER BY ts.start_date ASC
            LIMIT 10
        ");
        $stmt->execute();
        $trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $prompt = buildDevelopmentPrompt($employee_id, $competency_gaps, $trainings);
        
        // Call AI
        if (AI_PROVIDER === 'mock') {
            logAPIUsage('mock', 'development_recommendations');
            return generateMockDevelopmentPlan($competency_gaps, $trainings);
        } elseif (AI_PROVIDER === 'gemini') {
            $result = callGeminiPerformance($prompt);
            if (isset($result['success']) && $result['success']) {
                logAPIUsage('gemini', 'development_recommendations');
            }
            return $result;
        } else {
            $result = callOpenAIPerformance($prompt);
            if (isset($result['success']) && $result['success']) {
                logAPIUsage('openai', 'development_recommendations');
            }
            return $result;
        }
        
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Build performance insight prompt
 */
function buildPerformanceInsightPrompt($employee, $reviews, $feedback, $goals) {
    $prompt = "PERFORMANCE ANALYSIS REQUEST\n\n";
    
    $prompt .= "EMPLOYEE PROFILE:\n";
    $prompt .= "Name: {$employee['first_name']} {$employee['last_name']}\n";
    $prompt .= "Position: {$employee['title']}\n";
    $prompt .= "Department: {$employee['department_name']}\n";
    $prompt .= "Tenure: {$employee['date_of_joining']}\n\n";
    
    if (!empty($reviews)) {
        $prompt .= "RECENT PERFORMANCE REVIEWS:\n";
        foreach ($reviews as $review) {
            $prompt .= "- Date: {$review['review_date']}, Rating: {$review['overall_rating']}/5\n";
            if (!empty($review['summary'])) {
                $prompt .= "  Summary: " . substr($review['summary'], 0, 100) . "...\n";
            }
        }
    }
    
    if ($feedback) {
        $prompt .= "\n360-DEGREE FEEDBACK SCORES:\n";
        $prompt .= "- Leadership: {$feedback['leadership']}/5\n";
        $prompt .= "- Communication: {$feedback['communication']}/5\n";
        $prompt .= "- Teamwork: {$feedback['teamwork']}/5\n";
        $prompt .= "- Problem Solving: {$feedback['problem_solving']}/5\n";
        $prompt .= "- Work Quality: {$feedback['work_quality']}/5\n";
    }
    
    if ($goals) {
        $prompt .= "\nGOAL COMPLETION:\n";
        $prompt .= "- Total Goals: {$goals['total_goals']}\n";
        $prompt .= "- Completed: {$goals['completed_goals']}\n";
        $prompt .= "- Average Progress: {$goals['avg_progress']}%\n";
    }
    
    $prompt .= "\nProvide:\n";
    $prompt .= "1. Overall performance assessment\n";
    $prompt .= "2. Key strengths identified\n";
    $prompt .= "3. Areas for improvement\n";
    $prompt .= "4. Recommendations for growth\n";
    $prompt .= "5. Career path suggestions\n\n";
    $prompt .= "Format as JSON with keys: assessment, strengths, areas_for_improvement, recommendations, career_suggestions";
    
    return $prompt;
}

/**
 * Build feedback prompt
 */
function buildFeedbackPrompt($employee, $metrics, $competencies, $review_type) {
    $prompt = "GENERATE PERFORMANCE REVIEW FEEDBACK\n\n";
    
    $prompt .= "Employee: {$employee['first_name']} {$employee['last_name']}\n";
    $prompt .= "Position: {$employee['title']}\n";
    $prompt .= "Review Type: $review_type\n\n";
    
    if ($metrics) {
        $prompt .= "Performance Metrics:\n";
        $prompt .= "- Average Rating: {$metrics['avg_rating']}/5\n";
        $prompt .= "- Consistency: {$metrics['rating_variance']}\n\n";
    }
    
    if (!empty($competencies)) {
        $prompt .= "Top Competencies:\n";
        foreach (array_slice($competencies, 0, 5) as $comp) {
            $prompt .= "- {$comp['competency_name']}: {$comp['avg_rating']}/5\n";
        }
    }
    
    $prompt .= "\nGenerate professional, constructive feedback covering:\n";
    $prompt .= "1. Overall performance summary\n";
    $prompt .= "2. Specific accomplishments to highlight\n";
    $prompt .= "3. Development opportunities\n";
    $prompt .= "4. Goals for next period\n";
    $prompt .= "5. Encouragement and motivation\n\n";
    $prompt .= "Format as structured feedback with clear sections.";
    
    return $prompt;
}

/**
 * Build development prompt
 */
function buildDevelopmentPrompt($employee_id, $gaps, $trainings) {
    $prompt = "CREATE DEVELOPMENT PLAN\n\n";
    $prompt .= "Competency Gaps: " . count($gaps['gaps']) . " identified\n";
    
    if (!empty($gaps['gaps'])) {
        $prompt .= "Top Gaps:\n";
        foreach (array_slice($gaps['gaps'], 0, 3) as $gap) {
            $prompt .= "- {$gap['competency']}: Gap of {$gap['gap']} levels (Current: {$gap['current_level']}, Required: {$gap['required_level']})\n";
        }
    }
    
    $prompt .= "\nAvailable Training Programs:\n";
    foreach ($trainings as $training) {
        $prompt .= "- {$training['title']} ({$training['start_date']}): {$training['description']}\n";
    }
    
    $prompt .= "\nRecommend:\n";
    $prompt .= "1. Top 3 training programs to attend\n";
    $prompt .= "2. Mentoring opportunities\n";
    $prompt .= "3. On-the-job learning activities\n";
    $prompt .= "4. Timeline and milestones\n";
    $prompt .= "5. Success metrics\n\n";
    $prompt .= "Format as JSON with: recommended_trainings, mentoring, on_the_job_activities, timeline, metrics";
    
    return $prompt;
}

/**
 * Mock AI responses for testing
 */
function generateMockPerformanceInsights($employee, $reviews, $feedback, $goals) {
    return [
        'success' => true,
        'assessment' => "{$employee['first_name']} demonstrates solid performance in their role as a {$employee['title']} in the {$employee['department_name']} department.",
        'strengths' => [
            'Strong technical competency in core job functions',
            'Excellent collaboration and teamwork abilities',
            'Consistent goal achievement above expectations'
        ],
        'areas_for_improvement' => [
            'Leadership and delegation skills could be developed further',
            'Strategic thinking and long-term planning',
            'Industry knowledge expansion'
        ],
        'recommendations' => [
            'Consider leadership development program',
            'Enroll in advanced technical training',
            'Mentor junior team members for growth'
        ],
        'career_suggestions' => [
            'Potential for team lead position in 12-18 months',
            'Cross-functional project opportunity',
            'Specialization in emerging technologies'
        ]
    ];
}

function generateMockReviewFeedback($employee, $metrics, $competencies) {
    return [
        'success' => true,
        'feedback' => "Overall, {$employee['first_name']} has demonstrated commendable performance in the {$employee['title']} role. " .
                     "Their technical skills and collaborative approach have contributed positively to team objectives.",
        'accomplishments' => [
            'Successfully completed all assigned projects on schedule',
            'Improved team productivity by 15%',
            'Mentored 2 junior team members effectively'
        ],
        'development_opportunities' => [
            'Enhance public speaking skills',
            'Develop project management expertise',
            'Broaden industry knowledge'
        ],
        'goals_next_period' => [
            'Lead one major cross-functional project',
            'Complete advanced certification program',
            'Achieve 95% project completion rate'
        ]
    ];
}

function generateMockDevelopmentPlan($gaps, $trainings) {
    return [
        'success' => true,
        'recommended_trainings' => [
            'Leadership Essentials Course',
            'Advanced Project Management',
            'Strategic Planning Workshop'
        ],
        'mentoring' => [
            'Assign senior manager as mentor for 6 months',
            'Monthly 1-on-1 coaching sessions',
            'Peer mentoring with high-performer'
        ],
        'on_the_job_activities' => [
            'Lead team meeting facilitation',
            'Participate in strategic planning sessions',
            'Manage smaller team initiatives'
        ],
        'timeline' => [
            'Month 1-2: Complete online courses',
            'Month 3-4: Apply learning in real projects',
            'Month 5-6: Lead independent initiatives',
            'Month 6-12: Ongoing reinforcement'
        ],
        'metrics' => [
            'Competency assessment score increase by 1 level',
            'Project delivery time reduction by 20%',
            'Team feedback improvement of 30%',
            'Goal completion rate of 100%'
        ]
    ];
}

/**
 * Call Gemini API for performance analysis
 */
function callGeminiPerformance($prompt) {
    $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    
    if (empty($apiKey)) {
        return ['error' => 'Gemini API key not configured', 'success' => false];
    }
    
    $url = GEMINI_API_URL . '?key=' . $apiKey;
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 2000,
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        return ['error' => 'API request failed', 'success' => false];
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Try to parse JSON from response
        $json_match = null;
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $json_data = json_decode($matches[0], true);
            if ($json_data) {
                return array_merge(['success' => true], $json_data);
            }
        }
        
        return ['success' => true, 'analysis' => $text];
    }
    
    return ['error' => 'Invalid API response', 'success' => false];
}

/**
 * Call OpenAI API for performance analysis
 */
function callOpenAIPerformance($prompt) {
    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    
    if (empty($apiKey)) {
        return ['error' => 'OpenAI API key not configured', 'success' => false];
    }
    
    $payload = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert HR and performance management consultant providing detailed, actionable insights.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];
    
    $ch = curl_init(OPENAI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response === false) {
        return ['error' => 'API request failed', 'success' => false];
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        $text = $data['choices'][0]['message']['content'];
        
        // Try to parse JSON
        $json_match = null;
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            $json_data = json_decode($matches[0], true);
            if ($json_data) {
                return array_merge(['success' => true], $json_data);
            }
        }
        
        return ['success' => true, 'analysis' => $text];
    }
    
    return ['error' => 'Invalid API response', 'success' => false];
}

?>
