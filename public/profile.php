<?php
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];
$message = "";
$error = "";

$stmt = $pdo->prepare("SELECT id, name, username, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute(["id" => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($name === "" || $email === "") {
        $error = "Please enter your full name and email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $update = $pdo->prepare(
                "UPDATE users SET name = :name, email = :email WHERE id = :id"
            );
            $update->execute([
                "name" => $name,
                "email" => $email,
                "id" => $userId
            ]);

            $_SESSION["name"] = $name;

            $stmt = $pdo->prepare("SELECT id, name, username, email FROM users WHERE id = :id LIMIT 1");
            $stmt->execute(["id" => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $message = "Your profile has been updated successfully.";
        } catch (PDOException $e) {
            $error = "Unable to save changes. The email may already be in use.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | Smart Recipe Hub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --green: #174d37;
    --green-light: #287653;
    --cream: #fbf8f1;
    --dark: #202b23;
    --muted: #718075;
    --border: #e4e9df;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
    margin: 0;
    font-family: 'DM Sans', sans-serif;
    color: var(--dark);
    background:
        radial-gradient(circle at 8% 12%, #e7efdf 0, transparent 27%),
        radial-gradient(circle at 95% 80%, #f4ead6 0, transparent 25%),
        var(--cream);
    min-height: 100vh;
}
a { text-decoration: none; }
.navbar {
    position: sticky;
    top: 0;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 16px 6%;
    background: rgba(255,255,255,.94);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 5px 25px rgba(23,77,55,.04);
}
.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--green);
    font-size: 1.18rem;
    font-weight: 800;
    white-space: nowrap;
}
.brand-icon { font-size: 1.7rem; }
.nav-links {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}
.nav-links a {
    color: #435047;
    font-size: .9rem;
    font-weight: 700;
    transition: color .2s, transform .2s;
}
.nav-links a:hover { color: var(--green-light); }
.nav-links .profile-nav {
    width: 39px;
    height: 39px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #eaf2e7;
    border: 1px solid #dce8d8;
    font-size: 1.15rem;
}
.nav-links .logout {
    background: var(--green);
    color: #fff;
    padding: 10px 15px;
    border-radius: 10px;
}
.page-wrap {
    width: min(1100px, 92%);
    margin: 54px auto 75px;
}
.page-heading { text-align: center; margin-bottom: 30px; }
.eyebrow {
    display: inline-block;
    color: var(--green-light);
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: 2px;
    text-transform: uppercase;
}
.page-heading h1 {
    margin: 12px 0;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(2.2rem, 5vw, 3.4rem);
    line-height: 1.1;
}
.page-heading p {
    margin: 0 auto;
    max-width: 560px;
    color: var(--muted);
    line-height: 1.8;
}
.profile-layout {
    display: grid;
    grid-template-columns: 310px minmax(0, 1fr);
    gap: 24px;
    align-items: stretch;
}
.profile-summary, .profile-form-card {
    background: rgba(255,255,255,.92);
    border: 1px solid var(--border);
    border-radius: 24px;
    box-shadow: 0 18px 50px rgba(28,55,35,.07);
}
.profile-summary {
    padding: 34px 26px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.avatar {
    width: 104px;
    height: 104px;
    display: grid;
    place-items: center;
    border-radius: 32px;
    background: linear-gradient(145deg, #eaf3e5, #d9e8d1);
    border: 1px solid #d8e6d2;
    font-size: 3rem;
    box-shadow: 0 12px 25px rgba(23,77,55,.08);
    margin-bottom: 19px;
}
.profile-summary h2 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 1.55rem;
    margin: 0 0 7px;
    overflow-wrap: anywhere;
}
.username {
    color: var(--green-light);
    font-weight: 700;
    font-size: .92rem;
    overflow-wrap: anywhere;
}
.summary-divider { width: 100%; height: 1px; background: var(--border); margin: 25px 0; }
.summary-detail {
    width: 100%;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    text-align: left;
    padding: 12px 0;
    border-bottom: 1px solid #eef0e9;
    font-size: .86rem;
}
.summary-detail span { color: var(--muted); }
.summary-detail strong { text-align: right; overflow-wrap: anywhere; }
.summary-tip {
    margin-top: auto;
    padding-top: 25px;
    color: var(--muted);
    font-size: .83rem;
    line-height: 1.7;
}
.profile-form-card { padding: 34px; }
.form-heading {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 25px;
}
.form-icon {
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: #edf4e9;
    font-size: 1.4rem;
}
.form-heading h2 {
    margin: 1px 0 7px;
    font-size: 1.35rem;
}
.form-heading p { margin: 0; color: var(--muted); font-size: .9rem; line-height: 1.7; }
.alert {
    padding: 13px 15px;
    margin-bottom: 20px;
    border-radius: 12px;
    font-size: .9rem;
    line-height: 1.6;
}
.alert.success { background: #eaf6eb; color: #23653b; border: 1px solid #cfe8d1; }
.alert.error { background: #fff0ed; color: #a03b2f; border: 1px solid #f3d0c9; }
.form-group { margin-bottom: 19px; }
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: .9rem;
    font-weight: 800;
}
.input-wrap { position: relative; }
.input-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.05rem;
    pointer-events: none;
}
.form-group input {
    width: 100%;
    min-height: 51px;
    padding: 13px 15px 13px 45px;
    border: 1px solid #dce3d8;
    border-radius: 12px;
    outline: none;
    background: #fcfdfb;
    color: var(--dark);
    font: inherit;
    transition: border-color .2s, box-shadow .2s, background .2s;
}
.form-group input:focus {
    border-color: var(--green-light);
    background: white;
    box-shadow: 0 0 0 4px rgba(40,118,83,.1);
}
.form-group input[readonly] {
    background: #f2f4ef;
    color: #6f7a70;
    cursor: not-allowed;
}
.field-note { margin-top: 7px; color: var(--muted); font-size: .78rem; }
.form-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 25px; }
.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    min-height: 49px;
    padding: 13px 20px;
    border: 1px solid var(--green);
    border-radius: 12px;
    background: var(--green);
    color: white;
    font: inherit;
    font-weight: 800;
    cursor: pointer;
    transition: transform .2s, background .2s, box-shadow .2s;
}
.btn:hover { transform: translateY(-2px); background: var(--green-light); box-shadow: 0 9px 20px rgba(23,77,55,.15); }
.btn-secondary { background: white; color: var(--green); border-color: #d3dfd0; }
.btn-secondary:hover { color: white; }
footer {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    padding: 24px 6%;
    background: #123728;
    color: #e7eee8;
    font-size: .85rem;
}
footer strong { color: white; }
@media (max-width: 780px) {
    .profile-layout { grid-template-columns: 1fr; }
    .profile-summary { padding: 28px 22px; }
    .profile-summary .summary-tip { margin-top: 0; }
}
@media (max-width: 600px) {
    .navbar { align-items: flex-start; flex-direction: column; padding: 15px 5%; }
    .nav-links { gap: 13px; }
    .nav-links a { font-size: .82rem; }
    .page-wrap { margin-top: 35px; }
    .profile-form-card { padding: 23px 18px; }
    .form-actions { flex-direction: column; }
    .form-actions .btn { width: 100%; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
</style>
</head>
<body>
<header class="navbar">
    <a class="brand" href="index.php"><span class="brand-icon">🥭</span><span>Smart Recipe Hub</span></a>
    <nav class="nav-links">
        <a href="index.php">🏠 Home</a>
        <a href="calculator.php">🧮 Ingredients</a>
        <a href="recipes.php">📖 Recipes</a>
        <a href="admin.php">🔐 Admin</a>
        <a href="profile.php" class="profile-nav" title="My Profile" aria-label="My Profile">👤</a>
        <a href="logout.php" class="logout">Logout ↗</a>
    </nav>
</header>

<main class="page-wrap">
    <div class="page-heading">
        <span class="eyebrow">Your account · Your details</span>
        <h1>My Profile</h1>
        <p>Manage your Smart Recipe Hub account details in a clean, comfortable workspace.</p>
    </div>

    <div class="profile-layout">
        <aside class="profile-summary">
            <div class="avatar">👤</div>
            <h2><?= htmlspecialchars($user["name"], ENT_QUOTES, "UTF-8") ?></h2>
            <div class="username">@<?= htmlspecialchars($user["username"], ENT_QUOTES, "UTF-8") ?></div>

            <div class="summary-divider"></div>

            <div class="summary-detail">
                <span>Account ID</span>
                <strong>#<?= (int) $user["id"] ?></strong>
            </div>
            <div class="summary-detail">
                <span>Email</span>
                <strong><?= htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8") ?></strong>
            </div>

            <p class="summary-tip">🌿 Keep your account information accurate so your kitchen workspace stays up to date.</p>
        </aside>

        <section class="profile-form-card">
            <div class="form-heading">
                <div class="form-icon">✍️</div>
                <div>
                    <h2>Edit your details</h2>
                    <p>Update your full name or email address below. Your username stays unchanged.</p>
                </div>
            </div>

            <?php if ($message !== ""): ?>
                <div class="alert success"><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
            <?php endif; ?>

            <form method="POST" action="profile.php">
                <div class="form-group">
                    <label for="name">Full name</label>
                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input id="name" name="name" type="text" maxlength="100" required value="<?= htmlspecialchars($user["name"], ENT_QUOTES, "UTF-8") ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔖</span>
                        <input id="username" type="text" readonly value="<?= htmlspecialchars($user["username"], ENT_QUOTES, "UTF-8") ?>">
                    </div>
                    <div class="field-note">Your username cannot be changed here.</div>
                </div>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <div class="input-wrap">
                        <span class="input-icon">✉️</span>
                        <input id="email" name="email" type="email" maxlength="254" required value="<?= htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8") ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">💾 Save Changes</button>
                    <a href="index.php" class="btn btn-secondary">← Back to Home</a>
                </div>
            </form>
        </section>
    </div>
</main>

<footer>
    <span>🥭 <strong>Smart Recipe Hub</strong></span>
    <span>Profile settings · Ingredient planning made simple</span>
</footer>
</body>
</html>
