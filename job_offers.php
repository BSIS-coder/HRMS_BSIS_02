<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'generate_offer_letter':
            try {
                $application_id = $_POST['application_id'];
                $candidate_id = $_POST['candidate_id'];
                $job_opening_id = $_POST['job_opening_id'];
                
                // Get candidate and job details
                $stmt = $conn->prepare("SELECT c.*, ja.*, jo.title as job_title, jo.description, jo.requirements, 
                                       d.department_name, d.department_id
                                       FROM candidates c
                                       JOIN job_applications ja ON c.candidate_id = ja.candidate_id
                                       JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                                       JOIN departments d ON jo.department_id = d.department_id
                                       WHERE ja.application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();
                
                if (!$data) {
                    throw new Exception("Application not found");
                }
                
                // Store data in session for the AI generation page
                $_SESSION['offer_letter_data'] = [
                    'application_id' => $application_id,
                    'candidate_id' => $candidate_id,
                    'job_opening_id' => $job_opening_id,
                    'candidate_name' => $data['first_name'] . ' ' . $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'job_title' => $data['job_title'],
                    'department_name' => $data['department_name'],
                    'department_id' => $data['department_id'],
                    'description' => $data['description'],
                    'requirements' => $data['requirements']
                ];
                
                // Redirect to AI offer letter generator
                header('Location: generate_offer_letter_ai.php');
                exit;
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
            break;
            
        case 'save_offer_letter':
            try {
                $application_id = $_POST['application_id'];
                $letter_content = $_POST['letter_content'];
                $offered_salary = $_POST['offered_salary'];
                $offered_benefits = $_POST['offered_benefits'];
                $start_date = $_POST['start_date'];
                $expiration_date = date('Y-m-d', strtotime('+7 days'));
                
                // Check if offer already exists
                $check = $conn->prepare("SELECT offer_id FROM job_offers WHERE application_id = ?");
                $check->bind_param('i', $application_id);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();
                
                if ($existing) {
                    // Update existing offer
                    $stmt = $conn->prepare("UPDATE job_offers SET offered_salary = ?, offered_benefits = ?, 
                                           start_date = ?, expiration_date = ?, offer_status = 'Draft' 
                                           WHERE application_id = ?");
                    $stmt->bind_param('dsssi', $offered_salary, $offered_benefits, $start_date, $expiration_date, $application_id);
                    $stmt->execute();
                    $offer_id = $existing['offer_id'];
                } else {
                    // Create new offer
                    $stmt = $conn->prepare("SELECT candidate_id, job_opening_id FROM job_applications WHERE application_id = ?");
                    $stmt->bind_param('i', $application_id);
                    $stmt->execute();
                    $app_data = $stmt->get_result()->fetch_assoc();
                    
                    $stmt = $conn->prepare("INSERT INTO job_offers (application_id, job_opening_id, candidate_id, 
                                           offered_salary, offered_benefits, start_date, expiration_date, 
                                           approval_status, offer_status) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Draft')");
                    $stmt->bind_param('iiidsss', $application_id, $app_data['job_opening_id'], 
                                     $app_data['candidate_id'], $offered_salary, $offered_benefits, 
                                     $start_date, $expiration_date);
                    $stmt->execute();
                    $offer_id = $conn->insert_id;
                }
                
                // Save letter content
                $stmt = $conn->prepare("INSERT INTO offer_letters (offer_id, application_id, letter_content, 
                                       status, created_by, created_at) 
                                       VALUES (?, ?, ?, 'Draft', ?, NOW())
                                       ON DUPLICATE KEY UPDATE letter_content = ?, status = 'Draft'");
                $stmt->bind_param('iisis', $offer_id, $application_id, $letter_content, 
                                 $_SESSION['user_id'], $letter_content);
                $stmt->execute();
                
                $success_message = "✅ Offer letter saved as draft!";
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
            break;
            
        case 'send_offer':
            try {
                $offer_id = $_POST['offer_id'];
                
                // Update offer status to Sent
                $stmt = $conn->prepare("UPDATE job_offers SET offer_status = 'Sent' WHERE offer_id = ?");
                $stmt->bind_param('i', $offer_id);
                $stmt->execute();
                
                // Update letter status
                $stmt = $conn->prepare("UPDATE offer_letters SET status = 'Sent', sent_at = NOW() WHERE offer_id = ?");
                $stmt->bind_param('i', $offer_id);
                $stmt->execute();
                
                // Update application status to Offer
                $stmt = $conn->prepare("UPDATE job_applications ja 
                                       JOIN job_offers jo ON ja.application_id = jo.application_id 
                                       SET ja.status = 'Offer' 
                                       WHERE jo.offer_id = ?");
                $stmt->bind_param('i', $offer_id);
                $stmt->execute();
                
                $success_message = "📧 Offer letter sent to candidate!";
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
            break;
            
        case 'hire_candidate':
            try {
                $application_id = $_POST['application_id'];
                
                // Update job application status to Hired
                $stmt = $conn->prepare("UPDATE job_applications SET status = 'Hired' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                // Update offer status to Accepted
                $stmt = $conn->prepare("UPDATE job_offers SET offer_status = 'Accepted' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                // Update letter status to Accepted
                $stmt = $conn->prepare("UPDATE offer_letters SET status = 'Accepted' WHERE application_id = ?");
                $stmt->bind_param('i', $application_id);
                $stmt->execute();
                
                $success_message = "🎉 Candidate hired successfully!";
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
            break;
    }
}

// Create offer_letters table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS offer_letters (
    letter_id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    application_id INT NOT NULL,
    letter_content TEXT NOT NULL,
    status ENUM('Draft', 'Sent', 'Accepted', 'Declined') DEFAULT 'Draft',
    created_by INT,
    created_at DATETIME,
    sent_at DATETIME,
    UNIQUE KEY unique_offer (offer_id),
    FOREIGN KEY (offer_id) REFERENCES job_offers(offer_id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES job_applications(application_id) ON DELETE CASCADE
)");

// Get applicants with Offer status
$applicants_query = "SELECT c.*, ja.application_id, ja.status, ja.application_date,
                     jo.title as job_title, jo.job_opening_id, jo.description,
                     d.department_name, d.department_id,
                     jof.offer_id, jof.offered_salary, jof.offered_benefits, jof.start_date, 
                     jof.offer_status, jof.approval_status,
                     ol.letter_id, ol.letter_content, ol.status as letter_status
                     FROM candidates c
                     JOIN job_applications ja ON c.candidate_id = ja.candidate_id
                     JOIN job_openings jo ON ja.job_opening_id = jo.job_opening_id
                     JOIN departments d ON jo.department_id = d.department_id
                     LEFT JOIN job_offers jof ON ja.application_id = jof.application_id
                     LEFT JOIN offer_letters ol ON jof.offer_id = ol.offer_id
                     WHERE ja.status = 'Offer'
                     ORDER BY ja.application_date DESC";
$applicants = $conn->query($applicants_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Offers - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">� Job Offers Management</h2>
                    <div>
                        <span class="badge badge-info" style="font-size: 1rem; padding: 10px 15px;">
                            <i class="fas fa-users"></i> <?php echo count($applicants); ?> Candidates
                        </span>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error_message; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-contract mr-2"></i>Candidates Ready for Offer Letters</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($applicants)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user"></i> Candidate</th>
                                            <th><i class="fas fa-briefcase"></i> Position</th>
                                            <th><i class="fas fa-building"></i> Department</th>
                                            <th><i class="fas fa-envelope"></i> Contact</th>
                                            <th><i class="fas fa-file-alt"></i> Letter Status</th>
                                            <th><i class="fas fa-check-circle"></i> Offer Status</th>
                                            <th style="text-align: center;"><i class="fas fa-cogs"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applicants as $applicant): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($applicant['job_title']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($applicant['department_name']); ?></td>
                                            <td>
                                                <small>
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($applicant['email']); ?><br>
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($applicant['phone']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($applicant['letter_id']): ?>
                                                    <span class="badge badge-<?php echo $applicant['letter_status'] == 'Sent' ? 'success' : 'warning'; ?>">
                                                        <?php echo $applicant['letter_status']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Not Created</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($applicant['offer_id']): ?>
                                                    <span class="badge badge-<?php echo $applicant['offer_status'] == 'Sent' ? 'success' : 'info'; ?>">
                                                        <?php echo $applicant['offer_status']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">No Offer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (!$applicant['letter_id']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="generate_offer_letter">
                                                        <input type="hidden" name="application_id" value="<?php echo $applicant['application_id']; ?>">
                                                        <input type="hidden" name="candidate_id" value="<?php echo $applicant['candidate_id']; ?>">
                                                        <input type="hidden" name="job_opening_id" value="<?php echo $applicant['job_opening_id']; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-magic"></i> Generate with AI
                                                        </button>
                                                    </form>
                                                <?php elseif ($applicant['letter_status'] == 'Draft'): ?>
                                                    <button class="btn btn-info btn-sm" onclick="viewLetter(<?php echo $applicant['letter_id']; ?>, '<?php echo htmlspecialchars(addslashes($applicant['first_name'] . ' ' . $applicant['last_name'])); ?>')">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="send_offer">
                                                        <input type="hidden" name="offer_id" value="<?php echo $applicant['offer_id']; ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-paper-plane"></i> Send
                                                        </button>
                                                    </form>
                                                <?php elseif ($applicant['letter_status'] == 'Sent'): ?>
                                                    <button class="btn btn-info btn-sm" onclick="viewLetter(<?php echo $applicant['letter_id']; ?>, '<?php echo htmlspecialchars(addslashes($applicant['first_name'] . ' ' . $applicant['last_name'])); ?>')">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <a href="download_offer_pdf.php?letter_id=<?php echo $applicant['letter_id']; ?>" class="btn btn-warning btn-sm" target="_blank">
                                                        <i class="fas fa-file-pdf"></i> PDF
                                                    </a>
                                                    <button type="button" class="btn btn-success btn-sm" onclick="hireCandidate(<?php echo $applicant['application_id']; ?>, '<?php echo htmlspecialchars(addslashes($applicant['first_name'] . ' ' . $applicant['last_name'])); ?>')">
                                                        <i class="fas fa-user-check"></i> Hire
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Candidates with Offer Status</h4>
                                <p class="text-muted">Candidates will appear here when their status changes to "Offer".</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Letter Modal -->
    <div class="modal fade" id="viewLetterModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-file-alt"></i> Offer Letter - <span id="candidateName"></span></h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="letterContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2">Loading letter...</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function viewLetter(letterId, candidateName) {
            $('#candidateName').text(candidateName);
            $('#viewLetterModal').modal('show');
            
            $.get('fetch_offer_letter.php', { letter_id: letterId }, function(html) {
                $('#letterContent').html(html);
            }).fail(function() {
                $('#letterContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to load letter.</div>');
            });
        }
        
        function hireCandidate(applicationId, candidateName) {
            Swal.fire({
                title: 'Hire Candidate?',
                text: `Finalize hiring for ${candidateName}? This will mark them as officially hired.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-user-check"></i> Yes, Hire',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="hire_candidate">
                        <input type="hidden" name="application_id" value="${applicationId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
