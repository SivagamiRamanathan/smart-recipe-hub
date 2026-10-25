```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$recipes = $pdo->query(
    "SELECT id, recipe_name, recipe_name_ta, recipe_name_th,
            base_yield, base_unit
     FROM recipes
     ORDER BY recipe_name"
)->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$result = null;

$lang = (string)($_POST['lang'] ?? 'en');

if (!in_array($lang, ['en', 'ta', 'th'], true)) {
    $lang = 'en';
}

function calculatorNormalizeUnit(string $unit): string
{
    $unit = strtolower(trim($unit));

    $aliases = [
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

        'cup' => 'cups',
        'pieces' => 'piece',
        'count' => 'piece',
    ];

    return $aliases[$unit] ?? $unit;
}

function calculatorUnitType(string $unit): string
{
    $unit = calculatorNormalizeUnit($unit);

    if (in_array($unit, ['g', 'kg'], true)) {
        return 'weight';
    }

    if (in_array($unit, ['ml', 'litre'], true)) {
        return 'volume';
    }

    if (in_array($unit, ['piece'], true)) {
        return 'count';
    }

    return 'other';
}

function calculatorToBase(float $quantity, string $unit): float
{
    $unit = calculatorNormalizeUnit($unit);

    if (in_array($unit, ['kg', 'litre'], true)) {
        return $quantity * 1000;
    }

    return $quantity;
}

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

/**
 * Scale measurements written in the preparation method.
 *
 * Cooking times and temperatures are intentionally not scaled.
 */
function calculatorScaleMethod(string $method, float $factor): string
{
    $pattern = '~(?<![\w.])'
        . '(\d+\s*/\s*\d+|\d+(?:\.\d+)?|[½¼¾⅓⅔])'
        . '\s*'
        . '(kilograms?|kg|grams?|g|millilitres?|milliliters?|ml'
        . '|litres?|liters?|litre|liter|l'
        . '|teaspoons?|tsp|tablespoons?|tbsp|cups?)'
        . '\b~iu';

    $scaledMethod = preg_replace_callback(
        $pattern,
        static function (array $matches) use ($factor): string {
            $number = trim($matches[1]);
            $unit = $matches[2];

            $fractions = [
                '½' => 0.5,
                '¼' => 0.25,
                '¾' => 0.75,
                '⅓' => 1 / 3,
                '⅔' => 2 / 3,
            ];

            if (isset($fractions[$number])) {
                $quantity = $fractions[$number];
            } elseif (str_contains($number, '/')) {
                $parts = preg_split('~/~', $number);

                if (!$parts || count($parts) !== 2) {
                    return $matches[0];
                }

                $numerator = (float)trim($parts[0]);
                $denominator = (float)trim($parts[1]);

                if ($denominator == 0.0) {
                    return $matches[0];
                }

                $quantity = $numerator / $denominator;
            } else {
                $quantity = (float)$number;
            }

            $scaled = $quantity * $factor;

            return calculatorFormatNumber($scaled) . ' ' . $unit;
        },
        $method
    );

    return $scaledMethod ?? $method;
}

function calculatorScaleIngredientList(
    string $raw,
    float $factor
): array {
    $items = json_decode($raw, true);
    $ingredients = [];

    /*
     * JSON ingredient format:
     * [
     *   {"name":"Mango","quantity":1,"unit":"kg"}
     * ]
     */
    if (is_array($items)) {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $quantity = isset($item['quantity'])
                && is_numeric($item['quantity'])
                ? (float)$item['quantity']
                : null;

            $unit = calculatorNormalizeUnit(
                (string)($item['unit'] ?? '')
            );

            $nameEn = (string)(
                $item['name_en']
                ?? $item['name']
                ?? 'Ingredient'
            );

            $nameTa = (string)($item['name_ta'] ?? '');
            $nameTh = (string)($item['name_th'] ?? '');

            $displayQuantity = '';

            if ($quantity !== null) {
                $displayQuantity = calculatorFormatNumber(
                    $quantity * $factor
                );

                if ($unit !== '') {
                    $displayQuantity .= ' ' . $unit;
                }
            }

            $ingredients[] = [
                'name_en' => $nameEn,
                'name_ta' => $nameTa,
                'name_th' => $nameTh,
                'display_quantity' => $displayQuantity,
            ];
        }

        return $ingredients;
    }

    /*
     * Plain-text ingredient format:
     * Mango - 1 kg
     * Chilli powder - 100 g
     */
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

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

        $pattern = '~^(.*?)\s*(?:–|—|-|:)?\s*'
            . '(\d+\s*/\s*\d+|\d+(?:\.\d+)?|[½¼¾⅓⅔])'
            . '\s*'
            . '(kilograms?|kg|grams?|g|millilitres?|milliliters?|ml'
            . '|litres?|liters?|litre|liter|l'
            . '|teaspoons?|tsp|tablespoons?|tbsp|cups?)'
            . '\s*$~iu';

        if (preg_match($pattern, $line, $matches)) {
            $number = trim($matches[2]);

            $fractions = [
                '½' => 0.5,
                '¼' => 0.25,
                '¾' => 0.75,
                '⅓' => 1 / 3,
                '⅔' => 2 / 3,
            ];

            if (isset($fractions[$number])) {
                $quantity = $fractions[$number];
            } elseif (str_contains($number, '/')) {
                $parts = preg_split('~/~', $number);

                if (!$parts || count($parts) !== 2) {
                    $ingredients[] = [
                        'name_en' => trim($matches[1]),
                        'name_ta' => '',
                        'name_th' => '',
                        'display_quantity' => '',
                    ];

                    continue;
                }

                $denominator = (float)trim($parts[1]);

                $quantity = $denominator == 0.0
                    ? 0.0
                    : (float)trim($parts[0]) / $denominator;
            } else {
                $quantity = (float)$number;
            }

            $unit = calculatorNormalizeUnit($matches[3]);

            $ingredients[] = [
                'name_en' => trim($matches[1]),
                'name_ta' => '',
                'name_th' => '',
                'display_quantity' => calculatorFormatNumber(
                    $quantity * $factor
                ) . ' ' . $unit,
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

    return $ingredients;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = filter_var(
        $_POST['recipe_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $quantityInput = trim(
        (string)($_POST['quantity'] ?? '')
    );

    $unit = calculatorNormalizeUnit(
        (string)($_POST['unit'] ?? 'g')
    );

    if (!$id) {
        $error = 'Please select a recipe from the dropdown.';
    } elseif (
        !is_numeric($quantityInput)
        || (float)$quantityInput <= 0
        || (float)$quantityInput > 100000
    ) {
        $error = 'Enter a valid positive quantity (maximum 100000).';
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT * FROM recipes WHERE id = ?'
            );

            $statement->execute([$id]);

            $recipe = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$recipe) {
                $error = 'Recipe not found in the database.';
            } else {
                $baseYield = (float)(
                    $recipe['base_yield'] ?? 1
                );

                $baseUnit = calculatorNormalizeUnit(
                    (string)($recipe['base_unit'] ?? 'g')
                );

                if ($baseYield <= 0) {
                    $error = 'This recipe has an invalid base yield. Please correct it in Admin.';
                } elseif (
                    calculatorUnitType($unit)
                    !== calculatorUnitType($baseUnit)
                    || calculatorUnitType($unit) === 'other'
                ) {
                    $error = 'Choose a compatible unit: g/kg for weight or ml/litre for volume.';
                } else {
                    $factor = calculatorToBase(
                        (float)$quantityInput,
                        $unit
                    ) / calculatorToBase(
                        $baseYield,
                        $baseUnit
                    );

                    $rawIngredients = (string)(
                        $recipe['ingredients'] ?? ''
                    );

                    $ingredients = calculatorScaleIngredientList(
                        $rawIngredients,
                        $factor
                    );

                    $recipeName = (string)(
                        $recipe['recipe_name'] ?? 'Recipe'
                    );

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

                    $descriptionColumn = 'description_' . $lang;

                    $description = (string)(
                        $recipe[$descriptionColumn] ?? ''
                    );

                    $method = (string)(
                        $recipe['preparation_method'] ?? ''
                    );

                    $methodColumn = 'method_' . $lang;

                    if (
                        trim((string)($recipe[$methodColumn] ?? '')) !== ''
                    ) {
                        $method = (string)$recipe[$methodColumn];
                    }

                    $method = calculatorScaleMethod(
                        $method,
                        $factor
                    );

                    $result = [
                        'recipe_id' => (int)$recipe['id'],
                        'recipe_name' => $recipeName,
                        'description' => $description,
                        'requested_quantity' => (float)$quantityInput,
                        'requested_unit' => $unit,
                        'ingredients' => $ingredients,
                        'preparation_method' => $method,
                        'base_yield' => $baseYield,
                        'base_unit' => $baseUnit,
                    ];
                }
            }
        } catch (PDOException $exception) {
            error_log(
                'Calculator query: ' . $exception->getMessage()
            );

            $error = 'Unable to read recipe from PostgreSQL. Check the database connection and recipe columns.';
        }
    }
}

function calculatorUnitList(): array
{
    return [
        ['Gram', 'g'],
        ['Kilogram', 'kg'],
        ['Millilitre', 'ml'],
        ['Litre', 'litre'],
    ];
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
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
            font-family: Inter, "Segoe UI", Arial, sans-serif;
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
            font-weight: 750;
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

        .method {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
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
    <p>Calculate ingredients and prepare your favourite recipes.</p>
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
            Select a saved recipe and enter your required quantity.
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
                            $recipeDisplay = match ($lang) {
                                'ta' => (
                                    $recipe['recipe_name_ta']
                                    ?: $recipe['recipe_name']
                                ),
                                'th' => (
                                    $recipe['recipe_name_th']
                                    ?: $recipe['recipe_name']
                                ),
                                default => $recipe['recipe_name'],
                            };
                            ?>

                            <option
                                value="<?= (int)$recipe['id'] ?>"
                                <?= (
                                    (string)($_POST['recipe_id'] ?? '')
                                    === (string)$recipe['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= h((string)$recipeDisplay) ?>
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
                        value="<?= h((string)($_POST['quantity'] ?? '500')) ?>"
                        required
                    >
                </div>

                <div class="field">
                    <label for="unit">Unit</label>

                    <select id="unit" name="unit">
                        <?php foreach (calculatorUnitList() as [$label, $unitValue]): ?>
                            <option
                                value="<?= h($unitValue) ?>"
                                <?= (
                                    calculatorNormalizeUnit(
                                        (string)($_POST['unit'] ?? 'g')
                                    ) === $unitValue
                                ) ? 'selected' : '' ?>
                            >
                                <?= h($label . ' (' . $unitValue . ')') ?>
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
                        calculatorFormatNumber(
                            $result['requested_quantity']
                        ) . ' ' . $result['requested_unit']
                    ) ?>
                </div>

            </div>

            <h3>🥣 Ingredients</h3>

            <?php if (count($result['ingredients']) > 0): ?>

                <?php foreach ($result['ingredients'] as $ingredient): ?>

                    <?php
                    $ingredientName = match ($lang) {
                        'ta' => (
                            $ingredient['name_ta'] !== ''
                            ? $ingredient['name_ta']
                            : $ingredient['name_en']
                        ),
                        'th' => (
                            $ingredient['name_th'] !== ''
                            ? $ingredient['name_th']
                            : $ingredient['name_en']
                        ),
                        default => $ingredient['name_en'],
                    };
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
                    Ingredient details are not available for this recipe.
                </p>

            <?php endif; ?>

            <h3 style="margin-top:25px">
                👩‍🍳 Preparation Method
            </h3>

            <?php if (trim($result['preparation_method']) !== ''): ?>

                <div class="method"><?= h($result['preparation_method']) ?></div>

            <?php else: ?>

                <p class="muted">
                    Preparation method is not available for this recipe
                    in the selected language.
                </p>

            <?php endif; ?>

            <p class="muted" style="margin-top:18px">
                Base yield:
                <?= h(
                    calculatorFormatNumber($result['base_yield'])
                    . ' '
                    . $result['base_unit']
                ) ?>.
                Cooking time may vary depending on the quantity,
                vessel size and cooking method.
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
    const element = document.getElementById('result');

    if (!element) {
        return;
    }

    const title = document.getElementById('recipeTitle');

    const fileName = (
        title ? title.innerText : 'recipe'
    ).replace(/[^a-z0-9_-]/gi, '_');

    const blob = new Blob(
        [element.innerText],
        { type: 'text/plain;charset=utf-8' }
    );

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = fileName + '_recipe.txt';

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
```
