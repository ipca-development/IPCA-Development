<?php
/* =========================================================
   progress_api.php — PHP 5.3 compatible (mysqli)
   Rules:
   - If latest attempt is incomplete or RED → first “next” is same scenario (retake),
     then continue with the following scenarios to reach 3 “next” total.
   - If latest is completed → show next 3 scenarios after it.
   - Next scenarios show true status using latest attempt (if any) or schedule.
   - Strip window shows anchor + the 3 "next" (8 pills total window).
   ========================================================= */

/* --------- CONFIG --------- */
$DB_HOST = 'mysql056.hosting.combell.com';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$TZ      = 'America/Los_Angeles';
date_default_timezone_set($TZ);

/* --------- Helpers --------- */
function jexit($arr){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr);
  exit;
}
function jerr($msg, $extra){
  $out = array('ok'=>false, 'error'=>$msg);
  if (!empty($_GET['debug'])) $out['debug'] = $extra;
  jexit($out);
}
function safe_int($v){ return (int)preg_replace('/[^0-9]/','', (string)$v); }
function fetch_all_assoc($rs){
  $rows = array();
  if ($rs){
    while ($row = mysqli_fetch_assoc($rs)) $rows[] = $row;
    mysqli_free_result($rs);
  }
  return $rows;
}
function fetch_one_assoc($rs){
  if (!$rs) return null;
  $row = mysqli_fetch_assoc($rs);
  mysqli_free_result($rs);
  return $row ? $row : null;
}
function table_exists($mysqli, $db_name, $table){
  $q = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = '".mysqli_real_escape_string($mysqli,$db_name)."'
          AND TABLE_NAME   = '".mysqli_real_escape_string($mysqli,$table)."'
        LIMIT 1";
  $rs = mysqli_query($mysqli, $q);
  if ($rs && mysqli_num_rows($rs) > 0){ mysqli_free_result($rs); return true; }
  if ($rs) mysqli_free_result($rs);
  return false;
}
function normalize_sc_type($raw){
  $t = strtoupper(trim((string)$raw));
  if ($t === 'LB')     return 'BRIEFING';
  if ($t === 'FNPT')   return 'SIMULATOR';
  if ($t === 'SAB')    return 'SIMULATOR';
  if ($t === 'FLIGHT') return 'FLIGHT';
  return '';
}
function is_incomplete($grading_raw){
  $g = strtoupper(trim((string)$grading_raw));
  return ($g !== '' && substr($g, -1) === 'I');
}
function is_red($grading_raw){
  $g = strtoupper(trim((string)$grading_raw));
  return ($g !== '' && $g[0] === 'R');
}
function must_repeat($grading_raw){
  return is_incomplete($grading_raw) || is_red($grading_raw);
}

/* --------- Connect --------- */
$mysqli = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$mysqli) jerr('DB connect failed', array('mysqli_connect_error'=>mysqli_connect_error()));
@mysqli_set_charset($mysqli, 'utf8');

/* --------- Input --------- */
$student_id = isset($_GET['student_id']) ? safe_int($_GET['student_id']) : 0;
if ($student_id <= 0) jexit(array('ok'=>false, 'error'=>'Missing or invalid student_id'));

/* --------- Programs for student --------- */
$sql = "SELECT p.pr_id, p.pr_name, p.pr_db, p.pr_location, p.pr_active
        FROM programs_users pu
        INNER JOIN programs p ON p.pr_id = pu.pu_program
        WHERE pu.pu_user = ".(int)$student_id."
        ORDER BY pu.pu_start DESC, pu.pu_id DESC";
$rs = mysqli_query($mysqli, $sql);
if (!$rs) jerr('Query programs failed', array('sql'=>$sql,'mysqli_error'=>mysqli_error($mysqli)));
$programs = fetch_all_assoc($rs);
if (!count($programs)) jexit(array('ok'=>true, 'progress'=>array()));

/* --------- Curriculum helpers --------- */
function get_first_of_type($mysqli, $program_id, $bucket){
  if ($bucket === 'BRIEFING')      $cond = "UPPER(sc_type)='LB'";
  elseif ($bucket === 'SIMULATOR') $cond = "UPPER(sc_type) IN ('FNPT','SAB')";
  else                             $cond = "UPPER(sc_type)='FLIGHT'";
  $q = "SELECT sc_id, sc_code, sc_name, sc_order, sc_type, sc_stage, sc_phase
        FROM scenarios
        WHERE sc_program = ".(int)$program_id." AND $cond
        ORDER BY sc_stage ASC, sc_phase ASC, sc_order ASC
        LIMIT 1";
  $rs = mysqli_query($mysqli, $q);
  if (!$rs) return array('error'=>mysqli_error($mysqli),'sql'=>$q);
  return fetch_one_assoc($rs);
}
function get_next_after_bucket($mysqli, $program_id, $bucket, $after_order, $after_stage, $after_phase){
  if ($bucket === 'BRIEFING')      $cond = "UPPER(sc_type)='LB'";
  elseif ($bucket === 'SIMULATOR') $cond = "UPPER(sc_type) IN ('FNPT','SAB')";
  else                             $cond = "UPPER(sc_type)='FLIGHT'";
  $q = "SELECT sc_id, sc_code, sc_name, sc_order, sc_type, sc_stage, sc_phase
        FROM scenarios
        WHERE sc_program = ".(int)$program_id." AND $cond
          AND (sc_stage > ".(int)$after_stage."
               OR (sc_stage = ".(int)$after_stage." AND sc_phase > ".(int)$after_phase.")
               OR (sc_stage = ".(int)$after_stage." AND sc_phase = ".(int)$after_phase." AND sc_order > ".(int)$after_order."))
        ORDER BY sc_stage ASC, sc_phase ASC, sc_order ASC
        LIMIT 1";
  $rs = mysqli_query($mysqli, $q);
  if (!$rs) return array('error'=>mysqli_error($mysqli),'sql'=>$q);
  return fetch_one_assoc($rs);
}
function get_next_N_overall($mysqli, $program_id, $after_stage, $after_phase, $after_order, $limit){
  $q = "SELECT sc_id, sc_code, sc_name, sc_type, sc_stage, sc_phase, sc_order
        FROM scenarios
        WHERE sc_program = ".(int)$program_id."
          AND (sc_stage > ".(int)$after_stage."
               OR (sc_stage = ".(int)$after_stage." AND sc_phase > ".(int)$after_phase.")
               OR (sc_stage = ".(int)$after_stage." AND sc_phase = ".(int)$after_phase." AND sc_order > ".(int)$after_order."))
        ORDER BY sc_stage ASC, sc_phase ASC, sc_order ASC
        LIMIT ".(int)$limit;
  $rs = mysqli_query($mysqli, $q);
  if (!$rs) return array('error'=>mysqli_error($mysqli),'sql'=>$q);
  return fetch_all_assoc($rs);
}

/* --------- Build output --------- */
$out = array();
$debug = !empty($_GET['debug']);

for ($i=0; $i<count($programs); $i++){
  $pr  = $programs[$i];
  $pid = (int)$pr['pr_id'];

  /* normalize pr_db → tracking table */
  $rawDb = (string)$pr['pr_db'];
  $clean = preg_replace('/[^A-Za-z0-9_]/','', $rawDb);
  if ($clean === '') $track = '';
  else $track = preg_match('/^scenario_tracking_/i',$clean) ? $clean : ('scenario_tracking_'.$clean);

  $entry = array(
    'program_id'   => $pid,
    'program_name' => (string)$pr['pr_name'],
    'program_db'   => (string)$pr['pr_db'],
    'program_location' => (string)$pr['pr_location'],
    'table'        => $track,
    'exists'       => ($track !== '' && table_exists($mysqli, $DB_NAME, $track)) ? true : false,
    'last'         => new stdClass(),
    'next'         => new stdClass()
  );
  if (!$entry['exists']){ $out[] = $entry; continue; }

  /* ---------- LAST per bucket ---------- */
  $qLast = "SELECT t.sctr_id, t.sctr_scenario_id, t.sctr_date, t.sctr_grading,
                   s.sc_id, s.sc_code, s.sc_name, s.sc_order, s.sc_type, s.sc_stage, s.sc_phase
            FROM `".$track."` t
            INNER JOIN scenarios s ON s.sc_id = t.sctr_scenario_id AND s.sc_program = ".(int)$pid."
            WHERE t.sctr_student = ".(int)$student_id."
            ORDER BY t.sctr_date DESC, t.sctr_id DESC";
  $rs = mysqli_query($mysqli, $qLast);
  if (!$rs){
    if ($debug) $entry['debug_error_last'] = array('sql'=>$qLast,'err'=>mysqli_error($mysqli));
    $out[] = $entry; continue;
  }
  $lastBuckets = array('BRIEFING'=>null,'SIMULATOR'=>null,'FLIGHT'=>null);
  $latestAttemptAny = null; // latest attempt overall (for retake)
  while ($row = mysqli_fetch_assoc($rs)){
    if (!$latestAttemptAny) $latestAttemptAny = $row;
    $b = normalize_sc_type($row['sc_type']);
    if ($b && !$lastBuckets[$b]){
      $lastBuckets[$b] = array(
        'code'=>(string)$row['sc_code'],
        'name'=>(string)$row['sc_name'],
        'date'=>(string)$row['sctr_date'],
        'grading'=>(string)$row['sctr_grading'],
        'order'=>(int)$row['sc_order'],
        'stage'=>(int)$row['sc_stage'],
        'phase'=>(int)$row['sc_phase'],
        'sc_id'=>(int)$row['sc_id']
      );
    }
  }
  mysqli_free_result($rs);

  /* Fill last/next (same as before) */
  $types = array('BRIEFING','SIMULATOR','FLIGHT');
  $entry['last'] = array();
  $entry['next'] = array();
  foreach ($types as $T){
    $L = isset($lastBuckets[$T]) ? $lastBuckets[$T] : null;
    $entry['last'][$T] = $L ? array('code'=>$L['code'],'name'=>$L['name'],'date'=>$L['date'],'grading'=>$L['grading']) : null;
    $nextPayload = null;
    if ($L){
      if (must_repeat($L['grading'])){
        $nextPayload = array('code'=>$L['code'],'name'=>$L['name'],'rule'=>'repeat');
      } else {
        $nx = get_next_after_bucket($mysqli, $pid, $T, (int)$L['order'], (int)$L['stage'], (int)$L['phase']);
        if (is_array($nx) && isset($nx['error'])) $nx = null;
        if ($nx) $nextPayload = array('code'=>$nx['sc_code'],'name'=>$nx['sc_name'],'rule'=>'proceed');
        else     $nextPayload = array('code'=>'','name'=>'(end of sequence)','rule'=>'none');
      }
    } else {
      $first = get_first_of_type($mysqli, $pid, $T);
      if (is_array($first) && isset($first['error'])) $first = null;
      if ($first) $nextPayload = array('code'=>$first['sc_code'],'name'=>$first['sc_name'],'rule'=>'start');
    }
    $entry['next'][$T] = $nextPayload;
  }

  /* ---------- All attempts (ASC) + latest maps ---------- */
  $qTL = "SELECT t.sctr_id, t.sctr_scenario_id, t.sctr_student, t.sctr_instructor,
                 t.sctr_date, t.sctr_grading,
                 s.sc_id, s.sc_code, s.sc_name, s.sc_type,
                 s.sc_stage, s.sc_phase, s.sc_order,
                 u.voornaam AS inst_first, u.naam AS inst_last
          FROM `".$track."` t
          INNER JOIN scenarios s ON s.sc_id = t.sctr_scenario_id AND s.sc_program = ".(int)$pid."
          LEFT JOIN users u ON u.userid = t.sctr_instructor
          WHERE t.sctr_student = ".(int)$student_id."
          ORDER BY t.sctr_date ASC, t.sctr_id ASC";
  $attempts = fetch_all_assoc(mysqli_query($mysqli, $qTL));

  $items = array();
  $latestByScenario = array(); // sc_id => latest attempt row
  $lastCompletedIdx = -1;
  $lastCompletedStage = 0; $lastCompletedPhase = 0; $lastCompletedOrder = 0;

  for ($k=0; $k<count($attempts); $k++){
    $r = $attempts[$k];
    $sid = (int)$r['sc_id'];
    $nmType = normalize_sc_type($r['sc_type']);
    $instName = trim(($r['inst_first']!=''?$r['inst_first']:'').' '.($r['inst_last']!=''?$r['inst_last']:''));
    $status = is_incomplete($r['sctr_grading']) || is_red($r['sctr_grading']) ? 'incomplete' : 'completed';

    $items[] = array(
      'sc_id'          => $sid,
      'code'           => (string)$r['sc_code'],
      'name'           => (string)$r['sc_name'],
      'type'           => $nmType,
      'status'         => $status,
      'date'           => (string)$r['sctr_date'],
      'grading'        => (string)$r['sctr_grading'],
      'instructor_id'  => (int)$r['sctr_instructor'],
      'instructor_name'=> $instName,
      'stage'          => (int)$r['sc_stage'],
      'phase'          => (int)$r['sc_phase'],
      'order'          => (int)$r['sc_order']
    );

    $latestByScenario[$sid] = $r;

    if ($status === 'completed'){
      $lastCompletedIdx   = count($items) - 1;
      $lastCompletedStage = (int)$r['sc_stage'];
      $lastCompletedPhase = (int)$r['sc_phase'];
      $lastCompletedOrder = (int)$r['sc_order'];
    }
  }

  /* scheduled future reservations by scenario (soonest) */
  $qSched = "SELECT r.res_id, r.start_dt, r.status, r.scenario_id
             FROM reservations r
             INNER JOIN reservation_students rs ON rs.res_id = r.res_id
             WHERE rs.student_user_id = ".(int)$student_id."
               AND r.scenario_id IS NOT NULL
               AND r.status IN ('scheduled','checked-in','in-progress')
             ORDER BY r.start_dt ASC, r.res_id ASC";
  $scheduled = fetch_all_assoc(mysqli_query($mysqli, $qSched));
  $schedByScenario = array();
  for ($s=0; $s<count($scheduled); $s++){
    $sid = (int)$scheduled[$s]['scenario_id'];
    $dt  = substr((string)$scheduled[$s]['start_dt'],0,10);
    if (!isset($schedByScenario[$sid])) $schedByScenario[$sid] = $dt;
  }

  /* ---------- NEXT THREE construction (with retake if needed) ---------- */
  $nextDesired = 3;
  $appendedFrom = count($items); // index where we start appending “next” items

  // Determine anchor: if latest overall requires repeat → anchor = latest attempt scenario;
  // else anchor = last completed scenario.
  $needRepeat = false;
  $anchorStage = $lastCompletedStage;
  $anchorPhase = $lastCompletedPhase;
  $anchorOrder = $lastCompletedOrder;

  if ($latestAttemptAny && must_repeat($latestAttemptAny['sctr_grading'])){
    $needRepeat = true;
    // Use latest attempt's scenario coords as anchor
    $anchorStage = (int)$latestAttemptAny['sc_stage'];
    $anchorPhase = (int)$latestAttemptAny['sc_phase'];
    $anchorOrder = (int)$latestAttemptAny['sc_order'];

    // 1) add the same scenario as a repeat (first "next")
    $nmType = normalize_sc_type($latestAttemptAny['sc_type']);
    $instNm = '';
    if (!empty($latestAttemptAny['inst_first']) || !empty($latestAttemptAny['inst_last'])){
      $instNm = trim(($latestAttemptAny['inst_first']?$latestAttemptAny['inst_first']:'').' '.($latestAttemptAny['inst_last']?$latestAttemptAny['inst_last']:''));
    }
    $items[] = array(
      'sc_id'          => (int)$latestAttemptAny['sc_id'],
      'code'           => (string)$latestAttemptAny['sc_code'],
      'name'           => (string)$latestAttemptAny['sc_name'],
      'type'           => $nmType,
      'status'         => 'repeat', // your UI treats as repeat (styled same base color)
      'date'           => (string)$latestAttemptAny['sctr_date'],
      'grading'        => (string)$latestAttemptAny['sctr_grading'],
      'instructor_id'  => (int)$latestAttemptAny['sctr_instructor'],
      'instructor_name'=> $instNm,
      'stage'          => $anchorStage,
      'phase'          => $anchorPhase,
      'order'          => $anchorOrder
    );
    $nextDesired--; // one of the three consumed by the retake
  }

  // 2) add the following scenarios in curriculum order to reach total of 3 "next"
  if ($nextDesired > 0){
    $nextList = get_next_N_overall($mysqli, $pid, $anchorStage, $anchorPhase, $anchorOrder, $nextDesired);
    if (!is_array($nextList) || (isset($nextList['error']) && $nextList['error'])) $nextList = array();

    for ($n=0; $n<count($nextList); $n++){
      $sc  = $nextList[$n];
      $sid = (int)$sc['sc_id'];
      $nmType = normalize_sc_type($sc['sc_type']);

      // resolve status (latest attempt or scheduled or upcoming)
      $latest = isset($latestByScenario[$sid]) ? $latestByScenario[$sid] : null;
      $status = 'upcoming';
      $date   = '';
      $grading= '';
      $instId = 0;
      $instNm = '';

      if ($latest){
        $grading = (string)$latest['sctr_grading'];
        $date    = (string)$latest['sctr_date'];
        $instId  = (int)$latest['sctr_instructor'];
        $instNm  = trim(($latest['inst_first']!=''?$latest['inst_first']:'').' '.($latest['inst_last']!=''?$latest['inst_last']:''));
        $status  = (is_incomplete($grading) || is_red($grading)) ? 'incomplete' : 'completed';
      } elseif (isset($schedByScenario[$sid])) {
        $status = 'scheduled';
        $date   = (string)$schedByScenario[$sid];
      }

      $items[] = array(
        'sc_id'          => $sid,
        'code'           => (string)$sc['sc_code'],
        'name'           => (string)$sc['sc_name'],
        'type'           => $nmType,
        'status'         => $status,
        'date'           => $date,
        'grading'        => $grading,
        'instructor_id'  => $instId,
        'instructor_name'=> $instNm,
        'stage'          => (int)$sc['sc_stage'],
        'phase'          => (int)$sc['sc_phase'],
        'order'          => (int)$sc['sc_order']
      );
    }
  }

  /* ---------- 8-pill window:
       ensure all appended “next” items are visible along with the anchor
     ----------------------------------------- */
  $total = count($items);
  $start = 0; $end = ($total>0)?($total-1):0;

  // The first appended index (retake or first next after anchor)
  $firstNextIdx = ($appendedFrom < $total) ? $appendedFrom : -1;

  if ($firstNextIdx >= 0){
    // show from up to 7 items before the first next, so total 8
    $end   = min($total-1, $firstNextIdx + 2); // guarantee up to first next + 2 (so up to 3 next are fully visible)
    if ($end < $firstNextIdx + 2) $end = min($total-1, $firstNextIdx + 2);
    $start = max(0, $end - 7);
  } else {
    // no nexts appended (rare) → fall back to last items
    $start = max(0, $total - 8);
    $end   = $total - 1;
  }

  $entry['strip'] = array(
    'latest' => array(
      'index'   => ($lastCompletedIdx>=0 ? $lastCompletedIdx : 0),
      'anchor'  => $needRepeat ? 'latest_incomplete_or_red' : 'last_completed'
    ),
    'items'  => $items,
    'window' => array('start_index'=>$start,'end_index'=>$end)
  );

  $out[] = $entry;
}

/* --------- Done --------- */
jexit(array('ok'=>true, 'progress'=>$out));