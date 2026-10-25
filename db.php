<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* Configure these for your local PostgreSQL installation. Keep credentials private. */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'smart_recipe';
$dbuser = getenv('DB_USER') ?: 'postgres';
$dbpass = getenv('DB_PASS') ?: 'Sivagami@08'; // Put your local password here only if required.

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: '.$e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check PostgreSQL, PDO_PGSQL and db.php settings.');
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.h($_SESSION['csrf_token']).'">'; }
function verify_csrf(): void {
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419); exit('Session expired. Refresh the page and try again.');
    }
}

/* Idempotent schema migration: keeps existing recipe rows and columns. */
$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
 id SERIAL PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL,
 password_hash TEXT NOT NULL, pin_hash TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS recipes (
 id SERIAL PRIMARY KEY, recipe_name VARCHAR(200) NOT NULL,
 ingredients TEXT NOT NULL DEFAULT '', preparation_method TEXT NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$columns = [
 'base_yield'=>"NUMERIC(12,3) NOT NULL DEFAULT 1", 'base_unit'=>"VARCHAR(20) NOT NULL DEFAULT 'g'", 'image_url'=>"TEXT NOT NULL DEFAULT ''",
 'recipe_name_ta'=>"VARCHAR(200) NOT NULL DEFAULT ''", 'recipe_name_th'=>"VARCHAR(200) NOT NULL DEFAULT ''",
 'description_en'=>"TEXT NOT NULL DEFAULT ''", 'description_ta'=>"TEXT NOT NULL DEFAULT ''", 'description_th'=>"TEXT NOT NULL DEFAULT ''",
 'method_en'=>"TEXT NOT NULL DEFAULT ''", 'method_ta'=>"TEXT NOT NULL DEFAULT ''", 'method_th'=>"TEXT NOT NULL DEFAULT ''"
];
foreach ($columns as $col=>$def) $pdo->exec("ALTER TABLE recipes ADD COLUMN IF NOT EXISTS $col $def");
$pdo->exec("CREATE TABLE IF NOT EXISTS ingredients_master (
 id SERIAL PRIMARY KEY, name_en VARCHAR(150) NOT NULL, name_ta VARCHAR(150) NOT NULL DEFAULT '',
 name_th VARCHAR(150) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS units_master (
 id SERIAL PRIMARY KEY, unit_name VARCHAR(80) NOT NULL, unit_symbol VARCHAR(20) NOT NULL,
 unit_type VARCHAR(20) NOT NULL CHECK (unit_type IN ('weight','volume','count','other')),
 factor_to_base NUMERIC(14,6), base_unit VARCHAR(20) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS ingredients_master_en_unique ON ingredients_master (LOWER(name_en))");
$pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS units_master_symbol_unique ON units_master (LOWER(unit_symbol))");
$seedI = [
 ['Urad Dal','உளுந்து','Ulundhu'],['Chana Dal','கடலைப் பருப்பு','Kadalai Paruppu'],
 ['Sesame Seeds','எள்','Ellu'],['Dry Red Chillies','காய்ந்த மிளகாய்','Kaanja Milagai'],
 ['Asafoetida (Hing)','பெருங்காயம்','Perungayam'],['Salt','உப்பு','Uppu'],
 ['Curry Leaves','கறிவேப்பிலை','Karuveppilai'],['Moringa Leaves','முருங்கைக் கீரை','Murungai Keerai'],
 ['Turmeric Powder','மஞ்சள் தூள்','Manjal Thool'],['Cumin Seeds','சீரகம்','Seeragam'],
 ['Black Pepper','மிளகு','Milagu'],['Rice','அரிசி','Arisi'],['Water','தண்ணீர்','Thanneer']
];
$s = $pdo->prepare("INSERT INTO ingredients_master(name_en,name_ta,name_th) VALUES(?,?,?) ON CONFLICT DO NOTHING");
foreach ($seedI as $i) $s->execute($i);
$seedU = [
 ['Gram','g','weight',1,'g'],['Kilogram','kg','weight',1000,'g'],
 ['Millilitre','ml','volume',1,'ml'],['Litre','litre','volume',1000,'ml'],
 ['Piece','piece','count',null,'piece'],['Teaspoon','tsp','volume',5,'ml'],
 ['Tablespoon','tbsp','volume',15,'ml'],['Cup','cup','volume',240,'ml']
];
$s = $pdo->prepare("INSERT INTO units_master(unit_name,unit_symbol,unit_type,factor_to_base,base_unit) VALUES(?,?,?,?,?) ON CONFLICT DO NOTHING");
foreach ($seedU as $u) $s->execute($u);
