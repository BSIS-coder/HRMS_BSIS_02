<?php
// Include database connection
require_once 'config.php';

try {
    // Insert sample benefit plans
    $benefit_plans = [
        [
            'plan_name' => 'Comprehensive Health Insurance',
            'plan_type' => 'Health',
            'description' => 'Complete health coverage including hospitalization, outpatient care, dental, and vision benefits.',
            'eligibility_criteria' => 'All full-time employees and their dependents. Must complete 3-month probation period.'
        ],
        [
            'plan_name' => 'Retirement Savings Plan',
            'plan_type' => 'Retirement',
            'description' => '401(k) retirement plan with employer matching up to 5% of salary.',
            'eligibility_criteria' => 'All employees aged 18+. No waiting period for enrollment.'
        ],
        [
            'plan_name' => 'Life Insurance Coverage',
            'plan_type' => 'Insurance',
            'description' => 'Term life insurance coverage equal to 2x annual salary.',
            'eligibility_criteria' => 'All full-time employees. Coverage begins after 30-day waiting period.'
        ],
        [
            'plan_name' => 'Annual Leave Allowance',
            'plan_type' => 'Other',
            'description' => '15 days of paid annual leave per year, accumulating monthly.',
            'eligibility_criteria' => 'All employees. Leave accrues from date of hire.'
        ],
        [
            'plan_name' => 'Professional Development Fund',
            'plan_type' => 'Other',
            'description' => 'Up to ₱50,000 annually for training, conferences, and certifications.',
            'eligibility_criteria' => 'All employees with satisfactory performance rating.'
        ],
        [
            'plan_name' => 'Flexible Work Arrangement',
            'plan_type' => 'Other',
            'description' => 'Option for remote work, flexible hours, or compressed workweek.',
            'eligibility_criteria' => 'Employees with 6+ months tenure and manager approval.'
        ],
        [
            'plan_name' => 'Employee Assistance Program',
            'plan_type' => 'Other',
            'description' => '24/7 confidential counseling and support services.',
            'eligibility_criteria' => 'All employees and their immediate family members.'
        ],
        [
            'plan_name' => 'Transportation Allowance',
            'plan_type' => 'Other',
            'description' => 'Monthly transportation allowance of ₱2,500 for commuting expenses.',
            'eligibility_criteria' => 'All employees working from office locations.'
        ]
    ];

    $sql = "INSERT INTO benefits_plans (plan_name, plan_type, description, eligibility_criteria) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    foreach ($benefit_plans as $plan) {
        $stmt->execute([
            $plan['plan_name'],
            $plan['plan_type'],
            $plan['description'],
            $plan['eligibility_criteria']
        ]);
    }

    echo "<div class='alert alert-success'>";
    echo "<h4>✅ Benefit Plans Created Successfully!</h4>";
    echo "<p>Created " . count($benefit_plans) . " benefit plans:</p>";
    echo "<ul>";
    foreach ($benefit_plans as $plan) {
        echo "<li><strong>" . htmlspecialchars($plan['plan_name']) . "</strong> (" . htmlspecialchars($plan['plan_type']) . ")</li>";
    }
    echo "</ul>";
    echo "<p><a href='benefit_plans.php' class='btn btn-primary'>View Benefit Plans</a></p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Error Creating Benefit Plans</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
