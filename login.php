<?php
require_once "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter username and password.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name, username, password
                 FROM users
                 WHERE username = :username"
            );

            $stmt->execute([
                "username" => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user["password"])) {

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["name"] = $user["name"];

                header("Location: index.php");
                exit();

            } else {
                $error = "Invalid username or password.";
            }

        } catch (PDOException $e) {
            $error = "Login failed. Please check the database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Smart Recipe Hub</title>

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
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f0f5ef;
        }

        .login-box {
            background: white;
            width: 100%;
            max-width: 380px;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #236b52;
            margin-bottom: 10px;
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
            margin-bottom: 18px;
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
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #194d3b;
        }

        .error {
            color: #a00000;
            background: #ffeaea;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 15px;
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

    <div class="login-box">

        <h2>🥭 Smart Recipe Hub</h2>

        <p class="subtitle">Welcome back! Please login.</p>

        <?php if ($error !== ""): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <label for="username">Username</label>

            <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter your username"
                required
                autocomplete="username"
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                required
                autocomplete="current-password"
            >

            <button type="submit">Login</button>

        </form>

        <p class="bottom-text">
            Don't have an account?
            <a href="register.php">Create Account</a>
        </p>

    </div>

</body>
</html>