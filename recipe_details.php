<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$recipeId = filter_input(INPUT_GET, 'recipe_id', FILTER_VALIDATE_INT);

if (!$recipeId || $recipeId <= 0) {
    die("Invalid recipe ID. Please select a recipe.");
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, recipe_name, recipe_name_ta, recipe_name_th,
                description_en, description_ta, description_th,
                preparation_method, ingredients, base_yield, base_unit
         FROM recipes
         WHERE id = :id"
    );

    $stmt->execute(['id' => $recipeId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        http_response_code(404);
        die("Recipe not found.");
    }

    $ingredientsRaw = $recipe['ingredients'] ?? '';
    $ingredients = json_decode((string)$ingredientsRaw, true);

    // If ingredients are stored as plain text instead of JSON
    if (!is_array($ingredients)) {
        $ingredients = preg_split(
            '/\r\n|\r|\n/',
            (string)$ingredientsRaw
        ) ?: [];
    }

} catch (PDOException $e) {
    error_log('Recipe details error: ' . $e->getMessage());
    http_response_code(500);
    die("Unable to load recipe details. Please try again later.");
}

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= escapeHtml($recipe['recipe_name']) ?> - Ingredients
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f3f8f2;
            color: #263328;
        }

        .container {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
        }

        h1 {
            color: #24743a;
            margin-top: 0;
            text-align: center;
        }

        .description {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
        }

        h2 {
            color: #24743a;
            border-bottom: 2px solid #e0eee1;
            padding-bottom: 10px;
        }

        .recipe-info {
            background: #f0f8ef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .ingredient-list {
            list-style: none;
            padding: 0;
        }

        .ingredient-list li {
            padding: 13px;
            margin-bottom: 8px;
            background: #f8faf7;
            border: 1px solid #e4ebe2;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }

        .ingredient-name {
            font-weight: bold;
        }

        .quantity {
            float: right;
            color: #24743a;
            font-weight: bold;
            margin-left: 12px;
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        button, .home-button {
            border: none;
            padding: 12px 22px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
            text-decoration: none;
        }

        .print-button {
            background: #24743a;
            color: white;
        }

        .cancel-button {
            background: #e5e7eb;
            color: #222;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: 10px;
                max-width: 100%;
            }

            .buttons {
                display: none !important;
            }

            .ingredient-list li {
                break-inside: avoid;
            }
        }

        @media (max-width: 500px) {
            .container {
                padding: 20px 15px;
            }

            .quantity {
                float: none;
                display: block;
                margin: 6px 0 0;
            }
        }
    </style>
</head>

<body>

<div class="container">

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
        <?php if (count($ingredients) > 0): ?>

            <?php foreach ($ingredients as $ingredient): ?>

                <?php
                if (is_array($ingredient)) {
                    $ingredientName = $ingredient['name']
                        ?? $ingredient['name_en']
                        ?? 'Ingredient';

                    $quantity = $ingredient['quantity'] ?? null;
                    $unit = $ingredient['unit'] ?? '';
                } else {
                    $ingredientName = trim((string)$ingredient);
                    $quantity = null;
                    $unit = '';
                }
                ?>

                <?php if (trim((string)$ingredientName) !== ''): ?>
                    <li>
                        <span class="ingredient-name">
                            <?= escapeHtml($ingredientName) ?>
                        </span>

                        <?php if ($quantity !== null && $quantity !== ''): ?>
                            <span class="quantity">
                                <?= escapeHtml($quantity) ?>
                                <?= escapeHtml($unit) ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>

            <?php endforeach; ?>

        <?php else: ?>

            <li>No ingredients available for this recipe.</li>

        <?php endif; ?>
    </ul>

    <div class="buttons">
        <button
            class="print-button"
            onclick="window.print()">
            Print Ingredients
        </button>

        <button
            class="cancel-button"
            onclick="goHome()">
            Cancel / Home
        </button>
    </div>

</div>

<script>
function goHome() {
    window.location.href = 'index.php';
}
</script>

</body>
</html>
