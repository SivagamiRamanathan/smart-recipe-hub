```php
<?php
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

function escapeHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeUnit(string $unit): string
{
    $unit = strtolower(trim($unit));

    $units = [
        'g' => 'g',
        'gram' => 'g',
        'grams' => 'g',
        'kg' => 'kg',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'ml' => 'ml',
        'milliliter' => 'ml',
        'milliliters' => 'ml',
        'millilitre' => 'ml',
        'millilitres' => 'ml',
        'l' => 'l',
        'liter' => 'l',
        'liters' => 'l',
        'litre' => 'l',
        'litres' => 'l',
        'piece' => 'piece',
        'pieces' => 'piece',
        'pcs' => 'piece',
        'pc' => 'piece',
        'count' => 'piece'
    ];

    return $units[$unit] ?? $unit;
}

function unitCategory(string $unit): string
{
    $unit = normalizeUnit($unit);

    if (in_array($unit, ['g', 'kg'], true)) {
        return 'weight';
    }

    if (in_array($unit, ['ml', 'l'], true)) {
        return 'volume';
    }

    if ($unit === 'piece') {
        return 'count';
    }

    return 'unknown';
}

function convertToBase(float $quantity, string $unit): float
{
    $unit = normalizeUnit($unit);

    switch ($unit) {
        case 'kg':
            return $quantity * 1000;

        case 'l':
            return $quantity * 1000;

        case 'g':
        case 'ml':
        case 'piece':
            return $quantity;

        default:
            return $quantity;
    }
}

function formatQuantity(float $number): string
{
    if (abs($number - round($number)) < 0.000001) {
        return number_format(round($number), 0, '.', '');
    }

    return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
}

function displayUnit(string $unit): string
{
    $unit = normalizeUnit($unit);

    $labels = [
        'g' => 'g',
        'kg' => 'kg',
        'ml' => 'ml',
        'l' => 'L',
        'piece' => 'pieces'
    ];

    return $labels[$unit] ?? $unit;
}

/*
 * Scale quantities in preparation instructions when an explicit
 * quantity is followed by a recognized ingredient measurement unit.
 * Cooking times and temperatures are not scaled.
 */
function scalePreparationMethod(string $method, float $factor): string
{
    if ($method === '' || abs($factor - 1) < 0.000001) {
        return $method;
    }

    $pattern = '/(?<![\w.])(\d+(?:\.\d+)?)\s*(kilograms?|kilogram|kg|grams?|gram|g|milliliters?|millilitres?|ml|liters?|litres?|l|pieces?|pcs)\b/i';

    return preg_replace_callback(
        $pattern,
        function ($matches) use ($factor) {
            $quantity = (float)$matches[1] * $factor;
            return formatQuantity($quantity) . ' ' . $matches[2];
        },
        $method
    ) ?? $method;
}

$recipeId = filter_input(INPUT_GET, 'recipe_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);

    if ($postedId && $postedId > 0) {
        $recipeId = $postedId;
    }
}

$recipes = [];
$recipe = null;
$errorMessage = '';
$result = null;

try {
    $recipes = $pdo->query(
        "SELECT id, recipe_name, base_yield, base_unit
         FROM recipes
         ORDER BY recipe_name"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Calculator recipe list error: ' . $e->getMessage());
    $errorMessage = 'Unable to load recipes. Please try again.';
}

/* Load the selected recipe. */
if ($recipeId && $recipeId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM recipes
             WHERE id = :id"
        );

        $stmt->execute(['id' => $recipeId]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            $errorMessage = 'Recipe not found. Please select another recipe.';
        }
    } catch (PDOException $e) {
        error_log('Calculator recipe error: ' . $e->getMessage());
        $errorMessage = 'Unable to load the selected recipe.';
    }
}

$quantity = '';
$requestedUnit = '';
$calculatedIngredients = [];
$scaledMethod = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $recipe) {
    if (function_exists('verify_csrf')) {
        verify_csrf();
    }

    $quantity = trim((string)($_POST['quantity'] ?? ''));
    $requestedUnit = normalizeUnit((string)($_POST['unit'] ?? ''));

    if (!is_numeric($quantity) || (float)$quantity <= 0) {
        $errorMessage = 'Please enter a quantity greater than zero.';
    } else {
        $quantity = (float)$quantity;

        $baseYield = (float)($recipe['base_yield'] ?? 0);
        $baseUnit = normalizeUnit((string)($recipe['base_unit'] ?? ''));

        if ($baseYield <= 0 || $baseUnit === '') {
            $errorMessage = 'This recipe does not have a valid base yield or unit.';
        } elseif (
            unitCategory($baseUnit) === 'unknown' ||
            unitCategory($requestedUnit) === 'unknown' ||
            unitCategory($baseUnit) !== unitCategory($requestedUnit)
        ) {
            $errorMessage = 'Please select a unit compatible with this recipe base unit.';
        } else {
            $baseAmount = convertToBase($baseYield, $baseUnit);
            $requestedAmount = convertToBase($quantity, $requestedUnit);

            if ($baseAmount <= 0) {
                $errorMessage = 'The recipe base quantity must be greater than zero.';
            } else {
                $factor = $requestedAmount / $baseAmount;

                /*
                 * Decode ingredient JSON.
                 * Supported example:
                 * [
                 *   {"name":"Rice","quantity":500,"unit":"g"},
                 *   {"name":"Water","quantity":1,"unit":"L"}
                 * ]
                 */
                $rawIngredients = (string)($recipe['ingredients'] ?? '');
                $decodedIngredients = json_decode($rawIngredients, true);

                if (is_array($decodedIngredients)) {
                    foreach ($decodedIngredients as $ingredient) {
                        if (is_array($ingredient)) {
                            $name = (string)(
                                $ingredient['name']
                                ?? $ingredient['name_en']
                                ?? 'Ingredient'
                            );

                            $originalQuantity = $ingredient['quantity'] ?? '';
                            $originalUnit = (string)($ingredient['unit'] ?? '');

                            if ($originalQuantity !== '' && is_numeric($originalQuantity)) {
                                $scaledQuantity = (float)$originalQuantity * $factor;

                                $calculatedIngredients[] = [
                                    'name' => $name,
                                    'quantity' => formatQuantity($scaledQuantity),
                                    'unit' => displayUnit($originalUnit)
                                ];
                            } else {
                                $calculatedIngredients[] = [
                                    'name' => $name,
                                    'quantity' => '',
                                    'unit' => $originalUnit
                                ];
                            }
                        } else {
                            $calculatedIngredients[] = [
                                'name' => (string)$ingredient,
                                'quantity' => '',
                                'unit' => ''
                            ];
                        }
                    }
                } else {
                    /*
                     * Plain-text ingredient support.
                     * Example: Rice - 500 g
                     */
                    $lines = preg_split('/\r\n|\r|\n/', $rawIngredients) ?: [];

                    foreach ($lines as $line) {
                        $line = trim($line);

                        if ($line === '') {
                            continue;
                        }

                        $pattern
