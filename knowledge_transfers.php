<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php"); exit;
}
require_once 'dp.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $host = getenv('DB_HOST') ?? 'localhost';
$dbname = getenv('DB_NAME') ?? 'hr_system';
$username = getenv('DB_USER') ?? 'root';
$password = getenv('DB_PASS') ?? '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

$currentRole = $_SESSION['role'] ?? 'hr';
$currentUserId = $_SESSION['user_id'] ?? 1;
$currentUserName = $_SESSION['user_name'] ?? 'HR Admin';

// ── Auto-migrate: add columns if they don't exist (safe on every load) ──
try {
    $cols = $pdo->query("SHOW COLUMNS FROM knowledge_transfers")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('kt_status', $cols)) {
        $pdo->exec("ALTER TABLE knowledge_transfers ADD COLUMN kt_status ENUM('Pending','Ongoing','Completed') NOT NULL DEFAULT 'Pending'");
    }
    if (!in_array('transfer_deadline', $cols)) {
        $pdo->exec("ALTER TABLE knowledge_transfers ADD COLUMN transfer_deadline DATE NULL");
    }
    if (!in_array('created_at', $cols)) {
        $pdo->exec("ALTER TABLE knowledge_transfers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
    if (!in_array('updated_at', $cols)) {
        $pdo->exec("ALTER TABLE knowledge_transfers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
} catch(PDOException $e) { /* silently skip if no permission */ }

// ─────────────────────────────────────────────
// AJAX HANDLER
// ─────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_transfer':
            $id = intval($_GET['id']);
            $s = $pdo->prepare("
                SELECT kt.*,
                    CONCAT(pi.first_name,' ',pi.last_name) AS employee_name,
                    ep.employee_number, jr.title AS job_title, jr.department,
                    e.exit_date, e.exit_type,
                    CONCAT(pi_exit.first_name,' ',pi_exit.last_name) AS exiting_employee_name
                FROM knowledge_transfers kt
                LEFT JOIN employee_profiles ep ON kt.employee_id=ep.employee_id
                LEFT JOIN personal_information pi ON ep.personal_info_id=pi.personal_info_id
                LEFT JOIN job_roles jr ON ep.job_role_id=jr.job_role_id
                LEFT JOIN exits e ON kt.exit_id=e.exit_id
                LEFT JOIN employee_profiles ep_exit ON e.employee_id=ep_exit.employee_id
                LEFT JOIN personal_information pi_exit ON ep_exit.personal_info_id=pi_exit.personal_info_id
                WHERE kt.transfer_id=?");
            $s->execute([$id]);
            echo json_encode($s->fetch(PDO::FETCH_ASSOC)); exit;

        case 'get_responsibilities':
            $id = intval($_GET['transfer_id']);
            $s = $pdo->prepare("SELECT * FROM kt_responsibilities WHERE transfer_id=? ORDER BY priority_order ASC, created_at DESC");
            $s->execute([$id]);
            echo json_encode($s->fetchAll(PDO::FETCH_ASSOC)); exit;

        case 'get_documents':
            $id = intval($_GET['transfer_id']);
            $s = $pdo->prepare("
                SELECT d.*, dv.version_number, dv.uploaded_by_name, dv.upload_date,
                    dv.file_path, dv.file_name, dv.file_size, dv.notes AS version_notes,
                    (SELECT COUNT(*) FROM kt_document_versions WHERE document_id=d.document_id) AS total_versions
                FROM kt_documents d
                LEFT JOIN kt_document_versions dv ON d.current_version_id=dv.version_id
                WHERE d.transfer_id=? ORDER BY d.created_at DESC");
            $s->execute([$id]);
            echo json_encode($s->fetchAll(PDO::FETCH_ASSOC)); exit;

        case 'get_sessions':
            $id = intval($_GET['transfer_id']);
            $s = $pdo->prepare("SELECT * FROM kt_sessions WHERE transfer_id=? ORDER BY session_date DESC");
            $s->execute([$id]);
            echo json_encode($s->fetchAll(PDO::FETCH_ASSOC)); exit;

        case 'get_doc_versions':
            $docId = intval($_GET['document_id']);
            $s = $pdo->prepare("SELECT * FROM kt_document_versions WHERE document_id=? ORDER BY version_number DESC");
            $s->execute([$docId]);
            echo json_encode($s->fetchAll(PDO::FETCH_ASSOC)); exit;

        case 'get_progress':
            $id = intval($_GET['transfer_id']);
            $r1 = $pdo->prepare("SELECT COUNT(*) as total, SUM(is_completed) as done FROM kt_responsibilities WHERE transfer_id=?");
            $r1->execute([$id]); $rd = $r1->fetch(PDO::FETCH_ASSOC);
            $r2 = $pdo->prepare("SELECT COUNT(*) as total FROM kt_documents WHERE transfer_id=?");
            $r2->execute([$id]); $dd = $r2->fetch(PDO::FETCH_ASSOC);
            $r3 = $pdo->prepare("SELECT COUNT(*) as total FROM kt_sessions WHERE transfer_id=?");
            $r3->execute([$id]); $sd = $r3->fetch(PDO::FETCH_ASSOC);
            $rTotal = intval($rd['total']); $rDone = intval($rd['done']);
            $rPct = $rTotal > 0 ? round(($rDone/$rTotal)*100) : 0;
            $dPct = $dd['total'] > 0 ? 100 : 0;
            $sPct = $sd['total'] > 0 ? 100 : 0;
            $overall = round(($rPct*0.5)+($dPct*0.3)+($sPct*0.2));
            echo json_encode(['responsibilities'=>['total'=>$rTotal,'done'=>$rDone,'pct'=>$rPct],
                'documents'=>['total'=>$dd['total'],'pct'=>$dPct],
                'sessions'=>['total'=>$sd['total'],'pct'=>$sPct],'overall'=>$overall]); exit;
    }
    exit;
}

// ─────────────────────────────────────────────
// POST HANDLER
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    switch ($action) {
        case 'add_transfer':
            try {
                $s = $pdo->prepare("INSERT INTO knowledge_transfers (exit_id,employee_id,handover_details,start_date,completion_date,status,notes,transfer_deadline,kt_status) VALUES (?,?,?,?,?,?,?,?,?)");
                $s->execute([$_POST['exit_id'],$_POST['employee_id'],$_POST['handover_details'],
                    $_POST['start_date']?:null,$_POST['completion_date']?:null,$_POST['status'],
                    $_POST['notes'],$_POST['transfer_deadline']?:null,$_POST['kt_status']??'Pending']);
                echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId(),'message'=>'Knowledge transfer created!']); 
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'update_transfer':
            try {
                $ktStatus     = $_POST['kt_status'] ?? 'Pending';
                $transferId   = intval($_POST['transfer_id']);
                $statusMap    = ['Pending'=>'Not Started','Ongoing'=>'In Progress','Completed'=>'Completed'];
                $legacyStatus = $statusMap[$ktStatus] ?? ($_POST['status'] ?? 'Not Started');

                $s = $pdo->prepare("UPDATE knowledge_transfers SET exit_id=?, employee_id=?, handover_details=?, start_date=?, completion_date=?, status=?, notes=?, transfer_deadline=?, kt_status=? WHERE transfer_id=?");
                $s->execute([
                    $_POST['exit_id'],
                    $_POST['employee_id'],
                    $_POST['handover_details'],
                    $_POST['start_date'] ?: null,
                    $_POST['completion_date'] ?: null,
                    $legacyStatus,
                    $_POST['notes'],
                    $_POST['transfer_deadline'] ?: null,
                    $ktStatus,
                    $transferId
                ]);
                echo json_encode(['success'=>true,'message'=>'Knowledge transfer updated!','kt_status'=>$ktStatus]);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'delete_transfer':
            try {
                $id = intval($_POST['transfer_id']);
                $pdo->prepare("DELETE FROM kt_responsibilities WHERE transfer_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM kt_sessions WHERE transfer_id=?")->execute([$id]);
                // Delete document files
                $docs = $pdo->prepare("SELECT dv.file_path FROM kt_documents d JOIN kt_document_versions dv ON d.document_id=dv.document_id WHERE d.transfer_id=?");
                $docs->execute([$id]);
                foreach($docs->fetchAll() as $doc) { if(file_exists($doc['file_path'])) unlink($doc['file_path']); }
                $pdo->prepare("DELETE FROM kt_document_versions WHERE document_id IN (SELECT document_id FROM kt_documents WHERE transfer_id=?)")->execute([$id]);
                $pdo->prepare("DELETE FROM kt_documents WHERE transfer_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM knowledge_transfers WHERE transfer_id=?")->execute([$id]);
                echo json_encode(['success'=>true,'message'=>'Transfer deleted.']);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'add_responsibility':
            try {
                $s = $pdo->prepare("INSERT INTO kt_responsibilities (transfer_id,task_name,description,priority,priority_order,assigned_receiver,completion_status,remarks) VALUES (?,?,?,?,?,?,?,?)");
                $s->execute([$_POST['transfer_id'],$_POST['task_name'],$_POST['description'],
                    $_POST['priority'],$_POST['priority_order']??0,$_POST['assigned_receiver']?:null,'Pending',$_POST['remarks']?:null]);
                echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId(),'message'=>'Responsibility added!']);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'update_responsibility':
            try {
                $s = $pdo->prepare("UPDATE kt_responsibilities SET task_name=?,description=?,priority=?,priority_order=?,assigned_receiver=?,remarks=? WHERE responsibility_id=?");
                $s->execute([$_POST['task_name'],$_POST['description'],$_POST['priority'],
                    $_POST['priority_order']??0,$_POST['assigned_receiver']?:null,$_POST['remarks']?:null,$_POST['responsibility_id']]);
                echo json_encode(['success'=>true,'message'=>'Responsibility updated!']);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'toggle_responsibility':
            try {
                $id = intval($_POST['responsibility_id']);
                $row = $pdo->prepare("SELECT is_completed FROM kt_responsibilities WHERE responsibility_id=?");
                $row->execute([$id]); $cur = $row->fetch();
                $newState = $cur['is_completed'] ? 0 : 1;
                $completedAt = $newState ? date('Y-m-d H:i:s') : null;
                $pdo->prepare("UPDATE kt_responsibilities SET is_completed=?,completion_status=?,completed_at=? WHERE responsibility_id=?")
                    ->execute([$newState,$newState?'Completed':'Pending',$completedAt,$id]);
                echo json_encode(['success'=>true,'is_completed'=>$newState,'completed_at'=>$completedAt]);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'delete_responsibility':
            try {
                $pdo->prepare("DELETE FROM kt_responsibilities WHERE responsibility_id=?")->execute([intval($_POST['responsibility_id'])]);
                echo json_encode(['success'=>true]);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'add_document':
            try {
                // Detailed file error reporting
                if (!isset($_FILES['doc_file'])) {
                    echo json_encode(['success'=>false,'message'=>'No file received by server. Check PHP upload_max_filesize and post_max_size settings.']); exit;
                }
                $fileError = $_FILES['doc_file']['error'];
                if ($fileError !== UPLOAD_ERR_OK) {
                    $errMessages = [
                        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
                        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form.',
                        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                        UPLOAD_ERR_NO_FILE    => 'No file was selected.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
                    ];
                    echo json_encode(['success'=>false,'message'=>$errMessages[$fileError] ?? "Upload error code: $fileError"]); exit;
                }

                // Validate required fields
                if (empty($_POST['transfer_id'])) { echo json_encode(['success'=>false,'message'=>'Transfer ID is missing.']); exit; }
                if (empty($_POST['document_title'])) { echo json_encode(['success'=>false,'message'=>'Document title is required.']); exit; }
                if (empty($_POST['document_type'])) { echo json_encode(['success'=>false,'message'=>'Document type is required.']); exit; }

                // Create upload directory
                $uploadDir = 'uploads/kt_docs/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        echo json_encode(['success'=>false,'message'=>'Could not create upload directory. Check server folder permissions.']); exit;
                    }
                }

                $origName = basename($_FILES['doc_file']['name']);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','png','jpg','jpeg','gif','txt','zip'];
                if (!in_array($ext, $allowed)) {
                    echo json_encode(['success'=>false,'message'=>"File type '.$ext.' is not allowed. Allowed: ".implode(', ',$allowed)]); exit;
                }

                $fileName = uniqid('kt_').'.'.$ext;
                $filePath = $uploadDir.$fileName;

                if (!move_uploaded_file($_FILES['doc_file']['tmp_name'], $filePath)) {
                    echo json_encode(['success'=>false,'message'=>'Failed to move uploaded file. Check folder write permissions for: '.$uploadDir]); exit;
                }
                $fileSize = filesize($filePath);

                $pdo->beginTransaction();
                $s = $pdo->prepare("INSERT INTO kt_documents (transfer_id,document_title,document_type,description,current_version_id) VALUES (?,?,?,?,0)");
                $s->execute([
                    intval($_POST['transfer_id']),
                    $_POST['document_title'],
                    $_POST['document_type'],
                    $_POST['description'] ?? ''
                ]);
                $docId = $pdo->lastInsertId();

                $sv = $pdo->prepare("INSERT INTO kt_document_versions (document_id,version_number,file_path,file_name,file_size,uploaded_by_name,upload_date,notes) VALUES (?,1,?,?,?,?,NOW(),?)");
                $sv->execute([$docId, $filePath, $origName, $fileSize, $currentUserName, $_POST['version_notes'] ?: null]);
                $verId = $pdo->lastInsertId();

                $pdo->prepare("UPDATE kt_documents SET current_version_id=? WHERE document_id=?")->execute([$verId, $docId]);
                $pdo->commit();
                echo json_encode(['success'=>true,'id'=>$docId,'message'=>'Document uploaded successfully!']);
            } catch(PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
            }
            exit;

        case 'add_document_version':
            try {
                if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(['success'=>false,'message'=>'File upload failed.']); exit;
                }
                $docId = intval($_POST['document_id']);
                $vRow = $pdo->prepare("SELECT MAX(version_number) as maxv FROM kt_document_versions WHERE document_id=?");
                $vRow->execute([$docId]); $maxV = $vRow->fetch()['maxv'];
                $newV = $maxV + 1;

                $uploadDir = 'uploads/kt_docs/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $origName = basename($_FILES['doc_file']['name']);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $fileName = uniqid('kt_v'.$newV.'_').'.'.$ext;
                $filePath = $uploadDir.$fileName;
                move_uploaded_file($_FILES['doc_file']['tmp_name'], $filePath);
                $fileSize = filesize($filePath);

                $sv = $pdo->prepare("INSERT INTO kt_document_versions (document_id,version_number,file_path,file_name,file_size,uploaded_by_name,upload_date,notes) VALUES (?,?,?,?,?,?,NOW(),?)");
                $sv->execute([$docId,$newV,$filePath,$origName,$fileSize,$currentUserName,$_POST['version_notes']?:null]);
                $verId = $pdo->lastInsertId();
                $pdo->prepare("UPDATE kt_documents SET current_version_id=? WHERE document_id=?")->execute([$verId,$docId]);
                echo json_encode(['success'=>true,'version'=>$newV,'message'=>"Version $newV uploaded!"]);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'delete_document':
            try {
                $docId = intval($_POST['document_id']);
                $vers = $pdo->prepare("SELECT file_path FROM kt_document_versions WHERE document_id=?");
                $vers->execute([$docId]);
                foreach($vers->fetchAll() as $v) { if(file_exists($v['file_path'])) unlink($v['file_path']); }
                $pdo->prepare("DELETE FROM kt_document_versions WHERE document_id=?")->execute([$docId]);
                $pdo->prepare("DELETE FROM kt_documents WHERE document_id=?")->execute([$docId]);
                echo json_encode(['success'=>true]);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'add_session':
            try {
                $notesPath = null;
                if (isset($_FILES['meeting_notes']) && $_FILES['meeting_notes']['error']===UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/kt_sessions/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $origName = basename($_FILES['meeting_notes']['name']);
                    $fileName = uniqid('sess_').'_'.$origName;
                    $notesPath = $uploadDir.$fileName;
                    move_uploaded_file($_FILES['meeting_notes']['tmp_name'], $notesPath);
                }
                $s = $pdo->prepare("INSERT INTO kt_sessions (transfer_id,session_date,attendees,summary,action_items,meeting_notes_path) VALUES (?,?,?,?,?,?)");
                $s->execute([$_POST['transfer_id'],$_POST['session_date'],$_POST['attendees'],
                    $_POST['summary'],$_POST['action_items']?:null,$notesPath]);
                echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId(),'message'=>'Session recorded!']);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;

        case 'delete_session':
            try {
                $id = intval($_POST['session_id']);
                $row = $pdo->prepare("SELECT meeting_notes_path FROM kt_sessions WHERE session_id=?");
                $row->execute([$id]); $r = $row->fetch();
                if ($r && $r['meeting_notes_path'] && file_exists($r['meeting_notes_path'])) unlink($r['meeting_notes_path']);
                $pdo->prepare("DELETE FROM kt_sessions WHERE session_id=?")->execute([$id]);
                echo json_encode(['success'=>true]);
            } catch(PDOException $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
            exit;
    }
    exit;
}

// ─────────────────────────────────────────────
// PRINT REPORT
// ─────────────────────────────────────────────
if (isset($_GET['print']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $s = $pdo->prepare("
        SELECT kt.*, CONCAT(pi.first_name,' ',pi.last_name) AS employee_name,
            ep.employee_number, jr.title AS job_title, jr.department,
            e.exit_date, e.exit_type,
            CONCAT(pi_exit.first_name,' ',pi_exit.last_name) AS exiting_employee_name
        FROM knowledge_transfers kt
        LEFT JOIN employee_profiles ep ON kt.employee_id=ep.employee_id
        LEFT JOIN personal_information pi ON ep.personal_info_id=pi.personal_info_id
        LEFT JOIN job_roles jr ON ep.job_role_id=jr.job_role_id
        LEFT JOIN exits e ON kt.exit_id=e.exit_id
        LEFT JOIN employee_profiles ep_exit ON e.employee_id=ep_exit.employee_id
        LEFT JOIN personal_information pi_exit ON ep_exit.personal_info_id=pi_exit.personal_info_id
        WHERE kt.transfer_id=?");
    $s->execute([$id]); $kt = $s->fetch(PDO::FETCH_ASSOC);
    $resp = $pdo->prepare("SELECT * FROM kt_responsibilities WHERE transfer_id=? ORDER BY priority_order ASC");
    $resp->execute([$id]); $responsibilities = $resp->fetchAll(PDO::FETCH_ASSOC);
    $docs = $pdo->prepare("SELECT d.*, dv.version_number, dv.file_name, dv.uploaded_by_name, dv.upload_date FROM kt_documents d LEFT JOIN kt_document_versions dv ON d.current_version_id=dv.version_id WHERE d.transfer_id=?");
    $docs->execute([$id]); $documents = $docs->fetchAll(PDO::FETCH_ASSOC);
    $sess = $pdo->prepare("SELECT * FROM kt_sessions WHERE transfer_id=? ORDER BY session_date DESC");
    $sess->execute([$id]); $sessions = $sess->fetchAll(PDO::FETCH_ASSOC);
    // ── INLINE PRINT REPORT ──────────────────────────────────────────────
    if (!$kt) { echo "<p style='font-family:sans-serif;padding:40px'>Transfer not found.</p>"; exit; }
    $doneResp = count(array_filter($responsibilities, fn($r) => $r['is_completed']));
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>KT Report – <?= htmlspecialchars($kt['exiting_employee_name'] ?? 'Unknown') ?></title>
<style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',Arial,sans-serif;color:#333;background:#fff;font-size:13px}
    .page{max-width:940px;margin:0 auto;padding:30px}
    .no-print{margin-bottom:20px;text-align:right}
    .no-print button{border:none;padding:10px 22px;border-radius:25px;font-size:14px;font-weight:600;cursor:pointer;margin-left:8px}
    .btn-print{background:linear-gradient(135deg,#E91E63,#F06292);color:#fff}
    .btn-close{background:#e9ecef;color:#333}

    /* Header */
    .rpt-header{background:linear-gradient(135deg,#E91E63,#F06292);color:#fff;padding:28px 32px;border-radius:12px;margin-bottom:26px}
    .rpt-header h1{font-size:22px;font-weight:700;margin-bottom:6px}
    .rpt-header p{opacity:.85;font-size:13px;margin-bottom:16px}
    .rpt-meta{display:flex;flex-wrap:wrap;gap:24px}
    .rpt-meta-item .lbl{font-size:11px;opacity:.75;margin-bottom:2px}
    .rpt-meta-item .val{font-size:14px;font-weight:700}

    /* Summary boxes */
    .sum-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:26px}
    .sum-box{background:#fafafa;border:1px solid #eee;border-radius:10px;padding:18px;text-align:center}
    .sum-box .num{font-size:2rem;font-weight:700;color:#E91E63;line-height:1}
    .sum-box .lbl{font-size:12px;color:#888;margin-top:5px}

    /* Info grid */
    .section{margin-bottom:28px}
    .section-title{font-size:14px;font-weight:700;color:#C2185B;border-bottom:2px solid #F8BBD0;padding-bottom:8px;margin-bottom:14px}
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .info-box{background:#FFF0F5;border:1px solid #F8BBD0;border-radius:10px;padding:16px}
    .info-box h5{font-size:11px;font-weight:700;color:#C2185B;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
    .info-row{display:flex;gap:6px;margin-bottom:6px;font-size:12px}
    .info-row .lbl{font-weight:600;color:#555;min-width:110px;flex-shrink:0}
    .info-row .val{color:#333}
    .detail-block{background:#f8f9fa;border-radius:8px;padding:12px 14px;font-size:12px;white-space:pre-wrap;line-height:1.6;color:#444;margin-top:4px}

    /* Badges */
    .badge{display:inline-block;padding:3px 11px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
    .b-completed{background:#d4edda;color:#155724}
    .b-pending  {background:#fff3cd;color:#856404}
    .b-ongoing  {background:#cce5ff;color:#004085}
    .b-high     {background:#f8d7da;color:#721c24}
    .b-medium   {background:#fff3cd;color:#856404}
    .b-low      {background:#d4edda;color:#155724}
    .b-default  {background:#e2e3e5;color:#383d41}

    /* Tables */
    table{width:100%;border-collapse:collapse;font-size:12px}
    th{background:#F8BBD0;color:#C2185B;padding:9px 12px;text-align:left;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
    td{padding:9px 12px;border-bottom:1px solid #f5f5f5;vertical-align:top}
    tr:nth-child(even) td{background:#FFF5F8}
    .check{color:#28a745}
    .cross{color:#ccc}

    /* Session */
    .session-card{background:#fafafa;border:1px solid #eee;border-radius:8px;padding:14px;margin-bottom:12px}
    .session-card h4{font-size:13px;font-weight:700;color:#C2185B;margin-bottom:8px}
    .action-box{background:#fff3cd;border-radius:6px;padding:8px 12px;margin-top:8px;font-size:12px;white-space:pre-wrap}

    /* Footer */
    .rpt-footer{margin-top:40px;border-top:2px solid #F8BBD0;padding-top:14px;font-size:11px;color:#999;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px}

    @media print{
        .no-print{display:none}
        body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .page{padding:20px}
    }
</style>
</head>
<body>
<div class="page">

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <button class="btn-close" onclick="window.close()">✕ Close</button>
    </div>

    <!-- Header -->
    <div class="rpt-header">
        <h1>📚 Knowledge Transfer Summary Report</h1>
        <p>Official KT handover documentation – HR System</p>
        <div class="rpt-meta">
            <div class="rpt-meta-item"><div class="lbl">Transfer ID</div><div class="val">#<?= htmlspecialchars($kt['transfer_id']) ?></div></div>
            <div class="rpt-meta-item"><div class="lbl">Exiting Employee</div><div class="val"><?= htmlspecialchars($kt['exiting_employee_name'] ?? '—') ?></div></div>
            <div class="rpt-meta-item"><div class="lbl">Receiving Employee</div><div class="val"><?= htmlspecialchars($kt['employee_name'] ?? '—') ?></div></div>
            <div class="rpt-meta-item"><div class="lbl">KT Status</div><div class="val"><?= htmlspecialchars($kt['kt_status'] ?? $kt['status'] ?? '—') ?></div></div>
            <div class="rpt-meta-item"><div class="lbl">Generated</div><div class="val"><?= date('M d, Y H:i') ?></div></div>
        </div>
    </div>

    <!-- Summary counts -->
    <div class="sum-row">
        <div class="sum-box"><div class="num"><?= count($responsibilities) ?></div><div class="lbl">📋 Responsibilities<br><?= $doneResp ?>/<?= count($responsibilities) ?> completed</div></div>
        <div class="sum-box"><div class="num"><?= count($documents) ?></div><div class="lbl">📄 Documents uploaded</div></div>
        <div class="sum-box"><div class="num"><?= count($sessions) ?></div><div class="lbl">🗓️ Sessions recorded</div></div>
    </div>

    <!-- Transfer Info -->
    <div class="section">
        <div class="section-title">🧾 Transfer Information</div>
        <div class="info-grid">
            <div class="info-box">
                <h5>Exiting Employee</h5>
                <div class="info-row"><span class="lbl">Name</span><span class="val"><?= htmlspecialchars($kt['exiting_employee_name'] ?? '—') ?></span></div>
                <div class="info-row"><span class="lbl">Exit Type</span><span class="val"><?= htmlspecialchars($kt['exit_type'] ?? '—') ?></span></div>
                <div class="info-row"><span class="lbl">Exit Date</span><span class="val"><?= $kt['exit_date'] ? date('M d, Y', strtotime($kt['exit_date'])) : '—' ?></span></div>
            </div>
            <div class="info-box">
                <h5>Receiving Employee</h5>
                <div class="info-row"><span class="lbl">Name</span><span class="val"><?= htmlspecialchars($kt['employee_name'] ?? '—') ?></span></div>
                <div class="info-row"><span class="lbl">Job Title</span><span class="val"><?= htmlspecialchars($kt['job_title'] ?? '—') ?></span></div>
                <div class="info-row"><span class="lbl">Department</span><span class="val"><?= htmlspecialchars($kt['department'] ?? '—') ?></span></div>
                <div class="info-row"><span class="lbl">Employee #</span><span class="val"><?= htmlspecialchars($kt['employee_number'] ?? '—') ?></span></div>
            </div>
            <div class="info-box">
                <h5>Timeline</h5>
                <div class="info-row"><span class="lbl">Deadline</span><span class="val"><?= $kt['transfer_deadline'] ? date('M d, Y', strtotime($kt['transfer_deadline'])) : '—' ?></span></div>
                <div class="info-row"><span class="lbl">Start Date</span><span class="val"><?= $kt['start_date'] ? date('M d, Y', strtotime($kt['start_date'])) : '—' ?></span></div>
                <div class="info-row"><span class="lbl">Completion</span><span class="val"><?= $kt['completion_date'] ? date('M d, Y', strtotime($kt['completion_date'])) : '—' ?></span></div>
            </div>
            <div class="info-box">
                <h5>KT Status</h5>
                <?php
                    $ktSt = $kt['kt_status'] ?? $kt['status'] ?? 'Pending';
                    $stCls = match(strtolower($ktSt)) { 'completed'=>'b-completed','ongoing'=>'b-ongoing','pending'=>'b-pending', default=>'b-default' };
                ?>
                <div style="margin:6px 0"><span class="badge <?= $stCls ?>" style="font-size:12px;padding:6px 16px"><?= htmlspecialchars($ktSt) ?></span></div>
                <div class="info-row" style="margin-top:10px"><span class="lbl">Progress</span><span class="val"><?= $doneResp ?>/<?= count($responsibilities) ?> responsibilities handed over</span></div>
            </div>
        </div>
        <?php if (!empty($kt['handover_details'])): ?>
        <div style="margin-top:14px">
            <div style="font-size:12px;font-weight:600;color:#C2185B;margin-bottom:6px">Handover Details</div>
            <div class="detail-block"><?= htmlspecialchars($kt['handover_details']) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($kt['notes'])): ?>
        <div style="margin-top:12px">
            <div style="font-size:12px;font-weight:600;color:#C2185B;margin-bottom:6px">Notes</div>
            <div class="detail-block"><?= htmlspecialchars($kt['notes']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Responsibilities -->
    <div class="section">
        <div class="section-title">📋 Handover Responsibilities (<?= count($responsibilities) ?>)</div>
        <?php if ($responsibilities): ?>
        <table>
            <thead>
                <tr><th>#</th><th>Task / Responsibility</th><th>Priority</th><th>Assigned To</th><th>Status</th><th>✓</th><th>Completed At</th><th>Remarks</th></tr>
            </thead>
            <tbody>
                <?php foreach ($responsibilities as $i => $r):
                    $pc = match(strtolower($r['priority']??'')) { 'high'=>'b-high','medium'=>'b-medium','low'=>'b-low',default=>'b-default' };
                    $sc = $r['is_completed'] ? 'b-completed' : 'b-pending';
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($r['task_name']) ?></strong>
                        <?= $r['description'] ? '<br><span style="color:#888;font-size:11px">'.htmlspecialchars($r['description']).'</span>' : '' ?>
                    </td>
                    <td><span class="badge <?= $pc ?>"><?= htmlspecialchars($r['priority']) ?></span></td>
                    <td style="font-size:12px"><?= htmlspecialchars($r['assigned_receiver'] ?? '—') ?></td>
                    <td><span class="badge <?= $sc ?>"><?= htmlspecialchars($r['completion_status']) ?></span></td>
                    <td style="text-align:center"><?= $r['is_completed'] ? '<span class="check">✅</span>' : '<span class="cross">⬜</span>' ?></td>
                    <td style="font-size:11px;color:#888"><?= $r['completed_at'] ? date('M d, Y H:i', strtotime($r['completed_at'])) : '—' ?></td>
                    <td style="font-size:11px;color:#666"><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#aaa;padding:20px;text-align:center;background:#fafafa;border-radius:8px">No responsibilities recorded.</p>
        <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="section">
        <div class="section-title">📄 KT Documents (<?= count($documents) ?>)</div>
        <?php if ($documents): ?>
        <table>
            <thead>
                <tr><th>#</th><th>Title</th><th>Type</th><th>Version</th><th>Uploaded By</th><th>Upload Date</th><th>Description</th></tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $i => $d): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($d['document_title']) ?></strong></td>
                    <td><?= htmlspecialchars($d['document_type'] ?? '—') ?></td>
                    <td>v<?= htmlspecialchars($d['version_number'] ?? '1') ?></td>
                    <td><?= htmlspecialchars($d['uploaded_by_name'] ?? '—') ?></td>
                    <td><?= $d['upload_date'] ? date('M d, Y', strtotime($d['upload_date'])) : '—' ?></td>
                    <td style="color:#666"><?= htmlspecialchars($d['description'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#aaa;padding:20px;text-align:center;background:#fafafa;border-radius:8px">No documents uploaded.</p>
        <?php endif; ?>
    </div>

    <!-- Sessions -->
    <div class="section">
        <div class="section-title">🗓️ Knowledge Sharing Sessions (<?= count($sessions) ?>)</div>
        <?php if ($sessions): foreach ($sessions as $s): ?>
        <div class="session-card">
            <h4><?= date('l, F d, Y', strtotime($s['session_date'])) ?></h4>
            <div style="font-size:12px;color:#666;margin-bottom:8px">👥 Attendees: <?= htmlspecialchars($s['attendees']) ?></div>
            <p style="font-size:12px;color:#555;line-height:1.5"><?= htmlspecialchars($s['summary']) ?></p>
            <?php if ($s['action_items']): ?>
            <div class="action-box"><strong>📌 Action Items:</strong><br><?= htmlspecialchars($s['action_items']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; else: ?>
        <p style="color:#aaa;padding:20px;text-align:center;background:#fafafa;border-radius:8px">No sessions recorded.</p>
        <?php endif; ?>
    </div>

    <div class="rpt-footer">
        <span>Knowledge Transfer Report – HR System</span>
        <span>Generated: <?= date('F d, Y \a\t H:i') ?></span>
        <span>Transfer ID: #<?= htmlspecialchars($kt['transfer_id']) ?></span>
    </div>

</div>
</body>
</html>
<?php exit;
}

// ─────────────────────────────────────────────
// MAIN PAGE DATA
// ─────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT kt.*,
        CONCAT(pi.first_name,' ',pi.last_name) as employee_name,
        ep.employee_number, jr.title as job_title, jr.department,
        e.exit_date, e.exit_type,
        CONCAT(pi_exit.first_name,' ',pi_exit.last_name) as exiting_employee_name
    FROM knowledge_transfers kt
    LEFT JOIN employee_profiles ep ON kt.employee_id=ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id=pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id=jr.job_role_id
    LEFT JOIN exits e ON kt.exit_id=e.exit_id
    LEFT JOIN employee_profiles ep_exit ON e.employee_id=ep_exit.employee_id
    LEFT JOIN personal_information pi_exit ON ep_exit.personal_info_id=pi_exit.personal_info_id
    ORDER BY kt.transfer_id DESC");
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT e.exit_id, CONCAT(pi.first_name,' ',pi.last_name) as employee_name, e.exit_date, e.exit_type
    FROM exits e LEFT JOIN employee_profiles ep ON e.employee_id=ep.employee_id
    LEFT JOIN personal_information pi ON ep.personal_info_id=pi.personal_info_id ORDER BY e.exit_date DESC");
$exits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT ep.employee_id, CONCAT(pi.first_name,' ',pi.last_name) as full_name, ep.employee_number, jr.title as job_title
    FROM employee_profiles ep LEFT JOIN personal_information pi ON ep.personal_info_id=pi.personal_info_id
    LEFT JOIN job_roles jr ON ep.job_role_id=jr.job_role_id
    WHERE ep.employment_status='Full-time' OR ep.employment_status='Part-time' ORDER BY pi.first_name");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count pending alerts
$pendingCount = 0;
foreach ($transfers as $t) {
    if (($t['kt_status'] ?? $t['status']) === 'Pending' || ($t['kt_status'] ?? $t['status']) === 'Ongoing') $pendingCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Knowledge Transfer Management – HR System</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
<link rel="stylesheet" href="styles.css?v=rose">
<style>
/* ═══════════════════════════════════════════════
   ROOT & THEME
═══════════════════════════════════════════════ */
:root {
    --pink:        #E91E63;
    --pink-light:  #F06292;
    --pink-dark:   #C2185B;
    --pink-pale:   #FCE4EC;
    --pink-faint:  #FFF0F5;
    --pink-border: #F8BBD0;
    --green:       #28a745;
    --orange:      #fd7e14;
    --blue:        #17a2b8;
    --gray:        #6c757d;
    --radius:      12px;
    --shadow:      0 4px 20px rgba(0,0,0,0.08);
    --shadow-lg:   0 10px 40px rgba(0,0,0,0.15);
}

body { background: var(--pink-pale); font-family: 'Segoe UI', sans-serif; }
.container-fluid { padding: 0; }
.row { margin: 0; }

/* ── MAIN CONTENT ── */
.main-content { background: var(--pink-pale); padding: 24px; }
.section-title { color: var(--pink); font-weight: 700; font-size: 1.5rem; margin-bottom: 6px; }
.section-subtitle { color: #888; font-size: 0.9rem; margin-bottom: 24px; }

/* ── ALERT BANNER ── */
.kt-alert-banner {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border: 1px solid #ffc107;
    border-radius: var(--radius);
    padding: 14px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    color: #856404;
}
.kt-alert-banner .badge-count {
    background: #ffc107;
    color: #000;
    border-radius: 50%;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}

/* ── CONTROLS ── */
.controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.search-box { position: relative; flex: 1; max-width: 380px; }
.search-box input {
    width: 100%;
    padding: 11px 16px 11px 44px;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 14px;
    transition: all 0.3s;
    background: white;
}
.search-box input:focus { border-color: var(--pink); outline: none; box-shadow: 0 0 0 3px rgba(233,30,99,0.15); }
.search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 14px; }

/* ── BUTTONS ── */
.btn {
    padding: 10px 22px;
    border: none; border-radius: 25px;
    font-size: 14px; font-weight: 600;
    cursor: pointer; transition: all 0.25s;
    text-decoration: none; display: inline-flex;
    align-items: center; gap: 6px;
}
.btn-primary { background: linear-gradient(135deg, var(--pink), var(--pink-light)); color: white; }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(233,30,99,0.35); color: white; }
.btn-success { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
.btn-success:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(40,167,69,0.35); color: white; }
.btn-danger  { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }
.btn-danger:hover  { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220,53,69,0.35); color: white; }
.btn-warning { background: linear-gradient(135deg, #ffc107, #fd7e14); color: white; }
.btn-warning:hover { transform: translateY(-2px); color: white; }
.btn-info    { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
.btn-info:hover    { transform: translateY(-2px); color: white; }
.btn-secondary { background: #e9ecef; color: #495057; }
.btn-secondary:hover { background: #dee2e6; color: #495057; }
.btn-sm { padding: 6px 14px; font-size: 12px; }
.btn-icon { padding: 6px 10px; border-radius: 8px; }

/* ── MAIN TABLE ── */
.table-card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.table { width: 100%; border-collapse: collapse; }
.table thead th {
    background: linear-gradient(135deg, var(--pink-border), #f8f9fa);
    padding: 14px 16px;
    font-weight: 600;
    color: var(--pink-dark);
    font-size: 13px;
    border-bottom: 2px solid var(--pink-border);
    white-space: nowrap;
}
.table tbody td { padding: 14px 16px; border-bottom: 1px solid #f5f5f5; vertical-align: middle; font-size: 13px; }
.table tbody tr:hover { background: var(--pink-faint); }
.table tbody tr:last-child td { border-bottom: none; }

/* ── STATUS BADGES ── */
.status-badge {
    padding: 4px 12px; border-radius: 20px;
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
    display: inline-block; white-space: nowrap;
}
.status-pending     { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
.status-ongoing     { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
.status-completed   { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.status-not-started { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.status-in-progress { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
.status-na          { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }

/* Priority badges */
.priority-high   { background: #f8d7da; color: #721c24; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.priority-medium { background: #fff3cd; color: #856404; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.priority-low    { background: #d4edda; color: #155724; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }

/* ── NO RESULTS ── */
.no-results { text-align: center; padding: 60px 20px; color: #aaa; }
.no-results .icon { font-size: 3.5rem; margin-bottom: 16px; }
.no-results h4 { color: #bbb; font-weight: 600; }
.no-results p { font-size: 14px; }

/* ── MODAL ── */
.modal-overlay {
    display: none; position: fixed;
    z-index: 2000; inset: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.2s;
}
.modal-overlay.active { display: flex; align-items: flex-start; justify-content: center; padding: 30px 15px; overflow-y: auto; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-box {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 780px;
    box-shadow: var(--shadow-lg);
    animation: slideDown 0.3s ease;
    overflow: hidden;
    flex-shrink: 0;
}
.modal-box.wide { max-width: 1100px; }
@keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.modal-head {
    background: linear-gradient(135deg, var(--pink), var(--pink-light));
    color: white; padding: 18px 28px;
    display: flex; align-items: center; justify-content: space-between;
}
.modal-head h3 { margin: 0; font-size: 1.15rem; font-weight: 700; }
.modal-close { background: rgba(255,255,255,0.25); border: none; color: white; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
.modal-close:hover { background: rgba(255,255,255,0.4); }

.modal-body { padding: 28px; overflow-y: auto; max-height: 75vh; }

/* ── DETAIL PANEL (TABBED) ── */
.detail-panel-overlay {
    display: none; position: fixed;
    z-index: 1500; inset: 0;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(3px);
}
.detail-panel-overlay.active { display: block; }

.detail-panel {
    position: fixed; top: 0; right: -700px;
    width: 680px; height: 100vh;
    background: white; z-index: 1600;
    box-shadow: -8px 0 40px rgba(0,0,0,0.15);
    display: flex; flex-direction: column;
    transition: right 0.35s cubic-bezier(0.4,0,0.2,1);
    overflow: hidden;
}
.detail-panel.open { right: 0; }

.panel-header {
    background: linear-gradient(135deg, var(--pink), var(--pink-light));
    color: white; padding: 20px 24px;
    flex-shrink: 0;
}
.panel-header h3 { margin: 0 0 4px; font-size: 1.1rem; font-weight: 700; }
.panel-header p  { margin: 0; opacity: 0.85; font-size: 13px; }
.panel-header-actions { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }

/* ── TABS ── */
.kt-tabs {
    display: flex; border-bottom: 2px solid #f0f0f0;
    padding: 0 20px; background: white;
    flex-shrink: 0; overflow-x: auto;
}
.kt-tab {
    padding: 14px 18px; cursor: pointer;
    font-weight: 600; font-size: 13px;
    color: #888; border-bottom: 3px solid transparent;
    margin-bottom: -2px; white-space: nowrap;
    transition: all 0.2s; display: flex; align-items: center; gap: 6px;
}
.kt-tab:hover { color: var(--pink); }
.kt-tab.active { color: var(--pink); border-bottom-color: var(--pink); }
.kt-tab .tab-badge {
    background: var(--pink); color: white;
    border-radius: 10px; padding: 1px 7px;
    font-size: 10px; font-weight: 700;
}

.tab-content { display: none; flex: 1; overflow-y: auto; }
.tab-content.active { display: block; }

/* ── PROGRESS BAR ── */
.progress-section { padding: 20px 24px; background: #fafafa; border-bottom: 1px solid #f0f0f0; }
.progress-label { font-size: 12px; font-weight: 600; color: #666; margin-bottom: 6px; display: flex; justify-content: space-between; }
.progress-bar-wrap { height: 10px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin-bottom: 12px; }
.progress-bar-fill { height: 100%; border-radius: 10px; transition: width 0.6s ease; }
.progress-overall .progress-bar-fill { background: linear-gradient(90deg, var(--pink), var(--pink-light)); }
.progress-resp    .progress-bar-fill { background: linear-gradient(90deg, #17a2b8, #1de9b6); }
.progress-docs    .progress-bar-fill { background: linear-gradient(90deg, #fd7e14, #ffc107); }
.progress-sess    .progress-bar-fill { background: linear-gradient(90deg, #6f42c1, #e83e8c); }
.progress-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.progress-mini { font-size: 11px; }

/* ── OVERVIEW TAB ── */
.overview-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 24px; }
.info-card { background: var(--pink-faint); border-radius: 10px; padding: 16px; border: 1px solid var(--pink-border); }
.info-card h5 { color: var(--pink-dark); font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
.info-row { display: flex; gap: 6px; margin-bottom: 8px; font-size: 13px; }
.info-row .lbl { font-weight: 600; color: #555; min-width: 110px; flex-shrink: 0; }
.info-row .val { color: #333; }

/* ── RESPONSIBILITIES TAB ── */
.resp-list { padding: 0; }
.resp-item {
    padding: 16px 24px;
    border-bottom: 1px solid #f5f5f5;
    display: flex; gap: 14px; align-items: flex-start;
    transition: background 0.15s;
}
.resp-item:hover { background: var(--pink-faint); }
.resp-checkbox-wrap { margin-top: 2px; flex-shrink: 0; }
.resp-checkbox {
    width: 20px; height: 20px; cursor: pointer;
    accent-color: var(--pink);
}
.resp-body { flex: 1; }
.resp-title { font-weight: 600; font-size: 14px; color: #333; margin-bottom: 4px; }
.resp-title.completed-text { text-decoration: line-through; color: #aaa; }
.resp-meta { font-size: 12px; color: #888; display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 6px; }
.resp-desc { font-size: 13px; color: #555; line-height: 1.5; }
.resp-remarks { font-size: 12px; color: #777; background: #f8f9fa; padding: 6px 10px; border-radius: 6px; margin-top: 8px; border-left: 3px solid var(--pink-border); }
.resp-timestamp { font-size: 11px; color: var(--green); margin-top: 4px; font-style: italic; }
.resp-actions { display: flex; gap: 6px; flex-shrink: 0; }

.tab-toolbar { padding: 14px 24px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; background: white; position: sticky; top: 0; z-index: 10; }
.tab-toolbar h5 { margin: 0; font-size: 14px; font-weight: 700; color: #333; }

/* ── DOCUMENTS TAB ── */
.doc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; padding: 20px 24px; }
.doc-card {
    background: white; border: 1px solid #eee;
    border-radius: 10px; padding: 16px;
    transition: box-shadow 0.2s;
    position: relative;
}
.doc-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.1); }
.doc-icon { font-size: 2.2rem; margin-bottom: 10px; }
.doc-title { font-weight: 700; font-size: 14px; color: #333; margin-bottom: 4px; }
.doc-type  { font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--pink); letter-spacing: 0.5px; margin-bottom: 8px; }
.doc-meta  { font-size: 12px; color: #999; margin-bottom: 10px; }
.doc-desc  { font-size: 12px; color: #666; margin-bottom: 12px; line-height: 1.4; }
.doc-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.doc-version-badge { position: absolute; top: 10px; right: 10px; background: var(--pink); color: white; border-radius: 10px; padding: 2px 8px; font-size: 10px; font-weight: 700; }

/* ── SESSIONS TAB ── */
.session-item { padding: 18px 24px; border-bottom: 1px solid #f5f5f5; }
.session-item:hover { background: var(--pink-faint); }
.session-date { font-weight: 700; color: var(--pink); font-size: 15px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.session-meta { font-size: 12px; color: #888; margin-bottom: 10px; }
.session-summary { font-size: 13px; color: #555; margin-bottom: 10px; line-height: 1.5; }
.session-actions-block { background: #f8f9fa; padding: 10px 14px; border-radius: 8px; font-size: 13px; color: #555; margin-bottom: 10px; }
.session-actions-block h6 { font-size: 12px; font-weight: 700; color: #333; margin-bottom: 6px; }

/* ── FORMS ── */
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-weight: 600; font-size: 13px; color: #555; margin-bottom: 6px; }
.form-label .req { color: var(--pink); }
.form-control {
    width: 100%; padding: 10px 14px;
    border: 2px solid #e8e8e8; border-radius: 8px;
    font-size: 14px; transition: all 0.25s;
    background: white;
}
.form-control:focus { border-color: var(--pink); outline: none; box-shadow: 0 0 0 3px rgba(233,30,99,0.1); }
textarea.form-control { min-height: 90px; resize: vertical; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

.modal-footer { padding: 18px 28px; border-top: 1px solid #f0f0f0; display: flex; justify-content: flex-end; gap: 10px; background: #fafafa; }

/* ── TOAST ── */
.toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
.toast {
    background: white; border-radius: 10px;
    padding: 14px 18px; box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    display: flex; align-items: center; gap: 12px;
    min-width: 280px; max-width: 380px;
    animation: toastIn 0.3s ease; border-left: 4px solid;
}
.toast.success { border-color: var(--green); }
.toast.error   { border-color: #dc3545; }
.toast.warning { border-color: #ffc107; }
.toast-icon { font-size: 1.3rem; }
.toast-msg  { font-size: 14px; font-weight: 500; color: #333; flex: 1; }
@keyframes toastIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }

/* ── SPINNER ── */
.spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(233,30,99,0.2); border-top-color: var(--pink); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.loading-state { display: flex; align-items: center; justify-content: center; padding: 40px; gap: 12px; color: #888; }

/* ── SCROLLBAR ── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #f5f5f5; }
::-webkit-scrollbar-thumb { background: var(--pink-border); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--pink-light); }

/* ── FILE UPLOAD ── */
.file-drop-zone {
    border: 2px dashed var(--pink-border); border-radius: 10px;
    padding: 24px; text-align: center; cursor: pointer;
    transition: all 0.25s; background: var(--pink-faint);
}
.file-drop-zone:hover, .file-drop-zone.drag-over {
    border-color: var(--pink); background: var(--pink-border);
}
.file-drop-zone .icon { font-size: 2rem; margin-bottom: 8px; }
.file-drop-zone p { margin: 0; font-size: 13px; color: #666; }
.file-drop-zone strong { color: var(--pink); }

/* ── VERSIONS TIMELINE ── */
.version-timeline { padding: 4px 0; }
.version-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
.version-num { background: var(--pink); color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.version-info { flex: 1; font-size: 13px; }
.version-info .name { font-weight: 600; color: #333; }
.version-info .meta { color: #888; font-size: 11px; margin-top: 2px; }
.version-info .notes { color: #555; margin-top: 4px; font-style: italic; }

/* ── REPORT BADGE ── */
.report-btn { background: linear-gradient(135deg, #6f42c1, #e83e8c); color: white; }
.report-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(111,66,193,0.35); color: white; }

@media (max-width: 768px) {
    .detail-panel { width: 100%; right: -100%; }
    .overview-grid, .doc-grid, .form-row-2, .form-row-3 { grid-template-columns: 1fr; }
    .progress-grid { grid-template-columns: 1fr; }
    .controls { flex-direction: column; }
    .search-box { max-width: 100%; }
}
</style>
</head>
<body>
<div class="container-fluid">
<?php include 'navigation.php'; ?>
<div class="row">
<?php include 'sidebar.php'; ?>
<div class="main-content col">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-1">
        <div>
            <h2 class="section-title mb-0">📚 Knowledge Transfer Management</h2>
            <p class="section-subtitle">Track, manage, and validate employee knowledge handover processes</p>
        </div>
    </div>

    <!-- Pending Alert Banner -->
    <?php if ($pendingCount > 0): ?>
    <div class="kt-alert-banner">
        <div class="badge-count"><?= $pendingCount ?></div>
        <div>
            <strong>Pending KT Items:</strong> <?= $pendingCount ?> knowledge transfer(s) are still in progress or pending. Please review and complete them before the exit deadline.
        </div>
        <button class="btn btn-sm" style="background:#ffc107;color:#000;margin-left:auto;" onclick="document.getElementById('searchInput').value='Pending';filterTable('Pending')">View Pending</button>
    </div>
    <?php endif; ?>

    <!-- Controls -->
    <div class="controls">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search employee, department, status..." oninput="filterTable(this.value)">
        </div>
        <div class="d-flex gap-10" style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add KT Record
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="table-card">
        <table class="table" id="mainTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exiting Employee</th>
                    <th>Receiving Employee</th>
                    <th>Department</th>
                    <th>KT Status</th>
                    <th>Deadline</th>
                    <th>Start → End</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="mainTableBody">
                <?php foreach ($transfers as $t):
                    $ktStatus = $t['kt_status'] ?? $t['status'] ?? 'Pending';
                    $statusClass = 'status-'.strtolower(str_replace(' ','-',$ktStatus));
                    $deadlineStr = $t['transfer_deadline'] ? date('M d, Y', strtotime($t['transfer_deadline'])) : '—';
                    $startStr = $t['start_date'] ? date('M d, Y', strtotime($t['start_date'])) : '—';
                    $endStr   = $t['completion_date'] ? date('M d, Y', strtotime($t['completion_date'])) : '—';
                    $isOverdue = $t['transfer_deadline'] && strtotime($t['transfer_deadline']) < time() && $ktStatus !== 'Completed';
                ?>
                <tr data-id="<?= $t['transfer_id'] ?>">
                    <td><strong style="color:var(--pink)">#<?= $t['transfer_id'] ?></strong></td>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['exiting_employee_name'] ?? '—') ?></div>
                        <div style="font-size:11px;color:#888"><?= htmlspecialchars($t['exit_type'] ?? '') ?> · <?= $t['exit_date'] ? date('M d, Y', strtotime($t['exit_date'])) : '' ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($t['employee_name'] ?? '—') ?></div>
                        <div style="font-size:11px;color:#888"><?= htmlspecialchars($t['job_title'] ?? '') ?></div>
                    </td>
                    <td style="font-size:13px"><?= htmlspecialchars($t['department'] ?? '—') ?></td>
                    <td>
                        <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($ktStatus) ?></span>
                    </td>
                    <td>
                        <span style="font-size:13px;<?= $isOverdue ? 'color:#dc3545;font-weight:600' : '' ?>"><?= $deadlineStr ?><?= $isOverdue ? ' ⚠️' : '' ?></span>
                    </td>
                    <td style="font-size:12px;color:#666"><?= $startStr ?> → <?= $endStr ?></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap">
                            <button class="btn btn-info btn-sm btn-icon" onclick="openDetailPanel(<?= $t['transfer_id'] ?>)" title="Manage KT">
                                <i class="fas fa-tasks"></i> Manage
                            </button>
                            <button class="btn btn-warning btn-sm btn-icon" onclick="openEditModal(<?= $t['transfer_id'] ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-secondary btn-sm btn-icon report-btn" onclick="window.open('knowledge_transfers.php?print=1&id=<?= $t['transfer_id'] ?>','_blank')" title="Print Report">
                                <i class="fas fa-print"></i>
                            </button>
                            <button class="btn btn-danger btn-sm btn-icon" onclick="deleteTransfer(<?= $t['transfer_id'] ?>)" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($transfers)): ?>
        <div class="no-results">
            <div class="icon">📚</div>
            <h4>No Knowledge Transfers Found</h4>
            <p>Click <strong>"Add KT Record"</strong> to create your first knowledge transfer entry.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /main-content -->
</div>
</div>

<!-- ═══════════════════════════════════════════
     TOAST CONTAINER
═══════════════════════════════════════════ -->
<div class="toast-container" id="toastContainer"></div>

<!-- ═══════════════════════════════════════════
     ADD / EDIT TRANSFER MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="transferModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Add Knowledge Transfer</h3>
            <button class="modal-close" onclick="closeModal('transferModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="transferForm">
                <input type="hidden" id="f_transfer_id" name="transfer_id">
                <input type="hidden" id="f_action" name="action" value="add_transfer">

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Exit Record <span class="req">*</span></label>
                        <select id="f_exit_id" name="exit_id" class="form-control" required>
                            <option value="">Select exiting employee…</option>
                            <?php foreach ($exits as $ex): ?>
                            <option value="<?= $ex['exit_id'] ?>">
                                <?= htmlspecialchars($ex['employee_name']) ?> – <?= htmlspecialchars($ex['exit_type']) ?> (<?= date('M d, Y', strtotime($ex['exit_date'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Receiving Employee <span class="req">*</span></label>
                        <select id="f_employee_id" name="employee_id" class="form-control" required>
                            <option value="">Select receiver…</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['employee_id'] ?>">
                                <?= htmlspecialchars($emp['full_name']) ?> – <?= htmlspecialchars($emp['job_title']) ?> (<?= htmlspecialchars($emp['employee_number']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">KT Status <span class="req">*</span></label>
                        <select id="f_kt_status" name="kt_status" class="form-control" required>
                            <option value="Pending">Pending</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Transfer Deadline</label>
                        <input type="date" id="f_transfer_deadline" name="transfer_deadline" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Legacy Status</label>
                        <select id="f_status" name="status" class="form-control">
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="N/A">N/A</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="f_start_date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Completion Date</label>
                        <input type="date" id="f_completion_date" name="completion_date" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Handover Details</label>
                    <textarea id="f_handover_details" name="handover_details" class="form-control" placeholder="Describe the scope of knowledge, responsibilities, and tasks being transferred…"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea id="f_notes" name="notes" class="form-control" placeholder="Any additional observations or instructions…" style="min-height:70px"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('transferModal')">Cancel</button>
            <button class="btn btn-success" id="saveTransferBtn" onclick="saveTransfer()"><i class="fas fa-save"></i> Save Record</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     ADD RESPONSIBILITY MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="respModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="respModalTitle"><i class="fas fa-tasks"></i> Add Responsibility</h3>
            <button class="modal-close" onclick="closeModal('respModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="respForm">
                <input type="hidden" id="rf_responsibility_id" name="responsibility_id">
                <input type="hidden" id="rf_transfer_id" name="transfer_id">
                <input type="hidden" id="rf_action" name="action" value="add_responsibility">

                <div class="form-group">
                    <label class="form-label">Task / Responsibility Name <span class="req">*</span></label>
                    <input type="text" id="rf_task_name" name="task_name" class="form-control" required placeholder="e.g. Onboard new client management system">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="rf_description" name="description" class="form-control" placeholder="Detailed description of what needs to be handed over…"></textarea>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Priority <span class="req">*</span></label>
                        <select id="rf_priority" name="priority" class="form-control" required>
                            <option value="High">🔴 High</option>
                            <option value="Medium" selected>🟡 Medium</option>
                            <option value="Low">🟢 Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sort Order</label>
                        <input type="number" id="rf_priority_order" name="priority_order" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assigned Receiver</label>
                        <select id="rf_assigned_receiver" name="assigned_receiver" class="form-control">
                            <option value="">Auto (from KT record)</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= htmlspecialchars($emp['full_name']) ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Remarks</label>
                    <textarea id="rf_remarks" name="remarks" class="form-control" placeholder="Any remarks, caveats, or special instructions…" style="min-height:70px"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('respModal')">Cancel</button>
            <button class="btn btn-success" onclick="saveResponsibility()"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     ADD DOCUMENT MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="docModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-file-upload"></i> Upload KT Document</h3>
            <button class="modal-close" onclick="closeModal('docModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="docForm" enctype="multipart/form-data">
                <input type="hidden" id="df_transfer_id" name="transfer_id">

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Document Title <span class="req">*</span></label>
                        <input type="text" id="df_document_title" name="document_title" class="form-control" required placeholder="e.g. CRM Onboarding SOP v1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Document Type <span class="req">*</span></label>
                        <select id="df_document_type" name="document_type" class="form-control" required>
                            <option value="">Select type…</option>
                            <option value="SOP">📋 SOP (Standard Operating Procedure)</option>
                            <option value="Manual">📖 Manual</option>
                            <option value="Credentials Guide">🔐 Credentials Guide</option>
                            <option value="Workflow Diagram">🗺️ Workflow Diagram</option>
                            <option value="Training Material">🎓 Training Material</option>
                            <option value="Meeting Notes">📝 Meeting Notes</option>
                            <option value="Other">📄 Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="df_description" name="description" class="form-control" placeholder="Brief description of this document…" style="min-height:70px"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Upload File <span class="req">*</span></label>
                    <div class="file-drop-zone" id="fileDropZone" onclick="document.getElementById('df_doc_file').click()">
                        <div class="icon">📎</div>
                        <p><strong>Click to upload</strong> or drag and drop here</p>
                        <p style="font-size:11px;margin-top:4px;">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, PNG, JPG, ZIP</p>
                        <p id="selectedFileName" style="color:var(--pink);font-weight:600;margin-top:8px;display:none"></p>
                    </div>
                    <input type="file" id="df_doc_file" name="doc_file" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.gif,.txt,.zip" onchange="showFileName(this)">
                </div>
                <div class="form-group">
                    <label class="form-label">Version Notes</label>
                    <input type="text" id="df_version_notes" name="version_notes" class="form-control" placeholder="e.g. Initial upload / First draft">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('docModal')">Cancel</button>
            <button class="btn btn-success" onclick="uploadDocument()"><i class="fas fa-upload"></i> Upload</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     ADD VERSION MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="versionModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-code-branch"></i> Upload New Version</h3>
            <button class="modal-close" onclick="closeModal('versionModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="versionForm" enctype="multipart/form-data">
                <input type="hidden" id="vf_document_id" name="document_id">
                <div class="form-group">
                    <label class="form-label">New File <span class="req">*</span></label>
                    <div class="file-drop-zone" onclick="document.getElementById('vf_doc_file').click()">
                        <div class="icon">📎</div>
                        <p><strong>Click to upload</strong> new version</p>
                        <p id="vSelectedFileName" style="color:var(--pink);font-weight:600;margin-top:8px;display:none"></p>
                    </div>
                    <input type="file" id="vf_doc_file" name="doc_file" style="display:none" onchange="document.getElementById('vSelectedFileName').textContent=this.files[0].name;document.getElementById('vSelectedFileName').style.display='block'">
                </div>
                <div class="form-group">
                    <label class="form-label">Version Notes <span class="req">*</span></label>
                    <textarea id="vf_version_notes" name="version_notes" class="form-control" placeholder="What changed in this version?" required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('versionModal')">Cancel</button>
            <button class="btn btn-success" onclick="uploadVersion()"><i class="fas fa-code-branch"></i> Upload Version</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     VERSION HISTORY MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="historyModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-history"></i> Version History</h3>
            <button class="modal-close" onclick="closeModal('historyModal')">×</button>
        </div>
        <div class="modal-body" id="historyModalBody">
            <div class="loading-state"><div class="spinner"></div> Loading…</div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     ADD SESSION MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="sessionModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3><i class="fas fa-calendar-check"></i> Record KT Session</h3>
            <button class="modal-close" onclick="closeModal('sessionModal')">×</button>
        </div>
        <div class="modal-body">
            <form id="sessionForm" enctype="multipart/form-data">
                <input type="hidden" id="sf_transfer_id" name="transfer_id">
                <input type="hidden" name="action" value="add_session">

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Session Date <span class="req">*</span></label>
                        <input type="date" id="sf_session_date" name="session_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Attendees <span class="req">*</span></label>
                        <input type="text" id="sf_attendees" name="attendees" class="form-control" required placeholder="e.g. John Doe, Jane Smith">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Session Summary <span class="req">*</span></label>
                    <textarea id="sf_summary" name="summary" class="form-control" required placeholder="What was discussed and covered in this session?"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Action Items</label>
                    <textarea id="sf_action_items" name="action_items" class="form-control" placeholder="List action items or follow-ups from this session…" style="min-height:70px"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Meeting Notes (optional)</label>
                    <div class="file-drop-zone" onclick="document.getElementById('sf_meeting_notes').click()">
                        <div class="icon">📝</div>
                        <p><strong>Upload meeting notes</strong> file (optional)</p>
                        <p id="sSelectedFileName" style="color:var(--pink);font-weight:600;margin-top:8px;display:none"></p>
                    </div>
                    <input type="file" id="sf_meeting_notes" name="meeting_notes" style="display:none" onchange="document.getElementById('sSelectedFileName').textContent=this.files[0].name;document.getElementById('sSelectedFileName').style.display='block'">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('sessionModal')">Cancel</button>
            <button class="btn btn-success" onclick="saveSession()"><i class="fas fa-save"></i> Save Session</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     DETAIL PANEL (SLIDE-IN)
═══════════════════════════════════════════ -->
<div class="detail-panel-overlay" id="panelOverlay" onclick="closePanel()"></div>
<div class="detail-panel" id="detailPanel">
    <div class="panel-header">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <h3 id="panelTitle">Knowledge Transfer #—</h3>
                <p id="panelSubtitle">Loading…</p>
            </div>
            <button class="modal-close" onclick="closePanel()">×</button>
        </div>
        <div class="panel-header-actions" id="panelHeaderActions"></div>
    </div>

    <!-- Progress -->
    <div class="progress-section" id="progressSection">
        <div class="progress-label"><span>Overall Progress</span><span id="overallPct">0%</span></div>
        <div class="progress-bar-wrap progress-overall"><div class="progress-bar-fill" id="overallBar" style="width:0%"></div></div>
        <div class="progress-grid">
            <div class="progress-mini">
                <div class="progress-label"><span>📋 Responsibilities</span><span id="respPct">0%</span></div>
                <div class="progress-bar-wrap progress-resp"><div class="progress-bar-fill" id="respBar" style="width:0%"></div></div>
            </div>
            <div class="progress-mini">
                <div class="progress-label"><span>📄 Documents</span><span id="docsPct">0%</span></div>
                <div class="progress-bar-wrap progress-docs"><div class="progress-bar-fill" id="docsBar" style="width:0%"></div></div>
            </div>
            <div class="progress-mini">
                <div class="progress-label"><span>🗓️ Sessions</span><span id="sessPct">0%</span></div>
                <div class="progress-bar-wrap progress-sess"><div class="progress-bar-fill" id="sessBar" style="width:0%"></div></div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="kt-tabs">
        <div class="kt-tab active" onclick="switchTab('overview')" id="tab-overview">📊 Overview</div>
        <div class="kt-tab" onclick="switchTab('responsibilities')" id="tab-responsibilities">📋 Responsibilities <span class="tab-badge" id="badge-resp">0</span></div>
        <div class="kt-tab" onclick="switchTab('documents')" id="tab-documents">📄 Documents <span class="tab-badge" id="badge-docs">0</span></div>
        <div class="kt-tab" onclick="switchTab('sessions')" id="tab-sessions">🗓️ Sessions <span class="tab-badge" id="badge-sess">0</span></div>
    </div>

    <!-- Tab: Overview -->
    <div class="tab-content active" id="content-overview">
        <div class="overview-grid" id="overviewGrid">
            <div class="loading-state"><div class="spinner"></div> Loading…</div>
        </div>
    </div>

    <!-- Tab: Responsibilities -->
    <div class="tab-content" id="content-responsibilities">
        <div class="tab-toolbar">
            <h5>Handover Responsibilities</h5>
            <button class="btn btn-primary btn-sm" onclick="openRespModal()"><i class="fas fa-plus"></i> Add</button>
        </div>
        <div id="respList" class="resp-list">
            <div class="loading-state"><div class="spinner"></div> Loading…</div>
        </div>
    </div>

    <!-- Tab: Documents -->
    <div class="tab-content" id="content-documents">
        <div class="tab-toolbar">
            <h5>KT Documents</h5>
            <button class="btn btn-primary btn-sm" onclick="openDocModal()"><i class="fas fa-upload"></i> Upload</button>
        </div>
        <div id="docList" style="padding:20px 24px">
            <div class="loading-state"><div class="spinner"></div> Loading…</div>
        </div>
    </div>

    <!-- Tab: Sessions -->
    <div class="tab-content" id="content-sessions">
        <div class="tab-toolbar">
            <h5>Knowledge Sharing Sessions</h5>
            <button class="btn btn-primary btn-sm" onclick="openSessionModal()"><i class="fas fa-plus"></i> Add Session</button>
        </div>
        <div id="sessionList">
            <div class="loading-state"><div class="spinner"></div> Loading…</div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════ -->
<script>
// ── STATE ──
let currentTransferId = null;
let transfersData = <?= json_encode($transfers) ?>;
let exitsData     = <?= json_encode($exits) ?>;
let employeesData = <?= json_encode($employees) ?>;

// ── TOAST ──
function showToast(msg, type='success') {
    const icons = { success:'✅', error:'❌', warning:'⚠️' };
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span class="toast-icon">${icons[type]||'ℹ️'}</span><span class="toast-msg">${msg}</span>`;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(30px)'; t.style.transition='all 0.4s'; setTimeout(()=>t.remove(),400); }, 4000);
}

// ── SEARCH / FILTER ──
function filterTable(term) {
    term = term.toLowerCase();
    document.querySelectorAll('#mainTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
}

// ── MODAL OPEN / CLOSE ──
function openModal(id) {
    const el = document.getElementById(id);
    el.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}
window.addEventListener('click', e => {
    ['transferModal','respModal','docModal','versionModal','historyModal','sessionModal'].forEach(id => {
        if (e.target === document.getElementById(id)) closeModal(id);
    });
});

// ── ADD TRANSFER ──
function openAddModal() {
    document.getElementById('transferForm').reset();
    document.getElementById('f_transfer_id').value = '';
    document.getElementById('f_action').value = 'add_transfer';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add Knowledge Transfer';
    openModal('transferModal');
}

// ── EDIT TRANSFER ──
function openEditModal(id) {
    const t = transfersData.find(x => x.transfer_id == id);
    if (!t) return;
    document.getElementById('f_transfer_id').value = t.transfer_id;
    document.getElementById('f_action').value = 'update_transfer';
    document.getElementById('f_exit_id').value = t.exit_id || '';
    document.getElementById('f_employee_id').value = t.employee_id || '';
    document.getElementById('f_kt_status').value = t.kt_status || 'Pending';
    document.getElementById('f_status').value = t.status || 'Not Started';
    document.getElementById('f_transfer_deadline').value = t.transfer_deadline || '';
    document.getElementById('f_start_date').value = t.start_date || '';
    document.getElementById('f_completion_date').value = t.completion_date || '';
    document.getElementById('f_handover_details').value = t.handover_details || '';
    document.getElementById('f_notes').value = t.notes || '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Knowledge Transfer #' + id;
    openModal('transferModal');
}

async function saveTransfer() {
    const form = document.getElementById('transferForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const btn = document.getElementById('saveTransferBtn');
    btn.disabled = true; btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px"></div> Saving…';
    const fd = new FormData(form);
    try {
        const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            showToast(d.message, 'success');
            closeModal('transferModal');

            const action   = document.getElementById('f_action').value;
            const tid      = document.getElementById('f_transfer_id').value;
            const ktStatus = document.getElementById('f_kt_status').value;

            if (action === 'update_transfer' && tid) {
                // Update the in-memory data so the table row refreshes instantly
                const idx = transfersData.findIndex(x => x.transfer_id == tid);
                if (idx > -1) {
                    transfersData[idx].kt_status = ktStatus;
                    transfersData[idx].status    = {'Pending':'Not Started','Ongoing':'In Progress','Completed':'Completed'}[ktStatus] || ktStatus;
                }
                // Update the visible table row badge without full reload
                const row = document.querySelector(`tr[data-id="${tid}"]`);
                if (row) {
                    const statusCell = row.querySelector('td:nth-child(5) .status-badge');
                    if (statusCell) {
                        const cls = 'status-' + ktStatus.toLowerCase().replace(' ', '-');
                        statusCell.className = 'status-badge ' + cls;
                        statusCell.textContent = ktStatus;
                    }
                }
            }

            // Always reload to sync all data cleanly
            setTimeout(() => location.reload(), 800);
        } else { showToast(d.message, 'error'); }
    } catch(e) { showToast('Network error: ' + e.message, 'error'); }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save Record';
}

async function deleteTransfer(id) {
    if (!confirm('Delete this knowledge transfer and all associated data? This cannot be undone.')) return;
    const fd = new FormData(); fd.append('action','delete_transfer'); fd.append('transfer_id', id);
    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { showToast('Transfer deleted.'); document.querySelector(`tr[data-id="${id}"]`)?.remove(); }
    else showToast(d.message, 'error');
}

// ── DETAIL PANEL ──
async function openDetailPanel(id) {
    currentTransferId = id;
    document.getElementById('panelTitle').textContent = 'Knowledge Transfer #' + id;
    document.getElementById('panelSubtitle').textContent = 'Loading details…';
    document.getElementById('panelOverlay').classList.add('active');
    document.getElementById('detailPanel').classList.add('open');
    document.body.style.overflow = 'hidden';
    switchTab('overview');
    await Promise.all([loadOverview(id), loadProgress(id), loadResponsibilities(id), loadDocuments(id), loadSessions(id)]);
}

function closePanel() {
    document.getElementById('detailPanel').classList.remove('open');
    document.getElementById('panelOverlay').classList.remove('active');
    document.body.style.overflow = '';
    currentTransferId = null;
}

function switchTab(tab) {
    document.querySelectorAll('.kt-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.getElementById('content-' + tab).classList.add('active');
}

// ── LOAD PROGRESS ──
async function loadProgress(id) {
    const r = await fetch(`knowledge_transfers.php?ajax=1&action=get_progress&transfer_id=${id}`);
    const d = await r.json();
    const setBar = (barId, pctId, pct) => {
        document.getElementById(barId).style.width = pct + '%';
        document.getElementById(pctId).textContent = pct + '%';
    };
    setBar('overallBar', 'overallPct', d.overall);
    setBar('respBar', 'respPct', d.responsibilities.pct);
    setBar('docsBar', 'docsPct', d.documents.pct);
    setBar('sessBar', 'sessPct', d.sessions.pct);
}

// ── LOAD OVERVIEW ──
async function loadOverview(id) {
    const r = await fetch(`knowledge_transfers.php?ajax=1&action=get_transfer&id=${id}`);
    const t = await r.json();
    if (!t) return;
    document.getElementById('panelSubtitle').textContent = `${t.exiting_employee_name || '—'} → ${t.employee_name || '—'}`;
    document.getElementById('panelHeaderActions').innerHTML = `
        <button class="btn btn-warning btn-sm" onclick="openEditModal(${id})"><i class="fas fa-edit"></i> Edit</button>
        <button class="btn btn-secondary report-btn btn-sm" onclick="window.open('knowledge_transfers.php?print=1&id=${id}','_blank')"><i class="fas fa-print"></i> Print Report</button>`;

    const fmtDate = s => s ? new Date(s).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}) : '—';
    const ktStatus = t.kt_status || t.status || 'Pending';
    const statusClass = 'status-' + ktStatus.toLowerCase().replace(' ','-');
    const isOverdue = t.transfer_deadline && new Date(t.transfer_deadline) < new Date() && ktStatus !== 'Completed';

    document.getElementById('overviewGrid').innerHTML = `
        <div class="info-card" style="grid-column:1/-1">
            <h5>Transfer Status</h5>
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                <span class="status-badge ${statusClass}" style="font-size:14px;padding:8px 20px">${ktStatus}</span>
                ${isOverdue ? '<span style="color:#dc3545;font-weight:600">⚠️ OVERDUE</span>' : ''}
            </div>
        </div>
        <div class="info-card">
            <h5>Exiting Employee</h5>
            <div class="info-row"><span class="lbl">Name</span><span class="val"><strong>${t.exiting_employee_name||'—'}</strong></span></div>
            <div class="info-row"><span class="lbl">Exit Type</span><span class="val">${t.exit_type||'—'}</span></div>
            <div class="info-row"><span class="lbl">Exit Date</span><span class="val">${fmtDate(t.exit_date)}</span></div>
        </div>
        <div class="info-card">
            <h5>Receiving Employee</h5>
            <div class="info-row"><span class="lbl">Name</span><span class="val"><strong>${t.employee_name||'—'}</strong></span></div>
            <div class="info-row"><span class="lbl">Job Title</span><span class="val">${t.job_title||'—'}</span></div>
            <div class="info-row"><span class="lbl">Department</span><span class="val">${t.department||'—'}</span></div>
            <div class="info-row"><span class="lbl">Employee #</span><span class="val">${t.employee_number||'—'}</span></div>
        </div>
        <div class="info-card">
            <h5>Timeline</h5>
            <div class="info-row"><span class="lbl">Deadline</span><span class="val" style="${isOverdue?'color:#dc3545;font-weight:600':''}">${fmtDate(t.transfer_deadline)}</span></div>
            <div class="info-row"><span class="lbl">Start Date</span><span class="val">${fmtDate(t.start_date)}</span></div>
            <div class="info-row"><span class="lbl">Completion</span><span class="val">${fmtDate(t.completion_date)}</span></div>
        </div>
        <div class="info-card">
            <h5>Record Info</h5>
            <div class="info-row"><span class="lbl">Transfer ID</span><span class="val">#${t.transfer_id}</span></div>
            <div class="info-row"><span class="lbl">Created</span><span class="val">${fmtDate(t.created_at)}</span></div>
            <div class="info-row"><span class="lbl">Updated</span><span class="val">${fmtDate(t.updated_at)}</span></div>
        </div>
        ${t.handover_details ? `<div class="info-card" style="grid-column:1/-1"><h5>Handover Details</h5><p style="font-size:13px;color:#555;line-height:1.6;white-space:pre-wrap;margin:0">${escHtml(t.handover_details)}</p></div>` : ''}
        ${t.notes ? `<div class="info-card" style="grid-column:1/-1"><h5>Additional Notes</h5><p style="font-size:13px;color:#555;line-height:1.6;white-space:pre-wrap;margin:0">${escHtml(t.notes)}</p></div>` : ''}
    `;
}

// ── RESPONSIBILITIES ──
async function loadResponsibilities(id) {
    const r = await fetch(`knowledge_transfers.php?ajax=1&action=get_responsibilities&transfer_id=${id}`);
    const list = await r.json();
    document.getElementById('badge-resp').textContent = list.length;
    if (!list.length) {
        document.getElementById('respList').innerHTML = `<div class="no-results"><div class="icon">📋</div><h4>No responsibilities yet</h4><p>Click <strong>Add</strong> to define handover tasks.</p></div>`;
        return;
    }
    document.getElementById('respList').innerHTML = list.map(resp => {
        const pClass = 'priority-' + resp.priority.toLowerCase();
        const isComp = parseInt(resp.is_completed);
        const fmtTs = s => s ? new Date(s).toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '';
        return `
        <div class="resp-item" id="resp-${resp.responsibility_id}">
            <div class="resp-checkbox-wrap">
                <input type="checkbox" class="resp-checkbox" ${isComp?'checked':''} onchange="toggleResponsibility(${resp.responsibility_id}, this)" title="Mark as handed over">
            </div>
            <div class="resp-body">
                <div class="resp-title ${isComp?'completed-text':''}">${escHtml(resp.task_name)}</div>
                <div class="resp-meta">
                    <span class="${pClass}">${resp.priority}</span>
                    ${resp.assigned_receiver ? `<span>👤 ${escHtml(resp.assigned_receiver)}</span>` : ''}
                    <span class="status-badge status-${resp.completion_status==='Completed'?'completed':'pending'}" style="font-size:10px">${resp.completion_status}</span>
                </div>
                ${resp.description ? `<div class="resp-desc">${escHtml(resp.description)}</div>` : ''}
                ${resp.remarks ? `<div class="resp-remarks">💬 ${escHtml(resp.remarks)}</div>` : ''}
                ${isComp && resp.completed_at ? `<div class="resp-timestamp">✅ Completed: ${fmtTs(resp.completed_at)}</div>` : ''}
            </div>
            <div class="resp-actions">
                <button class="btn btn-warning btn-sm btn-icon" onclick="editResponsibility(${resp.responsibility_id})" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn btn-danger btn-sm btn-icon" onclick="deleteResponsibility(${resp.responsibility_id})" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    }).join('');
}

function openRespModal(mode='add', data=null) {
    document.getElementById('respForm').reset();
    document.getElementById('rf_transfer_id').value = currentTransferId;
    if (mode === 'add') {
        document.getElementById('rf_responsibility_id').value = '';
        document.getElementById('rf_action').value = 'add_responsibility';
        document.getElementById('respModalTitle').innerHTML = '<i class="fas fa-tasks"></i> Add Responsibility';
    } else {
        document.getElementById('rf_responsibility_id').value = data.responsibility_id;
        document.getElementById('rf_action').value = 'update_responsibility';
        document.getElementById('rf_task_name').value = data.task_name;
        document.getElementById('rf_description').value = data.description || '';
        document.getElementById('rf_priority').value = data.priority;
        document.getElementById('rf_priority_order').value = data.priority_order || 0;
        document.getElementById('rf_assigned_receiver').value = data.assigned_receiver || '';
        document.getElementById('rf_remarks').value = data.remarks || '';
        document.getElementById('respModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Responsibility';
    }
    openModal('respModal');
}

let _respCache = {};
async function editResponsibility(id) {
    const r = await fetch(`knowledge_transfers.php?ajax=1&action=get_responsibilities&transfer_id=${currentTransferId}`);
    const list = await r.json();
    const resp = list.find(x => x.responsibility_id == id);
    if (resp) openRespModal('edit', resp);
}

async function saveResponsibility() {
    const form = document.getElementById('respForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fd = new FormData(form);
    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { showToast(d.message); closeModal('respModal'); await loadResponsibilities(currentTransferId); await loadProgress(currentTransferId); }
    else showToast(d.message, 'error');
}

async function toggleResponsibility(id, checkbox) {
    const fd = new FormData(); fd.append('action','toggle_responsibility'); fd.append('responsibility_id', id);
    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { await loadResponsibilities(currentTransferId); await loadProgress(currentTransferId); }
    else { checkbox.checked = !checkbox.checked; showToast(d.message, 'error'); }
}

async function deleteResponsibility(id) {
    if (!confirm('Delete this responsibility?')) return;
    const fd = new FormData(); fd.append('action','delete_responsibility'); fd.append('responsibility_id', id);
    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { await loadResponsibilities(currentTransferId); await loadProgress(currentTransferId); }
    else showToast(d.message, 'error');
}

// ── DOCUMENTS ──
const docIcons = { SOP:'📋', Manual:'📖', 'Credentials Guide':'🔐', 'Workflow Diagram':'🗺️', 'Training Material':'🎓', 'Meeting Notes':'📝', Other:'📄' };
function fmtSize(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1048576).toFixed(1) + ' MB';
}

async function loadDocuments(id) {
    const r = await fetch(`knowledge_transfers.php?ajax=1&action=get_documents&transfer_id=${id}`);
    const list = await r.json();
    document.getElementById('badge-docs').textContent = list.length;
    if (!list.length) {
        document.getElementById('docList').innerHTML = `<div class="no-results"><div class="icon">📄</div><h4>No documents yet</h4><p>Click <strong>Upload</strong> to add KT documents.</p></div>`;
        return;
    }
    document.getElementById('docList').innerHTML = `<div class="doc-grid">${list.map(doc => `
        <div class="doc-card">
            <span class="doc-version-badge">v${doc.version_number||1}</span>
            <div class="doc-icon">${docIcons[doc.document_type]||'📄'}</div>
            <div class="doc-title">${escHtml(doc.document_title)}</div>
            <div class="doc-type">${escHtml(doc.document_type||'')}</div>
            <div class="doc-meta">By ${escHtml(doc.uploaded_by_name||'—')} · ${doc.upload_date ? new Date(doc.upload_date).toLocaleDateString() : '—'} · ${fmtSize(doc.file_size)}</div>
            ${doc.description ? `<div class="doc-desc">${escHtml(doc.description)}</div>` : ''}
            <div class="doc-actions">
                <a href="${escHtml(doc.file_path||'#')}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> View</a>
                <a href="${escHtml(doc.file_path||'#')}" download="${escHtml(doc.file_name||'file')}" class="btn btn-success btn-sm"><i class="fas fa-download"></i> DL</a>
                <button class="btn btn-warning btn-sm" onclick="openVersionModal(${doc.document_id})"><i class="fas fa-code-branch"></i> +Ver</button>
                <button class="btn btn-secondary btn-sm" onclick="viewVersionHistory(${doc.document_id})"><i class="fas fa-history"></i></button>
                <button class="btn btn-danger btn-sm btn-icon" onclick="deleteDocument(${doc.document_id})"><i class="fas fa-trash"></i></button>
            </div>
        </div>`).join('')}</div>`;
}

function openDocModal() {
    const form = document.getElementById('docForm');
    form.reset();
    document.getElementById('df_transfer_id').value = currentTransferId;
    document.getElementById('selectedFileName').style.display = 'none';
    // Reset file drop zone text
    document.getElementById('fileDropZone').querySelector('p').textContent = 'Click to upload or drag and drop here';
    openModal('docModal');
}

function showFileName(input) {
    const el = document.getElementById('selectedFileName');
    if (input.files[0]) { el.textContent = '📎 ' + input.files[0].name; el.style.display = 'block'; }
}

async function uploadDocument() {
    const form = document.getElementById('docForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fileInput = document.getElementById('df_doc_file');
    if (!fileInput.files || !fileInput.files[0]) { showToast('Please select a file to upload.', 'warning'); return; }

    // Use FormData(form) directly — this correctly includes the file input
    const fd = new FormData(form);
    // Ensure action is set (overrides the hidden field value just in case)
    fd.set('action', 'add_document');
    fd.set('transfer_id', currentTransferId);

    const btn = document.querySelector('#docModal .btn-success');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;display:inline-block"></div> Uploading…';

    try {
        const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) { showToast('Server error: ' + text.substring(0, 200), 'error'); btn.disabled=false; btn.innerHTML=origText; return; }
        if (d.success) {
            showToast(d.message, 'success');
            closeModal('docModal');
            form.reset();
            document.getElementById('selectedFileName').style.display = 'none';
            await loadDocuments(currentTransferId);
            await loadProgress(currentTransferId);
        } else {
            showToast(d.message, 'error');
        }
    } catch(e) {
        showToast('Network error: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = origText;
}

function openVersionModal(docId) {
    document.getElementById('versionForm').reset();
    document.getElementById('vf_document_id').value = docId;
    document.getElementById('vSelectedFileName').style.display = 'none';
    openModal('versionModal');
}

async function uploadVersion() {
    const form = document.getElementById('versionForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fileInput = document.getElementById('vf_doc_file');
    if (!fileInput.files[0]) { showToast('Please select a file.', 'warning'); return; }

    // Build FormData manually to avoid double-appending the file
    const fd = new FormData();
    fd.append('action', 'add_document_version');
    fd.append('document_id', document.getElementById('vf_document_id').value);
    fd.append('version_notes', document.getElementById('vf_version_notes').value);
    fd.append('doc_file', fileInput.files[0]);

    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { showToast(d.message); closeModal('versionModal'); await loadDocuments(currentTransferId); }
    else showToast(d.message, 'error');
}

async function viewVersionHistory(docId) {
    document.getElementById('historyModalBody').innerHTML = '<div class="loading-state"><div class="spinner"></div> Loading…</div>';
    openModal('historyModal');
    const r = await fetch(`knowledge_transfers.php?ajax=1&action=get_doc_versions&document_id=${docId}`);
    const list = await r.json();
    document.getElementById('historyModalBody').innerHTML = `
        <div class="version-timeline">
            ${list.map(v => `
            <div class="version-item">
                <div class="version-num">v${v.version_number}</div>
                <div class="version-info">
                    <div class="name">${escHtml(v.file_name||'file')}</div>
                    <div class="meta">By ${escHtml(v.uploaded_by_name||'—')} · ${v.upload_date ? new Date(v.upload_date).toLocaleString() : '—'} · ${fmtSize(v.file_size)}</div>
                    ${v.notes ? `<div class="notes">${escHtml(v.notes)}</div>` : ''}
                    <div style="margin-top:8px;display:flex;gap:8px">
                        <a href="${escHtml(v.file_path||'#')}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> View</a>
                        <a href="${escHtml(v.file_path||'#')}" download="${escHtml(v.file_name||'file')}" class="btn btn-success btn-sm"><i class="fas fa-download"></i> Download</a>
                    </div>
                </div>
            </div>`).join('')}
        </div>`;
}

async function deleteDocument(docId) {
    if (!confirm('Delete this document and all its versions?')) return;
    const fd = new FormData(); fd.append('action','delete_document'); fd.append('document_id', docId);
    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { await loadDocuments(currentTransferId); await loadProgress(currentTransferId); }
    else showToast(d.message, 'error');
}

// ── SESSIONS ──
async function loadSessions(id) {
    const r = await fetch(`knowledge_transfers.php?ajax=1&action=get_sessions&transfer_id=${id}`);
    const list = await r.json();
    document.getElementById('badge-sess').textContent = list.length;
    if (!list.length) {
        document.getElementById('sessionList').innerHTML = `<div class="no-results"><div class="icon">🗓️</div><h4>No sessions recorded</h4><p>Click <strong>Add Session</strong> to log a KT meeting.</p></div>`;
        return;
    }
    document.getElementById('sessionList').innerHTML = list.map(s => `
        <div class="session-item">
            <div class="session-date">
                <i class="fas fa-calendar-alt" style="color:var(--pink)"></i>
                ${new Date(s.session_date).toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'})}
            </div>
            <div class="session-meta">👥 Attendees: ${escHtml(s.attendees||'—')}</div>
            <div class="session-summary">${escHtml(s.summary||'')}</div>
            ${s.action_items ? `<div class="session-actions-block"><h6>📌 Action Items</h6>${escHtml(s.action_items)}</div>` : ''}
            <div style="display:flex;gap:8px;align-items:center">
                ${s.meeting_notes_path ? `<a href="${escHtml(s.meeting_notes_path)}" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-file"></i> Meeting Notes</a>` : ''}
                <button class="btn btn-danger btn-sm btn-icon" onclick="deleteSession(${s.session_id})"><i class="fas fa-trash"></i></button>
            </div>
        </div>`).join('');
}

function openSessionModal() {
    document.getElementById('sessionForm').reset();
    document.getElementById('sf_transfer_id').value = currentTransferId;
    document.getElementById('sSelectedFileName').style.display = 'none';
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('sf_session_date').value = today;
    openModal('sessionModal');
}

async function saveSession() {
    const form = document.getElementById('sessionForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fd = new FormData(form);
    const notesFile = document.getElementById('sf_meeting_notes').files[0];
    if (notesFile) fd.append('meeting_notes', notesFile);
    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { showToast(d.message); closeModal('sessionModal'); await loadSessions(currentTransferId); await loadProgress(currentTransferId); }
    else showToast(d.message, 'error');
}

async function deleteSession(id) {
    if (!confirm('Delete this session record?')) return;
    const fd = new FormData(); fd.append('action','delete_session'); fd.append('session_id', id);
    const r = await fetch('knowledge_transfers.php', { method:'POST', body: fd });
    const d = await r.json();
    if (d.success) { await loadSessions(currentTransferId); await loadProgress(currentTransferId); }
    else showToast(d.message, 'error');
}

// ── HELPERS ──
function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── DATE VALIDATION ──
document.getElementById('f_start_date').addEventListener('change', function() {
    document.getElementById('f_completion_date').setAttribute('min', this.value);
});
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>