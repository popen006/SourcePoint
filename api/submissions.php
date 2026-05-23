<?php
// =============================================
// API: Event Submissions (from organizations)
// SourcePoint - CamNorte Event Aggregator
// GET    = Fetch submissions (Admin)
// POST   = Submit proposal (Public)
// PUT    = Approve/Reject submission (Admin)
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/sms_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        // =============================================
        // GET: Fetch Submissions (Admin only)
        // =============================================
        case 'GET':
            requireAdminLogin();

            // Auto-cancel pending submissions whose event date has already passed
            $stmtAuto = $pdo->prepare("UPDATE submissions SET status = 'rejected', admin_notes = CONCAT(COALESCE(admin_notes, ''), ' [AUTO-CANCELLED: Event date has already passed on ', :today, ']'), reviewed_at = NOW() WHERE status = 'pending' AND event_date IS NOT NULL AND event_date < CURDATE()");
            $stmtAuto->execute([':today' => date('Y-m-d')]);

            $status = $_GET['status'] ?? 'all';
            $sql = "SELECT s.*, a.fullname as reviewer_name FROM submissions s LEFT JOIN admins a ON s.reviewed_by = a.id WHERE 1=1";
            $params = [];

            if ($status !== 'all') {
                $sql .= " AND s.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY s.submitted_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $submissions = $stmt->fetchAll();

            jsonResponse(['success' => true, 'submissions' => $submissions]);
            break;

        // =============================================
        // POST: Submit Event Proposal (Logged-in users only)
        // =============================================
        case 'POST':
            requireUserLogin();

            // Accept both JSON and multipart/form-data submissions
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($input) {
                // JSON submission (from contact page form)
                $org_name = trim($input['org_name'] ?? '');
                $contact_person = trim($input['contact_person'] ?? '');
                $contact_email = trim($input['contact_email'] ?? '');
                $contact_mobile = trim($input['contact_mobile'] ?? '');
                $event_title = trim($input['event_title'] ?? '');
                $event_description = trim($input['event_description'] ?? '');
                $event_category = trim($input['event_category'] ?? '');
                $event_location = trim($input['event_location'] ?? '');
                $event_date = $input['event_date'] ?? '';
                $event_organizer = trim($input['event_organizer'] ?? '');
                $partner_organizations = trim($input['partner_organizations'] ?? '');
                $coverImagePath = null;
            } else {
                // Form-data submission
                $org_name = trim($_POST['org_name'] ?? '');
                $contact_person = trim($_POST['contact_person'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_mobile = trim($_POST['contact_mobile'] ?? '');
                $event_title = trim($_POST['event_title'] ?? '');
                $event_description = trim($_POST['event_description'] ?? '');
                $event_category = trim($_POST['event_category'] ?? '');
                $event_location = trim($_POST['event_location'] ?? '');
                $event_date = $_POST['event_date'] ?? '';
                $event_organizer = trim($_POST['event_organizer'] ?? '');
                $partner_organizations = trim($_POST['partner_organizations'] ?? '');

                // Handle file upload (optional)
                $coverImagePath = null;
                if (isset($_FILES['cover_image']) && is_array($_FILES['cover_image']) && ($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['cover_image']['tmp_name'];
                    $origName = $_FILES['cover_image']['name'] ?? 'cover';

                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $allowed = ['png','jpg','jpeg','webp'];
                    if (in_array($ext, $allowed, true) && is_uploaded_file($tmpName)) {
                        $uploadsDir = realpath(__DIR__ . '/../uploads');
                        if ($uploadsDir === false) {
                            throw new Exception('Uploads directory not found');
                        }

                        $filename = 'submission_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                        $destPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;

                        if (!move_uploaded_file($tmpName, $destPath)) {
                            throw new Exception('Failed to move uploaded file');
                        }

                        // Store relative path (what UI can use)
                        $coverImagePath = 'uploads/' . $filename;
                    }
                }
            }

            if (empty($org_name) || empty($contact_person) || empty($contact_email) || empty($event_title) || empty($event_description)) {
                jsonResponse(['success' => false, 'message' => 'Organization name, contact person, email, event title, and description are required'], 400);
            }

            // Insert submission into database
            $stmt = $pdo->prepare("INSERT INTO submissions (org_name, contact_person, contact_email, contact_mobile, event_title, event_description, event_category, event_location, event_date, event_organizer, partner_organizations, cover_image)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Ensure optional fields are always stored as strings (or null)
            $contact_mobile = $contact_mobile !== '' ? $contact_mobile : null;
            $event_category = $event_category !== '' ? $event_category : null;
            $event_location = $event_location !== '' ? $event_location : null;
            $event_date = $event_date !== '' ? $event_date : null;
            $event_organizer = $event_organizer !== '' ? $event_organizer : null;
            $partner_organizations = $partner_organizations !== '' ? $partner_organizations : null;
            $coverImagePath = $coverImagePath !== '' ? $coverImagePath : null;

            $stmt->execute([
                $org_name,
                $contact_person,
                $contact_email,
                $contact_mobile,
                $event_title,
                $event_description,
                $event_category,
                $event_location,
                $event_date,
                $event_organizer,
                $partner_organizations,
                $coverImagePath
            ]);


            jsonResponse([
                'success' => true,
                'message' => 'Your event proposal has been submitted for review! We will contact you soon.'
            ], 201);
            break;

        // =============================================
        // PUT: Approve or Reject Submission (Admin only)
        // =============================================
        case 'PUT':
            requireAdminLogin();

            $input = json_decode(file_get_contents('php://input'), true);
            $submissionId = intval($input['id'] ?? 0);
            $action = $input['action'] ?? ''; // 'approved' or 'rejected'
            $adminNotes = trim($input['admin_notes'] ?? '');

            if ($submissionId <= 0 || !in_array($action, ['approved', 'rejected'])) {
                jsonResponse(['success' => false, 'message' => 'Valid submission ID and action (approved/rejected) are required'], 400);
            }

            // Fetch the submission
            $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch();

            if (!$submission) {
                jsonResponse(['success' => false, 'message' => 'Submission not found'], 404);
            }

            if ($submission['status'] !== 'pending') {
                jsonResponse(['success' => false, 'message' => 'This submission has already been ' . $submission['status']], 400);
            }

            // Update submission status
            $stmt = $pdo->prepare("UPDATE submissions SET status = ?, reviewed_by = ?, reviewed_at = NOW(), admin_notes = ? WHERE id = ?");
            $stmt->execute([$action, $_SESSION['admin_id'], $adminNotes, $submissionId]);

            // If approved, automatically create the event
            if ($action === 'approved') {
                // Find or create category
                $categoryId = null;
                if (!empty($submission['event_category'])) {
                    $stmtCat = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmtCat->execute([$submission['event_category']]);
                    $cat = $stmtCat->fetch();
                    if ($cat) {
                        $categoryId = $cat['id'];
                    }
                }

                // Use event_organizer if available, otherwise fall back to org_name
                $organizer = !empty($submission['event_organizer']) ? $submission['event_organizer'] : $submission['org_name'];
                
                // Use partner_organizations from submission if available
                $partnerOrgs = !empty($submission['partner_organizations']) ? $submission['partner_organizations'] : null;
                $coverImage = $submission['cover_image'] ?? null;

                // If cover_image exists in DB, it should be stored as a relative path like: uploads/<file>
                // UI expects ../{cover_image}.
                $stmt = $pdo->prepare("INSERT INTO events (title, description, category_id, location, event_date, organizer, partner_organizations, created_by, status, cover_image) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', ?)");
                $stmt->execute([
                    $submission['event_title'],
                    $submission['event_description'],
                    $categoryId,
                    $submission['event_location'] ?: 'To be announced',
                    $submission['event_date'] ?: date('Y-m-d', strtotime('+30 days')),
                    $organizer,
                    $partnerOrgs,
                    $_SESSION['admin_id'],
                    $coverImage
                ]);


                $eventId = $pdo->lastInsertId();

                // Send SMS notification to submitter
                if (!empty($submission['contact_mobile'])) {
                    $approvalMessage = "Good news! Your event proposal \"{$submission['event_title']}\" submitted by {$submission['org_name']} has been APPROVED and is now live on SourcePoint! Thank you for your contribution to Camarines Norte.";
                    sendUniSMS($submission['contact_mobile'], $approvalMessage);
                }

                jsonResponse([
                    'success' => true,
                    'message' => 'Submission approved and event created successfully!',
                    'event_id' => $eventId
                ]);
            } else {
                // Send SMS notification to submitter
                if (!empty($submission['contact_mobile'])) {
                    $reasonText = !empty($adminNotes) ? " Notes from admin: {$adminNotes}" : "";
                    $rejectionMessage = "Your event proposal \"{$submission['event_title']}\" submitted by {$submission['org_name']} has been reviewed. Unfortunately, it was not approved at this time.{$reasonText} You may resubmit with updated details. Thank you!";
                    sendUniSMS($submission['contact_mobile'], $rejectionMessage);
                }

                jsonResponse([
                    'success' => true,
                    'message' => 'Submission rejected successfully'
                ]);
            }
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
?>