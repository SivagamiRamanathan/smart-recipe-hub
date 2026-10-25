<?php
declare(strict_types=1);
require_once "db.php";

// Redirect to login if the user is not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$userName = $_SESSION["name"] ?? $_SESSION["username"] ?? "User";
$homeRecipes = [];

try {
    $homeRecipes = $pdo->query(
        "SELECT id, recipe_name, image_url
         FROM recipes
         ORDER BY id DESC
         LIMIT 12"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Home recipe cards: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Smart Recipe Hub | Home</title>

    <link rel="stylesheet" href="style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --green: #174d37;
            --green-light: #287653;
            --cream: #fbf8f1;
            --orange: #f2a444;
            --dark: #202b23;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--cream);
            color: var(--dark);
            font-family: 'DM Sans', sans-serif;
        }

        a {
            text-decoration: none;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
            padding: 17px 6%;
            background: rgba(255,255,255,.96);
            border-bottom: 1px solid #e9e8df;
            box-shadow: 0 5px 25px rgba(0,0,0,.035);
            backdrop-filter: blur(14px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--green);
            font-weight: 800;
            font-size: 1.25rem;
            white-space: nowrap;
        }

        .brand-icon {
            font-size: 1.8rem;
        }

        .main-nav {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .main-nav a {
            color: #39463d;
            font-size: .9rem;
            font-weight: 600;
            transition: color .25s, transform .25s;
        }

        .main-nav a:hover {
            color: var(--green-light);
            transform: translateY(-2px);
        }

        .profile-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #eaf1e8;
            font-size: 1.25rem !important;
        }

        .logout-link {
            padding: 10px 16px;
            color: white !important;
            background: var(--green);
            border-radius: 10px;
        }

        .logout-link:hover {
            background: var(--green-light);
        }

        .main-nav .admin-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 17px;
            border-radius: 10px;
            background: var(--orange);
            color: #203529;
            font-size: .9rem;
            font-weight: 800;
            transition: background .25s, transform .25s, box-shadow .25s;
        }

        .main-nav .admin-link:hover {
            background: #e5b45e;
            color: #173d2c;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(242,164,68,.25);
        }

        .hero-section {
            max-width: 1450px;
            margin: auto;
            min-height: 590px;
            padding: 65px 6%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 55px;
            overflow: hidden;
        }

        .hero-text {
            animation: slideUp .9s ease both;
        }

        .eyebrow {
            display: inline-block;
            color: var(--green-light);
            font-size: .75rem;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .hero-text h1 {
            margin: 20px 0;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(2.8rem, 5vw, 5rem);
            line-height: 1.08;
            letter-spacing: -1.8px;
        }

        .hero-text h1 span {
            color: var(--green-light);
        }

        .hero-text > p {
            max-width: 530px;
            color: #69756b;
            font-size: 1.02rem;
            line-height: 1.9;
        }

        .welcome-message {
            margin: 18px 0;
            color: #69756b;
            font-size: .9rem;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
            margin-top: 28px;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 48px;
            padding: 13px 21px;
            border: none;
            border-radius: 11px;
            background: var(--green);
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: transform .25s, box-shadow .25s, background .25s;
        }

        .button:hover {
            transform: translateY(-3px);
            background: var(--green-light);
            box-shadow: 0 10px 22px rgba(23,77,55,.18);
        }

        .button-outline {
            background: transparent;
            border: 1px solid #cdd8cc;
            color: var(--green);
        }

        .button-outline:hover {
            color: white;
        }

        .hero-image-wrap {
            position: relative;
            animation: fadeIn 1.2s ease both;
        }

        .hero-image {
            display: block;
            width: 100%;
            height: 430px;
            object-fit: cover;
            border-radius: 28px;
            box-shadow: 0 24px 65px rgba(34,49,36,.16);
            transition: transform .6s;
        }

        .hero-image-wrap:hover .hero-image {
            transform: scale(1.025);
        }

        .image-badge {
            position: absolute;
            left: -25px;
            bottom: 28px;
            display: flex;
            align-items: center;
            gap: 13px;
            max-width: 270px;
            padding: 17px 21px;
            background: white;
            border-radius: 17px;
            box-shadow: 0 12px 35px rgba(0,0,0,.1);
            animation: float 3s ease-in-out infinite;
        }

        .image-badge-icon {
            display: grid;
            place-items: center;
            width: 45px;
            height: 45px;
            background: #edf5e9;
            border-radius: 13px;
            font-size: 1.4rem;
        }

        .image-badge strong {
            display: block;
            font-size: .92rem;
        }

        .image-badge small {
            display: block;
            margin-top: 5px;
            color: #788078;
        }

        .highlights {
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 18px;
            margin-top: 38px;
            max-width: 530px;
        }

        .highlight {
            padding-right: 10px;
            border-right: 1px solid #dce1d7;
        }

        .highlight:last-child {
            border: none;
        }

        .highlight strong {
            display: block;
            color: var(--green);
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.7rem;
        }

        .highlight span {
            color: #6e786e;
            font-size: .8rem;
        }

        .content-section {
            padding: 70px 6%;
        }

        .section-title {
            max-width: 700px;
            margin: 0 auto 38px;
            text-align: center;
        }

        .section-title h2 {
            margin: 12px 0;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(2rem, 3.5vw, 3rem);
        }

        .section-title p {
            color: #738075;
            line-height: 1.8;
        }

        .services-section {
            background: #fff;
        }

        .saved-recipes-section {
            padding-top: 58px;
            background: #f0f4e9;
        }

        .food-video-section {
            background: #fff;
            padding-top: 58px;
        }

        .food-video-heading {
            margin-bottom: 28px;
        }

        .food-video-button {
            margin-top: 22px;
            background: #f2a444;
            color: #203529;
        }

        .food-video-button:hover {
            background: #ffd18a;
            color: #203529;
        }

        .food-motion-cover {
            position: relative;
            max-width: 1250px;
            min-height: 285px;
            margin: 0 auto 42px;
            overflow: hidden;
            isolation: isolate;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 44px 24px;
            color: #fff;
            background: #163d2b;
            box-shadow: 0 20px 50px rgba(18,55,36,.18);
        }

        .food-motion-cover::before {
            content: "";
            position: absolute;
            z-index: -2;
            inset: -8%;
            background-image:
                linear-gradient(90deg,rgba(9,35,23,.72),rgba(9,35,23,.28),rgba(9,35,23,.62)),
                url('https://images.unsplash.com/photo-1498837167922-ddd27525d352?auto=format&fit=crop&w=1800&q=90');
            background-size: cover;
            background-position: center;
            animation: foodVideoPan 18s ease-in-out infinite alternate;
        }

        .food-motion-cover::after {
            content: "";
            position: absolute;
            z-index: -1;
            inset: 0;
            background: linear-gradient(120deg,transparent 20%,rgba(255,255,255,.12) 48%,transparent 70%);
            transform: translateX(-100%);
            animation: foodShimmer 7s ease-in-out infinite;
        }

        .food-motion-content {
            max-width: 760px;
            animation: foodContentFloat 5s ease-in-out infinite;
        }

        .food-motion-cover .eyebrow {
            color: #ffe1a3;
        }

        .food-motion-cover h2 {
            margin: 14px 0 10px;
            color: #fff;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.12;
            text-shadow: 0 3px 18px rgba(0,0,0,.25);
        }

        .food-motion-cover p {
            max-width: 650px;
            margin: 0 auto;
            color: rgba(255,255,255,.92);
            line-height: 1.8;
        }

        .food-motion-pills {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .food-motion-pills span {
            padding: 9px 14px;
            border: 1px solid rgba(255,255,255,.4);
            border-radius: 999px;
            background: rgba(255,255,255,.13);
            backdrop-filter: blur(7px);
            font-size: .86rem;
            animation: pillBob 3.5s ease-in-out infinite;
        }

        .food-motion-pills span:nth-child(2) {
            animation-delay: .4s;
        }

        .food-motion-pills span:nth-child(3) {
            animation-delay: .8s;
        }

        @keyframes foodVideoPan {
            0% { transform: scale(1) translateX(-1%); }
            100% { transform: scale(1.13) translateX(1%); }
        }

        @keyframes foodShimmer {
            0%,30% { transform: translateX(-100%); }
            65%,100% { transform: translateX(100%); }
        }

        @keyframes foodContentFloat {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes pillBob {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 24px;
            max-width: 1250px;
            margin: auto;
        }

        .service-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e9e9e1;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,.035);
            transition: transform .35s, box-shadow .35s;
            color: var(--dark);
        }

        .service-card:hover {
            transform: translateY(-9px);
            box-shadow: 0 20px 40px rgba(23,77,55,.12);
        }

        .service-image {
            display: block;
            width: 100%;
            height: 225px;
            object-fit: cover;
            transition: transform .6s;
        }

        .service-card:hover .service-image {
            transform: scale(1.05);
        }

        .service-image-wrap {
            overflow: hidden;
        }

        .service-content {
            padding: 23px;
        }

        .service-icon {
            display: inline-grid;
            place-items: center;
            width: 47px;
            height: 47px;
            margin-top: -48px;
            position: relative;
            background: white;
            border-radius: 14px;
            font-size: 1.35rem;
            box-shadow: 0 5px 20px #00000010;
        }

        .service-content h3 {
            margin: 13px 0 9px;
            font-size: 1.2rem;
        }

        .service-content p {
            min-height: 55px;
            color: #738075;
            font-size: .91rem;
            line-height: 1.7;
        }

        .service-link {
            display: inline-block;
            margin-top: 9px;
            color: var(--green-light);
            font-weight: 800;
            font-size: .9rem;
        }

        .food-section {
            overflow: hidden;
            background: #f0f4e9;
        }

        .food-track {
            display: flex;
            width: max-content;
            gap: 20px;
            animation: scrollFood 35s linear infinite;
        }

        .food-track:hover {
            animation-play-state: paused;
        }

        .food-card {
            position: relative;
            width: 280px;
            height: 235px;
            overflow: hidden;
            border-radius: 19px;
            background: #e1e1d8;
        }

        .food-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s;
        }

        .food-card:hover img {
            transform: scale(1.1);
        }

        .food-label {
            position: absolute;
            right: 10px;
            bottom: 10px;
            left: 10px;
            padding: 13px;
            border-radius: 11px;
            background: rgba(255,255,255,.93);
            color: var(--green);
            font-weight: 800;
        }

        /* Saved recipe cards: image and recipe name only */
        .saved-recipes-grid {
            display: grid;
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 24px;
            max-width: 1250px;
            margin: auto;
        }

        .saved-recipe-card {
            display: block;
            overflow: hidden;
            color: var(--dark);
            background: white;
            border: 1px solid #e1e8dc;
            border-radius: 20px;
            box-shadow: 0 8px 24px #203b2710;
            transition: transform .35s ease, box-shadow .35s ease;
        }

        .saved-recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 35px #174d3720;
        }

        .saved-recipe-image-wrap {
            height: 230px;
            overflow: hidden;
            background: #e4eadf;
        }

        .saved-recipe-image {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .65s cubic-bezier(.2,.7,.2,1);
        }

        .saved-recipe-card:hover .saved-recipe-image {
            transform: scale(1.05);
        }

        .saved-recipe-content {
            padding: 18px 22px 20px;
        }

        .saved-recipe-content h3 {
            margin: 0;
            color: var(--green);
            font-size: 1.12rem;
        }

        .empty-recipes {
            max-width: 760px;
            margin: auto;
            padding: 28px;
            border: 1px dashed #b8cbb5;
            border-radius: 18px;
            text-align: center;
            color: #69756b;
            background: #ffffffa8;
        }

        .cta-section {
            padding: 75px 6%;
            text-align: center;
            background: var(--green);
            color: white;
        }

        .cta-section h2 {
            margin: 12px auto;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(2rem,4vw,3.2rem);
        }

        .cta-section p {
            max-width: 600px;
            margin: 15px auto 26px;
            color: #d9e7dc;
            line-height: 1.8;
        }

        .cta-section .button {
            background: white;
            color: var(--green);
        }

        .site-footer {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            padding: 28px 6%;
            background: #123728;
            color: #e6eee7;
            font-size: .88rem;
        }

        .site-footer a {
            color: white;
        }

        .reveal {
            opacity: 0;
            transform: translateY(25px);
            transition: opacity .7s ease, transform .7s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(25px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes float {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @keyframes scrollFood {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }

        @media (prefers-reduced-motion: reduce) {
            .food-motion-cover::before,
            .food-motion-cover::after,
            .food-motion-content,
            .food-motion-pills span {
                animation: none;
            }
        }

        @media(max-width:900px) {
            .hero-section {
                grid-template-columns: 1fr;
                gap: 35px;
                padding-top: 45px;
            }

            .hero-image {
                height: 370px;
            }

            .services-grid {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }

            .main-nav {
                gap: 14px;
            }

            .saved-recipes-grid {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }
        }

        @media(max-width:600px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
                padding: 15px 5%;
            }

            .main-nav {
                width: 100%;
                gap: 14px;
                flex-wrap: wrap;
            }

            .main-nav a {
                font-size: .8rem;
            }

            .hero-section {
                padding: 38px 5%;
            }

            .hero-text h1 {
                font-size: 2.8rem;
            }

            .hero-image {
                height: 290px;
            }

            .image-badge {
                left: 10px;
                bottom: 10px;
                padding: 12px;
            }

            .highlights {
                gap: 10px;
            }

            .highlight strong {
                font-size: 1.35rem;
            }

            .highlight span {
                font-size: .72rem;
            }

            .content-section {
                padding: 50px 5%;
            }

            .services-grid,
            .saved-recipes-grid {
                grid-template-columns: 1fr;
            }

            .service-image {
                height: 230px;
            }

            .food-card {
                width: 230px;
                height: 195px;
            }

            .site-footer {
                flex-direction: column;
            }
        }

        @media(prefers-reduced-motion: reduce) {
            *,*::before,*::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }

            .reveal {
                opacity: 1;
                transform: none;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <a class="brand" href="index.php">
        <span class="brand-icon">🥭</span>
        <span>Smart Recipe Hub</span>
    </a>

    <nav class="main-nav">
        <a href="index.php">🏠 Home</a>
        <a href="calculator.php">🧮 Ingredients</a>
        <a href="admin.php" class="admin-link">🔐 Admin</a>
        <a href="profile.php" class="profile-link" title="My Profile" aria-label="My Profile">👤</a>
        <a href="logout.php" class="logout-link">Logout ↗</a>
    </nav>
</header>

<main>

    <section class="hero-section">
        <div class="hero-text">
            <span class="eyebrow">Fresh ideas · Perfect measurements</span>

            <h1>
                Make every recipe<br>
                <span>taste extraordinary.</span>
            </h1>

            <p>
                Welcome to your kitchen workspace. Plan ingredients,
                calculate production quantities, and organize your
                kitchen recipes in one beautiful place.
            </p>

            <p class="welcome-message">
                👋 Welcome back,
                <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>!
            </p>

            <div class="hero-buttons">
                <a href="calculator.php" class="button">
                    🧮 Calculate Ingredients →
                </a>

                <a href="#explore-recipes" class="button button-outline">
                    Explore Recipes
                </a>
            </div>

            <div class="highlights">
                <div class="highlight">
                    <strong>01</strong>
                    <span>Plan ingredients</span>
                </div>

                <div class="highlight">
                    <strong>02</strong>
                    <span>Calculate batches</span>
                </div>

                <div class="highlight">
                    <strong>03</strong>
                    <span>Protect recipes</span>
                </div>
            </div>
        </div>

        <div class="hero-image-wrap">
            <img
                class="hero-image"
                src="https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1200&q=85"
                alt="Fresh vegetables and a beautifully prepared meal"
            >

            <div class="image-badge">
                <div class="image-badge-icon">🌿</div>
                <div>
                    <strong>Freshly planned</strong>
                    <small>Every ingredient counts.</small>
                </div>
            </div>
        </div>
    </section>

    <!-- Saved recipes: image and recipe name only -->
    <section class="content-section saved-recipes-section" id="explore-recipes">

        <div class="section-title reveal">
            <span class="eyebrow">From your recipe database</span>
            <h2>Explore your saved recipes.</h2>
        </div>

        <?php if (!empty($homeRecipes)): ?>

            <div class="saved-recipes-grid">

                <?php foreach ($homeRecipes as $recipe):

                    $recipeName = (string)($recipe['recipe_name'] ?? 'Recipe');
                    $imageUrl = trim((string)($recipe['image_url'] ?? ''));

                    // Use a default image if the admin has not added an image URL.
                    if ($imageUrl === '') {

                        $nameLower = function_exists('mb_strtolower')
                            ? mb_strtolower($recipeName, 'UTF-8')
                            : strtolower($recipeName);

                        if (str_contains($nameLower, 'podi')) {
                            $imageUrl = 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?auto=format&fit=crop&w=900&q=85';
                        } elseif (
                            str_contains($nameLower, 'moringa') ||
                            str_contains($nameLower, 'keerai')
                        ) {
                            $imageUrl = 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=85';
                        } elseif (
                            str_contains($nameLower, 'rice') ||
                            str_contains($nameLower, 'biryani')
                        ) {
                            $imageUrl = 'https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=900&q=85';
                        } else {
                            $imageUrl = 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85';
                        }
                    }
                ?>

                    <!-- This is a non-clickable card: no link and no redirect -->
                    <div class="saved-recipe-card reveal">

                        <div class="saved-recipe-image-wrap">
                            <img
                                class="saved-recipe-image"
                                src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($recipeName, ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85';"
                            >
                        </div>

                        <div class="saved-recipe-content">
                            <h3>
                                <?= htmlspecialchars($recipeName, ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-recipes">
                <h3>No saved recipes yet</h3>

                <p>
                    When the admin saves recipes, they will appear here
                    automatically with their food images and names.
                </p>

                <a class="button" href="admin.php">Add a recipe →</a>
            </div>

        <?php endif; ?>

    </section>

    <section class="content-section services-section food-video-section" aria-label="Food inspiration video section">

        <div class="food-video-heading section-title reveal">
            <span class="eyebrow">A little kitchen inspiration</span>

            <h2>Fresh ideas, straight to your kitchen.</h2>

            <p>
                Enjoy a moving food-inspired cover after exploring
                the recipes saved in your collection.
            </p>
        </div>

        <div class="food-motion-cover reveal" aria-label="Animated food inspiration cover">

            <div class="food-motion-content">
                <span class="eyebrow">Fresh ingredients · Beautiful recipes</span>

                <h2>Good food starts with a little inspiration.</h2>

                <p>
                    Discover ingredients, plan your batch, and make
                    every recipe with confidence.
                </p>

                <div class="food-motion-pills">
                    <span>🥬 Fresh ingredients</span>
                    <span>🍲 Recipe inspiration</span>
                    <span>✨ Made with care</span>
                </div>

                <a class="button food-video-button" href="calculator.php">
                    Plan your ingredients →
                </a>
            </div>

        </div>
    </section>

    <section class="cta-section">

        <span class="eyebrow" style="color:#f4d98b;">
            Your kitchen · Your workflow
        </span>

        <h2>Ready to plan your next batch?</h2>

        <p>
            Start with your required quantity, calculate the ingredients,
            and prepare an organized production list.
        </p>

        <a href="calculator.php" class="button">
            Open Ingredient Calculator →
        </a>

    </section>

</main>

<footer class="site-footer">
    <span>🥭 <strong>Smart Recipe Hub</strong></span>
    <span>Ingredient planning made simple.</span>

    <span>
        Logged in as
        <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
    </span>
</footer>

<script>
const revealItems = document.querySelectorAll(".reveal");

if ("IntersectionObserver" in window) {

    const observer = new IntersectionObserver((entries) => {

        entries.forEach((entry) => {

            if (entry.isIntersecting) {
                entry.target.classList.add("visible");
                observer.unobserve(entry.target);
            }

        });

    }, { threshold: 0.12 });

    revealItems.forEach((item) => observer.observe(item));

} else {

    revealItems.forEach((item) => {
        item.classList.add("visible");
    });

}
</script>

</body>
</html>
