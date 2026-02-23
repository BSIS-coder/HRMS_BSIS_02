<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Include database connection and helper functions
require_once 'config.php';

// For backward compatibility with existing code
$pdo = $conn;

// Get passed personal_info_id for linking from employment history
$linkedPersonalInfoId = isset($_GET['personal_info_id']) ? intval($_GET['personal_info_id']) : null;

// Handle form submissions
$message = '';
$messageType = '';

// File upload handler
function handleFileUpload($file, $targetDir) {
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetFile = $targetDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return '/uploads/' . basename($targetDir) . '/' . $fileName;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new personal information
                try {
                    $pdo->beginTransaction();
                    
                    // Check for duplicate Tax ID or GSIS ID
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM personal_information WHERE tax_id = ? OR gsis_id = ?");
                    $checkStmt->execute([$_POST['tax_id'], $_POST['gsis_id']]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $message = "Error: Tax ID or GSIS ID already exists!";
                        $messageType = "error";
                        $pdo->rollBack();
                    } else {
                        // Handle marital status document upload
                        $maritalDocUrl = null;
                        if (isset($_FILES['marital_status_document']) && $_FILES['marital_status_document']['error'] === 0) {
                            $maritalDocUrl = handleFileUpload($_FILES['marital_status_document'], 'uploads/marital_documents/');
                        }
                        
                        $stmt = $pdo->prepare("INSERT INTO personal_information (
                            first_name, last_name, date_of_birth, gender, marital_status, marital_status_date, 
                            spouse_name, marital_status_document, document_type, document_number, issuing_authority, nationality, tax_id, gsis_id, 
                            pag_ibig_id, philhealth_id, phone_number, 
                            emergency_contact_name, emergency_contact_relationship, emergency_contact_phone,
                            highest_education_level, field_of_study, institution_name, graduation_year, previous_job_experiences,
                            certifications, additional_training
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        $previousJobExperiences = !empty($_POST['previous_job_experiences']) ? json_encode(explode('\n\n', $_POST['previous_job_experiences'])) : null;
                        
                        $stmt->execute([
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_POST['date_of_birth'],
                            $_POST['gender'],
                            $_POST['marital_status'],
                            !empty($_POST['marital_status_date']) ? $_POST['marital_status_date'] : null,
                            $_POST['spouse_name'] ?? null,
                            $maritalDocUrl,
                            $_POST['document_type'] ?? null,
                            $_POST['document_number'] ?? null,
                            $_POST['issuing_authority'] ?? null,
                            $_POST['nationality'],
                            $_POST['tax_id'],
                            $_POST['gsis_id'],
                            $_POST['pag_ibig_id'] ?? null,
                            $_POST['philhealth_id'] ?? null,
                            $_POST['phone_number'],
                            $_POST['emergency_contact_name'],
                            $_POST['emergency_contact_relationship'],
                            $_POST['emergency_contact_phone'],
                            !empty($_POST['highest_education_level']) ? $_POST['highest_education_level'] : null,
                            $_POST['field_of_study'] ?? null,
                            $_POST['institution_name'] ?? null,
                            !empty($_POST['graduation_year']) ? $_POST['graduation_year'] : null,
                            $previousJobExperiences,
                            $_POST['certifications'] ?? null,
                            $_POST['additional_training'] ?? null
                        ]);
                        
                        $personalInfoId = $pdo->lastInsertId();
                        
                        // Add marital status history if provided
                        if (!empty($_POST['marital_status_date'])) {
                            $maritalStmt = $pdo->prepare("INSERT INTO marital_status_history (
                                personal_info_id, marital_status, status_date, spouse_name, 
                                supporting_document_type, document_url, document_number, issuing_authority, is_current
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                            
                            $maritalStmt->execute([
                                $personalInfoId,
                                $_POST['marital_status'],
                                $_POST['marital_status_date'],
                                $_POST['spouse_name'] ?? null,
                                $_POST['supporting_document_type'] ?? null,
                                $maritalDocUrl,
                                $_POST['document_number'] ?? null,
                                $_POST['issuing_authority'] ?? null
                            ]);
                        }
                        
                        $pdo->commit();
                        $message = "Personal information added successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Error adding personal information: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'update':
                // Update personal information
                try {
                    $pdo->beginTransaction();
                    
                    // Check for duplicate Tax ID or GSIS ID (excluding current record)
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM personal_information WHERE (tax_id = ? OR gsis_id = ?) AND personal_info_id != ?");
                    $checkStmt->execute([$_POST['tax_id'], $_POST['gsis_id'], $_POST['personal_info_id']]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $message = "Error: Tax ID or GSIS ID already exists!";
                        $messageType = "error";
                        $pdo->rollBack();
                    } else {
                        // Handle marital status document upload
                        $maritalDocUrl = $_POST['existing_marital_doc'] ?? null;
                        if (isset($_FILES['marital_status_document']) && $_FILES['marital_status_document']['error'] === 0) {
                            $maritalDocUrl = handleFileUpload($_FILES['marital_status_document'], 'uploads/marital_documents/');
                        }
                        
                        $previousJobExperiences = !empty($_POST['previous_job_experiences']) ? json_encode(explode('\n\n', $_POST['previous_job_experiences'])) : null;
                        
                        $stmt = $pdo->prepare("UPDATE personal_information SET 
                            first_name=?, last_name=?, date_of_birth=?, gender=?, marital_status=?, 
                            marital_status_date=?, spouse_name=?, marital_status_document=?, document_type=?, document_number=?, issuing_authority=?, nationality=?, tax_id=?, 
                            gsis_id=?, pag_ibig_id=?, philhealth_id=?, phone_number=?, 
                            emergency_contact_name=?, emergency_contact_relationship=?, emergency_contact_phone=?,
                            highest_education_level=?, field_of_study=?, institution_name=?, graduation_year=?, previous_job_experiences=?, certifications=?, additional_training=?
                            WHERE personal_info_id=?");
                        
                        $stmt->execute([
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_POST['date_of_birth'],
                            $_POST['gender'],
                            $_POST['marital_status'],
                            !empty($_POST['marital_status_date']) ? $_POST['marital_status_date'] : null,
                            $_POST['spouse_name'] ?? null,
                            $maritalDocUrl,
                            $_POST['document_type'] ?? null,
                            $_POST['document_number'] ?? null,
                            $_POST['issuing_authority'] ?? null,
                            $_POST['nationality'],
                            $_POST['tax_id'],
                            $_POST['gsis_id'],
                            $_POST['pag_ibig_id'] ?? null,
                            $_POST['philhealth_id'] ?? null,
                            $_POST['phone_number'],
                            $_POST['emergency_contact_name'],
                            $_POST['emergency_contact_relationship'],
                            $_POST['emergency_contact_phone'],
                            !empty($_POST['highest_education_level']) ? $_POST['highest_education_level'] : null,
                            $_POST['field_of_study'] ?? null,
                            $_POST['institution_name'] ?? null,
                            !empty($_POST['graduation_year']) ? $_POST['graduation_year'] : null,
                            $previousJobExperiences,
                            $_POST['certifications'] ?? null,
                            $_POST['additional_training'] ?? null,
                            $_POST['personal_info_id']
                        ]);
                        
                        $pdo->commit();
                        $message = "Personal information updated successfully!";
                        $messageType = "success";
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Error updating personal information: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_education':
                // Add educational background
                try {
                    $educationDocUrl = null;
                    if (isset($_FILES['education_document']) && $_FILES['education_document']['error'] === 0) {
                        $educationDocUrl = handleFileUpload($_FILES['education_document'], 'uploads/education_documents/');
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO educational_background (
                        personal_info_id, education_level, school_name, course_degree, major_specialization,
                        year_started, year_graduated, honors_awards, is_highest_attainment, document_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['education_level'],
                        $_POST['school_name'],
                        $_POST['course_degree'] ?? null,
                        $_POST['major_specialization'] ?? null,
                        !empty($_POST['year_started']) ? $_POST['year_started'] : null,
                        !empty($_POST['year_graduated']) ? $_POST['year_graduated'] : null,
                        $_POST['honors_awards'] ?? null,
                        isset($_POST['is_highest_attainment']) ? 1 : 0,
                        $educationDocUrl
                    ]);
                    
                    $message = "Educational background added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding educational background: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_certification':
                // Add employee certification
                try {
                    $certificationDocUrl = null;
                    if (isset($_FILES['certification_file']) && $_FILES['certification_file']['error'] === 0) {
                        $certificationDocUrl = handleFileUpload($_FILES['certification_file'], 'uploads/certifications/');
                    }
                    
                    if (!$certificationDocUrl) {
                        throw new Exception('Certification file upload failed!');
                    }
                    
                    $fileInfo = $_FILES['certification_file'];
                    $fileType = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
                    $fileSize = $fileInfo['size'];
                    
                    $stmt = $pdo->prepare("INSERT INTO employee_certifications (
                        personal_info_id, certification_name, issuing_organization, issue_date, expiry_date,
                        certification_number, file_path, file_type, file_size, obtained_before_joining, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['certification_name'],
                        $_POST['issuing_organization'],
                        $_POST['issue_date'],
                        !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                        $_POST['certification_number'] ?? null,
                        $certificationDocUrl,
                        $fileType,
                        $fileSize,
                        1, // obtained_before_joining = true
                        'Pending Verification'
                    ]);
                    
                    $message = "Certification added successfully!";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error adding certification: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
            
            case 'add_marital_history':
                // Add marital status history
                try {
                    $maritalDocUrl = null;
                    if (isset($_FILES['marital_history_document']) && $_FILES['marital_history_document']['error'] === 0) {
                        $maritalDocUrl = handleFileUpload($_FILES['marital_history_document'], 'uploads/marital_documents/');
                    }
                    
                    // Set all previous statuses to not current
                    $updateStmt = $pdo->prepare("UPDATE marital_status_history SET is_current = 0 WHERE personal_info_id = ?");
                    $updateStmt->execute([$_POST['personal_info_id']]);
                    
                    $stmt = $pdo->prepare("INSERT INTO marital_status_history (
                        personal_info_id, marital_status, status_date, spouse_name, supporting_document_type,
                        document_url, document_number, issuing_authority, remarks, is_current
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    
                    $stmt->execute([
                        $_POST['personal_info_id'],
                        $_POST['marital_status_new'],
                        $_POST['status_date'],
                        $_POST['spouse_name'] ?? null,
                        $_POST['supporting_document_type'] ?? null,
                        $maritalDocUrl,
                        $_POST['document_number'] ?? null,
                        $_POST['issuing_authority'] ?? null,
                        $_POST['remarks'] ?? null
                    ]);
                    
                    // Update personal_information table
                    $updatePersonalStmt = $pdo->prepare("UPDATE personal_information SET marital_status = ?, marital_status_date = ?, spouse_name = ?, marital_status_document = ?, document_type = ? WHERE personal_info_id = ?");
                    $updatePersonalStmt->execute([
                        $_POST['marital_status_new'],
                        $_POST['status_date'],
                        $_POST['spouse_name'] ?? null,
                        $maritalDocUrl,
                        $_POST['supporting_document_type'] ?? null,
                        $_POST['personal_info_id']
                    ]);
                    
                    $message = "Marital status history added successfully!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Error adding marital status history: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'delete':
                // Archive personal information instead of permanent delete
                try {
                    $pdo->beginTransaction();
                    
                    // Check if this person is linked to any employee profiles
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_profiles WHERE personal_info_id = ?");
                    $checkStmt->execute([$_POST['personal_info_id']]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $pdo->rollBack();
                        $message = "Error: Cannot delete personal information. This person is linked to an employee profile!";
                        $messageType = "error";
                    } else {
                        // Fetch the record to be archived
                        $fetchStmt = $pdo->prepare("SELECT * FROM personal_information WHERE personal_info_id = ?");
                        $fetchStmt->execute([$_POST['personal_info_id']]);
                        $recordToArchive = $fetchStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($recordToArchive) {
                            $archived_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                            
                            $employeeId = null;
                            $employeeCheck = $pdo->prepare("SELECT employee_id FROM employee_profiles WHERE personal_info_id = ? LIMIT 1");
                            $employeeCheck->execute([$_POST['personal_info_id']]);
                            $employeeResult = $employeeCheck->fetch(PDO::FETCH_ASSOC);
                            if ($employeeResult) {
                                $employeeId = $employeeResult['employee_id'];
                            }
                            
                            // Archive the record
                            $archiveStmt = $pdo->prepare("INSERT INTO archive_storage (
                                source_table, record_id, employee_id, archive_reason, archive_reason_details, 
                                archived_by, archived_at, can_restore, record_data, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1, ?, ?)");
                            
                            $archiveStmt->execute([
                                'personal_information',
                                $recordToArchive['personal_info_id'],
                                $employeeId,
                                'Data Cleanup',
                                'Personal information record deleted by user',
                                $archived_by,
                                json_encode($recordToArchive, JSON_PRETTY_PRINT),
                                'Personal information archived on deletion'
                            ]);
                            
                            // Delete from personal_information table
                            $deleteStmt = $pdo->prepare("DELETE FROM personal_information WHERE personal_info_id=?");
                            $deleteStmt->execute([$_POST['personal_info_id']]);
                            
                            $pdo->commit();
                            $message = "Personal information archived successfully! You can view it in Archive Storage.";
                            $messageType = "success";
                        } else {
                            $pdo->rollBack();
                            $message = "Error: Record not found!";
                            $messageType = "error";
                        }
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Error archiving personal information: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Fetch all personal information with education and marital status
$stmt = $pdo->query("
    SELECT pi.*,
           CONCAT(pi.first_name, ' ', pi.last_name) as full_name,
           TIMESTAMPDIFF(YEAR, pi.date_of_birth, CURDATE()) as age
    FROM personal_information pi
    ORDER BY pi.personal_info_id DESC
");
$personalInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Information Management - HR System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        /* [Previous CSS styles remain the same] */
        :root {
            --azure-blue: #E91E63;
            --azure-blue-light: #F06292;
            --azure-blue-dark: #C2185B;
            --azure-blue-lighter: #F8BBD0;
            --azure-blue-pale: #FCE4EC;
        }

        .section-title {
            color: var(--azure-blue);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        body {
            background: var(--azure-blue-pale);
        }

        .main-content {
            background: var(--azure-blue-pale);
            padding: 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 14px;
            margin: 0 3px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: linear-gradient(135deg, var(--azure-blue-lighter) 0%, #e9ecef 100%);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--azure-blue-dark);
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: var(--azure-blue-lighter);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-single {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-married {
            background: #d4edda;
            color: #155724;
        }

        .status-divorced {
            background: #f8d7da;
            color: #721c24;
        }

        .status-widowed {
            background: #e2e3e5;
            color: #383d41;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--azure-blue) 0%, var(--azure-blue-light) 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            opacity: 0.7;
        }

        .close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azure-blue-dark);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--azure-blue);
            outline: none;
            box-shadow: 0 0 10px rgba(233, 30, 99, 0.3);
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-col {
            flex: 1;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section-divider {
            border-top: 2px solid #e0e0e0;
            margin: 30px 0 20px 0;
            padding-top: 20px;
        }

        .section-header {
            font-size: 18px;
            font-weight: 600;
            color: var(--azure-blue-dark);
            margin-bottom: 20px;
        }

        .education-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .document-link {
            color: var(--azure-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .document-link:hover {
            text-decoration: underline;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
        }

        .nav-buttons-section {
            background: linear-gradient(135deg, var(--azure-blue-pale) 0%, #f0f0f0 100%);
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid var(--azure-blue);
        }

        .nav-buttons-section h4 {
            color: var(--azure-blue-dark);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .nav-button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-button-group .btn {
            flex: 1;
            min-width: 140px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .form-row {
                flex-direction: column;
            }

            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'navigation.php'; ?>
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="main-content">
                <h2 class="section-title">📋 Personal Information Management</h2>
                <div class="content">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="controls">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" placeholder="Search by name, phone, education...">
                        </div>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            👤 Add New Person
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="table" id="personalInfoTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Age/DOB</th>
                                    <th>Gender</th>
                                    <th>Marital Status</th>
                                    <th>Education</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="personalInfoTableBody">
                                <?php foreach ($personalInfo as $person): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($person['personal_info_id']) ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($person['full_name']) ?></strong><br>
                                            <small style="color: #666;">🌍 <?= htmlspecialchars($person['nationality']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= $person['age'] ?> years</strong><br>
                                            <small style="color: #666;">🎂 <?= date('M d, Y', strtotime($person['date_of_birth'])) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($person['gender']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($person['marital_status']) ?>">
                                            <?= htmlspecialchars($person['marital_status']) ?>
                                        </span>
                                        <?php if ($person['marital_status_date']): ?>
                                            <br><small style="color: #666;">📅 <?= date('Y', strtotime($person['marital_status_date'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($person['highest_education_level']): ?>
                                            <span class="education-badge">🎓 <?= htmlspecialchars($person['highest_education_level']) ?></span>
                                            <?php if ($person['institution_name']): ?>
                                                <br><small style="color: #666;"><?= htmlspecialchars($person['institution_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <small style="color: #999;">No education recorded</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>📞 <?= htmlspecialchars($person['phone_number']) ?></strong><br>
                                            <?php if ($person['emergency_contact_name']): ?>
                                                <small style="color: #666;">🚨 <?= htmlspecialchars($person['emergency_contact_name']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-small" onclick="viewDetails(<?= $person['personal_info_id'] ?>)">
                                            👁️ View
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="editPerson(<?= $person['personal_info_id'] ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="btn btn-primary btn-small" onclick="printPDS(<?= $person['personal_info_id'] ?>)">
                                            🖨️ Print PDS
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="deletePerson(<?= $person['personal_info_id'] ?>, '<?= htmlspecialchars(addslashes($person['full_name'])) ?>')">
                                            📦 Archive
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($personalInfo)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                                        No personal information records found. Click "Add New Person" to get started.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Personal Information Modal -->
    <div id="personalInfoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Person</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="personalInfoForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="personal_info_id" name="personal_info_id">

                    <!-- Basic Information Section -->
                    <div class="section-header">📋 Basic Information</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth *</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="nationality">Nationality *</label>
                                <input type="text" id="nationality" name="nationality" class="form-control" value="Filipino" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="phone_number">Phone Number *</label>
                                <input type="tel" id="phone_number" name="phone_number" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <!-- Marital Status Section -->
                    <div class="section-divider"></div>
                    <div class="section-header">💍 Marital Status</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="marital_status">Marital Status *</label>
                                <select id="marital_status" name="marital_status" class="form-control" required>
                                    <option value="">Select Status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="marital_status_date">Marital Status Date</label>
                                <input type="date" id="marital_status_date" name="marital_status_date" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="spouse_name">Spouse Name</label>
                                <input type="text" id="spouse_name" name="spouse_name" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="marital_status_document">Marital Status Document</label>
                                <input type="file" id="marital_status_document" name="marital_status_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <input type="hidden" id="existing_marital_doc" name="existing_marital_doc">
                                <small style="color: #666;">Upload marriage certificate, divorce decree, etc.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="supporting_document_type">Document Type</label>
                                <select id="supporting_document_type" name="supporting_document_type" class="form-control">
                                    <option value="">Select Type</option>
                                    <option value="Marriage Certificate">Marriage Certificate</option>
                                    <option value="Divorce Decree">Divorce Decree</option>
                                    <option value="Death Certificate">Death Certificate</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="document_number">Document Number</label>
                                <input type="text" id="document_number" name="document_number" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="issuing_authority">Issuing Authority</label>
                        <input type="text" id="issuing_authority" name="issuing_authority" class="form-control">
                    </div>

                    <!-- Identification Section -->
                    <div class="section-divider"></div>
                    <div class="section-header">🆔 Identification</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="tax_id">Tax ID *</label>
                                <input type="text" id="tax_id" name="tax_id" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="gsis_id">GSIS ID (Government Service Insurance System)</label>
                                <input type="text" id="gsis_id" name="gsis_id" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="pag_ibig_id">Pag-IBIG ID</label>
                                <input type="text" id="pag_ibig_id" name="pag_ibig_id" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="philhealth_id">PhilHealth ID</label>
                                <input type="text" id="philhealth_id" name="philhealth_id" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Previous Work Experience Section -->
                    <div class="section-divider"></div>
                    <div class="section-header">💼 Previous Work Experience</div>
                    <div class="form-group">
                        <label for="previous_job_experiences">Previous Job Experiences (One job per line, separate with blank line)</label>
                        <textarea id="previous_job_experiences" name="previous_job_experiences" class="form-control" rows="6" placeholder="Job Title: Senior Accountant&#10;Company: ABC Corporation&#10;Duration: 2015-2020&#10;Responsibilities: Financial reporting, audit&#10;&#10;Job Title: Accountant&#10;Company: XYZ Company&#10;Duration: 2010-2015&#10;Responsibilities: General accounting, reconciliation"></textarea>
                    </div>

                    <!-- Emergency Contact Section -->
                    <div class="section-divider"></div>
                    <div class="section-header">🚨 Emergency Contact</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name *</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="emergency_contact_relationship">Relationship *</label>
                                <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_phone">Emergency Contact Phone *</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" required>
                    </div>

                    <!-- Education Section -->
                    <div class="section-divider"></div>
                    <div class="section-header">🎓 Education</div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="highest_education_level">Highest Educational Attainment</label>
                                <select id="highest_education_level" name="highest_education_level" class="form-control">
                                    <option value="">Select Level</option>
                                    <option value="Elementary">Elementary</option>
                                    <option value="High School">High School</option>
                                    <option value="Senior High School">Senior High School</option>
                                    <option value="Vocational">Vocational</option>
                                    <option value="Associate Degree">Associate Degree</option>
                                    <option value="Bachelor's Degree">Bachelor's Degree</option>
                                    <option value="Master's Degree">Master's Degree</option>
                                    <option value="Doctorate">Doctorate</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="field_of_study">Course/Degree</label>
                                <input type="text" id="field_of_study" name="field_of_study" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="institution_name">School/University</label>
                                <input type="text" id="institution_name" name="institution_name" class="form-control">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="graduation_year">Year Graduated</label>
                                <input type="number" id="graduation_year" name="graduation_year" class="form-control" min="1900" max="<?= date('Y') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Certifications Section -->
                    <div class="section-divider"></div>
                    <div class="section-header">🏆 Professional Certifications</div>
                    <div class="form-group">
                        <label for="certifications">Certifications (One per line or comma-separated)</label>
                        <textarea id="certifications" name="certifications" class="form-control" rows="3" placeholder="Certified Public Accountant (CPA)&#10;Project Management Professional (PMP)&#10;ITIL Foundation Certification"></textarea>
                    </div>

                    <!-- Additional Training Section -->
                    <div class="section-divider"></div>
                    <div class="section-header">📚 Additional Training</div>
                    <div class="form-group">
                        <label for="additional_training">Training Programs (One per line or comma-separated)</label>
                        <textarea id="additional_training" name="additional_training" class="form-control" rows="3" placeholder="Advanced Excel Training&#10;Leadership Workshop&#10;Agile Scrum Master Training"></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <button type="button" class="btn" style="background: #6c757d; color: white; margin-right: 10px;" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">💾 Save Information</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Personal Information Details</h2>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div class="modal-body" id="viewDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div style="padding: 20px 30px; border-top: 1px solid #e0e0e0; text-align: center;">
                <button class="btn btn-success" id="printPDSFromView" onclick="printPDSFromViewModal()" style="display: none;">
                    🖨️ Print PDS
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let personalInfoData = <?= json_encode($personalInfo) ?>;
        
        // Fetch education and marital history data
        <?php
        $educationStmt = $pdo->query("SELECT * FROM educational_background ORDER BY personal_info_id, year_graduated DESC");
        $educationData = $educationStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $maritalStmt = $pdo->query("SELECT * FROM marital_status_history ORDER BY personal_info_id, status_date DESC");
        $maritalHistoryData = $maritalStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $certificationStmt = $pdo->query("SELECT * FROM employee_certifications WHERE obtained_before_joining = 1 ORDER BY personal_info_id, issue_date DESC");
        $employeeCertifications = $certificationStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        let educationData = <?= json_encode($educationData) ?>;
        let maritalHistoryData = <?= json_encode($maritalHistoryData) ?>;
        let employeeCertifications = <?= json_encode($employeeCertifications) ?>;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableBody = document.getElementById('personalInfoTableBody');
            const rows = tableBody.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Modal functions
        function openModal(mode, personalInfoId = null) {
            const modal = document.getElementById('personalInfoModal');
            const form = document.getElementById('personalInfoForm');
            const title = document.getElementById('modalTitle');
            const action = document.getElementById('action');

            if (mode === 'add') {
                title.textContent = 'Add New Person';
                action.value = 'add';
                form.reset();
                document.getElementById('personal_info_id').value = '';
                document.getElementById('nationality').value = 'Filipino';
            } else if (mode === 'edit' && personalInfoId) {
                title.textContent = 'Edit Personal Information';
                action.value = 'update';
                document.getElementById('personal_info_id').value = personalInfoId;
                populateEditForm(personalInfoId);
            }

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('personalInfoModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function closeViewModal() {
            const modal = document.getElementById('viewDetailsModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function populateEditForm(personalInfoId) {
            const person = personalInfoData.find(p => p.personal_info_id == personalInfoId);
            if (person) {
                document.getElementById('first_name').value = person.first_name || '';
                document.getElementById('last_name').value = person.last_name || '';
                document.getElementById('date_of_birth').value = person.date_of_birth || '';
                document.getElementById('gender').value = person.gender || '';
                document.getElementById('nationality').value = person.nationality || '';
                document.getElementById('phone_number').value = person.phone_number || '';
                document.getElementById('marital_status').value = person.marital_status || '';
                document.getElementById('marital_status_date').value = person.marital_status_date || '';
                document.getElementById('tax_id').value = person.tax_id || '';
                document.getElementById('gsis_id').value = person.gsis_id || '';
                document.getElementById('pag_ibig_id').value = person.pag_ibig_id || '';
                document.getElementById('philhealth_id').value = person.philhealth_id || '';
                document.getElementById('emergency_contact_name').value = person.emergency_contact_name || '';
                document.getElementById('emergency_contact_relationship').value = person.emergency_contact_relationship || '';
                document.getElementById('emergency_contact_phone').value = person.emergency_contact_phone || '';
                document.getElementById('highest_education_level').value = person.highest_education_level || '';
                document.getElementById('field_of_study').value = person.field_of_study || '';
                document.getElementById('institution_name').value = person.institution_name || '';
                document.getElementById('graduation_year').value = person.graduation_year || '';
                document.getElementById('certifications').value = person.certifications || '';
                document.getElementById('additional_training').value = person.additional_training || '';
                if (person.marital_status_document) {
                    document.getElementById('existing_marital_doc').value = person.marital_status_document;
                }
            }
        }

        function editPerson(personalInfoId) {
            openModal('edit', personalInfoId);
        }

        function deletePerson(personalInfoId, fullName) {
            if (confirm(`Are you sure you want to delete the personal information for "${fullName}"? This will archive the record.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="personal_info_id" value="${personalInfoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Store current viewing person ID for print function
        let currentViewingPersonId = null;

        function viewDetails(personalInfoId) {
            currentViewingPersonId = personalInfoId;
            const person = personalInfoData.find(p => p.personal_info_id == personalInfoId);
            if (!person) return;

            const personEducation = educationData.filter(e => e.personal_info_id == personalInfoId);
            const personMaritalHistory = maritalHistoryData.filter(m => m.personal_info_id == personalInfoId);
            const personCertifications = employeeCertifications.filter(c => c.personal_info_id == personalInfoId);
            
            // Show print button
            document.getElementById('printPDSFromView').style.display = 'inline-block';

            let html = `
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">${person.first_name} ${person.last_name}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value">${person.date_of_birth ? new Date(person.date_of_birth).toLocaleDateString() : 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Age</div>
                        <div class="info-value">${person.age} years</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Gender</div>
                        <div class="info-value">${person.gender || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Nationality</div>
                        <div class="info-value">${person.nationality || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value">${person.phone_number || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tax ID</div>
                        <div class="info-value">${person.tax_id || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">GSIS ID</div>
                        <div class="info-value">${person.gsis_id || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Pag-IBIG ID</div>
                        <div class="info-value">${person.pag_ibig_id || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">PhilHealth ID</div>
                        <div class="info-value">${person.philhealth_id || 'N/A'}</div>
                    </div>
                </div>

                <div class="section-divider"></div>
                <div class="section-header">💍 Marital Status</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">${person.marital_status || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status Date</div>
                        <div class="info-value">${person.marital_status_date ? new Date(person.marital_status_date).toLocaleDateString() : 'N/A'}</div>
                    </div>
                </div>

                ${personMaritalHistory.length > 0 ? `
                    <div class="section-divider"></div>
                    <div class="section-header">📜 Marital History</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Spouse Name</th>
                                <th>Document Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${personMaritalHistory.map(m => `
                                <tr>
                                    <td>${m.marital_status}</td>
                                    <td>${m.status_date ? new Date(m.status_date).toLocaleDateString() : 'N/A'}</td>
                                    <td>${m.spouse_name || 'N/A'}</td>
                                    <td>${m.supporting_document_type || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : ''}

                <div class="section-divider"></div>
                <div class="section-header">🚨 Emergency Contact</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value">${person.emergency_contact_name || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Relationship</div>
                        <div class="info-value">${person.emergency_contact_relationship || 'N/A'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value">${person.emergency_contact_phone || 'N/A'}</div>
                    </div>
                </div>

                <div class="section-divider"></div>
                <div class="section-header">🎓 Education</div>
                ${person.highest_education_level ? `
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Highest Attainment</div>
                            <div class="info-value">${person.highest_education_level}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Course/Degree</div>
                            <div class="info-value">${person.field_of_study || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">School/University</div>
                            <div class="info-value">${person.institution_name || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Year Graduated</div>
                            <div class="info-value">${person.graduation_year || 'N/A'}</div>
                        </div>
                    </div>
                ` : '<p>No education information recorded.</p>'}

                ${personEducation.length > 0 ? `
                    <div class="section-divider"></div>
                    <div class="section-header">📚 Educational Background</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th>School</th>
                                <th>Course/Degree</th>
                                <th>Year Graduated</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${personEducation.map(e => `
                                <tr>
                                    <td>${e.education_level}</td>
                                    <td>${e.school_name || 'N/A'}</td>
                                    <td>${e.course_degree || 'N/A'}</td>
                                    <td>${e.year_graduated || 'N/A'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : ''}

                ${person.certifications ? `
                    <div class="section-divider"></div>
                    <div class="section-header">🏆 Professional Certifications</div>
                    <div class="info-item">
                        <div style="line-height: 1.8; color: #333;">
                            ${person.certifications.split(',').map(cert => `<div>• ${cert.trim()}</div>`).join('')}
                        </div>
                    </div>
                ` : ''}

                ${person.additional_training ? `
                    <div class="section-divider"></div>
                    <div class="section-header">📚 Additional Training</div>
                    <div class="info-item">
                        <div style="line-height: 1.8; color: #333;">
                            ${person.additional_training.split(',').map(training => `<div>• ${training.trim()}</div>`).join('')}
                        </div>
                    </div>
                ` : ''}

                ${personCertifications.length > 0 ? `
                    <div class="section-divider"></div>
                    <div class="section-header">🏆 Pre-Employment Certifications</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Certification Name</th>
                                <th>Issuing Organization</th>
                                <th>Issued Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Document</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${personCertifications.map(c => `
                                <tr>
                                    <td>${c.certification_name || 'N/A'}</td>
                                    <td>${c.issuing_organization || 'N/A'}</td>
                                    <td>${c.issue_date ? new Date(c.issue_date).toLocaleDateString() : 'N/A'}</td>
                                    <td>${c.expiry_date ? new Date(c.expiry_date).toLocaleDateString() : 'N/A (No Expiry)'}</td>
                                    <td><span class="status-badge" style="background: ${c.status === 'Verified' ? '#d4edda' : '#fff3cd'}; color: ${c.status === 'Verified' ? '#155724' : '#856404'};">${c.status}</span></td>
                                    <td><a href="${c.file_path}" target="_blank" class="document-link">📄 View</a></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : ''}
            `;

            // Add navigation section
            html += `
                <div class="nav-buttons-section">
                    <h4>📋 Related Information</h4>
                    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Access related records for this person:</p>
                    <div class="nav-button-group">
                        <a href="employment_history.php?personal_info_id=${personalInfoId}" class="btn btn-success" title="View employment history">
                            📊 Employment History
                        </a>
                    </div>
                </div>
            `;

            document.getElementById('viewDetailsContent').innerHTML = html;
            document.getElementById('viewDetailsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function printPDSFromViewModal() {
            if (currentViewingPersonId) {
                printPDS(currentViewingPersonId);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const personalInfoModal = document.getElementById('personalInfoModal');
            const viewDetailsModal = document.getElementById('viewDetailsModal');
            if (event.target === personalInfoModal) {
                closeModal();
            }
            if (event.target === viewDetailsModal) {
                closeViewModal();
            }
        }

        // Form validation
        document.getElementById('personalInfoForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            
            if (firstName.length < 2) {
                e.preventDefault();
                alert('First name must be at least 2 characters long');
                return;
            }
            
            if (lastName.length < 2) {
                e.preventDefault();
                alert('Last name must be at least 2 characters long');
                return;
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set max date for birth date (today)
            const today = new Date().toISOString().split('T')[0];
            const dateOfBirthInput = document.getElementById('date_of_birth');
            if (dateOfBirthInput) {
                dateOfBirthInput.setAttribute('max', today);
                
                // Set min date for birth date (120 years ago)
                const minDate = new Date();
                minDate.setFullYear(minDate.getFullYear() - 120);
                dateOfBirthInput.setAttribute('min', minDate.toISOString().split('T')[0]);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // ESC to close modal
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
            }
            
            // Ctrl+N to add new person
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openModal('add');
            }
        });

        // Age calculator helper function
        function calculateAge(birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            
            return age;
        }

        // Live age calculation on birth date change
        const dateOfBirthInput = document.getElementById('date_of_birth');
        if (dateOfBirthInput) {
            dateOfBirthInput.addEventListener('change', function() {
                const birthDate = this.value;
                if (birthDate) {
                    const age = calculateAge(birthDate);
                    console.log(`Age will be: ${age} years`);
                }
            });
        }

        // Print PDS function
        function printPDS(personalInfoId) {
            const person = personalInfoData.find(p => p.personal_info_id == personalInfoId);
            if (!person) {
                alert('Person not found!');
                return;
            }

            const personEducation = educationData.filter(e => e.personal_info_id == personalInfoId);
            const personMaritalHistory = maritalHistoryData.filter(m => m.personal_info_id == personalInfoId);

            // Format date helper
            function formatDate(dateString) {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            }

            // Get Pag-IBIG ID
            const pagIbigId = person.pag_ibig_id || 'N/A';

            // Generate PDS HTML
            let pdsHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Data Sheet - ${person.first_name} ${person.last_name}</title>
    <style>
        @media print {
            @page {
                size: A4;
                margin: 0.8cm;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-after: always;
            }
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
            background: #fff;
            padding: 10px;
        }
        .pds-header {
            text-align: center;
            border: 2px solid #000;
            padding: 8px;
            margin-bottom: 10px;
        }
        .pds-header h1 {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .pds-header p {
            font-size: 8pt;
            margin: 0;
        }
        .photo-section {
            float: right;
            width: 100px;
            height: 120px;
            border: 1px solid #000;
            margin: 0 0 10px 10px;
            text-align: center;
            padding: 5px;
            background: #f9f9f9;
        }
        .photo-section p {
            font-size: 7pt;
            margin-top: 85px;
        }
        .section {
            margin-bottom: 10px;
            clear: both;
        }
        .section-title {
            background: #000;
            color: #fff;
            padding: 4px 8px;
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8.5pt;
        }
        .info-table td {
            padding: 3px 6px;
            border: 1px solid #000;
            vertical-align: top;
        }
        .info-table td.label {
            width: 30%;
            background: #f0f0f0;
            font-weight: bold;
            font-size: 8pt;
        }
        .info-table td.value {
            width: 70%;
            font-size: 8.5pt;
        }
        .info-table thead td {
            padding: 4px 6px;
            font-size: 8pt;
        }
        .signature-section {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 35px;
            padding-top: 3px;
            font-size: 8pt;
        }
        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 7pt;
            color: #666;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .print-button:hover {
            background: #218838;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print PDS</button>
    
    <div class="pds-header">
        <h1>Personal Data Sheet</h1>
        <p>Republic of the Philippines</p>
    </div>

    <div class="photo-section">
        <p>Paste ID Picture Here<br>(4.5 cm x 3.5 cm)</p>
    </div>

    <div class="section">
        <div class="section-title">I. Personal Information</div>
        <table class="info-table">
            <tr>
                <td class="label">1. SURNAME</td>
                <td class="value">${person.last_name || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">2. FIRST NAME</td>
                <td class="value">${person.first_name || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">3. MIDDLE NAME</td>
                <td class="value">N/A</td>
            </tr>
            <tr>
                <td class="label">4. DATE OF BIRTH</td>
                <td class="value">${formatDate(person.date_of_birth)}</td>
            </tr>
            <tr>
                <td class="label">6. GENDER</td>
                <td class="value">${person.gender || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">7. CIVIL STATUS</td>
                <td class="value">${person.marital_status || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">8. NATIONALITY</td>
                <td class="value">${person.nationality || 'N/A'}</td>
            </tr>

        </table>
    </div>

    <div class="section">
        <div class="section-title">II. Contact Information</div>
        <table class="info-table">
            <tr>
                <td class="label">14. TELEPHONE NO.</td>
                <td class="value">${person.phone_number || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">15. MOBILE NO.</td>
                <td class="value">${person.phone_number || 'N/A'}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">III. Educational Background</div>
        <table class="info-table">
            <thead>
                <tr>
                    <td class="label" style="text-align: center; font-weight: bold;">LEVEL</td>
                    <td class="label" style="text-align: center; font-weight: bold;">NAME OF SCHOOL</td>
                    <td class="label" style="text-align: center; font-weight: bold;">COURSE/DEGREE</td>
                    <td class="label" style="text-align: center; font-weight: bold;">YEAR GRADUATED</td>
                </tr>
            </thead>
            <tbody>
                ${personEducation.length > 0 ? personEducation.slice(0, 3).map(e => `
                    <tr>
                        <td class="value">${e.education_level || 'N/A'}</td>
                        <td class="value">${e.school_name || 'N/A'}</td>
                        <td class="value">${e.course_degree || 'N/A'}</td>
                        <td class="value">${e.year_graduated || 'N/A'}</td>
                    </tr>
                `).join('') : ''}
                ${person.highest_education_level && !personEducation.some(e => e.education_level === person.highest_education_level) ? `
                    <tr>
                        <td class="value">${person.highest_education_level}</td>
                        <td class="value">${person.institution_name || 'N/A'}</td>
                        <td class="value">${person.field_of_study || 'N/A'}</td>
                        <td class="value">${person.graduation_year || 'N/A'}</td>
                    </tr>
                ` : ''}
                ${personEducation.length === 0 && !person.highest_education_level ? `
                    <tr>
                        <td class="value" colspan="4" style="text-align: center;">No educational background recorded</td>
                    </tr>
                ` : ''}
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">IV. Government Issued ID</div>
        <table class="info-table">
            <tr>
                <td class="label">17. TAX ID (TIN)</td>
                <td class="value">${person.tax_id || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">18. GOVERNMENT SERVICE INSURANCE SYSTEM (GSIS)</td>
                <td class="value">${person.gsis_id || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">19. PAG-IBIG ID</td>
                <td class="value">${pagIbigId}</td>
            </tr>
            <tr>
                <td class="label">20. PHILHEALTH ID</td>
                <td class="value">${person.philhealth_id || 'N/A'}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">V. Emergency Contact</div>
        <table class="info-table">
            <tr>
                <td class="label">21. NAME</td>
                <td class="value">${person.emergency_contact_name || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">22. RELATIONSHIP</td>
                <td class="value">${person.emergency_contact_relationship || 'N/A'}</td>
            </tr>
            <tr>
                <td class="label">23. CONTACT NUMBER</td>
                <td class="value">${person.emergency_contact_phone || 'N/A'}</td>
            </tr>
        </table>
    </div>

    ${personMaritalHistory.length > 0 ? `
    <div class="section">
        <div class="section-title">VI. Marital Status History</div>
        <table class="info-table">
            <thead>
                <tr>
                    <td class="label" style="text-align: center; font-weight: bold;">STATUS</td>
                    <td class="label" style="text-align: center; font-weight: bold;">DATE</td>
                    <td class="label" style="text-align: center; font-weight: bold;">SPOUSE NAME</td>
                    <td class="label" style="text-align: center; font-weight: bold;">DOCUMENT TYPE</td>
                </tr>
            </thead>
            <tbody>
                ${personMaritalHistory.slice(0, 2).map(m => `
                    <tr>
                        <td class="value">${m.marital_status || 'N/A'}</td>
                        <td class="value">${formatDate(m.status_date)}</td>
                        <td class="value">${m.spouse_name || 'N/A'}</td>
                        <td class="value">${m.supporting_document_type || 'N/A'}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
    ` : ''}

    ${person.certifications ? `
    <div class="section">
        <div class="section-title">VII. Professional Certifications</div>
        <table class="info-table">
            <tr>
                <td class="label">Certifications</td>
                <td class="value">${person.certifications.split(',').map(c => c.trim()).join('<br>')}</td>
            </tr>
        </table>
    </div>
    ` : ''}

    ${person.additional_training ? `
    <div class="section">
        <div class="section-title">VIII. Additional Training</div>
        <table class="info-table">
            <tr>
                <td class="label">Training Programs</td>
                <td class="value">${person.additional_training.split(',').map(t => t.trim()).join('<br>')}</td>
            </tr>
        </table>
    </div>
    ` : ''}

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <strong>Signature of Employee</strong>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <strong>Date</strong>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>This Personal Data Sheet is generated on ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
    </div>
</body>
</html>
            `;

            // Open print window
            const printWindow = window.open('', '_blank');
            printWindow.document.write(pdsHTML);
            printWindow.document.close();
            
            // Wait for content to load, then trigger print
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.print();
                }, 250);
            };
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
