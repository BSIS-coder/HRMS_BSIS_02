<?php
// Function to link candidate documents to employee when hired
function linkCandidateDocuments($candidate_id, $employee_id, $conn) {
    try {
        // 1) Update documents where notes explicitly include the Candidate ID
        $stmt = $conn->prepare(
            "UPDATE document_management\n"
            . "SET employee_id = ?, notes = REPLACE(notes, CONCAT('Candidate ID: ', ?), CONCAT('Employee ID: ', ?))\n"
            . "WHERE notes LIKE CONCAT('%Candidate ID: ', ?, '%')"
        );
        $stmt->execute([$employee_id, $candidate_id, $employee_id, $candidate_id]);
        $count1 = $stmt->rowCount();

        // 2) Legacy/incorrect inserts: some uploads may have stored the candidate's id in employee_id column.
        // Update those rows to point to the real employee_id and append a note so we can track the change.
        $stmt2 = $conn->prepare(
            "UPDATE document_management\n"
            . "SET employee_id = ?, notes = CONCAT(COALESCE(notes, ''), ' | Linked from Candidate ID: ', ?)\n"
            . "WHERE employee_id = ?"
        );
        $stmt2->execute([$employee_id, $candidate_id, $candidate_id]);
        $count2 = $stmt2->rowCount();

        return $count1 + $count2;
    } catch (PDOException $e) {
        error_log("Error linking candidate documents: " . $e->getMessage());
        return false;
    }
}

// Example usage when candidate is hired:
// $documents_linked = linkCandidateDocuments($candidate_id, $new_employee_id, $conn);
?>