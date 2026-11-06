<?php
session_start();
ini_set('display_errors', '0');

$db = new PDO(
    'mysql:host=localhost;dbname=notes_lab;charset=utf8mb4',
    getenv('LAB_DB_USER') ?: 'root',
    getenv('LAB_DB_PASSWORD') ?: ''
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$action = $_POST['action'] ?? '';


if ($action === 'register') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (!$email || strlen($password) < 6) {
        $message = 'Invalid email or password too short.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'student';
        
        try {
            $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$email, $hash, $role]);
            header('Location: /?message=Account created successfully');
            exit;
        } catch (PDOException $e) {
            $message = 'Registration failed. Email may already be in use.';
        }
    }
}


if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: /');
        exit;
    }
    $message = 'Invalid credentials.';
}


$loggedInUserId = $_SESSION['user_id'] ?? null;


if ($action === 'save' && $loggedInUserId) {
    $title = $_POST['title'] ?? '';
    $body = $_POST['body'] ?? '';
    
    $stmt = $db->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
    $stmt->execute([$loggedInUserId, $title, $body]);
    header('Location: /');
    exit;
}


if ($action === 'update' && $loggedInUserId) {
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $body = $_POST['body'] ?? '';

    $stmt = $db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $body, $id, $loggedInUserId]);
    header('Location: /');
    exit;
}


if (isset($_GET['delete']) && $loggedInUserId) {
    $stmt = $db->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$_GET['delete'], $loggedInUserId]);
    header('Location: /');
    exit;
}


if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: /');
    exit;
}

$message = $message ?? ($_GET['message'] ?? '');
$notes = [];
$editing = false;


if ($loggedInUserId) {
    $stmt = $db->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$loggedInUserId]);
    $notes = $stmt->fetchAll();

    if (isset($_GET['edit'])) {
        $stmt = $db->prepare('SELECT id, title, body FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([$_GET['edit'], $loggedInUserId]);
        $editing = $stmt->fetch();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Campus Notes</title>
    <style>body{font:16px sans-serif;max-width:750px;margin:2rem auto}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}</style>
</head>
<body>
<h1>Campus Notes</h1>
<p><?= htmlspecialchars($message) ?></p>

<?php if (!$loggedInUserId): ?>
<h2>Register or sign in</h2>
<form method="post">
  <label>Email <input name="email" type="email" required></label>
  <label>Password <input name="password" type="password" minlength="6" required></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>

<?php else: ?>
<p>User ID: <?= htmlspecialchars($_SESSION['user_id']) ?> | Role: <?= htmlspecialchars($_SESSION['role']) ?> | <a href="?logout=1">Log out</a></p>

<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post">
  <input type="hidden" name="id" value="<?= htmlspecialchars($editing['id']) ?>">
  <label>Title <input name="title" value="<?= htmlspecialchars($editing['title']) ?>" required></label>
  <label>Body <textarea name="body" required><?= htmlspecialchars($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>

<h2>Create a note</h2>
<form method="post">
  <label>Title <input name="title" required></label>
  <label>Body <textarea name="body" required></textarea></label>
  <button name="action" value="save">Save</button>
</form>

<h2>My Notes</h2>
<?php foreach ($notes as $note): ?>
<article>
    <h3><?= htmlspecialchars($note['title']) ?></h3>
    <p><?= htmlspecialchars($note['body']) ?></p>
    <small>Owner: <?= htmlspecialchars($note['user_id']) ?></small>
    <a href="?edit=<?= htmlspecialchars($note['id']) ?>">Edit</a>
    <a href="?delete=<?= htmlspecialchars($note['id']) ?>">Delete</a>
</article>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>