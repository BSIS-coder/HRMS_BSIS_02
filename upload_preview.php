<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $response = ['success' => false, 'message' => '', 'document_id' => null];
    
    try {
        $file = $_FILES['file'];
        $docType = $_POST['doc_type'] ?? 'Other';
        $candidateName = $_POST['candidate_name'] ?? 'Preview User';
        $uploadContext = $_POST['upload_context'] ?? ''; // To distinguish education vs certification docs
        
        // Temporarily disable foreign key checks and create document record
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        $expiryDate = date('Y-m-d', strtotime('+30 days')); // 30 days from now
        $stmt = $conn->prepare("INSERT INTO document_management (employee_id, document_type, document_name, file_path, document_status, expiry_date, notes) VALUES (0, ?, ?, '', 'Pending Review', ?, ?)");
        $stmt->execute([
            $docType,
            $docType . ' - ' . $candidateName,
            $expiryDate,
            'Document uploaded during job application process by ' . $candidateName . '. Status: Pending application submission. Will be processed when application is completed.'
        ]);

        
        $document_id = $conn->lastInsertId();
        
        // Save the actual file
        $uploadsDir = 'uploads/documents/';
        $yearMonth = date('Y/m');
        $fullUploadDir = $uploadsDir . $yearMonth . '/';
        
        if (!file_exists($fullUploadDir)) {
            mkdir($fullUploadDir, 0755, true);
        }
        
        $fileName = 'doc_' . $document_id . '_' . strtolower(str_replace(' ', '_', $docType)) . '.pdf';
        $filePath = $fullUploadDir . $fileName;
        
        // Save file and update record
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $stmt = $conn->prepare("UPDATE document_management SET file_path = ? WHERE document_id = ?");
            $stmt->execute([$filePath, $document_id]);
        }
        
        // Re-enable foreign key checks
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        // Update job application notes to mark document as uploaded
        $jobId = $_POST['job_id'] ?? null;
        if ($jobId) {
            // Find the most recent application for this job
            $stmt = $conn->prepare("SELECT application_id, notes FROM job_applications WHERE job_opening_id = ? ORDER BY application_date DESC LIMIT 1");
            $stmt->execute([$jobId]);
            $app = $stmt->fetch();
            
            if ($app) {
                $notes = $app['notes'] ?? '';
                $docFlag = '';
                
                // Determine which document flag to update based on doc type and context
                if ($docType === 'Contract') {
                    $docFlag = 'Work Docs:';
                } elseif ($docType === 'License') {
                    $docFlag = 'License Docs:';
                } elseif ($docType === 'Certificate') {
                    // Use upload context to distinguish between education and certification
                    if ($uploadContext === 'education') {
                        $docFlag = 'Education Docs:';
                    } else {
                        $docFlag = 'Cert Docs:';
                    }
                }
                
                if ($docFlag) {
                    // Update the specific document flag from false to true
                    $notes = preg_replace('/' . preg_quote($docFlag) . '\s*false/', $docFlag . ' true', $notes);
                    
                    // Update the job application notes
                    $stmt = $conn->prepare("UPDATE job_applications SET notes = ? WHERE application_id = ?");
                    $stmt->execute([$notes, $app['application_id']]);
                }
            }
        }
        
        $response['success'] = true;
        $response['message'] = 'Document uploaded successfully!';
        $response['document_id'] = $document_id;
        $response['filename'] = $file['name'];
        $response['file_path'] = $filePath;
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>