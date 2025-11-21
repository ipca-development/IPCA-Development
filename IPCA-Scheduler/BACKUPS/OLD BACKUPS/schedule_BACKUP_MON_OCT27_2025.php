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
// S-05E — PHP: API reminders_get (read single reminder by id)
// ===================================================================
if ($api === 'reminders_get') {
  @ini_set('display_errors','0'); @error_reporting(E_ERROR | E_PARSE);

  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if (!$id) jexit(array('ok'=>false, 'error'=>'Missing id'));

  try{
    $r = q('SELECT
              id, target_type, target_id, name, short_label, track_by,
              last_completed_num, last_completed_date,
              interval_value, interval_unit, calendar_month,
              warn_value, warn_unit,
              next_due_num, next_due_date,
              primary_flag, send_email, send_slack, notes, updated_at, created_at
            FROM reminders
            WHERE id = ?
            LIMIT 1', array($id))->fetch();

    if (!$r) jexit(array('ok'=>false, 'error'=>'Reminder not found'));

    // Return in the exact shape reminders.php expects
    jexit(array(
      'ok' => true,
      'id' => (int)$r['id'],
      'target_type' => (string)$r['target_type'],
      'target_id'   => (int)$r['target_id'],
      'name'        => (string)$r['name'],
      'short_label' => isset($r['short_label']) ? (string)$r['short_label'] : null,
      'track_by'    => (string)$r['track_by'],

      'last_completed_num'  => $r['last_completed_num'] !== null ? (float)$r['last_completed_num'] : null,
      'last_completed_date' => $r['last_completed_date'] ?: null,

      'interval_value' => $r['interval_value'] !== null ? (int)$r['interval_value'] : null,
      'interval_unit'  => $r['interval_unit'] ?: null,
      'calendar_month' => !empty($r['calendar_month']) ? 1 : 0,

      'warn_value' => $r['warn_value'] !== null ? (int)$r['warn_value'] : null,
      'warn_unit'  => $r['warn_unit'] ?: null,

      'next_due_num'  => $r['next_due_num'] !== null ? (float)$r['next_due_num'] : null,
      'next_due_date' => $r['next_due_date'] ?: null,

      'primary_flag' => !empty($r['primary_flag']) ? 1 : 0,
      'send_email'   => !empty($r['send_email']) ? 1 : 0,
      'send_slack'   => !empty($r['send_slack']) ? 1 : 0,
      'notes'        => $r['notes'] ?: null,
      'updated_at'   => $r['updated_at'] ?: null
    ));
  }catch(Exception $e){
    jexit(array('ok'=>false, 'error'=>$e->getMessage()));
  }
}	
	
// ===================================================================
// S-05F — PHP: API reminders_update (update existing reminder)
// ===================================================================
if ($api === 'reminders_update') {
  @ini_set('display_errors','0'); @error_reporting(E_ERROR | E_PARSE);

  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) $in = array();

  $id = isset($in['id']) ? (int)$in['id'] : 0;
  if (!$id) jexit(array('ok'=>false, 'error'=>'Missing id'));

  // Normalize/Default
  $track_by = isset($in['track_by']) ? $in['track_by'] : 'DATE';

  // ---------- BACKEND GUARD: compute next_due_* if UI didn’t send ----------
  if ($track_by === 'DATE') {
    $next_due_date = isset($in['next_due_date']) ? $in['next_due_date'] : null;

    if (!$next_due_date) {
      if (!empty($in['last_completed_date']) && !empty($in['interval_value']) && !empty($in['interval_unit'])) {
        $base = new DateTime($in['last_completed_date']);
        if ($in['interval_unit'] === 'DAYS') {
          $base->modify('+'.intval($in['interval_value']).' days');
        } else {
          $base->modify('+'.intval($in['interval_value']).' months');
          if (!empty($in['calendar_month'])) $base->modify('last day of this month');
        }
        $next_due_date = $base->format('Y-m-d');
      } elseif (!empty($in['due_date'])) {
        $next_due_date = $in['due_date'];
      }
    }

    $in['next_due_date'] = $next_due_date ?: null;
    $in['next_due_num']  = null;

  } else {
    $next_due_num = isset($in['next_due_num']) ? $in['next_due_num'] : null;

    if ($next_due_num === null) {
      if (!empty($in['last_completed_num']) && !empty($in['interval_value']) && ($in['interval_unit'] === 'HOURS')) {
        $next_due_num = round(floatval($in['last_completed_num']) + floatval($in['interval_value']), 1);
      } elseif (!empty($in['due_num'])) {
        $next_due_num = floatval($in['due_num']);
      }
    }

    $in['next_due_num']  = ($next_due_num !== null ? (float)$next_due_num : null);
    $in['next_due_date'] = null;
  }

  // ---------- UPDATE ----------
  try{
    q('UPDATE reminders
       SET target_type=?,
           target_id=?,
           name=?,
           short_label=?,
           track_by=?,
           last_completed_num=?,
           last_completed_date=?,
           interval_value=?,
           interval_unit=?,
           calendar_month=?,
           warn_value=?,
           warn_unit=?,
           next_due_num=?,
           next_due_date=?,
           primary_flag=?,
           send_email=?,
           send_slack=?,
           notes=?,
           updated_at=NOW()
       WHERE id=?',
      array(
        isset($in['target_type']) ? $in['target_type'] : 'DEVICE',
        isset($in['target_id']) ? intval($in['target_id']) : 0,
        isset($in['name']) ? $in['name'] : '',
        isset($in['short_label']) ? substr(trim($in['short_label']),0,4) : null,
        $track_by,
        isset($in['last_completed_num']) ? $in['last_completed_num'] : null,
        isset($in['last_completed_date']) ? $in['last_completed_date'] : null,
        isset($in['interval_value']) ? intval($in['interval_value']) : null,
        isset($in['interval_unit']) ? $in['interval_unit'] : ($track_by==='DATE'?'DAYS':'HOURS'),
        !empty($in['calendar_month']) ? 1 : 0,
        isset($in['warn_value']) ? intval($in['warn_value']) : null,
        isset($in['warn_unit']) ? $in['warn_unit'] : null,
        $in['next_due_num'],
        $in['next_due_date'],
        !empty($in['primary_flag']) ? 1 : 0,
        !empty($in['send_email']) ? 1 : 0,
        !empty($in['send_slack']) ? 1 : 0,
        isset($in['notes']) ? $in['notes'] : null,
        $id
      )
    );

    jexit(array('ok'=>true, 'id'=>$id,
      'computed'=>array('next_due_num'=>$in['next_due_num'], 'next_due_date'=>$in['next_due_date'])
    ));
  }catch(Exception $e){
    jexit(array('ok'=>false, 'error'=>$e->getMessage()));
  }
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
  
	
/* --- S-05X — API: cohort_list (active only, ordered) --- */
if ($api==='cohort_list') {
  try{
    $rows = q('SELECT id, name, program, start_date, end_date, active
               FROM cohorts
               WHERE active=1
               ORDER BY name ASC')->fetchAll();
    jexit(array('ok'=>true,'cohorts'=>$rows));
  }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
}

/* --- S-05Y — API: cohort_students?ids=1,2,3  (distinct users) --- */
if ($api==='cohort_students') {
  $idsRaw = isset($_GET['ids']) ? trim($_GET['ids']) : '';
  if ($idsRaw==='') jexit(array('ok'=>true,'students'=>array(), 'groups'=>array()));
  $ids = array();
  foreach(explode(',',$idsRaw) as $x){ $n=(int)$x; if($n>0) $ids[]=$n; }
  if (!count($ids)) jexit(array('ok'=>true,'students'=>array(), 'groups'=>array()));

  try{
    $ph = implode(',', array_fill(0,count($ids),'?'));
    $sql = 'SELECT DISTINCT u.userid AS id, u.voornaam AS first_name, u.naam AS last_name,
                   c.id AS cohort_id, c.name AS cohort_name
            FROM cohort_members m
            JOIN users u   ON u.userid = m.student_id
            JOIN cohorts c ON c.id     = m.cohort_id
            WHERE m.cohort_id IN ('.$ph.')
            ORDER BY c.name ASC, u.voornaam ASC, u.naam ASC';
    $rows = q($sql,$ids)->fetchAll();

    // pack by cohort
    $groups=array(); $flat=array();
    for($i=0;$i<count($rows);$i++){
      $r=$rows[$i];
      $cid=(int)$r['cohort_id']; $cname=(string)$r['cohort_name'];
      if(!isset($groups[$cid])) $groups[$cid]=array('id'=>$cid,'name'=>$cname,'users'=>array());
      $u=array('id'=>(int)$r['id'],'first_name'=>$r['first_name'],'last_name'=>$r['last_name']);
      $groups[$cid]['users'][]=$u;
      $flat[]=$u;
    }
    // to array
    $grpArr = array(); foreach($groups as $g){ $grpArr[]=$g; }
    jexit(array('ok'=>true,'students'=>$flat,'groups'=>$grpArr));
  }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
}	
	
	
/* =========================================================
   ?api=ai_schedule — AI-generated Draft Creator
   Compatible with PHP 5.3
   Table: reservation_drafts
   ========================================================= */
if (isset($_GET['api']) && $_GET['api'] === 'ai_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');

  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true);
  if (!is_array($body)) $body = array();
  $prompt = isset($body['prompt']) ? trim($body['prompt']) : '';

  if ($prompt === '') {
    echo json_encode(array('ok'=>false,'error'=>'Empty prompt'));
    exit;
  }

  // ===== Parse text command =====
  $txt = strtolower($prompt);
  $date = null;
  if (preg_match('/(\d{4}-\d{2}-\d{2})/', $txt, $m)) $date = $m[1];
  elseif (strpos($txt, 'tomorrow') !== false) $date = date('Y-m-d', strtotime('+1 day'));
  else $date = date('Y-m-d');

  $start = ''; $end = '';
  if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $prompt, $m)) {
    $start = $m[1]; $end = $m[2];
  }

  $device = null;
  if (preg_match('/device\s*:?\s*(\d+)/i', $prompt, $m)) $device = intval($m[1]);

  $instructor = null;
  if (preg_match('/\b(instructor|instr|staff)\s*:?\s*(\d+)/i', $prompt, $m)) $instructor = intval($m[2]);

  $students = '';
  if (preg_match('/\bstudents?\s*:?\s*([0-9,\s]+)/i', $prompt, $m)) {
    $students = preg_replace('/\s+/', '', $m[1]);
  }

  $mission_code = ''; $mission_name = '';
  if (preg_match('/mission\s*:\s*"([^"]+)"/i', $prompt, $m)) {
    $mission_name = trim($m[1]);
  } elseif (preg_match('/mission\s*:\s*([^\r\n]+)/i', $prompt, $m)) {
    $mission_name = trim($m[1]);
  }

  // Basic validation
  if (!$start || !$end) {
    echo json_encode(array('ok'=>false,'error'=>'Missing time range (use HH:MM-HH:MM)'));
    exit;
  }

  $start_dt = $date.' '.$start.':00';
  $end_dt   = $date.' '.$end.':00';

  // ===== Insert into reservation_drafts =====
  global $mysqli;
  if (!$mysqli) {
    echo json_encode(array('ok'=>false,'error'=>'No mysqli connection available'));
    exit;
  }

  $sql = sprintf(
    "INSERT INTO reservation_drafts
     (created_by, created_at, res_type, start_dt, end_dt, device_id, instructor_user_id, mission_name, student_ids)
     VALUES (0, NOW(), 'AI_DRAFT', '%s', '%s', %s, %s, '%s', '%s')",
    mysqli_real_escape_string($mysqli, $start_dt),
    mysqli_real_escape_string($mysqli, $end_dt),
    $device ? intval($device) : 'NULL',
    $instructor ? intval($instructor) : 'NULL',
    mysqli_real_escape_string($mysqli, $mission_name),
    mysqli_real_escape_string($mysqli, $students)
  );

  if (!mysqli_query($mysqli, $sql)) {
    echo json_encode(array('ok'=>false,'error'=>mysqli_error($mysqli)));
    exit;
  }

  $draft_id = mysqli_insert_id($mysqli);

  echo json_encode(array(
    'ok' => true,
    'reply' => 'Draft created (#'.$draft_id.') for '.$date.' '.$start.'-'.$end.
               ($device ? ' | device '.$device : '').
               ($instructor ? ' | instructor '.$instructor : '').
               ($students ? ' | students '.$students : '').
               ($mission_name ? ' | mission '.$mission_name : ''),
    'draft_id' => $draft_id,
    'created_drafts' => true
  ));
  exit;
}	
	
/* ===================== AI: list draft reservations ===================== */
if ($api==='draft_list') {
  try{
    $rows = q('SELECT d.*,
      (SELECT CONCAT(u.voornaam, " ", u.naam) FROM users u WHERE u.userid=d.instructor_user_id) AS instructor_name
    FROM reservation_drafts d
    ORDER BY d.start_dt')->fetchAll();

    // Helper strings for the JS renderer
    foreach($rows as &$r){
      $csv = trim((string)$r['student_ids']);
      $r['student_ids_csv'] = $csv;
      $r['student_names'] = '';
      $r['first_student_name'] = null;

      if ($csv !== ''){
        $ids = array_map('intval', explode(',', $csv));
        if (count($ids)){
          $in = implode(',', array_fill(0,count($ids),'?'));
          $names = q('SELECT CONCAT(voornaam," ",naam) AS nm FROM users WHERE userid IN ('.$in.')', $ids)->fetchAll();
          if ($names){
            $r['student_names'] = implode(', ', array_map(function($x){ return $x['nm']; }, $names));
            $r['first_student_name'] = $names[0]['nm'];
          }
        }
      }
    }
    unset($r);

    jexit(array('ok'=>true,'drafts'=>$rows));
  }catch(Exception $e){
    jexit(array('ok'=>false,'error'=>$e->getMessage()));
  }
}	
	
/* ===================== AI: clear all drafts ===================== */
if ($api==='draft_clear') {
  try{
    q('TRUNCATE TABLE reservation_drafts');
    jexit(array('ok'=>true));
  }catch(Exception $e){
    jexit(array('ok'=>false,'error'=>$e->getMessage()));
  }
}	

/* =========================================================
   AI Scheduler – Create drafts (PHP 5.3 compatible)
   Place this right after your other ?api=... handlers and before any HTML.
   ========================================================= */
if (isset($_GET['api']) && $_GET['api'] === 'ai_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // --- read JSON body ---
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = array();
    $prompt = isset($body['prompt']) ? trim($body['prompt']) : '';

    // --- tiny parser helpers (PHP 5.3 safe) ---
    function _ai_today(){ return date('Y-m-d'); }
    function _ai_tomorrow(){ return date('Y-m-d', strtotime('+1 day')); }
    function _ai_parse_time_pair($s){
        $s = trim($s);
        if (preg_match('/\b(\d{1,2}):?(\d{2})\s*[-–]\s*(\d{1,2}):?(\d{2})\b/', $s, $m)) {
            $h1=(int)$m[1]; $mi1=(int)$m[2]; $h2=(int)$m[3]; $mi2=(int)$m[4];
            if ($h1>=0&&$h1<24&&$mi1>=0&&$mi1<60&&$h2>=0&&$h2<24&&$mi2>=0&&$mi2<60) {
                return array(sprintf('%02d:%02d',$h1,$mi1), sprintf('%02d:%02d',$h2,$mi2));
            }
        }
        return null;
    }
    function _ai_first_int_after($hay, $key){
        $re = '/\b'.preg_quote($key,'/').'\s*[:=]?\s*(\d+)\b/i';
        if (preg_match($re, $hay, $m)) return (int)$m[1];
        return null;
    }
    function _ai_csv_after($hay, $key){
        $re = '/\b'.preg_quote($key,'/').'\s*[:=]\s*([0-9,\s]+)\b/i';
        if (preg_match($re, $hay, $m)) {
            $parts = preg_split('/\s*,\s*/', trim($m[1]));
            $out = array();
            for ($i=0;$i<count($parts);$i++){
                if ($parts[$i]!=='' && ctype_digit($parts[$i])) $out[] = $parts[$i];
            }
            return $out;
        }
        return array();
    }
    function _ai_extract_mission($hay){
        if (preg_match('/\bmission\s*:\s*"([^"]+)"/i', $hay, $m)) return $m[1];
        if (preg_match('/\bmission\s*:\s*([^\r\n]+)/i', $hay, $m)) return trim($m[1]);
        return '';
    }
    function _ai_extract_date($hay){
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $hay, $m)) return $m[1];
        if (preg_match('/\btomorrow\b/i', $hay)) return _ai_tomorrow();
        if (preg_match('/\btoday\b/i', $hay)) return _ai_today();
        return _ai_today();
    }
    function _ai_extract_times($hay){
        if (preg_match('/([0-9: ]{4,9}\s*[-–]\s*[0-9: ]{4,9})/i', $hay, $m)) {
            return _ai_parse_time_pair($m[1]);
        }
        return null;
    }

    // --- parse prompt ---
    $dateStr  = _ai_extract_date($prompt);
    $times    = _ai_extract_times($prompt);
    if ($times === null) $times = array('09:00','10:00'); // default 1h
    $start_dt = $dateStr . ' ' . $times[0] . ':00';
    $end_dt   = $dateStr . ' ' . $times[1] . ':00';

    $deviceId = _ai_first_int_after($prompt, 'device');
    if ($deviceId === null) $deviceId = _ai_first_int_after($prompt, 'device_id');

    $staffId  = _ai_first_int_after($prompt, 'staff');
    if ($staffId === null) $staffId = _ai_first_int_after($prompt, 'instructor');
    if ($staffId === null) $staffId = _ai_first_int_after($prompt, 'instructor_id');

    $students = _ai_csv_after($prompt, 'students');
    if (!count($students)) {
        $one = _ai_first_int_after($prompt, 'student');
        if ($one !== null) $students = array((string)$one);
    }

    $missionRaw = _ai_extract_mission($prompt);
    $mission_code = '';
    $mission_name = '';
    if ($missionRaw !== '' && preg_match('/^\s*([0-9A-Za-z\-]+)\s*[-–]\s*(.+)$/', $missionRaw, $mm)) {
        $mission_code = $mm[1];
        $mission_name = $mm[2];
    } else {
        $mission_name = $missionRaw;
    }

    if ($deviceId === null) {
        echo json_encode(array('ok'=>false,'error'=>'No device specified. Use "device:ID" (e.g., device:3).'));
        exit;
    }

    // --- find a DB handle: $db or $pdo (PDO) OR $mysqli (mysqli) ---
    $dbh_type = null; // 'pdo' or 'mysqli'
    if (isset($db) && $db instanceof PDO) { $dbh_type='pdo'; $dbh=$db; }
    elseif (isset($pdo) && $pdo instanceof PDO) { $dbh_type='pdo'; $dbh=$pdo; }
    elseif (isset($mysqli) && $mysqli instanceof mysqli) { $dbh_type='mysqli'; $dbh=$mysqli; }
    else { $dbh_type=null; $dbh=null; }

    if ($dbh_type === null) {
        // No fatal — return a clear JSON error so the UI won't show "network error"
        echo json_encode(array(
            'ok'=>false,
            'error'=>'DB handle not found in ai_schedule. Please paste this block into the same file/scope as draft_list/finalize/clear so it shares the DB connection.'
        ));
        exit;
    }

    // --- compose student CSV ---
    $student_csv = count($students) ? implode(',', $students) : '';

    // --- optional name lookups (safe to skip if you don’t have users table) ---
    $instructor_name = '';
    if (!empty($staffId) && $dbh_type==='pdo') {
        try {
            $qI = $dbh->prepare('SELECT first_name,last_name FROM users WHERE id=?');
            $qI->execute(array((int)$staffId));
            $rI = $qI->fetch(PDO::FETCH_ASSOC);
            if ($rI) $instructor_name = trim(($rI['first_name']?$rI['first_name']:'').' '.($rI['last_name']?$rI['last_name']:''));
        } catch (Exception $e) {}
    } elseif (!empty($staffId) && $dbh_type==='mysqli') {
        if ($stmt = $dbh->prepare('SELECT first_name,last_name FROM users WHERE id=?')) {
            $sid = (int)$staffId;
            $stmt->bind_param('i', $sid);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc())) {
                    $instructor_name = trim(($row['first_name']?$row['first_name']:'').' '.($row['last_name']?$row['last_name']:''));
                }
            }
            $stmt->close();
        }
    }

    $student_names = '';
    if ($student_csv !== '') {
        $ids = explode(',', $student_csv);
        $names = array();
        for ($i=0; $i<count($ids); $i++){
            $sid = trim($ids[$i]);
            if ($sid==='' || !ctype_digit($sid)) continue;

            if ($dbh_type==='pdo') {
                try {
                    $qS = $dbh->prepare('SELECT first_name,last_name FROM users WHERE id=?');
                    $qS->execute(array((int)$sid));
                    $rS = $qS->fetch(PDO::FETCH_ASSOC);
                    if ($rS) $names[] = trim(($rS['first_name']?$rS['first_name']:'').' '.($rS['last_name']?$rS['last_name']:''));
                } catch (Exception $e) {}
            } else {
                if ($stmt = $dbh->prepare('SELECT first_name,last_name FROM users WHERE id=?')) {
                    $sid_i = (int)$sid;
                    $stmt->bind_param('i', $sid_i);
                    if ($stmt->execute()) {
                        $res = $stmt->get_result();
                        if ($res && ($row = $res->fetch_assoc())) {
                            $names[] = trim(($row['first_name']?$row['first_name']:'').' '.($row['last_name']?$row['last_name']:''));
                        }
                    }
                    $stmt->close();
                }
            }
        }
        if (count($names)) $student_names = implode(', ', $names);
    }

    // --- INSERT into your draft table ---
    // Adjust table/column names if needed to match your existing ?api=draft_list output.
    $created = 0;
    if ($dbh_type==='pdo') {
        try {
            $sql = 'INSERT INTO reservations_draft
                    (device_id, instructor_user_id, student_ids_csv, start_dt, end_dt, mission_code, mission_name, instructor_name, student_names)
                    VALUES (?,?,?,?,?,?,?,?,?)';
            $stmt = $dbh->prepare($sql);
            $stmt->execute(array(
                (int)$deviceId,
                ($staffId!==null ? (int)$staffId : null),
                $student_csv,
                $start_dt,
                $end_dt,
                $mission_code,
                $mission_name,
                $instructor_name,
                $student_names
            ));
            $created = 1;
        } catch (Exception $e) {
            echo json_encode(array('ok'=>false,'error'=>'DB insert failed: '.$e->getMessage()));
            exit;
        }
    } else { // mysqli
        $sql = 'INSERT INTO reservations_draft
                (device_id, instructor_user_id, student_ids_csv, start_dt, end_dt, mission_code, mission_name, instructor_name, student_names)
                VALUES (?,?,?,?,?,?,?,?,?)';
        if ($stmt = $dbh->prepare($sql)) {
            $dev_i = (int)$deviceId;
            $ins_i = ($staffId!==null ? (int)$staffId : null);
            $stmt->bind_param(
                'iisssssss',
                $dev_i,
                $ins_i,
                $student_csv,
                $start_dt,
                $end_dt,
                $mission_code,
                $mission_name,
                $instructor_name,
                $student_names
            );
            if (!$stmt->execute()) {
                echo json_encode(array('ok'=>false,'error'=>'DB insert failed (mysqli).'));
                $stmt->close();
                exit;
            }
            $stmt->close();
            $created = 1;
        } else {
            echo json_encode(array('ok'=>false,'error'=>'DB prepare failed (mysqli).'));
            exit;
        }
    }

    // --- reply for chat window ---
    $parts = array();
    $parts[] = 'Created draft';
    $parts[] = 'device #'.$deviceId;
    $parts[] = 'on '.$dateStr.' '.$times[0].'-'.$times[1];
    if (!empty($staffId)) $parts[] = 'staff #'.$staffId;
    if ($student_csv !== '') $parts[] = 'students '.$student_csv;
    if ($mission_code!=='' || $mission_name!=='') {
        $mn = ($mission_code!=='' ? $mission_code.' - ' : '') . $mission_name;
        $parts[] = 'mission "'.$mn.'"';
    }

    echo json_encode(array(
        'ok' => true,
        'reply' => implode(', ', $parts).'.',
        'created_drafts' => (bool)$created
    ));
    exit;
}
	
	
/* ===================== AI: finalize drafts into real reservations ===================== */
if ($api==='draft_finalize') {
  try{
    $drafts = q('SELECT * FROM reservation_drafts ORDER BY start_dt')->fetchAll();
    $ok = 0; $fail = 0; $errs = array();

    foreach($drafts as $d){
      try{
        // Map mission fields (keep as-is)
        $mission_code = $d['mission_code'];
        $mission_name = $d['mission_name'];
        $route = $d['route'];

        // Insert the reservation (adjust column names if your schema differs)
        q('INSERT INTO reservations
            (res_type, start_dt, end_dt, device_id, instructor_user_id, route, mission_code, mission_name, created_at)
           VALUES (?,?,?,?,?,?,?,?,NOW())',
          array($d['res_type'], $d['start_dt'], $d['end_dt'],
                $d['device_id'], $d['instructor_user_id'], $route, $mission_code, $mission_name));
        $newId = db()->lastInsertId();

        // Attach students (CSV -> rows)
        $csv = trim((string)$d['student_ids']);
        if ($csv!==''){
          $ids = array_map('intval', explode(',', $csv));
          foreach($ids as $uid){
            q('INSERT INTO reservations_students (res_id, user_id) VALUES (?,?)', array($newId, $uid));
          }
        }
        $ok++;
      }catch(Exception $e){
        $fail++; $errs[]=$e->getMessage();
      }
    }

    if ($fail===0){
      q('TRUNCATE TABLE reservation_drafts');  // clear drafts only if all succeeded
    }

    jexit(array('ok'=> ($fail===0),
                'message'=>"Finalized $ok draft(s).".($fail? " $fail failed.":""),
                'errors'=>$errs));
  }catch(Exception $e){
    jexit(array('ok'=>false,'error'=>$e->getMessage()));
  }
}	

/* =========================================================
   ?api=ai_match_test — test eligibility for a scenario
   Example:
   ?api=ai_match_test&scenario_id=123&date=2025-10-28&start=09:00&end=11:00
   ========================================================= */
if ($api === 'ai_match_test') {
  header('Content-Type: application/json; charset=utf-8');

  $scenarioId = isset($_GET['scenario_id']) ? (int)$_GET['scenario_id'] : 0;
  $date       = isset($_GET['date'])        ? $_GET['date']        : date('Y-m-d');
  $start      = isset($_GET['start'])       ? $_GET['start']       : '09:00';
  $end        = isset($_GET['end'])         ? $_GET['end']         : '10:00';
  $location = isset($_GET['location']) ? strtoupper(trim($_GET['location'])) : null;	
	
	
  if ($scenarioId <= 0) {
    echo json_encode(array('ok'=>false,'error'=>'Missing or invalid scenario_id'));
    exit;
  }

  require_once __DIR__ . '/includes/ai_scheduler/ai_match.php';

  try {
     $eligible = getEligibleInstructorDevicePairs($scenarioId, $date, $start, $end, null, $location);

    // Fetch names for readability
    $instructors = array();
    if (count($eligible['instructors'])) {
      $inList = implode(',', array_map('intval', $eligible['instructors']));
      if ($location) {
        $instructors = q("SELECT userid, CONCAT(voornaam,' ',naam) AS name
                          FROM users
                          WHERE userid IN ($inList)
                            AND UPPER(work_location)=?", array($location))
                        ->fetchAll(PDO::FETCH_ASSOC);
      } else {
        $instructors = q("SELECT userid, CONCAT(voornaam,' ',naam) AS name
                          FROM users
                          WHERE userid IN ($inList)")
                        ->fetchAll(PDO::FETCH_ASSOC);
      }
    }

    $devices = array();
    if (count($eligible['devices'])) {
      $devList = implode(',', array_map('intval', $eligible['devices']));
      $devices = q("SELECT dev_id, dev_name, dev_type 
                    FROM devices 
                    WHERE dev_id IN ($devList)")->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(array(
      'ok'          => true,
      'scenario_id' => $scenarioId,
      'date'        => $date,
      'time'        => $start . '-' . $end,
      'eligible'    => array(
        'instructors' => $instructors,
        'devices'     => $devices
      )
    ));
  } catch (Exception $e) {
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()));
  }
  exit;
}	



/* =========================================================
   ?api=ai_cohort_next — list next scenario per student in a cohort
   ========================================================= */
if ($api === 'ai_cohort_next') {
  header('Content-Type: application/json; charset=utf-8');
  error_reporting(E_ALL);
  ini_set('display_errors', 1);

  $cid = isset($_GET['cohort_id']) ? (int)$_GET['cohort_id'] : 0;
  if ($cid <= 0) {
    echo json_encode(array('ok'=>false,'error'=>'Missing cohort_id'));
    exit;
  }

  // --- DB credentials (same as progress_api.php)
  $DB_HOST = 'mysql056.hosting.combell.com';
  $DB_NAME = 'ID127947_egl1';
  $DB_USER = 'ID127947_egl1';
  $DB_PASS = 'Plane123';

  require_once __DIR__ . '/includes/ai_scheduler/ai_cohort_next.php';

  // --- Ensure a MySQLi handle exists ---
  global $mysqli;
  if (!isset($mysqli) || !$mysqli) {
    $mysqli = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    @mysqli_set_charset($mysqli, 'utf8');
  }

  if (!$mysqli) {
    echo json_encode(array('ok'=>false,'error'=>'Database connection failed.'));
    exit;
  }

  $rows = get_cohort_next_scenarios($mysqli, $cid);
  echo json_encode(array('ok'=>true,'cohort_id'=>$cid,'students'=>$rows));
  exit;
}

/* =========================================================
   ?api=ai_cohort_plan — bulk-create reservation_drafts for cohorts
   Compatible with PHP 5.3
   ========================================================= */
if ($api === 'ai_cohort_plan') {
  header('Content-Type: application/json; charset=utf-8');
  ini_set('display_errors',1); error_reporting(E_ALL); // remove when confirmed working

  try {
    // ---------- Parse inputs ----------
    $cidSingle = isset($_GET['cohort_id']) ? (int)$_GET['cohort_id'] : 0;
    $cidCsv    = isset($_GET['cohort_ids']) ? trim($_GET['cohort_ids']) : '';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $endDate   = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-d', strtotime('+30 days'));
    $hstart    = isset($_GET['hstart'])     ? max(0, min(23, (int)$_GET['hstart'])) : 8;
    $hend      = isset($_GET['hend'])       ? max(1, min(24, (int)$_GET['hend']))   : 18;
    // If duration_min not supplied, let settings decide per scenario type → use 0 sentinel here
    $durMinReq = isset($_GET['duration_min']) ? max(0, (int)$_GET['duration_min']) : 0;
    $weekdays  = isset($_GET['weekdays']) ? strtolower(trim($_GET['weekdays'])) : 'mon,tue,wed,thu,fri';
    $maxPerDay = isset($_GET['max_per_day']) ? max(1, (int)$_GET['max_per_day']) : 1;
    $location  = isset($_GET['location']) ? strtoupper(trim($_GET['location'])) : null;

    if ($hend <= $hstart) {
      echo json_encode(array('ok'=>false,'error'=>'hend must be greater than hstart'));
      exit;
    }

    // Cohort IDs
    $cohortIds = array();
    if ($cidSingle > 0) $cohortIds[] = $cidSingle;
    if ($cidCsv !== '') {
      foreach (explode(',', $cidCsv) as $c) {
        $n = (int)trim($c);
        if ($n > 0) $cohortIds[] = $n;
      }
    }
    $cohortIds = array_values(array_unique($cohortIds));
    if (!count($cohortIds)) {
      echo json_encode(array('ok'=>false,'error'=>'Missing cohort_id or cohort_ids'));
      exit;
    }

    // Weekday allow-list
    $allowedDow = array();
    foreach (explode(',', $weekdays) as $w) {
      $w = trim($w);
      if ($w !== '') $allowedDow[$w] = true;
    }
    if (!count($allowedDow)) {
      echo json_encode(array('ok'=>false,'error'=>'No valid weekdays provided'));
      exit;
    }

    // ---------- Dependencies ----------
    $root = dirname(__FILE__);
    require_once $root . '/includes/ai_scheduler/ai_cohort_next.php';
    require_once $root . '/includes/ai_scheduler/ai_match.php';
    // settings.php is optional but recommended; we provide safe fallbacks below
    @require_once $root . '/includes/ai_scheduler/settings.php';

    // ---------- Fallbacks if settings helpers are missing ----------
    if (!function_exists('ai_max_duty_hours')) {
      function ai_max_duty_hours() { return 12; }
    }
    if (!function_exists('ai_max_flight_instr_hours')) {
      function ai_max_flight_instr_hours() { return 8; }
    }
    if (!function_exists('ai_duration_for_type_min')) {
      // $typeUpper: 'BRIEFING'|'SIMULATOR'|'FLIGHT'
      function ai_duration_for_type_min($typeUpper, $default=60){
        $t = strtoupper((string)$typeUpper);
        if ($t === 'BRIEFING')  return 60;
        if ($t === 'SIMULATOR') return 120;
        if ($t === 'FLIGHT')    return 120;
        return (int)$default;
      }
    }
    if (!function_exists('ai_instructor_recent_hours')) {
      // Minimal no-op calculator to avoid fatal; returns zeros
      function ai_instructor_recent_hours($instructorId, $startDT, $endDT){
        return array('duty_hr'=>0.0, 'flight_hr'=>0.0);
      }
    }

    // ---------- Small helpers ----------
    function _dow_token($ts) { // mon..sun — PHP 5.3 safe
      $map = array('sun','mon','tue','wed','thu','fri','sat');
      $w = (int)date('w', $ts); // 0=Sun
      return $map[$w];
    }
    function _ymd($ts){ return date('Y-m-d', $ts); }
    function _res_type_for($scTypeRaw){
      $t = strtoupper(trim((string)$scTypeRaw));
      if ($t==='LB' || $t==='BRIEFING') return 'Briefing';
      if ($t==='FNPT' || $t==='SAB' || $t==='SIM' || $t==='SIMULATOR') return 'Simulator';
      return 'Flight Training';
    }

    // --- Overlap check using q() helper (PDO) ---
    function _has_overlap($startDT, $endDT, $deviceId, $instructorId, $studentId) {
      $dev  = (int)$deviceId;
      $inst = (int)$instructorId;
      $stu  = (int)$studentId;

      // existing reservations
      $r1 = q("SELECT 1
               FROM reservations r
               LEFT JOIN reservation_students rs ON rs.res_id = r.res_id
               WHERE r.status IN ('scheduled','checked-in','in-progress')
                 AND (r.device_id = ? OR r.instructor_user_id = ? OR rs.student_user_id = ?)
                 AND r.end_dt   > ?
                 AND r.start_dt < ?
               LIMIT 1", array($dev, $inst, $stu, $startDT, $endDT))->fetch();
      if ($r1) return true;

      // draft overlaps
      $r2 = q("SELECT 1
               FROM reservation_drafts d
               WHERE (d.device_id = ? OR d.instructor_user_id = ? OR FIND_IN_SET(?, COALESCE(d.student_ids,'')) > 0)
                 AND d.end_dt   > ?
                 AND d.start_dt < ?
               LIMIT 1", array($dev, $inst, (string)$stu, $startDT, $endDT))->fetch();
      return (bool)$r2;
    }

    // ---------- Instructor filter (active + optional location, collation safe) ----------
    function _filter_active_instructors($userIds, $location /* may be null */){
      if (!count($userIds)) return array();
      $ids = array(); foreach($userIds as $x){ $ids[] = (int)$x; }
      $inList = implode(',', $ids);

      if ($location) {
        $rows = q("
          SELECT userid
          FROM users
          WHERE userid IN ($inList)
            AND (
              actief_tot IS NULL
              OR actief_tot = '0000-00-00'
              OR actief_tot >= CURDATE()
            )
            AND UPPER(CONVERT(work_location USING latin1)) = UPPER(CONVERT(? USING latin1))
        ", array($location))->fetchAll(PDO::FETCH_COLUMN, 0);
      } else {
        $rows = q("
          SELECT userid
          FROM users
          WHERE userid IN ($inList)
            AND (
              actief_tot IS NULL
              OR actief_tot = '0000-00-00'
              OR actief_tot >= CURDATE()
            )
        ")->fetchAll(PDO::FETCH_COLUMN, 0);
      }
      $out = array(); foreach ($rows as $id) $out[(int)$id] = true;
      return array_keys($out);
    }

    // ---------- Collect students ----------
    $allStudents = array();
    if (count($cohortIds)) {
      $inC = implode(',', $cohortIds);
      $stRows = q("
        SELECT DISTINCT cm.student_id
        FROM cohort_members cm
        INNER JOIN cohorts c ON c.id = cm.cohort_id AND c.active = 1
        WHERE cm.cohort_id IN ($inC)
      ")->fetchAll(PDO::FETCH_COLUMN, 0);
      foreach ($stRows as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) $allStudents[$sid] = true;
      }
    }
    $studentIds = array_keys($allStudents);
    if (!count($studentIds)) {
      echo json_encode(array('ok'=>true,'created'=>0,'note'=>'No active students in cohorts.'));
      exit;
    }

    // ---------- Next scenario per student ----------
    $nextByStudent = array();
    foreach ($studentIds as $sid) {
      if (function_exists('get_next_for_student')) {
        $rows = get_next_for_student($sid);
        if ($rows && isset($rows['sc_id']) && $rows['sc_id'] > 0) $nextByStudent[$sid] = $rows;
      }
    }

    // ---------- Duty/flight limits from settings (with fallbacks above) ----------
    $maxDutyHr   = ai_max_duty_hours();           // e.g. 12
    $maxFlightHr = ai_max_flight_instr_hours();   // e.g. 8

    $created = 0; $details = array();
    $ts = strtotime($startDate);
    $endTs = strtotime($endDate.' 23:59:59');

    while ($ts <= $endTs) {
      $dow = _dow_token($ts);
      if (!isset($allowedDow[$dow])) { $ts = strtotime('+1 day',$ts); continue; }
      $date = _ymd($ts);

      foreach ($studentIds as $sid) {
        if (!isset($nextByStudent[$sid])) continue;
        $sc = $nextByStudent[$sid];
        $placedToday = 0;

        // Scenario-type aware duration:
        // if caller gave duration_min>0 use it; else pull from settings by type
        $resType = _res_type_for(isset($sc['sc_type'])?$sc['sc_type']:'');
        $durMin  = ($durMinReq>0) ? $durMinReq : ai_duration_for_type_min(strtoupper($sc['sc_type']),60);

        // Build slots for THIS scenario type on THIS day
        $slotsHM = array();
        for ($m=$hstart*60; $m+$durMin <= $hend*60; $m+=$durMin){
          $slotsHM[] = sprintf('%02d:%02d', floor($m/60), $m%60);
        }

        foreach ($slotsHM as $hm) {
          if ($placedToday >= $maxPerDay) break;

          $startDT = $date.' '.$hm.':00';
          list($hh,$mm)=explode(':',$hm);
          $eh=$hh; $em=$mm+$durMin;
          $eh+=(int)($em/60); $em=$em%60;
          $endDT=$date.' '.sprintf('%02d:%02d:00',$eh,$em);

          // Ask matcher (already filters by instructor/device location & device active)
          $elig = getEligibleInstructorDevicePairs((int)$sc['sc_id'], $date, $hm, sprintf('%02d:%02d', $eh, $em), null, $location);
          $eligInst = isset($elig['instructors'])?$elig['instructors']:array();
          $eligDev  = isset($elig['devices'])?$elig['devices']:array();
          if (!count($eligInst) || !count($eligDev)) continue;

          // Active + location-safe instructors
          $eligInst = _filter_active_instructors($eligInst, $location);
          if (!count($eligInst)) continue;

          // Enforce duty/flight limits
          $instOk = array();
          foreach ($eligInst as $iid) {
            $h = ai_instructor_recent_hours((int)$iid, $startDT, $endDT);
            $fitsDuty   = ((float)$h['duty_hr']   < (float)$maxDutyHr);
            $fitsFlight = true;
            if ($resType==='Flight Training') {
              $fitsFlight = ((float)$h['flight_hr'] < (float)$maxFlightHr);
            }
            if ($fitsDuty && $fitsFlight) $instOk[] = (int)$iid;
          }
          if (!count($instOk)) continue;

          // Greedy pairing: first instructor/device that doesn’t overlap
          $chosenInst=null; $chosenDev=null;
          foreach ($instOk as $instId){
            foreach ($eligDev as $devId){
              if (!_has_overlap($startDT,$endDT,$devId,$instId,$sid)){
                $chosenInst=(int)$instId; $chosenDev=(int)$devId; break 2;
              }
            }
          }
          if ($chosenInst===null || $chosenDev===null) continue;

          // Create draft
          q("INSERT INTO reservation_drafts
               (res_type,start_dt,end_dt,device_id,instructor_user_id,student_ids,mission_code,mission_name,route)
             VALUES (?,?,?,?,?,?,?,?,?)",
             array($resType,$startDT,$endDT,$chosenDev,$chosenInst,(string)$sid,
                   isset($sc['code'])?$sc['code']:null,
                   isset($sc['name'])?$sc['name']:null,
                   null)
          );
          $created++; $placedToday++;
          $details[]=array('date'=>$date,'start'=>$hm,'student_id'=>$sid,
            'device_id'=>$chosenDev,'instructor'=>$chosenInst,
            'scenario'=>$sc);
        }
      }
      $ts=strtotime('+1 day',$ts);
    }

    echo json_encode(array('ok'=>true,'created'=>$created,'details'=>$details));
    exit;

  } catch (Exception $e) {
    // Never go blank — always JSON error
    echo json_encode(array('ok'=>false,'error'=>$e->getMessage()));
    exit;
  }
}
	
/* ===================== AI: accept a natural-language prompt and create drafts (demo) ===================== */
if ($api==='ai_prompt') {
  $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
  $prompt = isset($in['prompt']) ? trim($in['prompt']) : '';
  $date   = isset($in['date'])   ? $in['date']   : date('Y-m-d');
  $hstart = isset($in['hstart']) ? (int)$in['hstart'] : 8;
  $hend   = isset($in['hend'])   ? (int)$in['hend']   : 18;

  if ($prompt==='') jexit(array('ok'=>false,'error'=>'Empty prompt.'));

  try{
    // DEMO: wipe previous drafts for fast iteration
    q('TRUNCATE TABLE reservation_drafts');

    // Try to pick any device / instructor (both optional)
    $dev  = null; $inst = null;
    try{ $dev  = q('SELECT dev_id FROM devices ORDER BY dev_id LIMIT 1')->fetch(); }catch(Exception $e){}
    try{ $inst = q('SELECT userid FROM users ORDER BY userid LIMIT 1')->fetch(); }catch(Exception $e){}

    $start = $date.' 09:00:00';
    $end   = $date.' 10:00:00';

    q('INSERT INTO reservation_drafts
        (res_type,start_dt,end_dt,device_id,instructor_user_id,student_ids,mission_code,mission_name,route)
       VALUES (?,?,?,?,?,?,?,?,?)',
      array('Flight Training',$start,$end,
            $dev ? (int)$dev['dev_id'] : null,
            $inst ? (int)$inst['userid'] : null,
            '',   // CSV of student IDs (empty in demo)
            null, null, null));

    jexit(array('ok'=>true,
                'message'=>'Draft created for a demo session at 09:00. Click “Refresh Drafts” to view.',
                'refresh'=>true));
  }catch(Exception $e){
    jexit(array('ok'=>false,'error'=>$e->getMessage()));
  }


}	
	
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

	
	/* --- Cohort dropdown --- */
	.dd{position:relative}
	.ddbtn{background:#ffffff22;border:1px solid #ffffff30;color:#fff;padding:6px 10px;border-radius:8px;cursor:pointer}
	.ddbtn:hover{background:#ffffff33}
	.ddpanel{
	  position:absolute; right:0; top:38px; min-width:280px; max-width:360px;
	  background:#fff; color:#1a1f36; border:1px solid #dde3f0; border-radius:12px;
	  box-shadow:0 18px 40px rgba(0,0,0,.25); display:none; z-index:100000; overflow:hidden
	}
	
	
.ddpanel.show{display:block}
.ddhd{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;border-bottom:1px solid #e7eaf2}
.ddhd h4{margin:0;font-size:14px}
.ddsrch{flex:1}
.ddsrch input{width:100%;padding:8px;border:1px solid #cfd5e3;border-radius:8px}
.ddlst{max-height:280px; overflow:auto}
.dditem{display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid #f2f4f8}
.dditem:last-child{border-bottom:none}
.ddft{display:flex;justify-content:space-between;gap:8px;padding:10px 12px;border-top:1px solid #e7eaf2;background:#fafbff}
.ddft .btn{color:#1a1f36;background:#f3f6fb;border-color:#cfd5e3}
.ddft .btn.apply{background:#1e3c72;border-color:#1e3c72;color:#fff}
.badge{background:#eef2ff;border:1px solid #cfe3ff;color:#244f87;border-radius:999px;padding:2px 8px;font-size:12px}	
	
	
	
  .wrap{padding:8px 16px}
  /* let tooltips extend above the first row */
  .card{
  background:#fff;
  border:1px solid #dde3f0;
  border-radius:14px;
  overflow: visible;         /* was hidden */
}

  .timeHeader{display:flex;border-bottom:1px solid var(--grid);background:#fafbff}
  .hLeft{width:<?php echo (int)$LABEL_W; ?>px;flex:0 0 <?php echo (int)$LABEL_W; ?>px;border-right:1px solid #d8dbe2}
  .hRight{flex:1 1 auto;display:grid;grid-template-columns:repeat(<?php echo (int)($H_END-$H_START); ?>,1fr)}
  .hRight div{padding:10px 0;text-align:center;color:var(--muted);font-weight:700;border-left:1px solid var(--grid)}
  .hRight div:first-child{border-left:none}

  .rowsGrid{position:relative;display:grid;grid-template-columns: <?php echo (int)$LABEL_W; ?>px 1fr;grid-auto-rows:minmax(54px,auto);background:#fff;border-top:1px solid #d8dbe2;border-bottom:1px solid #d8dbe2}
  
  .sectionLabel{
  grid-column:1;
  display:flex; align-items:center;
  padding:0 10px; font-weight:700;
  background:#f3f5fb;               /* keep opaque so it hides lines */
  border-top:1px solid #d8dbe2; border-bottom:1px solid #d8dbe2;
  position:relative; z-index:5;      /* <- added */
}
.sectionSpacer{
  grid-column:2;
  background:#f3f5fb;               /* keep opaque so it hides lines */
  border-top:1px solid #d8dbe2; border-bottom:1px solid #d8dbe2; border-left:1px solid #d8dbe2;
  position:relative; z-index:5;      /* <- added */
}	
	
	
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
  .slot.maint { background:#fee2e2; color:#991b1b; border-color:#fecaca; }
  .slot.maint .pill { background:#ef4444; color:#fff; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:800; }
  .rlabel.has-maint .l1 { color:#b91c1c; }
  .unavail-bg {
  position:absolute; left:0; height:100%;
  background:#e5e7eb; opacity:.6;
  pointer-events:none; z-index:1; /* under the slots, above cell bg */
  border-radius:6px;
	}
	
/* Device pill: two lines, truncate long names & keep both lines visible */
.slot.dev {
  height: 46px;                 /* slightly taller */
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: left;
  overflow: visible;
}

.slot.dev .slotText {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
  white-space: nowrap;          /* keep each line on one line */
  overflow: hidden;             /* hide overflow */
  text-overflow: ellipsis;      /* add "..." when text overflows */
  width: 100%;
  padding: 2px 6px;             /* small padding for clarity */
  box-sizing: border-box;
}

.slot.dev .name {
  font-weight: 800;
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.slot.dev .meta {
  font-size: 12px;
  font-weight: 600;
  opacity: 0.85;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
	
/* Staff pill: identical to device pill */
.slot.staff{
  height:46px;
  display:flex; align-items:center; justify-content:center;
  text-align:left; overflow:visible;
}
.slot.staff .slotText{
  display:flex; flex-direction:column; line-height:1.15;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  width:100%; padding:2px 6px; box-sizing:border-box;
}
.slot.staff .name{
  font-weight:800; font-size:13px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.slot.staff .meta{
  font-size:12px; font-weight:600; opacity:.85;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
} 
	
 
	
 
#overlay{
  position:absolute;
  left:<?php echo (int)$LABEL_W; ?>px; right:0; top:0; bottom:0;
  pointer-events:none;
  z-index:1; /* was 4: let dividers sit above */
}	
	
  .vline{position:absolute;top:0;bottom:0;width:2px;background:#e94141;opacity:.95}
  .vline.sun{background:#ffb703}
	
	
  /* Hours dropdown */
#hoursMenu.dd{
  position: absolute; z-index: 20; display:none;
  background:#fff; border:1px solid #e5e7eb; border-radius:10px;
  padding:10px; box-shadow:0 8px 24px rgba(0,0,0,.12);
  width:220px;
}
#hoursMenu.show{ display:block; }
#hoursMenu .row{ display:flex; align-items:center; gap:8px; margin:6px 0; }
#hoursMenu .row label{ width:48px; font-size:12px; color:#64748b; }
#hoursMenu .row.actions{
  display:flex;
  justify-content:flex-end;
  gap:8px;
  margin-top:8px;
  padding-top:8px;
  border-top:1px solid #e5e7eb;
}

/* Quarter-hour grid on overlay */
#overlay .gline{
  position:absolute; top:0; bottom:0; pointer-events:none;
  border-left:1px solid transparent;
}
#overlay .gline.hour     { border-left-color:#cbd5e1; }  /* darker (00) */
#overlay .gline.half     { border-left-color:#e5e7eb; }  /* medium (30) */
#overlay .gline.quarter  { border-left-color:#f1f5f9; }  /* very light (15/45) */	

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
  margin-left:8px;
  padding:2px 6px;
  border-radius:6px;
  font-size:11px;
  background:#eef2ff;
  border:1px solid #cfe3ff;
  color:#244f87;
}

	
	
/* AI draft pill styling */
.slot.draft{
  background:#fff;
  border:2px dashed #90caf9;
  color:#0b5394;
}
.slot.draft .meta{ opacity:0.9; }

	
  .toast{ position:fixed; left:50%; transform:translateX(-50%); bottom:18px; padding:10px 14px; background:#0f172a; color:#fff; border-radius:8px; display:none; z-index:100000; }
	
/* === Tooltip overflow + stacking fixes === */

/* Allow tooltips to overflow out of the cell/grid */
.rcell,
.rowsGrid,
.card,
.wrap {
  overflow: visible;
}

/* Ensure device and staff pills don’t clip their tooltips */
.slot.dev,
.slot.staff {
  overflow: visible;
}

/* Make sure tooltip layers appear above everything else */
.slot .tooltip {
  z-index: 10010;
}
.slot.raise {
  z-index: 10011;
}

	
/* Prevent time header from covering tooltips */
.timeHeader {
  z-index: 5;
}	



	
	
	

	
/* =====================================================
   AI Scheduler Modal Styles (S-15b)
   ===================================================== */
#aiWrap {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  display: none;
  align-items: center;
  justify-content: center;
  background: rgba(20, 25, 35, 0.5);
  z-index: 10001;
}
#aiWrap.show { display: flex; }

#aiWrap .modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 40px rgba(0,0,0,0.2);
  width: 90%;
  max-width: 760px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  z-index: 10002;
}
#aiWrap .modalHd {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(90deg, #1e3c72, #2a5298);
  color: #fff;
  padding: 12px 16px;
}
#aiWrap .modalBd { flex: 1; display: flex; flex-direction: column; background: #f8fafc; }
#aiWrap .modalFt { background: #f1f4f8; padding: 10px 16px; border-top: 1px solid #dde3f0; }

#aiChat {
  background: #f8fafc;
  border-bottom: 1px solid #e5e9f2;
  padding: 12px;
  height: 380px;
  overflow-y: auto;
  font-size: 14px;
  color: #1a1f36;
}

#aiWrap .inputRow { display:flex; gap:8px; padding: 10px 12px; }
#aiWrap input#aiInput {
  flex: 1;
  padding: 10px;
  border: 1px solid #cfd5e3;
  border-radius: 8px;
  font-size: 14px;
}

#aiWrap .btnPri {
  background: #1e3c72;
  border: none;
  color: #fff;
  border-radius: 8px;
  padding: 8px 14px;
  cursor: pointer;
}
#aiWrap .btnSec {
  background: #f3f6fb;
  color: #1a1f36;
  border: 1px solid #cfd5e3;
  border-radius: 8px;
  padding: 8px 14px;
  cursor: pointer;
}
#aiWrap .btnSec:hover, #aiWrap .btnPri:hover { opacity: 0.9; }

/* Optional: dashed style for drafts */
.slot.draft {
  outline: 2px dashed #7c8aa5;
  outline-offset: -3px;
  background: rgba(124,138,165,0.05);
}	


	
	
/* Keep the progress block inside the modal and span both columns */
#studentProgress { grid-column: 1 / -1; max-width: 100%; }

/* Force the 3-column layout (overrides inline flex the JS sets) */
#studentProgress.pgWrap{
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  align-items: start;
  max-width: 100%;
}

/* Cards shouldn’t grow beyond the container */
.pgCard{ 
  padding: 10px 12px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 1px 0 rgba(0,0,0,.03);
  box-sizing: border-box;
  max-width: 100%;
}

/* Prevent long lines from forcing horizontal growth */
.pgRow{ 
  display: flex; 
  align-items: center; 
  gap: 8px; 
  overflow: hidden;            /* <— key */
}
.pgRow > *{ min-width: 0; }    /* allow children to shrink */

.progCol{ display:flex; align-items:center; gap:6px; min-width:0; flex:1 1 0; }
.progCol .code{ font-weight:500; white-space:nowrap; }
.progCol .name, .progCol .date{
  color:#6b7280;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;      /* truncate nicely instead of expanding */
}
.progCol .name.muted{ color:#94a3b8; }
.dot16{ width:16px; height:16px; flex:0 0 16px; }

.pgTitle{ font-weight:600; margin-bottom:6px; color:#111827; }
.pgArrow{ opacity:.5; flex:0 0 auto; }	
	
	
	
	
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
	  
  <button id="hoursBtn" class="btn">Hours ▾</button>

<div id="hoursMenu" class="dd" aria-hidden="true">
  <div class="row">
    <label>Start</label>
    <input id="hStartInp" type="time" step="900" value="05:00">
  </div>
  <div class="row">
    <label>End</label>
    <input id="hEndInp" type="time" step="900" value="23:00">
  </div>
  <div class="row actions">
    <button id="hoursApply" class="btn">Apply</button>
    <button id="hoursReset" class="btn btn-light">Reset</button>
  </div>
</div>	  
	  

<button id="gridToggle" class="btn" data-on="1">Grid: On</button> 	  
	  
  <button class="btn" onclick="navDay(-1)">←</button>
  <button class="btn" onclick="navDay(1)">→</button>
  <button class="btn" id="remindersBtn" onclick="goReminders()">🔔 Reminders</button> 
  <button class="btn ghost" onclick="location.href='cohorts.php'">Cohorts</button>
  <button class="btn ghost" onclick="location.href='availability.php?user_id=1'">My Availability</button>

  <!-- Display Cohorts dropdown -->
  <div class="dd" id="cohortDD">
    <button class="ddbtn" id="cohortBtn">Display Cohorts ▾ <span class="badge" id="cohortBadge" style="display:none"></span></button>
    <div class="ddpanel" id="cohortPanel">
      <div class="ddhd">
        <h4>Display Cohorts</h4>
        <div class="ddsrch"><input type="text" id="cohortSearch" placeholder="Search cohorts…"></div>
      </div>
      <div class="ddlst" id="cohortList"><div class="dditem">Loading…</div></div>
      <div class="ddft">
        <div style="display:flex;gap:6px">
          <button class="btn" id="cohortSelAll">Select All</button>
          <button class="btn" id="cohortSelNone">None</button>
        </div>
        <div style="display:flex;gap:6px">
          <button class="btn" id="cohortClear">Clear</button>
          <button class="btn apply" id="cohortApply">Apply</button>
        </div>
      </div>
    </div>
  </div>
</div>	
	
	
	
  <div id="whoami" style="margin-left:12px;color:#e8eefc;"></div>
</div>

<div class="headerBar">
  <input type="date" id="pick" value="<?php echo safe($date); ?>" onchange="pickDate(this.value)">
  <div id="dateHuman" class="dateTitle"><?php echo safe($date_long); ?></div>
  <div style="display:flex; gap:8px; align-items:center">
    <button class="newBtn" id="newBtn">+ New Reservation</button>
    <button id="aiBtn" class="btnSec" type="button" style="margin-left:8px">AI Scheduler</button>
  </div>
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

		<!-- Training progress (auto-fills after student select) -->
<div class="formRow" id="progressRow" style="grid-column:1 / span 2; display:none;">
  <label>Training Progress</label>
  <div id="progressBox" class="progressBox">
    <!-- JS fills three cards: Briefing / Simulator / Flight -->
  </div>
</div>  
		  
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
			<option>Unavailable</option>   
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

		<!-- Training Progress row (shows last + next per type) -->
<div id="progressRow" class="formRow" style="grid-column:1 / 3; display:none; gap:8px;">
  <label>Training Progress</label>
  <div id="progressBox" class="progressBox"></div>
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

<!-- =====================================================
     S-15b — AI Scheduler Chat Modal
     ===================================================== -->
<div id="aiWrap" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="aiTitle">
    <div class="modalHd">
      <div id="aiTitle">AI Scheduler</div>
      <button id="aiClose" class="btnSec" type="button" aria-label="Close">✕</button>
    </div>

    <div class="modalBd">
      <div id="aiChat"></div>
      <div class="inputRow">
        <input id="aiInput" type="text" placeholder="Describe what you want to schedule…">
        <button id="aiSend" class="btnPri" type="button">Send</button>
      </div>
    </div>

    <div class="modalFt" style="display:flex; gap:8px; justify-content:flex-end;">
      <button id="aiRefreshDrafts" class="btnSec" type="button">Refresh Drafts</button>
      <button id="aiClearDrafts" class="btnSec" type="button">Clear Drafts</button>
      <button id="aiFinalize" class="btnPri" type="button">Finalize Drafts</button>
    </div>
  </div>
</div>	
	
<div class="toast" id="toast"></div>
	  
<style>
/* tiny chat bubbles */
.ai-msg { margin:8px 0; }
.ai-me  { text-align:right; }
.ai-me  .ai-bubble { display:inline-block; background:#e7f0ff; color:#0f2a52; padding:8px 10px; border-radius:10px; }
.ai-bot .ai-bubble { display:inline-block; background:#f5f7fb; color:#1a1f36; padding:8px 10px; border-radius:10px; }
	
</style>	
	


	
<script>
// ===================================================================
// S-16 — JS: Globals & utilities  (CLEANED)
// ===================================================================

// ===== ES5 =====
var H_START = <?php echo (int)$H_START; ?>;
var H_END   = <?php echo (int)$H_END; ?>;

// Respect saved day-window (localStorage) on load
(function(){
  try{
    var s = localStorage.getItem('hoursStart');
    var e = localStorage.getItem('hoursEnd');
    if (s && e){
      var sh = parseInt(s.split(':')[0],10);
      var eh = parseInt(e.split(':')[0],10);
      if (!isNaN(sh) && !isNaN(eh) && eh > sh && sh >= 0 && eh <= 24){
        H_START = sh; H_END = eh;
      }
    }
  }catch(_){}
})();

/* ---------- Hour header ---------- */
function buildHourHeader(){
  var hr = document.getElementById('hRight');
  if (!hr) return;
  hr.innerHTML = '';
  for (var h = H_START; h < H_END; h++){
    var d = document.createElement('div');
    d.textContent = (h<10?'0':'') + h + ':00';
    hr.appendChild(d);
  }
}

/* ---------- Safe text ---------- */
function esc(s){
  return (s==null?'':String(s)).replace(/[&<>"']/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}

/* ---------- Human date (Tuesday, October 14, 2025) ---------- */
function toLongDate(dStr){
  // Treat dStr as local calendar date (no TZ shift)
  var d = new Date(String(dStr)+'T12:00:00');
  return d.toLocaleDateString(undefined, {weekday:'long', year:'numeric', month:'long', day:'numeric'});
}

/* ---------- Server datetime parsing (UTC vs Local) ---------- */
/* Set this based on how your DB stores times. If your DB stores local
   America/Los_Angeles timestamps, set to false. If it stores UTC, set true. */
var SERVER_TIMES_ARE_UTC = false;

function parseServerDateTime(s){
  if (!s) return new Date(NaN);
  var m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/);
  if (!m) {
    var d = new Date(String(s).replace(' ','T'));
    return isNaN(+d) ? new Date(NaN) : d;
  }
  var Y=+m[1], Mo=+m[2]-1, D=+m[3], H=+m[4], Mi=+m[5], S=+(m[6]||0);
  var d = SERVER_TIMES_ARE_UTC ? new Date(Date.UTC(Y,Mo,D,H,Mi,S)) : new Date(Y,Mo,D,H,Mi,S);
  return isNaN(+d) ? new Date(NaN) : d;
}

/* Minutes between two server datetimes (single source of truth) */
function minutesBetween(a, b){
  var A = parseServerDateTime(a);
  var B = parseServerDateTime(b);
  if (isNaN(+A) || isNaN(+B)) return 0;
  return Math.max(0, Math.round((B - A) / 60000));
}

/* X position (%) of a server datetime within [H_START, H_END) */
function timeToX(dt){
  var d = parseServerDateTime(dt);
  if (isNaN(+d)) return 0;
  var mins = d.getHours()*60 + d.getMinutes();
  var dayStart = H_START * 60;
  var total = (H_END - H_START) * 60;
  mins = Math.min(Math.max(mins, dayStart), dayStart + total);
  return ((mins - dayStart) / total) * 100;
}

/* Width (%) helpers */
function spanWidthMins(mins){ return (mins / ((H_END - H_START) * 60)) * 100; }
function widthPct(a, b){ return spanWidthMins(minutesBetween(a, b)); }
/* Back-compat alias if old code still calls spanWidth(mins) */
function spanWidth(mins){ return spanWidthMins(mins); }

/* Lane packing */
function packLanes(items){
  var sorted = items.slice().sort(function(a,b){
    return parseServerDateTime(a.start) - parseServerDateTime(b.start);
  });
  var lanesEnd = [];
  for (var k=0;k<sorted.length;k++){
    var it = sorted[k];
    var start = parseServerDateTime(it.start);
    var end   = parseServerDateTime(it.end);
    var lane = -1;
    for (var i=0;i<lanesEnd.length;i++){
      if (start >= lanesEnd[i]) { lane = i; break; }
    }
    if (lane === -1){ lane = lanesEnd.length; lanesEnd.push(end); }
    else { lanesEnd[lane] = end; }
    it._lane = lane;
  }
  return { items: sorted, lanes: lanesEnd.length };
}

/* ---------- DOM helpers for the rows grid ---------- */
function addSection(title){
  var g = document.getElementById('rowsGrid'); if(!g) return;
  var L = document.createElement('div'); L.className='sectionLabel'; L.textContent=title;
  var R = document.createElement('div'); R.className='sectionSpacer';
  g.appendChild(L); g.appendChild(R);
}
function addRowLabelHTML(html){
  var g = document.getElementById('rowsGrid'); if(!g) return null;
  var L = document.createElement('div'); L.className='rlabel'; L.innerHTML=html; g.appendChild(L);
  return L;
}
function addRowCell(cb){
  var g = document.getElementById('rowsGrid'); if(!g) return null;
  var C = document.createElement('div'); C.className='rcell';
  if (typeof cb === 'function') cb(C);
  g.appendChild(C);
  return C;
}

/* ---------- Tooltip behavior ---------- */
function attachTooltipBehavior(root){
  if (!root) return;
  function hideAll(){
    var tips = root.querySelectorAll('.tooltip.show');
    for (var i=0;i<tips.length;i++){
      var t = tips[i];
      t.classList.remove('show'); t.classList.remove('above'); t.style.left='';
      var s = t.parentNode; while (s && (!s.classList || !s.classList.contains('slot'))) s = s.parentNode;
      if (s) s.classList.remove('raise');
    }
  }
  function place(slot, tip){
    var rect = slot.getBoundingClientRect(); tip.classList.remove('above'); tip.style.left='0px';
    var forceAbove = slot.getAttribute('data-force-above') === '1';
    var bottom = rect.bottom + 38 + tip.offsetHeight + 8;
    if (forceAbove || bottom > window.innerHeight){ tip.classList.add('above'); }
    var tipRect = tip.getBoundingClientRect();
    var overR = (rect.left + tipRect.width) - (window.innerWidth - 8);
    if (overR > 0){ tip.style.left = Math.max(0, -overR) + 'px'; }
  }
  var slots = root.querySelectorAll('.slot');
  for (var j=0;j<slots.length;j++){
    (function(slot){
      var tip = slot.querySelector('.tooltip'); if(!tip) return;
      slot.addEventListener('mouseenter', function(){ hideAll(); slot.classList.add('raise'); tip.classList.add('show'); place(slot,tip); });
      slot.addEventListener('mouseleave', function(){ tip.classList.remove('show'); tip.classList.remove('above'); tip.style.left=''; slot.classList.remove('raise'); });
      slot.addEventListener('click', function(e){
        if (slot.getAttribute('data-res-id')) return; // let editor open
        e.stopPropagation();
        var open = tip.classList.contains('show');
        hideAll();
        if (!open){ slot.classList.add('raise'); tip.classList.add('show'); place(slot,tip); }
      }, false);
    })(slots[j]);
  }
  // Add the document click handler only once
  if (!window.__tipDocBound) {
    document.addEventListener('click', function(){ hideAll(); }, false);
    window.__tipDocBound = true;
  }
}

/* ---------- Reminders helpers used by S-17 ---------- */
function pickPrimaryReminder(list){
  if(!list || !list.length) return null;
  for (var i=0;i<list.length;i++){ if(list[i] && list[i].primary) return list[i]; }
  return list[0];
}

function statusBarFromReminder(R, current){
  function fmt1(x){ return (Math.round(x*10)/10).toFixed(1); }
  function trim10(x){ var s=(Math.round(x*10)/10).toFixed(1); return s.endsWith('.0')?s.slice(0,-2):s; }
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
      var label = nm.replace(/\(.*?\)/g,'').split(':')[0].trim();
      if (label){
        var acr = label.match(/\b(CFI|CFII|MEI|BFR|IPC|IR|ME|SE|TSA|MEDICAL)\b/i);
        if (acr) return acr[1].toUpperCase();
        var parts = label.split(/\s+/).filter(Boolean).slice(0,2);
        return parts.length ? parts.join(' ') : 'due';
      }
      return 'due';
    }
  }

  var isDate = (R.track_by === 'DATE');
  var rem = (R.remaining!=null && !isNaN(R.remaining)) ? Number(R.remaining) : null;

  if (isDate){
    if (R.next_due_date){
      var today = new Date(); today.setHours(12,0,0,0);
      var due   = new Date(String(R.next_due_date)+'T12:00:00');
      rem = Math.ceil((due - today)/86400000);
    }
    var total=null, used=null, pct=0;
    var lastD = R.last_completed_date ? new Date(String(R.last_completed_date)+'T12:00:00') : null;
    var nextD = R.next_due_date       ? new Date(String(R.next_due_date)+'T12:00:00')       : null;
    if (lastD && nextD){ total = Math.max(1, Math.round((nextD - lastD)/86400000)); if (!isNaN(rem)) used = Math.max(0, total - rem); }
    else if (R.interval_unit==='DAYS' && R.interval_value){ total = Number(R.interval_value); if (!isNaN(rem)) used = Math.max(0,total-rem); }
    else if (R.interval_unit==='MONTHS' && R.interval_value){ total = Number(R.interval_value)*30; if (!isNaN(rem)) used = Math.max(0,total-rem); }
    if (!isNaN(total) && total>0 && !isNaN(used)) pct = Math.max(0, Math.min(100, (used/total)*100));
    else if (!isNaN(rem) && rem<=0) pct=100;

    var color = (!isNaN(rem) && rem<=0) ? '#e53935'
             : (!isNaN(rem) && !isNaN(total) && total>0 && rem/total<=0.25) ? '#f59e0b'
             : '#22c55e';
    var targetLabel = friendlyTargetLabel(R, true, null, null);
    var shortText = (!isNaN(rem)) ? (Math.max(0, rem)+'d till '+targetLabel) : '—';

    return ''+
      '<div style="display:flex; align-items:center; gap:8px;">' +
        '<div style="flex:0 0 22%; max-width:187px; min-width:110px; height:10px; background:#e6e9ef; border-radius:999px; overflow:hidden;">' +
          '<div style="width:'+pct+'%; height:100%; background:'+color+'; border-radius:999px;"></div>' +
        '</div>' +
        '<div style="font-size:0.85em; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">'+esc(shortText)+'</div>' +
      '</div>';
  }

  var next = (R.next_due_num!=null && !isNaN(R.next_due_num)) ? Number(R.next_due_num) : null;
  if (next==null && R.due_text){
    var mNext = String(R.due_text).match(/Next\s*@\s*[A-Za-z]+:\s*([0-9]+(?:\.[0-9]+)?)/i);
    if (mNext) next = Number(mNext[1]);
  }
  if ((rem==null || isNaN(rem)) && current!=null && !isNaN(current) && next!=null){ rem = next - Number(current); }

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

  var color2 = (!isNaN(rem) && rem<=0) ? '#e53935'
           : (!isNaN(rem) && !isNaN(total) && total>0 && rem/total<=0.25) ? '#f59e0b'
           : '#22c55e';
  var targetLabel2 = friendlyTargetLabel(R, false, next, iv);
  var shortText2 = (!isNaN(rem) && targetLabel2)
    ? (fmt1(Math.max(0, rem)) + 'h till ' + targetLabel2)
    : (!isNaN(rem) ? (fmt1(Math.max(0, rem)) + 'h remaining') : '—');

  return ''+
    '<div style="display:flex; align-items:center; gap:8px;">' +
      '<div style="flex:0 0 22%; max-width:187px; min-width:110px; height:10px; background:#e6e9ef; border-radius:999px; overflow:hidden;">' +
        '<div style="width:'+pct+'%; height:100%; background:'+color2+'; border-radius:999px;"></div>' +
      '</div>' +
      '<div style="font-size:0.85em; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">'+esc(shortText2)+'</div>' +
    '</div>';
}

/* ---------- Debug banner toggle ---------- */
function updateDebugBoxSimple(){
  var box = document.getElementById('debugBox');
  if (box) box.style.display = 'none';
}

/* ---------- Merge reminders into day payload ---------- */
function mergeRemindersIntoDay(day, rems){
  var OUT = day ? JSON.parse(JSON.stringify(day)) : { devices: [], staff: [] };
  if (!rems || rems.ok !== true) return OUT;

  var dIndex = {};
  for (var i=0;i<(OUT.devices||[]).length;i++){
    var d = OUT.devices[i];
    var key = (d.dev_id!=null)?String(d.dev_id):(d.id!=null?String(d.id):null);
    if (key!=null) dIndex[key]=i;
    if (!d.reminders) d.reminders=[];
  }
  for (var j=0;j<(rems.devices||[]).length;j++){
    var rd=rems.devices[j], k=(rd&&rd.id!=null)?String(rd.id):null;
    if (k!=null && dIndex.hasOwnProperty(k)){
      OUT.devices[dIndex[k]].reminders = rd.reminders||[];
      if (rd.latest_tacho!=null && OUT.devices[dIndex[k]].latest_tacho==null) OUT.devices[dIndex[k]].latest_tacho=rd.latest_tacho;
      if (rd.latest_hobbs!=null && OUT.devices[dIndex[k]].latest_hobbs==null) OUT.devices[dIndex[k]].latest_hobbs=rd.latest_hobbs;
    }
  }

  var sIndex={};
  for (var a=0;a<(OUT.staff||[]).length;a++){
    var s=OUT.staff[a], sk=(s&&s.id!=null)?String(s.id):null;
    if (sk!=null) sIndex[sk]=a;
    if (!s.reminders) s.reminders=[];
  }
  for (var b=0;b<(rems.staff||[]).length;b++){
    var rs=rems.staff[b], rk=(rs&&rs.id!=null)?String(rs.id):null;
    if (rk!=null && sIndex.hasOwnProperty(rk)) OUT.staff[sIndex[rk]].reminders = rs.reminders||[];
  }
  return OUT;
}

/* ---------- Small shared paint helper for unavailability ---------- */
function paintUnavail(cell, leftPct, widthPct, styleExtras){
  var d = document.createElement('div');
  d.className = 'unavail-bg';
  d.style.position = 'absolute';
  d.style.left = leftPct + '%';
  d.style.width = widthPct + '%';
  d.style.top = '0';
  d.style.bottom = '0';
  d.style.pointerEvents = 'none';
  if (styleExtras) for (var k in styleExtras){ d.style[k] = styleExtras[k]; }
  cell.appendChild(d);
}

/* ---------- Day fetch pipeline ---------- */
function fetchDay(){
  var dayStr = currentDateParam();
  var q1 = '?api=list_day&date=' + encodeURIComponent(dayStr) +
           '&hstart=' + H_START + '&hend=' + H_END +
           (new URLSearchParams(location.search).get('debug') ? '&debug=1' : '');

  return Promise.all([
    fetch(q1).then(function(r){ return r.json(); }),
    fetch('?api=reminders_list').then(function(r){ return r.json(); }),
    fetch('?api=draft_list').then(function(r){ return r.json(); })
  ])
  .then(function(parts){
    var day = parts[0] || {};
    var rem = parts[1] || {};
    var drafts = parts[2] || { ok:true, drafts:[] };

    DATA = mergeRemindersIntoDay(day, rem);
    DATA.drafts = (drafts && drafts.ok && drafts.drafts) ? drafts.drafts : [];

    // Update date header + picker
    var human = (typeof toLongDate === 'function') ? toLongDate(DATA.date) : (DATA.date || dayStr);
    var el = document.getElementById('dateHuman'); if (el) el.textContent = human;
    var dp = document.getElementById('pick');      if (dp) dp.value      = (DATA.date || dayStr);

    // Load availability before rendering
    return applyAvailabilityOverlay(DATA.date || dayStr);
  })
  .then(function(){
    // render after availability is loaded
    buildHourHeader();
    renderRows();
  })
  .catch(function(err){
    console.error('fetchDay failed:', err);
    var dbg = document.getElementById('debugBox');
    if (dbg){
      dbg.style.display='block';
      dbg.textContent='Failed to load data (see console)';
    }
    buildHourHeader();
    try { renderRows(); } catch(e){}
  });
}

function applyAvailabilityOverlay(date){
  return fetch('availability_api.php?date=' + encodeURIComponent(date))
    .then(function(r){ return r.json(); })
    .then(function(js){
      if(js && js.ok && js.data){
        DATA.availability = js.data;
      } else {
        DATA.availability = {};
      }
    })
    .catch(function(e){
      console.warn('availability overlay load failed', e);
      DATA.availability = {};
    });
}	

// ===================================================================
// S-17 — JS: Rendering (devices, staff, rows)
// ===================================================================

/* ---------- Timeline guide-line helpers (safe shims) ---------- */
(function(){
  // Clamp [0..1]
  if (typeof window._clamp01 !== 'function') {
    window._clamp01 = function(x){ x = Number(x); if (!isFinite(x)) x = 0; return Math.max(0, Math.min(1, x)); };
  }

  // Convert "minutes since midnight" into % across [H_START, H_END)
  if (typeof window._pctFromMinutes !== 'function') {
    window._pctFromMinutes = function(mins){
      var dayStart = (window.H_START||0) * 60;
      var total    = ( (window.H_END||24) - (window.H_START||0) ) * 60;
      return _clamp01((mins - dayStart) / total) * 100;
    };
  }

  // Parse many “time-ish” inputs → minutes since midnight (local)
  // Accepts: "06:51", "6:51", "2025-10-21 06:51", "2025-10-21T06:51:00",
  //          Date, or anything Date() can parse. Returns null if unknown.
  if (typeof window._minsFromStamp !== 'function') {
    window._minsFromStamp = function(st){
      if (st == null) return null;

      // Date object
      if (Object.prototype.toString.call(st) === '[object Date]' && !isNaN(st)) {
        return st.getHours()*60 + st.getMinutes();
      }

      var s = String(st).trim();

      // Fast path: HH:MM
      var m = s.match(/\b(\d{1,2}):(\d{2})\b/);
      if (m) {
        var h = +m[1], mi = +m[2];
        if (h>=0 && h<24 && mi>=0 && mi<60) return h*60 + mi;
      }

      // Try general date parsing (allow “YYYY-MM-DD HH:MM[:SS]”)
      var d = new Date(s.replace(' ', 'T'));
      if (!isNaN(d)) return d.getHours()*60 + d.getMinutes();

      return null;
    };
  }
})();	
	
	
(function(){
  if (typeof window.esc !== 'function') {
    window.esc = function (s) {
      return (s == null ? '' : String(s))
        .replace(/[&<>"']/g, function (c) {
          return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    };
  }

  // Icon for device type (used when rendering device labels)
  if (typeof window.iconForDevType !== 'function') {
    window.iconForDevType = function(t){
      var s = (t || '').toString().toUpperCase();
      if (s === 'AIRCRAFT') return '✈️ ';
      if (s === 'SIMULATOR' || s === 'SIM') return '🖥️ ';
      if (s === 'BRIEFING' || s === 'CLASSROOM') return '🏫 ';
      if (s === 'AVP' || s === 'AR') return '🥽 ';
      if (s === 'OFFICE') return '🏢 ';
      return '• ';
    };
  }

  // Canonicalize device types (used by device filtering)
  if (typeof window.canonDevType !== 'function') {
    window.canonDevType = function(raw){
      var t = (raw || '').toString().trim().toUpperCase();
      if (t === 'SIM') return 'SIMULATOR';
      if (t === 'CLASSROOM' || t === 'BRIEFING ROOM') return 'BRIEFING';
      if (t === 'AR' || t === 'APPLE VISION PRO' || t.indexOf('AVP') >= 0 || t.indexOf('VISION') >= 0) return 'AVP';
      if (t.indexOf('ACFT') >= 0 || t === 'PLANE') return 'AIRCRAFT';
      return t;
    };
  }
})();	
	
	
var DATA = window.DATA || null;

/* ---------- Helpers ---------- */
function isMaint(r){return (r.res_type||'').toLowerCase()==='maintenance';}
function isUnavailable(r){return (r.res_type||'').toLowerCase()==='unavailable';}
function timePct(dt){return timeToX(dt);}
function spanWidthMins(mins){return (mins / ((H_END - H_START) * 60)) * 100;}
function widthPct(a,b){return spanWidthMins(minutesBetween(a,b));}

/* ---------- Guide lines ---------- */
function _clamp01(x){return Math.max(0,Math.min(1,x));}
function _pctFromMinutes(m){
  var s=H_START*60, t=(H_END-H_START)*60;
  return _clamp01((m-s)/t)*100;
}
function drawLines(){
  var ov=document.getElementById('overlay'); if(!ov) return;
  ov.innerHTML='';
  var now=new Date(), minsNow=now.getHours()*60+now.getMinutes();
  var xNow=_pctFromMinutes(minsNow);
  var ln=document.createElement('div'); ln.className='vline'; ln.style.left=xNow+'%'; ov.appendChild(ln);
  if(DATA&&DATA.sunrise){
    var ms=_minsFromStamp(DATA.sunrise);
    if(ms!=null){var xs=_pctFromMinutes(ms); var s1=document.createElement('div');
      s1.className='vline sun'; s1.style.left=xs+'%'; ov.appendChild(s1);}
  }
  if(DATA&&DATA.sunset){
    var me=_minsFromStamp(DATA.sunset);
    if(me!=null){var xe=_pctFromMinutes(me); var s2=document.createElement('div');
      s2.className='vline sun'; s2.style.left=xe+'%'; ov.appendChild(s2);}
  }
}
	
	
// Quarter-hour grid overlay + integration with drawLines()
var GRID_ON = true;
try{
  var gv = localStorage.getItem('gridLines');
  if (gv != null) GRID_ON = (gv === '1' || gv === 'true');
}catch(_){}

function drawTimeGrid(){
  var ov = document.getElementById('overlay');
  if (!ov) return;

  // remove old grid lines
  var olds = ov.querySelectorAll('.gline');
  for (var i=0;i<olds.length;i++) olds[i].remove();

  if (!GRID_ON) return;

  var totalMin = (H_END - H_START) * 60;
  var startMin = H_START * 60;
  function pctFor(min){ return ((min - startMin) / totalMin) * 100; }

  for (var h = H_START; h <= H_END; h++){
    for (var off = 0; off < 60; off += 15){
      var m = h*60 + off;
      if (m < startMin || m > startMin + totalMin) continue;
      var div = document.createElement('div');
      div.className = 'gline ' + (off===0 ? 'hour' : (off===30 ? 'half' : 'quarter'));
      var x = Math.min(100, Math.max(0, pctFor(m)));
      div.style.left = x + '%';
      ov.appendChild(div);
    }
  }
}

// Wrap original drawLines to also paint grid
(function(){
  var _origDrawLines = window.drawLines;
  window.drawLines = function(){
    if (typeof _origDrawLines === 'function') _origDrawLines();
    drawTimeGrid();
  };
})();	
	
function drawLinesSafe(){
  var ov=document.getElementById('overlay');
  if(!ov){requestAnimationFrame(drawLinesSafe);return;}
  try{drawLines();}catch(e){console.error(e);}
  if(window._timer) clearInterval(window._timer);
  window._timer=setInterval(function(){try{drawLines();}catch(e){console.error(e);}},60000);
}

/* ---------- Primary reminder bars ---------- */
function showPrimaryBarForDevice(dev,labelEl){
  if (!labelEl || !dev || !dev.reminders || !dev.reminders.length) return;
  var R=pickPrimaryReminder(dev.reminders);
  if(!R)return;
  var current=null;
  if(R.track_by==='HOURS_TACHO'&&dev.latest_tacho!=null)current=Number(dev.latest_tacho);
  if(R.track_by==='HOURS_HOBBS'&&dev.latest_hobbs!=null)current=Number(dev.latest_hobbs);
  var holder=document.createElement('div');
  holder.className='expWrap';
  holder.innerHTML=statusBarFromReminder(R,current);
  labelEl.appendChild(holder);
}
function showPrimaryBarForStaff(staff,labelEl){
  if(!labelEl||!staff||!staff.reminders||!staff.reminders.length)return;
  var R=pickPrimaryReminder(staff.reminders); if(!R)return;
  var holder=document.createElement('div');
  holder.className='expWrap';
  holder.innerHTML=statusBarFromReminder(R,null);
  labelEl.appendChild(holder);
}

// --- Availability helpers (drop-in) ---
function _hmToMin_safe(hm){
  if(!hm) return null;
  // accept "06:00", "6:00", or "06:00:00"
  var s=String(hm).trim();
  var m = s.match(/^(\d{1,2}):(\d{2})/);
  if(!m) return null;
  var h=+m[1], mi=+m[2];
  if(isNaN(h)||isNaN(mi)||h<0||h>23||mi<0||mi>59) return null;
  return h*60+mi;
}

function _mergeSpans(spans){
  if(!spans||!spans.length) return [];
  spans.sort(function(a,b){return a.s-b.s;});
  var out=[{s:spans[0].s, e:spans[0].e}];
  for(var i=1;i<spans.length;i++){
    var last=out[out.length-1], cur=spans[i];
    if(cur.s>last.e) out.push({s:cur.s, e:cur.e});
    else last.e = Math.max(last.e, cur.e);
  }
  return out;
}

// Availability map can have numeric or string keys.
// This normalizes lookups.
function _getAvailEntry(uid){
  if(!DATA || !DATA.availability) return null;
  var m = DATA.availability;
  return m[uid] || m[String(uid)] || m[Number(uid)] || null;
}

// Paint unavailable blocks into a cell for a given entry.
function paintAvailabilityIntoCell(cell, entry, H_START, H_END){
  if(!cell || !entry) return;

  // ensure the cell is a positioned container
  var pos = window.getComputedStyle(cell).position;
  if (pos === 'static' || !pos) cell.style.position = 'relative';

  var mode = (entry.mode||'unavailability').toLowerCase(); // 'availability' or 'unavailability'
  var rules = entry.rules || [];

  var dayStart = H_START*60, dayEnd = H_END*60, dayTotal = (H_END - H_START)*60;

  // clamp helper
  function clamp(v,a,b){return Math.max(a, Math.min(b, v));}

  // turn rules → spans within our visible window
  var spans=[];
  for(var i=0;i<rules.length;i++){
    var s = _hmToMin_safe(rules[i].start);
    var e = _hmToMin_safe(rules[i].end);
    if(s==null || e==null) continue;
    s = clamp(s, dayStart, dayEnd);
    e = clamp(e, dayStart, dayEnd);
    if(e>s) spans.push({s:s, e:e});
  }
  spans = _mergeSpans(spans);

  // Decide which spans to paint as UNavailable
  var unav = [];
  if (mode === 'unavailability'){
    unav = spans; // rules are already unavailable blocks
  } else {
    // mode === 'availability' → paint the complement of "spans"
    var cur = dayStart;
    for(var j=0;j<spans.length;j++){
      if(spans[j].s > cur) unav.push({s:cur, e:spans[j].s});
      cur = Math.max(cur, spans[j].e);
    }
    if (cur < dayEnd) unav.push({s:cur, e:dayEnd});
  }

  // Draw
  for(var k=0;k<unav.length;k++){
    var seg = unav[k];
    var left = ((seg.s - dayStart)/dayTotal)*100;
    var w    = ((seg.e - seg.s)/dayTotal)*100;
    var bg = document.createElement('div');
    bg.className = 'unavail-bg';
    bg.style.position = 'absolute';
    bg.style.left  = left+'%';
    bg.style.width = w+'%';
    bg.style.top   = '0';
    bg.style.bottom= '0';
    bg.style.pointerEvents = 'none';
    // light grey with a bit of transparency so pills remain readable
    bg.style.background = '#e5e7eb';
    bg.style.opacity = '0.55';
    bg.style.zIndex = '0'; // slots should naturally be above
    cell.appendChild(bg);
  }
}	
	
	
/* ---------- renderRows() ---------- */
function renderRows(){
try{
  var grid=document.getElementById('rowsGrid');
  if(!grid){console.warn('#rowsGrid missing');return;}
  grid.innerHTML='<div id="overlay"></div>';

  /* ===== DEVICES ===== */
  addSection('Devices');
  var devs=(DATA&&DATA.devices)?DATA.devices:[];
  for(var di=0;di<devs.length;di++){
    (function(d){
      var name=d.dev_name||'';
      var model=d.dev_model?' <span class="model">('+esc(d.dev_model)+')</span>':'';
      var icon=iconForDevType(d.dev_type);
      var labelEl=addRowLabelHTML('<div class="l1">'+icon+esc(name)+model+'</div>');
      showPrimaryBarForDevice(d,labelEl);

      addRowCell(function(cell){
        cell.addEventListener('click',function(e){
          var rect=cell.getBoundingClientRect();
          var rel=(e.clientX-rect.left)/rect.width;
          rel=Math.max(0,Math.min(1,rel));
          var mins=H_START*60+Math.round(rel*((H_END-H_START)*60));
          mins=Math.floor(mins/15)*15;
          openModalPrefill(name,d.dev_id,Math.floor(mins/60),mins%60);
        },false);

        var rlist=(DATA&&DATA.reservations)?DATA.reservations:[];
        var items=[],unav=[];
        for(var i=0;i<rlist.length;i++){
          var r=rlist[i];
          if(String(r.device_id)!==String(d.dev_id))continue;
          if(!r.start_dt||!r.end_dt)continue;
          if(isUnavailable(r)){unav.push({start:r.start_dt,end:r.end_dt});continue;}
          var students=(DATA.res_students&&DATA.res_students[r.res_id])?DATA.res_students[r.res_id]:[];
          var studentName='—';
          if(students.length){
            var s0=students[0];
            studentName=(s0.first_name||'')+(s0.last_name?(' '+s0.last_name):'');
            studentName=studentName.trim()||'—';
          }
          var tStart=String(r.start_dt).substr(11,5);
          var tEnd=String(r.end_dt).substr(11,5);
          var missionTxt=r.mission_code?r.mission_code:(r.mission_name||'');
          var metaTxt=missionTxt?(tStart+' - '+tEnd+' | '+missionTxt):(tStart+' - '+tEnd);
          var missionFull=(r.mission_code&&r.mission_name)?(r.mission_code+' — '+r.mission_name):(r.mission_code||r.mission_name||'—');
          var tipLines=['Device: '+(r.dev_name||''),'Instructor: '+((r.instr_first||'')+(r.instr_last?(' '+r.instr_last):'')),'Students: '+(students.length?students.map(function(s){return(s.first_name||'')+(s.last_name?(' '+s.last_name):'');}).join(', '):'—'),'Mission: '+missionFull,'Time: '+tStart+' - '+tEnd];
          items.push({res_id:r.res_id,start:r.start_dt,end:r.end_dt,_studentName:studentName,_metaTxt:metaTxt,tipLines:tipLines});
        }
        // Add drafts
        var drafts=(DATA&&DATA.drafts)?DATA.drafts:[];
        for(var di2=0;di2<drafts.length;di2++){
          var dft=drafts[di2];
          if(String(dft.device_id)!==String(d.dev_id))continue;
          var sFirst=(dft.student_ids_csv||'').split(',').filter(Boolean)[0]||'';
          var sName=(dft.first_student_name||sFirst||'—');
          var tStart=String(dft.start_dt).substr(11,5);
          var tEnd=String(dft.end_dt).substr(11,5);
          var missionTxt=dft.mission_code?dft.mission_code:(dft.mission_name||'');
          var metaTxt=missionTxt?(tStart+' - '+tEnd+' | '+missionTxt):(tStart+' - '+tEnd);
          items.push({res_id:'DRAFT-'+dft.id,start:dft.start_dt,end:dft.end_dt,_studentName:sName,_metaTxt:metaTxt,tipLines:['DRAFT','Instructor: '+(dft.instructor_name||'—'),'Device: '+(d.dev_name||''),'Students: '+(dft.student_names||'—'),'Mission: '+(missionTxt||'—'),'Time: '+tStart+' - '+tEnd],_isDraft:true});
        }

        // unavail bg
        for(var u=0;u<unav.length;u++){
          var seg=unav[u];
          var div=document.createElement('div');
          div.className='unavail-bg';
          div.style.left=timeToX(seg.start)+'%';
          div.style.width=widthPct(seg.start,seg.end)+'%';
          div.style.top='0';div.style.bottom='0';
          div.style.pointerEvents='none';
          cell.appendChild(div);
        }

        if(items.length){
          var SLOT_H=44,GAP=6,TOP0=6;
          var packed=packLanes(items);
          var totalH=TOP0+packed.lanes*(SLOT_H+GAP)-GAP+TOP0;
          cell.style.minHeight=Math.max(54,totalH)+'px';
          for(var pi=0;pi<packed.items.length;pi++){
            var it=packed.items[pi];
            var slot=document.createElement('div');
            slot.className='slot dev'+(it._isDraft?' draft':'');
            slot.setAttribute('data-force-above','1');
            slot.setAttribute('data-res-id',String(it.res_id));
            slot.style.left=timeToX(it.start)+'%';
            slot.style.width=widthPct(it.start,it.end)+'%';
            slot.style.top=(TOP0+it._lane*(SLOT_H+GAP))+'px';
            slot.addEventListener('click',function(e){
              e.stopPropagation();
              var id=this.getAttribute('data-res-id');
              if(!id)return;
              if(id.indexOf('DRAFT-')===0)return; // ignore draft click for now
              openReservationEditor(parseInt(id,10));
            },false);
            var txt=document.createElement('span');
            txt.className='slotText';
            txt.innerHTML='<span class="name">'+esc(it._studentName)+'</span><span class="meta">'+esc(it._metaTxt)+'</span>';
            slot.appendChild(txt);
            if(it.tipLines&&it.tipLines.length){
              var tip=document.createElement('div');
              tip.className='tooltip';
              tip.innerHTML=esc(it.tipLines.join('\n')).replace(/\n/g,'<br>');
              slot.appendChild(tip);
            }
            cell.appendChild(slot);
          }
        }else cell.style.minHeight='54px';
      });
    })(devs[di]);
  }

 /* ===== STAFF ===== */
addSection('Staff');
var st = (DATA && DATA.staff) ? DATA.staff : [];

for (var si = 0; si < st.length; si++) {
  (function (staff) {
    var uid = +staff.id;
    var fname = staff.first_name || '', lname = staff.last_name || '';
    var roleRaw = (staff.role || ''), rr = roleRaw.toUpperCase();
    var roleAbbrev = (rr === 'ADMIN') ? 'COO' : (rr === 'INSTRUCTOR' ? 'Instructor' : roleRaw);
    var label = '<div class="l1">' + esc((fname + ' ' + lname).trim()) +
                ' <span class="roleText">(' + esc(roleAbbrev) + ')</span></div>';
    var lbl = addRowLabelHTML(label);

    showPrimaryBarForStaff(staff, lbl);

    addRowCell(function (cell) {
      // open new reservation on click at clicked time
      cell.addEventListener('click', function (e) {
        var rect = cell.getBoundingClientRect();
        var rel = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        var mins = H_START * 60 + Math.round(rel * ((H_END - H_START) * 60));
        mins = Math.floor(mins / 15) * 15;
        openModalPrefill(null, null, Math.floor(mins / 60), mins % 60, uid);
      }, false);

      /* ---------- Availability overlay (grey blocks) ---------- */
      try {
        // ensure the cell can host absolutely positioned overlays
        if (!cell.style.position || window.getComputedStyle(cell).position === 'static') {
          cell.style.position = 'relative';
        }

        // normalize availability lookup (numeric or string key)
        var entry = (function getAvailEntry(id){
          if (!DATA || !DATA.availability) return null;
          var m = DATA.availability;
          return m[id] || m[String(id)] || m[Number(id)] || null;
        })(uid);

        if (entry) {
          // helpers
          function hmToMin(hm){
            if(!hm) return null;
            var s=String(hm).trim();
            var m=s.match(/^(\d{1,2}):(\d{2})/);
            if(!m) return null;
            var h=+m[1], mi=+m[2];
            if(isNaN(h)||isNaN(mi)||h<0||h>23||mi<0||mi>59) return null;
            return h*60+mi;
          }
          function merge(spans){
            if(!spans.length) return spans;
            spans.sort(function(a,b){return a.s-b.s;});
            var out=[{s:spans[0].s, e:spans[0].e}];
            for(var i=1;i<spans.length;i++){
              var last=out[out.length-1], cur=spans[i];
              if(cur.s>last.e) out.push({s:cur.s,e:cur.e});
              else last.e=Math.max(last.e,cur.e);
            }
            return out;
          }
          function clamp(v,a,b){ return Math.max(a, Math.min(b, v)); }

          var mode = (entry.mode || 'unavailability').toLowerCase(); // 'availability' | 'unavailability'
          var rules = entry.rules || [];

          var dayStart = H_START * 60, dayEnd = H_END * 60, total = (H_END - H_START) * 60;

          // rules → spans in minutes inside the visible window
          var spans = [];
          for (var r=0; r<rules.length; r++){
            var sM = hmToMin(rules[r].start);
            var eM = hmToMin(rules[r].end);
            if (sM==null || eM==null) continue;
            sM = clamp(sM, dayStart, dayEnd);
            eM = clamp(eM, dayStart, dayEnd);
            if (eM > sM) spans.push({s:sM, e:eM});
          }
          spans = merge(spans);

          // choose what to paint as UNAVAILABLE
          var unav = [];
          if (mode === 'unavailability') {
            unav = spans.slice();
          } else {
            // mode === 'availability' → paint complement
            var cur = dayStart;
            for (var i=0;i<spans.length;i++){
              if (spans[i].s > cur) unav.push({s:cur, e:spans[i].s});
              cur = Math.max(cur, spans[i].e);
            }
            if (cur < dayEnd) unav.push({s:cur, e:dayEnd});
          }

          // draw blocks
          for (var k=0;k<unav.length;k++){
            var seg = unav[k];
            var left = ((seg.s - dayStart) / total) * 100;
            var w    = ((seg.e - seg.s) / total) * 100;
            var bg = document.createElement('div');
            bg.className = 'unavail-bg';
            bg.style.position = 'absolute';
            bg.style.left = left + '%';
            bg.style.width = w + '%';
            bg.style.top = '0';
            bg.style.bottom = '0';
            bg.style.pointerEvents = 'none';
            bg.style.background = '#e5e7eb';
            bg.style.opacity = '0.55';
            bg.style.zIndex = '0';
            cell.appendChild(bg);
          }
        }
      } catch (err) {
        console.warn('Availability overlay error', err);
      }

      /* ---------- Staff reservations (pills + tooltip) ---------- */
      var rlist = (DATA && DATA.reservations) ? DATA.reservations : [];
      var items = [];

      for (var x = 0; x < rlist.length; x++) {
        var r = rlist[x];
        if (+r.instructor_user_id !== uid) continue;
        if (!r.start_dt || !r.end_dt) continue;

        var students = (DATA.res_students && DATA.res_students[r.res_id]) ? DATA.res_students[r.res_id] : [];
        var fullNames = students.map(function (s) { return (s.first_name || '') + (s.last_name ? (' ' + s.last_name) : ''); });
        var primaryName = fullNames.length ? fullNames[0] + (fullNames.length > 1 ? ' +' + (fullNames.length - 1) : '') : '—';

        var t1 = String(r.start_dt).substr(11, 5), t2 = String(r.end_dt).substr(11, 5);
        var metaLine = t1 + ' - ' + t2 + (r.mission_code ? ' | ' + r.mission_code : '');

        var missionFull = (r.mission_code && r.mission_name)
          ? (r.mission_code + ' — ' + r.mission_name)
          : (r.mission_code || r.mission_name || '—');

        items.push({
          res_id: r.res_id,
          start:  r.start_dt,
          end:    r.end_dt,
          _studentName: primaryName,
          _metaTxt: metaLine,
          tipLines: [
            'Instructor: ' + (fname + ' ' + lname).trim(),
            'Device: ' + (r.dev_name || '—'),
            'Students: ' + (fullNames.join(', ') || '—'),
            'Mission: ' + missionFull,
            'Time: ' + t1 + ' - ' + t2
          ]
        });
      }

      if (items.length) {
        var packed = packLanes(items), SLOT_H = 44, GAP = 6, TOP0 = 6;
        var totalH = TOP0 + packed.lanes * (SLOT_H + GAP) - GAP + TOP0;
        cell.style.minHeight = Math.max(54, totalH) + 'px';

        for (var p = 0; p < packed.items.length; p++) {
          var it = packed.items[p];

          var slot = document.createElement('div');
          slot.className = 'slot staff';
          slot.setAttribute('data-force-above', '1');
          slot.setAttribute('data-res-id', String(it.res_id));
          slot.style.left = timeToX(it.start) + '%';
          slot.style.width = widthPct(it.start, it.end) + '%';
          slot.style.top = (TOP0 + it._lane * (SLOT_H + GAP)) + 'px';

          slot.addEventListener('click', function (e) {
            e.stopPropagation();
            var id = this.getAttribute('data-res-id');
            if (id) openReservationEditor(parseInt(id, 10));
          }, false);

          var txt = document.createElement('span');
          txt.className = 'slotText';
          txt.innerHTML =
            '<span class="name">' + esc(it._studentName) + '</span>' +
            '<span class="meta">' + esc(it._metaTxt) + '</span>';
          slot.appendChild(txt);

          var tip = document.createElement('div');
          tip.className = 'tooltip';
          tip.innerHTML = esc(it.tipLines.join('\n')).replace(/\n/g, '<br>');
          slot.appendChild(tip);

          cell.appendChild(slot);
        }
      } else {
        cell.style.minHeight = '54px';
      }
    });
  })(st[si]);
}

  if(typeof drawLinesSafe==='function')drawLinesSafe();else drawLines();
  if(typeof attachTooltipBehavior==='function')attachTooltipBehavior(document.getElementById('rowsGrid'));
}catch(err){
  console.error('renderRows failed:',err);
  var dbg=document.getElementById('debugBox');
  if(dbg){dbg.style.display='block';dbg.textContent='Render error (see console)';}
}
} // end renderRows()	
	

	
	
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
var missionRow=document.getElementById('missionRow'); // we’ll insert progress just before this

var EDITING_RES_ID = null; // null => create mode; number => edit mode
var PREFILL_R = null;      // used only while editing to auto-select mission
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

/* -------------------------------------------------------------------
   Ensure the PROGRESS STRIP lives *below Route* and *above Mission*.
   We dynamically create/relocate #progressRow -> #progressBox here.
------------------------------------------------------------------- */
function ensureProgressMount(){
  // If the row already exists, just move it into place.
  var row = document.getElementById('progressRow');
  if (!row){
    row = document.createElement('div');
    row.id = 'progressRow';
    // simple neutral spacing; you can theme this later
    row.style.margin = '8px 0 12px 0';

    var label = document.createElement('div');
    label.textContent = 'Training Progress';
    label.style.fontSize = '12px';
    label.style.fontWeight = '600';
    label.style.color = '#374151';
    label.style.marginBottom = '6px';
    row.appendChild(label);

    var box = document.createElement('div');
    box.id = 'progressBox';
    row.appendChild(box);
  }
  // Insert right before Mission row
  if (missionRow && row.parentNode !== missionRow.parentNode){
    missionRow.parentNode.insertBefore(row, missionRow);
  }else if (missionRow && row.nextSibling !== missionRow){
    missionRow.parentNode.insertBefore(row, missionRow);
  }
  return row;
}

function openModalPrefill(deviceName, deviceId, hour, minute, staffId){
  setModalMode(false);
  EDITING_RES_ID = null;   // create mode

  modalWrap.style.display = 'flex';

  var d = (DATA && DATA.date) ? DATA.date : '<?php echo safe($date); ?>';
  f_sdate.value = d; f_edate.value = d;

  var h = (hour   != null) ? hour   : 10;
  var m = (minute != null) ? minute : 0;
  var endM = (h*60 + m) + 60;
  if (endM > (23*60 + 45)) endM = 23*60 + 45;
  var eh = Math.floor(endM/60), em = endM % 60;

  buildTimeSelect(f_stime, pad(h)+':'+pad(m));
  buildTimeSelect(f_etime, pad(eh)+':'+pad(em));

  ensureProgressMount(); // make sure it renders in the right spot

  loadFormOptions(function(){
    if (deviceId) f_device.value = String(deviceId);
    if (staffId)  f_staff.value  = String(staffId);
    updateMissionField();
    updateScenarioStrip(); // draw (will hide if no student yet)
  });
}

function _extractCode(s){
  var m = String(s == null ? '' : s).match(/^\s*([0-9]+(?:-[0-9]+)*)/);
  return m ? m[1] : '';
}
function _stripCodeFromLabel(s){
  return String(s == null ? '' : s).replace(/^\s*[0-9]+(?:-[0-9]+)*\s*[-\u2014]\s*/,'').trim();
}

function selectMissionFromReservation(R){
  if (!R || !f_mission_sel) return;
  var wantCode = (R.mission_code||'').trim();
  var wantName = (R.mission_name||'').trim();
  var combined = (wantCode && wantName) ? (wantCode+' - '+wantName)
                : (wantCode || wantName || '');

  if (combined){
    for (var i=0;i<f_mission_sel.options.length;i++){
      var o = f_mission_sel.options[i];
      if ((o.value||'').trim() === combined){ f_mission_sel.value = o.value; return; }
    }
  }
  if (wantCode){
    for (var j=0;j<f_mission_sel.options.length;j++){
      var ov = f_mission_sel.options[j].value || '';
      var ot = f_mission_sel.options[j].text  || '';
      if (_extractCode(ov) === wantCode || _extractCode(ot) === wantCode){
        f_mission_sel.value = f_mission_sel.options[j].value; return;
      }
    }
  }
  if (wantName){
    var wantNameNorm = wantName.toLowerCase();
    for (var k=0;k<f_mission_sel.options.length;k++){
      var txtAfter = _stripCodeFromLabel(f_mission_sel.options[k].text||'').toLowerCase();
      if (txtAfter === wantNameNorm){ f_mission_sel.value = f_mission_sel.options[k].value; return; }
    }
  }
  if (combined){
    var extra = document.createElement('option');
    extra.value = combined; extra.text  = combined;
    f_mission_sel.insertBefore(extra, f_mission_sel.firstChild);
    f_mission_sel.value = combined;
  }
}


/* =====================================================
   SCENARIO STRIP (8 pills) — consumes progress_api.php
   Mounted right above Mission (ensureProgressMount()).
   Colors:
     LB → light orange, FNPT/SAB → light magenta, FLIGHT → light blue
   Two-line text inside each pill:
     Line 1 (bold, smaller):  "1-1-5 FLT"
     Line 2 (smaller):        "Oct 16, 25"
   Black floating tooltip above pills.
   ===================================================== */
(function(){
  // ---------------- Theme colors (easy to tweak later) ----------------
  var COLOR_BRIEFING = '#FDE68A';  // Light Orange
  var COLOR_SIM      = '#FBCFE8';  // Light Magenta
  var COLOR_FLIGHT   = '#BFDBFE';  // Light Blue
  var COLOR_NEUTRAL  = '#E5E7EB';  // Gray-200

  // ---------------- Utilities ----------------
  function esc(s){
    return String(s==null?'':s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function normalizeType(t){
    var s = String(t||'').toUpperCase();
    if (s==='LB' || s==='BRIEFING') return 'BRIEFING';
    if (s==='FNPT' || s==='SAB' || s==='SIMULATOR') return 'SIMULATOR';
    if (s==='FLIGHT') return 'FLIGHT';
    return '';
  }
  function shortType(t){
    var n = normalizeType(t);
    if (n==='BRIEFING')  return 'BRF';
    if (n==='SIMULATOR') return 'SIM';
    if (n==='FLIGHT')    return 'FLT';
    return '';
  }
  function colorForType(t){
    var n = normalizeType(t);
    if (n==='BRIEFING')  return COLOR_BRIEFING;
    if (n==='SIMULATOR') return COLOR_SIM;
    if (n==='FLIGHT')    return COLOR_FLIGHT;
    return COLOR_NEUTRAL;
  }
  // Lighten a hex color toward white by `ratio` (0..1)
  function lighten(hex, ratio){
    hex = String(hex||'').replace('#','');
    if (hex.length===3){ hex = hex.replace(/(.)/g,'$1$1'); }
    var r = parseInt(hex.substr(0,2),16),
        g = parseInt(hex.substr(2,2),16),
        b = parseInt(hex.substr(4,2),16);
    function mix(c){ return Math.round(c + (255 - c) * Math.min(Math.max(ratio,0),1)); }
    var r2 = mix(r), g2 = mix(g), b2 = mix(b);
    var toHex = function(v){ var s=v.toString(16); return (s.length===1?'0':'')+s; };
    return '#' + toHex(r2) + toHex(g2) + toHex(b2);
  }
  // Darken a hex color toward black by `ratio` (0..1)
  function darken(hex, ratio){
    hex = String(hex||'').replace('#','');
    if (hex.length===3){ hex = hex.replace(/(.)/g,'$1$1'); }
    var r = parseInt(hex.substr(0,2),16),
        g = parseInt(hex.substr(2,2),16),
        b = parseInt(hex.substr(4,2),16);
    function mix(c){ return Math.round(c * (1 - Math.min(Math.max(ratio,0),1))); }
    var r2 = mix(r), g2 = mix(g), b2 = mix(b);
    var toHex = function(v){ var s=v.toString(16); return (s.length===1?'0':'')+s; };
    return '#' + toHex(r2) + toHex(g2) + toHex(b2);
  }
  function firstSelectedStudentId(){
    var sel = document.getElementById('f_student');
    if (!sel) return '';
    for (var i=0;i<sel.options.length;i++){
      var o = sel.options[i];
      if (o.selected && o.value) return o.value;
    }
    return '';
  }
  function pickStripFromApi(js){
    if (!js || js.ok !== true || !js.progress || !js.progress.length) return null;
    for (var i=0;i<js.progress.length;i++){
      var p = js.progress[i];
      if (p && p.strip && p.strip.items && p.strip.items.length) return p.strip;
    }
    return null;
  }
  // Date formatter: "Oct 16, 25"
  function formatDateNice(iso){
    if (!iso) return '';
    // expect YYYY-MM-DD
    var m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return iso;
    var y = parseInt(m[1],10), mo = parseInt(m[2],10)-1, d = parseInt(m[3],10);
    var dt = new Date(y, mo, d);
    if (isNaN(dt.getTime())) return iso;
    var mon = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][dt.getMonth()];
    var yy  = (dt.getFullYear()%100);
    var yy2 = (yy<10?('0'+yy):(''+yy));
    return mon + ' ' + d + ', ' + yy2;
  }

  // ---------------- Grading text + dot image mapping ----------------
  function gradingText(code){
    var c = String(code||'').toUpperCase();
    // Completed vs Incomplete wording plus color
    if (c==='GC') return 'Green - Complete';
    if (c==='BC') return 'Blue - Complete';
    if (c==='RC') return 'Red - Complete';
    if (c==='YC') return 'Yellow - Complete';
    if (c==='GI') return 'Green - Incomplete';
    if (c==='BI') return 'Blue - Incomplete';
    if (c==='RI') return 'Red - Incomplete';
    if (c==='YI') return 'Yellow - Incomplete';
    return '—';
  }
  function dotSrcFor(item){
    // Priority: explicit grading → scheduled → upcoming (white) → fallback grey
    var g = String(item && item.grading || '').toUpperCase();
    if (g==='GC') return 'img/green_complete.png';
    if (g==='BC') return 'img/blue_complete.png';
    if (g==='RC') return 'img/red_complete.png';
    if (g==='YC') return 'img/yellow_complete.png';
    if (g==='GI') return 'img/green_incomplete.png';
    if (g==='BI') return 'img/blue_incomplete.png';
    if (g==='RI') return 'img/red_incomplete.png';
    if (g==='YI') return 'img/yellow_incomplete.png';

    // No grading → scheduled or upcoming
    if (item && item.status === 'scheduled') return 'img/grey_scheduled.png'; // you said you'll upload
    if (item && item.status === 'upcoming')  return 'img/white_upcoming.png'; // you said you'll upload

    return 'img/grey_scheduled.png';
  }

  // ---------------- Tooltip (black box above the pill) ----------------
  var tipEl = null;
  function ensureTooltip(){
    if (tipEl) return tipEl;
    tipEl = document.createElement('div');
    tipEl.id = 'progressTip';
    tipEl.style.position = 'fixed';
    tipEl.style.zIndex = '99999';
    tipEl.style.maxWidth = '320px';
    tipEl.style.background = 'rgba(0,0,0,0.92)';
    tipEl.style.color = '#fff';
    tipEl.style.borderRadius = '8px';
    tipEl.style.padding = '10px 12px';
    tipEl.style.fontSize = '12px';
    tipEl.style.lineHeight = '1.35';
    tipEl.style.boxShadow = '0 6px 18px rgba(0,0,0,0.35)';
    tipEl.style.pointerEvents = 'none';
    tipEl.style.display = 'none';
    tipEl.style.whiteSpace = 'normal';
    tipEl.style.border = '1px solid rgba(255,255,255,0.12)';
    document.body.appendChild(tipEl);
    return tipEl;
  }
  function showTip(html, anchorRect){
    var t = ensureTooltip();
    t.innerHTML = html;
    t.style.display = 'block';
    // position above anchor
    var pad = 8;
    var x = Math.round(anchorRect.left + (anchorRect.width/2));
    var y = Math.round(anchorRect.top) - pad;
    // measure size
    t.style.left = '0px'; t.style.top = '0px';
    var w = t.offsetWidth, h = t.offsetHeight;
    t.style.left = Math.max(8, x - Math.round(w/2)) + 'px';
    t.style.top  = Math.max(8, anchorRect.top - h - pad) + 'px';
  }
  function hideTip(){
    if (tipEl){ tipEl.style.display = 'none'; }
  }
  function tipHtmlFor(it){
    var parts = [];
    if (it.type) parts.push('<div><b>Type:</b> '+esc(normalizeType(it.type).charAt(0)+normalizeType(it.type).slice(1).toLowerCase())+'</div>');
    if (it.code) parts.push('<div><b>Scenario:</b> '+esc(it.code)+'</div>');
    if (it.name) parts.push('<div><b>Title:</b> '+esc(it.name)+'</div>');
    if (it.date){
      var label = (it.status==='scheduled') ? 'Scheduled' : 'Date';
      parts.push('<div><b>'+label+':</b> '+esc(formatDateNice(it.date))+'</div>');
    }
    if (it.grading) parts.push('<div><b>Grading:</b> '+esc(gradingText(it.grading))+'</div>');
    if (it.duration) parts.push('<div><b>Duration:</b> '+esc(it.duration)+'</div>');
    if (it.instructor_name) parts.push('<div><b>Instructor:</b> '+esc(it.instructor_name)+'</div>');
    return parts.join('');
  }

  // ---------------- Strip window (8 pills) ----------------
  function computeWindow8(strip){
    var items = strip.items||[];
    var latestIdx = (strip.latest && typeof strip.latest.index==='number') ? strip.latest.index : 0;
    // Ensure "repeat after incomplete" exists (fallback if API missed it)
    if (items.length && strip.latest && (strip.latest.status==='incomplete' || (strip.latest.grading||'').toUpperCase().indexOf('I')>0)){
      // If next item is not a repeat of same sc_id, insert a virtual repeat
      var needVirtual = true;
      if (latestIdx+1 < items.length){
        var nxt = items[latestIdx+1];
        if (nxt && (nxt.status==='repeat' || nxt.sc_id===items[latestIdx].sc_id)) needVirtual = false;
      }
      if (needVirtual){
        var cur = items[latestIdx];
        var virtual = {
          sc_id: cur.sc_id,
          code: cur.code,
          name: cur.name,
          type: cur.type,
          status: 'repeat',
          grading: cur.grading, // keep last grading for icon
          date: '',             // no date for "next"
          date_fmt: '',
          instructor_id: cur.instructor_id,
          instructor_name: cur.instructor_name
        };
        items.splice(latestIdx+1, 0, virtual);
      }
    }

    // Center 8 around latest
    var start = Math.max(0, latestIdx - 4);
    var end   = Math.min(items.length - 1, start + 7);
    start = Math.max(0, end - 7);
    return {start:start, end:end, items:items};
  }

  // ---------------- Render ----------------
  function renderStrip(strip){
    ensureProgressMount();
    var row = document.getElementById('progressRow');
    var box = document.getElementById('progressBox');
    if (!row || !box){ return; }

    if (!strip || !strip.items || !strip.items.length){
      row.style.display = 'none';
      box.innerHTML = '';
      return;
    }

    var win = computeWindow8(strip);
    var items = win.items;
    var html = '<div style="display:flex; gap:6px; align-items:stretch;">';

    for (var i=win.start; i<=win.end; i++){
      var it = items[i] || {};
      var baseBg   = colorForType(it.type);
      var bg       = baseBg;
      var border   = '1px solid ' + darken(baseBg, 0.25);
      var textCol  = '#111827';
      var isScheduled = (it.status==='scheduled');
      var isUpcoming  = (it.status==='upcoming');

      if (isScheduled){
        bg      = lighten(baseBg, 0.88);
        border  = '1px dashed #9CA3AF';
        textCol = '#374151';
      } else if (isUpcoming){
        bg      = lighten(baseBg, 0.82);
        border  = '1px solid #D1D5DB';
        textCol = '#6B7280';
      }

      // Small two-line text & grading dot (20px)
      var codeTop  = esc(it.code || '—') + (shortType(it.type) ? ' ' + esc(shortType(it.type)) : '');
      var dateStr  = it.date_fmt ? it.date_fmt : (it.date ? formatDateNice(it.date) : '');
      var tipHtml  = tipHtmlFor(it);

      html += ''+
        '<div class="pill" '+
          'data-tip="'+esc(tipHtml)+'" '+
          'style="flex:1 1 0; position:relative; min-height:26px; '+
                 'background:'+bg+'; border:'+border+'; '+
                 'border-radius:10px; display:flex; align-items:center; justify-content:center; '+
                 'padding:0 6px; text-align:center; overflow:hidden;">' +
            '<div style="display:flex; align-items:center; gap:6px; max-width:100%;">' +
              '<img src="'+esc(dotSrcFor(it))+'" alt="" style="width:20px; height:20px; flex:0 0 auto;" />' +
              '<div style="line-height:1.08; color:'+textCol+'; max-width:100%;">' +
                '<div style="font-size:10px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">'+ codeTop +'</div>' +
                '<div style="font-size:9px; white-space:nowrap; opacity:0.95;">'+ esc(dateStr) +'</div>' +
              '</div>' +
            '</div>' +
        '</div>';
    }
    html += '</div>';

    box.innerHTML = html;
    row.style.display = '';

    // Hook tooltips
    var pills = box.getElementsByClassName('pill');
    for (var p=0; p<pills.length; p++){
      (function(el){
        el.addEventListener('mouseenter', function(){
          var rect = el.getBoundingClientRect();
          var html = el.getAttribute('data-tip') || '';
          if (html){ showTip(html, rect); }
        }, false);
        el.addEventListener('mouseleave', function(){ hideTip(); }, false);
      })(pills[p]);
    }
  }

  // ---------------- Public updater ----------------
  window.updateScenarioStrip = function(){
    ensureProgressMount();
    var row = document.getElementById('progressRow');
    var box = document.getElementById('progressBox');
    if (!row || !box) return;

    var sid = firstSelectedStudentId();
    if (!sid){
      row.style.display = 'none';
      box.innerHTML = '';
      return;
    }

    fetch('progress_api.php?student_id=' + encodeURIComponent(sid))
      .then(function(r){ return r.json(); })
      .then(function(js){
        var strip = pickStripFromApi(js);
        renderStrip(strip);
      })
      .catch(function(){
        row.style.display = 'none';
        box.innerHTML = '';
      });
  };

  // Update on student change
  if (window.f_student){
    try{ f_student.addEventListener('change', window.updateScenarioStrip, false); }catch(_){}
  }
})();
	
/* ---------- Editor open ---------- */
function openReservationEditor(resId){
  modalWrap.style.display='flex';
  setModalMode(true);
  EDITING_RES_ID = resId;

  var d = (DATA && DATA.date) ? DATA.date : '<?php echo safe($date); ?>';
  f_sdate.value = d; f_edate.value = d;
  buildTimeSelect(f_stime, '09:00');
  buildTimeSelect(f_etime, '10:00');

  ensureProgressMount();

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

        for (var i=0;i<f_student.options.length;i++){ f_student.options[i].selected = false; }
        for (var k=0;k<sids.length;k++){
          var v = String(sids[k]);
          var opt = f_student.querySelector('option[value="'+v+'"]');
          if (opt) opt.selected = true;
        }

        updateScenarioStrip(); // draw immediately for first selected student

        // fire a change so dependent logic refreshes (mission list + strip)
        try {
          if (typeof Event === 'function') {
            var ev = new Event('change', { bubbles: true });
            f_student.dispatchEvent(ev);
          } else if (document.createEvent) {
            var ev2 = document.createEvent('HTMLEvents');
            ev2.initEvent('change', true, false);
            f_student.dispatchEvent(ev2);
          } else {
            if (typeof updateMissionField === 'function') updateMissionField();
            if (typeof updateScenarioStrip === 'function') updateScenarioStrip();
          }
        } catch (e) {
          if (typeof updateMissionField === 'function') updateMissionField();
          if (typeof updateScenarioStrip === 'function') updateScenarioStrip();
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
                          var o=document.createElement('option'); o.value=scn.value; o.text='   '+scn.label;
                          og.appendChild(o); hadAny=true;
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
                  selectMissionFromReservation(R);
                }
              });
          }
        }else{
          f_mission_sel.style.display='none';
          f_mission_text.style.display='block';
          f_mission_text.value = R.mission_name || '';
        }

        updateMissionField();
        setTimeout(function(){ if (typeToScenarioKind(f_type.value)) selectMissionFromReservation(R); }, 0);
      });
  });
}

function closeModalNow(){ modalWrap.style.display='none'; }

newBtn.addEventListener('click', function(){ openModalPrefill(null,null,10,0); }, false);
closeModal.addEventListener('click', closeModalNow, false);
cancelBtn.addEventListener('click', closeModalNow, false);

/* ---------- Data for modal: Students (grouped), Staff, Devices ---------- */
var ALL_DEVICES_CACHE = [];

function loadFormOptions(cb){
  var setFallbacks = function(){
    f_student.innerHTML='';
    var og=document.createElement('optgroup'); og.label='Unassigned / Other';
    var o=document.createElement('option'); o.value=''; o.text='No active users'; og.appendChild(o);
    f_student.appendChild(og);

    f_staff.innerHTML='';
    var s=document.createElement('option'); s.value=''; s.text='No staff'; f_staff.appendChild(s);

    f_device.innerHTML='';
    var d=document.createElement('option'); d.value=''; d.text='No devices'; f_device.appendChild(d);

    if(typeof cb==='function'){ cb(); }
  };

  ensureProgressMount();    // keep mount correct even before data arrives
  updateScenarioStrip();    // harmless if no student yet

  fetch('?api=form_options')
    .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
    .then(function(opt){
      // Students
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

      // Staff
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

      // Devices
      ALL_DEVICES_CACHE = opt.devices || [];
      applyDeviceFilter();

      if(typeof cb==='function'){ cb(); }
    })
    .catch(function(){ setFallbacks(); });
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
  if(t==='ar briefing') return ['BRIEFING','AIRCRAFT','AVP'];
  if(t==='meeting') return ['OFFICE'];
  if(t==='assessment') return ['BRIEFING','SIMULATOR'];
  return null;
}
function applyDeviceFilter(){
  var allow = allowedDevTypesFor(f_type.value);
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

    var sid = '';
    for(var i=0;i<f_student.options.length;i++){
      var o=f_student.options[i]; if(o.selected && o.value){ sid=o.value; break; }
    }

    if(!sid){
      f_mission_sel.innerHTML='<option value="">Select a student first…</option>';
      return;
    }

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

// --- Listeners ---
f_type.addEventListener('change', function(){
  updateMissionField();
  applyDeviceFilter();
}, false);

f_student.addEventListener('change', function(){
  updateMissionField();
  updateStudentProgress();   // fetch & render progress for the first selected student
}, false);
	

/* Save + overlap handling */
saveBtn.addEventListener('click', function () {
  var typeVal = (f_type && f_type.value) ? f_type.value.toLowerCase() : '';

  if (!f_staff || !f_staff.value) {
    alert('⚠️ Please select a Staff Member.');
    f_staff && f_staff.focus();
    return;
  }

  var requireStudents = !/^(unavailable|maintenance|personal|meeting)$/i.test(typeVal);
  var selectedStudents = [];
  for (var i = 0; i < f_student.options.length; i++) {
    var o = f_student.options[i];
    if (o.selected && o.value) selectedStudents.push(parseInt(o.value,10));
  }
  if (requireStudents && !selectedStudents.length) {
    alert('⚠️ Please select at least one Student/User.');
    f_student && f_student.focus();
    return;
  }

  var isUnavailable = typeVal === 'unavailable';
  var isMaintenance = typeVal === 'maintenance';
  var haveDevice    = (f_device && f_device.value);
  var haveStaff     = (f_staff && f_staff.value);

  if (isUnavailable) {
    if (!haveDevice && !haveStaff) {
      alert('⚠️ For Unavailable, select a Device and/or a Staff member.');
      return;
    }
  } else if (isMaintenance) {
    if (!haveDevice) {
      alert('⚠️ Please select a Device for Maintenance.');
      f_device && f_device.focus();
      return;
    }
  } else {
    if (!haveDevice) {
      alert('⚠️ Please select a Device.');
      f_device && f_device.focus();
      return;
    }
  }

  var missionValue = '';
  var needScenario = typeToScenarioKind(f_type.value);
  if (needScenario) {
    if (!f_mission_sel || !f_mission_sel.value) {
      alert('⚠️ Please choose a Mission/Scenario.');
      f_mission_sel && f_mission_sel.focus();
      return;
    }
    missionValue = f_mission_sel.value;
  } else if (!/^(unavailable|maintenance|personal|meeting)$/i.test(typeVal)) {
    var txt = (f_mission_text && f_mission_text.value) ? f_mission_text.value.replace(/^\s+|\s+$/g,'') : '';
    if (!txt) {
      alert('⚠️ Please enter a Mission/Notes description.');
      f_mission_text && f_mission_text.focus();
      return;
    }
    missionValue = txt;
  }

  var payload = {
    type: f_type.value,
    student_ids: selectedStudents,
    start_date: f_sdate.value, start_time: f_stime.value,
    end_date:   f_edate.value, end_time:   f_etime.value,
    device_id:  haveDevice ? f_device.value : null,
    staff_id:   haveStaff  ? f_staff.value  : null,
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

/* ---------- Navigation & clock ---------- */
function parseYMD(s){ var a=s.split('-'); return new Date(+a[0], (+a[1])-1, +a[2]); }
function fmtYMD(d){
  var y=d.getFullYear(), m=('0'+(d.getMonth()+1)).slice(-2), da=('0'+d.getDate()).slice(-2);
  return y+'-'+m+'-'+da;
}
function pickDate(v){
  var p = new URLSearchParams(location.search),
      hs = p.get('hstart') || <?php echo (int)$H_START; ?>,
      he = p.get('hend')   || <?php echo (int)$H_END; ?>;
  location.search = '?date='+encodeURIComponent(v)+'&hstart='+hs+'&hend='+he+(p.get('debug')?'&debug=1':'');
}
function navDay(delta){
  var inp = document.getElementById('pick');
  var cur = (inp && inp.value) ? inp.value :
            (new URLSearchParams(location.search).get('date') || '<?php echo safe($date); ?>');
  var d = parseYMD(cur); d.setDate(d.getDate() + (delta||0));
  pickDate(fmtYMD(d));
}
function goToday(){ pickDate('<?php echo safe($today); ?>'); }

// Hours dropdown + Grid toggle
(function(){
  var hoursBtn  = document.getElementById('hoursBtn');
  var hoursMenu = document.getElementById('hoursMenu');
  var hStartInp = document.getElementById('hStartInp');
  var hEndInp   = document.getElementById('hEndInp');
  var applyBtn  = document.getElementById('hoursApply');
  var resetBtn  = document.getElementById('hoursReset');
  var gridBtn   = document.getElementById('gridToggle');

  // Seed inputs from current globals
  function seedInputs(){
    var curS = (H_START<10?'0':'')+H_START+':00';
    var curE = (H_END<10?'0':'')+H_END+':00';
    hStartInp.value = curS; hEndInp.value = curE;
  }

  function placeMenu(){
    if (!hoursBtn || !hoursMenu) return;
    var r = hoursBtn.getBoundingClientRect();
    hoursMenu.style.left = (r.left) + 'px';
    hoursMenu.style.top  = (r.bottom + 6 + window.scrollY) + 'px';
  }

  // Toggle popover
  if (hoursBtn && hoursMenu){
    hoursBtn.addEventListener('click', function(e){
      e.stopPropagation();
      placeMenu();
      if (!hoursMenu.classList.contains('show')) seedInputs();
      hoursMenu.classList.toggle('show');
      hoursMenu.setAttribute('aria-hidden', hoursMenu.classList.contains('show') ? 'false' : 'true');
    }, false);

    document.addEventListener('click', function(e){
      if (!hoursMenu.contains(e.target) && e.target !== hoursBtn){
        hoursMenu.classList.remove('show');
        hoursMenu.setAttribute('aria-hidden','true');
      }
    }, false);

    window.addEventListener('resize', function(){
      if (hoursMenu.classList.contains('show')) placeMenu();
    }, { passive:true });
  }

  // Apply hours -> update URL (preserving date, debug, cohorts)
  function applyHours(startHM, endHM){
    var sh = parseInt(startHM.split(':')[0],10);
    var eh = parseInt(endHM.split(':')[0],10);
    if (isNaN(sh) || isNaN(eh) || eh <= sh){
      alert('Please choose a valid Start/End window (End must be after Start).');
      return;
    }
    try{
      localStorage.setItem('hoursStart', startHM);
      localStorage.setItem('hoursEnd',   endHM);
    }catch(_){}

    var p = new URLSearchParams(location.search);
    var date = p.get('date') || (typeof currentDateParam==='function' ? currentDateParam() : '');
    var debug = p.get('debug') ? '&debug=1' : '';
    var cohorts = p.get('cohorts') ? ('&cohorts='+encodeURIComponent(p.get('cohorts'))) : '';
    location.search = '?date=' + encodeURIComponent(date) +
                      '&hstart=' + sh + '&hend=' + eh + debug + cohorts;
  }

if (applyBtn) applyBtn.addEventListener('click', function(){
  applyHours(hStartInp.value, hEndInp.value);
  // close popover for clear feedback
  if (hoursMenu){
    hoursMenu.classList.remove('show');
    hoursMenu.setAttribute('aria-hidden','true');
  }
}, false);
	
// Pressing Enter in either time input applies
[hStartInp, hEndInp].forEach(function(inp){
  if (!inp) return;
  inp.addEventListener('keydown', function(e){
    if (e.key === 'Enter'){
      applyHours(hStartInp.value, hEndInp.value);
      if (hoursMenu){
        hoursMenu.classList.remove('show');
        hoursMenu.setAttribute('aria-hidden','true');
      }
    }
  }, false);
});	

if (resetBtn) resetBtn.addEventListener('click', function(){
  applyHours('05:00','23:00');
  if (hoursMenu){
    hoursMenu.classList.remove('show');
    hoursMenu.setAttribute('aria-hidden','true');
  }
}, false);

  // Grid toggle
  function refreshGridButton(){
    if (!gridBtn) return;
    gridBtn.textContent = 'Grid: ' + (GRID_ON ? 'On' : 'Off');
    gridBtn.dataset.on = GRID_ON ? '1' : '0';
  }
  if (gridBtn){
    refreshGridButton();
    gridBtn.addEventListener('click', function(){
      GRID_ON = !GRID_ON;
      try{ localStorage.setItem('gridLines', GRID_ON ? '1' : '0'); }catch(_){}
      drawTimeGrid();   // refresh immediately
      refreshGridButton();
    }, false);
  }
})();	
	
function tick(){
  var c = document.getElementById('clock');
  if (c) c.textContent = new Date().toLocaleTimeString();
}
setInterval(tick, 1000); tick();

function currentDateParam(){
  var inp = document.getElementById('pick');
  return (inp && inp.value) ? inp.value : ((DATA && DATA.date) || '<?php echo safe($date); ?>');
}

function goReminders(){
  var d = currentDateParam();
  location.href = '?page=reminders&date=' + encodeURIComponent(d);
}


/* =========================================================
   AI Scheduler — Buttons inside the AI chat modal
   Matches your HTML IDs: aiRefreshDrafts / aiClearDrafts / aiFinalize
   ========================================================= */

/* AI chat — SEND handler (wire up #aiSend + Enter to call ?api=ai_schedule) */
(function(){
  var input = document.getElementById('aiInput');
  var send  = document.getElementById('aiSend');
  var box   = document.getElementById('aiChat');

  if (!input || !send || !box) return; // modal not in DOM yet

  function appendYou(msg){
    var you = document.createElement('div');
    you.style.cssText = 'margin:8px 0; color:#0f172a; font-size:13px;';
    you.textContent = 'You: ' + msg;
    box.appendChild(you);
    box.scrollTop = box.scrollHeight;
  }

  function postToAI(msg){
    // show user line
    appendYou(msg);

    // call backend; expects { ok:true, reply:"...", created_drafts:true|false }
    fetch('?api=ai_schedule', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        prompt: msg,
        date: (typeof currentDateParam === 'function') ? currentDateParam() : null
      })
    })
    .then(r => r.json())
    .then(js => {
      if (!js || js.ok !== true){
        aiAppendSystemLine('⚠️ AI request failed. Implement ?api=ai_schedule to return { ok:true, reply:"..." }.');
        return;
      }
      if (js.reply) aiAppendSystemLine(js.reply);

      // If the server says it created drafts, refresh the grid automatically
      if (js.created_drafts) {
        if (typeof window.forceRefresh === 'function') window.forceRefresh();
        else if (typeof fetchDay === 'function') fetchDay();
      }
    })
    .catch(() => {
      aiAppendSystemLine('⚠️ Network error calling ?api=ai_schedule.');
    });
  }

  send.addEventListener('click', function(){
    var msg = (input.value || '').trim();
    if (!msg) return;
    postToAI(msg);
    input.value = '';
    input.focus();
  }, false);

  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send.click();
    }
  }, false);
})();

/* Small helper to append a system line into the chat window */
function aiAppendSystemLine(text){
  var box = document.getElementById('aiChat');
  if (!box) return;
  var p = document.createElement('div');
  p.style.cssText = 'margin:8px 0; color:#475569; font-size:13px;';
  p.textContent = text;
  box.appendChild(p);
  box.scrollTop = box.scrollHeight;
}

/* Refresh Drafts */
(function(){
  var btn = document.getElementById('aiRefreshDrafts');
  if (!btn) return;
  btn.addEventListener('click', function(){
    fetch('?api=draft_list')
      .then(function(r){ return r.json(); })
      .then(function(js){
        if (!js || js.ok !== true){
          alert('Failed to load drafts');
          return;
        }
        var n = (js.drafts || []).length;
        aiAppendSystemLine('Drafts refreshed ('+n+' found). Dashed draft pills are now visible on the schedule.');
        // Re-render the main grid so the dashed draft pills show up
        if (typeof window.forceRefresh === 'function') window.forceRefresh();
        else if (typeof fetchDay === 'function') fetchDay();
      })
      .catch(function(){
        alert('Draft list failed');
      });
  }, false);
})();

/* Finalize Drafts */
(function(){
  var btn = document.getElementById('aiFinalize');
  if (!btn) return;
  btn.addEventListener('click', function(){
    if (!confirm('Finalize ALL current drafts into real reservations?')) return;
    fetch('?api=draft_finalize', { method:'POST' })
      .then(function(r){ return r.json(); })
      .then(function(js){
        if (!js || js.ok !== true){
          alert('Finalize failed');
          return;
        }
        aiAppendSystemLine('Drafts finalized. The schedule now shows confirmed reservations.');
        if (typeof window.forceRefresh === 'function') window.forceRefresh();
        else if (typeof fetchDay === 'function') fetchDay();
      })
      .catch(function(){
        alert('Finalize failed');
      });
  }, false);
})();

/* Clear Drafts */
(function(){
  var btn = document.getElementById('aiClearDrafts');
  if (!btn) return;
  btn.addEventListener('click', function(){
    if (!confirm('Delete ALL current drafts?')) return;
    fetch('?api=draft_clear', { method:'POST' })
      .then(function(r){ return r.json(); })
      .then(function(js){
        if (!js || js.ok !== true){
          alert('Clear failed');
          return;
        }
        aiAppendSystemLine('All drafts cleared.');
        if (typeof window.forceRefresh === 'function') window.forceRefresh();
        else if (typeof fetchDay === 'function') fetchDay();
      })
      .catch(function(){
        alert('Clear failed');
      });
  }, false);
})();


/* ---------- Boot (safe) — ensure timeline renders immediately ---------- */
(function () {
  function once(fn){
    try { fn(); } catch(e){ console.warn('[boot]', e); }
  }
  function start() {
    // 1) Make sure the hour header shows even if data fetch is slow or fails
    if (typeof buildHourHeader === 'function') {
      once(buildHourHeader);
    } else {
      // If S-16 hasn’t defined it yet, try again shortly.
      setTimeout(start, 15);
      return;
    }

    // 2) Kick the data pipeline
    if (typeof fetchDay === 'function') {
      // Expose an easy manual refresh for debugging
      window.forceRefresh = fetchDay;
      // Fetch (this will call renderRows, drawLines, etc.)
      once(fetchDay);
    } else {
      // Wait for S-16 to finish parsing
      setTimeout(start, 15);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();

/* ---------- Resize: redraw guide lines (light debounce) ---------- */
(function(){
  var _t = null;
  window.addEventListener('resize', function(){
    if (_t) clearTimeout(_t);
    _t = setTimeout(function(){
      if (typeof drawLines === 'function') drawLines();
    }, 80);
  }, { passive:true });
})();


// ===== Cohort dropdown wiring + render under grid (with persistence) =====
(function(){
  var btn    = document.getElementById('cohortBtn');
  var COHORT_IS_RENDERING = false;
  var panel  = document.getElementById('cohortPanel');
  var listEl = document.getElementById('cohortList');
  var badge  = document.getElementById('cohortBadge');
  var srch   = document.getElementById('cohortSearch');
  var rowsGrid = document.getElementById('rowsGrid');

  var LS_KEY = 'sched.cohorts.selected';  // <— persist checked cohort IDs here

  var selected = new Set();      // string IDs
  var allCohorts = [];
  var fullStudentHTML = null;
  var cohortGroupsCache = [];

  // ---------- Persistence helpers ----------
  function saveSelected(){
    try{
      var csv = Array.from(selected).join(',');
      if (csv) localStorage.setItem(LS_KEY, csv);
      else localStorage.removeItem(LS_KEY);
    }catch(e){}
  }
  function loadSelected(){
    selected.clear();
    try{
      var csv = localStorage.getItem(LS_KEY);
      if (csv && csv.trim()){
        csv.split(',').map(function(x){return x.trim();}).filter(Boolean).forEach(function(id){
          selected.add(id);
        });
      }
    }catch(e){}
  }

  // Initial load of persisted selection (before any UI work)
  loadSelected();

  function togglePanel(show){ panel.classList[show?'add':'remove']('show'); }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

  btn.addEventListener('click', function(e){
    e.stopPropagation();
    togglePanel(!panel.classList.contains('show'));
    if (panel.classList.contains('show') && !allCohorts.length) loadCohorts();
  });
  document.addEventListener('click', function(e){
    if (!panel.contains(e.target) && e.target!==btn) togglePanel(false);
  });

  function loadCohorts(){
    listEl.innerHTML = '<div class="dditem">Loading…</div>';
    fetch('?api=cohort_list')
      .then(function(r){return r.json();})
      .then(function(js){
        if (!js.ok){ listEl.innerHTML = '<div class="dditem">Failed to load.</div>'; return; }
        allCohorts = js.cohorts||[];
        renderList();
      });
  }

  function renderList(){
    var q = (srch.value||'').toLowerCase();
    listEl.innerHTML = '';
    var shown = 0;
    for (var i=0;i<allCohorts.length;i++){
      var c = allCohorts[i];
      var label = c.name + (c.program?(' · '+c.program):'') +
                  ((c.start_date||c.end_date)?(' — '+(c.start_date||'')+' → '+(c.end_date||'')):'');
      if (q && label.toLowerCase().indexOf(q)===-1) continue;
      shown++;
      var cidStr = String(c.id);
      var id = 'ch_c_'+cidStr;
      var row = document.createElement('div');
      row.className = 'dditem';
      row.innerHTML = '<input type="checkbox" id="'+id+'" '+(selected.has(cidStr)?'checked':'')+'>'
                    + '<label for="'+id+'" style="cursor:pointer;flex:1">'+escapeHtml(label)+'</label>';
      row.querySelector('input').addEventListener('change', function(){
        var cid = this.id.replace('ch_c_','');
        if (this.checked) selected.add(cid); else selected.delete(cid);
        saveSelected();
        updateBadge();
      });
      listEl.appendChild(row);
    }
    if (!shown) listEl.innerHTML = '<div class="dditem">No matches.</div>';
  }

  srch.addEventListener('input', renderList);

  document.getElementById('cohortSelAll').addEventListener('click', function(){
    for (var i=0;i<allCohorts.length;i++) selected.add(String(allCohorts[i].id));
    saveSelected();
    renderList(); updateBadge();
  });
  document.getElementById('cohortSelNone').addEventListener('click', function(){
    selected.clear();
    saveSelected();
    renderList(); updateBadge();
  });
  document.getElementById('cohortClear').addEventListener('click', function(){
    selected.clear();
    saveSelected();
    updateBadge();
    restoreStudents();
    cohortGroupsCache = [];
    renderCohortStudents(); // clears from grid
    togglePanel(false);
  });
  document.getElementById('cohortApply').addEventListener('click', function(){
    applyCohorts();
    togglePanel(false);
  });

  function updateBadge(){
    var n = selected.size;
    if (n>0){ badge.style.display='inline-block'; badge.textContent = n+' selected'; }
    else { badge.style.display='none'; }
  }
  // reflect persisted selection on first paint
  updateBadge();

  /* ===== Modal Student/User filtering ===== */
  function ensureStudentBackup(){
    if (fullStudentHTML===null){
      var sel = document.getElementById('f_student');
      if (sel) fullStudentHTML = sel.innerHTML;
    }
  }
  function restoreStudents(){
    var sel = document.getElementById('f_student');
    if (sel && fullStudentHTML!==null) sel.innerHTML = fullStudentHTML;
  }

  function applyCohorts(){
    if (!selected.size){
      restoreStudents();
      cohortGroupsCache = [];
      renderCohortStudents();
      return;
    }
    ensureStudentBackup();
    var ids = Array.from(selected).join(',');
    // keep ids persisted (in case apply is clicked from a fresh page)
    saveSelected();

    fetch('?api=cohort_students&ids='+encodeURIComponent(ids))
      .then(function(r){return r.json();})
      .then(function(js){
        // 1) Modal list
        var sel = document.getElementById('f_student');
        if (sel){
          if (!js.ok){ alert('Failed to load students for cohorts'); return; }
          var html = '';
          var groups = js.groups||[];
          for (var i=0;i<groups.length;i++){
            var g = groups[i];
            html += '<optgroup label="Cohort: '+escapeHtml(g.name)+'">';
            var users = g.users||[];
            for (var k=0;k<users.length;k++){
              var u = users[k];
              var name = (u.first_name||'')+' '+(u.last_name||'');
              html += '<option value="'+escapeHtml(String(u.id))+'">'+escapeHtml(name)+'</option>';
            }
            html += '</optgroup>';
          }
          if (!html) html = '<optgroup label="Cohort"><option value="">No users</option></optgroup>';
          sel.innerHTML = html;
        }

        // 2) Cache + grid render
        cohortGroupsCache = (js.ok && js.groups) ? js.groups : [];
        renderCohortStudents();
      });
  }

  /* ===== Grid rendering of selected cohorts (rows under existing sections) ===== */
  function clearCohortRows(){
    if (!rowsGrid) return;
    var nodes = rowsGrid.querySelectorAll('[data-sec="cohorts"]');
    for (var i=0;i<nodes.length;i++) nodes[i].parentNode.removeChild(nodes[i]);
  }

  function addSectionLabel(text){
    var lab = document.createElement('div');
    lab.className = 'sectionLabel';
    lab.setAttribute('data-sec','cohorts');
    lab.textContent = text;
    var sp = document.createElement('div');
    sp.className = 'sectionSpacer';
    sp.setAttribute('data-sec','cohorts');
    rowsGrid.appendChild(lab);
    rowsGrid.appendChild(sp);
  }

  function addStudentRow(fullName){
    var rl = document.createElement('div');
    rl.className = 'rlabel';
    rl.setAttribute('data-sec','cohorts');
    rl.innerHTML = '<div class="l1">'+escapeHtml(fullName)+'</div>';

    var rc = document.createElement('div');
    rc.className = 'rcell';
    rc.setAttribute('data-sec','cohorts');

    rowsGrid.appendChild(rl);
    rowsGrid.appendChild(rc);
    return rc;
  }

  function _hmToMin(hm){
    if(!hm)return null;
    var s=String(hm).slice(0,5);
    var h=parseInt(s.substr(0,2),10),m=parseInt(s.substr(3,2),10);
    if(isNaN(h)||isNaN(m))return null;
    return h*60+m;
  }

  function _cohortHasStudent(res, uid){
    var arr = (DATA && DATA.res_students && DATA.res_students[res.res_id]) ? DATA.res_students[res.res_id] : [];
    for (var i=0;i<arr.length;i++){
      if (String(arr[i].userid) === String(uid)) return true;
    }
    return false;
  }

  function renderOneCohortStudentRow(cell, uid){
    if (!cell) return;

    // open modal on click
    cell.addEventListener('click', function(e){
      var rect = cell.getBoundingClientRect();
      var rel = (e.clientX - rect.left) / rect.width;
      rel = Math.max(0, Math.min(1, rel));
      var mins = H_START*60 + Math.round(rel * ((H_END - H_START)*60));
      mins = Math.floor(mins/15)*15;
      openModalPrefill(null, null, Math.floor(mins/60), mins%60, null);
      setTimeout(function(){
        var sel = document.getElementById('f_student');
        if (sel){
          for (var i=0;i<sel.options.length;i++){
            sel.options[i].selected = (String(sel.options[i].value) === String(uid));
          }
          if (typeof updateMissionField === 'function') updateMissionField();
        }
      }, 0);
    }, false);

    // Availability overlay for student (optional – uses same DATA.availability map)
    try{
      var av = (DATA && DATA.availability) ? DATA.availability : null;
      var entry = (av && (av[uid] || av[String(uid)])) ? (av[uid] || av[String(uid)]) : null;
      if (entry){
        var mode  = (entry.mode || 'unavailability').toLowerCase();
        var rules = entry.rules || [];
        var dayStart = H_START*60, dayEnd = H_END*60, dayTotal = (H_END - H_START)*60;
        var spans = [];
        for (var r=0;r<rules.length;r++){
          var sM=_hmToMin(rules[r].start), eM=_hmToMin(rules[r].end);
          if (sM==null||eM==null) continue;
          sM=Math.max(dayStart,Math.min(dayEnd,sM));
          eM=Math.max(dayStart,Math.min(dayEnd,eM));
          if(eM>sM) spans.push({s:sM,e:eM});
        }
        spans.sort(function(a,b){return a.s-b.s;});
        var merged=[];
        for (var i=0;i<spans.length;i++){
          if(!merged.length||spans[i].s>merged[merged.length-1].e) merged.push({s:spans[i].s,e:spans[i].e});
          else merged[merged.length-1].e=Math.max(merged[merged.length-1].e,spans[i].e);
        }
        var unav=[];
        if (mode==='unavailability') unav=merged;
        else{
          var cur=dayStart;
          for(var j=0;j<merged.length;j++){
            if(merged[j].s>cur) unav.push({s:cur,e:merged[j].s});
            cur=Math.max(cur,merged[j].e);
          }
          if(cur<dayEnd) unav.push({s:cur,e:dayEnd});
        }
        // draw
        if (window.getComputedStyle(cell).position === 'static') cell.style.position='relative';
        for(var k=0;k<unav.length;k++){
          var seg=unav[k];
          var left=((seg.s-dayStart)/dayTotal)*100;
          var w=((seg.e-seg.s)/dayTotal)*100;
          var bg=document.createElement('div');
          bg.className='unavail-bg';
          bg.style.position='absolute';
          bg.style.left=left+'%';
          bg.style.width=w+'%';
          bg.style.top='0';bg.style.bottom='0';
          bg.style.background='#e5e7eb';
          bg.style.opacity='0.55';
          bg.style.pointerEvents='none';
          cell.appendChild(bg);
        }
      }
    }catch(e){ console.warn('student availability paint failed', e); }

    // Reservations
    var rlist=(DATA && DATA.reservations)?DATA.reservations:[];
    var items=[];
    for (var i=0;i<rlist.length;i++){
      var r=rlist[i];
      if (!_cohortHasStudent(r,uid)) continue;
      if (!r.start_dt||!r.end_dt) continue;
      var instr=((r.instr_first||'')+(r.instr_last?(' '+r.instr_last):'')).trim()||'—';
      var t1=String(r.start_dt).substr(11,5),t2=String(r.end_dt).substr(11,5);
      var timeTxt=t1+'-'+t2;
      var meta=r.dev_name||r.mission_code||r.mission_name||'';
      var metaLine=meta?(timeTxt+' | '+meta):timeTxt;
      var mission=(r.mission_code&&r.mission_name)?(r.mission_code+' — '+r.mission_name):(r.mission_code||r.mission_name||'—');
      items.push({
        res_id:r.res_id,start:r.start_dt,end:r.end_dt,
        _line1:instr,_line2:metaLine,
        tipLines:['Instructor: '+instr,'Device: '+(r.dev_name||'—'),'Mission: '+mission,'Time: '+timeTxt]
      });
    }

    if (items.length){
      var packed=packLanes(items),SLOT_H=44,GAP=6,TOP0=6;
      var totalH=TOP0+packed.lanes*(SLOT_H+GAP)-GAP+TOP0;
      cell.style.minHeight=Math.max(54,totalH)+'px';
      for(var p=0;p<packed.items.length;p++){
        var it=packed.items[p];
        var slot=document.createElement('div');
        slot.className='slot staff';
        slot.setAttribute('data-force-above','1');
        slot.setAttribute('data-res-id', String(it.res_id));
        slot.style.left=timeToX(it.start)+'%';
        slot.style.width=widthPct(it.start,it.end)+'%';
        slot.style.top=(TOP0+it._lane*(SLOT_H+GAP))+'px';
        slot.addEventListener('click',function(e){
          e.stopPropagation();
          var id=this.getAttribute('data-res-id');
          if(id) openReservationEditor(parseInt(id,10));
        },false);
        var txt=document.createElement('span');
        txt.className='slotText';
        txt.innerHTML='<span class="name">'+esc(it._line1)+'</span><span class="meta">'+esc(it._line2)+'</span>';
        slot.appendChild(txt);
        var tip=document.createElement('div');
        tip.className='tooltip';
        tip.innerHTML=esc(it.tipLines.join('\n')).replace(/\n/g,'<br>');
        slot.appendChild(tip);
        cell.appendChild(slot);
      }
    } else {
      cell.style.minHeight='54px';
    }
  }

  function renderCohortStudents(){
    COHORT_IS_RENDERING = true;
    clearCohortRows();

    if (!rowsGrid || !cohortGroupsCache || cohortGroupsCache.length === 0) {
      COHORT_IS_RENDERING = false;
      return;
    }

    addSectionLabel('Students (Cohorts)');

    for (var i = 0; i < cohortGroupsCache.length; i++) {
      var g = cohortGroupsCache[i];
      addSectionLabel('— ' + g.name);
      var users = g.users || [];
      for (var k = 0; k < users.length; k++) {
        var u = users[k];
        var name = (u.first_name || '') + ' ' + (u.last_name || '');
        var cell = addStudentRow(name);
        renderOneCohortStudentRow(cell, u.id);
      }
    }

    if (typeof attachTooltipBehavior === 'function') attachTooltipBehavior(rowsGrid);
    COHORT_IS_RENDERING = false;
  }

  // Re-inject cohort rows when base grid rebuilds (e.g., day nav)
  var mo = new MutationObserver(function(){
    if (COHORT_IS_RENDERING) return;
    var hasCohortNodes = !!rowsGrid.querySelector('[data-sec="cohorts"]');
    if (hasCohortNodes) return;
    if (renderCohortStudents._t) clearTimeout(renderCohortStudents._t);
    renderCohortStudents._t = setTimeout(renderCohortStudents, 0);
  });
  if (rowsGrid){ mo.observe(rowsGrid, {childList:true, subtree:false}); }

  // ---------- Auto-apply persisted cohorts on page load ----------
  (function autoApplyOnBoot(){
    if (selected.size > 0) {
      // If the dropdown isn’t loaded yet, that’s fine; we only need the IDs.
      applyCohorts();    // will fetch and render; observer re-injects after date changes
    }
  })();
})();

/* =========================================================
   AI Scheduler — Modal open/close wiring + buttons
   ========================================================= */
(function(){
  var wrap = document.getElementById('aiWrap');
  var btn  = document.getElementById('aiBtn');
  var close= document.getElementById('aiClose');
  var send = document.getElementById('aiSend');
  var input= document.getElementById('aiInput');
  var chat = document.getElementById('aiChat');

  function open(){ if (!wrap) return; wrap.classList.add('show'); wrap.setAttribute('aria-hidden','false'); }
  function closeModal(){ if (!wrap) return; wrap.classList.remove('show'); wrap.setAttribute('aria-hidden','true'); }

  if (btn && !btn.__bound){ btn.__bound = true; btn.addEventListener('click', open); }
  if (close && !close.__bound){ close.__bound = true; close.addEventListener('click', closeModal); }

  wrap.addEventListener('click', function(e){ if (e.target === wrap) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape' && wrap.classList.contains('show')) closeModal(); });

  function aiAppendSystemLine(text){
    if (!chat) return;
    var p = document.createElement('div');
    p.style.cssText = 'margin:8px 0; color:#475569; font-size:13px;';
    p.textContent = text;
    chat.appendChild(p);
    chat.scrollTop = chat.scrollHeight;
  }
  window.aiAppendSystemLine = aiAppendSystemLine;

  // Placeholder send (you can hook OpenAI later)
  if (send){ send.addEventListener('click', function(){
    var msg = (input.value||'').trim();
    if(!msg) return;
    aiAppendSystemLine('You: ' + msg);
    input.value='';
  }); }

  // Refresh / Finalize / Clear
  document.getElementById('aiRefreshDrafts').addEventListener('click', function(){
    fetch('?api=draft_list').then(r=>r.json()).then(js=>{
      if(!js||js.ok!==true) return alert('Failed to load drafts');
      aiAppendSystemLine('Drafts refreshed ('+(js.drafts||[]).length+' found).');
      if(typeof window.forceRefresh==='function')window.forceRefresh();
      else if(typeof fetchDay==='function')fetchDay();
    }).catch(()=>alert('Draft list failed'));
  });

  document.getElementById('aiFinalize').addEventListener('click', function(){
    if(!confirm('Finalize ALL current drafts into real reservations?'))return;
    fetch('?api=draft_finalize',{method:'POST'}).then(r=>r.json()).then(js=>{
      if(!js||js.ok!==true)return alert('Finalize failed');
      aiAppendSystemLine('Drafts finalized.');
      if(typeof window.forceRefresh==='function')window.forceRefresh();
      else if(typeof fetchDay==='function')fetchDay();
    }).catch(()=>alert('Finalize failed'));
  });

  document.getElementById('aiClearDrafts').addEventListener('click', function(){
    if(!confirm('Delete ALL current drafts?'))return;
    fetch('?api=draft_clear',{method:'POST'}).then(r=>r.json()).then(js=>{
      if(!js||js.ok!==true)return alert('Clear failed');
      aiAppendSystemLine('All drafts cleared.');
      if(typeof window.forceRefresh==='function')window.forceRefresh();
      else if(typeof fetchDay==='function')fetchDay();
    }).catch(()=>alert('Clear failed'));
  });
})();
	
	</script>	

	
</body>
</html>	
	