<?php
session_start();
require_once 'db_connect.php';

if (!isset($_GET['letter_id'])) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit;
}

$letter_id = $_GET['letter_id'];

$stmt = $conn->prepare("SELECT ol.*, jo.offered_salary, jo.offered_benefits, jo.start_date, jo.offer_status,
                       c.first_name, c.last_name, job.title as job_title, d.department_name
                       FROM offer_letters ol
                       JOIN job_offers jo ON ol.offer_id = jo.offer_id
                       JOIN job_applications ja ON ol.application_id = ja.application_id
                       JOIN candidates c ON ja.candidate_id = c.candidate_id
                       JOIN job_openings job ON ja.job_opening_id = job.job_opening_id
                       JOIN departments d ON job.department_id = d.department_id
                       WHERE ol.letter_id = ?");
$stmt->bind_param('i', $letter_id);
$stmt->execute();
$letter = $stmt->get_result()->fetch_assoc();

if (!$letter) {
    echo '<div class="alert alert-danger">Letter not found</div>';
    exit;
}
?>

<div class="offer-letter-preview" style="background: white; padding: 30px; border: 1px solid #ddd; border-radius: 8px;">
    <div class="mb-4">
        <h6 class="text-muted">Offer Details</h6>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Candidate:</strong> <?php echo htmlspecialchars($letter['first_name'] . ' ' . $letter['last_name']); ?></p>
                <p><strong>Position:</strong> <?php echo htmlspecialchars($letter['job_title']); ?></p>
                <p><strong>Department:</strong> <?php echo htmlspecialchars($letter['department_name']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Salary:</strong> ₱<?php echo number_format($letter['offered_salary'], 2); ?></p>
                <p><strong>Start Date:</strong> <?php echo date('F j, Y', strtotime($letter['start_date'])); ?></p>
                <p><strong>Status:</strong> <span class="badge badge-<?php echo $letter['status'] == 'Sent' ? 'success' : 'warning'; ?>"><?php echo $letter['status']; ?></span></p>
            </div>
        </div>
    </div>
    
    <hr>
    
    <div class="letter-content" style="white-space: pre-wrap; font-family: 'Times New Roman', serif; line-height: 1.8;">
        <?php echo htmlspecialchars($letter['letter_content']); ?>
    </div>
    
    <?php if ($letter['offered_benefits']): ?>
        <hr>
        <div class="mt-4">
            <h6><strong>Benefits Package:</strong></h6>
            <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($letter['offered_benefits']); ?></p>
        </div>
    <?php endif; ?>
</div>
