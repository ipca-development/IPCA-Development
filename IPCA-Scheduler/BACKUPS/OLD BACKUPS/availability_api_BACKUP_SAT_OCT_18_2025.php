<?php
// ===================================================================
// availability_api.php  —  PHP 5.3 compatible
// Returns each user’s available/unavailable periods for a given date
// Uses your existing tables: user_availability_mode & user_availability_rules
// ===================================================================

ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// ---- DB ----
$DB_HOST='mysql056.hosting.combell.com';
$DB_NAME='ID127947_egl1';
$DB_USER='ID127947_egl1';
$DB_PASS='Plane123';
$TZ='America/Los_Angeles';
date_default_timezone_set($TZ);

function db(){
  static $pdo=null,$init=false; global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS;
  if($init) return $pdo; $init=true;
  try{
    $pdo=new PDO('mysql:host='.$DB_HOST.';dbname='.$DB_NAME,$DB_USER,$DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $pdo->exec('SET NAMES utf8');
  }catch(Exception $e){ $pdo=null; }
  return $pdo;
}
function q($sql,$a=array()){ $st=db()->prepare($sql); $st->execute($a); return $st; }
function jexit($a){ echo json_encode($a); exit; }
function safeDate($d){ return preg_match('/^\d{4}-\d{2}-\d{2}$/',$d)?$d:date('Y-m-d'); }

// ---- Input ----
$date = isset($_GET['date']) ? safeDate($_GET['date']) : date('Y-m-d');
$ts = strtotime($date.' 12:00:00');
$dowName = date('D',$ts); // Mon..Sun

try{
  // ---- fetch all users who have any rules or mode ----
  $users = q('SELECT DISTINCT m.user_id FROM user_availability_mode m
              UNION
              SELECT DISTINCT r.user_id FROM user_availability_rules r')->fetchAll();
  if(!$users){ jexit(array('ok'=>true,'date'=>$date,'data'=>new stdClass())); }

  $data=array();
  foreach($users as $u){
    $uid=(int)$u['user_id'];

    // mode
    $mrow=q('SELECT mode FROM user_availability_mode WHERE user_id=?',array($uid))->fetch();
    $mode=$mrow?$mrow['mode']:'unavailability';

    // rules for this day (either repeating or date-bounded)
    $rules=q('SELECT * FROM user_availability_rules
              WHERE user_id=?
                AND day_of_week=? 
                AND (start_date IS NULL OR start_date<=?)
                AND (end_date IS NULL OR end_date>=?)',
              array($uid,$dowName,$date,$date))->fetchAll();

    $blocks=array();
    foreach($rules as $r){
      $blocks[]=array(
        'start'=>$r['start_time'],
        'end'=>$r['end_time'],
        'note'=>$r['note']
      );
    }

    $data[$uid]=array(
      'mode'=>$mode,
      'rules'=>$blocks
    );
  }

  jexit(array('ok'=>true,'date'=>$date,'data'=>$data));
}catch(Exception $e){
  jexit(array('ok'=>false,'error'=>$e->getMessage()));
}
?>