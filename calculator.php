<?php
// IMPORTANT: No spaces or HTML before this opening PHP tag.
require_once __DIR__ . "/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login check before any HTML output.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$recipeId = filter_input(INPUT_GET, "recipe_id", FILTER_VALIDATE_INT);

if (!$recipeId || $recipeId < 1) {
    http_response_code(400);
    exit("Invalid recipe ID. Please select a recipe from the home page.");
}

$recipe = null;
$error = "";
$ingredients = [];
$calculatedIngredients = [];
$requiredYield = null;
$baseYield = 1;
$calculationDone = false;

try {
    // SELECT * avoids assuming every optional column exists.
    $stmt = $pdo->prepare(
        "SELECT * FROM recipes WHERE id = :id LIMIT 1"
    );

    $stmt->execute(["id" => $recipeId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        http_response_code(404);
        exit("Recipe not found. Please return to the home page.");
    }

} catch (Throwable $e) {
    error_log("Calculator recipe loading error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load the recipe. Please check the database connection.");
}


// Find a value from possible column names.
function getRecipeValue(array $recipe, array $keys, $default = "")
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $recipe) && $recipe[$key] !== null) {
            return $recipe[$key];
        }
    }

    return $default;
}


// Read base yield from the recipe record.
$baseYieldValue = getRecipeValue(
    $recipe,
    ["base_yield", "yield_quantity", "servings", "base_quantity"],
    1
);

if (is_numeric($baseYieldValue) && (float)$baseYieldValue > 0) {
    $baseYield = (float)$baseYieldValue;
}


// Read recipe name and image.
$recipeName = (string)getRecipeValue(
    $recipe,
    ["recipe_name", "name"],
    "Recipe"
);

$imageUrl = (string)getRecipeValue(
    $recipe,
    ["image_url", "image"],
    ""
);


// Read optional description.
$description = (string)getRecipeValue(
    $recipe,
    ["description", "recipe_description"],
    ""
);


// Read preparation method from possible column names.
$preparationMethod = getRecipeValue(
    $recipe,
    ["preparation_method", "method", "instructions", "steps"],
    ""
);

if (is_array($preparationMethod)) {
    $preparationMethod = implode("\n", $preparationMethod);
}

$preparationMethod = (string)$preparationMethod;


// Parse ingredients stored in the recipes table.
$rawIngredients = getRecipeValue(
    $recipe,
    ["ingredients", "ingredient_details"],
    ""
);

if (is_array($rawIngredients)) {
    $decodedIngredients = $rawIngredients;
} else {
    $decodedIngredients = json_decode((string)$rawIngredients, true);
}


// Supported JSON formats include:
// [
//   {"name":"Rice","quantity":2,"unit":"kg"},
//   {"ingredient_name":"Salt","quantity":10,"unit":"g"}
// ]
//
// If the database contains plain text, display the text without
// pretending that unstructured quantities can be scaled accurately.

if (is_array($decodedIngredients)) {

    // Handle an object containing an "ingredients" array.
    if (isset($decodedIngredients["ingredients"]) &&
        is_array($decodedIngredients["ingredients"])) {
        $decodedIngredients = $decodedIngredients["ingredients"];
    }

    foreach ($decodedIngredients as $item) {

        if (is_string($item)) {
            $ingredients[] = [
                "name" => $item,
                "quantity" => null,
                "unit" => "",
                "scalable" => false
            ];

            continue;
        }

        if (!is_array($item)) {
            continue;
        }

        $name = $item["name"]
            ?? $item["ingredient_name"]
            ?? $item["name_en"]
            ?? $item["ingredient"]
            ?? "";

        $quantity = $item["quantity"]
            ?? $item["qty"]
            ?? $item["amount"]
            ?? null;

        $unit = $item["unit"]
            ?? $item["unit_name"]
            ?? "";

        $scalable = is_numeric($quantity);

        $ingredients[] = [
            "name" => (string)$name,
            "quantity" => $scalable ? (float)$quantity : null,
            "unit" => (string)$unit,
            "scalable" => $scalable
        ];
    }

} elseif (trim((string)$rawIngredients) !== "") {

    // Plain text cannot be reliably scaled without structured quantities.
    $ingredients[] = [
        "name" => (string)$rawIngredients,
        "quantity" => null,
        "unit" => "",
        "scalable" => false
    ];
}


// Process calculation form.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedRecipeId = filter_input(
        INPUT_POST,
        "recipe_id",
        FILTER_VALIDATE_INT
    );

    $postedYield = filter_input(
        INPUT_POST,
        "required_quantity",
        FILTER_VALIDATE_FLOAT
    );

    if (!$postedRecipeId || $postedRecipeId !== (int)$recipeId) {

        $error = "Invalid recipe submission.";

    } elseif ($postedYield === false ||
              $postedYield === null ||
              $postedYield <= 0) {

        $error = "Please enter a required quantity greater than zero.";

    } else {

        $requiredYield = (float)$postedYield;
        $calculationDone = true;

        $scaleFactor = $requiredYield / $baseYield;

        foreach ($ingredients as $ingredient) {

            if ($ingredient["scalable"]) {

                $scaledQuantity =
                    $ingredient["quantity"] * $scaleFactor;

                $calculatedIngredients[] = [
                    "name" => $ingredient["name"],
                    "quantity" => $scaledQuantity,
                    "unit" => $ingredient["unit"],
                    "scalable" => true
                ];

            } else {

                $calculatedIngredients[] = $ingredient;

            }

        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($recipeName, ENT_QUOTES, 'UTF-8') ?>
        | Ingredient Calculator
    </title>

    <link rel="stylesheet" href="style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --green: #174d37;
            --green-light: #287653;
            --cream: #fbf8f1;
            --dark: #202b23;
            --orange: #f2a444;
        }

        * {
            box-sizing: border-box;
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
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 17px 6%;
            background: white;
            border-bottom: 1px solid #e9e8df;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--green);
            font-size: 1.2rem;
            font-weight: 800;
        }

        .main-nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .main-nav a {
            color: var(--green);
            font-weight: 700;
        }

        .page {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--green);
            font-weight: 700;
        }

        .recipe-header {
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            padding: 25px;
            background: white;
            border: 1px solid #e5e8de;
            border-radius: 22px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .04);
        }

        .recipe-image {
            width: 100%;
            height: 320px;
            object-fit: cover;
            border-radius: 16px;
            background: #e4eadf;
        }

        .recipe-info {
            align-self: center;
        }

        .eyebrow {
            color: var(--green-light);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        h1, h2 {
            font-family: 'Playfair Display', Georgia, serif;
        }

        h1 {
            margin: 12px 0;
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1.15;
        }

        .description {
            color: #6c786e;
            line-height: 1.8;
        }

        .base-info {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 14px;
            background: #edf5e9;
            border-radius: 10px;
            color: var(--green);
            font-weight: 700;
        }

        .panel {
            margin-top: 25px;
            padding: 25px;
            background: white;
            border: 1px solid #e5e8de;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .035);
        }

        .panel h2 {
            margin-top: 0;
            color: var(--green);
        }

        .calculator-form {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 15px;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input[type="number"] {
            width: 100%;
            min-height: 48px;
            padding: 12px;
            border: 1px solid #ccd8cb;
            border-radius: 10px;
            font: inherit;
        }

        .button {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-height: 48px;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            background: var(--green);
            color: white;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .button:hover {
            background: var(--green-light);
        }

        .error {
            margin-bottom: 18px;
            padding: 14px;
            border-radius: 10px;
            background: #fff0ef;
            color: #a3261c;
        }

        .success {
            padding: 15px;
            margin-bottom: 18px;
            border-radius: 10px;
            background: #edf5e9;
            color: var(--green);
        }

        .ingredient-list {
            display: grid;
            gap: 12px;
            padding: 0;
            list-style: none;
        }

        .ingredient-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #e5e8de;
            border-radius: 12px;
            background: #fffefa;
        }

        .ingredient-name {
            font-weight: 700;
        }

        .ingredient-quantity {
            color: var(--green);
            font-weight: 800;
            text-align: right;
        }

        .muted {
            color: #788078;
            line-height: 1.8;
        }

        .method-text {
            white-space: pre-wrap;
            line-height: 1.9;
            color: #566258;
        }

        .footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 25px;
        }

        .button-secondary {
            background: #edf5e9;
            color: var(--green);
        }

        @media (max-width: 700px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .recipe-header {
                grid-template-columns: 1fr;
                padding: 17px;
            }

            .recipe-image {
                height: 240px;
            }

            .panel {
                padding: 18px;
            }

            .ingredient-item {
                align-items: flex-start;
                flex-direction: column;
            }

            .ingredient-quantity {
                text-align: left;
            }
        }
    </style>
</head>

<body>

<header class="topbar">

    <a class="brand" href="index.php">
        🥭 Smart Recipe Hub
    </a>

    <nav class="main-nav">
        <a href="index.php">🏠 Home</a>
        <a href="calculator.php">🧮 Calculator</a>
        <a href="profile.php">👤 Profile</a>
        <a href="logout.php">Logout ↗</a>
    </nav>

</header>


<main class="page">

    <a class="back-link" href="index.php#explore-recipes">
        ← Back to saved recipes
    </a>


    <!-- RECIPE DETAILS -->

    <section class="recipe-header">

        <div>

            <?php if ($imageUrl !== ""): ?>

                <img
                    class="recipe-image"
                    src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($recipeName, ENT_QUOTES, 'UTF-8') ?>"
                >

            <?php else: ?>

                <div
                    class="recipe-image"
                    style="display:grid;place-items:center;font-size:4rem;"
                >
                    🍲
                </div>

            <?php endif; ?>

        </div>


        <div class="recipe-info">

            <span class="eyebrow">
                Ingredient calculator
            </span>

            <h1>
                <?= htmlspecialchars($recipeName, ENT_QUOTES, 'UTF-8') ?>
            </h1>

            <?php if ($description !== ""): ?>

                <p class="description">
                    <?= nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) ?>
                </p>

            <?php else: ?>

                <p class="description">
                    Calculate ingredient quantities for your required batch.
                </p>

            <?php endif; ?>

            <div class="base-info">
                Base quantity:
                <?= htmlspecialchars(
                    rtrim(rtrim(number_format($baseYield, 4, '.', ''), '0'), '.'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        </div>

    </section>


    <!-- CALCULATOR FORM -->

    <section class="panel">

        <h2>🧮 Calculate Ingredients</h2>

        <p class="muted">
            Enter your required quantity. The ingredient quantities will
            be scaled using the recipe's base quantity.
        </p>

        <?php if ($error !== ""): ?>

            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>


        <form
            class="calculator-form"
            method="POST"
            action="calculator.php?recipe_id=<?= (int)$recipeId ?>"
        >

            <input
                type="hidden"
                name="recipe_id"
                value="<?= (int)$recipeId ?>"
            >

            <div class="form-group">

                <label for="required_quantity">
                    Required quantity
                </label>

                <input
                    type="number"
                    id="required_quantity"
                    name="required_quantity"
                    min="0.001"
                    step="any"
                    required
                    value="<?= $requiredYield !== null
                        ? htmlspecialchars((string)$requiredYield, ENT_QUOTES, 'UTF-8')
                        : '' ?>"
                    placeholder="Enter required quantity"
                >

            </div>

            <button type="submit" class="button">
                Calculate →
            </button>

        </form>

    </section>


    <!-- INGREDIENT RESULTS -->

    <section class="panel">

        <h2>
            <?= $calculationDone
                ? "Calculated Ingredients"
                : "Recipe Ingredients" ?>
        </h2>

        <?php if ($calculationDone): ?>

            <div class="success">

                Required quantity:
                <strong>
                    <?= htmlspecialchars(
                        rtrim(rtrim(number_format($requiredYield, 4, '.', ''), '0'), '.'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

                <br>

                Scale factor:
                <strong>
                    <?= htmlspecialchars(
                        rtrim(rtrim(number_format($requiredYield / $baseYield, 4, '.', ''), '0'), '.'),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>

            </div>

        <?php endif; ?>


        <?php
        $displayIngredients = $calculationDone
            ? $calculatedIngredients
            : $ingredients;
        ?>


        <?php if (!empty($displayIngredients)): ?>

            <ul class="ingredient-list">

                <?php foreach ($displayIngredients as $ingredient): ?>

                    <li class="ingredient-item">

                        <span class="ingredient-name">
                            <?= htmlspecialchars(
                                $ingredient["name"] ?? "",
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <span class="ingredient-quantity">

                            <?php if (!empty($ingredient["scalable"])): ?>

                                <?php
                                $quantity = (float)$ingredient["quantity"];

                                echo htmlspecialchars(
                                    rtrim(
                                        rtrim(number_format($quantity, 4, '.', ''), '0'),
                                        '.'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                                if (!empty($ingredient["unit"])) {
                                    echo " " . htmlspecialchars(
                                        $ingredient["unit"],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                }
                                ?>

                            <?php else: ?>

                                <span class="muted">
                                    Quantity not available in structured format
                                </span>

                            <?php endif; ?>

                        </span>

                    </li>

                <?php endforeach; ?>

            </ul>

            <?php if (!$calculationDone): ?>

                <p class="muted">
                    Enter the required quantity above to calculate the
                    ingredient quantities.
                </p>

            <?php endif; ?>


            <?php
            $hasUnscalableIngredient = false;

            foreach ($displayIngredients as $ingredient) {
                if (empty($ingredient["scalable"])) {
                    $hasUnscalableIngredient = true;
                    break;
                }
            }
            ?>

            <?php if ($hasUnscalableIngredient): ?>

                <p class="muted">
                    Some ingredient details do not contain separate numeric
                    quantities and units. Those details cannot be scaled
                    automatically. Store ingredients as structured data
                    containing a name, quantity, and unit for accurate
                    calculation.
                </p>

            <?php endif; ?>


        <?php else: ?>

            <p class="muted">
                No structured ingredients were found for this recipe.
                Please check the ingredients saved by the admin.
            </p>

        <?php endif; ?>

    </section>


    <!-- PREPARATION METHOD -->

    <section class="panel">

        <h2>👩‍🍳 Preparation Method</h2>

        <?php if (trim($preparationMethod) !== ""): ?>

            <div class="method-text">
                <?= htmlspecialchars(
                    $preparationMethod,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php else: ?>

            <p class="muted">
                No preparation method was found in the recipe record.
            </p>

        <?php endif; ?>

    </section>


    <div class="footer-actions">

        <a href="index.php#explore-recipes" class="button button-secondary">
            ← Back to Recipes
        </a>

        <button
            type="button"
            class="button"
            onclick="window.print()"
        >
            🖨️ Print Recipe
        </button>

    </div>

</main>

</body>
</html>
