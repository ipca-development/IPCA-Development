<?php
/*
  chat_router_ai.php — NL router → autodraft_v0_egl1.php
  v1.1 (local include bridge + curl fallback) — PHP 5.3 compatible
*/

ini_set('display_errors','1'); error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

// ---------- CONFIG ----------
$DEFAULT_TZ = 'America/Los_Angeles';
date_default_timezone_set($DEFAULT_TZ);

// If autodraft sits in the same directory, this is correct:
$AUTODRAFT_PATH = __DIR__ . '/autodraft_v0_egl1.php';

// In case you also want remote fallback:
$AUTODRAFT_URL  = cur_base_url() . 'autodraft_v0_egl1.php';

// ---------- ENTRY ----------
$ask = isset($_REQUEST['ask']) ? trim($_REQUEST['ask']) : '';
if ($ask === '') {
  echo json_encode(array(
    'ok'=>false,
    'error'=>'missing_ask',
    'hint'=>'Pass ask=... (try: ask=schedule sim tomorrow 10-12 cohort 2 device 25 instructor 1 route TRM)'
  ));
  exit;
}

$ask_norm = preg_replace('/\s+/', ' ', strtolower($ask));
$intent   = detect_intent($ask_norm);

// Parse params
$params = array();
$params['dry'] = (stripos($ask_norm,'commit')===false && stripos($ask_norm,'finalize')===false && stripos($ask_norm,'confirm')===false) ? '1' : '0';

$tm = parse_type_and_mix($ask_norm);
$params['type'] = $tm['type'];
if ($tm['mix'] !== '') $params['mix'] = $tm['mix'];

$win = parse_window($ask_norm);
foreach ($win as $k=>$v) $params[$k] = $v;

$sc  = parse_students_and_cohort($ask_norm);
foreach ($sc as $k=>$v) if ($v !== null) $params[$k] = $v;

$idr = parse_instructor_device_route($ask_norm);
foreach ($idr as $k=>$v) if ($v !== null) $params[$k] = $v;

$grp = parse_grouping_pack($ask_norm);
foreach ($grp as $k=>$v) if ($v !== null) $params[$k] = $v;

$flags = parse_special_flags($ask_norm);
foreach ($flags as $k=>$v) if ($v !== null) $params[$k] = $v;

// SIM pack default
if ($params['type']==='SIM' || (isset($params['mix']) && stripos($params['mix'],'sim')!==false)) {
  if (!isset($params['sim_pack'])) $params['sim_pack'] = isset($params['pack_size']) ? $params['pack_size'] : 1;
}

// CANCEL flows
if ($intent === 'cancel_preview') {
  $c = parse_cancel_preview($ask_norm);
  if (!$c['device_id'] || !$c['date']) {
    echo json_encode(array('ok'=>false,'error'=>'cancel_preview_parse','hint'=>'e.g. "cancel preview device 25 on 2025-11-10"')); exit;
  }
  $q = array('cancel'=>'preview','device_id'=>$c['device_id'],'date'=>$c['date'],'dry'=>$params['dry']);
  echo call_autodraft($AUTODRAFT_PATH, $AUTODRAFT_URL, $q, /*prefer_local=*/true, /*router_echo=*/array('intent'=>$intent,'understood'=>$q));
  exit;
}
if ($intent === 'cancel_id') {
  $cid = parse_cancel_id($ask_norm);
  if (!$cid) { echo json_encode(array('ok'=>false,'error'=>'cancel_id_parse','hint'=>'e.g. "cancel draft id 123"')); exit; }
  $q = array('cancel'=>'id','id'=>$cid,'dry'=>$params['dry']);
  echo call_autodraft($AUTODRAFT_PATH, $AUTODRAFT_URL, $q, true, array('intent'=>$intent,'understood'=>$q));
  exit;
}

// Default: schedule
$q = build_autodraft_query($params);
echo call_autodraft($AUTODRAFT_PATH, $AUTODRAFT_URL, $q, /*prefer_local=*/true, /*router_echo=*/array('intent'=>$intent,'understood'=>$q));
exit;

// ============================== CORE ===============================

function call_autodraft($local_path, $remote_url, $params, $prefer_local, $router_echo) {
  // A) LOCAL INCLUDE BRIDGE (no networking)
  if ($prefer_local && is_readable($local_path)) {
    $out = include_bridge($local_path, $params);
    if ($out !== false) return enrich($out, $router_echo);
  }

  // B) cURL fallback
  $raw = curl_get($remote_url, $params);
  if ($raw !== false) return enrich($raw, $router_echo);

  // C) file_get_contents last resort
  $url = $remote_url . '?' . http_build_query($params);
  $ctx = stream_context_create(array('http'=>array('method'=>'GET','timeout'=>15)));
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw !== false) return enrich($raw, $router_echo);

  return json_encode(array('ok'=>false,'error'=>'autodraft_unreachable','url'=>$url,'_router_echo'=>$router_echo));
}

function include_bridge($local_path, $params) {
  // Run autodraft in-process, feeding it $_GET, capturing echo
  $old_get = $_GET;
  $_GET = $params;
  ob_start();
  try {
    include $local_path; // expects to echo JSON and exit or fall through
  } catch (Exception $e) {
    ob_end_clean();
    $_GET = $old_get;
    return false;
  }
  $buf = ob_get_clean();
  $_GET = $old_get;
  return $buf;
}

function curl_get($base, $params) {
  if (!function_exists('curl_init')) return false;
  $ch = curl_init();
  $url = $base . '?' . http_build_query($params);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  // If your host has strict certs, you can disable verify at your own risk:
  // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  $res = curl_exec($ch);
  if ($res === false) { curl_close($ch); return false; }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code >= 200 && $code < 300) return $res;
  return false;
}

function enrich($raw, $echo) {
  $d = json_decode($raw, true);
  if (is_array($d)) {
    $d['_router_echo'] = $echo;
    return json_encode($d);
  }
  // if downstream didn’t return JSON, wrap it
  return json_encode(array('ok'=>false,'error'=>'downstream_not_json','raw'=>$raw,'_router_echo'=>$echo));
}

// ============================== PARSERS ===============================

function detect_intent($t) {
  if (strpos($t, 'cancel preview') !== false) return 'cancel_preview';
  if (preg_match('/cancel\s+(draft\s+)?id\s+\d+/', $t)) return 'cancel_id';
  return 'schedule';
}

function parse_type_and_mix($t) {
  $out = array('type'=>'SIM','mix'=>'');
  if (strpos($t,'briefing')!==false || preg_match('/\bbrief\b/',$t)) $out['type'] = 'BRIEF';
  if (strpos($t,'flight')!==false) $out['type'] = 'FLIGHT';
  if (strpos($t,'mix')!==false) {
    $out['type'] = 'MIX';
    $hasBrief = (strpos($t,'brief')!==false || strpos($t,'briefing')!==false);
    $hasSim   = (strpos($t,'sim')!==false || strpos($t,'simulator')!==false || strpos($t,'fnpt')!==false || strpos($t,'sab')!==false);
    $hasFly   = (strpos($t,'flight')!==false);
    $parts = array(); if ($hasBrief) $parts[]='BRIEF'; if ($hasSim) $parts[]='SIM'; if ($hasFly) $parts[]='FLIGHT';
    $out['mix'] = implode(' ', $parts);
  }
  return $out;
}

function parse_window($t) {
  $out = array('date_from'=>date('Y-m-d'), 'days'=>'1', 'slot'=>null);

  if (strpos($t,'tomorrow')!==false) $out['date_from'] = date('Y-m-d', time()+86400);
  elseif (preg_match('/next\s+(mon|tue|wed|thu|fri|sat|sun)/',$t,$m)) $out['date_from']=next_dow_iso($m[1]);
  elseif (preg_match('/\b(20\d\d-\d\d-\d\d)\b/',$t,$m)) $out['date_from']=$m[1];

  if (preg_match('/days?\s*=\s*(\d+)/',$t,$m)) $out['days']=$m[1];
  elseif (preg_match('/over\s+(\d+)\s+days?/',$t,$m)) $out['days']=$m[1];
  else $out['days']='1';

  if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*-\s*(\d{1,2})(?::(\d{2}))?\b/',$t,$m)) {
    $h1=intval($m[1]); $m1=isset($m[2])?intval($m[2]):0; $h2=intval($m[3]); $m2=isset($m[4])?intval($m[4]):0;
    $out['slot']=sprintf('%02d:%02d-%02d:%02d',$h1,$m1,$h2,$m2);
  } elseif (preg_match('/\b(\d{1,2})\s*-\s*(\d{1,2})\b/',$t,$m)) {
    $out['slot']=sprintf('%02d:00-%02d:00',intval($m[1]),intval($m[2]));
  }
  return $out;
}

function parse_students_and_cohort($t) {
  $out = array('student_ids'=>null,'cohort_id'=>null);
  if (preg_match('/cohort\s+(\d+)/',$t,$m)) $out['cohort_id']=$m[1];
  if (preg_match('/students?\s+([\d,\s]+)/',$t,$m)) {
    $ids = preg_replace('/\s+/','',trim($m[1])); $ids=trim($ids,','); if ($ids!=='') $out['student_ids']=$ids;
  }
  if (!$out['student_ids'] && preg_match('/for\s+([\d,\s]{3,})/',$t,$m)) {
    $ids = preg_replace('/[^\d,]/','',$m[1]); $ids=trim($ids,','); if ($ids!=='') $out['student_ids']=$ids;
  }
  return $out;
}

function parse_instructor_device_route($t) {
  $out = array('instructor_id'=>null,'device_id'=>null,'route'=>null);
  if (preg_match('/instructor\s+(\d+)/',$t,$m)) $out['instructor_id']=$m[1];
  if (preg_match('/device\s+(\d+)/',$t,$m)) $out['device_id']=$m[1];
  if (preg_match('/route\s+([a-z]{3,5})/i',$t,$m)) $out['route']=strtoupper($m[1]);
  return $out;
}

function parse_grouping_pack($t) {
  $out = array('group_brief'=>null,'group_sim'=>null,'pack_size'=>null,'solo_flight'=>null,'backseat'=>null);
  if (strpos($t,'group briefing')!==false || strpos($t,'group brief')!==false) $out['group_brief']=1;
  if (strpos($t,'no group briefing')!==false) $out['group_brief']=0;
  if (strpos($t,'group sim')!==false || strpos($t,'group simulator')!==false) $out['group_sim']=1;
  if (strpos($t,'no group sim')!==false) $out['group_sim']=0;
  if (preg_match('/pack\s+(\d+)/',$t,$m)) $out['pack_size']=$m[1];
  if (strpos($t,'solo flight')!==false) $out['solo_flight']=1;
  if (preg_match('/backseat\s+(\d+)/',$t,$m)) $out['backseat']=$m[1];
  return $out;
}

function parse_special_flags($t) {
  $out = array('brief_ahead'=>null,'ignore_gates'=>null,'require_device_instructor'=>null);
  if (strpos($t,'brief ahead')!==false) $out['brief_ahead']=1;
  if (strpos($t,'ignore gates')!==false) $out['ignore_gates']=1;
  if (strpos($t,'require device instructor')!==false) $out['require_device_instructor']=1;
  return $out;
}

function parse_cancel_preview($t) {
  $out = array('device_id'=>null,'date'=>null);
  if (preg_match('/device\s+(\d+)/',$t,$m)) $out['device_id']=$m[1];
  if (preg_match('/on\s+(20\d\d-\d\d-\d\d)/',$t,$m)) $out['date']=$m[1];
  return $out;
}
function parse_cancel_id($t) {
  return preg_match('/cancel\s+(draft\s+)?id\s+(\d+)/',$t,$m) ? $m[2] : null;
}

function build_autodraft_query($p) {
  $allowed = array(
    'type','mix','date_from','days','slot','student_ids','cohort_id',
    'device_id','instructor_id','route','dry','group_brief','group_sim',
    'pack_size','solo_flight','backseat','sim_pack','brief_ahead','ignore_gates',
    'require_device_instructor'
  );
  $q = array();
  foreach ($allowed as $k) if (isset($p[$k]) && $p[$k] !== null && $p[$k] !== '') $q[$k] = $p[$k];
  if (!isset($q['dry'])) $q['dry']='1';
  if (!isset($q['days'])) $q['days']='1';
  if (!isset($q['type'])) $q['type']='SIM';
  if (!isset($q['group_brief'])) $q['group_brief']=1;
  if (!isset($q['group_sim']))   $q['group_sim']=0;
  return $q;
}

// ---------------------- date helpers ----------------------
function next_dow_iso($abbr) {
  $map = array('sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6);
  $abbr = substr(strtolower($abbr),0,3);
  if (!isset($map[$abbr])) return date('Y-m-d');
  $target=$map[$abbr]; $nowDow=intval(date('w'));
  $add = ($target-$nowDow+7)%7; if ($add==0) $add=7;
  return date('Y-m-d', time()+86400*$add);
}
function cur_base_url() {
  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https://' : 'http://';
  $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
  $uri   = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
  $dir   = preg_replace('#/[^/]*$#','/',$uri);
  return $proto.$host.$dir;
}