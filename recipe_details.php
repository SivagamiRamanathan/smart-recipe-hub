<?php
require_once __DIR__ . '/db.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$recipeId = filter_input(INPUT_GET, 'recipe_id', FILTER_VALIDATE_INT);
$recipe = null;
$errorMessage = '';

if (!$recipeId || $recipeId <= 0) {
    $errorMessage = 'Invalid recipe ID. Please select a recipe.';
} else {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, recipe_name, recipe_name_ta, recipe_name_th,
                    description_en, description_ta, description_th,
                    preparation_method, ingredients, base_yield, base_unit
             FROM recipes WHERE id = :id"
        );

        $stmt->execute(['id' => $recipeId]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            $errorMessage = 'Recipe not found.';
        }
    } catch (PDOException $e) {
        error_log('Recipe details error: ' . $e->getMessage());
        $errorMessage = 'Unable to load recipe details.';
    }
}

function escapeHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$ingredients = [];

if ($recipe) {
    $raw = (string)($recipe['ingredients'] ?? '');
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        $ingredients = $decoded;
    } else {
        $ingredients = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
<?= $recipe ? escapeHtml($recipe['recipe_name']) : 'Recipe Details' ?>
</title>

<style>
*{box-sizing:border-box}
body{margin:0;padding:30px 15px;font-family:Arial,sans-serif;background:#f3f8f2;color:#263328}
.container{max-width:750px;margin:auto;background:white;padding:30px;border-radius:14px;box-shadow:0 4px 18px #00000014}
h1,h2{color:#24743a}
h1{text-align:center;margin-top:0}
.description{text-align:center;color:#666;line-height:1.7}
.recipe-info{background:#f0f8ef;padding:15px;border-radius:8px;margin:20px 0}
.ingredient-list{list-style:none;padding:0}
.ingredient-list li{padding:13px;margin-bottom:8px;background:#f8faf7;border:1px solid #e4ebe2;border-radius:7px;overflow-wrap:anywhere}
.method{white-space:pre-line;line-height:1.8;background:#f8faf7;padding:15px;border-radius:8px}
.buttons{display:flex;justify-content:center;gap:12px;margin-top:25px;flex-wrap:wrap}
button,.link-button{border:0;padding:12px 20px;border-radius:7px;cursor:pointer;font-size:15px;text-decoration:none;display:inline-block}
.calculate-button{background:#24743a;color:white}
.print-button{background:#28734a;color:white}
.home-button{background:#e5e7eb;color:#222}
.error{background:#ffeaea;color:#a00000;padding:15px;border-radius:8px;text-align:center}
@media print{body{background:white;padding:0}.container{box-shadow:none;max-width:100%}.buttons{display:none}}
@media(max-width:500px){.container{padding:20px 15px}}
</style>
</head>

<body>
<div class="container">

<?php if ($errorMessage !== ''): ?>

    <h1>Recipe Details</h1>
    <p class="error"><?= escapeHtml($errorMessage) ?></p>

<?php else: ?>

    <h1><?= escapeHtml($recipe['recipe_name']) ?></h1>

    <?php if (!empty($recipe['description_en'])): ?>
        <p class="description">
            <?= escapeHtml($recipe['description_en']) ?>
        </p>
    <?php endif; ?>

    <div class="recipe-info">
        <strong>Base Yield:</strong>
        <?= escapeHtml($recipe['base_yield'] ?? '') ?>
        <?= escapeHtml($recipe['base_unit'] ?? '') ?>
    </div>

    <h2>Ingredients</h2>

    <ul class="ingredient-list">
    <?php if ($ingredients): ?>
        <?php foreach ($ingredients as $ingredient): ?>
            <li>
            <?php
            if (is_array($ingredient)) {
                $name = $ingredient['name']
                    ?? $ingredient['name_en']
                    ?? 'Ingredient';

                $quantity = $ingredient['quantity'] ?? '';
                $unit = $ingredient['unit'] ?? '';

                echo escapeHtml($name);

                if ($quantity !== '') {
                    echo ' — ' . escapeHtml($quantity . ' ' . $unit);
                }
            } else {
                echo escapeHtml($ingredient);
            }
            ?>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li>No ingredients available.</li>
    <?php endif; ?>
    </ul>

    <?php if (!empty($recipe['preparation_method'])): ?>
        <h2>Preparation Method</h2>
        <div class="method"><?= escapeHtml($recipe['preparation_method']) ?></div>
    <?php endif; ?>

    <div class="buttons">
        <a class="link-button calculate-button"
           href="calculator.php?recipe_id=<?= (int)$recipe['id'] ?>">
            Calculate for Required Quantity
        </a>

        <button class="print-button" onclick="window.print()">
            Print
        </button>

        <button class="home-button"
                onclick="window.location.href='index.php'">
            Home
        </button>
    </div>

<?php endif; ?>

</div>
</body>
</html>
