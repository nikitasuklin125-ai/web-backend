<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/db.php';

if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        return preg_match_all('/./us', $str, $matches);
    }
}

$formData = [
    'name' => '', 'phone' => '', 'email' => '', 'birthdate' => '',
    'gender' => '', 'bio' => '', 'languages' => [], 'contract' => false
];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'birthdate' => trim($_POST['birthdate'] ?? ''),
        'gender' => trim($_POST['gender'] ?? ''),
        'bio' => trim($_POST['bio'] ?? ''),
        'languages' => $_POST['languages'] ?? [],
        'contract' => isset($_POST['contract'])
    ];

    // Name
    $name = $formData['name'];
    if ($name === '') {
        $errors['name'] = 'Name is required';
    } else {
        $len = mb_strlen($name, 'UTF-8');
        if ($len > 150) {
            $errors['name'] = 'Name must be at most 150 characters';
        } elseif (!preg_match('/^[\p{L} ]+$/u', $name)) {
            $errors['name'] = 'Name contains invalid characters';
        }
    }

    // Phone
    $phone = $formData['phone'];
    if ($phone === '') {
        $errors['phone'] = 'Phone is required';
    } elseif (!preg_match('/^\+?[0-9()\- ]{7,32}$/', $phone)) {
        $errors['phone'] = 'Phone format is invalid';
    }

    // Email
    $email = $formData['email'];
    if ($email === '') {
        $errors['email'] = 'Email is required';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors['email'] = 'Email format is invalid';
    }

    // Birthdate
    $birthdate = $formData['birthdate'];
    if ($birthdate === '') {
        $errors['birthdate'] = 'Birthdate is required';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$date || $date->format('Y-m-d') !== $birthdate) {
            $errors['birthdate'] = 'Birthdate format is invalid (expected YYYY-MM-DD)';
        }
    }

    // Gender
    if (!in_array($formData['gender'], ['male', 'female'], true)) {
        $errors['gender'] = "Gender must be 'male' or 'female'";
    }

    // Languages
    $languages = $formData['languages'];
    $validLangIds = array_map('strval', range(1, 12));
    if (empty($languages)) {
        $errors['languages'] = 'At least one language must be selected';
    } else {
        foreach ($languages as $lang) {
            if (!in_array((string)$lang, $validLangIds, true)) {
                $errors['languages'] = 'Invalid language selection';
                break;
            }
        }
    }

    // Bio
    if ($formData['bio'] === '') {
        $errors['bio'] = 'Bio is required';
    }

    // Contract
    if (!$formData['contract']) {
        $errors['contract'] = 'You must agree to the contract';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, 1)
            ");
            $stmt->execute([
                ':full_name' => $formData['name'],
                ':phone' => $formData['phone'],
                ':email' => $formData['email'],
                ':birth_date' => $formData['birthdate'],
                ':gender' => $formData['gender'],
                ':biography' => $formData['bio']
            ]);
            $appId = $pdo->lastInsertId();

            $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:app_id, :lang_id)");
            foreach ($formData['languages'] as $langId) {
                $langStmt->execute([':app_id' => $appId, ':lang_id' => $langId]);
            }

            $pdo->commit();
            $success = true;
            $formData = [
                'name' => '', 'phone' => '', 'email' => '', 'birthdate' => '',
                'gender' => '', 'bio' => '', 'languages' => [], 'contract' => false
            ];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <form action="form.php" method="POST">
        <h1>Анкета</h1>

        <?php if ($success): ?>
            <div class="message-success">
                ✓ Application submitted successfully!
            </div>
        <?php elseif (!empty($errors) && isset($errors['general'])): ?>
            <div class="message-error">
                <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php elseif (!empty($errors)): ?>
            <div class="message-error">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($errors as $field => $errMsg): ?>
                        <?php if ($field !== 'general'): ?>
                            <li><?= htmlspecialchars($errMsg) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <p>
            <label>ФИО</label>
            <input type="text" name="name" value="<?= htmlspecialchars($formData['name']) ?>">
        </p>
        <p>
            <label>Телефон</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>">
        </p>
        <p>
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>">
        </p>
        <p>
            <label>Дата рождения</label>
            <input type="date" name="birthdate" value="<?= htmlspecialchars($formData['birthdate']) ?>">
        </p>
        <p>
            <label>Пол</label>
            <label><input type="radio" name="gender" value="male" <?= $formData['gender'] === 'male' ? 'checked' : '' ?>> Мужской</label><br>
            <label><input type="radio" name="gender" value="female" <?= $formData['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
        </p>
        <p>
            <label>Любимый язык программирования</label>
            <select name="languages[]" multiple size="5">
                <option value="1" <?= in_array('1', $formData['languages']) ? 'selected' : '' ?>>Pascal</option>
                <option value="2" <?= in_array('2', $formData['languages']) ? 'selected' : '' ?>>C</option>
                <option value="3" <?= in_array('3', $formData['languages']) ? 'selected' : '' ?>>C++</option>
                <option value="4" <?= in_array('4', $formData['languages']) ? 'selected' : '' ?>>JavaScript</option>
                <option value="5" <?= in_array('5', $formData['languages']) ? 'selected' : '' ?>>PHP</option>
                <option value="6" <?= in_array('6', $formData['languages']) ? 'selected' : '' ?>>Python</option>
                <option value="7" <?= in_array('7', $formData['languages']) ? 'selected' : '' ?>>Java</option>
                <option value="8" <?= in_array('8', $formData['languages']) ? 'selected' : '' ?>>Haskell</option>
                <option value="9" <?= in_array('9', $formData['languages']) ? 'selected' : '' ?>>Clojure</option>
                <option value="10" <?= in_array('10', $formData['languages']) ? 'selected' : '' ?>>Prolog</option>
                <option value="11" <?= in_array('11', $formData['languages']) ? 'selected' : '' ?>>Scala</option>
                <option value="12" <?= in_array('12', $formData['languages']) ? 'selected' : '' ?>>Go</option>
            </select>
        </p>
        <p>
            <label>Биография</label>
            <textarea name="bio"><?= htmlspecialchars($formData['bio']) ?></textarea>
        </p>
        <p>
            <label>
                <input type="checkbox" name="contract" <?= $formData['contract'] ? 'checked' : '' ?>>
                С контрактом ознакомлен
            </label>
        </p>
        <p>
            <button type="submit">Сохранить</button>
        </p>
    </form>
</body>
</html>