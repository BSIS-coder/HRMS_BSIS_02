<?php
header('Content-Type: application/json');
require_once 'config.php'; // provides $conn (PDO)

try {
    // Single competency by ID (used in edit modal)
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid competency ID']);
            exit;
        }

        $sql = "
            SELECT 
                c.competency_id,
                c.name,
                c.description,
                c.job_role_id,
                c.category,
                jr.title AS role
            FROM competencies c
            LEFT JOIN job_roles jr ON c.job_role_id = jr.job_role_id
            WHERE c.competency_id = :id
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $competency = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($competency) {
            echo json_encode($competency);
        } else {
            echo json_encode(['success' => false, 'message' => 'Competency not found']);
        }
        exit;
    }

    // Filtered by job_role_id
    $job_role_id = isset($_GET['job_role_id']) ? (int)$_GET['job_role_id'] : 0;

    if ($job_role_id > 0) {
        $sql = "
            SELECT 
                c.competency_id, 
                c.name, 
                c.description,
                c.job_role_id,
                c.category,
                jr.title AS role
            FROM competencies c
            LEFT JOIN job_roles jr ON c.job_role_id = jr.job_role_id
            WHERE c.job_role_id = :job_role_id OR c.job_role_id IS NULL
            ORDER BY c.name
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':job_role_id' => $job_role_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($rows);
        exit;
    }

    // Default: fetch all competencies
    $sql = "
        SELECT 
            c.competency_id, 
            c.name, 
            c.description,
            c.job_role_id,
            c.category,
            jr.title AS role
        FROM competencies c
        LEFT JOIN job_roles jr ON c.job_role_id = jr.job_role_id
        ORDER BY c.name
    ";
    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
