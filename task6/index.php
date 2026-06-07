<?php
session_start();
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/config.php';

$payload = getJWTFromCookie();
$isLoggedIn = ($payload !== null);
$login = $isLoggedIn ? htmlspecialchars($payload['login']) : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета — Главная</title>
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
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 480px;
            text-align: center;
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
            margin-bottom: 0.5rem;
        }
        .subtitle {
            font-size: 0.9rem;
            color: #7f9bb5;
            margin-bottom: 2rem;
        }
        .user-greeting {
            background: #10161c;
            border: 1px solid #2e3a42;
            border-radius: 1.2rem;
            padding: 0.7rem;
            color: #bdd4e8;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 0.8rem;
            border-radius: 1.5rem;
            font-size: 0.95rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 0.8rem;
        }
        .btn-primary {
            background: #2e3b44;
            color: #e2e8f0;
            border: 1px solid #4a5b66;
        }
        .btn-primary:hover {
            background: #3a4a55;
            transform: scale(0.98);
            border-color: #7f9bb5;
        }
        .btn-secondary {
            background: #10161c;
            color: #7f9bb5;
            border: 1px solid #2e3a42;
        }
        .btn-secondary:hover {
            background: #1a212a;
            border-color: #5e7687;
        }
        .btn-danger {
            background: #1f1820;
            color: #e0a98b;
            border: 1px solid #5e4a42;
        }
        .btn-danger:hover {
            background: #2a1e1c;
            border-color: #b87a6c;
        }
        .btn-admin {
            background: #10161c;
            color: #7f9bb5;
            border: 1px solid #2e3a42;
            font-size: 0.85rem;
        }
        .divider {
            height: 1px;
            background: #2e3b44;
            margin: 0.5rem 0 1.2rem;
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
    <h1>Система анкетирования</h1>
    <p class="subtitle">Заполните анкету или войдите, чтобы изменить данные</p>

    <?php if ($isLoggedIn): ?>
        <div class="user-greeting">Вы вошли как <strong><?= $login ?></strong></div>
        <a href="edit.php" class="btn btn-primary">Редактировать анкету</a>
        <div class="divider"></div>
        <a href="logout.php" class="btn btn-danger">Выйти</a>
    <?php else: ?>
        <a href="form.php" class="btn btn-primary">Заполнить анкету</a>
        <a href="login.php" class="btn btn-secondary">Войти</a>
        <div class="divider"></div>
        <a href="admin.php" class="btn btn-admin">Войти как администратор</a>
    <?php endif; ?>
</div>
</body>
</html>