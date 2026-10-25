<?php

require_once __DIR__ . '/db.php';

/*
 * Smart Recipe Hub Calculator
 * Uses the existing PostgreSQL recipes table.
 */

$error = '';
$result = null;

$lang = (string)($_POST['lang'] ?? 'en');

if (!in_array($lang, ['en', 'ta', 'th'], true)) {
    $lang = 'en';
}

/* Normalize measurement units */
function calculatorNormalizeUnit(string $unit): string
{
    $unit = strtolower(trim($unit));

    $map = [
        'gram' => 'g',
        'grams' => 'g',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'millilitre' => 'ml',
        'millilitres' => 'ml',
        'milliliter' => 'ml',
        'milliliters' => 'ml',
        'liter' => 'litre',
        'liters' => 'litre',
        'litres' => 'litre',
        'l' => 'litre',
        'teaspoon' => 'tsp',
        'teaspoons' => 'tsp',
        'tablespoon' => 'tbsp',
        'tablespoons' => 'tbsp',
        'cups' => 'cup',
        'pieces' => 'piece',
        'count' => 'piece',
    ];

    return $map[$unit] ?? $unit;
}

/* Convert units into comparable measurement categories */
function calculatorUnitType(string $unit): string
{
    $unit = calculatorNormalizeUnit($unit);

    if (in_array($unit, ['g', 'kg'], true)) {
        return 'weight';
    }

    if (in_array($unit, ['ml', 'litre', 'tsp', 'tbsp', 'cup'], true)) {
        return 'volume';
    }

    if ($unit === 'piece') {
        return 'count';
    }

    return 'other';
}

/* Convert a quantity into a common unit */
function calculatorToBase(float $quantity, string $unit): float
{
    $unit = calculatorNormalizeUnit($unit);

    $factors = [
        'kg' => 1000,
        'litre' => 1000,
        'tsp' => 5,
        'tbsp' => 15,
        'cup' => 240,
    ];

    return $quantity * ($factors[$unit] ?? 1);
}

/* Format scaled quantities */
function calculatorFormatNumber(float $number): string
{
    if (abs($number - round($number)) < 0.000001) {
        return (string)(int)round($number);
    }

    return rtrim(
        rtrim(number_format($number, 3, '.', ''), '0'),
        '.'
    );
}

/* Parse fractions used in ingredient lists */
function calculatorParseQuantity(string $value): ?float
{
    $value = trim($value);

    $fractions = [
        '½' => 0.5,
        '¼' => 0.25,
        '¾' => 0.75,
        '⅓' => 1 / 3,
        '⅔' => 2 / 3,
    ];

    if (isset($fractions[$value])) {
        return $fractions[$value];
    }

    if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $value, $matches)) {
        $denominator = (int)$matches[2];

        if ($denominator === 0) {
            return null;
        }

        return (int)$matches[1] / $denominator;
    }

    if (is_numeric($value)) {
        return (float)$value;
    }

    return null;
}

/* Load saved recipes */
try {
    $recipeQuery = $pdo->query(
        "SELECT id, recipe_name, recipe_name_ta, recipe_name_th,
                base_yield, base_unit
         FROM recipes
         ORDER BY recipe_name"
    );

    $recipes = $recipeQuery->fetchAll();

} catch (PDOException $e) {
    error_log('Calculator recipe list error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load recipes. Please check the database configuration.');
}

/* Calculate requested recipe quantities */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $recipeId = filter_var(
        $_POST['recipe_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $quantityInput = trim((string)($_POST['quantity'] ?? ''));

    $requestedUnit = calculatorNormalizeUnit(
        (string)($_POST['unit'] ?? 'g')
    );

    if (!$recipeId || $recipeId < 1) {
        $error = 'Please select a saved recipe.';
    } elseif (
        !is_numeric($quantityInput)
        || (float)$quantityInput <= 0
        || (float)$quantityInput > 100000
    ) {
        $error = 'Enter a quantity greater than 0 and not more than 100000.';
    } else {
        try {
            $statement = $pdo->prepare(
                "SELECT * FROM recipes WHERE id = :id"
            );

            $statement->execute(['id' => $recipeId]);

            $recipe = $statement->fetch();

            if (!$recipe) {
                $error = 'The selected recipe was not found.';
            } else {
                $baseYield = (float)($recipe['base_yield'] ?? 1);

                $baseUnit = calculatorNormalizeUnit(
                    (string)($recipe['base_unit'] ?? 'g')
                );

                if ($baseYield <= 0) {
                    $error = 'The recipe base yield must be greater than zero.';
                } elseif (
                    calculatorUnitType($requestedUnit) === 'other'
                    || calculatorUnitType($baseUnit) === 'other'
                    || calculatorUnitType($requestedUnit)
                        !== calculatorUnitType($baseUnit)
                ) {
                    $error = 'Choose a compatible unit. Weight: g/kg; volume: ml/litre; count: piece.';
                } else {
                    /*
                     * The selected quantity is compared with the recipe's
                     * base yield to calculate the scaling factor.
                     */
                    $factor = calculatorToBase(
                        (float)$quantityInput,
                        $requestedUnit
                    ) / calculatorToBase($baseYield, $baseUnit);

                    $rawIngredients = (string)($recipe['ingredients'] ?? '');

                    $ingredientData = json_decode(
                        $rawIngredients,
                        true
                    );

                    $ingredients = [];

                    /*
                     * JSON ingredient format:
                     * [
                     *   {"name":"Rice","quantity":200,"unit":"g"},
                     *   {"name":"Water","quantity":500,"unit":"ml"}
                     * ]
                     */
                    if (
                        json_last_error() === JSON_ERROR_NONE
                        && is_array($ingredientData)
                    ) {
                        foreach ($ingredientData as $item) {
                            if (!is_array($item)) {
                                continue;
                            }

                            $ingredientName = (string)(
                                $item['name']
                                ?? $item['name_en']
                                ?? 'Ingredient'
                            );

                            $ingredientQuantity = null;

                            if (
                                isset($item['quantity'])
                                && is_numeric($item['quantity'])
                            ) {
                                $ingredientQuantity = (float)$item['quantity'];
                            }

                            $ingredientUnit = calculatorNormalizeUnit(
                                (string)($item['unit'] ?? '')
                            );

                            $displayQuantity = '';

                            if ($ingredientQuantity !== null) {
                                $scaledQuantity = $ingredientQuantity * $factor;

                                $displayQuantity = calculatorFormatNumber(
                                    $scaledQuantity
                                );

                                if ($ingredientUnit !== '') {
                                    $displayQuantity .= ' ' . $ingredientUnit;
                                }
                            }

                            $ingredients[] = [
                                'name_en' => $ingredientName,
                                'name_ta' => (string)($item['name_ta'] ?? ''),
                                'name_th' => (string)($item['name_th'] ?? ''),
                                'display_quantity' => $displayQuantity,
                            ];
                        }
                    } else {
                        /*
                         * Plain-text ingredient format:
                         * Rice - 200 g
                         * Water - 500 ml
                         *
                         * Lines without a recognizable quantity are
                         * preserved without scaling.
                         */
                        $lines = preg_split(
                            '/\r\n|\r|\n/',
                            $rawIngredients
                        ) ?: [];

                        foreach ($lines as $line) {
                            $line = trim($line);

                            $line = preg_replace(
                                '/^[\s\-•*]+/u',
                                '',
                                $line
                            ) ?? $line;

                            if ($line === '') {
                                continue;
                            }

                            $pattern = '/^(.*?)\s*(?:–|—|-|:)?\s*'
                                . '(\d+(?:\.\d+)?|\d+\s*\/\s*\d+|½|¼|¾|⅓|⅔)'
                                . '\s*(kg|kilograms?|g|grams?|ml|millilitres?'
                                . '|milliliters?|litres?|liters?|l|tsp'
                                . '|teaspoons?|tbsp|tablespoons?|cups?)\b/iu';

                            if (preg_match($pattern, $line, $matches)) {
                                $ingredientName = trim($matches[1]);

                                $parsedQuantity = calculatorParseQuantity(
                                    $matches[2]
                                );

                                $ingredientUnit = calculatorNormalizeUnit(
                                    $matches[3]
                                );

                                if ($parsedQuantity !== null) {
                                    $scaledQuantity = $parsedQuantity * $factor;

                                    $displayQuantity =
                                        calculatorFormatNumber($scaledQuantity)
                                        . ' '
                                        . $ingredientUnit;
                                } else {
                                    $displayQuantity = '';
                                }

                                $ingredients[] = [
                                    'name_en' => $ingredientName,
                                    'name_ta' => '',
                                    'name_th' => '',
                                    'display_quantity' => $displayQuantity,
                                ];
                            } else {
                                $ingredients[] = [
                                    'name_en' => $line,
                                    'name_ta' => '',
                                    'name_th' => '',
                                    'display_quantity' => '',
                                ];
                            }
                        }
                    }

                    /* Recipe title in selected language */
                    $recipeName = (string)$recipe['recipe_name'];

                    if (
                        $lang === 'ta'
                        && trim((string)($recipe['recipe_name_ta'] ?? '')) !== ''
                    ) {
                        $recipeName = (string)$recipe['recipe_name_ta'];
                    } elseif (
                        $lang === 'th'
                        && trim((string)($recipe['recipe_name_th'] ?? '')) !== ''
                    ) {
                        $recipeName = (string)$recipe['recipe_name_th'];
                    }

                    /* Preparation method */
                    $method = (string)(
                        $recipe['preparation_method'] ?? ''
                    );

                    $methodColumn = 'method_' . $lang;

                    if (
                        trim((string)($recipe[$methodColumn] ?? '')) !== ''
                    ) {
                        $method = (string)$recipe[$methodColumn];
                    }

                    $descriptionColumn = 'description_' . $lang;

                    $description = (string)(
                        $recipe[$descriptionColumn] ?? ''
                    );

                    $result = [
                        'recipe_name' => $recipeName,
                        'description' => $description,
                        'requested_quantity' => (float)$quantityInput,
                        'requested_unit' => $requestedUnit,
                        'ingredients' => $ingredients,
                        'preparation_method' => $method,
                        'base_yield' => $baseYield,
                        'base_unit' => $baseUnit,
                    ];
                }
            }

        } catch (PDOException $e) {
            error_log('Calculator calculation error: ' . $e->getMessage());

            $error = 'Unable to calculate the recipe. Please check the database.';
        }
    }
}

/* Available units */
$unitOptions = [
    ['Gram', 'g'],
    ['Kilogram', 'kg'],
    ['Millilitre', 'ml'],
    ['Litre', 'litre'],
    ['Piece', 'piece'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Smart Recipe Hub | Calculator</title>

    <style>
        :root {
            --forest: #173f2b;
            --green: #28734a;
            --mint: #eaf5e9;
            --cream: #f7f8ef;
            --ink: #25352a;
            --muted: #718074;
            --line: #dce6d9;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--cream);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        .hero {
            background: linear-gradient(125deg, #173f2b, #34764d);
            color: white;
            text-align: center;
            padding: 28px 15px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(28px, 4vw, 40px);
        }

        .nav {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            background: #123521;
            padding: 10px;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 8px 13px;
            border-radius: 9px;
        }

        .nav a:hover {
            background: #ffffff20;
        }

        .wrap {
            max-width: 900px;
            margin: 28px auto;
            padding: 0 16px;
        }

        .card {
            background: white;
            border: 1px solid #e5ece1;
            border-radius: 20px;
            padding: clamp(19px, 4vw, 30px);
            margin-bottom: 22px;
            box-shadow: 0 12px 35px #173f2b0b;
        }

        h2, h3 {
            color: var(--forest);
            margin-top: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 18px;
        }

        .wide {
            grid-column: 1 / -1;
        }

        .field {
            margin: 13px 0;
        }

        label {
            display: block;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 7px;
        }

        input, select {
            width: 100%;
            padding: 13px;
            border: 1px solid #d5dfd2;
            border-radius: 11px;
            background: #fcfdf9;
            font: inherit;
        }

        input:focus, select:focus {
            outline: 3px solid #26724720;
            border-color: var(--green);
        }

        .btn {
            border: 0;
            display: inline-flex;
            justify-content: center;
            padding: 12px 17px;
            border-radius: 11px;
            background: var(--green);
            color: white;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }

        .btn-light {
            background: var(--mint);
            color: var(--forest);
        }

        .alert {
            padding: 13px 15px;
            border-radius: 12px;
            margin: 14px 0;
            background: #fff0ed;
            color: #a33125;
        }

        .muted {
            color: var(--muted);
            font-size: 14px;
        }

        .yield {
            display: inline-block;
            background: var(--mint);
            padding: 10px 15px;
            border-radius: 12px;
            font-weight: 850;
            color: var(--forest);
        }

        .ingredient {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 13px 15px;
            margin: 9px 0;
            border: 1px solid #e2eddd;
            border-left: 5px solid #43845a;
            border-radius: 12px;
            background: #f7faf5;
        }

        .amount {
            font-weight: 850;
            color: var(--forest);
            white-space: nowrap;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        footer {
            text-align: center;
            padding: 22px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .wide {
                grid-column: auto;
            }

            .actions .btn {
                width: 100%;
            }

            .ingredient {
                align-items: flex-start;
            }
        }

        @media print {
            .hero, .nav, form, .actions, footer {
                display: none;
            }

            .card {
                box-shadow: none;
                border: 0;
            }
        }
    </style>
</head>

<body>

<header class="hero">
    <h1>🥭 Smart Recipe Hub</h1>
    <p>Calculate ingredients for your required quantity</p>
</header>

<nav class="nav">
    <a href="index.php">Home</a>
    <a href="calculator.php">Calculator</a>
    <a href="profile.php">Profile</a>
    <a href="logout.php">Logout</a>
</nav>

<main class="wrap">

    <section class="card">
        <h2>Calculate Ingredients</h2>

        <p class="muted">
            Select a saved recipe, enter your required quantity,
            and calculate the ingredient amounts.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert" role="alert">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="calculator.php">
            <?= csrf_field() ?>

            <div class="grid">

                <div class="field wide">
                    <label for="recipe_id">Recipe</label>

                    <select id="recipe_id" name="recipe_id" required>
                        <option value="">Choose saved recipe</option>

                        <?php foreach ($recipes as $recipe): ?>
                            <?php
                            $displayName = (string)$recipe['recipe_name'];

                            if (
                                $lang === 'ta'
                                && trim((string)($recipe['recipe_name_ta'] ?? '')) !== ''
                            ) {
                                $displayName = (string)$recipe['recipe_name_ta'];
                            } elseif (
                                $lang === 'th'
                                && trim((string)($recipe['recipe_name_th'] ?? '')) !== ''
                            ) {
                                $displayName = (string)$recipe['recipe_name_th'];
                            }
                            ?>

                            <option
                                value="<?= (int)$recipe['id'] ?>"
                                <?= (
                                    (string)($_POST['recipe_id'] ?? '')
                                    === (string)$recipe['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= h($displayName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="quantity">Required quantity</label>

                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        min="0.01"
                        max="100000"
                        step="any"
                        value="<?= h($_POST['quantity'] ?? '500') ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="unit">Unit</label>

                    <select id="unit" name="unit">
                        <?php foreach ($unitOptions as [$unitName, $unitValue]): ?>
                            <option
                                value="<?= h($unitValue) ?>"
                                <?= (
                                    calculatorNormalizeUnit(
                                        (string)($_POST['unit'] ?? 'g')
                                    ) === $unitValue
                                ) ? 'selected' : '' ?>
                            >
                                <?= h($unitName . ' (' . $unitValue . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field wide">
                    <label for="lang">Language / மொழி</label>

                    <select id="lang" name="lang">
                        <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>
                            English
                        </option>

                        <option value="ta" <?= $lang === 'ta' ? 'selected' : '' ?>>
                            தமிழ்
                        </option>

                        <option value="th" <?= $lang === 'th' ? 'selected' : '' ?>>
                            Thanglish
                        </option>
                    </select>
                </div>

            </div>

            <button class="btn" type="submit">
                ✨ Calculate Recipe
            </button>
        </form>
    </section>

    <?php if (is_array($result)): ?>

        <section class="card" id="result">

            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:start">

                <div>
                    <h2 id="recipeTitle">
                        <?= h($result['recipe_name']) ?>
                    </h2>

                    <?php if (trim($result['description']) !== ''): ?>
                        <p><?= nl2br(h($result['description'])) ?></p>
                    <?php endif; ?>
                </div>

                <div class="yield">
                    <?= h(
                        calculatorFormatNumber($result['requested_quantity'])
                        . ' '
                        . $result['requested_unit']
                    ) ?>
                </div>

            </div>

            <h3>🥣 Ingredients</h3>

            <?php if (count($result['ingredients']) > 0): ?>

                <?php foreach ($result['ingredients'] as $ingredient): ?>

                    <?php
                    $ingredientName = $ingredient['name_en'];

                    if (
                        $lang === 'ta'
                        && $ingredient['name_ta'] !== ''
                    ) {
                        $ingredientName = $ingredient['name_ta'];
                    } elseif (
                        $lang === 'th'
                        && $ingredient['name_th'] !== ''
                    ) {
                        $ingredientName = $ingredient['name_th'];
                    }
                    ?>

                    <div class="ingredient">
                        <span><?= h($ingredientName) ?></span>

                        <strong class="amount">
                            <?= h($ingredient['display_quantity']) ?>
                        </strong>
                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="muted">
                    No ingredients have been added to this recipe yet.
                </p>

            <?php endif; ?>

            <h3 style="margin-top:25px">
                👩‍🍳 Preparation Method
            </h3>

            <?php if (trim($result['preparation_method']) !== ''): ?>

                <div>
                    <?= nl2br(h($result['preparation_method'])) ?>
                </div>

            <?php else: ?>

                <p class="muted">
                    Preparation method has not been added for this recipe.
                </p>

            <?php endif; ?>

            <p class="muted">
                Base yield:
                <?= h(
                    calculatorFormatNumber($result['base_yield'])
                    . ' '
                    . $result['base_unit']
                ) ?>.
                Preparation instructions are displayed as saved;
                ingredient quantities are scaled according to the selected yield.
            </p>

            <div class="actions">
                <button
                    class="btn"
                    type="button"
                    onclick="window.print()"
                >
                    🖨️ Print
                </button>

                <button
                    class="btn btn-light"
                    type="button"
                    onclick="downloadResult()"
                >
                    ⬇️ Download TXT
                </button>
            </div>

        </section>

    <?php endif; ?>

</main>

<footer>
    Smart Recipe Hub · Cook smart, measure better 🌿
</footer>

<script>
function downloadResult() {
    const resultElement = document.getElementById('result');

    if (!resultElement) {
        return;
    }

    const title = document.getElementById('recipeTitle');

    const filename = (
        title ? title.innerText : 'recipe'
    ).replace(/[^a-z0-9_-]/gi, '_');

    const blob = new Blob(
        [resultElement.innerText],
        { type: 'text/plain;charset=utf-8' }
    );

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename + '_recipe.txt';

    document.body.appendChild(link);
    link.click();
    link.remove();

    setTimeout(function () {
        URL.revokeObjectURL(url);
    }, 1000);
}
</script>

</body>
</html>
