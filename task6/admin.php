<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config.php';

function authenticateBasic() {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Need authentication';
        exit;
    }
    $login = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    global $pdo;
    $passwordHash = getAdminByLogin($pdo, $login);
    if (!$passwordHash || !checkPassword($password, $passwordHash)) {
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Invalid credentials';
        exit;
    }
}

authenticateBasic();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'edit') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid ID';
        exit;
    }
    $appData = getApplicationByID($pdo, $id);
    if (!$appData) {
        http_response_code(404);
        echo 'Form not found';
        exit;
    }
    renderAdminEdit($appData, $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    if (!validateCSRFToken()) {
        http_response_code(403);
        echo 'Request denied';
        exit;
    }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid ID';
        exit;
    }
    list($formData, $errors) = validateFormData($_POST);
    if (!empty($errors)) {
        renderAdminEdit($formData, $id, $errors);
        exit;
    }
    if (updateApplication($pdo, $id, $formData)) {
        header('Location: admin.php');
        exit;
    } else {
        http_response_code(500);
        echo 'Update error';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    if (!validateCSRFToken()) {
        http_response_code(403);
        echo 'Request denied';
        exit;
    }
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid ID';
        exit;
    }
    if (deleteApplication($pdo, $id)) {
        header('Location: admin.php');
        exit;
    } else {
        http_response_code(500);
        echo 'Delete error';
        exit;
    }
}

$applications = getAllApplications($pdo);
$stats = getLanguageStats($pdo);
$csrfToken = getOrCreateCSRFToken();

renderAdminList($applications, $stats, $csrfToken);

function renderAdminList($apps, $stats, $csrfToken) {
    $maxCount = 0;
    foreach ($stats as $s) if ($s['count'] > $maxCount) $maxCount = $s['count'];
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ панель</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #12161c;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, sans-serif;
            padding: 30px 20px;
        }
        .topbar {
            max-width: 1200px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e242c;
            padding: 0.8rem 1.5rem;
            border-radius: 2rem;
            border: 1px solid #2e3a42;
        }
        h1, h2 {
            color: #e2e8f0;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 550;
        }
        h2 {
            font-size: 1.4rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: #bdd4e8;
        }
        .card {
            background: #1e242c;
            border-radius: 2rem;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            padding: 1.8rem;
            margin-bottom: 2rem;
            border: 1px solid #2e3a42;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        th {
            background: #10161c;
            color: #bdd4e8;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #2e3a42;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #2e3b44;
            vertical-align: top;
            color: #cddfed;
        }
        .lang-badge {
            display: inline-block;
            background: #10161c;
            color: #7f9bb5;
            border-radius: 2rem;
            padding: 2px 10px;
            font-size: 0.7rem;
            margin: 2px;
            border: 1px solid #2e3a42;
        }
        .btn {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 1.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-edit {
            background: #2e3b44;
            color: #e2e8f0;
            border: 1px solid #4a5b66;
        }
        .btn-edit:hover {
            background: #3a4a55;
            border-color: #7f9bb5;
        }
        .btn-delete {
            background: #1f1820;
            color: #e0a98b;
            border: 1px solid #5e4a42;
            margin-left: 6px;
        }
        .btn-delete:hover {
            background: #2a1e1c;
            border-color: #b87a6c;
        }
        .stat-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            gap: 12px;
        }
        .stat-name {
            width: 120px;
            font-weight: 500;
            color: #bdd4e8;
        }
        .stat-bar-wrap {
            flex: 1;
            background: #2e3b44;
            border-radius: 1rem;
            height: 20px;
            overflow: hidden;
        }
        .stat-bar {
            height: 100%;
            background: #7f9bb5;
            width: 0%;
            border-radius: 1rem;
        }
        .stat-count {
            width: 35px;
            text-align: right;
            color: #bdd4e8;
        }
        .edit-form .field {
            margin-bottom: 1.2rem;
        }
        .edit-form label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: #7f9bb5;
            margin-bottom: 0.3rem;
        }
        .edit-form input, .edit-form select, .edit-form textarea {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid #2e3a42;
            border-radius: 1rem;
            background: #10161c;
            color: #e2e8f0;
        }
        .edit-form .field-error input {
            border-color: #b87a6c;
            background: #1f1820;
        }
        .edit-form .error-msg {
            font-size: 0.7rem;
            color: #e0a98b;
            margin-top: 0.3rem;
        }
        .edit-form .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.3rem;
        }
        .btn-save {
            background: #2e3b44;
            color: #e2e8f0;
            border: 1px solid #4a5b66;
            padding: 10px 24px;
            font-size: 0.9rem;
            border-radius: 1.5rem;
            cursor: pointer;
        }
        .btn-save:hover {
            background: #3a4a55;
            border-color: #7f9bb5;
        }
        .btn-cancel {
            background: #10161c;
            color: #7f9bb5;
            border: 1px solid #2e3a42;
            padding: 10px 24px;
            margin-left: 10px;
            text-decoration: none;
            border-radius: 1.5rem;
        }
        .btn-cancel:hover {
            background: #1a212a;
            border-color: #5e7687;
        }
        a {
            color: #7f9bb5;
            text-decoration: none;
        }
        a:hover {
            color: #bdd4e8;
        }
    </style>
</head>
<body>
<div class="topbar">
    <h1>Панель администратора</h1>
    <a href="../task6/index.php">На главную</a>
</div>

<div class="card">
    <h2>Все анкеты (<?= count($apps) ?>)</h2>
    <?php if ($apps): ?>
    <table>
        <thead>
            <tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рождения</th><th>Пол</th><th>Языки</th><th>Действия</th></tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $app): ?>
        <tr>
            <td><?= htmlspecialchars($app['id']) ?></td>
            <td><?= htmlspecialchars($app['name']) ?></td>
            <td><?= htmlspecialchars($app['phone']) ?></td>
            <td><?= htmlspecialchars($app['email']) ?></td>
            <td><?= htmlspecialchars($app['birthdate']) ?></td>
            <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
            <td><?php foreach ($app['languages'] as $lang): ?><span class="lang-badge"><?= htmlspecialchars($lang) ?></span><?php endforeach; ?></td>
            <td>
                <a href="admin.php?action=edit&id=<?= $app['id'] ?>" class="btn btn-edit">Изменить</a>
                <form style="display:inline" action="admin.php?action=delete&id=<?= $app['id'] ?>" method="POST" onsubmit="return confirm('Удалить анкету #<?= $app['id'] ?>?')">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                    <button type="submit" class="btn btn-delete">Удалить</button>
                </form>
             </nav>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="color:#7f9bb5">Анкет пока нет</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Статистика по языкам</h2>
    <?php foreach ($stats as $s): ?>
    <div class="stat-row">
        <span class="stat-name"><?= htmlspecialchars($s['name']) ?></span>
        <div class="stat-bar-wrap"><div class="stat-bar" style="width: <?= $maxCount > 0 ? round($s['count'] / $maxCount * 100) : 0 ?>%"></div></div>
        <span class="stat-count"><?= $s['count'] ?></span>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
<?php
}

function renderAdminEdit($appData, $id, $errors = []) {
    global $ALL_LANGUAGES;
    $csrfToken = getOrCreateCSRFToken();
    $isSelected = function($langId) use ($appData) {
        return in_array($langId, $appData['languages'] ?? []);
    };
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование анкеты #<?= $id ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #12161c;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, sans-serif;
            padding: 30px 20px;
        }
        .card {
            background: #1e242c;
            border-radius: 2rem;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            padding: 1.8rem;
            max-width: 680px;
            margin: 0 auto;
            border: 1px solid #2e3a42;
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 550;
            color: #e2e8f0;
            margin-bottom: 1.5rem;
        }
        .field {
            margin-bottom: 1.2rem;
        }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: #7f9bb5;
            margin-bottom: 0.3rem;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid #2e3a42;
            border-radius: 1rem;
            background: #10161c;
            color: #e2e8f0;
            transition: 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #7f9bb5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(127, 155, 181, 0.2);
        }
        .field-error input {
            border-color: #b87a6c;
            background: #1f1820;
        }
        .error-msg {
            font-size: 0.7rem;
            color: #e0a98b;
            margin-top: 0.3rem;
        }
        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.3rem;
        }
        .btn-save {
            background: #2e3b44;
            color: #e2e8f0;
            border: 1px solid #4a5b66;
            padding: 10px 24px;
            border-radius: 1.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-save:hover {
            background: #3a4a55;
            border-color: #7f9bb5;
        }
        .btn-cancel {
            background: #10161c;
            color: #7f9bb5;
            border: 1px solid #2e3a42;
            padding: 10px 24px;
            margin-left: 10px;
            text-decoration: none;
            border-radius: 1.5rem;
        }
        .btn-cancel:hover {
            background: #1a212a;
            border-color: #5e7687;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Редактирование анкеты #<?= $id ?></h1>
    <form action="admin.php?action=edit&id=<?= $id ?>" method="POST" class="edit-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field <?= isset($errors['name']) ? 'field-error' : '' ?>">
            <label>ФИО</label>
            <input type="text" name="name" value="<?= htmlspecialchars($appData['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($appData['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['email']) ? 'field-error' : '' ?>">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($appData['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['birthdate']) ? 'field-error' : '' ?>">
            <label>Дата рождения</label>
            <input type="date" name="birthdate" value="<?= htmlspecialchars($appData['birthdate'] ?? '') ?>">
            <?php if (isset($errors['birthdate'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['birthdate']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['gender']) ? 'field-error' : '' ?>">
            <label>Пол</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= ($appData['gender'] ?? '') === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= ($appData['gender'] ?? '') === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['languages']) ? 'field-error' : '' ?>">
            <label>Языки программирования</label>
            <select name="languages[]" multiple size="5">
                <?php foreach ($ALL_LANGUAGES as $lang): ?>
                <option value="<?= htmlspecialchars($lang['id']) ?>" <?= $isSelected($lang['id']) ? 'selected' : '' ?>><?= htmlspecialchars($lang['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['bio']) ? 'field-error' : '' ?>">
            <label>Биография</label>
            <textarea name="bio"><?= htmlspecialchars($appData['bio'] ?? '') ?></textarea>
            <?php if (isset($errors['bio'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['bio']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['contract']) ? 'field-error' : '' ?>">
            <label style="display:flex; align-items:center; gap:8px;">
                <input type="checkbox" name="contract" <?= ($appData['contract'] ?? false) ? 'checked' : '' ?>> С контрактом ознакомлен(а)
            </label>
            <?php if (isset($errors['contract'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['contract']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-save">Сохранить</button>
        <a href="admin.php" class="btn-cancel">Отмена</a>
    </form>
</div>
</body>
</html>
<?php
}
?>