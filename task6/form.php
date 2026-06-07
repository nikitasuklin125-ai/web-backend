<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cookies.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/csrf.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $pageData = loadFromCookies();
    $pageData['csrf_token'] = getOrCreateCSRFToken();
    $newCreds = null;
    $rawCreds = getCookieValue('new_credentials');
    if ($rawCreds !== null) {
        decodeFromCookie($rawCreds, $newCreds);
        deleteCookie('new_credentials');
    }
    renderForm($pageData, $newCreds);
    exit;
}

if ($method === 'POST') {
    if (!validateCSRFToken()) {
        http_response_code(403);
        echo 'Request denied';
        exit;
    }

    list($formData, $errors) = validateFormData($_POST);
    if (!empty($errors)) {
        saveErrorsToCookie($errors, $formData);
        header('Location: form.php');
        exit;
    }

    $appId = saveToDatabase($pdo, $formData);
    if ($appId === false) {
        http_response_code(500);
        echo 'Internal server error. Try again later';
        exit;
    }

    $login = generateLogin();
    $password = generatePassword();
    $passwordHash = hashPassword($password);
    if (!saveCredentials($pdo, $appId, $login, $passwordHash)) {
        http_response_code(500);
        echo 'Internal server error';
        exit;
    }

    saveSuccessToCookie($formData);
    setSessionCookie('new_credentials', encodeToCookie(['login' => $login, 'password' => $password]));
    header('Location: form.php');
    exit;
}

http_response_code(405);
echo 'Method not allowed';

function renderForm($pageData, $newCreds) {
    global $ALL_LANGUAGES;
    $values = $pageData['values'];
    $errors = $pageData['errors'];
    $success = $pageData['success'];
    $csrfToken = htmlspecialchars($pageData['csrf_token']);

    $isSelectedLang = function($id) use ($values) {
        return in_array($id, $values['languages'] ?? []);
    };
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #12161c;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #1e242c;
            border-radius: 2rem;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            padding: 2.2rem 2rem 2.5rem;
            width: 100%;
            max-width: 680px;
            transition: all 0.25s ease;
            border: 1px solid #2e3a42;
            animation: fadeSlideUp 0.45s ease-out;
        }
        .card:hover {
            border-color: #4a5b66;
            box-shadow: 0 24px 42px -14px rgba(0, 0, 0, 0.6);
        }
        h1 {
            font-size: 2rem;
            font-weight: 550;
            letter-spacing: -0.3px;
            color: #e2e8f0;
            margin-bottom: 1.8rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #2e3b44;
            display: inline-block;
        }
        .field {
            margin-bottom: 1.5rem;
        }
        .field > label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: #7f9bb5;
            margin-bottom: 0.4rem;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #2e3a42;
            border-radius: 1rem;
            font-size: 0.95rem;
            font-family: inherit;
            background: #10161c;
            transition: all 0.2s ease;
            outline: none;
            color: #e2e8f0;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #7f9bb5;
            box-shadow: 0 0 0 2px rgba(127, 155, 181, 0.2);
            background: #0f151c;
        }
        .field-error input, .field-error select, .field-error textarea {
            border-color: #b87a6c;
            background: #1f1820;
        }
        .error-msg {
            font-size: 0.75rem;
            color: #e0a98b;
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .error-msg::before {
            content: "▸";
            font-size: 0.7rem;
            color: #b87a6c;
        }
        textarea {
            height: 110px;
            resize: vertical;
        }
        select[multiple] {
            height: 160px;
        }
        .radio-group {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 0.3rem;
        }
        .radio-group label, .checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 400;
            color: #bdd4e8;
            cursor: pointer;
        }
        input[type="radio"], input[type="checkbox"] {
            accent-color: #7f9bb5;
            width: 1rem;
            height: 1rem;
            margin: 0;
        }
        .btn {
            width: 100%;
            padding: 0.9rem;
            background: #2e3b44;
            color: #e2e8f0;
            border: 1px solid #4a5b66;
            border-radius: 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-top: 0.6rem;
        }
        .btn:hover {
            background: #3a4a55;
            transform: scale(0.98);
            border-color: #7f9bb5;
        }
        .success-banner {
            background: #1a2a22;
            border: 1px solid #3a6b55;
            border-radius: 1.2rem;
            padding: 0.9rem;
            color: #8fc9b0;
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .credentials-banner {
            background: #10161c;
            border: 1px solid #2e3a42;
            border-radius: 1.2rem;
            padding: 1.2rem;
            margin-top: 1.5rem;
        }
        .credentials-banner h3 {
            font-size: 1rem;
            color: #bdd4e8;
            margin-bottom: 0.5rem;
        }
        .credentials-banner p {
            font-size: 0.8rem;
            color: #7f9bb5;
            margin-bottom: 0.8rem;
        }
        .cred-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .cred-label {
            color: #7f9bb5;
            font-size: 0.8rem;
            width: 60px;
        }
        .cred-row strong {
            font-family: monospace;
            font-size: 0.9rem;
            background: #1e242c;
            padding: 0.2rem 0.6rem;
            border-radius: 0.8rem;
            letter-spacing: 0.5px;
            color: #bdd4e8;
            border: 1px solid #2e3a42;
        }
        .btn-login {
            display: inline-block;
            margin-top: 0.8rem;
            padding: 0.4rem 1.2rem;
            background: #2e3b44;
            color: #e2e8f0;
            border: 1px solid #4a5b66;
            border-radius: 1.5rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background: #3a4a55;
            transform: scale(0.96);
            border-color: #7f9bb5;
        }
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 600px) {
            body { padding: 1rem; }
            .card { padding: 1.6rem; }
            h1 { font-size: 1.6rem; }
            .radio-group { flex-direction: column; align-items: flex-start; gap: 0.6rem; }
        }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #191f26; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #556e82; border-radius: 10px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Анкета</h1>

    <?php if ($success): ?>
        <div class="success-banner">Анкета успешно сохранена</div>
    <?php endif; ?>

    <form action="form.php" method="POST">
        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">

        <div class="field <?= isset($errors['name']) ? 'field-error' : '' ?>">
            <label>ФИО</label>
            <input type="text" name="name" value="<?= htmlspecialchars($values['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($values['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['email']) ? 'field-error' : '' ?>">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($values['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['birthdate']) ? 'field-error' : '' ?>">
            <label>Дата рождения</label>
            <input type="date" name="birthdate" value="<?= htmlspecialchars($values['birthdate'] ?? '') ?>">
            <?php if (isset($errors['birthdate'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['birthdate']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['gender']) ? 'field-error' : '' ?>">
            <label>Пол</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= ($values['gender'] ?? '') === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= ($values['gender'] ?? '') === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['languages']) ? 'field-error' : '' ?>">
            <label>Любимый язык программирования</label>
            <select name="languages[]" multiple>
                <?php foreach ($ALL_LANGUAGES as $lang): ?>
                <option value="<?= htmlspecialchars($lang['id']) ?>" <?= $isSelectedLang($lang['id']) ? 'selected' : '' ?>><?= htmlspecialchars($lang['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['bio']) ? 'field-error' : '' ?>">
            <label>Биография</label>
            <textarea name="bio"><?= htmlspecialchars($values['bio'] ?? '') ?></textarea>
            <?php if (isset($errors['bio'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['bio']) ?></div>
            <?php endif; ?>
        </div>

        <div class="field <?= isset($errors['contract']) ? 'field-error' : '' ?>">
            <label class="checkbox-label">
                <input type="checkbox" name="contract" <?= ($values['contract'] ?? false) ? 'checked' : '' ?>> С контрактом ознакомлен(а)
            </label>
            <?php if (isset($errors['contract'])): ?>
                <div class="error-msg"><?= htmlspecialchars($errors['contract']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn">Сохранить</button>

        <?php if ($newCreds): ?>
        <div class="credentials-banner">
            <h3>Анкета отправлена</h3>
            <p>Сохраните данные для входа — они показываются только один раз:</p>
            <div class="cred-row"><span class="cred-label">Логин:</span><strong><?= htmlspecialchars($newCreds['login']) ?></strong></div>
            <div class="cred-row"><span class="cred-label">Пароль:</span><strong><?= htmlspecialchars($newCreds['password']) ?></strong></div>
            <a href="login.php" class="btn-login">Войти</a>
        </div>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
<?php
}
?>