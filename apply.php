<?php
require_once 'config.php';
require_once 'link_candidate_documents.php';

$job_id = $_GET['job_id'] ?? null;
if (!$job_id) {
    header('Location: public_jobs.php');
    exit;
}

// Get job details
$stmt = $conn->prepare("SELECT jo.*, d.department_name FROM job_openings jo JOIN departments d ON jo.department_id = d.department_id WHERE jo.job_opening_id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: public_jobs.php');
    exit;
}

$success = false;
$error = '';

// Check for existing candidate or create draft
$candidateId = null;
if (isset($_POST['email']) && !empty($_POST['email'])) {
    // Check by email first
    $stmt = $conn->prepare("SELECT candidate_id, first_name, last_name FROM candidates WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $candidateId = $existing['candidate_id'];
        
        // If names are provided and different, check for potential duplicate person
        if (isset($_POST['first_name']) && isset($_POST['last_name'])) {
            $providedName = strtolower(trim($_POST['first_name'] . ' ' . $_POST['last_name']));
            $existingName = strtolower(trim($existing['first_name'] . ' ' . $existing['last_name']));
            
            if (!empty($existingName) && $providedName !== $existingName) {
                throw new Exception('This email is already registered with a different name. Please use a different email or contact support.');
            }
        }
    }
    
    // Check for duplicate full name with different email
    if (isset($_POST['first_name']) && isset($_POST['last_name'])) {
        $fullName = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
        $stmt = $conn->prepare("SELECT candidate_id, email FROM candidates WHERE CONCAT(first_name, ' ', last_name) = ? AND email != ?");
        $stmt->execute([$fullName, $_POST['email']]);
        $nameMatch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($nameMatch && !isset($_POST['confirm_duplicate'])) {
            throw new Exception('DUPLICATE_NAME:' . $nameMatch['email']);
        }
    }
}

// Handle email confirmation
if (isset($_GET['confirm']) && isset($_GET['token'])) {
    $token = $_GET['token'];
    // Use source field to store token temporarily
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE source = ?");
    $stmt->execute(['TOKEN:' . $token]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($candidate) {
        // Update candidate source and application status
        $stmt = $conn->prepare("UPDATE candidates SET source = 'Website' WHERE candidate_id = ?");
        $stmt->execute([$candidate['candidate_id']]);
        
        // Update application status to Applied
        $stmt = $conn->prepare("UPDATE job_applications SET status = 'Applied' WHERE candidate_id = ? AND job_opening_id = ?");
        $stmt->execute([$candidate['candidate_id'], $job_id]);
        
        $success = true;
    } else {
        $error = 'Invalid or expired confirmation link.';
    }
}

// Process application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if email is verified
        session_start();
        $email = $_POST['email'] ?? '';
        if (!isset($_SESSION['verified_emails'][$email])) {
            throw new Exception('Please verify your email address before submitting the application.');
        }
        // Create uploads directories if not exist
        $resumeDir = 'uploads/resumes/';
        $coverLetterDir = 'uploads/cover_letters/';
        $pdsDir = 'uploads/pds/';
        
        if (!file_exists($resumeDir)) mkdir($resumeDir, 0777, true);
        if (!file_exists($coverLetterDir)) mkdir($coverLetterDir, 0777, true);
        if (!file_exists($pdsDir)) mkdir($pdsDir, 0777, true);
        
        // Handle file uploads
        $resumePath = null;
        $coverLetterPath = null;
        $pdsPath = null;
        
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_resume_' . $_FILES['resume']['name'];
            $resumePath = $resumeDir . $fileName;
            if (!move_uploaded_file($_FILES['resume']['tmp_name'], $resumePath)) {
                throw new Exception('Failed to upload resume');
            }
        }
        
        if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_cover_' . $_FILES['cover_letter']['name'];
            $coverLetterPath = $coverLetterDir . $fileName;
            if (!move_uploaded_file($_FILES['cover_letter']['tmp_name'], $coverLetterPath)) {
                throw new Exception('Failed to upload cover letter');
            }
        }
        
        if (isset($_FILES['pds']) && $_FILES['pds']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_pds_' . $_FILES['pds']['name'];
            $pdsPath = $pdsDir . $fileName;
            if (!move_uploaded_file($_FILES['pds']['tmp_name'], $pdsPath)) {
                throw new Exception('Failed to upload PDS');
            }
        }
        
        if ($candidateId) {
            
            // Update existing candidate
            $stmt = $conn->prepare("UPDATE candidates SET first_name = ?, last_name = ?, phone = ?, address = ?, resume_url = ?, current_position = ?, current_company = ?, expected_salary = ?, cover_letter_url = ?, source = ? WHERE candidate_id = ?");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['phone'],
                $_POST['address'],
                $resumePath ?: null,
                $_POST['current_position'] ?: null,
                $_POST['current_company'] ?: null,
                $_POST['expected_salary'] ?: null,
                $pdsPath ?: null,
                'Website',
                $candidateId
            ]);
        } else {
            
            // Insert new candidate
            $stmt = $conn->prepare("INSERT INTO candidates (first_name, last_name, email, phone, address, resume_url, current_position, current_company, expected_salary, cover_letter_url, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'], 
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'],
                $resumePath ?: null,
                $_POST['current_position'] ?: null,
                $_POST['current_company'] ?: null,
                $_POST['expected_salary'] ?: null,
                $pdsPath ?: null,
                'Website'
            ]);
            $candidateId = $conn->lastInsertId();
        }
        
        // Check if application already exists
        $stmt = $conn->prepare("SELECT application_id FROM job_applications WHERE job_opening_id = ? AND candidate_id = ?");
        $stmt->execute([$job_id, $candidateId]);
        $existingApp = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingApp) {
            throw new Exception('You have already applied for this position.');
        }
        
        // Insert job application with Applied status
        $stmt = $conn->prepare("INSERT INTO job_applications (job_opening_id, candidate_id, application_date, status) VALUES (?, ?, NOW(), 'Applied')");
        $stmt->execute([$job_id, $candidateId]);
        

        
        // Insert documents into document management
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        $candidateName = $_POST['first_name'] . ' ' . $_POST['last_name'];
        $jobTitle = $job['title'];
        
        // Handle Resume
        if ($resumePath) {
            $documentName = 'Resume - ' . $candidateName . ' - ' . $jobTitle;
            $stmt = $conn->prepare("SELECT document_id FROM document_management WHERE employee_id = ? AND document_type = 'Resume'");
            $stmt->execute([$candidateId]);
            $existingDoc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingDoc) {
                $stmt = $conn->prepare("UPDATE document_management SET document_name = ?, file_path = ?, notes = ? WHERE document_id = ?");
                $stmt->execute([$documentName, $resumePath, 'Resume updated during job application. Candidate ID: ' . $candidateId, $existingDoc['document_id']]);
            } else {
                $stmt = $conn->prepare("INSERT INTO document_management (employee_id, document_type, document_name, file_path, document_status, notes) VALUES (?, 'Resume', ?, ?, 'Active', ?)");
                $stmt->execute([$candidateId, $documentName, $resumePath, 'Resume uploaded during job application. Candidate ID: ' . $candidateId]);
            }
        }
        
        // Handle Cover Letter
        if ($coverLetterPath) {
            $documentName = 'Cover Letter - ' . $candidateName . ' - ' . $jobTitle;
            $stmt = $conn->prepare("INSERT INTO document_management (employee_id, document_type, document_name, file_path, document_status, notes) VALUES (?, 'Contract', ?, ?, 'Active', ?)");
            $stmt->execute([$candidateId, $documentName, $coverLetterPath, 'Cover Letter uploaded during job application. Candidate ID: ' . $candidateId]);
        }
        
        // Handle PDS
        if ($pdsPath) {
            $documentName = 'PDS - ' . $candidateName . ' - ' . $jobTitle;
            $stmt = $conn->prepare("INSERT INTO document_management (employee_id, document_type, document_name, file_path, document_status, notes) VALUES (?, 'Contract', ?, ?, 'Active', ?)");
            $stmt->execute([$candidateId, $documentName, $pdsPath, 'PDS uploaded during job application. Candidate ID: ' . $candidateId]);
        }
        
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?php echo htmlspecialchars($job['title']); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        :root {
            --primary: #E91E63;
            --primary-light: #F06292;
            --primary-dark: #C2185B;
            --accent: #F8BBD0;
            --light: #FCE4EC;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, #fff 100%);
            min-height: 100vh;
        }
        
        .application-container {
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.2);
            overflow: hidden;
            margin: 50px auto;
            max-width: 900px;
            position: relative;
            animation: slideUp 0.6s ease-out;
        }
        
        .application-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), #ff6b9d, var(--primary));
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }
        
        .application-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, #ff6b9d 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .application-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .application-header h2 {
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .application-header p {
            position: relative;
            z-index: 2;
            opacity: 0.9;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 40px 0;
            position: relative;
        }
        
        .step {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e9ecef, #f8f9fa);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 15px;
            font-weight: bold;
            position: relative;
            transition: all 0.4s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 3px solid #e9ecef;
        }
        
        .step.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
            border-color: var(--primary-light);
            animation: pulse 2s infinite;
        }
        
        .step.completed {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-color: #28a745;
        }
        
        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 30px;
            height: 3px;
            background: linear-gradient(90deg, #e9ecef, #dee2e6);
            transform: translateY(-50%);
            border-radius: 2px;
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .form-section {
            padding: 50px;
            background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
            position: relative;
        }
        
        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50px;
            right: 50px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .success-container {
            text-align: center;
            padding: 80px 40px;
            background: linear-gradient(135deg, #f8fff9 0%, #ffffff 100%);
            position: relative;
        }
        
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 30px;
            animation: bounce 1s ease-out;
            filter: drop-shadow(0 4px 15px rgba(40, 167, 69, 0.3));
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        @keyframes float {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            50% { transform: translate(-45%, -55%) rotate(180deg); }
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4); }
            50% { box-shadow: 0 8px 35px rgba(233, 30, 99, 0.6); }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }
        
        .form-check {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .form-check:hover {
            border-color: var(--primary-light);
            background: var(--light);
        }
        
        .form-check-input:checked ~ .form-check-label {
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-top: 0;
            margin-right: 10px;
            flex-shrink: 0;
        }
        
        .form-check-label {
            font-size: 16px;
            margin-left: 0;
            cursor: pointer;
            flex: 1;
        }
        
        .form-control-file {
            margin-top: 10px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="application-container">
            <?php if ($success): ?>
                <?php if (isset($_GET['confirm'])): ?>
                    <div class="application-header">
                        <h2><i class="fas fa-check-circle mr-3"></i>Email Confirmed!</h2>
                        <p>Your application has been successfully submitted</p>
                    </div>
                    <div class="success-container">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h4>Application Confirmed and Submitted</h4>
                        <p class="text-muted mb-4">Thank you for confirming your email. We will review your application and contact you soon.</p>
                        <a href="public_jobs.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Job Listings
                        </a>
                    </div>
                <?php else: ?>
                    <div class="application-header">
                        <h2><i class="fas fa-envelope mr-3"></i>Check Your Email!</h2>
                        <p>Confirmation email sent</p>
                    </div>
                    <div class="success-container">
                        <i class="fas fa-envelope success-icon"></i>
                        <h4>Confirmation Email Sent</h4>
                        <p class="text-muted mb-4">We've sent a confirmation email to <strong><?php echo htmlspecialchars($_POST['email'] ?? ''); ?></strong>. Please check your inbox and click the confirmation link to complete your application.</p>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Important:</strong> The confirmation link will expire in 24 hours. If you don't see the email, please check your spam folder.
                        </div>
                        <a href="public_jobs.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Job Listings
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="application-header">
                    <h2><i class="fas fa-briefcase mr-3"></i><?php echo htmlspecialchars($job['title']); ?></h2>
                    <p><?php echo htmlspecialchars($job['department_name']); ?></p>
                </div>
                
                <?php if ($error): ?>
                    <?php if (strpos($error, 'DUPLICATE_NAME:') === 0): ?>
                        <?php $existingEmail = substr($error, 15); ?>
                        <div class="alert alert-warning m-4">
                            <h6><i class="fas fa-exclamation-triangle mr-2"></i>Duplicate Name Detected</h6>
                            <p>A candidate with the same name already exists with email: <strong><?php echo htmlspecialchars($existingEmail); ?></strong></p>
                            <p>Are you sure this is a different person?</p>
                            <form method="POST" enctype="multipart/form-data" style="display: inline;">
                                <?php foreach($_POST as $key => $value): ?>
                                    <?php if ($key !== 'confirm_duplicate'): ?>
                                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <input type="hidden" name="confirm_duplicate" value="1">
                                <button type="submit" class="btn btn-warning mr-2">
                                    <i class="fas fa-check mr-1"></i>Yes, Continue
                                </button>
                            </form>
                            <a href="apply.php?job_id=<?php echo $job_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times mr-1"></i>No, Review
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger m-4"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="form-section">
                    <form method="POST" enctype="multipart/form-data" id="applicationForm">
                        <input type="hidden" name="confirm_duplicate" id="confirmDuplicate" value="">
                        <!-- Personal Information -->
                        <h5 class="mb-4"><i class="fas fa-user mr-2"></i>Personal Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name *</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div id="nameAlert"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email *</label>
                                    <input type="email" name="email" class="form-control" required>
                                    <div id="emailAlert" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" name="phone" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Professional Questions -->
                        <h5 class="mb-4"><i class="fas fa-question-circle mr-2"></i>Professional Questions</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Current Position <small class="text-muted">(Leave blank if new graduate)</small></label>
                                    <input type="text" name="current_position" class="form-control" placeholder="e.g., Software Developer, Student, etc.">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Current Company <small class="text-muted">(Leave blank if unemployed/student)</small></label>
                                    <input type="text" name="current_company" class="form-control" placeholder="e.g., ABC Corporation, University, etc.">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Expected Salary <small class="text-muted">(Monthly in PHP)</small></label>
                            <input type="number" name="expected_salary" class="form-control" step="1000" placeholder="e.g., 25000">
                            <small class="text-muted">Please provide your salary expectation for this position</small>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- Documents -->
                        <h5 class="mb-4"><i class="fas fa-file-upload mr-2"></i>Upload Documents</h5>
                        <div class="form-group">
                            <label>Resume/CV *</label>
                            <input type="file" name="resume" class="form-control-file" accept=".pdf,.doc,.docx" required>
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Max 5MB)</small>
                            <div id="resumePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Cover Letter <small class="text-muted">(Optional)</small></label>
                            <input type="file" name="cover_letter" class="form-control-file" accept=".pdf,.doc,.docx">
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Max 5MB)</small>
                            <div id="coverLetterPreview" class="mt-2"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Personal Data Sheet (PDS) * <span class="text-danger">(Required - Will be sent to Mayor)</span></label>
                            <input type="file" name="pds" class="form-control-file" accept=".pdf,.doc,.docx" required>
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX (Max 5MB)</small>
                            <div class="alert alert-warning mt-2">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>IMPORTANT:</strong> PDS is mandatory and will be forwarded to the Mayor's office for review.
                                <br>
                                <strong>Need PDS Template?</strong> 
                                <a href="uploads/pds_templates/PDS_Template.html" target="_blank" class="btn btn-sm btn-outline-primary ml-2">
                                    <i class="fas fa-download mr-1"></i>Download PDS Template
                                </a>
                                <small class="d-block mt-1 text-muted">Print and fill out the PDF template completely, then scan/photo and upload</small>
                            </div>
                            <div id="pdsPreview" class="mt-2"></div>
                        </div>
                        
                        <div class="text-center mt-5">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-paper-plane mr-2"></i>Submit Application
                            </button>
                            <a href="public_jobs.php" class="btn btn-secondary btn-lg ml-3">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Jobs
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        let candidateDraftCreated = false;
        let nameCheckTimeout, emailCheckTimeout;
        
        // Real-time name duplicate checker
        function checkNameDuplicate() {
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const lastName = document.querySelector('input[name="last_name"]').value.trim();
            
            if (firstName && lastName) {
                clearTimeout(nameCheckTimeout);
                nameCheckTimeout = setTimeout(() => {
                    fetch('check_duplicate.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `type=name&first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const nameAlert = document.getElementById('nameAlert');
                        if (data.exists) {
                            nameAlert.innerHTML = `
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Duplicate Name Found!</strong><br>
                                    Email: ${data.data.email}<br>
                                    Phone: ${data.data.phone || 'Not provided'}<br>
                                    <small>Is this a different person? You can continue if confirmed.</small>
                                </div>
                            `;
                        } else {
                            nameAlert.innerHTML = '<div class="alert alert-success"><i class="fas fa-check mr-2"></i>Name available</div>';
                        }
                    });
                }, 500);
            } else {
                document.getElementById('nameAlert').innerHTML = '';
            }
        }
        
        // Real-time email duplicate checker
        function checkEmailDuplicate() {
            const email = document.querySelector('input[name="email"]').value.trim();
            
            if (email) {
                clearTimeout(emailCheckTimeout);
                emailCheckTimeout = setTimeout(() => {
                    fetch('check_duplicate.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `type=email&email=${encodeURIComponent(email)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        const emailAlert = document.getElementById('emailAlert');
                        if (data.exists) {
                            emailAlert.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-times mr-2"></i>
                                    <strong>Email Already Registered!</strong><br>
                                    Name: ${data.data.name}<br>
                                    Phone: ${data.data.phone || 'Not provided'}<br>
                                    <small>Please use a different email address.</small>
                                </div>
                            `;
                        } else {
                            emailAlert.innerHTML = `
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <strong>Email Available</strong>
                                </div>
                            `;
                        }
                    });
                }, 500);
            } else {
                document.getElementById('emailAlert').innerHTML = '';
            }
        }
        
        // Attach event listeners
        document.querySelector('input[name="first_name"]').addEventListener('input', checkNameDuplicate);
        document.querySelector('input[name="last_name"]').addEventListener('input', checkNameDuplicate);
        document.querySelector('input[name="email"]').addEventListener('input', checkEmailDuplicate);
        
        // File preview and validation function
        function setupFileValidation(inputName, previewId) {
            document.querySelector(`input[name="${inputName}"]`).addEventListener('change', function(e) {
                const file = e.target.files[0];
                const preview = document.getElementById(previewId);
                
                if (file) {
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    
                    if (!allowedTypes.includes(file.type)) {
                        preview.innerHTML = '<div class="alert alert-danger">Invalid file type. Please upload PDF, DOC, or DOCX files only.</div>';
                        e.target.value = '';
                        return;
                    }
                    
                    if (fileSize > 5) {
                        preview.innerHTML = '<div class="alert alert-danger">File too large. Maximum size is 5MB.</div>';
                        e.target.value = '';
                        return;
                    }
                    
                    preview.innerHTML = `<div class="alert alert-success"><i class="fas fa-file mr-2"></i>${file.name} (${fileSize} MB) - Ready to upload</div>`;
                } else {
                    preview.innerHTML = '';
                }
            });
        }
        
        // Setup file validation for all file inputs
        setupFileValidation('resume', 'resumePreview');
        setupFileValidation('cover_letter', 'coverLetterPreview');
        setupFileValidation('pds', 'pdsPreview');
        
        // Form submission confirmation
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>