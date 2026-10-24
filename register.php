<?php
require_once "db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $name === "" ||
        $username === "" ||
        $email === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (strlen($username) < 3) {
        $error = "Username must contain at least 3 characters.";
    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $check = $pdo->prepare(
                "SELECT id FROM users
                 WHERE username = :username OR email = :email"
            );

            $check->execute([
                "username" => $username,
                "email" => $email
            ]);

            if ($check->fetch()) {
                $error = "Username or email already exists.";
            } else {
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    "INSERT INTO users
                    (name, username, email, password)
                    VALUES (:name, :username, :email, :password)"
                );

                $stmt->execute([
                    "name" => $name,
                    "username" => $username,
                    "email" => $email,
                    "password" => $hashed_password
                ]);

                $success = "Account created successfully! You can now login.";
            }
        } catch (PDOException $e) {
            $error = "Unable to create account. Please check your database setup.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account - Smart Recipe Hub</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f0f5ef;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .register-box {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #236b52;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #ccc;
            border-radius: 7px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #236b52;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #194d3b;
        }

        .error, .success {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
        }

        .error {
            color: #a00000;
            background: #ffeaea;
        }

        .success {
            color: #176b35;
            background: #e8f8ed;
        }

        .bottom-text {
            text-align: center;
            margin-top: 20px;
        }

        a {
            color: #236b52;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="register-box">

    <h2>🥭 Smart Recipe Hub</h2>
    <p class="subtitle">Create your account</p>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="success">
            <?= htmlspecialchars($success) ?>
            <br><br>
            <a href="login.php">Go to Login</a>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <label for="name">Full Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="100"
            required
        >

        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            minlength="3"
            maxlength="50"
            required
        >

        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >

        <label for="confirm_password">Confirm Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="8"
            required
        >

        <button type="submit">Create Account</button>

    </form>

    <p class="bottom-text">
        Already have an account?
        <a href="login.php">Login</a>
    </p>

</div>

</body>
</html>