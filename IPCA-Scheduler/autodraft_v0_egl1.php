<?php
/* =========================================================
   autodraft_v0_egl1.php — AI Autodraft Engine (EGL1)
   PHP 5.3-compatible. Uses latin1 where relevant.

   What’s included:
   - Cohorts + student_ids intake
   - Program-sensitive next-scenario picking (BRIEF / SIM / FLIGHT)
   - MIX modes (BRIEF, SIM, FLIGHT in one call)
   - Grouping for BRIEFs and SIMs; solo/with backseat for FLIGHT (future)
   - SIM packs (per_student, sim_pack)
   - Device/Instructor mutex & optional hard requirement
   - “Why skipped” JSON + deep debug
   - PATCH: brief_ahead (A2 rule) — allow scheduling up to N future BRIEFs
   - PATCH: ignore_gates for testing (bypass brief/sim gates)
   - CANCEL helpers (preview window / by id)

   Tables used:
   - programs_users (pu_user, pu_program, pu_start, pu_id)
   - programs (pr_id, pr_name, pr_db)
   - scenarios (sc_id, sc_program, sc_code, sc_name, sc_type, sc_stage, sc_phase, sc_order)
   - scenario_tracking_<pr_db> with columns we autodetect:
     * sctr_scenario_id (FK -> scenarios.sc_id)
     * sctr_student
     * sctr_date
     * sctr_grading  (G/Y/R Complete/Incomplete/Repeat/Fail text variants supported)
   - reservation_drafts (id, created_by, created_at, res_type, start_dt, end_dt,
                         device_id, instructor_user_id, route, mission_code,
                         mission_name, student_ids)

   Author: ChatGPT (bundle drop-in)
   ========================================================= */

ini_set('display_errors','1'); error_reporting(E_ALL);

/* -------- DB CONFIG (from your environment) -------- */
$DB_HOST = 'com-linmysql056.srv.combell-ops.net';
$DB_PORT = '3306';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';

/* -------- Helpers -------- */
function get($k, $def=null){ return isset($_GET[$k]) ? $_GET[$k] : $def; }

function pdo() {
  static $pdo = null;
  if ($pdo) return $pdo;
  $dsn = 'mysql:host='.$GLOBALS['DB_HOST'].';port='.$GLOBALS['DB_PORT'].';dbname='.$GLOBALS['DB_NAME'];
  $pdo = new PDO($dsn, $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES latin1"
  ));
  return $pdo;
}
function q($sql,$params=array()){
  $st = pdo()->prepare($sql);
  $st->execute($params);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}
function one($sql,$params=array()){
  $st = pdo()->prepare($sql);
  $st->execute($params);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ? $row : null;
}
function jexit($obj){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($obj);
  exit;
}

/* -------- Params -------- */
$student_ids  = trim((string)get('student_ids','')); // comma list
$cohort_id    = get('cohort_id', null);
$type         = strtoupper(trim((string)get('type','SIM'))); // BRIEF | SIM | FLIGHT | MIX
$mix          = trim((string)get('mix','')); // e.g. "BRIEF SIM", "SIM", "BRIEF FLIGHT", etc (space separated)
$date_from    = (string)get('date_from', date('Y-m-d'));
$days         = max(1, intval(get('days', 1)));
$slot         = (string)get('slot','10:00-11:00'); // HH:MM-HH:MM
$per_student  = max(1, intval(get('per_student', 1)));
$sim_pack     = max(1, intval(get('sim_pack', 1))); // pack size per student (e.g., 2h)
$device_id    = get('device_id', null); if ($device_id==='') $device_id = null;
$instructor_id= get('instructor_id', null); if ($instructor_id==='') $instructor_id = null;
$route        = get('route', null);
$dry          = intval(get('dry',1)); // 1 = preview
$created_by   = get('created_by', null);
$group_brief  = intval(get('group_brief', 1)); // allow group BRIEF
$group_sim    = intval(get('group_sim', 1));   // allow group SIM in a row (serial packing)
$solo_flight  = intval(get('solo_flight', 0)); // force solo flights
$require_both = intval(get('require_device_instructor', 0)); // require both device & instructor
$brief_ahead  = max(0, intval(get('brief_ahead', 0))); // PATCH: allow up to N future BRIEFs
$ignore_gates = intval(get('ignore_gates', 0)); // PATCH: bypass gating checks

/* Cancel helpers */
$cancel       = strtolower(trim((string)get('cancel',''))); // 'preview' or 'id'
$cancel_id    = get('id', null);

/* -------- Time window parsing -------- */
list($hh1,$mm1,$hh2,$mm2) = array(0,0,0,0);
if (preg_match('/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/',$slot,$m)) {
  $hh1=intval($m[1]); $mm1=intval($m[2]); $hh2=intval($m[3]); $mm2=intval($m[4]);
} else {
  jexit(array('ok'=>false,'error'=>'bad_slot','note'=>'Use slot=HH:MM-HH:MM'));
}

/* -------- Cancel Operations -------- */
if ($cancel==='preview') {
  // Example scope: same-day device window
  $day = $date_from;
  $start_dt = $day.' 00:00:00';
  $end_dt   = $day.' 23:59:59';
  $sql = "DELETE FROM reservation_drafts WHERE start_dt >= ? AND end_dt <= ? AND device_id = ?";
  if ($dry) {
    jexit(array('ok'=>true,'dry'=>1,'action'=>'CANCEL_PREVIEW','debug'=>array('sql'=>$sql,'params'=>array($start_dt,$end_dt,(string)$device_id))));
  } else {
    $st = pdo()->prepare($sql);
    $st->execute(array($start_dt, $end_dt, $device_id));
    jexit(array('ok'=>true,'dry'=>0,'action'=>'CANCEL','deleted'=>$st->rowCount()));
  }
}
if ($cancel==='id' && $cancel_id) {
  $sql = "DELETE FROM reservation_drafts WHERE id = ?";
  if ($dry) {
    jexit(array('ok'=>true,'dry'=>1,'action'=>'CANCEL_PREVIEW','debug'=>array('sql'=>$sql,'params'=>array((string)$cancel_id))));
  } else {
    $st = pdo()->prepare($sql);
    $st->execute(array($cancel_id));
    jexit(array('ok'=>true,'dry'=>0,'action'=>'CANCEL','deleted'=>$st->rowCount(),'debug'=>array('sql'=>$sql,'params'=>array((string)$cancel_id))));
  }
}

/* -------- Intake students (cohort or explicit) -------- */
$targets = array();
if ($cohort_id) {
  $rows = q("SELECT cm.student_id
               FROM cohort_members cm
               JOIN cohorts c ON c.id = cm.cohort_id
              WHERE cm.cohort_id = ? AND c.active = 1", array($cohort_id));
  foreach ($rows as $r) $targets[] = intval($r['student_id']);
}
if ($student_ids) {
  foreach (explode(',', $student_ids) as $sid) {
    $sid = trim($sid);
    if ($sid !== '') $targets[] = intval($sid);
  }
}
$targets = array_values(array_unique($targets));

if (!count($targets)) {
  jexit(array('ok'=>true,'dry'=>$dry,'inserted'=>0,'items'=>array(),'note'=>'No target students'));
}

/* -------- Utilities for scenarios & tracking -------- */
function latest_program_for_student($SID){
  return one("
    SELECT p.pr_id, p.pr_name, p.pr_db
      FROM programs_users pu
      JOIN programs p ON p.pr_id = pu.pu_program
     WHERE pu.pu_user = ?
     ORDER BY pu.pu_start DESC, pu.pu_id DESC
     LIMIT 1
  ", array($SID));
}

function detect_track_schema($track_table){
  // Return columns, main keys, and status mapping
  $cols = q("SHOW COLUMNS FROM `$track_table`");
  if (!$cols) return array('error'=>'track_table_missing');

  $have = array();
  foreach ($cols as $c) $have[$c['Field']] = strtolower($c['Type']);

  $need = array('sctr_scenario_id','sctr_student','sctr_date','sctr_grading');
  foreach ($need as $k) if (!isset($have[$k])) {
    return array('error'=>'track_columns_incomplete','columns'=>$have);
  }
  return array(
    'ok'=>true,
    'student_col'=>'sctr_student',
    'when_col'=>'sctr_date',
    'scenario_fk'=>'sctr_scenario_id',
    'columns'=>$have
  );
}

function all_program_scenarios_ordered($prog_id){
  return q("
    SELECT sc_id, sc_code, sc_name, sc_type, sc_stage, sc_phase, sc_order
      FROM scenarios
     WHERE sc_program = ?
     ORDER BY sc_stage, sc_phase, sc_order
  ", array($prog_id));
}

function latest_attempts_by_scenario($track_table, $SID, $scenario_fk, $when_col){
  // latest row per scenario for a student
  $sql = "
    SELECT t1.$scenario_fk AS sc_id, t1.$when_col AS sctr_when, t1.sctr_grading
      FROM `$track_table` t1
      JOIN (
        SELECT $scenario_fk AS scx, MAX($when_col) AS mx
          FROM `$track_table`
         WHERE sctr_student = :sid
         GROUP BY $scenario_fk
      ) t2 ON t2.scx = t1.$scenario_fk AND t2.mx = t1.$when_col
     WHERE t1.sctr_student = :sid
  ";
  $rows = q($sql, array(':sid'=>$SID));
  $map = array();
  foreach ($rows as $r) {
    $map[intval($r['sc_id'])] = array(
      'when'=>$r['sctr_when'],
      'grading'=>strtoupper(trim((string)$r['sctr_grading']))
    );
  }
  return $map;
}

/* Simple grading interpreters */
function is_brief_done($grading){
  // brief marked by sctr_brief time in your legacy, but we only have grading text: accept complete (“GREEN COMPLETE”, “GC”, “G COMPLETE”, “COMPLETE”)
  $g = strtoupper((string)$grading);
  if ($g==='') return false;
  // treat any *COMPLETE* as done for its scenario type
  if (strpos($g,'COMPLETE')!==false) return true;
  // allow “GREEN COMPLETE”, “GC”
  if ($g==='GC' || $g==='GREEN COMPLETE') return true;
  return false;
}
function need_repeat($grading){
  $g = strtoupper((string)$grading);
  return (strpos($g,'REPEAT')!==false || strpos($g,'FAIL')!==false || strpos($g,'INCOMPLETE')!==false);
}

/* Anchor & next-eligible pickers */
function anchor_index_for_student($all, $attempts){
  // Find last completed scenario index; if none, anchor before 0
  $last = -1;
  for ($i=0; $i<count($all); $i++){
    $sc = $all[$i];
    $sid = intval($sc['sc_id']);
    if (isset($attempts[$sid]) && is_brief_done($attempts[$sid]['grading'])) {
      $last = $i;
    }
  }
  return $last+1; // next after last completed BRIEF (approximation)
}

function next_eligible_for_type($SID, $want, $prog, $all, $attempts){
  // Globals for patches
  $brief_ahead  = $GLOBALS['brief_ahead'];
  $ignore_gates = $GLOBALS['ignore_gates'];

  $want = strtoupper($want);
  $anchor_index = anchor_index_for_student($all, $attempts);
  if ($anchor_index<0) $anchor_index=0;

  // Strict pass along the ordered list
  if ($want==='BRIEF'){
    // 1) strict: first BRIEF at/after anchor
    for ($i=$anchor_index; $i<count($all); $i++){
      $sc = $all[$i];
      if (strtoupper($sc['sc_type'])==='BRIEF' || strtoupper($sc['sc_type'])==='LB'){
        return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog,'anchor_at'=>$all[max(0,$anchor_index-1)]);
      }
    }
    // 2) PATCH: brief_ahead — allow jumping up to N future BRIEFs counted from last completed BRIEF
    if ($brief_ahead>0){
      // last completed BRIEF idx
      $last_brief_idx = -1;
      for ($i=0; $i<count($all); $i++){
        $sc = $all[$i];
        if (strtoupper($sc['sc_type'])==='BRIEF' || strtoupper($sc['sc_type'])==='LB'){
          $sid = intval($sc['sc_id']);
          if (isset($attempts[$sid]) && is_brief_done($attempts[$sid]['grading'])) $last_brief_idx = $i;
        }
      }
      $found = 0;
      for ($i=$last_brief_idx+1; $i<count($all); $i++){
        $sc = $all[$i];
        if (strtoupper($sc['sc_type'])==='BRIEF' || strtoupper($sc['sc_type'])==='LB'){
          $found++;
          if ($found <= $brief_ahead){
            return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog,'anchor_at'=>$all[max(0,$anchor_index-1)]);
          } else {
            break;
          }
        }
      }
    }
    return array('error'=>'no_next_brief','anchor_at'=>isset($all[max(0,$anchor_index-1)])?$all[max(0,$anchor_index-1)]:null);
  }

  if ($want==='SIM'){
    // Must have a BRIEF before SIM unless ignore_gates
    for ($i=$anchor_index; $i<count($all); $i++){
      $sc = $all[$i];
      $t  = strtoupper($sc['sc_type']);
      if ($t==='SIM' || $t==='FNPT' || $t==='SAB'){
        // gate: previous BRIEF in order done?
        if ($ignore_gates) return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog,'anchor_at'=>$all[max(0,$anchor_index-1)]);
        // find nearest previous BRIEF index
        $prev_brief_idx = -1;
        for ($j=$i-1; $j>=0; $j--){
          $p = $all[$j];
          $pt = strtoupper($p['sc_type']);
          if ($pt==='BRIEF' || $pt==='LB'){ $prev_brief_idx = $j; break; }
        }
        $brief_ok = true;
        if ($prev_brief_idx>=0){
          $pb = $all[$prev_brief_idx];
          $pbid = intval($pb['sc_id']);
          $brief_ok = isset($attempts[$pbid]) && is_brief_done($attempts[$pbid]['grading']);
        }
        if ($brief_ok) return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog,'anchor_at'=>$all[max(0,$anchor_index-1)]);
      }
    }
    // if no forward SIM, optionally pick earliest pending SIM in program (fallback)
    // choose the first SIM-type without a COMPLETE or with REPEAT/INCOMPLETE
    for ($i=0; $i<count($all); $i++){
      $sc = $all[$i];
      $t = strtoupper($sc['sc_type']);
      if ($t==='SIM' || $t==='FNPT' || $t==='SAB'){
        $sid = intval($sc['sc_id']);
        $done = isset($attempts[$sid]) && is_brief_done($attempts[$sid]['grading']);
        if (!$done || (isset($attempts[$sid]) && need_repeat($attempts[$sid]['grading']))){
          if ($ignore_gates) return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog);
          // still require the nearest previous brief if exists
          $prev_brief_idx = -1;
          for ($j=$i-1; $j>=0; $j--){
            $p = $all[$j]; $pt = strtoupper($p['sc_type']);
            if ($pt==='BRIEF' || $pt==='LB'){ $prev_brief_idx = $j; break; }
          }
          $brief_ok = true;
          if ($prev_brief_idx>=0){
            $pb = $all[$prev_brief_idx]; $pbid = intval($pb['sc_id']);
            $brief_ok = isset($attempts[$pbid]) && is_brief_done($attempts[$pbid]['grading']);
          }
          if ($brief_ok) return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog);
        }
      }
    }
    return array('error'=>'no_eligible_sim','anchor_at'=>isset($all[max(0,$anchor_index-1)])?$all[max(0,$anchor_index-1)]:null);
  }

  if ($want==='FLIGHT'){
    // Must have previous SIM (if any between briefs) and previous BRIEF unless ignore_gates
    for ($i=$anchor_index; $i<count($all); $i++){
      $sc = $all[$i];
      if (strtoupper($sc['sc_type'])==='FLIGHT'){
        if ($ignore_gates) return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog,'anchor_at'=>$all[max(0,$anchor_index-1)]);
        // gate 1: previous BRIEF
        $prev_brief_idx = -1;
        for ($j=$i-1; $j>=0; $j--){
          $p = $all[$j]; $pt=strtoupper($p['sc_type']);
          if ($pt==='BRIEF' || $pt==='LB'){ $prev_brief_idx = $j; break; }
        }
        $brief_ok = true;
        if ($prev_brief_idx>=0){
          $pb = $all[$prev_brief_idx]; $pbid = intval($pb['sc_id']);
          $brief_ok = isset($attempts[$pbid]) && is_brief_done($attempts[$pbid]['grading']);
        }
        // gate 2: any SIM after that brief and before this flight must be done (if SIM exists)
        $sim_needed_ok = true;
        if ($prev_brief_idx>=0){
          for ($k=$prev_brief_idx+1; $k<$i; $k++){
            $x = $all[$k]; $xt = strtoupper($x['sc_type']);
            if ($xt==='SIM' || $xt==='FNPT' || $xt==='SAB'){
              $sid = intval($x['sc_id']);
              if (!isset($attempts[$sid]) || (!is_brief_done($attempts[$sid]['grading']) || need_repeat($attempts[$sid]['grading']))){
                $sim_needed_ok = false; break;
              }
            }
          }
        }
        if ($brief_ok && $sim_needed_ok) {
          return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog,'anchor_at'=>$all[max(0,$anchor_index-1)]);
        }
      }
    }
    return array('error'=>'no_eligible_flight','anchor_at'=>isset($all[max(0,$anchor_index-1)])?$all[max(0,$anchor_index-1)]:null);
  }

  return array('error'=>'bad_type');
}

/* Reservation mutex checks (simple overlaps) */
function times_overlap($startA,$endA,$startB,$endB){
  return !($endA <= $startB || $startA >= $endB);
}
function device_conflict($device_id,$start_dt,$end_dt){
  $row = one("SELECT id FROM reservation_drafts
               WHERE device_id = ? AND NOT (end_dt <= ? OR start_dt >= ?)
               LIMIT 1", array($device_id, $start_dt, $end_dt));
  return $row ? $row['id'] : null;
}
function instructor_conflict($inst_id,$start_dt,$end_dt){
  $row = one("SELECT id FROM reservation_drafts
               WHERE instructor_user_id = ? AND NOT (end_dt <= ? OR start_dt >= ?)
               LIMIT 1", array($inst_id, $start_dt, $end_dt));
  return $row ? $row['id'] : null;
}

/* Insert draft */
function insert_draft($rec){
  $sql = "INSERT INTO reservation_drafts
          (created_by, res_type, start_dt, end_dt, device_id, instructor_user_id, route,
           mission_code, mission_name, student_ids)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  $st = pdo()->prepare($sql);
  $st->execute(array(
    $rec['created_by'],
    $rec['res_type'],
    $rec['start_dt'],
    $rec['end_dt'],
    $rec['device_id'],
    $rec['instructor_user_id'],
    $rec['route'],
    $rec['mission_code'],
    $rec['mission_name'],
    $rec['student_ids']
  ));
  return pdo()->lastInsertId();
}

/* Build proposals for a single student and one wanted type */
function proposals_for_student($SID, $want_type){
  $lastProg = latest_program_for_student($SID);
  if (!$lastProg) {
    return array(array(
      'student_id'=>$SID,
      'action'=>'NO_PROPOSALS',
      'reason'=>'No program mapping',
      'debug'=>array('student'=>$SID)
    ));
  }
  $track = $lastProg['pr_db']; // already in your data like "scenario_tracking_EASAACP"
  $track_table = $track; // table name is exactly pr_db
  $schema = detect_track_schema($track_table);
  if (isset($schema['error'])){
    return array(array(
      'student_id'=>$SID,
      'action'=>'NO_PROPOSALS',
      'reason'=>'Tracking not usable (missing table/columns)',
      'debug'=>array('program'=>$lastProg,'track'=>$track_table,'schema'=>$schema)
    ));
  }
  $all = all_program_scenarios_ordered($lastProg['pr_id']);
  if (!$all) {
    return array(array(
      'student_id'=>$SID,
      'action'=>'NO_PROPOSALS',
      'reason'=>'No scenarios in program',
      'debug'=>array('program'=>$lastProg)
    ));
  }
  $attempts = latest_attempts_by_scenario($track_table, $SID, $schema['scenario_fk'], $schema['when_col']);

  $pick = next_eligible_for_type($SID, $want_type, $lastProg, $all, $attempts);
  if (isset($pick['error'])){
    return array(array(
      'student_id'=>$SID,
      'action'=>'NO_PROPOSALS',
      'reason'=>$pick['error'],
      'debug'=>array('student'=>$SID,'type'=>$want_type,'program'=>$lastProg,'track'=>$track_table,'schema'=>$schema,'attempts_count'=>count($attempts),'anchor_at'=>isset($pick['anchor_at'])?$pick['anchor_at']:null)
    ));
  }
  $sc = $pick['scenario'];
  // Proposal shell — time window will be applied outside (in main packing)
  return array(array(
    'student_id'=>$SID,
    'res_type'=> (strtoupper($sc['sc_type'])==='BRIEF'||strtoupper($sc['sc_type'])==='LB') ? 'Briefing' : ((strtoupper($sc['sc_type'])==='FLIGHT')?'Flight Training':'Simulator'),
    'device_id'=> null,
    'instructor_user_id'=> null,
    'route'=> null,
    'mission_code'=> $sc['sc_code'],
    'mission_name'=> $sc['sc_name'],
    'student_ids'=> (string)$SID,
    'action'=>'PROPOSAL'
  ));
}

/* -------- Main packing per requested mode -------- */
$items   = array();
$inserted= 0;

/* Require both device & instructor (optional hard rule) */
if ($require_both && (!$device_id || !$instructor_id)){
  jexit(array('ok'=>false,'error'=>'require_device_instructor','note'=>'Pass device_id and instructor_id'));
}

$start_day = new DateTime($date_from.' 00:00:00');
for ($d=0; $d<$days; $d++){
  $day = clone $start_day; if ($d) $day->modify('+'.$d.' day');
  $winStart = clone $day; $winStart->setTime($hh1,$mm1,0);
  $winEnd   = clone $day; $winEnd->setTime($hh2,$mm2,0);
  $windowMin= (int)(($winEnd->getTimestamp() - $winStart->getTimestamp())/60);

  $debug_block = array(
    'type'=>$type,
    'mix'=>$mix,
    'window'=>array('start'=>$winStart->format('Y-m-d H:i'), 'end'=>$winEnd->format('Y-m-d H:i'), 'minutes'=>$windowMin),
    'group_brief'=>$group_brief,
    'group_sim'=>$group_sim,
    'pack_size'=>$sim_pack,
    'solo_flight'=>$solo_flight
  );

  /* Helper to stamp start/end & conflict checks */
  $make_slot = function($base, $s, $e) use ($device_id,$instructor_id) {
    $rec = $base;
    $rec['start_dt'] = $s->format('Y-m-d H:i:s');
    $rec['end_dt']   = $e->format('Y-m-d H:i:s');
    $rec['device_id'] = $rec['res_type']==='Simulator' ? ($rec['device_id']?:$device_id) : $device_id;
    $rec['instructor_user_id'] = $rec['instructor_user_id']?:$instructor_id;
    $rec['route'] = $rec['route']; // keep as is; falls back to global $route where applied

    // Mutex checks
    if ($rec['device_id']){
      $dc = device_conflict($rec['device_id'], $rec['start_dt'], $rec['end_dt']);
      if ($dc) return array('skip'=>true,'item'=>array('student_id'=>$base['student_ids'],'action'=>'SKIP_DEVICE_CONFLICT','start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt']));
    }
    if ($rec['instructor_user_id']){
      $ic = instructor_conflict($rec['instructor_user_id'], $rec['start_dt'], $rec['end_dt']);
      if ($ic) return array('skip'=>true,'item'=>array('student_id'=>$base['student_ids'],'action'=>'SKIP_INSTRUCTOR_CONFLICT','start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt']));
    }
    return array('skip'=>false,'rec'=>$rec);
  };

  if ($type==='SIM'){
    // Each student gets a SIM proposal; pack as group (serial) or parallel
    // 1) collect proposals
    $pps = array();
    foreach ($targets as $SID){
      $p = proposals_for_student($SID, 'SIM'); // respects gates unless ignore_gates=1
      if (!count($p) || $p[0]['action']!=='PROPOSAL'){ $items = array_merge($items,$p); continue; }
      $row = $p[0];
      // decorate globals
      $row['device_id'] = $device_id;
      $row['instructor_user_id'] = $instructor_id;
      $row['route'] = $route;
      $pps[] = $row;
    }
    if (!count($pps)) { /* nothing */ }
    else {
      // Packing
      if ($group_sim){
        // serial slices
        $perMin = max(60, floor($windowMin / max(1,count($pps)))); // default 60 if window bigger
        // if sim_pack>1 → multiply per student
        $perMin = max($perMin, 60 * $sim_pack); // assume 1h per SIM scenario * sim_pack
        $cursor = clone $winStart;
        foreach ($pps as $pp){
          $s = clone $cursor;
          $e = clone $s; $e->modify('+'.$perMin.' minute');
          if ($e > $winEnd) break;

          $r = $make_slot($pp,$s,$e);
          if ($r['skip']) { $items[] = $r['item']; continue; }
          $rec = $r['rec'];

          if ($dry){
            $rec['action'] = 'PREVIEW';
            $items[] = $rec;
          } else {
            $id = insert_draft(array(
              'created_by'=>$GLOBALS['created_by'],
              'res_type'=>$rec['res_type'],
              'start_dt'=>$rec['start_dt'],
              'end_dt'=>$rec['end_dt'],
              'device_id'=>$rec['device_id'],
              'instructor_user_id'=>$rec['instructor_user_id'],
              'route'=>$rec['route'],
              'mission_code'=>$rec['mission_code'],
              'mission_name'=>$rec['mission_name'],
              'student_ids'=>$rec['student_ids'],
            ));
            $items[] = array('student_id'=>$pp['student_ids'],'mission_code'=>$pp['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
            $inserted++;
          }
          $cursor = $e;
        }
      } else {
        // parallel: everyone same slot window
        foreach ($pps as $pp){
          $s = clone $winStart; $e = clone $winEnd;
          $r = $make_slot($pp,$s,$e);
          if ($r['skip']) { $items[] = $r['item']; continue; }
          $rec = $r['rec'];

          if ($dry){
            $rec['action'] = 'PREVIEW';
            $items[] = $rec;
          } else {
            $id = insert_draft(array(
              'created_by'=>$GLOBALS['created_by'],
              'res_type'=>$rec['res_type'],
              'start_dt'=>$rec['start_dt'],
              'end_dt'=>$rec['end_dt'],
              'device_id'=>$rec['device_id'],
              'instructor_user_id'=>$rec['instructor_user_id'],
              'route'=>$rec['route'],
              'mission_code'=>$rec['mission_code'],
              'mission_name'=>$rec['mission_name'],
              'student_ids'=>$rec['student_ids'],
            ));
            $items[] = array('student_id'=>$pp['student_ids'],'mission_code'=>$pp['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
            $inserted++;
          }
        }
      }
    }

    jexit(array('ok'=>true,'dry'=>$dry,'inserted'=>$inserted,'items'=>$items,'debug'=>$debug_block));
  }

  if ($type==='BRIEF'){
    // Group brief (single reservation containing all students) or per-student brief
    $pps = array();
    foreach ($targets as $SID){
      $p = proposals_for_student($SID, 'BRIEF');
      if (!count($p) || $p[0]['action']!=='PROPOSAL'){ $items = array_merge($items,$p); continue; }
      $pps[] = $p[0];
    }
    if (!count($pps)) { /* no briefs */ }
    else {
      if ($group_brief){
        // Single shared reservation
        $codes = array(); $names=array(); $sids=array();
        foreach ($pps as $pp){ $codes[]=$pp['mission_code']; $names[]=$pp['mission_name']; $sids[]=$pp['student_ids']; }
        $code = count(array_unique($codes))===1 ? $codes[0] : $codes[0]; // keep first
        $name = count(array_unique($names))===1 ? $names[0] : $names[0].' (+ mixed)';
        $pp0  = $pps[0];
        $base = array(
          'student_id'=>implode(',',$sids),
          'res_type'=>'Briefing',
          'device_id'=>null,
          'instructor_user_id'=>$GLOBALS['instructor_id'],
          'route'=>null,
          'mission_code'=>$code,
          'mission_name'=>$name,
          'student_ids'=>implode(',',$sids)
        );
        $s = clone $winStart; $e = clone $winEnd;
        $r = $make_slot($base,$s,$e);
        if ($r['skip']) { $items[] = $r['item']; }
        else {
          $rec = $r['rec'];
          if ($dry){ $rec['action']='PREVIEW'; $items[]=$rec; }
          else {
            $id = insert_draft(array(
              'created_by'=>$GLOBALS['created_by'],
              'res_type'=>$rec['res_type'],
              'start_dt'=>$rec['start_dt'],
              'end_dt'=>$rec['end_dt'],
              'device_id'=>$rec['device_id'],
              'instructor_user_id'=>$rec['instructor_user_id'],
              'route'=>$rec['route'],
              'mission_code'=>$rec['mission_code'],
              'mission_name'=>$rec['mission_name'],
              'student_ids'=>$rec['student_ids'],
            ));
            $items[] = array('student_id'=>$base['student_id'],'mission_code'=>$code,'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
            $inserted++;
          }
        }
      } else {
        // Per-student brief same slot
        foreach ($pps as $pp){
          $pp['instructor_user_id'] = $GLOBALS['instructor_id'];
          $s = clone $winStart; $e = clone $winEnd;
          $r = $make_slot($pp,$s,$e);
          if ($r['skip']) { $items[] = $r['item']; continue; }
          $rec = $r['rec'];
          if ($dry){ $rec['action']='PREVIEW'; $items[]=$rec; }
          else {
            $id = insert_draft(array(
              'created_by'=>$GLOBALS['created_by'],
              'res_type'=>$rec['res_type'],
              'start_dt'=>$rec['start_dt'],
              'end_dt'=>$rec['end_dt'],
              'device_id'=>$rec['device_id'],
              'instructor_user_id'=>$rec['instructor_user_id'],
              'route'=>$rec['route'],
              'mission_code'=>$rec['mission_code'],
              'mission_name'=>$rec['mission_name'],
              'student_ids'=>$rec['student_ids'],
            ));
            $items[] = array('student_id'=>$pp['student_ids'],'mission_code'=>$pp['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
            $inserted++;
          }
        }
      }
    }

    jexit(array('ok'=>true,'dry'=>$dry,'inserted'=>$inserted,'items'=>$items,'debug'=>$debug_block));
  }

  if ($type==='FLIGHT'){
    // For now: per-student flight in window; honor gates unless ignore_gates
    foreach ($targets as $SID){
      $p = proposals_for_student($SID, 'FLIGHT');
      if (!count($p) || $p[0]['action']!=='PROPOSAL'){ $items = array_merge($items,$p); continue; }
      $pp = $p[0];
      $pp['device_id'] = $device_id; // aircraft id if mapped like devices table (optional)
      $pp['instructor_user_id'] = $instructor_id;
      $pp['route'] = $route;

      // Use full window or 1h default
      $durMin = min($windowMin, 60); // default 1h flight
      $s = clone $winStart; $e = clone $s; $e->modify('+'.$durMin.' minute');

      $r = $make_slot($pp,$s,$e);
      if ($r['skip']) { $items[]=$r['item']; continue; }
      $rec = $r['rec'];
      if ($dry){ $rec['action']='PREVIEW'; $items[]=$rec; }
      else {
        $id = insert_draft(array(
          'created_by'=>$GLOBALS['created_by'],
          'res_type'=>$rec['res_type'],
          'start_dt'=>$rec['start_dt'],
          'end_dt'=>$rec['end_dt'],
          'device_id'=>$rec['device_id'],
          'instructor_user_id'=>$rec['instructor_user_id'],
          'route'=>$rec['route'],
          'mission_code'=>$rec['mission_code'],
          'mission_name'=>$rec['mission_name'],
          'student_ids'=>$rec['student_ids'],
        ));
        $items[] = array('student_id'=>$pp['student_ids'],'mission_code'=>$pp['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
        $inserted++;
      }
    }

    jexit(array('ok'=>true,'dry'=>$dry,'inserted'=>$inserted,'items'=>$items,'debug'=>$debug_block));
  }

  if ($type==='MIX'){
    // Interpret mix as tokens in order; e.g., "BRIEF SIM", "BRIEF SIM FLIGHT"
    $tokens = preg_split('/\s+/', trim($mix));
    $per_student_debug = array();

    foreach ($tokens as $tk){
      $TK = strtoupper(trim($tk));
      if (!in_array($TK, array('BRIEF','SIM','FLIGHT'))) continue;

      if ($TK==='BRIEF'){
        $pps = array();
        foreach ($targets as $SID){
          $p = proposals_for_student($SID, 'BRIEF');
          if (!count($p) || $p[0]['action']!=='PROPOSAL'){ $items=array_merge($items,$p); $per_student_debug[] = array('student_id'=>$SID,'proposals'=>$p,'debug'=>array()); continue; }
          $pps[] = $p[0]; $per_student_debug[] = array('student_id'=>$SID,'proposals'=>$p,'debug'=>array());
        }
        if (count($pps)){
          if ($group_brief){
            $codes=array(); $names=array(); $sids=array();
            foreach ($pps as $pp){ $codes[]=$pp['mission_code']; $names[]=$pp['mission_name']; $sids[]=$pp['student_ids']; }
            $code = $codes[0];
            $name = count(array_unique($names))===1 ? $names[0] : $names[0].' (+ mixed)';
            $base = array(
              'student_id'=>implode(',',$sids),
              'res_type'=>'Briefing',
              'device_id'=>null,
              'instructor_user_id'=>$GLOBALS['instructor_id'],
              'route'=>null,
              'mission_code'=>$code,
              'mission_name'=>$name,
              'student_ids'=>implode(',',$sids)
            );
            $s = clone $winStart; $e = clone $winEnd; // for MIX we split window evenly per block; here we just use whole window per token in your tests you pass short windows
            $sliceMin = max(30, floor($windowMin / max(1,count($tokens))));
            $e = clone $s; $e->modify('+'.$sliceMin.' minute');

            $r = $make_slot($base,$s,$e);
            if ($r['skip']) { $items[]=$r['item']; }
            else {
              $rec=$r['rec'];
              if ($dry){ $rec['action']='PREVIEW'; $items[]=$rec; }
              else {
                $id = insert_draft(array(
                  'created_by'=>$GLOBALS['created_by'],
                  'res_type'=>$rec['res_type'],
                  'start_dt'=>$rec['start_dt'],
                  'end_dt'=>$rec['end_dt'],
                  'device_id'=>$rec['device_id'],
                  'instructor_user_id'=>$rec['instructor_user_id'],
                  'route'=>$rec['route'],
                  'mission_code'=>$rec['mission_code'],
                  'mission_name'=>$rec['mission_name'],
                  'student_ids'=>$rec['student_ids'],
                ));
                $items[] = array('student_id'=>$base['student_id'],'mission_code'=>$code,'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
                $inserted++;
              }
              // move window start for next token
              $winStart = $e; $windowMin = (int)(($winEnd->getTimestamp()-$winStart->getTimestamp())/60);
            }
          }
        }
      }

      if ($TK==='SIM'){
        $pps = array();
        foreach ($targets as $SID){
          $p = proposals_for_student($SID, 'SIM');
          if (!count($p) || $p[0]['action']!=='PROPOSAL'){ $items=array_merge($items,$p); $per_student_debug[] = array('student_id'=>$SID,'proposals'=>$p,'debug'=>array()); continue; }
          $row = $p[0];
          $row['device_id'] = $device_id;
          $row['instructor_user_id'] = $instructor_id;
          $row['route'] = $route;
          $pps[] = $row;
          $per_student_debug[] = array('student_id'=>$SID,'proposals'=>array($row),'debug'=>array());
        }
        if (count($pps)){
          if ($group_sim){
            // serial packs; slice current remaining window among students
            $perMin = max(60*$GLOBALS['sim_pack'], 60); // default 1h * sim_pack
            $cursor = clone $winStart;
            foreach ($pps as $pp){
              $s = clone $cursor; $e = clone $s; $e->modify('+'.$perMin.' minute');
              if ($e > $winEnd) break;
              $r = $make_slot($pp,$s,$e);
              if ($r['skip']) { $items[] = $r['item']; }
              else {
                $rec = $r['rec'];
                if ($dry){ $rec['action']='PREVIEW'; $items[]=$rec; }
                else {
                  $id = insert_draft(array(
                    'created_by'=>$GLOBALS['created_by'],
                    'res_type'=>$rec['res_type'],
                    'start_dt'=>$rec['start_dt'],
                    'end_dt'=>$rec['end_dt'],
                    'device_id'=>$rec['device_id'],
                    'instructor_user_id'=>$rec['instructor_user_id'],
                    'route'=>$rec['route'],
                    'mission_code'=>$rec['mission_code'],
                    'mission_name'=>$rec['mission_name'],
                    'student_ids'=>$rec['student_ids'],
                  ));
                  $items[] = array('student_id'=>$pp['student_ids'],'mission_code'=>$pp['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
                  $inserted++;
                }
              }
              $cursor = $e;
            }
            $winStart = $cursor; $windowMin = (int)(($winEnd->getTimestamp()-$winStart->getTimestamp())/60);
          } else {
            // parallel: same remaining window
            foreach ($pps as $pp){
              $s = clone $winStart; $e = clone $winEnd;
              $r = $make_slot($pp,$s,$e);
              if ($r['skip']) { $items[] = $r['item']; }
              else {
                $rec = $r['rec'];
                if ($dry){ $rec['action']='PREVIEW'; $items[]=$rec; }
                else {
                  $id = insert_draft(array(
                    'created_by'=>$GLOBALS['created_by'],
                    'res_type'=>$rec['res_type'],
                    'start_dt'=>$rec['start_dt'],
                    'end_dt'=>$rec['end_dt'],
                    'device_id'=>$rec['device_id'],
                    'instructor_user_id'=>$rec['instructor_user_id'],
                    'route'=>$rec['route'],
                    'mission_code'=>$rec['mission_code'],
                    'mission_name'=>$rec['mission_name'],
                    'student_ids'=>$rec['student_ids'],
                  ));
                  $items[] = array('student_id'=>$pp['student_ids'],'mission_code'=>$pp['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
                  $inserted++;
                }
              }
            }
          }
        }
      }

      if ($TK==='FLIGHT'){
        foreach ($targets as $SID){
          $p = proposals_for_student($SID, 'FLIGHT');
          if (!count($p) || $p[0]['action']!=='PROPOSAL'){ $items=array_merge($items,$p); $per_student_debug[] = array('student_id'=>$SID,'proposals'=>$p,'debug'=>array()); continue; }
          $pp = $p[0];
          $pp['device_id'] = $device_id;
          $pp['instructor_user_id'] = $instructor_id;
          $pp['route'] = $route;

          $durMin = min($windowMin, 60);
          $s = clone $winStart; $e = clone $s; $e->modify('+'.$durMin.' minute');

          $r = $make_slot($pp,$s,$e);
          if ($r['skip']) { $items[]=$r['item']; }
          else {
            $rec = $r['rec'];
            if ($dry){ $rec['action']='PREVIEW'; $items[]=$rec; }
            else {
              $id = insert_draft(array(
                'created_by'=>$GLOBALS['created_by'],
                'res_type'=>$rec['res_type'],
                'start_dt'=>$rec['start_dt'],
                'end_dt'=>$rec['end_dt'],
                'device_id'=>$rec['device_id'],
                'instructor_user_id'=>$rec['instructor_user_id'],
                'route'=>$rec['route'],
                'mission_code'=>$rec['mission_code'],
                'mission_name'=>$rec['mission_name'],
                'student_ids'=>$rec['student_ids'],
              ));
              $items[] = array('student_id'=>$pp['student_ids'],'mission_code'=>$pp['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED','id'=>$id);
              $inserted++;
            }
          }
          $winStart = $e; $windowMin = (int)(($winEnd->getTimestamp()-$winStart->getTimestamp())/60);
        }
      }
    } // tokens

    $debug_block['per_student'] = $per_student_debug;
    jexit(array('ok'=>true,'dry'=>$dry,'inserted'=>$inserted,'items'=>$items,'debug'=>$debug_block));
  }

  // fallback
  jexit(array('ok'=>false,'error'=>'bad_type','note'=>'Use type=BRIEF|SIM|FLIGHT|MIX'));

} // days loop end

// If we ever fall through:
jexit(array('ok'=>true,'dry'=>$dry,'inserted'=>$inserted,'items'=>$items));