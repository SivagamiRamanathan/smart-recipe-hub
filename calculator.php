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
