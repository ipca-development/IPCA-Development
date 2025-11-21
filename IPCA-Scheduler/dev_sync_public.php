<?php
/**
 * dev_sync_public.php — read-only live snapshot for ChatGPT
 * PHP 5.3 compatible
 *
 * SECURITY:
 * - Require a strong token via ?token=...
 * - Optionally, restrict IPs (see $IP_WHITELIST)
 * - Deploy only on dev/staging; remove when not needed.
 */

///// CONFIG ///////////////////////////////////////////////////////////////
$ACCESS_TOKEN   = 'a3b7e62fdf1c4a8f9c2b746f03d95e8eaac72e14e70c4f5b9dfedbc2a48e93f2'; // required in every request
$IP_WHITELIST   = array(); // e.g. array('1.2.3.4'); leave empty to disable
$DB_DSN_FN      = 'get_mysqli'; // optional factory that returns mysqli; else manual connect below

// If you prefer manual DB connect (when $DB_BOOTSTRAP has no helper), fill in:
$DB_HOST = 'mysql056.hosting.combell.com';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$DB_NAME = 'ID127947_egl1';

// Whitelisted roots to expose (relative to this file)
$ALLOW_PATHS = array(
  'schedule.php',
  'availability.php',
  'availability_api.php',
  'schedule_cohorts.inc.php',
  'cohorts.php',
  'reminders.php',
  'includes',       // expose your helper dir
  'public/js',      // if your modal JS lives here
  'public/css'
);

// Files/dirs to always exclude (regex, case-insensitive)
$EXCLUDE_PATTERNS = array(
  '#(^|/)\.(git|svn)(/|$)#i',
  '#(^|/)vendor(/|$)#i',
  '#(^|/)node_modules(/|$)#i',
  '#(^|/)storage(/|$)#i',
  '#(^|/)cache(/|$)#i',
  '#(^|/)logs?(/|$)#i',
  '#\.env#i',
  '#composer\.(json|lock)#i'
);

// Filenames that may contain secrets; we’ll redact matches for (password|pass|secret)
$REDACT_IF_MATCH = array(
  '#config\.php$#i',
  '#settings\.php$#i',
  '#env\.php$#i'
);

// Optional app version file
$VERSION_FILE = __DIR__.'/version.php'; // define('APP_VERSION','1.2.3'), define('DB_SCHEMA_VERSION','...')

///////////////////////////////////////////////////////////////////////////

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

function deny($code=403, $msg='Forbidden'){ http_response_code($code); echo $msg; exit; }
function want($key){ return isset($_GET[$key]) ? $_GET[$key] : ''; }
function check_token($expect){
  $tok = want('token');
  if(!$tok || !hash_equals_53($expect, $tok)) deny(401,'Unauthorized');
}
function hash_equals_53($a,$b){ return $a === $b && strlen($a) === strlen($b); }

if (!empty($IP_WHITELIST) && !in_array($_SERVER['REMOTE_ADDR'], $IP_WHITELIST)) deny();

check_token($ACCESS_TOKEN);

$action = want('action') ?: 'manifest';

// Build mysqli
$mysqli = null;
if (file_exists($DB_BOOTSTRAP)) {
  require_once $DB_BOOTSTRAP;
  if (function_exists($DB_DSN_FN)) {
    $mysqli = call_user_func($DB_DSN_FN);
  }
}
if (!$mysqli) {
  $mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
}
if ($mysqli && $mysqli->connect_errno) $mysqli = null;

function list_allowed_paths($root, $allow, $excl){
  $items = array();
  foreach ($allow as $rel) {
    $path = realpath($root.'/'.$rel);
    if (!$path) continue;
    $items = array_merge($items, scan_path($path, $root, $excl));
  }
  return $items;
}
function scan_path($path, $root, $excl){
  $out = array();
  if (is_dir($path)) {
    $it = opendir($path);
    if(!$it) return $out;
    while(false!==($f=readdir($it))){
      if($f==='.'||$f==='..') continue;
      $p = $path.'/'.$f;
      if (excluded($p,$excl,$root)) continue;
      $out = array_merge($out, scan_path($p,$root,$excl));
    }
    closedir($it);
  } elseif (is_file($path)) {
    $rel = ltrim(substr($path, strlen(realpath($root))), DIRECTORY_SEPARATOR);
    $out[] = $rel;
  }
  return $out;
}
function excluded($path,$patterns,$root){
  $rel = ltrim(substr($path, strlen(realpath($root))), DIRECTORY_SEPARATOR);
  foreach($patterns as $re){
    if (preg_match($re, $rel)) return true;
  }
  return false;
}
function file_meta($abs){
  return array(
    'size' => filesize($abs),
    'mtime'=> filemtime($abs),
    'sha1' => sha1_file($abs),
  );
}
function safe_read($abs, $rel, $redactRules){
  $data = @file_get_contents($abs);
  foreach($redactRules as $re){
    if (preg_match($re, $rel)) {
      // redact simple key= or 'key' => 'value' patterns
      $data = preg_replace('/(?i)(password|pass|secret)\s*[:=]\s*[\'"][^\'"]*[\'"]/', '$1: "[REDACTED]"', $data);
      $data = preg_replace('/(?i)(password|pass|secret)\s*=>\s*[\'"][^\'"]*[\'"]/', '$1 => "[REDACTED]"', $data);
    }
  }
  return $data;
}
function get_versions($verfile){
  $out = array('APP_VERSION'=>null,'DB_SCHEMA_VERSION'=>null,'PHP_VERSION'=>PHP_VERSION);
  if (file_exists($verfile)) {
    include $verfile;
    if (defined('APP_VERSION')) $out['APP_VERSION'] = APP_VERSION;
    if (defined('DB_SCHEMA_VERSION')) $out['DB_SCHEMA_VERSION'] = DB_SCHEMA_VERSION;
  }
  return $out;
}

function schema_dump($mysqli){
  if (!$mysqli) return array('error'=>'no-db-connection');
  $tables = array();
  $res = $mysqli->query("SHOW TABLES");
  if(!$res) return array('error'=>$mysqli->error);
  while($row = $res->fetch_array()){
    $t = $row[0];
    $cr = $mysqli->query("SHOW CREATE TABLE `".$mysqli->real_escape_string($t)."`");
    if($cr){
      $row2 = $cr->fetch_assoc();
      $tables[$t] = $row2['Create Table'];
    }
  }
  ksort($tables);
  // fingerprint
  $concat = '';
  foreach($tables as $t=>$sql){ $concat .= $t."\n".$sql."\n\n"; }
  return array('tables'=>$tables,'fingerprint'=>sha1($concat));
}

$root = __DIR__;
$files = list_allowed_paths($root, $ALLOW_PATHS, $EXCLUDE_PATTERNS);
sort($files);

if ($action === 'manifest') {
  $meta = array();
  foreach($files as $rel){
    $abs = realpath($root.'/'.$rel);
    $meta[$rel] = file_meta($abs);
  }
  $schema = schema_dump($mysqli);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array(
    'versions' => get_versions($VERSION_FILE),
    'schema'   => array(
      'fingerprint' => isset($schema['fingerprint']) ? $schema['fingerprint'] : null,
      'error'       => isset($schema['error']) ? $schema['error'] : null,
      'table_count' => isset($schema['tables']) ? count($schema['tables']) : 0
    ),
    'files'    => $meta
  ));
  exit;
}

if ($action === 'schema') {
  $schema = schema_dump($mysqli);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($schema);
  exit;
}

if ($action === 'files') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(array('files'=>$files));
  exit;
}

if ($action === 'get') {
  $rel = want('path');
  if (!$rel) deny(400,'Missing path');
  // normalize
  $rel = ltrim(str_replace('\\','/',$rel),'/');
  if (!in_array($rel, $files, true)) deny(404,'Not allowed');
  $abs = realpath($root.'/'.$rel);
  if (!$abs || !is_file($abs)) deny(404,'Not found');
  $raw = safe_read($abs, $rel, $REDACT_IF_MATCH);
  header('Content-Type: text/plain; charset=utf-8');
  echo $raw;
  exit;
}

deny(400,'Unknown action');