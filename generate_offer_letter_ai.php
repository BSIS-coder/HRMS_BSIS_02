<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['offer_letter_data'])) {
    header('Location: job_offers.php');
    exit;
}

require_once 'db_connect.php';
require_once 'ai_config.php';

$data = $_SESSION['offer_letter_data'];
$success_message = '';
$error_message = '';
$generated_letter = '';

// Handle AI generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_ai') {
        try {
            $offered_salary = $_POST['offered_salary'];
            $offered_benefits = $_POST['offered_benefits'];
            $start_date = $_POST['start_date'];
            
            // Prepare AI prompt
            $prompt = "Generate a professional job offer letter with the following details:\n\n";
            $prompt .= "Candidate Name: {$data['candidate_name']}\n";
            $prompt .= "Position: {$data['job_title']}\n";
            $prompt .= "Department: {$data['department_name']}\n";
            $prompt .= "Salary: ₱" . number_format($offered_salary, 2) . "\n";
            $prompt .= "Start Date: " . date('F j, Y', strtotime($start_date)) . "\n";
            $prompt .= "Benefits: {$offered_benefits}\n\n";
            $prompt .= "Job Description: {$data['description']}\n\n";
            $prompt .= "Requirements: {$data['requirements']}\n\n";
            $prompt .= "Please create a formal, professional offer letter that includes:\n";
            $prompt .= "1. A warm greeting and congratulations\n";
            $prompt .= "2. Position title and department\n";
            $prompt .= "3. Salary and benefits\n";
            $prompt .= "4. Start date and work schedule\n";
            $prompt .= "5. Key responsibilities\n";
            $prompt .= "6. Acceptance deadline (7 days from today)\n";
            $prompt .= "7. Professional closing\n\n";
            $prompt .= "Format it as a formal business letter from the Municipal HR Department.";
            
            // Call AI API based on provider
            $provider = AI_PROVIDER;
            
            if ($provider === 'mock') {
                // Generate mock offer letter
                $generated_letter = generateMockOfferLetter(
                    $data['candidate_name'],
                    $data['job_title'],
                    $data['department_name'],
                    $offered_salary,
                    $start_date,
                    $offered_benefits
                );
                $success_message = "✅ Offer letter generated successfully! (Mock Mode)";
            } else {
                // Try to call real AI API
                try {
                    if ($provider === 'gemini') {
                        $result = callGeminiForOfferLetter($prompt);
                    } elseif ($provider === 'openai') {
                        $result = callOpenAIForOfferLetter($prompt);
                    } else {
                        throw new Exception("Unknown AI provider: " . $provider);
                    }
                    
                    if (isset($result['success']) && $result['success']) {
                        $generated_letter = $result['letter'];
                        $success_message = "✅ Offer letter generated successfully with " . strtoupper($provider) . "!";
                    } else {
                        throw new Exception($result['error'] ?? 'AI generation failed');
                    }
                    
                    logAPIUsage($provider, 'offer_letter_generation');
                } catch (Exception $e) {
                    // Fallback to mock if AI fails
                    $generated_letter = generateMockOfferLetter(
                        $data['candidate_name'],
                        $data['job_title'],
                        $data['department_name'],
                        $offered_salary,
                        $start_date,
                        $offered_benefits
                    );
                    $error_message = "⚠️ " . $e->getMessage();
                    $success_message = "✅ Offer letter generated using template (AI unavailable)";
                }
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'save_letter') {
        try {
            $application_id = $data['application_id'];
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
                $stmt = $conn->prepare("INSERT INTO job_offers (application_id, job_opening_id, candidate_id, 
                                       offered_salary, offered_benefits, start_date, expiration_date, 
                                       approval_status, offer_status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Draft')");
                $stmt->bind_param('iiidsss', $application_id, $data['job_opening_id'], 
                                 $data['candidate_id'], $offered_salary, $offered_benefits, 
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
            
            // Clear session data
            unset($_SESSION['offer_letter_data']);
            
            // Redirect back to job offers page
            $_SESSION['success_message'] = "✅ Offer letter saved successfully!";
            header('Location: job_offers.php');
            exit;
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Default values
$default_salary = 25000;
$default_benefits = "• Government-mandated benefits (SSS, PhilHealth, Pag-IBIG)\n• 13th month pay\n• Vacation and sick leave credits\n• Health insurance\n• Performance bonuses";
$default_start_date = date('Y-m-d', strtotime('+14 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Offer Letter - HR Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">🤖 AI Offer Letter Generator</h2>
                    <a href="job_offers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Job Offers
                    </a>
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

                <div class="row">
                    <!-- Left Column: Input Form -->
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user-tie mr-2"></i>Candidate Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($data['candidate_name']); ?></p>
                                    <p><strong>Position:</strong> <?php echo htmlspecialchars($data['job_title']); ?></p>
                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($data['department_name']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($data['email']); ?></p>
                                </div>
                                
                                <hr>
                                
                                <form method="POST" id="generateForm">
                                    <input type="hidden" name="action" value="generate_ai">
                                    
                                    <div class="form-group">
                                        <label><strong>Offered Salary (₱)</strong></label>
                                        <input type="number" name="offered_salary" id="offered_salary" class="form-control" 
                                               value="<?php echo $default_salary; ?>" step="0.01" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><strong>Start Date</strong></label>
                                        <input type="date" name="start_date" id="start_date" class="form-control" 
                                               value="<?php echo $default_start_date; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><strong>Benefits Package</strong></label>
                                        <textarea name="offered_benefits" id="offered_benefits" class="form-control" 
                                                  rows="8" required><?php echo $default_benefits; ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success btn-block btn-lg">
                                        <i class="fas fa-magic mr-2"></i>Generate Offer Letter with AI
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Generated Letter -->
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-file-alt mr-2"></i>Generated Offer Letter</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($generated_letter): ?>
                                    <form method="POST" id="saveForm">
                                        <input type="hidden" name="action" value="save_letter">
                                        <input type="hidden" name="offered_salary" value="<?php echo htmlspecialchars($_POST['offered_salary']); ?>">
                                        <input type="hidden" name="offered_benefits" value="<?php echo htmlspecialchars($_POST['offered_benefits']); ?>">
                                        <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date']); ?>">
                                        
                                        <div class="form-group">
                                            <label><strong>Edit Letter Content:</strong></label>
                                            <textarea name="letter_content" id="letter_content" class="form-control" 
                                                      rows="20" style="font-family: 'Times New Roman', serif; white-space: pre-wrap;"><?php echo htmlspecialchars($generated_letter); ?></textarea>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="button" class="btn btn-info" onclick="previewLetter()">
                                                <i class="fas fa-eye mr-1"></i>Preview
                                            </button>
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="fas fa-save mr-2"></i>Save Offer Letter
                                            </button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-robot fa-4x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Letter Generated Yet</h5>
                                        <p class="text-muted">Fill in the offer details and click "Generate Offer Letter with AI" to create a professional offer letter.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Letter Preview</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" style="background: white; padding: 40px;">
                    <div id="previewContent" style="font-family: 'Times New Roman', serif; line-height: 1.8; white-space: pre-wrap;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function previewLetter() {
            const content = document.getElementById('letter_content').value;
            document.getElementById('previewContent').textContent = content;
            $('#previewModal').modal('show');
        }
    </script>
</body>
</html>
