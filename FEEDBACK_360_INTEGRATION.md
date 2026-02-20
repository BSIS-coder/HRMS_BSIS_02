# 360-Degree Feedback System - HR System Integration Guide

## Overview
The 360-Degree Feedback module has been successfully integrated into the HR system. This document outlines all integration points and how to use the feedback system across different modules.

## Integration Points

### 1. **Navigation & Sidebar**
The feedback module is already linked in the main sidebar under the **Performance** section:
- **URL**: `feedback_360.php`
- **Icon**: `fas fa-comment-dots`
- **Access**: HR, Admin, and Manager roles

### 2. **Database Tables**
Created (or uses existing) these tables:
- `feedback_cycles` - Feedback evaluation periods
- `feedback_requests` - Feedback requests to reviewers
- `feedback_responses` - Submitted feedback data

### 3. **Helper Functions Integration**
Include the integration helper in any file that needs feedback data:

```php
require_once 'feedback_360_integration.php';

// Get feedback overview for an employee
$feedback_overview = getEmployeeFeedbackOverview($employee_id);

// Get pending feedback count for current user
$pending_count = getUserPendingFeedbackCount($_SESSION['user_id']);

// Get average feedback score
$score = getEmployeeFeedbackScore($employee_id);

// Display feedback badge
echo displayFeedbackBadge($employee_id);
```

## Available Functions

### Employee-Related Functions

#### `getEmployeeFeedbackOverview($employee_id)`
Returns feedback statistics for an employee:
```php
[
    'total_cycles' => 3,
    'pending_count' => 2,
    'completed_count' => 1,
    'last_feedback_date' => '2026-02-19 10:30:00'
]
```

#### `getEmployeeFeedbackScore($employee_id)`
Returns average feedback score (0-5):
```php
$score = getEmployeeFeedbackScore(5); // Returns 4.2
```

#### `displayFeedbackBadge($employee_id)`
Returns HTML badge for employee profile:
```php
echo displayFeedbackBadge($employee_id);
// Output: <span class="badge badge-success">Feedback: 4.2/5</span>
```

#### `exportFeedbackData($employee_id, $cycle_id = null)`
Exports feedback data for reports:
```php
$data = exportFeedbackData($employee_id, $cycle_id);
// Returns array of all feedback for employee
```

### System-Wide Functions

#### `getActiveFeedbackCycles()`
Returns all active feedback cycles:
```php
$cycles = getActiveFeedbackCycles();
```

#### `getUserPendingFeedbackCount($user_id)`
Returns count of pending feedback for a user:
```php
$count = getUserPendingFeedbackCount($user_id);
```

#### `getRecentFeedbackForDashboard($limit = 5)`
Returns recent feedback activities for dashboard:
```php
$activities = getRecentFeedbackForDashboard(5);
```

#### `getFeedbackComparison($employee_ids)`
Compares feedback scores for multiple employees:
```php
$comparison = getFeedbackComparison([1, 2, 3, 4, 5]);
```

## Integration Examples

### 1. Add Feedback Widget to Employee Profile
In `employee_profile.php`, add:

```php
<?php
require_once 'feedback_360_integration.php';
$feedback_overview = getEmployeeFeedbackOverview($employee_id);
?>

<!-- Feedback Section -->
<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">360-Degree Feedback</h5>
    </div>
    <div class="card-body">
        <?php if ($feedback_overview): ?>
            <div class="row">
                <div class="col-md-3">
                    <h6>Total Cycles</h6>
                    <p class="h4"><?= $feedback_overview['total_cycles']; ?></p>
                </div>
                <div class="col-md-3">
                    <h6>Pending</h6>
                    <p class="h4 text-warning"><?= $feedback_overview['pending_count']; ?></p>
                </div>
                <div class="col-md-3">
                    <h6>Completed</h6>
                    <p class="h4 text-success"><?= $feedback_overview['completed_count']; ?></p>
                </div>
                <div class="col-md-3">
                    <h6>Score</h6>
                    <p class="h4"><?= number_format(getEmployeeFeedbackScore($employee_id), 1); ?>/5</p>
                </div>
            </div>
            <a href="feedback_360.php" class="btn btn-primary mt-3">
                <i class="fas fa-comments"></i> View Full Feedback
            </a>
        <?php else: ?>
            <p class="text-muted">No feedback data available yet.</p>
        <?php endif; ?>
    </div>
</div>
```

### 2. Add Feedback Notification to Dashboard
In `index.php`, add:

```php
<?php
require_once 'feedback_360_integration.php';
$pending_feedback = getUserPendingFeedbackCount($_SESSION['user_id']);
?>

<!-- In notifications section -->
<?php if ($pending_feedback > 0): ?>
    <div class="alert alert-info">
        <i class="fas fa-comment-dots"></i>
        You have <strong><?= $pending_feedback; ?></strong> pending feedback request(s).
        <a href="feedback_360.php" class="alert-link">View</a>
    </div>
<?php endif; ?>
```

### 3. Add Feedback to Performance Review Page
In `performance_reviews.php`, add:

```php
<?php
require_once 'feedback_360_integration.php';
$feedback_data = exportFeedbackData($employee_id);
?>

<!-- Tab for feedback data -->
<div class="tab-pane" id="feedbackTab">
    <h5>360-Degree Feedback Results</h5>
    <?php if (!empty($feedback_data)): ?>
        <!-- Display feedback scores and comments -->
    <?php else: ?>
        <p>No feedback available for this evaluation period.</p>
    <?php endif; ?>
</div>
```

### 4. Link from Goals to Feedback
In `goal_updates.php`, add:

```php
<?php
require_once 'feedback_360_integration.php';
// Add feedback score context when viewing employee goals
$feedback_score = getEmployeeFeedbackScore($employee_id);
?>

<div class="alert alert-info">
    <strong>Feedback Score:</strong> <?= number_format($feedback_score, 1); ?>/5
    <a href="feedback_360.php" class="float-right">View 360 Feedback</a>
</div>
```

## Quick Start Checklist

- [ ] Feedback tables are created in database
- [ ] Navigation link is visible in sidebar (Performance section)
- [ ] Users can create feedback cycles
- [ ] Users can request feedback
- [ ] Users can submit feedback
- [ ] Integration helper is included in other modules
- [ ] Employee profiles show feedback badges
- [ ] Dashboard shows pending feedback notifications

## Access Control

**Who can access?**
- Admin - Full access
- HR - Full access
- Manager - Can create cycles, request feedback, view team feedback
- Employee - Limited (can only provide feedback when requested)

**Who can see what?**
- All users can see their own pending feedback requests
- Managers can see their team's feedback
- HR/Admin can see organization-wide feedback data

## Database Queries

If you need to create the tables manually:

```sql
-- Run add_goal_tracking_columns.php first to ensure database is set up
-- Then the feedback_360.php will auto-create these tables on first load
```

## Troubleshooting

### Tables not created
- Run `feedback_360.php` in your browser
- Check error logs for database connection issues
- Ensure PDO database connection is working

### Can't see pending feedback
- Verify user_id is set in session
- Check feedback_requests table has correct reviewer_id
- Ensure feedback cycle is 'Active'

### Integration not showing
- Include `feedback_360_integration.php` in the page
- Check that database connection is established
- Verify table names are correct

## Future Enhancements

- [ ] Export feedback reports to PDF
- [ ] Email notifications for pending feedback
- [ ] Feedback trends and analytics
- [ ] Integration with performance ratings
- [ ] Comparative feedback analysis
- [ ] Scheduling reminders for feedback submission

## Support

For issues or questions about the feedback system integration, contact HR IT Team.

---
Last Updated: February 19, 2026
