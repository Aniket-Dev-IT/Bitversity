<?php
require_once __DIR__ . '/../includes/config.php';

// Set proper headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to submit a project request.']);
    exit;
}

// Check if method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    // Get current user
    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];

    // Validate required fields
    $required_fields = [
        'project_title' => 'Project Title',
        'project_description' => 'Project Description', 
        'tech_requirements' => 'Technical Requirements',
        'budget_range' => 'Budget Range',
        'timeline' => 'Timeline'
    ];

    $errors = [];
    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "$label is required.";
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }

    // Sanitize input data
    $project_data = [
        'user_id' => $userId,
        'type' => 'project',
        'title' => sanitize($_POST['project_title']),
        'description' => sanitize($_POST['project_description']),
        'technical_requirements' => sanitize($_POST['tech_requirements']),
        'budget_range' => sanitize($_POST['budget_range']),
        'timeline' => sanitize($_POST['timeline']),
        'deadline' => !empty($_POST['deadline']) ? sanitize($_POST['deadline']) : null,
        'additional_notes' => !empty($_POST['additional_notes']) ? sanitize($_POST['additional_notes']) : null,
        'reference_urls' => !empty($_POST['reference_urls']) ? sanitize($_POST['reference_urls']) : null,
        'contact_name' => sanitize($_POST['contact_name'] ?? ''),
        'contact_email' => sanitize($_POST['contact_email'] ?? ''),
        'status' => 'pending'
    ];

    // Note: File uploads will be handled later when we extend the table schema

    // Insert into database
    $sql = "INSERT INTO custom_order_requests (
        user_id, type, title, description, technical_requirements, 
        budget_range, timeline, deadline, additional_notes, 
        reference_urls, contact_name, contact_email, status
    ) VALUES (
        :user_id, :type, :title, :description, :technical_requirements,
        :budget_range, :timeline, :deadline, :additional_notes,
        :reference_urls, :contact_name, :contact_email, :status
    )";

    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($project_data)) {
        $request_id = $db->lastInsertId();
        
        // Send notification email (optional - you may want to implement this later)
        try {
            // You can add email notification here if needed
            // sendCustomRequestNotification($request_id, $project_data);
        } catch (Exception $e) {
            // Log email error but don't fail the request
            error_log("Failed to send notification email: " . $e->getMessage());
        }

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Your project request has been submitted successfully!',
            'request_id' => $request_id
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save your request. Please try again.'
        ]);
    }

} catch (Exception $e) {
    error_log("Project request submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again.'
    ]);
}
?>