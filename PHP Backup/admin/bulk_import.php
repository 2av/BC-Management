<?php
require_once 'config.php';
requireAdminLogin();

$error = '';
$success = '';
$importResults = [];

// Get all groups for selection
$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM bc_groups WHERE status = 'active' ORDER BY group_name");
$groups = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['download_template'])) {
        // Download CSV template
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="member_import_template.csv"');
        
        echo "member_name,member_number,username,phone,email,address\n";
        echo "John Doe,1,john_doe,9876543210,john@example.com,123 Main St\n";
        echo "Jane Smith,2,jane_smith,9876543211,jane@example.com,456 Oak Ave\n";
        echo "Bob Johnson,3,,9876543212,bob@example.com,789 Pine Rd\n";
        exit;
    }
    
    if (isset($_POST['import_members'])) {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $skipDuplicates = isset($_POST['skip_duplicates']);
        $resetPasswords = isset($_POST['reset_passwords']);
        
        if ($groupId <= 0) {
            $error = 'Please select a valid group.';
        } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please upload a valid CSV file.';
        } else {
            try {
                $csvFile = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($csvFile, 'r');
                
                if (!$handle) {
                    throw new Exception('Could not read CSV file.');
                }
                
                // Get group info
                $stmt = $pdo->prepare("SELECT group_name, total_members FROM bc_groups WHERE id = ?");
                $stmt->execute([$groupId]);
                $groupInfo = $stmt->fetch();
                
                if (!$groupInfo) {
                    throw new Exception('Selected group not found.');
                }
                
                // Get existing member numbers in group
                $stmt = $pdo->prepare("SELECT member_number FROM members WHERE group_id = ?");
                $stmt->execute([$groupId]);
                $existingNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Get existing usernames
                $stmt = $pdo->query("SELECT username FROM members WHERE username IS NOT NULL");
                $existingUsernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $pdo->beginTransaction();
                
                $header = fgetcsv($handle);
                $rowNumber = 1;
                $imported = 0;
                $skipped = 0;
                $errors = 0;
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    $rowNumber++;
                    
                    if (count($row) < 2) {
                        $importResults[] = [
                            'row' => $rowNumber,
                            'status' => 'error',
                            'message' => 'Insufficient data in row'
                        ];
                        $errors++;
                        continue;
                    }
                    
                    $memberName = trim($row[0] ?? '');
                    $memberNumber = (int)($row[1] ?? 0);
                    $username = trim($row[2] ?? '');
                    $phone = trim($row[3] ?? '');
                    $email = trim($row[4] ?? '');
                    $address = trim($row[5] ?? '');
                    
                    // Validation
                    if (empty($memberName)) {
                        $importResults[] = [
                            'row' => $rowNumber,
                            'status' => 'error',
                            'message' => 'Member name is required'
                        ];
                        $errors++;
                        continue;
                    }
                    
                    if ($memberNumber <= 0 || $memberNumber > $groupInfo['total_members']) {
                        $importResults[] = [
                            'row' => $rowNumber,
                            'status' => 'error',
                            'message' => "Invalid member number. Must be between 1 and {$groupInfo['total_members']}"
                        ];
                        $errors++;
                        continue;
                    }
                    
                    // Check for duplicate member number
                    if (in_array($memberNumber, $existingNumbers)) {
                        if ($skipDuplicates) {
                            $importResults[] = [
                                'row' => $rowNumber,
                                'status' => 'skipped',
                                'message' => "Member number {$memberNumber} already exists"
                            ];
                            $skipped++;
                            continue;
                        } else {
                            $importResults[] = [
                                'row' => $rowNumber,
                                'status' => 'error',
                                'message' => "Member number {$memberNumber} already exists"
                            ];
                            $errors++;
                            continue;
                        }
                    }
                    
                    // Check for duplicate username
                    if (!empty($username) && in_array($username, $existingUsernames)) {
                        if ($skipDuplicates) {
                            $username = ''; // Clear username to avoid conflict
                            $importResults[] = [
                                'row' => $rowNumber,
                                'status' => 'warning',
                                'message' => "Username '{$username}' exists, imported without username"
                            ];
                        } else {
                            $importResults[] = [
                                'row' => $rowNumber,
                                'status' => 'error',
                                'message' => "Username '{$username}' already exists"
                            ];
                            $errors++;
                            continue;
                        }
                    }
                    
                    // Insert member
                    $password = $resetPasswords ? 'member123' : 'member123';
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO members (group_id, member_name, member_number, username, password, phone, email, address, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    $stmt->execute([
                        $groupId,
                        $memberName,
                        $memberNumber,
                        $username ?: null,
                        $hashedPassword,
                        $phone ?: null,
                        $email ?: null,
                        $address ?: null
                    ]);
                    
                    $memberId = $pdo->lastInsertId();
                    
                    // Create member summary
                    $stmt = $pdo->prepare("
                        INSERT INTO member_summary (member_id, total_paid, given_amount, profit) 
                        VALUES (?, 0, 0, 0)
                    ");
                    $stmt->execute([$memberId]);
                    
                    // Track for duplicates
                    $existingNumbers[] = $memberNumber;
                    if ($username) {
                        $existingUsernames[] = $username;
                    }
                    
                    $importResults[] = [
                        'row' => $rowNumber,
                        'status' => 'success',
                        'message' => "Successfully imported {$memberName}"
                    ];
                    $imported++;
                }
                
                fclose($handle);
                $pdo->commit();
                
                $success = "Import completed! Imported: {$imported}, Skipped: {$skipped}, Errors: {$errors}";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Import failed: ' . $e->getMessage();
            }
        }
    }
}

// Set page title for the header
$page_title = 'Bulk Import Members';

// Include the new header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
    .import-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
    }
    .file-upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
    }
    .file-upload-area:hover {
        border-color: #667eea;
        background-color: #f8f9fa;
    }
    .file-upload-area.dragover {
        border-color: #667eea;
        background-color: #e3f2fd;
    }
</style>

<!-- Page content starts here -->
<div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card import-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-upload fa-3x mb-3"></i>
                            <h3>Bulk Import Members</h3>
                            <p class="mb-0">Import multiple members from CSV file</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Step 1: Download Template -->
                        <div class="card bg-light text-dark mb-4">
                            <div class="card-body">
                                <h5><i class="fas fa-download"></i> Step 1: Download Template</h5>
                                <p class="mb-3">Download the CSV template to see the required format for member data.</p>
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="download_template" class="btn btn-info">
                                        <i class="fas fa-download"></i> Download CSV Template
                                    </button>
                                </form>
                                
                                <div class="mt-3">
                                    <strong>CSV Format:</strong>
                                    <code>member_name, member_number, username, phone, email, address</code>
                                    <ul class="mt-2 mb-0">
                                        <li><strong>member_name</strong>: Required - Full name of the member</li>
                                        <li><strong>member_number</strong>: Required - Unique number within the group (1 to total members)</li>
                                        <li><strong>username</strong>: Optional - Login username (leave empty to use member name)</li>
                                        <li><strong>phone</strong>: Optional - Contact phone number</li>
                                        <li><strong>email</strong>: Optional - Email address</li>
                                        <li><strong>address</strong>: Optional - Physical address</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Import Members -->
                        <form method="POST" enctype="multipart/form-data">
                            <div class="card bg-light text-dark">
                                <div class="card-body">
                                    <h5><i class="fas fa-upload"></i> Step 2: Import Members</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="group_id" class="form-label">
                                                <i class="fas fa-users"></i> Select Group *
                                            </label>
                                            <select class="form-select" id="group_id" name="group_id" required>
                                                <option value="">Choose Group</option>
                                                <?php foreach ($groups as $group): ?>
                                                    <option value="<?= $group['id'] ?>">
                                                        <?= htmlspecialchars($group['group_name']) ?> 
                                                        (<?= $group['total_members'] ?> members max)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Import Options</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="skip_duplicates" name="skip_duplicates" checked>
                                                <label class="form-check-label" for="skip_duplicates">
                                                    Skip duplicate member numbers
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="reset_passwords" name="reset_passwords" checked>
                                                <label class="form-check-label" for="reset_passwords">
                                                    Set default password "member123"
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="csv_file" class="form-label">
                                            <i class="fas fa-file-csv"></i> CSV File *
                                        </label>
                                        <div class="file-upload-area" onclick="document.getElementById('csv_file').click()">
                                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                            <h5>Click to select CSV file</h5>
                                            <p class="text-muted mb-0">Or drag and drop your CSV file here</p>
                                            <input type="file" class="form-control d-none" id="csv_file" name="csv_file" 
                                                   accept=".csv" required onchange="updateFileName()">
                                        </div>
                                        <div id="file-name" class="mt-2 text-center"></div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="import_members" class="btn btn-success btn-lg">
                                            <i class="fas fa-upload"></i> Import Members
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Import Results -->
                <?php if (!empty($importResults)): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-list"></i> Import Results
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Row</th>
                                            <th>Status</th>
                                            <th>Message</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($importResults as $result): ?>
                                            <tr>
                                                <td><?= $result['row'] ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $result['status'] === 'success' ? 'success' : 
                                                        ($result['status'] === 'warning' ? 'warning' : 
                                                        ($result['status'] === 'skipped' ? 'info' : 'danger')) ?>">
                                                        <?= ucfirst($result['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($result['message']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateFileName() {
            const fileInput = document.getElementById('csv_file');
            const fileName = document.getElementById('file-name');
            
            if (fileInput.files.length > 0) {
                fileName.innerHTML = `<i class="fas fa-file-csv text-success"></i> Selected: ${fileInput.files[0].name}`;
            }
        }
        
        // Drag and drop functionality
        const uploadArea = document.querySelector('.file-upload-area');
        const fileInput = document.getElementById('csv_file');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileName();
            }
        });
    </script>

<?php require_once 'includes/footer.php'; ?>
