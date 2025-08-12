<?php
require_once 'config.php';
requireAdminLogin();

// Get all existing member names from database for suggestions
$pdo = getDB();
$stmt = $pdo->query("SELECT DISTINCT member_name FROM members ORDER BY member_name");
$existingMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupName = trim($_POST['group_name'] ?? '');
    $totalMembers = (int)($_POST['total_members'] ?? 0);
    $monthlyContribution = (float)($_POST['monthly_contribution'] ?? 0);
    $startDate = $_POST['start_date'] ?? '';
    $memberNames = $_POST['member_names'] ?? [];
    
    // Validation
    if (empty($groupName)) {
        $error = 'Group name is required.';
    } elseif ($totalMembers < 2 || $totalMembers > 50) {
        $error = 'Total members must be between 2 and 50.';
    } elseif ($monthlyContribution <= 0) {
        $error = 'Monthly contribution must be greater than 0.';
    } elseif (empty($startDate)) {
        $error = 'Start date is required.';
    } elseif (count(array_filter($memberNames)) !== $totalMembers) {
        $error = 'Please enter all member names.';
    } else {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();
            
            $totalMonthlyCollection = $totalMembers * $monthlyContribution;
            
            // Create group
            $stmt = $pdo->prepare("
                INSERT INTO bc_groups (group_name, total_members, monthly_contribution, total_monthly_collection, start_date) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$groupName, $totalMembers, $monthlyContribution, $totalMonthlyCollection, $startDate]);
            $groupId = $pdo->lastInsertId();
            
            // Add members with auto-generated login credentials
            $stmt = $pdo->prepare("INSERT INTO members (group_id, member_name, member_number, username, password) VALUES (?, ?, ?, ?, ?)");
            foreach ($memberNames as $index => $memberName) {
                if (!empty(trim($memberName))) {
                    $cleanName = strtolower(str_replace(' ', '', trim($memberName)));
                    $username = $cleanName . ($index + 1);
                    $password = password_hash('member123', PASSWORD_DEFAULT);

                    $stmt->execute([$groupId, trim($memberName), $index + 1, $username, $password]);
                }
            }
            
            $pdo->commit();
            
            setMessage("BC Group '{$groupName}' created successfully!");
            redirect("view_group.php?id={$groupId}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to create group. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create BC Group - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .name-suggestion {
            cursor: pointer;
            transition: all 0.2s;
        }
        .name-suggestion:hover {
            background-color: #007bff !important;
            color: white !important;
            transform: scale(1.05);
        }
        .member-input-group {
            position: relative;
        }
        .quick-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins"></i> <?= APP_NAME ?>
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Create New BC Group</h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="groupForm">
                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name *</label>
                                <input type="text" class="form-control" id="group_name" name="group_name" 
                                       value="<?= htmlspecialchars($_POST['group_name'] ?? '') ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="total_members" class="form-label">Total Members *</label>
                                        <input type="number" class="form-control" id="total_members" name="total_members" 
                                               value="<?= htmlspecialchars($_POST['total_members'] ?? '') ?>" 
                                               min="2" max="50" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="monthly_contribution" class="form-label">Monthly Contribution *</label>
                                        <input type="number" class="form-control" id="monthly_contribution" name="monthly_contribution" 
                                               value="<?= htmlspecialchars($_POST['monthly_contribution'] ?? '') ?>" 
                                               min="100" step="50" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Member Names *</label>
                                <div class="quick-actions">
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addExistingMembers()">
                                            <i class="fas fa-user-friends"></i> Fill from Existing Members
                                        </button>
                                        <button type="button" class="btn btn-outline-info btn-sm" onclick="addFamilyTemplate()">
                                            <i class="fas fa-users"></i> Family Template
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="showAddNewMemberModal(0)">
                                            <i class="fas fa-plus"></i> Add New Member
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllNames()">
                                            <i class="fas fa-eraser"></i> Clear All
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        <i class="fas fa-lightbulb"></i>
                                        Select from existing members or add new ones. Click on name badges below for quick selection.
                                    </small>
                                </div>
                                <div id="memberNames">
                                    <!-- Member name inputs will be generated here -->
                                </div>

                                <!-- Existing Members Suggestions -->
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <strong>Existing Members:</strong> Click on names to add them quickly
                                    </small>
                                    <div id="commonNamesSuggestions" class="mt-1">
                                        <!-- Existing member names will be populated here -->
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6>Calculated Values</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Total Monthly Collection:</strong>
                                            <div class="text-primary fs-5" id="totalCollection">₹0</div>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Duration:</strong>
                                            <div class="text-info fs-5" id="duration">0 months</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Create Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCalculations() {
            const members = parseInt(document.getElementById('total_members').value) || 0;
            const contribution = parseFloat(document.getElementById('monthly_contribution').value) || 0;
            
            const totalCollection = members * contribution;
            
            document.getElementById('totalCollection').textContent = '₹' + totalCollection.toLocaleString();
            document.getElementById('duration').textContent = members + ' months';
        }
        
        // Existing members from database
        const existingMembers = <?= json_encode($existingMembers) ?>;

        // Common surnames for new members
        const commonSurnames = [
            'Vishwakarma', 'Sharma', 'Gupta', 'Singh', 'Kumar', 'Patil', 'Shukla', 'Bhardwaj',
            'Yadav', 'Verma', 'Mishra', 'Agarwal', 'Jain', 'Tiwari'
        ];

        function generateMemberInputs() {
            const members = parseInt(document.getElementById('total_members').value) || 0;
            const container = document.getElementById('memberNames');

            container.innerHTML = '';

            for (let i = 1; i <= members; i++) {
                const div = document.createElement('div');
                div.className = 'mb-2';
                div.innerHTML = `
                    <div class="input-group">
                        <input type="text" class="form-control member-name-input" name="member_names[]"
                               placeholder="Member ${i} Name (type to search or select from dropdown)" required id="member_${i}"
                               list="memberSuggestions_${i}" autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false" title="Select from existing members">
                            <i class="fas fa-user-friends"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="max-height: 300px; overflow-y: auto;">
                            <li><h6 class="dropdown-header"><i class="fas fa-users"></i> Existing Members</h6></li>
                            ${existingMembers.length > 0 ?
                                existingMembers.slice(0, 15).map(name =>
                                    `<li><a class="dropdown-item" href="#" onclick="setMemberName(${i}, '${name.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-user"></i> ${name}
                                    </a></li>`
                                ).join('') :
                                '<li><span class="dropdown-item-text text-muted">No existing members found</span></li>'
                            }
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-primary" href="#" onclick="showAddNewMemberModal(${i})">
                                <i class="fas fa-plus"></i> Add New Member
                            </a></li>
                        </ul>
                        <datalist id="memberSuggestions_${i}">
                            ${existingMembers.map(name => `<option value="${name}">`).join('')}
                        </datalist>
                    </div>
                `;
                container.appendChild(div);
            }

            generateCommonNamesSuggestions();
        }

        function generateCommonNamesSuggestions() {
            const container = document.getElementById('commonNamesSuggestions');
            if (existingMembers.length > 0) {
                container.innerHTML = existingMembers.slice(0, 20).map(name =>
                    `<span class="badge bg-primary text-white me-1 mb-1 name-suggestion"
                           onclick="addNameToFirstEmpty('${name.replace(/'/g, "\\'")})" title="Click to add ${name}">
                        <i class="fas fa-user"></i> ${name}
                    </span>`
                ).join('') +
                `<span class="badge bg-success text-white me-1 mb-1 name-suggestion"
                       onclick="showAddNewMemberModal(0)" title="Add a completely new member">
                    <i class="fas fa-plus"></i> Add New Member
                </span>`;
            } else {
                container.innerHTML = `
                    <span class="badge bg-warning text-dark me-1 mb-1">
                        <i class="fas fa-info-circle"></i> No existing members found
                    </span>
                    <span class="badge bg-success text-white me-1 mb-1 name-suggestion"
                           onclick="showAddNewMemberModal(0)" title="Add a completely new member">
                        <i class="fas fa-plus"></i> Add New Member
                    </span>
                `;
            }
        }

        function setMemberName(memberIndex, name) {
            document.getElementById(`member_${memberIndex}`).value = name;
        }

        function addNameToFirstEmpty(name) {
            const inputs = document.querySelectorAll('.member-name-input');
            for (let input of inputs) {
                if (!input.value.trim()) {
                    input.value = name;
                    input.focus();
                    break;
                }
            }
        }

        function showAddNewMemberModal(memberIndex) {
            const newName = prompt('Enter new member name:', '');
            if (newName && newName.trim()) {
                if (memberIndex > 0) {
                    document.getElementById(`member_${memberIndex}`).value = newName.trim();
                } else {
                    addNameToFirstEmpty(newName.trim());
                }
            }
        }

        function addExistingMembers() {
            const inputs = document.querySelectorAll('.member-name-input');
            const shuffledMembers = [...existingMembers].sort(() => 0.5 - Math.random());

            inputs.forEach((input, index) => {
                if (!input.value.trim() && index < shuffledMembers.length) {
                    input.value = shuffledMembers[index];
                }
            });
        }

        function addFamilyTemplate() {
            const inputs = document.querySelectorAll('.member-name-input');
            const familyTemplate = [
                'Akhilesh Vishwakarma', 'Ghanshyam Vishwakarma', 'Mohanish Patil',
                'Pradeep Shukla', 'Manish Vishwakarma', 'Rahul Vishwakarma',
                'Vishal Bhardwaj', 'Vishnu Kumar', 'Vishal Vishwakarma'
            ];

            inputs.forEach((input, index) => {
                if (index < familyTemplate.length) {
                    input.value = familyTemplate[index];
                }
            });
        }

        function clearAllNames() {
            const inputs = document.querySelectorAll('.member-name-input');
            inputs.forEach(input => {
                input.value = '';
            });
        }
        
        document.getElementById('total_members').addEventListener('input', function() {
            updateCalculations();
            generateMemberInputs();
        });
        
        document.getElementById('monthly_contribution').addEventListener('input', updateCalculations);
        
        // Initial setup
        updateCalculations();
        generateMemberInputs();
    </script>
</body>
</html>
