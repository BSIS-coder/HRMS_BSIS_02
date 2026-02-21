<?php
require_once 'config.php';
require_once 'ai_pds_extractor.php';

$job_id = $_GET['job_id'] ?? null;
if (!$job_id) {
    header('Location: public_jobs.php');
    exit;
}

$stmt = $conn->prepare("SELECT jo.*, d.department_name FROM job_openings jo JOIN departments d ON jo.department_id = d.department_id WHERE jo.job_opening_id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: public_jobs.php');
    exit;
}

$success = false;
$error = '';
$extractedData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pds']) && !isset($_POST['confirm_submit'])) {
    try {
        if ($_FILES['pds']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('PDS file is required');
        }

        $fileExt = strtolower(pathinfo($_FILES['pds']['name'], PATHINFO_EXTENSION));
        $pdsFileBlob = file_get_contents($_FILES['pds']['tmp_name']);
        
        $result = extractPDSWithAI($pdsFileBlob, $fileExt);
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to extract PDS data');
        }
        
        if (empty($result['data']['first_name']) || empty($result['data']['last_name']) || empty($result['data']['email'])) {
            throw new Exception('PDS must contain first name, last name, and email');
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT candidate_id FROM candidates WHERE email = ?");
        $stmt->execute([$result['data']['email']]);
        $existingCandidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingCandidate) {
            // Check if they already applied for this job
            $stmt = $conn->prepare("SELECT application_id FROM job_applications WHERE job_opening_id = ? AND candidate_id = ?");
            $stmt->execute([$job_id, $existingCandidate['candidate_id']]);
            if ($stmt->fetch()) {
                throw new Exception('You have already applied for this position with this email address.');
            }
            
            // Use existing candidate
            $tempCandidateId = $existingCandidate['candidate_id'];
            
            // Check if pds_data exists for this candidate
            $stmt = $conn->prepare("SELECT pds_id FROM pds_data WHERE candidate_id = ?");
            $stmt->execute([$tempCandidateId]);
            $existingPDS = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPDS) {
                // Update existing pds_data with new file
                $stmt = $conn->prepare("UPDATE pds_data SET pds_file_blob = ?, pds_file_name = ?, pds_file_type = ?, pds_file_size = ?, updated_at = NOW() WHERE pds_id = ?");
                $stmt->execute([$pdsFileBlob, $_FILES['pds']['name'], $_FILES['pds']['type'], $_FILES['pds']['size'], $existingPDS['pds_id']]);
                $pdsId = $existingPDS['pds_id'];
            } else {
                // Create new pds_data
                $stmt = $conn->prepare("INSERT INTO pds_data (candidate_id, pds_file_blob, pds_file_name, pds_file_type, pds_file_size) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$tempCandidateId, $pdsFileBlob, $_FILES['pds']['name'], $_FILES['pds']['type'], $_FILES['pds']['size']]);
                $pdsId = $conn->lastInsertId();
            }
        } else {
            // Create new candidate
            $stmt = $conn->prepare("INSERT INTO candidates (first_name, last_name, email, source) VALUES (?, ?, ?, 'Website')");
            $stmt->execute([$result['data']['first_name'], $result['data']['last_name'], $result['data']['email']]);
            $tempCandidateId = $conn->lastInsertId();
            
            // Create pds_data with raw file
            $stmt = $conn->prepare("INSERT INTO pds_data (candidate_id, pds_file_blob, pds_file_name, pds_file_type, pds_file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tempCandidateId, $pdsFileBlob, $_FILES['pds']['name'], $_FILES['pds']['type'], $_FILES['pds']['size']]);
            $pdsId = $conn->lastInsertId();
        }
        
        $extractedData = $result['data'];
        $extractedData['pds_id'] = $pdsId;
        $extractedData['candidate_id'] = $tempCandidateId;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_submit'])) {
    try {
        $pdsId = $_POST['pds_id'];
        $candidateId = $_POST['candidate_id'];
        
        // Handle resume upload
        $resumeUrl = null;
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $resumeUrl = $_FILES['resume']['name'];
        }
        
        // Handle cover letter upload
        $coverLetterUrl = null;
        if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK) {
            $coverLetterUrl = $_FILES['cover_letter']['name'];
        }
        
        // Update candidate
        $stmt = $conn->prepare("UPDATE candidates SET first_name = ?, last_name = ?, phone = ?, address = ?, resume_url = ?, cover_letter_url = ? WHERE candidate_id = ?");
        $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['phone'], $_POST['address'], $resumeUrl, $coverLetterUrl, $candidateId]);
        
        $personalInfo = json_decode($_POST['personal_info'], true) ?? [];
        $educationData = json_decode($_POST['education_data'], true) ?? [];
        $workExpData = json_decode($_POST['work_experience_data'], true) ?? [];
        $skillsData = json_decode($_POST['skills_data'], true) ?? [];
        $certificationsData = json_decode($_POST['certifications_data'] ?? '[]', true) ?? [];
        $trainingsData = json_decode($_POST['trainings_data'] ?? '[]', true) ?? [];
        $referencesData = json_decode($_POST['references_data'] ?? '[]', true) ?? [];
        
        // Update pds_data
        $stmt = $conn->prepare("UPDATE pds_data SET 
            first_name = ?, surname = ?, middle_name = ?, email = ?, mobile = ?,
            date_of_birth = ?, place_of_birth = ?, sex = ?, civil_status = ?,
            citizenship_type = ?, height = ?, weight = ?, blood_type = ?,
            gsis_id = ?, pagibig_id = ?, philhealth_no = ?, sss_no = ?, tin_no = ?,
            residential_address = ?,
            education = ?, work_experience = ?, special_skills = ?,
            eligibility = ?, training = ?, `references` = ?,
            application_source = 'Website Application',
            updated_at = NOW() 
            WHERE pds_id = ?");
        $stmt->execute([
            $_POST['first_name'], $_POST['last_name'], $personalInfo['middle_name'] ?? null, $_POST['email'], $_POST['phone'],
            $personalInfo['date_of_birth'] ?? null, $personalInfo['place_of_birth'] ?? null, $personalInfo['gender'] ?? null, $personalInfo['civil_status'] ?? null,
            $personalInfo['citizenship'] ?? null, $personalInfo['height'] ?? null, $personalInfo['weight'] ?? null, $personalInfo['blood_type'] ?? null,
            $personalInfo['gsis_id'] ?? null, $personalInfo['pagibig_id'] ?? null, $personalInfo['philhealth_no'] ?? null, $personalInfo['sss_no'] ?? null, $personalInfo['tin_no'] ?? null,
            $_POST['address'],
            json_encode($educationData), json_encode($workExpData), json_encode($skillsData),
            json_encode($certificationsData), json_encode($trainingsData), json_encode($referencesData),
            $pdsId
        ]);
        
        $stmt = $conn->prepare("SELECT application_id FROM job_applications WHERE job_opening_id = ? AND candidate_id = ?");
        $stmt->execute([$job_id, $candidateId]);
        if ($stmt->fetch()) {
            throw new Exception('You have already applied for this position.');
        }
        
        $stmt = $conn->prepare("INSERT INTO job_applications (job_opening_id, candidate_id, application_date, status) VALUES (?, ?, NOW(), 'Applied')");
        $stmt->execute([$job_id, $candidateId]);
        
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #FCE4EC 0%, #fff 100%); min-height: 100vh; }
        .application-container { background: white; border-radius: 25px; box-shadow: 0 20px 60px rgba(233, 30, 99, 0.2); overflow: hidden; margin: 50px auto; max-width: 900px; }
        .application-header { background: linear-gradient(135deg, #E91E63 0%, #F06292 100%); color: white; padding: 40px; text-align: center; }
        .form-section { padding: 50px; }
        .upload-area { border: 3px dashed #E91E63; border-radius: 15px; padding: 60px 30px; text-align: center; background: #FCE4EC; cursor: pointer; transition: all 0.3s ease; }
        .upload-area:hover { background: #F8BBD0; border-color: #C2185B; }
        .upload-icon { font-size: 4rem; color: #E91E63; margin-bottom: 20px; }
        .btn-primary { background: linear-gradient(135deg, #E91E63 0%, #F06292 100%); border: none; padding: 12px 30px; border-radius: 25px; font-weight: 600; }
        .data-section { background: #f8f9fa; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="application-container">
            <?php if ($success): ?>
                <div class="application-header">
                    <h2><i class="fas fa-check-circle mr-3"></i>Application Submitted!</h2>
                </div>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle" style="font-size: 5rem; color: #28a745;"></i>
                    <h4>Application Submitted Successfully</h4>
                    <p class="text-muted mb-4">Your PDS has been analyzed and your application is being processed.</p>
                    <a href="public_jobs.php" class="btn btn-primary mt-4"><i class="fas fa-arrow-left mr-2"></i>Back to Job Listings</a>
                </div>
            <?php elseif ($extractedData): ?>
                <div class="application-header">
                    <h2><i class="fas fa-check-circle mr-3"></i>PDS Extracted Successfully</h2>
                    <p>Review and edit the extracted information below</p>
                </div>
                <div class="form-section">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-robot mr-2"></i>AI has extracted your PDS data. You can edit any field before submitting.
                        <button type="button" class="btn btn-sm btn-info float-right" onclick="toggleRawData()">
                            <i class="fas fa-code mr-1"></i>Show Extracted JSON
                        </button>
                    </div>
                    
                    <div id="rawDataPanel" class="alert alert-dark" style="display: none;">
                        <h6><i class="fas fa-database mr-2"></i>Raw Extracted Data</h6>
                        <pre style="max-height: 400px; overflow-y: auto; background: #2d3748; color: #68d391; padding: 15px; border-radius: 8px;"><?php echo json_encode($extractedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                    </div>
                    
                    <form method="POST" id="reviewForm" enctype="multipart/form-data">
                        <input type="hidden" name="confirm_submit" value="1">
                        <input type="hidden" name="pds_id" value="<?php echo htmlspecialchars($extractedData['pds_id']); ?>">
                        <input type="hidden" name="candidate_id" value="<?php echo htmlspecialchars($extractedData['candidate_id']); ?>">
                        <input type="hidden" name="personal_info" value="<?php echo htmlspecialchars(json_encode($extractedData['personal_info'] ?? [])); ?>">
                        <input type="hidden" name="education_data" value="<?php echo htmlspecialchars(json_encode($extractedData['education'] ?? [])); ?>">
                        <input type="hidden" name="work_experience_data" value="<?php echo htmlspecialchars(json_encode($extractedData['work_experience'] ?? [])); ?>">
                        <input type="hidden" name="skills_data" value="<?php echo htmlspecialchars(json_encode($extractedData['skills'] ?? [])); ?>">
                        <input type="hidden" name="certifications_data" value="<?php echo htmlspecialchars(json_encode($extractedData['certifications'] ?? [])); ?>">
                        <input type="hidden" name="trainings_data" value="<?php echo htmlspecialchars(json_encode($extractedData['trainings'] ?? [])); ?>">
                        <input type="hidden" name="references_data" value="<?php echo htmlspecialchars(json_encode($extractedData['references'] ?? [])); ?>">
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <label><i class="fas fa-file-alt mr-2"></i>Resume (Optional)</label>
                                <input type="file" name="resume" class="form-control-file" accept=".pdf,.doc,.docx">
                                <small class="text-muted">PDF, DOC, DOCX (Max 5MB)</small>
                            </div>
                            <div class="col-md-6">
                                <label><i class="fas fa-envelope mr-2"></i>Cover Letter (Optional)</label>
                                <input type="file" name="cover_letter" class="form-control-file" accept=".pdf,.doc,.docx">
                                <small class="text-muted">PDF, DOC, DOCX (Max 5MB)</small>
                            </div>
                        </div>
                        <div class="data-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5><i class="fas fa-user mr-2"></i>Personal Information</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleEdit('personal')">
                                    <i class="fas fa-edit mr-1"></i>Edit
                                </button>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" name="first_name" class="form-control editable-personal" value="<?php echo htmlspecialchars($extractedData['first_name']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" name="last_name" class="form-control editable-personal" value="<?php echo htmlspecialchars($extractedData['last_name']); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email (Private)</label>
                                        <input type="email" name="email" class="form-control editable-personal" value="<?php echo htmlspecialchars($extractedData['email']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input type="text" name="phone" class="form-control editable-personal" value="<?php echo htmlspecialchars($extractedData['phone'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <textarea name="address" class="form-control editable-personal" rows="2" readonly><?php echo htmlspecialchars($extractedData['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <?php if (!empty($extractedData['personal_info'])): ?>
                        <div class="data-section">
                            <h5><i class="fas fa-id-card mr-2"></i>Additional Personal Details</h5>
                            <?php if (!empty($extractedData['personal_info']['date_of_birth'])): ?>
                                <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($extractedData['personal_info']['date_of_birth']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($extractedData['personal_info']['gender'])): ?>
                                <p><strong>Gender:</strong> <?php echo htmlspecialchars($extractedData['personal_info']['gender']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($extractedData['personal_info']['civil_status'])): ?>
                                <p><strong>Civil Status:</strong> <?php echo htmlspecialchars($extractedData['personal_info']['civil_status']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($extractedData['personal_info']['citizenship'])): ?>
                                <p><strong>Citizenship:</strong> <?php echo htmlspecialchars($extractedData['personal_info']['citizenship']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($extractedData['education'])): ?>
                        <div class="data-section">
                            <h5><i class="fas fa-graduation-cap mr-2"></i>Education</h5>
                            <?php foreach ($extractedData['education'] as $edu): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars($edu['level'] ?? ''); ?>:</strong>
                                    <?php echo htmlspecialchars($edu['degree'] ?? ''); ?> at <?php echo htmlspecialchars($edu['school'] ?? ''); ?>
                                    <?php if (!empty($edu['year_graduated'])): ?>(<?php echo htmlspecialchars($edu['year_graduated']); ?>)<?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($extractedData['work_experience'])): ?>
                        <div class="data-section">
                            <h5><i class="fas fa-briefcase mr-2"></i>Work Experience</h5>
                            <?php foreach ($extractedData['work_experience'] as $exp): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars($exp['position'] ?? ''); ?></strong> at <?php echo htmlspecialchars($exp['company'] ?? ''); ?>
                                    <?php if (!empty($exp['from_date'])): ?>(<?php echo htmlspecialchars($exp['from_date']); ?> - <?php echo htmlspecialchars($exp['to_date'] ?? 'Present'); ?>)<?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($extractedData['skills'])): ?>
                        <div class="data-section">
                            <h5><i class="fas fa-tools mr-2"></i>Skills</h5>
                            <p><?php echo htmlspecialchars(implode(', ', $extractedData['skills'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($extractedData['certifications'])): ?>
                        <div class="data-section">
                            <h5><i class="fas fa-certificate mr-2"></i>Certifications & Licenses</h5>
                            <?php foreach ($extractedData['certifications'] as $cert): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars(is_array($cert) ? ($cert['name'] ?? '') : $cert); ?></strong>
                                    <?php if (is_array($cert) && !empty($cert['issuer'])): ?> - <?php echo htmlspecialchars($cert['issuer']); ?><?php endif; ?>
                                    <?php if (is_array($cert) && !empty($cert['date'])): ?> (<?php echo htmlspecialchars($cert['date']); ?>)<?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($extractedData['trainings'])): ?>
                        <div class="data-section">
                            <h5><i class="fas fa-chalkboard-teacher mr-2"></i>Training & Seminars</h5>
                            <?php foreach ($extractedData['trainings'] as $training): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars(is_array($training) ? ($training['title'] ?? '') : $training); ?></strong>
                                    <?php if (is_array($training) && !empty($training['organizer'])): ?> - <?php echo htmlspecialchars($training['organizer']); ?><?php endif; ?>
                                    <?php if (is_array($training) && !empty($training['date'])): ?> (<?php echo htmlspecialchars($training['date']); ?>)<?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($extractedData['references'])): ?>
                        <div class="data-section">
                            <h5><i class="fas fa-users mr-2"></i>Character References</h5>
                            <?php foreach ($extractedData['references'] as $ref): ?>
                                <div class="mb-3">
                                    <strong><?php echo htmlspecialchars($ref['name'] ?? ''); ?></strong>
                                    <?php if (!empty($ref['position'])): ?><br><small class="text-muted"><?php echo htmlspecialchars($ref['position']); ?></small><?php endif; ?>
                                    <?php if (!empty($ref['company'])): ?><br><small class="text-muted"><?php echo htmlspecialchars($ref['company']); ?></small><?php endif; ?>
                                    <?php if (!empty($ref['contact'])): ?><br><small class="text-muted">Contact: <?php echo htmlspecialchars($ref['contact']); ?></small><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane mr-2"></i>Confirm & Submit Application</button>
                            <a href="apply.php?job_id=<?php echo $job_id; ?>" class="btn btn-secondary btn-lg ml-3"><i class="fas fa-upload mr-2"></i>Upload Different PDS</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="application-header">
                    <h2><i class="fas fa-briefcase mr-3"></i><?php echo htmlspecialchars($job['title']); ?></h2>
                    <p><?php echo htmlspecialchars($job['department_name']); ?></p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger m-4"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <div class="form-section">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-robot mr-2"></i><strong>AI-Powered Application</strong><br>
                        Upload your PDS file. AI will extract and show you the data for review before submission.
                        <hr>
                        <small><strong>Supported Data:</strong> Personal Info, Education, Work Experience, Skills, Certifications, Training, References</small>
                        <hr>
                        <a href="uploads/pds_templates/PDS_Template.html" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-external-link-alt mr-1"></i>Open PDS Template</a>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="upload-area" onclick="document.getElementById('pdsFile').click()">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h5>Upload Your PDS File</h5>
                            <p class="text-muted">Click to browse</p>
                            <small class="text-muted">Accepted: JSON, PDF, DOC, DOCX (Max 5MB)</small>
                            <input type="file" name="pds" id="pdsFile" accept=".json,.pdf,.doc,.docx" required style="display: none;">
                        </div>
                        <div id="filePreview" class="mt-3"></div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <label><i class="fas fa-file-alt mr-2"></i>Resume (Optional)</label>
                                <input type="file" name="resume" class="form-control-file" accept=".pdf,.doc,.docx">
                                <small class="text-muted">PDF, DOC, DOCX (Max 5MB)</small>
                            </div>
                            <div class="col-md-6">
                                <label><i class="fas fa-envelope mr-2"></i>Cover Letter (Optional)</label>
                                <input type="file" name="cover_letter" class="form-control-file" accept=".pdf,.doc,.docx">
                                <small class="text-muted">PDF, DOC, DOCX (Max 5MB)</small>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="extractBtn"><i class="fas fa-robot mr-2"></i>Extract Data with AI</button>
                            <a href="public_jobs.php" class="btn btn-secondary btn-lg ml-3"><i class="fas fa-arrow-left mr-2"></i>Back</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleRawData() {
            const panel = document.getElementById('rawDataPanel');
            const btn = event.target.closest('button');
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                btn.innerHTML = '<i class="fas fa-eye-slash mr-1"></i>Hide Extracted JSON';
            } else {
                panel.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-code mr-1"></i>Show Extracted JSON';
            }
        }
        
        function toggleEdit(section) {
            const fields = document.querySelectorAll('.editable-' + section);
            const btn = event.target.closest('button');
            
            fields.forEach(field => {
                if (field.hasAttribute('readonly')) {
                    field.removeAttribute('readonly');
                    field.style.background = 'white';
                    btn.innerHTML = '<i class="fas fa-lock mr-1"></i>Lock';
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-outline-success');
                } else {
                    field.setAttribute('readonly', true);
                    field.style.background = '#f8f9fa';
                    btn.innerHTML = '<i class="fas fa-edit mr-1"></i>Edit';
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-outline-primary');
                }
            });
        }
        
        const fileInput = document.getElementById('pdsFile');
        const filePreview = document.getElementById('filePreview');
        
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    if (fileSize > 5) {
                        filePreview.innerHTML = '<div class="alert alert-danger">File too large. Maximum size is 5MB.</div>';
                        fileInput.value = '';
                        return;
                    }
                    filePreview.innerHTML = `<div class="alert alert-success"><i class="fas fa-file mr-2"></i>${file.name} (${fileSize} MB)</div>`;
                }
            });
        }
        
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function() {
                document.getElementById('extractBtn').innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Extracting...';
                document.getElementById('extractBtn').disabled = true;
            });
        }
    </script>
</body>
</html>
