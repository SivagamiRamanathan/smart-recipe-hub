<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function reply(int $status,array $data): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function unitNorm(string $u): string { $u=strtolower(trim($u)); return ['gram'=>'g','grams'=>'g','kilogram'=>'kg','kilograms'=>'kg','l'=>'litre','liter'=>'litre','liters'=>'litre','litres'=>'litre','millilitre'=>'ml','millilitres'=>'ml','milliliter'=>'ml','milliliters'=>'ml'][$u]??$u; }
function unitTypeApi(string $u): string { $u=unitNorm($u); return in_array($u,['g','kg'],true)?'weight':(in_array($u,['ml','litre'],true)?'volume':'other'); }
function toBaseApi(float $q,string $u): float { return in_array(unitNorm($u),['kg','litre'],true)?$q*1000:$q; }
function fmt(float $n): string { if(abs($n-round($n))<0.000001)return (string)(int)round($n); return rtrim(rtrim(number_format($n,3,'.',''),'0'),'.'); }
if (!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)) reply(405,['ok'=>false,'message'=>'Method not allowed']);
$in=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET;
$id=filter_var($in['id']??null,FILTER_VALIDATE_INT); $name=trim((string)($in['recipe_name']??'')); $lang=(string)($in['lang']??'en');
if(!in_array($lang,['en','ta','th'],true))$lang='en';
try {
 if(!$id && $name==='') {
  reply(200,['ok'=>true,'recipes'=>$pdo->query("SELECT id,recipe_name,recipe_name_ta,recipe_name_th,base_yield,base_unit FROM recipes ORDER BY recipe_name")->fetchAll()]);
 }
 if($id){$st=$pdo->prepare('SELECT * FROM recipes WHERE id=?');$st->execute([$id]);}
 else {$st=$pdo->prepare("SELECT * FROM recipes WHERE LOWER(TRIM(recipe_name))=LOWER(TRIM(?)) OR LOWER(TRIM(recipe_name_ta))=LOWER(TRIM(?)) OR LOWER(TRIM(recipe_name_th))=LOWER(TRIM(?)) ORDER BY id DESC LIMIT 1");$st->execute([$name,$name,$name]);}
 $r=$st->fetch(); if(!$r)reply(404,['ok'=>false,'message'=>'Recipe not found in the admin database.']);
 $base=(float)($r['base_yield']??0); $baseUnit=unitNorm((string)($r['base_unit']??'g'));
 $raw=(string)$r['ingredients']; $data=json_decode($raw,true); $ings=[];
 if(is_array($data)){
  foreach($data as $v){if(!is_array($v))continue;$ings[]=['name_en'=>(string)($v['name']??$v['name_en']??''),'name_ta'=>(string)($v['name_ta']??''),'name_th'=>(string)($v['name_th']??''),'quantity'=>isset($v['quantity'])?(float)$v['quantity']:null,'unit'=>unitNorm((string)($v['unit']??''))];}
 } else {
  foreach(preg_split('/\r\n|\r|\n/',$raw)?:[] as $line){
   $line=trim(preg_replace('/^[\s\-•*]+/u','',$line)??$line);if($line==='')continue;
   if(preg_match('~^(.*?)\s*(?:–|—|-|:)?\s*(\d+(?:\.\d+)?|\d+\s*/\s*\d+|½|¼|¾|⅓|⅔)\s*(kg|kilograms?|g|grams?|ml|millilitres?|milliliters?|litres?|liters?|l|tsp|teaspoons?|tbsp|tablespoons?|cups?)\b~iu',$line,$m)){
    $n=trim($m[2]);$map=['½'=>.5,'¼'=>.25,'¾'=>.75,'⅓'=>1/3,'⅔'=>2/3];if(isset($map[$n]))$q=$map[$n];elseif(str_contains($n,'/')){[$a,$b]=array_map('floatval',preg_split('~/~',$n));$q=$b==0?0:$a/$b;}else$q=(float)$n;
    $ings[]=['name_en'=>trim($m[1]),'name_ta'=>'','name_th'=>'','quantity'=>$q,'unit'=>unitNorm($m[3])];
   }else $ings[]=['name_en'=>$line,'name_ta'=>'','name_th'=>'','quantity'=>null,'unit'=>''];
  }
 }
 $qty=trim((string)($in['quantity']??''));$unit=unitNorm((string)($in['unit']??$baseUnit));$factor=1;
 if($qty!==''){
  if(!is_numeric($qty)||(float)$qty<=0||(float)$qty>100000)reply(422,['ok'=>false,'message'=>'Enter a valid positive quantity (maximum 100000).']);
  if($base<=0)reply(422,['ok'=>false,'message'=>'Please set a valid base yield for this recipe in Admin.']);
  if(unitTypeApi($unit)!==unitTypeApi($baseUnit)||unitTypeApi($unit)==='other')reply(422,['ok'=>false,'message'=>'Choose a compatible unit: g/kg for weight or ml/litre for volume.']);
  $factor=toBaseApi((float)$qty,$unit)/toBaseApi($base,$baseUnit);
 }
 foreach($ings as &$v){if($v['quantity']!==null){$v['quantity']=(float)$v['quantity']*$factor;$v['display_quantity']=fmt($v['quantity']).($v['unit']!==''?' '.$v['unit']:'');}else $v['display_quantity']='';}unset($v);
 $nameCol=$lang==='ta'?'recipe_name_ta':($lang==='th'?'recipe_name_th':'recipe_name');
 $methodCol='method_'.$lang;$descCol='description_'.$lang;
 reply(200,['ok'=>true,'recipe'=>[
  'id'=>(int)$r['id'],'recipe_name'=>trim((string)($r[$nameCol]??''))?:$r['recipe_name'],
  'description'=>(string)($r[$descCol]??''),'preparation_method'=>trim((string)($r[$methodCol]??''))?: (string)($r['preparation_method']??''),
  'base_yield'=>$base,'base_unit'=>$baseUnit,'requested_quantity'=>$qty===''?null:(float)$qty,'requested_unit'=>$unit,
  'scaling_factor'=>$factor,'ingredients'=>$ings
 ]]);
} catch(PDOException $e){error_log('Recipe API: '.$e->getMessage());reply(500,['ok'=>false,'message'=>'Unable to load recipe from database.']);}
