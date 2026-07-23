<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

// Set page title
$page_title = 'Language Test';

// Include header
require_once 'includes/header.php';
?>

<!-- Page Content -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-language me-2"></i>Language Test Page</h5>
            </div>
            <div class="card-body">
                <h3>Current Language Information</h3>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <strong>Current Language:</strong> <?= getCurrentLanguage() ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Session Language:</strong> <?= $_SESSION['language'] ?? 'Not set' ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Cookie Language:</strong> <?= $_COOKIE['bc_language'] ?? 'Not set' ?>
                    </li>
                </ul>

                <h3 class="mt-4">Translation Tests</h3>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Translation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>dashboard</td>
                                <td><?= t('dashboard') ?></td>
                            </tr>
                            <tr>
                                <td>members</td>
                                <td><?= t('members') ?></td>
                            </tr>
                            <tr>
                                <td>groups</td>
                                <td><?= t('groups') ?></td>
                            </tr>
                            <tr>
                                <td>welcome</td>
                                <td><?= t('welcome') ?></td>
                            </tr>
                            <tr>
                                <td>language</td>
                                <td><?= t('language') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-4">Available Languages</h3>
                <div class="row">
                    <?php foreach (getAvailableLanguages() as $code => $language): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card <?= getCurrentLanguage() === $code ? 'border-primary' : '' ?>">
                                <div class="card-body text-center">
                                    <h5><?= $language['flag'] ?> <?= $language['name'] ?></h5>
                                    <p>Code: <code><?= $code ?></code></p>
                                    <?php if (getCurrentLanguage() === $code): ?>
                                        <span class="badge bg-primary">Current</span>
                                    <?php else: ?>
                                        <a href="?change_language=<?= $code ?>" class="btn btn-outline-primary btn-sm">
                                            Switch to <?= $language['name'] ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="mt-4">Debug Information</h3>
                <div class="alert alert-info">
                    <h6>Language File Paths:</h6>
                    <ul>
                        <li>English: <?= __DIR__ . '/../common/languages/en.php' ?> 
                            <?= file_exists(__DIR__ . '/../common/languages/en.php') ? '✅' : '❌' ?>
                        </li>
                        <li>Hindi: <?= __DIR__ . '/../common/languages/hi.php' ?> 
                            <?= file_exists(__DIR__ . '/../common/languages/hi.php') ? '✅' : '❌' ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
