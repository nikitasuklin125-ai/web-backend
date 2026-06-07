<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/csrf.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (getJWTFromCookie() !== null) {
        header('Location: edit.php');
        exit;
    }
    renderLogin('', getOrCreateCSRFToken());
    exit;
}

if ($method === 'POST') {
    if (!validateCSRFToken()) {
        http_response_code(403);
        echo 'Request denied';
        exit;
    }

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        renderLogin('Login and password cannot be empty', getOrCreateCSRFToken(true));
        exit;
    }

    $creds = findCredentialsByLogin($pdo, $login);
    if (!$creds || !checkPassword($password, $creds['password_hash'])) {
        renderLogin('Invalid login or password', getOrCreateCSRFToken(true));
        exit;
    }

    $token = generateJWT($creds['application_id'], $login);
    setJWTCookie($token);
    header('Location: edit.php');
    exit;
}

http_response_code(405);
echo 'Method not allowed';

function renderLogin($error, $csrfToken) {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
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
            padding: 2.2rem 2rem;
            width: 100%;
            max-width: 420px;
            transition: all 0.25s ease;
            border: 1px solid #2e3a42;
            animation: fadeSlideUp 0.45s ease-out;
        }
        .card:hover {
            border-color: #4a5b66;
            box-shadow: 0 24px 42px -14px rgba(0, 0, 0, 0.6);
        }
        h1 {
            font-size: 1.8rem;
            font-weight: 550;
            letter-spacing: -0.3px;
            color: #e2e8f0;
            text-align: center;
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
        input {
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
        input:focus {
            border-color: #7f9bb5;
            box-shadow: 0 0 0 2px rgba(127, 155, 181, 0.2);
            background: #0f151c;
        }
        .error-banner {
            background: #1f1820;
            border: 1px solid #5e4a42;
            border-radius: 1rem;
            padding: 0.7rem;
            color: #e0a98b;
            text-align: center;
            margin-bottom: 1.5rem;
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
            margin-top: 0.5rem;
        }
        .btn:hover {
            background: #3a4a55;
            transform: scale(0.98);
            border-color: #7f9bb5;
        }
        .links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
        }
        .links a {
            color: #7f9bb5;
            text-decoration: none;
        }
        .links a:hover {
            color: #bdd4e8;
            text-decoration: underline;
        }
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 600px) {
            body { padding: 1rem; }
            .card { padding: 1.6rem; }
            h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Вход в систему</h1>

    <?php if ($error): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
        <div class="field">
            <label>Логин</label>
            <input type="text" name="login" autocomplete="username">
        </div>
        <div class="field">
            <label>Пароль</label>
            <input type="password" name="password" autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Войти</button>
    </form>

    <div class="links">
        <a href="form.php">← Заполнить новую анкету</a>
    </div>
</div>
</body>
</html>
<?php
}
?>