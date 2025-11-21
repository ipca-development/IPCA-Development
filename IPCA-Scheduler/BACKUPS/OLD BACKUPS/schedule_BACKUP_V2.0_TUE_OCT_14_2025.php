<?php

// ===================================================================
// S-01 — PHP: Header, Config, DB setup, column maps
// ===================================================================

ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

/* --------- CONFIG --------- */
$DB_HOST = 'mysql056.hosting.combell.com';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$TZ      = 'America/Los_Angeles';
date_default_timezone_set($TZ);

// ===================================================================
// S-01A — PHP: Page Router for Reminders
// ===================================================================

$PAGE = isset($_GET['page']) ? $_GET['page'] : 'schedule';
if ($PAGE === 'reminders') {
    include __DIR__ . '/reminders.php';
    exit;
}

/* Time window (?hstart=&hend= overrides) */
$H_START = isset($_GET['hstart']) ? max(0, min(23, (int)$_GET['hstart'])) : 5;
$H_END   = isset($_GET['hend'])   ? max($H_START+1, min(24, (int)$_GET['hend'])) : 23;

/* Left column width */
$LABEL_W = 240;

/* Location (Thermal, CA) */
$LOC_NAME = 'SoCal Pilot Center – California (#SPCS024M)';
$LOC_LAT  = 33.6409;
$LOC_LON  = -116.1597;

/* Column maps (match your schema) */
$COLMAP_DEVICES = array(
  'table'       => 'devices',
  'id'          => 'dev_id',
  'name'        => 'dev_name',
  'type'        => 'dev_type',       // AIRCRAFT / SIMULATOR / BRIEFING / AVP / OFFICE
  'model'       => 'dev_sort',       // shown in brackets
  'maint_type'  => 'dev_maint_type',
  'maint_next'  => 'dev_maint_next', // INT epoch
  'visible'     => 'dev_vis',        // Y/N
  'active'      => 'dev_active',     // YES/NO
  'rate'        => 'dev_rate',
  'location'    => 'dev_location',   // US/BE
  'order'       => 'dev_order'       // optional
);

$COLMAP_USERS = array(
  'table'     => 'users',
  'id'        => 'userid',
  'lname'     => 'naam',         // last name
  'fname'     => 'voornaam',     // first name
  'role'      => 'type',         // INSTRUCTOR / ADMIN / ...
  'active_to' => 'actief_tot',   // DATE, must be >= today (no 0000-00-00)
  'work_loc'  => 'work_location' // used only for staff filtering
);

/* Programs + program memberships (fixed column names) */
$COLMAP_PROGRAMS = array(
  'table'    => 'programs',
  'id'       => 'pr_id',
  'name'     => 'pr_name',
  'active'   => 'pr_active',   // Y/YES/1/TRUE/T means active
  'location' => 'pr_location'  // 'US','ALL', etc.
);
$COLMAP_PU = array(
  'table'   => 'programs_users',
  'user'    => 'pu_user',
  'program' => 'pu_program'
);

/* Programs (has optional order) */
$PROGRAMS = array(
  'table'    => 'programs',
  'id'       => 'pr_id',
  'name'     => 'pr_name',
  'active'   => 'pr_active',
  'location' => 'pr_location',
  'order'    => 'pr_order'   // optional INT
);

/* Scenarios flexible mapping */
$SCEN = array(
  'table'       => 'scenarios',
  'type'        => 'sc_type',          // FLIGHT / FNPT / LB
  'code'        => 'sc_code',
  'name'        => 'sc_name',
  'program'     => 'sc_program',       // FK to programs.pr_id
  'order'       => 'sc_order',         // scenario order (fallback)
  'phase_order' => 'sc_phase_order',   // optional INT
  'stage_order' => 'sc_stage_order'    // optional INT
);

/* programs / programs_users can vary; try common patterns */
$TBL_PROGRAMS = 'programs';
$PROGRAM_COL_CANDIDATES = array(
  array('id'=>'program_id','name'=>'program_name'),
  array('id'=>'id','name'=>'name'),
  array('id'=>'id','name'=>'program'),
  array('id'=>'pgm_id','name'=>'pgm_name')
);

$TBL_PU = 'programs_users';
$PU_CANDIDATES = array(
  array('user'=>'user_id','program'=>'program_id'),
  array('user'=>'userid','program'=>'program_id'),
  array('user'=>'pu_user_id','program'=>'pu_program_id'),
  array('user'=>'users_id','program'=>'programs_id'),
  array('user'=>'user_id','program'=>'pr_id'),
  array('user'=>'userid','program'=>'pr_id'),
  array('user'=>'pu_user_id','program'=>'pr_id'),
  array('user'=>'users_id','program'=>'pr_id')
);

/* Reservation tables (already created) */
$TBL_RES  = 'reservations';
$TBL_RS   = 'reservation_students';
$TBL_AUD  = 'reservation_audit';


// ===================================================================
// S-02 — PHP: DB helper functions
// ===================================================================

/* --------- DB helpers (PHP 5.3 safe) --------- */
function db(){
  static $pdoInit=false,$pdo=null; global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS;
  if($pdoInit) return $pdo; $pdoInit=true;
  try{
    $pdo=new PDO('mysql:host='.$DB_HOST.';dbname='.$DB_NAME, $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('SET NAMES utf8');
  }catch(Exception $e){ $pdo=null; }
  return $pdo;
}
function q($sql,$args=array()){ $pdo=db(); if(!$pdo) throw new Exception('DB unavailable'); $st=$pdo->prepare($sql); $st->execute($args); return $st; }
function jexit($arr){ if(!headers_sent()){ header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); } echo json_encode($arr); exit; }
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function day_bounds($date){ $d=date('Y-m-d', strtotime($date)); return array("$d 00:00:00","$d 23:59:59"); }
function make_dt($d,$t){ $d=trim($d); $t=trim($t); if($d==''||$t=='') return null; if(strlen($t)==5) $t.=':00'; return $d.' '.$t; }

/* --------- helpers --------- */
function scenario_kind_from_type($t){
  $s=strtolower(trim($t));
  if($s==='flight training' || $s==='faa practical exam' || $s==='easa practical exam') return 'FLIGHT';
  if($s==='simulator') return 'FNPT';
  if($s==='briefing' || $s==='ar briefing') return 'LB';
  return ''; // none
}


// ===================================================================
// S-03 — PHP: API router entry
// ===================================================================

/* --------- API --------- */
if(isset($_GET['api'])){
  $api=$_GET['api'];

  /* JSON-SAFE: silence notices/warnings for API responses so fetch() gets clean JSON */
  @ini_set('display_errors','0');
  @error_reporting(E_ERROR | E_PARSE);

	
	
	
	
  // ===================================================================
  // S-04 — PHP: API list_day
  // ===================================================================

  /* Day payload (devices, staff, reservations) */
  if($api==='list_day'){
    $date = isset($_GET['date'])? $_GET['date'] : date('Y-m-d');
    list($start,$end)=day_bounds($date);
    $debug = array();

    /* DEVICES — location must be exactly US (case/space proof) */
    $d = $COLMAP_DEVICES;
    $selD =
      ''.$d['id'].'           AS dev_id,
       '.$d['name'].'         AS dev_name,
       '.$d['type'].'         AS dev_type,
       '.$d['model'].'        AS dev_model,
       '.$d['maint_type'].'   AS dev_maint_type,
       '.$d['maint_next'].'   AS dev_maint_next,
       '.$d['visible'].'      AS dev_vis,
       '.$d['active'].'       AS dev_active,
       '.$d['rate'].'         AS dev_rate,
       '.$d['location'].'     AS dev_location,
       '.$d['order'].'        AS dev_order';

    $devices=array();
    try{
      $devSQL='SELECT '.$selD.' FROM '.$d['table'].'
               WHERE TRIM(UPPER('.$d['visible'].'))  IN (\'Y\',\'YES\',\'1\',\'TRUE\',\'T\')
                 AND TRIM(UPPER('.$d['active'].'))   IN (\'Y\',\'YES\',\'1\',\'TRUE\',\'T\')
                 AND TRIM(UPPER('.$d['location'].')) = \'US\'
               ORDER BY '.$d['order'].' IS NULL, '.$d['order'].' ASC, '.$d['type'].' ASC, '.$d['name'].' ASC';
      $devices=q($devSQL)->fetchAll();
      $debug['devices_sql']=$devSQL;
      $debug['devices_count']=count($devices);
    }catch(Exception $e){
      $devices=array();
      $debug['devices_error']=$e->getMessage();
    }

    /* normalize reminder */
    $nowTs=time();
    for($i=0;$i<count($devices);$i++){
      $ts=(int)$devices[$i]['dev_maint_next'];
      $devices[$i]['dev_due_iso']= $ts>0 ? date('Y-m-d H:i:s',$ts) : null;
      $devices[$i]['dev_days_left']=$ts>0 ? (int)floor(($ts-$nowTs)/86400) : null;
    }

    /* STAFF — roles INSTRUCTOR/ADMIN, active today or future (zero date excluded), work_location exactly US */
    $u=$COLMAP_USERS;
    $selU=''.$u['id'].' AS id, '.$u['fname'].' AS first_name, '.$u['lname'].' AS last_name, '.$u['role'].' AS role,
           '.$u['active_to'].' AS active_to, '.$u['work_loc'].' AS work_location';
    $staff=array();
    try{
      $userSQL='SELECT '.$selU.' FROM '.$u['table'].'
               WHERE LOWER('.$u['role'].') IN (\'instructor\',\'admin\')
                 AND '.$u['active_to'].' <> \'0000-00-00\'
                 AND '.$u['active_to'].' >= CURDATE()
                 AND TRIM(UPPER('.$u['work_loc'].')) = \'US\'
               ORDER BY '.$u['fname'].' ASC, '.$u['lname'].' ASC';
      $staff=q($userSQL)->fetchAll();
      $debug['staff_sql']=$userSQL;
      $debug['staff_count']=count($staff);
    }catch(Exception $ex){
      $staff=array();
      $debug['staff_error']=$ex->getMessage();
    }

    /* Reservations for the day (unchanged) */
    $resRows=array(); $resStudents=array();
    try{
      $sql = 'SELECT r.res_id, r.res_type, r.start_dt, r.end_dt, r.device_id, r.instructor_user_id,
                     r.mission_code, r.mission_name, r.route, r.status, r.color_hint,
                     d.'.$d['name'].' AS dev_name, d.'.$d['type'].' AS dev_type, d.'.$d['model'].' AS dev_sort,
                     iu.'.$u['fname'].' AS instr_first, iu.'.$u['lname'].' AS instr_last
              FROM '.$GLOBALS['TBL_RES'].' r
              LEFT JOIN '.$d['table'].' d ON d.'.$d['id'].' = r.device_id
              LEFT JOIN '.$u['table'].' iu ON iu.'.$u['id'].' = r.instructor_user_id
              WHERE r.start_dt <= ? AND r.end_dt >= ?
              ORDER BY r.start_dt ASC';
      $resRows = q($sql, array($end,$start))->fetchAll();
      $debug['reservations_count']=count($resRows);

      if($resRows && count($resRows)){
        $ids=array(); for($i=0;$i<count($resRows);$i++){ $ids[]=(int)$resRows[$i]['res_id']; }
        $ph = implode(',', array_fill(0,count($ids),'?'));
        $sql2='SELECT rs.res_id, u.'.$u['id'].' AS userid, u.'.$u['fname'].' AS first_name, u.'.$u['lname'].' AS last_name
               FROM '.$GLOBALS['TBL_RS'].' rs
               JOIN '.$u['table'].' u ON u.'.$u['id'].' = rs.student_user_id
               WHERE rs.res_id IN ('.$ph.')';
        $resStudentsRaw = q($sql2,$ids)->fetchAll();
        $resStudents=array();
        for($i=0;$i<count($resStudentsRaw);$i++){
          $r=$resStudentsRaw[$i];
          $rid=(int)$r['res_id']; if(!isset($resStudents[$rid])) $resStudents[$rid]=array();
          $resStudents[$rid][] = array('userid'=>$r['userid'],'first_name'=>$r['first_name'],'last_name'=>$r['last_name']);
        }
        $debug['res_students_map_keys']=count($resStudents);
      }
    }catch(Exception $e){
      $resRows=array(); $resStudents=array();
      $debug['reservations_error']=$e->getMessage();
    }

    // Sunrise/Sunset
    $tsNoon=strtotime($date.' 12:00:00');
    $sun=function_exists('date_sun_info') ? @date_sun_info($tsNoon,$LOC_LAT,$LOC_LON) : false;
    $sunrise = ($sun && isset($sun['sunrise'])) ? date('Y-m-d H:i:s',$sun['sunrise']) : null;
    $sunset  = ($sun && isset($sun['sunset']))  ? date('Y-m-d H:i:s',$sun['sunset'])  : null;

    $payload = array(
      'date'=>$date,'hstart'=>$H_START,'hend'=>$H_END,
      'sunrise'=>$sunrise,'sunset'=>$sunset,
      'location'=>array('name'=>$LOC_NAME,'lat'=>$LOC_LAT,'lon'=>$LOC_LON),
      'devices'=>$devices,'staff'=>$staff,'reservations'=>$resRows,'res_students'=>$resStudents
    );
    if(isset($_GET['debug'])){ $payload['debug']=$debug; }
    jexit($payload);
  }

	
	
	

  // ===================================================================
  // S-05 — PHP: API form_options
  // ===================================================================
  

if($api==='form_options'){
    $today = date('Y-m-d');
    $u=$COLMAP_USERS; $d=$COLMAP_DEVICES;
    $p=$COLMAP_PROGRAMS; $pu=$COLMAP_PU;
    $debug = array();

    /* ---------- ACTIVE USERS (for Student/User) ---------- */
    $allUsers = array();
    try{
      $selU=''.$u['id'].' AS id, '.$u['fname'].' AS first_name, '.$u['lname'].' AS last_name, '.$u['role'].' AS role';
      $sqlU='SELECT '.$selU.' FROM '.$u['table'].'
             WHERE '.$u['active_to'].' <> "0000-00-00"
               AND '.$u['active_to'].' >= CURDATE()
             ORDER BY '.$u['fname'].', '.$u['lname'].'';
      $allUsers = q($sqlU)->fetchAll();
      $debug['users_sql']=$sqlU;
      $debug['users_count']=count($allUsers);
    }catch(Exception $e){ $debug['users_error']=$e->getMessage(); }

    // index by id for quick lookups
    $userById = array();
    for($i=0;$i<count($allUsers);$i++){
      $userById[(int)$allUsers[$i]['id']] = $allUsers[$i];
    }

    /* ---------- ELIGIBLE PROGRAMS (active AND location US/ALL) ---------- */
    $programs = array(); // pid => name
    try{
      $sqlP='SELECT '.$p['id'].' AS id, '.$p['name'].' AS name
             FROM '.$p['table'].'
             WHERE TRIM(UPPER('.$p['active'].')) IN ("Y","YES","1","TRUE","T")
               AND TRIM(UPPER('.$p['location'].')) IN ("US","ALL")
             ORDER BY '.$p['name'].' ASC';
      $rows = q($sqlP)->fetchAll();
      for($i=0;$i<count($rows);$i++){ $programs[(int)$rows[$i]['id']] = $rows[$i]['name']; }
      $debug['programs_sql']=$sqlP;
      $debug['programs_count']=count($rows);
    }catch(Exception $e){ $debug['programs_error']=$e->getMessage(); }

    /* ---------- PROGRAM ↔ USER LINKS (programs_users) ---------- */
    $userPrograms = array(); // uid => [pid,...] (only eligible program ids kept)
    if(count($programs)){
      try{
        $sqlPU='SELECT '.$pu['user'].' AS uid, '.$pu['program'].' AS pid FROM '.$pu['table'].'';
        $links = q($sqlPU)->fetchAll();
        $debug['pu_sql']=$sqlPU;
        $debug['pu_links_total']=count($links);

        for($i=0;$i<count($links);$i++){
          $uid=(int)$links[$i]['uid']; $pid=(int)$links[$i]['pid'];
          if(!isset($programs[$pid])) continue; // only US/ALL + active programs
          if(!isset($userPrograms[$uid])) $userPrograms[$uid]=array();
          if(!in_array($pid,$userPrograms[$uid])) $userPrograms[$uid][]=$pid;
        }
        $debug['pu_links_kept']=count($links);
      }catch(Exception $e){ $debug['pu_error']=$e->getMessage(); }
    }

    /* ---------- BUILD GROUPS: users per program, sorted by voornaam ---------- */
    $groups = array();      // program name => [users...]
    $unassigned = array();  // active users not in any eligible program

    $cmp=function($a,$b){
      $fa=strtolower($a['first_name']); $fb=strtolower($b['first_name']);
      if($fa===$fb){
        $la=strtolower($a['last_name']); $lb=strtolower($b['last_name']);
        return strcmp($la,$lb);
      }
      return strcmp($fa,$fb);
    };

    // assign users to their eligible programs
    foreach($userById as $uid=>$urow){
      if(isset($userPrograms[$uid]) && count($userPrograms[$uid])){
        for($k=0;$k<count($userPrograms[$uid]);$k++){
          $pid = (int)$userPrograms[$uid][$k];
          $pname = $programs[$pid]; // safe, filtered above
          if(!isset($groups[$pname])) $groups[$pname]=array();
          $groups[$pname][] = $urow;
        }
      }else{
        $unassigned[] = $urow;
      }
    }

    // order program names naturally (PHP 5.3-safe)
    $pgNames = array_keys($groups);
    if(function_exists('natcasesort')){ natcasesort($pgNames); $pgNames = array_values($pgNames); }
    else { sort($pgNames); }

    // sort members inside each program + unassigned
    for($gi=0;$gi<count($pgNames);$gi++){ $nm=$pgNames[$gi]; usort($groups[$nm], $cmp); }
    usort($unassigned, $cmp);

    $debug['student_groups']=count($pgNames);
    $debug['unassigned_count']=count($unassigned);

    /* ---------- STAFF (US-only, active, roles) ---------- */
    $staff=array();
    try{
      $selS=''.$u['id'].' AS id, '.$u['fname'].' AS first_name, '.$u['lname'].' AS last_name, '.$u['role'].' AS role';
      $sqlS='SELECT '.$selS.' FROM '.$u['table'].'
             WHERE LOWER('.$u['role'].') IN ("instructor","admin")
               AND '.$u['active_to'].' <> "0000-00-00"
               AND '.$u['active_to'].' >= CURDATE()
               AND TRIM(UPPER('.$u['work_loc'].')) = "US"
             ORDER BY '.$u['fname'].', '.$u['lname'].'';
      $staff=q($sqlS)->fetchAll();
      $debug['staff_sql']=$sqlS;
      $debug['staff_count']=count($staff);
    }catch(Exception $e){ $staff=array(); $debug['staff_error']=$e->getMessage(); }

    /* ---------- DEVICES (US-only, active/visible) ---------- */
    $devices=array();
    try{
      $selD=''.$d['id'].' AS dev_id, '.$d['name'].' AS dev_name, '.$d['type'].' AS dev_type, '.$d['model'].' AS dev_model';
      $sqlD='SELECT '.$selD.' FROM '.$d['table'].'
             WHERE TRIM(UPPER('.$d['visible'].')) IN ("Y","YES","1","TRUE","T")
               AND TRIM(UPPER('.$d['active'].'))  IN ("Y","YES","1","TRUE","T")
               AND TRIM(UPPER('.$d['location'].')) = "US"
             ORDER BY '.$d['order'].' IS NULL, '.$d['order'].' ASC, '.$d['type'].' ASC, '.$d['name'].' ASC';
      $devices=q($sqlD)->fetchAll();
      $debug['devices_sql']=$sqlD;
      $debug['devices_count']=count($devices);
    }catch(Exception $e){ $devices=array(); $debug['devices_error']=$e->getMessage(); }

    /* ---------- OUTPUT ---------- */
    $out = array(
      'student_groups'=>array('groups'=>$groups,'group_order'=>$pgNames,'unassigned'=>$unassigned),
      'staff'=>$staff,
      'devices'=>$devices
    );
    if(isset($_GET['debug'])){ $out['debug']=$debug; }
    jexit($out);
  }

// ===================================================================
// S-05A — PHP: API reminders_list (read-only)  (UPDATED)
// ===================================================================

if ($api === 'reminders_list') {
  @ini_set('display_errors','0'); @error_reporting(E_ERROR | E_PARSE);

  $today = date('Y-m-d');
  $nowTs = time();

  // ----- Devices (US-only, visible+active) -----
  $d = $COLMAP_DEVICES;
  $devices = array();
  try{
    $sqlD = 'SELECT '.
      $d['id'].'   AS dev_id, '.
      $d['name'].' AS dev_name, '.
      $d['type'].' AS dev_type, '.
      $d['model'].' AS dev_model, '.
      $d['maint_type'].' AS dev_maint_type, '.
      $d['maint_next'].' AS dev_maint_next, '.
      'latest_tacho AS latest_tacho, '.
      'latest_hobbs AS latest_hobbs '.
      'FROM '.$d['table'].' '.
      'WHERE TRIM(UPPER('.$d['visible'].')) IN ("Y","YES","1","TRUE","T") '.
        'AND TRIM(UPPER('.$d['active'].')) IN ("Y","YES","1","TRUE","T") '.
        'AND TRIM(UPPER('.$d['location'].')) = "US" '.
      'ORDER BY '.$d['order'].' IS NULL, '.$d['order'].' ASC, '.$d['type'].' ASC, '.$d['name'].' ASC';
    $devices = q($sqlD)->fetchAll();
  }catch(Exception $e){
    $devices = array();
  }

  $devRem = array();
  $devIdList = array();
  $meterByDev = array(); // id => ['tacho'=>..., 'hobbs'=>...]

  for ($i=0; $i<count($devices); $i++){
    $row = $devices[$i];
    $ts  = (int)$row['dev_maint_next'];
    $due_iso = $ts>0 ? date('Y-m-d H:i:s', $ts) : null;
    $days_left = ($ts>0) ? (int)floor(($ts - $nowTs)/86400) : null;

    $sev = 'none';
    if ($days_left !== null){
      if ($days_left < 0) $sev = 'overdue';
      else if ($days_left <= 30) $sev = 'soon';
      else if ($days_left <= 120) $sev = 'notice';
      else $sev = 'ok';
    }

    $id = (int)$row['dev_id'];
    $devIdList[] = $id;

    $latest_tacho = isset($row['latest_tacho']) ? (float)$row['latest_tacho'] : null;
    $latest_hobbs = isset($row['latest_hobbs']) ? (float)$row['latest_hobbs'] : null;
    $meterByDev[$id] = array('tacho'=>$latest_tacho, 'hobbs'=>$latest_hobbs);

    $devRem[] = array(
      'id'           => $id,
      'name'         => (string)$row['dev_name'],
      'type'         => (string)$row['dev_type'],
      'model'        => (string)$row['dev_model'],
      'maint_type'   => (string)($row['dev_maint_type'] ?: ''),
      'due_iso'      => $due_iso,
      'days_left'    => $days_left,
      'severity'     => $sev,
      'latest_tacho' => $latest_tacho,
      'latest_hobbs' => $latest_hobbs,
      // will fill below:
      'reminders'    => array()
    );
  }

  // ----- Staff (US-only, active, roles) -----
  $u = $COLMAP_USERS;
  $staffRows = array();
  try{
    $sqlS='SELECT '.
      $u['id'].' AS id, '.
      $u['fname'].' AS first_name, '.
      $u['lname'].' AS last_name, '.
      $u['role'].' AS role, '.
      $u['active_to'].' AS active_to '.
      'FROM '.$u['table'].' '.
      'WHERE LOWER('.$u['role'].') IN ("instructor","admin") '.
        'AND '.$u['active_to'].' <> "0000-00-00" '.
        'AND '.$u['active_to'].' >= CURDATE() '.
        'AND TRIM(UPPER('.$u['work_loc'].')) = "US" '.
      'ORDER BY '.$u['fname'].', '.$u['lname'].'';
    $staffRows = q($sqlS)->fetchAll();
  }catch(Exception $e){ $staffRows = array(); }

  $staffRem = array();
  $staffIdList = array();
  for ($i=0; $i<count($staffRows); $i++){
    $s = $staffRows[$i];
    $due = $s['active_to'] ? (string)$s['active_to'].' 00:00:00' : null;
    $ts  = $due ? strtotime($due) : 0;
    $days_left = $due ? (int)floor(($ts - $nowTs)/86400) : null;

    $sev = 'none';
    if ($days_left !== null){
      if ($days_left < 0) $sev = 'overdue';
      else if ($days_left <= 30) $sev = 'soon';
      else if ($days_left <= 120) $sev = 'notice';
      else $sev = 'ok';
    }

    $name = trim(($s['first_name']?:'').' '.($s['last_name']?:''));
    $sid  = (int)$s['id'];
    $staffIdList[] = $sid;

    $staffRem[] = array(
      'id'        => $sid,
      'name'      => $name !== '' ? $name : '—',
      'role'      => (string)$s['role'],
      'due_iso'   => $due,
      'days_left' => $days_left,
      'label'     => 'Active until',
      'severity'  => $sev,
      // will fill below:
      'reminders' => array()
    );
  }

  // ----- Fuel (read-only) -----
  $fuel = array('has'=>false, 'gallons'=>null, 'updated_by'=>null, 'updated_at'=>null);
  try{
    $st = q('SELECT gallons, updated_by, updated_at FROM fuel_station ORDER BY updated_at DESC LIMIT 1');
    $row = $st->fetch();
    if ($row) {
      $fuel['has']        = true;
      $fuel['gallons']    = (float)$row['gallons'];
      $fuel['updated_by'] = (string)($row['updated_by'] ?: '');
      $fuel['updated_at'] = (string)$row['updated_at'];
    }
  }catch(Exception $e){
    // ignore
  }

  // ----- NEW: pull saved reminders and attach to devices/staff -----
  // Helper to compute severity from remaining (hours or days)
  $sev_from_remaining = function($rem){
    if ($rem === null) return 'none';
    if ($rem < 0) return 'overdue';
    if ($rem <= 30) return 'soon';
    if ($rem <= 120) return 'notice';
    return 'ok';
  };

  // Map arrays for quick attachment
  $devIndex = array(); for($i=0;$i<count($devRem);$i++){ $devIndex[$devRem[$i]['id']] = $i; }
  $staffIndex = array(); for($i=0;$i<count($staffRem);$i++){ $staffIndex[$staffRem[$i]['id']] = $i; }

  // DEVICE reminders
  if (count($devIdList)){
    $ph = implode(',', array_fill(0, count($devIdList), '?'));
    try{
      $rows = q('SELECT id, target_id, name, track_by,
                  last_completed_num, last_completed_date,
                  interval_value, interval_unit, calendar_month,
                  warn_value, warn_unit,
                  next_due_num, next_due_date,
                  primary_flag, send_email, send_slack, notes, updated_at,
                  short_label
            FROM reminders
            WHERE target_type="DEVICE" AND target_id IN ('.$ph.')
            ORDER BY primary_flag DESC, updated_at DESC', $devIdList)->fetchAll();

for($i=0;$i<count($rows);$i++){
  $r = $rows[$i]; $devId = (int)$r['target_id'];
  if(!isset($devIndex[$devId])) continue;

  $remaining = null; $due_text = '';
  if ($r['track_by']==='HOURS_TACHO' && $r['next_due_num']!==null){
    $cur = isset($meterByDev[$devId]['tacho']) ? $meterByDev[$devId]['tacho'] : null;
    if($cur!==null) $remaining = round(((float)$r['next_due_num']) - $cur, 1);
    $due_text = 'Next @ Tacho: '.(is_null($r['next_due_num'])?'—':(0+($r['next_due_num'])));
  } elseif ($r['track_by']==='HOURS_HOBBS' && $r['next_due_num']!==null){
    $cur = isset($meterByDev[$devId]['hobbs']) ? $meterByDev[$devId]['hobbs'] : null;
    if($cur!==null) $remaining = round(((float)$r['next_due_num']) - $cur, 1);
    $due_text = 'Next @ Hobbs: '.(is_null($r['next_due_num'])?'—':(0+($r['next_due_num'])));
  } elseif ($r['track_by']==='DATE' && $r['next_due_date']){
    $dt = strtotime($r['next_due_date'].' 12:00:00');
    $remaining = (int)ceil(($dt - $nowTs)/86400);
    $due_text = 'Next Due: '.$r['next_due_date'];
  }

  $sev = $sev_from_remaining($remaining);

  $devRem[$devIndex[$devId]]['reminders'][] = array(
    'id'                   => (int)$r['id'],
    'name'                 => (string)$r['name'],
    'track_by'             => (string)$r['track_by'], // HOURS_TACHO|HOURS_HOBBS|DATE
    'due_text'             => $due_text,
    'remaining'            => $remaining,
    'severity'             => $sev,
    'primary'              => (int)$r['primary_flag']?true:false,
    'last_completed_num'   => $r['last_completed_num'] !== null ? (float)$r['last_completed_num'] : null,
    'last_completed_date'  => $r['last_completed_date'] ?: null,
    'interval_value'       => $r['interval_value'] !== null ? (int)$r['interval_value'] : null,
    'interval_unit'        => $r['interval_unit'] ?: null,     // HOURS|DAYS|MONTHS
    'next_due_num'         => $r['next_due_num'] !== null ? (float)$r['next_due_num'] : null,
    'next_due_date'        => $r['next_due_date'] ?: null,
    'short_label'          => isset($r['short_label']) ? (string)$r['short_label'] : null
  );
}
    }catch(Exception $e){ /* ignore */ }
  }

  // STAFF reminders (optional for later; included for parity)
if (count($staffIdList)){
  $ph = implode(',', array_fill(0, count($staffIdList), '?'));
  try{
    $rows = q(
      'SELECT id, target_id, name, track_by,
              last_completed_num, last_completed_date,
              interval_value, interval_unit, calendar_month,
              warn_value, warn_unit,
              next_due_num, next_due_date,
              primary_flag, send_email, send_slack, notes, updated_at,
              short_label
       FROM reminders
       WHERE target_type="STAFF" AND target_id IN ('.$ph.')
       ORDER BY primary_flag DESC, updated_at DESC',
      $staffIdList
    )->fetchAll();

    for($i=0;$i<count($rows);$i++){
      $r = $rows[$i]; $sid = (int)$r['target_id'];
      if(!isset($staffIndex[$sid])) continue;

      $remaining = null; $due_text = '';
      if ($r['track_by']==='DATE' && $r['next_due_date']){
        $dt = strtotime($r['next_due_date'].' 12:00:00');
        $remaining = (int)ceil(($dt - $nowTs)/86400);
        $due_text = 'Next Due: '.$r['next_due_date'];
      }
      $sev = $sev_from_remaining($remaining);

      $staffRem[$staffIndex[$sid]]['reminders'][] = array(
        'id'                   => (int)$r['id'],
        'name'                 => (string)$r['name'],
        'track_by'             => (string)$r['track_by'], // DATE expected
        'due_text'             => $due_text,
        'remaining'            => $remaining,             // days; may be null
        'severity'             => $sev,
        'primary'              => (int)$r['primary_flag']?true:false,
        'last_completed_num'   => $r['last_completed_num'] !== null ? (float)$r['last_completed_num'] : null,
        'last_completed_date'  => $r['last_completed_date'] ?: null,
        'interval_value'       => $r['interval_value'] !== null ? (int)$r['interval_value'] : null,
        'interval_unit'        => $r['interval_unit'] ?: null,     // DAYS|MONTHS
        'next_due_num'         => $r['next_due_num'] !== null ? (float)$r['next_due_num'] : null,
        'next_due_date'        => $r['next_due_date'] ?: null,
        'short_label'          => isset($r['short_label']) ? (string)$r['short_label'] : null
      );
    }
  }catch(Exception $e){ /* ignore */ }
}

  jexit(array(
    'ok'      => true,
    'devices' => $devRem,
    'staff'   => $staffRem,
    'fuel'    => $fuel
  ));
}
	
	// ===================================================================
	// S-05B — PHP: API reminders_fuel_save (create/update latest fuel row)
	// ===================================================================
	if ($api === 'reminders_fuel_save') {
  // Clean JSON output
  @ini_set('display_errors','0'); @error_reporting(E_ERROR | E_PARSE);

  // Read JSON body
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) $input = array();

  $gallons    = isset($input['gallons']) ? floatval($input['gallons']) : null;
  $updated_by = isset($input['updated_by']) ? trim($input['updated_by']) : '';

  if ($gallons === null || !is_numeric($gallons)) {
    jexit(array('ok'=>false, 'error'=>'Invalid gallons value.'));
  }

  try{
    // Ensure table exists (matches your reader in reminders_list)
    q('CREATE TABLE IF NOT EXISTS fuel_station (
         id INT AUTO_INCREMENT PRIMARY KEY,
         gallons DECIMAL(10,1) NOT NULL,
         updated_by VARCHAR(80) DEFAULT NULL,
         updated_at DATETIME NOT NULL
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8');

    // Insert a new reading as the latest row
    q('INSERT INTO fuel_station (gallons, updated_by, updated_at)
       VALUES (?, ?, NOW())', array($gallons, $updated_by));

    // Return the latest row in the same shape your reader uses
    $row = q('SELECT gallons, updated_by, updated_at
              FROM fuel_station
              ORDER BY updated_at DESC
              LIMIT 1')->fetch();

    $fuel = array(
      'has'        => $row ? true : false,
      'gallons'    => $row ? (float)$row['gallons'] : null,
      'updated_by' => $row ? (string)($row['updated_by'] ?: '') : null,
      'updated_at' => $row ? (string)$row['updated_at'] : null
    );

    jexit(array('ok'=>true, 'fuel'=>$fuel));
  }catch(Exception $e){
    jexit(array('ok'=>false, 'error'=>$e->getMessage()));
  }
}

// ===================================================================
// S-05B — PHP: API reminders_save (create new reminder)
// ===================================================================
if ($api === 'reminders_save') {
  header('Content-Type: application/json');
  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) $in = array();

  // default value for track_by
  $track_by = isset($in['track_by']) ? $in['track_by'] : 'DATE';

  // ---------- BACKEND GUARD: compute next_due_* if UI didn’t send ----------
  if ($track_by === 'DATE') {
    $next_due_date = isset($in['next_due_date']) ? $in['next_due_date'] : null;

    if (!$next_due_date) {
      if (!empty($in['last_completed_date']) && !empty($in['interval_value']) && !empty($in['interval_unit'])) {
        $base = new DateTime($in['last_completed_date']);
        if ($in['interval_unit'] === 'DAYS') {
          $base->modify('+' . intval($in['interval_value']) . ' days');
        } else { // MONTHS
          $base->modify('+' . intval($in['interval_value']) . ' months');
          if (!empty($in['calendar_month'])) $base->modify('last day of this month');
        }
        $next_due_date = $base->format('Y-m-d');
      } elseif (!empty($in['due_date'])) {
        $next_due_date = $in['due_date'];
      }
    }

    $in['next_due_date'] = $next_due_date;
    $in['next_due_num']  = null; // normalize

  } else {
    $next_due_num = isset($in['next_due_num']) ? $in['next_due_num'] : null;

    if ($next_due_num === null) {
      if (!empty($in['last_completed_num']) && !empty($in['interval_value']) && ($in['interval_unit'] === 'HOURS')) {
        $next_due_num = round(floatval($in['last_completed_num']) + floatval($in['interval_value']), 1);
      } elseif (!empty($in['due_num'])) {
        $next_due_num = floatval($in['due_num']);
      }
    }

    $in['next_due_num']  = $next_due_num;
    $in['next_due_date'] = null; // normalize
  }

  // -------------------------------------------------------------------------
  try {
    q('INSERT INTO reminders
    (target_type,target_id,name,short_label,track_by,
     last_completed_num,last_completed_date,
     interval_value,interval_unit,calendar_month,
     warn_value,warn_unit,
     next_due_num,next_due_date,
     primary_flag,send_email,send_slack,notes,created_at,updated_at)
   VALUES (?,?,?,?,?,
           ?,?,
           ?,?,?,
           ?,?,
           ?,?,
           ?,?,?,?,NOW(),NOW())',
   array(
     isset($in['target_type']) ? $in['target_type'] : 'DEVICE',
     isset($in['target_id']) ? intval($in['target_id']) : 0,
     isset($in['name']) ? $in['name'] : '',
     isset($in['short_label']) ? $in['short_label'] : null,  // NEW
     $track_by,
     isset($in['last_completed_num']) ? $in['last_completed_num'] : null,
     isset($in['last_completed_date']) ? $in['last_completed_date'] : null,
     isset($in['interval_value']) ? intval($in['interval_value']) : null,
     isset($in['interval_unit']) ? $in['interval_unit'] : 'HOURS',
     !empty($in['calendar_month']) ? 1 : 0,
     isset($in['warn_value']) ? intval($in['warn_value']) : null,
     isset($in['warn_unit']) ? $in['warn_unit'] : null,
     $in['next_due_num'],
     $in['next_due_date'],
     !empty($in['primary_flag']) ? 1 : 0,
     !empty($in['send_email']) ? 1 : 0,
     !empty($in['send_slack']) ? 1 : 0,
     isset($in['notes']) ? $in['notes'] : null
   ));
    $rid = db()->lastInsertId();
    jexit(array('ok'=>true,'reminder_id'=>$rid,
      'computed'=>array('next_due_num'=>$in['next_due_num'],'next_due_date'=>$in['next_due_date'])));
  } catch(Exception $e) {
    jexit(array('ok'=>false,'error'=>$e->getMessage()));
  }
}
	
// ===================================================================
// S-05D — PHP: API reminders_form_options
// ===================================================================
if ($api === 'reminders_form_options') {
  @ini_set('display_errors','0'); @error_reporting(E_ERROR | E_PARSE);

  // ----- Devices (US-only, visible+active)
  $d = $COLMAP_DEVICES;
  $devices = array();
  try{
    $sql = 'SELECT '.
      $d['id'].'   AS id, '.
      $d['name'].' AS name, '.
      $d['model'].' AS model '.
      'FROM '.$d['table'].' '.
      'WHERE TRIM(UPPER('.$d['visible'].')) IN ("Y","YES","1","TRUE","T") '.
        'AND TRIM(UPPER('.$d['active'].'))  IN ("Y","YES","1","TRUE","T") '.
        'AND TRIM(UPPER('.$d['location'].')) = "US" '.
      'ORDER BY '.$d['order'].' IS NULL, '.$d['order'].' ASC, '.$d['type'].' ASC, '.$d['name'].' ASC';
    $rows = q($sql)->fetchAll();
    foreach($rows as $r){
      $devices[] = array(
        'id'    => (int)$r['id'],
        'label' => trim($r['name'].($r['model']?(' ('.$r['model'].')'):''))
      );
    }
  }catch(Exception $e){ $devices = array(); }

  // ----- Staff (US-only, instructor/admin)
  $u = $COLMAP_USERS;
  $staff = array();
  try{
    $sqlS='SELECT '.
      $u['id'].' AS id, '.
      $u['fname'].' AS fn, '.
      $u['lname'].' AS ln, '.
      $u['role'].' AS role '.
      'FROM '.$u['table'].' '.
      'WHERE LOWER('.$u['role'].') IN ("instructor","admin") '.
        'AND '.$u['active_to'].' <> "0000-00-00" '.
        'AND '.$u['active_to'].' >= CURDATE() '.
        'AND TRIM(UPPER('.$u['work_loc'].')) = "US" '.
      'ORDER BY '.$u['fname'].', '.$u['lname'].'';
    $rows = q($sqlS)->fetchAll();
    foreach($rows as $r){
      $staff[] = array(
        'id'    => (int)$r['id'],
        'label' => trim(($r['fn']?:'').' '.($r['ln']?:'')) ?: '—'
      );
    }
  }catch(Exception $e){ $staff = array(); }

  jexit(array('ok'=>true, 'devices'=>$devices, 'staff'=>$staff));
}	
	
	
  // ===================================================================
  // S-06 — PHP: API scenarios
  // ===================================================================

	
if($api==='scenarios'){
    $sid = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    $resType = isset($_GET['res_type']) ? $_GET['res_type'] : '';
    $kind = scenario_kind_from_type($resType);

    if(!$sid || !$kind){
      jexit(array('scenarios'=>array(), 'structure'=>array(), 'note'=>'missing student or type'));
    }

    // tables/columns
    $p  = $PROGRAMS;   // programs
    $s  = $SCEN;       // scenarios
    $pu = $COLMAP_PU;  // programs_users

    $rows = array(); $debug = array('kind'=>$kind,'sid'=>$sid,'resType'=>$resType);

    try{
      // Join stages / phases to fetch HUMAN NAMES + ORDERS
      $sql =
        'SELECT
           p.'.$p['name'].'            AS pr_name,
           s.'.$s['type'].'            AS sc_type,
           s.'.$s['program'].'         AS sc_program,

           st.st_id                      AS st_id,
           st.st_order                   AS st_order,
           st.st_name                    AS st_name,

           ph.ph_id                      AS ph_id,
           ph.ph_order                   AS ph_order,
           ph.ph_name                    AS ph_name,

           s.'.$s['order'].'           AS sc_order,
           s.'.$s['code'].'            AS sc_code,
           s.'.$s['name'].'            AS sc_name
         FROM '.$s['table'].' s
         JOIN '.$p['table'].' p
              ON p.'.$p['id'].' = s.'.$s['program'].'
         JOIN '.$pu['table'].' pu
              ON pu.'.$pu['program'].' = p.'.$p['id'].'
             AND pu.'.$pu['user'].'    = ?
         LEFT JOIN stages st ON st.st_id = s.sc_stage
         LEFT JOIN phases ph ON ph.ph_id = s.sc_phase
         WHERE UPPER(s.'.$s['type'].') = UPPER(?)
           AND TRIM(UPPER(p.'.$p['active'].'))   IN ("Y","YES","1","TRUE","T")
           AND TRIM(UPPER(p.'.$p['location'].')) IN ("US","ALL")
         ORDER BY
           p.'.$p['name'].' ASC,
           st.st_order IS NULL, st.st_order ASC, st.st_name ASC,
           ph.ph_order IS NULL, ph.ph_order ASC, ph.ph_name ASC,
           s.'.$s['order'].' IS NULL, s.'.$s['order'].' ASC,
           s.'.$s['code'].' ASC';

      $rows = q($sql, array($sid, $kind))->fetchAll();
      $debug['query']='with-stage-phase-names';
    }catch(Exception $e){
      $rows = array();
      $debug['error']=$e->getMessage();
    }

    // Build both a flat list (back-compat) and a structured tree for the UI
    $flat = array();
    $tree = array(); // [program => [stageKey => ['label'=>..., 'phases' => [phaseKey=>['label'=>...,'scenarios'=>[]]]]]]

    for($i=0;$i<count($rows);$i++){
      $r = $rows[$i];

      $progName  = isset($r['pr_name']) ? $r['pr_name'] : 'Program';
      $stageName = (isset($r['st_name']) && $r['st_name']!=='') ? $r['st_name'] : 'Stage';
      $phaseName = (isset($r['ph_name']) && $r['ph_name']!=='') ? $r['ph_name'] : 'Phase';

      $stageOrder = isset($r['st_order']) ? (int)$r['st_order'] : PHP_INT_MAX;
      $phaseOrder = isset($r['ph_order']) ? (int)$r['ph_order'] : PHP_INT_MAX;

      $code = isset($r['sc_code']) ? $r['sc_code'] : '';
      $name = isset($r['sc_name']) ? $r['sc_name'] : '';
      $text = ($code!=='' ? ($code.' - ') : '').$name;

      $flat[] = array('sc_code'=>$code, 'sc_name'=>$name, 'program'=>$progName);

      if(!isset($tree[$progName])) $tree[$progName] = array();

      $stageKey = sprintf('%010d|%s', $stageOrder, $stageName);
      if(!isset($tree[$progName][$stageKey])){
        $tree[$progName][$stageKey] = array(
          'stage_order'=>$stageOrder,
          'label'=>$stageName,
          'phases'=>array()
        );
      }

      $phaseKey = sprintf('%010d|%s', $phaseOrder, $phaseName);
      if(!isset($tree[$progName][$stageKey]['phases'][$phaseKey])){
        $tree[$progName][$stageKey]['phases'][$phaseKey] = array(
          'phase_order'=>$phaseOrder,
          'label'=>$phaseName,
          'scenarios'=>array()
        );
      }

      $tree[$progName][$stageKey]['phases'][$phaseKey]['scenarios'][] = array(
        'value' => $text,
        'label' => $text
      );
    }

    // Convert to sorted arrays
    $structure = array();
    foreach($tree as $prog => $stages){
      uksort($stages, function($a,$b){ if($a===$b) return 0; return ($a<$b)?-1:1; });
      $stageArr = array();
      foreach($stages as $stageData){
        $phases = $stageData['phases'];
        uksort($phases, function($a,$b){ if($a===$b) return 0; return ($a<$b)?-1:1; });
        $phaseArr = array();
        foreach($phases as $phaseData){
          $phaseArr[] = array('label'=>$phaseData['label'],'scenarios'=>$phaseData['scenarios']);
        }
        $stageArr[] = array('label'=>$stageData['label'],'phases'=>$phaseArr);
      }
      $structure[] = array('program'=>$prog, 'stages'=>$stageArr);
    }

    if(isset($_GET['debug'])){ jexit(array('scenarios'=>$flat,'structure'=>$structure,'debug'=>$debug)); }
    jexit(array('scenarios'=>$flat,'structure'=>$structure));
  }

  /* Create reservation with overlap checks + optional override (with audit action fix) */
  // ===================================================================
  // S-07 — PHP: API create_reservation
  // ===================================================================
  if($api==='create_reservation'){
    $input = json_decode(file_get_contents('php://input'), true);
    if(!is_array($input)) $input=array();

    $type   = isset($input['type']) ? trim($input['type']) : '';
    $sidArr = array();
    if(isset($input['student_ids']) && is_array($input['student_ids'])){
      foreach($input['student_ids'] as $v){ $sidArr[] = (int)$v; }
    }elseif(isset($input['student_id']) && $input['student_id']!==''){
      $sidArr[] = (int)$input['student_id'];
    }

    $device_id = isset($input['device_id']) && $input['device_id']!=='' ? (int)$input['device_id'] : null;
    $staff_id  = isset($input['staff_id'])  && $input['staff_id']!==''  ? (int)$input['staff_id']  : null;
    $route     = isset($input['route']) ? trim($input['route']) : '';
    $mission   = isset($input['mission']) ? trim($input['mission']) : '';
    $allowOverlap = !empty($input['allow_overlap']) ? true : false;

    $start_dt = make_dt(@$input['start_date'], @$input['start_time']);
    $end_dt   = make_dt(@$input['end_date'],   @$input['end_time']);

    if(!$type || !$start_dt || !$end_dt || !$device_id){
      jexit(array('ok'=>false, 'error'=>'Missing required fields (type/start/end/device).'));
    }
    if(strtotime($end_dt) <= strtotime($start_dt)){
      jexit(array('ok'=>false, 'error'=>'End must be after Start.'));
    }

    $mission_code = null; $mission_name = null;
    if($mission!==''){
      $parts = explode(' - ', $mission, 2);
      if(count($parts)==2){ $mission_code=$parts[0]; $mission_name=$parts[1]; }
      else { $mission_name = $mission; }
    }

    $conflicts = array();

    // Device overlaps
    try{
      $sql='SELECT r.res_id, d.dev_name, r.start_dt, r.end_dt
            FROM '.$GLOBALS['TBL_RES'].' r
            LEFT JOIN devices d ON d.dev_id=r.device_id
            WHERE r.device_id=? AND r.start_dt<? AND r.end_dt>?';
      $rows=q($sql, array($device_id, $end_dt, $start_dt))->fetchAll();
      if($rows) $conflicts['device']=$rows;
    }catch(Exception $e){}

    // Instructor overlaps
    if($staff_id){
      try{
        $sql='SELECT r.res_id, r.start_dt, r.end_dt
              FROM '.$GLOBALS['TBL_RES'].' r
              WHERE r.instructor_user_id=? AND r.start_dt<? AND r.end_dt>?';
        $rows=q($sql, array($staff_id, $end_dt, $start_dt))->fetchAll();
        if($rows) $conflicts['instructor']=$rows;
      }catch(Exception $e){}
    }

    // Student overlaps
    if(count($sidArr)){
      try{
        $ph = implode(',', array_fill(0,count($sidArr),'?'));
        $args=$sidArr; $args[]=$end_dt; $args[]=$start_dt;
        $sql='SELECT rs.student_user_id, r.res_id, r.start_dt, r.end_dt
              FROM '.$GLOBALS['TBL_RS'].' rs
              JOIN '.$GLOBALS['TBL_RES'].' r ON r.res_id=rs.res_id
              WHERE rs.student_user_id IN ('.$ph.')
                AND r.start_dt<? AND r.end_dt>?';
        $rows=q($sql,$args)->fetchAll();
        if($rows) $conflicts['students']=$rows;
      }catch(Exception $e){}
    }

    if((isset($conflicts['device']) || isset($conflicts['instructor']) || isset($conflicts['students'])) && !$allowOverlap){
      jexit(array('ok'=>false, 'overlap'=>true, 'conflicts'=>$conflicts));
    }

    /* Insert reservation */
    $res_id = null;
    try{
      $sql='INSERT INTO '.$GLOBALS['TBL_RES'].'
           (res_type,start_dt,end_dt,device_id,instructor_user_id,route,
            scenario_id,mission_code,mission_name,notes,status,color_hint,
            created_by,created_at,updated_at)
           VALUES (?,?,?,?,?,?,
                   NULL,?,?,NULL,\'scheduled\',NULL,
                   NULL,NOW(),NOW())';
      q($sql, array($type,$start_dt,$end_dt,$device_id,$staff_id,$route,$mission_code,$mission_name));
      $res_id = db()->lastInsertId();
    }catch(Exception $e){
      jexit(array('ok'=>false,'error'=>'Insert reservation failed: '.$e->getMessage()));
    }

    /* Insert students */
    if($res_id && count($sidArr)){
      foreach($sidArr as $sid){
        try{
          q('INSERT INTO '.$GLOBALS['TBL_RS'].' (res_id,student_user_id) VALUES (?,?)', array($res_id,$sid));
        }catch(Exception $e){}
      }
    }

    /* Audit (action name fix per your request) */
    try{
      $newJson = json_encode(array(
        'res_id'=>$res_id,'type'=>$type,'start_dt'=>$start_dt,'end_dt'=>$end_dt,
        'device_id'=>$device_id,'instructor_user_id'=>$staff_id,'route'=>$route,
        'mission_code'=>$mission_code,'mission_name'=>$mission_name,
        'student_ids'=>$sidArr, 'allow_overlap'=>$allowOverlap
      ));
      $act = $allowOverlap ? 'create_override' : 'create';
      q('INSERT INTO '.$GLOBALS['TBL_AUD'].' (res_id,action,old_json,new_json,actor_id,created_at)
         VALUES (?,?,?,?,NULL,NOW())', array($res_id,$act,NULL,$newJson));
    }catch(Exception $e){}

    jexit(array('ok'=>true,'res_id'=>$res_id));
  }

  /* Get a single reservation (details + student ids) */
  // ===================================================================
  // S-08 — PHP: API get_reservation
  // ===================================================================
  if ($api==='get_reservation') {
    $res_id = isset($_GET['res_id']) ? (int)$_GET['res_id'] : 0;
    if(!$res_id) jexit(array('ok'=>false,'error'=>'Missing res_id'));

    try{
      $r = q('SELECT r.*, d.dev_name, d.dev_type, d.dev_sort AS dev_model
              FROM '.$GLOBALS['TBL_RES'].' r
              LEFT JOIN devices d ON d.dev_id = r.device_id
              WHERE r.res_id=? LIMIT 1', array($res_id))->fetch();
      if(!$r) jexit(array('ok'=>false,'error'=>'Reservation not found'));

      $stud = q('SELECT student_user_id FROM '.$GLOBALS['TBL_RS'].' WHERE res_id=?', array($res_id))->fetchAll();
      $sids = array();
      for($i=0;$i<count($stud);$i++) $sids[] = (int)$stud[$i]['student_user_id'];

      jexit(array('ok'=>true,'reservation'=>$r,'student_ids'=>$sids));
    }catch(Exception $e){
      jexit(array('ok'=>false,'error'=>$e->getMessage()));
    }
  }	
	
  /* Update reservation (overlap checks; full audit with old/new json) */
  // ===================================================================
  // S-09 — PHP: API update_reservation
  // ===================================================================
  if ($api==='update_reservation') {
    $input = json_decode(file_get_contents('php://input'), true);
    if(!is_array($input)) $input=array();
    $res_id   = isset($input['res_id']) ? (int)$input['res_id'] : 0;
    $type     = isset($input['type']) ? trim($input['type']) : '';
    $device_id= isset($input['device_id']) && $input['device_id']!=='' ? (int)$input['device_id'] : null;
    $staff_id = isset($input['staff_id'])  && $input['staff_id']!==''  ? (int)$input['staff_id']  : null;
    $route    = isset($input['route']) ? trim($input['route']) : '';
    $mission  = isset($input['mission']) ? trim($input['mission']) : '';
    $allowOverlap = !empty($input['allow_overlap']) ? true : false;

    $sidArr = array();
    if(isset($input['student_ids']) && is_array($input['student_ids'])){
      foreach($input['student_ids'] as $v){ $sidArr[] = (int)$v; }
    }

    $start_dt = make_dt(@$input['start_date'], @$input['start_time']);
    $end_dt   = make_dt(@$input['end_date'],   @$input['end_time']);

    if(!$res_id || !$type || !$start_dt || !$end_dt || !$device_id){
      jexit(array('ok'=>false,'error'=>'Missing required fields (res_id/type/start/end/device).'));
    }
    if(strtotime($end_dt)<=strtotime($start_dt)){
      jexit(array('ok'=>false,'error'=>'End must be after Start.'));
    }

    // Load OLD snapshot for audit
    $old = null; $oldStud=array();
    try{
      $old = q('SELECT * FROM '.$GLOBALS['TBL_RES'].' WHERE res_id=?', array($res_id))->fetch();
      $rows = q('SELECT student_user_id FROM '.$GLOBALS['TBL_RS'].' WHERE res_id=?', array($res_id))->fetchAll();
      for($i=0;$i<count($rows);$i++) $oldStud[] = (int)$rows[$i]['student_user_id'];
    }catch(Exception $e){}

    $mission_code = null; $mission_name = null;
    if($mission!==''){
      $parts = explode(' - ', $mission, 2);
      if(count($parts)==2){ $mission_code=$parts[0]; $mission_name=$parts[1]; }
      else { $mission_name = $mission; }
    }

    $conflicts = array();

    // Device overlaps (exclude self)
    try{
      $sql='SELECT r.res_id, d.dev_name, r.start_dt, r.end_dt
            FROM '.$GLOBALS['TBL_RES'].' r
            LEFT JOIN devices d ON d.dev_id=r.device_id
            WHERE r.res_id<>? AND r.device_id=? AND r.start_dt<? AND r.end_dt>?';
      $rows=q($sql, array($res_id,$device_id,$end_dt,$start_dt))->fetchAll();
      if($rows) $conflicts['device']=$rows;
    }catch(Exception $e){}

    // Instructor overlaps (exclude self)
    if($staff_id){
      try{
        $sql='SELECT r.res_id, r.start_dt, r.end_dt
              FROM '.$GLOBALS['TBL_RES'].' r
              WHERE r.res_id<>? AND r.instructor_user_id=? AND r.start_dt<? AND r.end_dt>?';
        $rows=q($sql, array($res_id,$staff_id,$end_dt,$start_dt))->fetchAll();
        if($rows) $conflicts['instructor']=$rows;
      }catch(Exception $e){}
    }

    // Student overlaps (exclude self)
    if(count($sidArr)){
      try{
        $ph = implode(',', array_fill(0,count($sidArr),'?'));
        $args=$sidArr; array_unshift($args,$res_id);
        $args[]=$end_dt; $args[]=$start_dt;
        $sql='SELECT rs.student_user_id, r.res_id, r.start_dt, r.end_dt
              FROM '.$GLOBALS['TBL_RS'].' rs
              JOIN '.$GLOBALS['TBL_RES'].' r ON r.res_id=rs.res_id
              WHERE r.res_id<>? AND rs.student_user_id IN ('.$ph.')
                AND r.start_dt<? AND r.end_dt>?';
        $rows=q($sql,$args)->fetchAll();
        if($rows) $conflicts['students']=$rows;
      }catch(Exception $e){}
    }

    if((isset($conflicts['device']) || isset($conflicts['instructor']) || isset($conflicts['students'])) && !$allowOverlap){
      jexit(array('ok'=>false,'overlap'=>true,'conflicts'=>$conflicts));
    }

    // Update row
    try{
      $sql='UPDATE '.$GLOBALS['TBL_RES'].'
            SET res_type=?, start_dt=?, end_dt=?, device_id=?, instructor_user_id=?, route=?,
                scenario_id=NULL, mission_code=?, mission_name=?, updated_at=NOW()
            WHERE res_id=?';
      q($sql, array($type,$start_dt,$end_dt,$device_id,$staff_id,$route,$mission_code,$mission_name,$res_id));
    }catch(Exception $e){
      jexit(array('ok'=>false,'error'=>'Update failed: '.$e->getMessage()));
    }

    // Replace students
    try{
      q('DELETE FROM '.$GLOBALS['TBL_RS'].' WHERE res_id=?', array($res_id));
      for($i=0;$i<count($sidArr);$i++){
        q('INSERT INTO '.$GLOBALS['TBL_RS'].' (res_id,student_user_id) VALUES (?,?)', array($res_id,$sidArr[$i]));
      }
    }catch(Exception $e){}

    // NEW snapshot for audit
    $new = array(
      'res_id'=>$res_id,'type'=>$type,'start_dt'=>$start_dt,'end_dt'=>$end_dt,
      'device_id'=>$device_id,'instructor_user_id'=>$staff_id,'route'=>$route,
      'mission_code'=>$mission_code,'mission_name'=>$mission_name,
      'student_ids'=>$sidArr,'allow_overlap'=>$allowOverlap
    );

    // Audit
    try{
      q('INSERT INTO '.$GLOBALS['TBL_AUD'].' (res_id,action,old_json,new_json,actor_id,created_at)
         VALUES (?,?,?,?,NULL,NOW())', array($res_id,'update', json_encode(array('reservation'=>$old,'student_ids'=>$oldStud)), json_encode($new)));
    }catch(Exception $e){}

    jexit(array('ok'=>true,'res_id'=>$res_id));
  }

  /* Delete reservation (with audit; confirm on client) */
  // ===================================================================
  // S-10 — PHP: API delete_reservation
  // ===================================================================
  if ($api==='delete_reservation') {
    $input = json_decode(file_get_contents('php://input'), true);
    if(!is_array($input)) $input=array();
    $res_id = isset($input['res_id']) ? (int)$input['res_id'] : 0;
    if(!$res_id) jexit(array('ok'=>false,'error'=>'Missing res_id'));

    // capture old snapshot for audit
    $old = null; $oldStud=array();
    try{
      $old = q('SELECT * FROM '.$GLOBALS['TBL_RES'].' WHERE res_id=?', array($res_id))->fetch();
      $rows = q('SELECT student_user_id FROM '.$GLOBALS['TBL_RS'].' WHERE res_id=?', array($res_id))->fetchAll();
      for($i=0;$i<count($rows);$i++) $oldStud[] = (int)$rows[$i]['student_user_id'];
    }catch(Exception $e){}

    try{
      q('DELETE FROM '.$GLOBALS['TBL_RS'].' WHERE res_id=?', array($res_id));
      q('DELETE FROM '.$GLOBALS['TBL_RES'].' WHERE res_id=?', array($res_id));
    }catch(Exception $e){
      jexit(array('ok'=>false,'error'=>'Delete failed: '.$e->getMessage()));
    }

    // audit
    try{
      q('INSERT INTO '.$GLOBALS['TBL_AUD'].' (res_id,action,old_json,new_json,actor_id,created_at)
         VALUES (?,?,?,?,NULL,NOW())', array($res_id,'delete', json_encode(array('reservation'=>$old,'student_ids'=>$oldStud)), NULL));
    }catch(Exception $e){}

    jexit(array('ok'=>true));
  }

  // ===================================================================
  // S-11 — PHP: API whoami + API close
  // ===================================================================
  if($api==='whoami'){ jexit(array('user'=>array('name'=>'Kay V','role'=>'admin'))); }
  exit;
}

// ===================================================================
// S-12 — HTML: Page vars + <!doctype html>
// ===================================================================
/* --------- Page vars --------- */
$date       = isset($_GET['date'])? $_GET['date'] : date('Y-m-d');
$today      = date('Y-m-d');
$date_long  = date('l, F j, Y', strtotime($date));
?>
<!doctype html>
<html>
	
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IPCA Scheduler – Day View</title>

<!-- ===================================================================
     S-13 — HTML: Styles & layout
     =================================================================== -->
<style>
  :root{
    --ipca-blue:#1e3c72; --ipca-blue2:#2a5298; --bg:#eef1f6; --grid:#e2e6f0; --divider:#d8dbe2;
    --text:#1a1f36; --muted:#7a8599;

    --resv-bg:#b7d2ff; --resv-fg:#0c2b5a; --resv-border:#8fb7ff;

    /* Expiration colors */
    --exp-ok:#3bc17f; --exp-warn:#f2a93b; --exp-bad:#e34c4c; --exp-track:#e9edf5;

    /* Tooltip */
    --tip-bg:#0f172a;  --tip-fg:#fff; --tip-border:#1f2a44;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Arial}

  .topbar{background:linear-gradient(90deg,var(--ipca-blue),var(--ipca-blue2));color:#fff;display:flex;align-items:center;gap:12px;padding:10px 16px}
  .brand{display:flex;align-items:center;gap:10px;font-weight:600}
  .brand img.logo{height:24px;width:auto;display:block}
  .btn{background:#ffffff22;border:1px solid #ffffff30;color:#fff;padding:6px 10px;border-radius:8px;cursor:pointer}
  .btn:hover{background:#ffffff33}
  .ghost{background:transparent;border:1px solid #ffffff55}
  .menu{margin-left:auto;display:flex;gap:8px}

  .wrap{padding:8px 16px}
  .card{background:#fff;border:1px solid #dde3f0;border-radius:14px;overflow:hidden}

  .timeHeader{display:flex;border-bottom:1px solid var(--grid);background:#fafbff}
  .hLeft{width:<?php echo (int)$LABEL_W; ?>px;flex:0 0 <?php echo (int)$LABEL_W; ?>px;border-right:1px solid #d8dbe2}
  .hRight{flex:1 1 auto;display:grid;grid-template-columns:repeat(<?php echo (int)($H_END-$H_START); ?>,1fr)}
  .hRight div{padding:10px 0;text-align:center;color:var(--muted);font-weight:700;border-left:1px solid var(--grid)}
  .hRight div:first-child{border-left:none}

  .rowsGrid{position:relative;display:grid;grid-template-columns: <?php echo (int)$LABEL_W; ?>px 1fr;grid-auto-rows:minmax(54px,auto);background:#fff;border-top:1px solid #d8dbe2;border-bottom:1px solid #d8dbe2}
  .sectionLabel{grid-column:1;display:flex;align-items:center;padding:0 10px;font-weight:700;background:#f3f5fb;border-top:1px solid #d8dbe2;border-bottom:1px solid #d8dbe2}
  .sectionSpacer{grid-column:2;background:#f3f5fb;border-top:1px solid #d8dbe2;border-bottom:1px solid #d8dbe2;border-left:1px solid #d8dbe2}
  .rlabel{grid-column:1;display:flex;flex-direction:column;justify-content:center;padding:6px 10px;border-bottom:1px solid #d8dbe2;background:#fff;z-index:2}
  .rlabel .l1{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:700}
  .rlabel .model{font-weight:700;color:#8aa0c3;margin-left:6px}
  .rlabel .expWrap{display:flex;align-items:center;gap:8px;margin-top:6px}
  .rlabel .expBar{flex:1;height:6px;background:var(--exp-track);border-radius:6px;overflow:hidden}
  .rlabel .expFill{height:100%}
  .rlabel .expFill.ok{background:var(--exp-ok)}
  .rlabel .expFill.warn{background:var(--exp-warn)}
  .rlabel .expFill.bad{background:var(--exp-bad)}
  .rlabel .expText{font-size:12px;color:#7a8599;white-space:nowrap}

  .rcell{grid-column:2;position:relative;border-bottom:1px solid #d8dbe2;border-left:1px solid #d8dbe2;background:#fff;cursor:pointer}

  .slot{
    position:absolute;height:32px;border-radius:6px;display:flex;align-items:center;gap:8px;padding:0 10px;
    z-index:10;max-width:100%;border:1px solid var(--resv-border);background:var(--resv-bg);color:var(--resv-fg);
    box-shadow:0 1px 0 rgba(0,0,0,.04);font-weight:700;left:0;top:0;overflow:visible;cursor:pointer;
  }
  .slot .slotText{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;max-width:100%}

  .slot .tooltip{
    display:none;position:absolute;left:0;top:38px;background:var(--tip-bg);color:var(--tip-fg);
    padding:10px 12px;border-radius:8px;border:1px solid var(--tip-border);box-shadow:0 8px 24px rgba(2,6,23,.35);
    z-index:10001;min-width:260px;max-width:420px;font-weight:600
  }
  .slot .tooltip.show{display:block}
  .slot .tooltip.above{top:auto;bottom:38px}
  .slot .tooltip.above::after{top:auto;bottom:-6px;transform:rotate(225deg)}
  .slot.raise{ z-index: 10000; }

  #overlay{position:absolute;left:<?php echo (int)$LABEL_W; ?>px;right:0;top:0;bottom:0;pointer-events:none;z-index:4}
  .vline{position:absolute;top:0;bottom:0;width:2px;background:#e94141;opacity:.95}
  .vline.sun{background:#ffb703}

  .headerBar{display:flex;align-items:center;gap:12px;padding:12px 16px}
  input[type=date]{padding:8px 10px;border:1px solid #cfd5e3;border-radius:8px}
  .dateTitle{flex:1;text-align:center;font-weight:800;font-size:28px;color:#1e3c72;letter-spacing:.2px}
  .spacer{flex:1}
  .foot{display:flex;justify-content:space-between;color:#7a8599;padding:10px 16px}

  .roleText{ color:#5b7ec9; font-weight:600; font-size:14px; margin-left:6px; }
  .roleTag{ all:unset; }

  .newBtn{
    padding:8px 14px; border-radius:8px; border:1px solid #1e3c72;
    background:#1e3c72; color:#fff; font-weight:700; cursor:pointer;
  }
  .newBtn:hover{ background:#fff; color:#1e3c72; }

  .modalWrap{ position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center; z-index:99999; }
  .modal{ background:#fff; width:min(900px, 92vw); max-height:92vh; overflow:auto; border-radius:14px; border:1px solid #dde3f0; box-shadow:0 20px 60px rgba(0,0,0,.25); }
  .modalHd{ display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #e5e9f2; }
  .modalHd h3{ margin:0; font-size:18px; }
  .modalBd{ padding:14px 16px; }
  .formGrid{ display:grid; grid-template-columns: 1fr 1fr; gap:12px 16px; }
  .formRow{ display:flex; flex-direction:column; gap:6px; }
  .formRow label{ font-weight:700; color:#1e2a44; }
  .formRow input[type=text], .formRow select, .formRow input[type=date]{ padding:10px; border:1px solid #cfd5e3; border-radius:8px; }
  .modalFt{ display:flex; justify-content:flex-end; gap:10px; padding:14px 16px; border-top:1px solid #e5e9f2; }
  .btnSec{ background:#f3f6fb; border:1px solid #cfd5e3; color:#1a1f36; padding:8px 12px; border-radius:8px; cursor:pointer; }
  .btnPri{ background:#1e3c72; border:1px solid #1e3c72; color:#fff; padding:8px 12px; border-radius:8px; cursor:pointer; font-weight:700; }
  .btnPri:hover{ background:#234686; }
  .note{ color:#6b7487; font-size:12px; }
  .primaryRemBar { margin-top:6px }
  .primaryRemBar .track { flex:0 0 40%; max-width:340px; min-width:200px; height:10px;
    background:#e6e9ef; border-radius:999px; overflow:hidden; }
  .primaryRemBar .label { margin-left:auto; color:#6b7487 }
  .dbgChip{
  margin-left:8px; padding:2px 6px; border-radius:6px; font-size:11px;
  background:#eef2ff; border:1px solid #cfe3ff; color:#244f87;
}	
	
  .toast{ position:fixed; left:50%; transform:translateX(-50%); bottom:18px; padding:10px 14px; background:#0f172a; color:#fff; border-radius:8px; display:none; z-index:100000; }
</style>	
	
<!-- ===================================================================
     S-14 — HTML: Top bar & grid markup
     =================================================================== -->
<div class="topbar">
  <div class="brand">
    <img class="logo" src="img/IPCA.png" alt="IPCA">
    <span><?php echo safe($LOC_NAME); ?></span>
  </div>
  <button class="btn">☰ Schedule</button>
  <div class="menu">
  	<button class="btn ghost" onclick="goToday()">Today</button>
  	<button class="btn" onclick="navDay(-1)">←</button>
  	<button class="btn" onclick="navDay(1)">→</button>
  	<button class="btn" id="remindersBtn" onclick="goReminders()">🔔 Reminders</button>
  </div>
  <div id="whoami" style="margin-left:12px;color:#e8eefc;"></div>
</div>

<div class="headerBar">
  <input type="date" id="pick" value="<?php echo safe($date); ?>" onchange="pickDate(this.value)">
  <div id="dateHuman" class="dateTitle"><?php echo safe($date_long); ?></div>
  <button class="newBtn" id="newBtn">+ New Reservation</button>
</div>

<div id="debugBox" style="display:none;margin:8px 16px;padding:10px 12px;border:1px dashed #cfd5e3;border-radius:10px;background:#fafcff;color:#445066"></div>	
	
<div class="wrap">
  <div class="card">
    <div class="timeHeader">
      <div class="hLeft"></div>
      <div class="hRight" id="hRight"></div>
    </div>
    <div class="rowsGrid" id="rowsGrid"><div id="overlay"></div></div>
  </div>
</div>

<div class="foot"><div id="clock"></div><div></div></div>

<!-- ===================================================================
     S-15 — HTML: Reservation Modal
     =================================================================== -->
<!-- Modal -->
<div class="modalWrap" id="modalWrap">
  <div class="modal">
    <div class="modalHd">
      <h3 id="modalTitle">New Reservation</h3>
      <button class="btnSec" id="closeModal">✕</button>
    </div>
    <div class="modalBd">
      <div class="formGrid">

        <div class="formRow">
          <label for="f_type">Reservation Type</label>
          <select id="f_type">
            <option>Flight Training</option>
            <option>Briefing</option>
            <option>AR Briefing</option>
            <option>Simulator</option>
            <option>Theory</option>
            <option>FAA Mock Theory Exam</option>
            <option>EASA Mock Theory Exam</option>
            <option>FAA Theory Exam</option>
            <option>EASA Theory Exam</option>
            <option>FAA Practical Exam</option>
            <option>EASA Practical Exam</option>
            <option>Meeting</option>
            <option>Assessment</option>
            <option>Maintenance</option>
            <option>Personal</option>
          </select>
        </div>

        <div class="formRow">
          <label for="f_student">Student/User (Ctrl/Cmd-click for multiple)</label>
          <select id="f_student" multiple size="10"><option value="">Loading…</option></select>
        </div>

        <div class="formRow">
          <label for="f_sdate">Start Date</label>
          <input type="date" id="f_sdate">
        </div>

        <div class="formRow">
          <label for="f_stime">Start Time</label>
          <select id="f_stime"></select>
        </div>

        <div class="formRow">
          <label for="f_edate">End Date</label>
          <input type="date" id="f_edate">
        </div>

        <div class="formRow">
          <label for="f_etime">End Time</label>
          <select id="f_etime"></select>
        </div>

        <div class="formRow">
          <label for="f_device">Device</label>
          <select id="f_device"><option value="">Loading…</option></select>
        </div>

        <div class="formRow">
          <label for="f_staff">Staff/Instructor</label>
          <select id="f_staff"><option value="">Loading…</option></select>
        </div>

        <div class="formRow" style="grid-column:1 / span 2">
          <label for="f_route">Route</label>
          <input type="text" id="f_route" placeholder="e.g. TRM–L35–TRM via Banning Pass">
        </div>

        <div class="formRow" id="missionRow" style="grid-column:1 / span 2">
          <label for="f_mission_sel">Mission</label>
          <select id="f_mission_sel" style="display:none"></select>
          <input type="text" id="f_mission_text" placeholder="Mission / notes">
          <div class="note">For Flight/Briefing/Simulator the scenarios are listed automatically; other types allow free text.</div>
        </div>

      </div>
    </div>
    <div class="modalFt">
      <button class="btnSec" id="cancelBtn">Cancel</button>
      <button class="btnSec" id="deleteBtn" style="display:none">Delete</button>
      <button class="btnPri" id="saveBtn">Save</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
	  
<script>

// ===================================================================
// S-16 — JS: Globals & utilities
// ===================================================================

// ===== ES5 =====
var H_START = <?php echo (int)$H_START; ?>;
var H_END   = <?php echo (int)$H_END; ?>;

/* ---------- Utils ---------- */
function buildHourHeader(){
  var hr=document.getElementById('hRight'); hr.innerHTML='';
  for(var h=H_START; h<H_END; h++){
    var d=document.createElement('div');
    d.textContent=(h<10?'0':'')+h+':00';
    hr.appendChild(d);
  }
}
function timeToX(dt){
  var d=new Date(dt.replace(' ','T')),
      mins=d.getHours()*60+d.getMinutes(),
      dayStart=H_START*60,
      total=(H_END-H_START)*60;
  if(mins<dayStart) mins=dayStart;
  if(mins>dayStart+total) mins=dayStart+total;
  return (mins-dayStart)/total*100;
}
function minutesBetween(a,b){
  return Math.max(0, Math.round((new Date(b.replace(' ','T'))-new Date(a.replace(' ','T')))/60000));
}
function spanWidth(mins){ return (mins/((H_END-H_START)*60))*100; }
function esc(s){
  return (s||'').toString().replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}
function toLongDate(dStr){
  return new Date(dStr+'T12:00:00').toLocaleDateString(undefined,{weekday:'long',year:'numeric',month:'long',day:'numeric'});
}

function packLanes(items){
  var sorted=items.slice().sort(function(a,b){return (new Date(a.start))-(new Date(b.start));});
  var lanesEnd=[];
  for(var k=0;k<sorted.length;k++){
    var it=sorted[k], lane=-1;
    for(var i=0;i<lanesEnd.length;i++){ if(new Date(it.start)>=lanesEnd[i]){ lane=i; break; } }
    if(lane===-1){ lane=lanesEnd.length; lanesEnd.push(new Date(it.end)); }
    else { lanesEnd[lane]=new Date(it.end); }
    it._lane=lane;
  }
  return {items:sorted, lanes:lanesEnd.length};
}

function addSection(title){
  var g=document.getElementById('rowsGrid');
  var L=document.createElement('div'); L.className='sectionLabel'; L.textContent=title;
  var R=document.createElement('div'); R.className='sectionSpacer';
  g.appendChild(L); g.appendChild(R);
}
function addRowLabelHTML(html){
  var g=document.getElementById('rowsGrid');
  var L=document.createElement('div'); L.className='rlabel'; L.innerHTML=html; g.appendChild(L);
  return L;
}
function addRowCell(cb){
  var g=document.getElementById('rowsGrid');
  var C=document.createElement('div'); C.className='rcell';
  if(typeof cb==='function'){ cb(C); }
  g.appendChild(C);
  return C;
}

/* Tooltip behavior (fix: ignore click toggle when slot has a reservation id) */
function attachTooltipBehavior(root){
  var slots = root.querySelectorAll('.slot');
  function hideAll(){
    var tips = root.querySelectorAll('.tooltip.show');
    for(var i=0;i<tips.length;i++){
      var t = tips[i]; t.classList.remove('show'); t.classList.remove('above'); t.style.left='';
      var s = t.parentNode; while(s && (!s.classList || !s.classList.contains('slot'))) s = s.parentNode;
      if(s){ s.classList.remove('raise'); }
    }
  }
  function place(slot, tip){
    var rect=slot.getBoundingClientRect(); tip.classList.remove('above'); tip.style.left='0px';
    var forceAbove = slot.getAttribute('data-force-above') === '1';
    var bottom = rect.bottom + 38 + tip.offsetHeight + 8;
    if(forceAbove || bottom > window.innerHeight){ tip.classList.add('above'); }
    var tipRect = tip.getBoundingClientRect();
    var overR = (rect.left + tipRect.width) - (window.innerWidth - 8);
    if(overR>0){ tip.style.left = Math.max(0, -overR) + 'px'; }
  }
  for(var j=0;j<slots.length;j++){
    (function(slot){
      var tip = slot.querySelector('.tooltip'); if(!tip) return;
      slot.addEventListener('mouseenter', function(){ hideAll(); slot.classList.add('raise'); tip.classList.add('show'); place(slot,tip); });
      slot.addEventListener('mouseleave', function(){ tip.classList.remove('show'); tip.classList.remove('above'); tip.style.left=''; slot.classList.remove('raise'); });
      slot.addEventListener('click', function(e){
        if (slot.getAttribute('data-res-id')) return; // let editor open
        e.stopPropagation();
        var open=tip.classList.contains('show');
        hideAll();
        if(!open){ slot.classList.add('raise'); tip.classList.add('show'); place(slot,tip); }
      }, false);
    })(slots[j]);
  }
  document.addEventListener('click', function(){ hideAll(); }, false);
}

/* === Reminders → Primary picker + status bar (shared with reminders.php) === */
function pickPrimaryReminder(list){
  if(!list || !list.length) return null;
  for(var i=0;i<list.length;i++){ if(list[i] && list[i].primary) return list[i]; }
  return list[0];
}


/* === Compact reminder bar (friendly target labels for HOURS & DATE) === */
function statusBarFromReminder(R, current){
  var isDate = (R.track_by === 'DATE');

  // ---------- helpers ----------
  function fmt1(x){ return (Math.round(x*10)/10).toFixed(1); } // one decimal, e.g. 34.8
  function trim10(x){ var s=(Math.round(x*10)/10).toFixed(1); return s.endsWith('.0')?s.slice(0,-2):s; }

  // Build a friendly target label:
  // - Prefer explicit short_label if present (e.g. "100h", "CFI")
  // - For HOURS: try to parse "100h" from name, else fall back to interval_value+"h", else next_due_num (last resort)
  // - For DATE: try to clean the name into a short tag (e.g., "CFI", "Medical"), else use "due"
  function friendlyTargetLabel(R, isDate, nextNum, intervalVal){
    if (R.short_label && String(R.short_label).trim()) return String(R.short_label).trim();

    var nm = (R.name||'').trim();

    if (!isDate){
      var mH = nm.match(/(\d+(?:\.\d+)?)\s*h\b/i);
      if (mH) return mH[1].replace(/\.0$/,'') + 'h';
      if (intervalVal!=null && !isNaN(intervalVal)) return trim10(intervalVal)+'h';
      if (nextNum!=null && !isNaN(nextNum)) return trim10(nextNum)+'h';
      return 'target';
    } else {
      // Strip parentheses and anything after ":" to keep a short tag
      var label = nm.replace(/\(.*?\)/g,'').split(':')[0].trim();
      // Keep it concise: first 1–2 words or a common acronym
      if (label){
        // If it contains an obvious acronym (CFI, IPC, TSA, BFR, MEI, IR), prefer that
        var acr = label.match(/\b(CFI|CFII|MEI|BFR|IPC|IR|ME|SE|TSA|MEDICAL)\b/i);
        if (acr) return acr[1].toUpperCase();
        // else first up to 2 words
        var parts = label.split(/\s+/).filter(Boolean).slice(0,2);
        return parts.length ? parts.join(' ') : 'due';
      }
      return 'due';
    }
  }

  // ---------- remaining ----------
  var rem = (R.remaining!=null && !isNaN(R.remaining)) ? Number(R.remaining) : null;

  // ---------- DATE path ----------
  if (isDate){
    if (R.next_due_date){
      var today = new Date(); today.setHours(12,0,0,0);
      var due   = new Date(String(R.next_due_date)+'T12:00:00');
      rem = Math.ceil((due - today)/86400000);
    }

    // progress
    var total=null, used=null, pct=0;
    var lastD = R.last_completed_date ? new Date(String(R.last_completed_date)+'T12:00:00') : null;
    var nextD = R.next_due_date       ? new Date(String(R.next_due_date)+'T12:00:00')       : null;
    if (lastD && nextD){
      total = Math.max(1, Math.round((nextD - lastD)/86400000));
      if (!isNaN(rem)) used = Math.max(0, total - rem);
    } else if (R.interval_unit==='DAYS' && R.interval_value){
      total = Number(R.interval_value); if (!isNaN(rem)) used = Math.max(0,total-rem);
    } else if (R.interval_unit==='MONTHS' && R.interval_value){
      total = Number(R.interval_value)*30; if (!isNaN(rem)) used = Math.max(0,total-rem);
    }
    if (!isNaN(total) && total>0 && !isNaN(used)) pct = Math.max(0, Math.min(100, (used/total)*100));
    else if (!isNaN(rem) && rem<=0) pct=100;

    var color = (!isNaN(rem) && rem<=0) ? '#e53935'
             : (!isNaN(rem) && !isNaN(total) && total>0 && rem/total<=0.25) ? '#f59e0b'
             : '#22c55e';

    var targetLabel = friendlyTargetLabel(R, true, null, null); // e.g., "CFI"
    var shortText = (!isNaN(rem)) ? (Math.max(0, rem)+'d till '+targetLabel) : '—';

    return ''+
      '<div style="display:flex; align-items:center; gap:8px;">' +
        '<div style="flex:0 0 22%; max-width:187px; min-width:110px; height:10px; background:#e6e9ef; border-radius:999px; overflow:hidden;">' +
          '<div style="width:'+pct+'%; height:100%; background:'+color+'; border-radius:999px;"></div>' +
        '</div>' +
        '<div style="font-size:0.85em; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">'+esc(shortText)+'</div>' +
      '</div>';
  }

  // ---------- HOURS path ----------
  // derive numeric next (for progress only; NOT for label)
  var next = (R.next_due_num!=null && !isNaN(R.next_due_num)) ? Number(R.next_due_num) : null;
  if (next==null && R.due_text){
    var mNext = String(R.due_text).match(/Next\s*@\s*[A-Za-z]+:\s*([0-9]+(?:\.[0-9]+)?)/i);
    if (mNext) next = Number(mNext[1]);
  }

  // compute remaining from current meter if possible
  if ((rem==null || isNaN(rem)) && current!=null && !isNaN(current) && next!=null){
    rem = next - Number(current);
  }

  // progress calc
  var total=null, used=null, pct=0;
  var iv   = (R.interval_unit==='HOURS' && R.interval_value!=null && !isNaN(R.interval_value)) ? Number(R.interval_value) : null;
  var last = (R.last_completed_num!=null && !isNaN(R.last_completed_num)) ? Number(R.last_completed_num) : null;

  if (next!=null && current!=null && !isNaN(current)){
    var cur = Number(current);
    rem = next - cur;
    if (!isNaN(last)) { total = next - last; used = cur - last; }
    else if (!isNaN(iv)) { total = iv; used = Math.max(0, total - rem); }
  }
  if ((total==null || isNaN(total)) && !isNaN(rem) && !isNaN(iv)){ total=iv; used=Math.max(0,total-rem); }
  if (!isNaN(total) && total>0 && !isNaN(used)) pct = Math.max(0, Math.min(100, (used/total)*100));
  else if (!isNaN(rem) && rem<=0) pct=100;

  var color = (!isNaN(rem) && rem<=0) ? '#e53935'
           : (!isNaN(rem) && !isNaN(total) && total>0 && rem/total<=0.25) ? '#f59e0b'
           : '#22c55e';

  var targetLabel = friendlyTargetLabel(R, false, next, iv); // e.g., "100h"
  var shortText = (!isNaN(rem) && targetLabel)
    ? (fmt1(Math.max(0, rem)) + 'h till ' + targetLabel)
    : (!isNaN(rem) ? (fmt1(Math.max(0, rem)) + 'h remaining') : '—');

  return ''+
    '<div style="display:flex; align-items:center; gap:8px;">' +
      '<div style="flex:0 0 22%; max-width:187px; min-width:110px; height:10px; background:#e6e9ef; border-radius:999px; overflow:hidden;">' +
        '<div style="width:'+pct+'%; height:100%; background:'+color+'; border-radius:999px;"></div>' +
      '</div>' +
      '<div style="font-size:0.85em; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">'+esc(shortText)+'</div>' +
    '</div>';
}
	
/* ---------- debug banner: disabled ---------- */
function updateDebugBoxSimple(){ 
  var box = document.getElementById('debugBox'); 
  if (box) box.style.display = 'none';
}

/* ---------- Merge reminders → day (final) ---------- */
function mergeRemindersIntoDay(day, rems){
  var OUT = day ? JSON.parse(JSON.stringify(day)) : { devices: [], staff: [] };
  if (!rems || rems.ok !== true) return OUT;

  // Devices
  var dIndex = {};
  for (var i=0;i<(OUT.devices||[]).length;i++){
    var d = OUT.devices[i];
    var key = (d.dev_id!=null)?String(d.dev_id):(d.id!=null?String(d.id):null);
    if(key!=null) dIndex[key]=i;
    if(!d.reminders) d.reminders=[];
  }
  for (var j=0;j<(rems.devices||[]).length;j++){
    var rd=rems.devices[j], k=(rd&&rd.id!=null)?String(rd.id):null;
    if(k!=null && dIndex.hasOwnProperty(k)){
      OUT.devices[dIndex[k]].reminders = rd.reminders||[];
      if(rd.latest_tacho!=null && OUT.devices[dIndex[k]].latest_tacho==null) OUT.devices[dIndex[k]].latest_tacho=rd.latest_tacho;
      if(rd.latest_hobbs!=null && OUT.devices[dIndex[k]].latest_hobbs==null) OUT.devices[dIndex[k]].latest_hobbs=rd.latest_hobbs;
    }
  }

  // Staff
  var sIndex={};
  for(var a=0;a<(OUT.staff||[]).length;a++){
    var s=OUT.staff[a], sk=(s&&s.id!=null)?String(s.id):null;
    if(sk!=null) sIndex[sk]=a;
    if(!s.reminders) s.reminders=[];
  }
  for(var b=0;b<(rems.staff||[]).length;b++){
    var rs=rems.staff[b], rk=(rs&&rs.id!=null)?String(rs.id):null;
    if(rk!=null && sIndex.hasOwnProperty(rk)) OUT.staff[sIndex[rk]].reminders = rs.reminders||[];
  }
  return OUT;
}

/* ---------- fetchDay (clean) ---------- */
function fetchDay(){
  var params = (function(){ return new URLSearchParams(location.search); })();
  var date   = params.get('date') || '<?php echo safe($date); ?>';

  Promise.all([
    fetch('?api=list_day&date='+encodeURIComponent(date))
      .then(function(r){ if(!r.ok){ throw 0; } return r.json(); }),
    fetch('?api=whoami')
      .then(function(r){ return r.json(); }),
    fetch('?api=reminders_list') // same file; no debug/test
      .then(function(r){ return r.json(); })
      .catch(function(){ return { ok:false }; })
  ])
  .then(function(all){
    var day  = all[0];
    var user = all[1];
    var rems = (all[2] && all[2].ok) ? all[2] : null;

    DATA = mergeRemindersIntoDay(day, rems);

    var who = document.getElementById('whoami');
    if (who && user && user.user) who.textContent = user.user.name+' · '+user.user.role;

    buildHourHeader();
    renderRows();

    if (window._timer){ clearInterval(window._timer); }
    window._timer = setInterval(drawLines, 60000);
  })
  .catch(function(){
    DATA = { devices:[], staff:[], date:'<?php echo safe($date); ?>' };
    buildHourHeader();
    renderRows();
  });
}
	
// ===================================================================
// S-17 — JS: Rendering (devices, staff, rows)
// ===================================================================
/* --------- render --------- */
var DATA = null;

function iconForDevType(t){
  var s=(t||'').toString().toUpperCase();
  if(s==='AIRCRAFT') return '✈️ ';
  if(s==='SIMULATOR' || s==='SIM') return '🖥️ ';
  if(s==='BRIEFING' || s==='CLASSROOM') return '🏫 ';
  if(s==='AVP' || s==='AR') return '🥽 ';
  if(s==='OFFICE') return '🏢 ';
  return '• ';
}

/* ---- Canonicalize device type (shared with modal filtering) ---- */
function canonDevType(raw){
  var t = (raw||'').toString().trim().toUpperCase();
  if (t === 'SIM') return 'SIMULATOR';
  if (t === 'CLASSROOM' || t === 'BRIEFING ROOM') return 'BRIEFING';
  if (t === 'AR' || t === 'APPLE VISION PRO' || t.indexOf('AVP')>=0 || t.indexOf('VISION')>=0) return 'AVP';
  if (t.indexOf('ACFT')>=0 || t === 'PLANE') return 'AIRCRAFT';
  return t;
}

/* Small inline debug chip under labels */
function addDbgChip(target, text){
  var s = document.createElement('span');
  s.className = 'dbgChip';
  s.textContent = text;
  target.appendChild(s);
}

function renderRows(){
  var grid=document.getElementById('rowsGrid'); grid.innerHTML='<div id="overlay"></div>';

  /* DEVICES */
  addSection('Devices');
  var devs = (DATA && DATA.devices)? DATA.devices : [];
  for(var di=0; di<devs.length; di++){
    var d = devs[di];
    var name  = d.dev_name || '';
    var model = d.dev_model ? ' <span class="model">('+esc(d.dev_model)+')</span>' : '';
    var icon  = iconForDevType(d.dev_type);
    var labelEl = addRowLabelHTML('<div class="l1">'+icon+esc(name)+model+'</div>');

    // Primary Reminder bar (fallback to old device-maint bar if none)
    (function(){
      var R = (d.reminders && d.reminders.length) ? pickPrimaryReminder(d.reminders) : null;
      if (R){
        var current = (R.track_by==='HOURS_TACHO') ? d.latest_tacho
                    : (R.track_by==='HOURS_HOBBS') ? d.latest_hobbs
                    : null;

        var holder = document.createElement('div');
        holder.className = 'expWrap';
        holder.innerHTML = statusBarFromReminder(R, current);
        labelEl.appendChild(holder);
      } else if (d.dev_due_iso && d.dev_days_left != null){
        var days = parseInt(d.dev_days_left, 10);
        var txt  = (days >= 0 ? (days+' days ') : (Math.abs(days)+' days overdue ')) + (d.dev_maint_type ? d.dev_maint_type : '');
        var color = (days < 0) ? 'bad' : (days <= 30 ? 'bad' : (days <= 120 ? 'warn' : 'ok'));
        var pct   = (days < 0) ? 100 : (days <= 30 ? 90 : (days <= 120 ? 60 : 30));

        var wrap=document.createElement('div'); wrap.className='expWrap';
        var bar=document.createElement('div'); bar.className='expBar';
        var fill=document.createElement('div'); fill.className='expFill '+color; fill.style.width=Math.max(0,Math.min(100,pct))+'%';
        bar.appendChild(fill);
        var t=document.createElement('div'); t.className='expText'; t.textContent=txt.replace(/^\s+|\s+$/g,'');
        wrap.appendChild(bar); wrap.appendChild(t); labelEl.appendChild(wrap);
      }
    })();

    addRowCell((function(nameCopy, devIdCopy){
      return function(cell){
        cell.addEventListener('click', function(e){
          var rect=cell.getBoundingClientRect();
          var rel = (e.clientX - rect.left)/rect.width; if(rel<0) rel=0; if(rel>1) rel=1;
          var mins = H_START*60 + Math.round(rel * ((H_END-H_START)*60));
          mins = Math.floor(mins/15)*15;
          var h = Math.floor(mins/60), m = mins%60;
          openModalPrefill(nameCopy, devIdCopy, h, m);
        }, false);

        // draw reservations
        var rlist = (DATA && DATA.reservations) ? DATA.reservations : [];
        var items=[], i, r;
        for(i=0;i<rlist.length;i++){
          r=rlist[i];
          if(String(r.device_id)===String(devIdCopy)){
            var students = (DATA && DATA.res_students && DATA.res_students[r.res_id]) ? DATA.res_students[r.res_id] : [];
            var studentNames=[];
            for(var s=0;s<students.length;s++){
              var nm=(students[s].first_name||'')+(students[s].last_name?(' '+students[s].last_name):'');
              studentNames.push(nm);
            }
            var labelTime = r.start_dt.substr(11,5)+' - '+r.end_dt.substr(11,5);
            var label = labelTime+' | '+ (studentNames.length? studentNames.join(', ') : '—') + (r.mission_code? (' | '+r.mission_code):'');

            items.push({
              res_id: r.res_id,
              start: r.start_dt, end: r.end_dt, label: label,
              lines:[
                'Device: '+nameCopy,
                'Instructor: '+((r.instr_first||'')+(r.instr_last?(' '+r.instr_last):'')),
                'Students: '+(studentNames.length? studentNames.join(', '): '—'),
                'Mission: '+(r.mission_code? r.mission_code : (r.mission_name||'—')),
                'Time: '+labelTime
              ]
            });
          }
        }
        if(items.length){
          var packed = packLanes(items), SLOT_H=32, GAP=6, TOP0=6;
          var totalH = TOP0 + packed.lanes*(SLOT_H+GAP) - GAP + TOP0; cell.style.minHeight = totalH+'px';
          for(var pi=0; pi<packed.items.length; pi++){
            var it = packed.items[pi];
            var slot=document.createElement('div'); slot.className='slot';
            slot.setAttribute('data-res-id', String(it.res_id));
            slot.addEventListener('click', function(e){
              e.stopPropagation();
              var id = this.getAttribute('data-res-id');
              if(id) openReservationEditor(parseInt(id,10));
            }, false);

            slot.style.left = timeToX(it.start)+'%';
            slot.style.width= spanWidth(minutesBetween(it.start,it.end))+'%';
            slot.style.top  = (TOP0 + it._lane*(SLOT_H+GAP))+'px';
            var txt=document.createElement('span'); txt.className='slotText'; txt.textContent=it.label; slot.appendChild(txt);
            var tip=document.createElement('div'); tip.className='tooltip'; tip.innerHTML=esc(it.lines.join('\n')).replace(/\n/g,'<br>');
            slot.appendChild(tip); cell.appendChild(slot);
          }
        }
      };
    })(name, d.dev_id));
  }

  /* STAFF */
  addSection('Staff');
  var st = (DATA && DATA.staff)? DATA.staff : [];
  for(var si=0; si<st.length; si++){
    var u = st[si];
    var fname = u.first_name || '';
    var lname = u.last_name  || '';
    var roleRaw = (u.role || ''); var rr = roleRaw.toString().toUpperCase();
    var roleAbbrev = (rr === 'ADMIN') ? 'COO' : (rr === 'INSTRUCTOR' ? 'Instructor' : roleRaw);
    var baseName = (fname ? esc(fname) : '') + (lname ? ' ' + esc(lname) : '');
    if (!baseName) baseName = '—';
    var labelHTML = '<div class="l1">' + baseName + ' <span class="roleText">(' + esc(roleAbbrev) + ')</span></div>';
    var sLabelEl = addRowLabelHTML(labelHTML);

    (function(){
      var R = (u.reminders && u.reminders.length) ? pickPrimaryReminder(u.reminders) : null;
      if (!R) return;
      var holder = document.createElement('div');
      holder.className = 'expWrap';
      holder.innerHTML = statusBarFromReminder(R, null);
      sLabelEl.appendChild(holder);
    })();

    addRowCell((function(uObj){
      return function(cell){
        cell.addEventListener('click', function(){
          openModalPrefill(null, null, 9, 0, uObj.id);
        }, false);

        var rlist = (DATA && DATA.reservations) ? DATA.reservations : [];
        for(var i=0;i<rlist.length;i++){
          var r = rlist[i];
          if(String(r.instructor_user_id)===String(uObj.id)){
            var slot=document.createElement('div'); slot.className='slot'; slot.setAttribute('data-force-above','1');
            slot.style.left=timeToX(r.start_dt)+'%';
            slot.style.width=spanWidth(minutesBetween(r.start_dt, r.end_dt))+'%';
            slot.style.top='6px';

            slot.setAttribute('data-res-id', String(r.res_id));
            slot.addEventListener('click', function(e){
              e.stopPropagation();
              var id=this.getAttribute('data-res-id');
              if(id) openReservationEditor(parseInt(id,10));
            }, false);

            var labelTime = r.start_dt.substr(11,5)+' - '+r.end_dt.substr(11,5);
            var txt=document.createElement('span'); txt.className='slotText';
            txt.textContent='Busy: '+(r.dev_name||'')+' · '+labelTime; slot.appendChild(txt);

            var students = (DATA.res_students && DATA.res_students[r.res_id]) ? DATA.res_students[r.res_id] : [];
            var names=[]; for(var s=0;s<students.length;s++){ names.push((students[s].first_name||'')+(students[s].last_name?(' '+students[s].last_name):'')); }
            var tip=document.createElement('div'); tip.className='tooltip';
            tip.innerHTML='Instructor: '+((r.instr_first||'')+(r.instr_last?(' '+r.instr_last):''))+
                          '<br>Device: '+(r.dev_name||'')+
                          '<br>Students: '+(names.length? esc(names.join(', ')) : '—')+
                          '<br>Mission: '+(r.mission_code? esc(r.mission_code) : (r.mission_name? esc(r.mission_name):'—'))+
                          '<br>Time: '+labelTime;
            slot.appendChild(tip); cell.appendChild(slot); cell.style.minHeight='54px';
          }
        }
      };
    })(u));
  }

  drawLines();
  attachTooltipBehavior(document);

  if(DATA && DATA.date){
    var human=toLongDate(DATA.date);
    var el=document.getElementById('dateHuman'); if(el) el.textContent=human;
    var dp=document.getElementById('pick'); if(dp && dp.value!==DATA.date) dp.value=DATA.date;
  }
}

function drawLines(){
  var ov=document.getElementById('overlay'); if(!ov) return; ov.innerHTML='';
  var now=new Date(), cur=now.toISOString().slice(0,10)+' '+now.toTimeString().slice(0,5)+':00';
  var x=timeToX(cur); var ln=document.createElement('div'); ln.className='vline'; ln.style.left=x+'%'; ov.appendChild(ln);
  if(DATA && DATA.sunrise){ var xs=timeToX(DATA.sunrise); var s1=document.createElement('div'); s1.className='vline sun'; s1.style.left=xs+'%'; ov.appendChild(s1); }
  if(DATA && DATA.sunset){ var xe=timeToX(DATA.sunset); var s2=document.createElement('div'); s2.className='vline sun'; s2.style.left=xe+'%'; ov.appendChild(s2); }
}
	
// ===================================================================
// S-18 — JS: Modal logic & population
// ===================================================================
/* ---------- Reservation Modal logic ---------- */
var modalWrap=document.getElementById('modalWrap');
var newBtn=document.getElementById('newBtn');
var closeModal=document.getElementById('closeModal');
var cancelBtn=document.getElementById('cancelBtn');
var saveBtn=document.getElementById('saveBtn');

var f_type=document.getElementById('f_type');
var f_student=document.getElementById('f_student');   // multiple with optgroups
var f_sdate=document.getElementById('f_sdate');
var f_stime=document.getElementById('f_stime');
var f_edate=document.getElementById('f_edate');
var f_etime=document.getElementById('f_etime');
var f_device=document.getElementById('f_device');
var f_staff=document.getElementById('f_staff');
var f_route=document.getElementById('f_route');
var f_mission_sel=document.getElementById('f_mission_sel');
var f_mission_text=document.getElementById('f_mission_text');
var missionRow=document.getElementById('missionRow');
var EDITING_RES_ID = null; // null => create mode; number => edit mode
var modalTitle = document.getElementById('modalTitle');
var deleteBtn  = document.getElementById('deleteBtn');

function showToast(msg){
  var t=document.getElementById('toast'); t.textContent=msg; t.style.display='block';
  setTimeout(function(){ t.style.display='none'; }, 1800);
}
function pad(n){ return (n<10?'0':'')+n; }
function buildTimeSelect(selEl, defaultHM){
  selEl.innerHTML='';
  for (var h=0; h<24; h++){
    for (var m=0; m<60; m+=15){
      var hh=(h<10?'0':'')+h, mm=(m<10?'0':'')+m, v=hh+':'+mm;
      var opt=document.createElement('option'); opt.value=v; opt.text=v; selEl.appendChild(opt);
    }
  }
  if (defaultHM) selEl.value = defaultHM;
}

function setModalMode(isEdit){
  if (modalTitle) modalTitle.textContent = isEdit ? 'Edit Reservation' : 'New Reservation';
  if (deleteBtn)  deleteBtn.style.display = isEdit ? 'inline-block' : 'none';
}	
	
function openModalPrefill(deviceName, deviceId, hour, minute, staffId){
  setModalMode(false);
  EDITING_RES_ID = null;

  modalWrap.style.display='flex';
  var d = (DATA && DATA.date) ? DATA.date : '<?php echo safe($date); ?>';
  f_sdate.value = d; f_edate.value = d;

  var h=hour!=null?hour:10, m=minute!=null?minute:0;
  var endM = (h*60+m)+60; if(endM>23*60+45) endM=23*60+45; var eh=Math.floor(endM/60), em=endM%60;

  buildTimeSelect(f_stime, pad(h)+':'+pad(m));
  buildTimeSelect(f_etime, pad(eh)+':'+pad(em));

  loadFormOptions(function(){
    if(deviceId){ f_device.value = String(deviceId); }
    if(staffId){ f_staff.value = String(staffId); }
    // Optional polish #5: ensure correct mission control is visible immediately
    updateMissionField();
  });
  updateMissionField();
}
	
function openReservationEditor(resId){
  modalWrap.style.display='flex';
  setModalMode(true);
  EDITING_RES_ID = resId;

  var d = (DATA && DATA.date) ? DATA.date : '<?php echo safe($date); ?>';
  f_sdate.value = d; f_edate.value = d;
  buildTimeSelect(f_stime, '09:00');
  buildTimeSelect(f_etime, '10:00');

  loadFormOptions(function(){
    fetch('?api=get_reservation&res_id='+encodeURIComponent(resId))
      .then(function(r){ return r.json(); })
      .then(function(js){
        if(!js.ok){ alert('Error: '+(js.error||'Failed to load reservation')); return; }

        var R = js.reservation || {};
        var sids = js.student_ids || [];

        f_type.value = R.res_type || 'Flight Training';

        var sdt = (R.start_dt||'').split(' ');
        var edt = (R.end_dt||'').split(' ');
        if (sdt.length===2){ f_sdate.value = sdt[0]; buildTimeSelect(f_stime, sdt[1].substr(0,5)); }
        if (edt.length===2){ f_edate.value = edt[0]; buildTimeSelect(f_etime, edt[1].substr(0,5)); }

        if (R.instructor_user_id) f_staff.value = String(R.instructor_user_id);

        for (var i=0;i<f_student.options.length;i++){ f_student.options[i].selected=false; }
        for (var k=0;k<sids.length;k++){
          var v=String(sids[k]); var opt=f_student.querySelector('option[value="'+v+'"]');
          if(opt) opt.selected=true;
        }

        applyDeviceFilter();
        if (R.device_id) f_device.value = String(R.device_id);

        f_route.value = R.route || '';

        var kind = typeToScenarioKind(f_type.value);
        if (kind){
          var combined = (R.mission_code ? R.mission_code+' - ' : '') + (R.mission_name||'');
          f_mission_text.style.display = 'none';
          f_mission_sel.style.display = 'block';

          var sidFirst = '';
          for (var ii=0;ii<f_student.options.length;ii++){
            var o=f_student.options[ii]; if(o.selected && o.value){ sidFirst=o.value; break; }
          }

          if (!sidFirst){
            f_mission_sel.innerHTML = '<option value="">Select a student first…</option>';
          } else {
            fetch('?api=scenarios&student_id='+encodeURIComponent(sidFirst)+'&res_type='+encodeURIComponent(f_type.value))
              .then(function(r){ return r.json(); })
              .then(function(js2){
                f_mission_sel.innerHTML='';
                var hadAny=false;
                if(js2.structure && js2.structure.length){
                  var ph=document.createElement('option'); ph.value=''; ph.text='Select a scenario…';
                  f_mission_sel.appendChild(ph);
                  for(var p=0;p<js2.structure.length;p++){
                    var prog=js2.structure[p];
                    for(var s=0;s<prog.stages.length;s++){
                      var stage=prog.stages[s];
                      var og=document.createElement('optgroup'); og.label=prog.program+' — '+stage.label; f_mission_sel.appendChild(og);
                      for(var phix=0;phix<stage.phases.length;phix++){
                        var phase=stage.phases[phix];
                        if(phase.label && phase.label.trim()!==''){
                          var hdr=document.createElement('option'); hdr.disabled=true; hdr.value=''; hdr.textContent='— '+phase.label+' —';
                          og.appendChild(hdr);
                        }
                        for(var sc=0; sc<(phase.scenarios||[]).length; sc++){
                          var scn=phase.scenarios[sc];
                          var o=document.createElement('option'); o.value=scn.value; o.text='   '+scn.label; og.appendChild(o); hadAny=true;
                        }
                      }
                    }
                  }
                  if(!hadAny){ f_mission_sel.innerHTML='<option value="">No scenarios available</option>'; }
                }else{
                  f_mission_sel.innerHTML='<option value="">No scenarios available</option>';
                }
                if (combined) {
                  var exact = f_mission_sel.querySelector('option[value="'+combined.replace(/"/g,'&quot;')+'"]');
                  if (exact) f_mission_sel.value = combined;
                }
              });
          }
        }else{
          f_mission_sel.style.display='none';
          f_mission_text.style.display='block';
          f_mission_text.value = R.mission_name || '';
        }

        // Optional polish #5 (again, after values populated)
        updateMissionField();
      });
  });
}

function closeModalNow(){ modalWrap.style.display='none'; }

newBtn.addEventListener('click', function(){ openModalPrefill(null,null,10,0); }, false);
closeModal.addEventListener('click', closeModalNow, false);
cancelBtn.addEventListener('click', closeModalNow, false);
	
/* ---------- Data for modal: Students (grouped), Staff, Devices ---------- */
var ALL_DEVICES_CACHE = []; // single global cache

function loadFormOptions(cb){
  var setFallbacks = function(){
    // Students
    f_student.innerHTML='';
    var og=document.createElement('optgroup'); og.label='Unassigned / Other';
    var o=document.createElement('option'); o.value=''; o.text='No active users'; og.appendChild(o);
    f_student.appendChild(og);

    // Staff
    f_staff.innerHTML='';
    var s=document.createElement('option'); s.value=''; s.text='No staff'; f_staff.appendChild(s);

    // Devices
    f_device.innerHTML='';
    var d=document.createElement('option'); d.value=''; d.text='No devices'; f_device.appendChild(d);

    if(typeof cb==='function'){ cb(); }
  };

  fetch('?api=form_options')
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(opt){
      // --- Students grouped by program ---
      f_student.innerHTML='';
      var sg = opt.student_groups || {};
      var order = (sg.group_order || []);
      for(var i=0;i<order.length;i++){
        var label = order[i];
        var og = document.createElement('optgroup'); og.label = label; f_student.appendChild(og);
        var arr = (sg.groups && sg.groups[label]) ? sg.groups[label] : [];
        for(var k=0;k<arr.length;k++){
          var s = arr[k];
          var nameTxt = (s.first_name?s.first_name:'') + (s.last_name?(', '+s.last_name):'');
          var op=document.createElement('option'); op.value=String(s.id); op.text=nameTxt; og.appendChild(op);
        }
      }
      if (sg.unassigned && sg.unassigned.length){
        var og2=document.createElement('optgroup'); og2.label='Unassigned / Other'; f_student.appendChild(og2);
        for(var z=0;z<sg.unassigned.length;z++){
          var su=sg.unassigned[z];
          var nameTxt2 = (su.first_name?su.first_name:'') + (su.last_name?(', '+su.last_name):'');
          var op2=document.createElement('option'); op2.value=String(su.id); op2.text=nameTxt2; og2.appendChild(op2);
        }
      }
      if (!f_student.children.length){
        var og3=document.createElement('optgroup'); og3.label='Unassigned / Other';
        var o3=document.createElement('option'); o3.value=''; o3.text='No active users'; og3.appendChild(o3);
        f_student.appendChild(og3);
      }

      // --- Staff ---
      f_staff.innerHTML='';
      var s0=document.createElement('option'); s0.value=''; s0.text='Select staff…'; f_staff.appendChild(s0);
      var staff = opt.staff || [];
      for(var j=0;j<staff.length;j++){
        var st=staff[j];
        var rr=(st.role||'').toString().toUpperCase();
        var ab=(rr==='ADMIN')?'COO':((rr==='INSTRUCTOR')?'Instructor':st.role||'');
        var txt=(st.first_name?st.first_name:'') + (st.last_name?(' '+st.last_name):'') + (ab?(' ('+ab+')'):'');
        var sop=document.createElement('option'); sop.value=String(st.id); sop.text=txt; f_staff.appendChild(sop);
      }
      if (f_staff.options.length===1){ f_staff.options[0].text='No staff'; }

      // --- Devices ---
      ALL_DEVICES_CACHE = opt.devices || [];

      // Immediately populate the device dropdown (so it doesn't stay on "Loading…")
      applyDeviceFilter();

      if(typeof cb==='function'){ cb(); }
    })
    .catch(function(){
      // Any failure → ensure we don’t leave “Loading…” stuck
      setFallbacks();
    });
}	

// ===================================================================
// S-19 — JS: Filtering, missions, save/delete
// ===================================================================
/* ---------- Device filtering ---------- */
function allowedDevTypesFor(type){
  var t=(type||'').toLowerCase();
  if(t==='flight training' || t==='faa practical exam' || t==='easa practical exam') return ['AIRCRAFT'];
  if(t==='simulator') return ['SIMULATOR'];
  if(t==='briefing' || t==='theory' || t==='faa mock theory exam' || t==='easa mock theory exam' || t==='faa theory exam' || t==='easa theory exam') return ['BRIEFING'];
  if(t==='ar briefing') return ['BRIEFING','AIRCRAFT','AVP']; // show briefing rooms, aircraft, Apple Vision Pro
  if(t==='meeting') return ['OFFICE'];
  if(t==='assessment') return ['BRIEFING','SIMULATOR'];
  return null; // Maintenance / Personal → no extra filter (show all)
}

function applyDeviceFilter(){
  var allow = allowedDevTypesFor(f_type.value); // null => show all
  var prevVal = f_device.value || '';

  f_device.innerHTML='';
  var o0=document.createElement('option'); o0.value=''; o0.text='Select device…'; f_device.appendChild(o0);

  if(!ALL_DEVICES_CACHE.length){
    f_device.options[0].text='No devices';
    return;
  }

  for(var i=0;i<ALL_DEVICES_CACHE.length;i++){
    var dv = ALL_DEVICES_CACHE[i];
    var dt = canonDevType(dv.dev_type);
    if(allow && allow.indexOf(dt)===-1) continue;

    var op=document.createElement('option');
    op.value=String(dv.dev_id);
    op.text=(dv.dev_name||'') + (dv.dev_model?(' ('+dv.dev_model+')'):'');
    f_device.appendChild(op);
  }

  if(f_device.options.length===1){ f_device.options[0].text='No devices'; }
  if(prevVal && f_device.querySelector('option[value="'+prevVal+'"]')) f_device.value=prevVal;
}

/* Mission logic */
function typeToScenarioKind(val){
  var s=(val||'').toLowerCase();
  if(s==='flight training' || s==='faa practical exam' || s==='easa practical exam') return 'FLIGHT';
  if(s==='briefing' || s==='ar briefing') return 'LB';
  if(s==='simulator') return 'FNPT';
  return '';
}

function updateMissionField(){
  var need = typeToScenarioKind(f_type.value);
  if(need){
    f_mission_text.style.display='none';
    f_mission_sel.style.display='block';

    // First selected student (if any)
    var sid = '';
    for(var i=0;i<f_student.options.length;i++){
      var o=f_student.options[i]; if(o.selected && o.value){ sid=o.value; break; }
    }

    if(!sid){
      f_mission_sel.innerHTML='<option value="">Select a student first…</option>';
      return;
    }

    // Fetch + render structured scenarios
    fetch('?api=scenarios&student_id='+encodeURIComponent(sid)+'&res_type='+encodeURIComponent(f_type.value))
      .then(function(r){ return r.json(); })
      .then(function(js){
        f_mission_sel.innerHTML='';
        var hadAny = false;

        if(js.structure && js.structure.length){
          var ph=document.createElement('option'); ph.value=''; ph.text='Select a scenario…';
          f_mission_sel.appendChild(ph);

          for(var p=0; p<js.structure.length; p++){
            var prog = js.structure[p];

            for(var s=0; s<prog.stages.length; s++){
              var stage = prog.stages[s];
              var og = document.createElement('optgroup');
              og.label = prog.program + ' — ' + stage.label;
              f_mission_sel.appendChild(og);

              if(stage.phases && stage.phases.length){
                for(var phix=0;phix<stage.phases.length;phix++){
                  var phase = stage.phases[phix];
                  if(phase.label && phase.label.trim()!==''){
                    var hdr=document.createElement('option');
                    hdr.disabled=true; hdr.value='';
                    hdr.textContent='— '+phase.label+' —';
                    og.appendChild(hdr);
                  }
                  if(phase.scenarios && phase.scenarios.length){
                    for(var sc=0; sc<phase.scenarios.length; sc++){
                      var scn = phase.scenarios[sc];
                      var o=document.createElement('option');
                      o.value=scn.value;
                      o.text='   '+scn.label;
                      og.appendChild(o);
                      hadAny = true;
                    }
                  }
                }
              }
            }
          }

          if(!hadAny){
            f_mission_sel.innerHTML='<option value="">No scenarios available</option>';
          }
        }else{
          var flat = js.scenarios || [];
          if(flat.length){
            var ph2=document.createElement('option'); ph2.value=''; ph2.text='Select a scenario…';
            f_mission_sel.appendChild(ph2);
            for(var i2=0;i2<flat.length;i2++){
              var t = (flat[i2].sc_code? flat[i2].sc_code+' - ':'') + (flat[i2].sc_name||'');
              var o2=document.createElement('option'); o2.value=t; o2.text=t; f_mission_sel.appendChild(o2);
            }
          }else{
            f_mission_sel.innerHTML='<option value="">No scenarios available</option>';
          }
        }
      })
      .catch(function(){
        f_mission_sel.innerHTML='<option value="">No scenarios available</option>';
      });

  }else{
    f_mission_sel.style.display='none';
    f_mission_text.style.display='block';
  }
}

/* ---- Listeners ---- */
f_type.addEventListener('change', updateMissionField, false);
f_type.addEventListener('change', applyDeviceFilter, false);
f_student.addEventListener('change', updateMissionField, false);

/* Save + overlap handling (Fix #3: remove duplicate/stray opener; keep only this handler) */
saveBtn.addEventListener('click', function (e) {
  if (!f_staff || !f_staff.value) { alert('⚠️ Please select a Staff Member.'); f_staff&&f_staff.focus(); return; }

  var selectedStudents = [];
  for (var i = 0; i < f_student.options.length; i++) {
    var o = f_student.options[i];
    if (o.selected && o.value) selectedStudents.push(parseInt(o.value,10));
  }
  if (!selectedStudents.length) { alert('⚠️ Please select at least one Student/User.'); f_student&&f_student.focus(); return; }
  if (!f_device || !f_device.value) { alert('⚠️ Please select a Device.'); f_device&&f_device.focus(); return; }

  var needScenario = typeToScenarioKind(f_type.value);
  var missionValue;
  if (needScenario) {
    if (!f_mission_sel || !f_mission_sel.value) { alert('⚠️ Please choose a Mission/Scenario.'); f_mission_sel&&f_mission_sel.focus(); return; }
    missionValue = f_mission_sel.value;
  } else {
    var txt = (f_mission_text && f_mission_text.value) ? f_mission_text.value.replace(/^\s+|\s+$/g,'') : '';
    if (!txt) { alert('⚠️ Please enter a Mission/Notes description.'); f_mission_text&&f_mission_text.focus(); return; }
    missionValue = txt;
  }

  var payload = {
    type: f_type.value,
    student_ids: selectedStudents,
    start_date: f_sdate.value, start_time: f_stime.value,
    end_date:   f_edate.value, end_time:   f_etime.value,
    device_id:  f_device.value || null,
    staff_id:   f_staff.value || null,
    route:      f_route.value || '',
    mission:    missionValue || ''
  };

  var url = '?api=create_reservation';
  if (EDITING_RES_ID != null) {
    payload.res_id = EDITING_RES_ID;
    url = '?api=update_reservation';
  }

  function doPost(p){
    fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(p) })
      .then(function(r){ return r.json(); })
      .then(function(js){
        if(js.ok){
          modalWrap.style.display='none';
          setModalMode(false);
          showToast(EDITING_RES_ID!=null ? 'Reservation updated.' : 'Reservation saved.');
          EDITING_RES_ID = null;
          fetchDay();
        }else if(js.overlap){
          var lines=['Overlap detected:'];
          if(js.conflicts.device){ lines.push('• Device conflict ('+js.conflicts.device.length+')'); }
          if(js.conflicts.instructor){ lines.push('• Instructor conflict ('+js.conflicts.instructor.length+')'); }
          if(js.conflicts.students){ lines.push('• Student conflict ('+js.conflicts.students.length+')'); }
          lines.push('Save anyway?');
          if(confirm(lines.join('\n'))){
            p.allow_overlap = 1;
            doPost(p);
          }
        }else if(js.error){
          alert('Error: '+js.error);
        }else{
          alert('Unexpected response.');
        }
      });
  }
  doPost(payload);
}, false);

deleteBtn.addEventListener('click', function(){
  if (EDITING_RES_ID==null) return;
  if (!confirm('Delete this reservation? This cannot be undone.')) return;

  fetch('?api=delete_reservation', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({res_id: EDITING_RES_ID})
  })
  .then(function(r){ return r.json(); })
  .then(function(js){
    if(js.ok){
      modalWrap.style.display='none';
      setModalMode(false);
      EDITING_RES_ID=null;
      showToast('Reservation deleted.');
      fetchDay();
    }else{
      alert('Error: '+(js.error||'Delete failed'));
    }
  });
}, false);
	
// ===================================================================
// S-20 — JS: Navigation, clock & boot
// ===================================================================
/* Navigation & clock */
function parseYMD(s){ var a=s.split('-'); return new Date(+a[0],(+a[1])-1,+a[2]); }
function fmtYMD(d){
  var y=d.getFullYear(), m=('0'+(d.getMonth()+1)).slice(-2), da=('0'+d.getDate()).slice(-2);
  return y+'-'+m+'-'+da;
}
function pickDate(v){
  var p=new URLSearchParams(location.search),
      hs=p.get('hstart')||<?php echo (int)$H_START; ?>,
      he=p.get('hend')  ||<?php echo (int)$H_END; ?>;
  location.search='?date='+encodeURIComponent(v)+'&hstart='+hs+'&hend='+he+(p.get('debug')?'&debug=1':'');
}
function navDay(delta){
  var inp=document.getElementById('pick');
  var cur = (inp && inp.value) ? inp.value :
            (new URLSearchParams(location.search).get('date') || '<?php echo safe($date); ?>');
  var d=parseYMD(cur); d.setDate(d.getDate()+(delta||0));
  pickDate(fmtYMD(d));
}
function goToday(){ pickDate('<?php echo safe($today); ?>'); }
function tick(){ var c=document.getElementById('clock'); if(c) c.textContent=new Date().toLocaleTimeString(); }
setInterval(tick,1000); tick();

function currentDateParam(){
  var inp = document.getElementById('pick');
  return (inp && inp.value) ? inp.value : ((DATA && DATA.date) || '<?php echo safe($date); ?>');
}

function goReminders(){
  var d = currentDateParam();
  // If you want to preserve hour window, you can add &hstart=&hend= as well.
  location.href = '?page=reminders&date=' + encodeURIComponent(d);
}	
	
/* Boot */
buildHourHeader();
fetchDay();

/* Resize: redraw guide lines (light debounce) */
(function(){
  var _t=null;
  window.addEventListener('resize', function(){
    if(_t) clearTimeout(_t);
    _t=setTimeout(function(){ drawLines(); }, 80);
  }, {passive:true});
})();
</script>	
	
	