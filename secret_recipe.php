<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed.']); exit;
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Please sign in first.']); exit;
}
// Example policy: only staff/admin roles can request the secret recipe.
if (!in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)) {
    http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Your account is not authorized to view secret recipes.']); exit;
}
$pin = (string)($_POST['pin'] ?? '');
$stmt = $pdo->prepare('SELECT id, title, recipe_text, pin_hash FROM secret_recipes WHERE slug = :slug AND active = TRUE');
$stmt->execute(['slug' => 'mango-pickle']);
$recipe = $stmt->fetch();
if (!$recipe || !password_verify($pin, $recipe['pin_hash'])) {
    // For production, add persistent rate limiting and audit logging here.
    http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Invalid PIN or recipe access denied.']); exit;
}
echo json_encode([
  'ok' => true,
  'message' => 'PIN verified. Authorized recipe displayed.',
  'recipe' => $recipe['title'] . "\n\n" . $recipe['recipe_text']
], JSON_UNESCAPED_UNICODE);
