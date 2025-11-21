<?php
/* =========================================================
   progress_api.php — PHP 5.3 compatible
   ---------------------------------------------------------
   Input:  GET student_id (required), debug=1 (optional)
   Output: JSON with:
     ok, progress: [
       {
         program_id, program_name, program_db, program_location,
         table, exists, last: {BRIEFING,SIMULATOR,FLIGHT}, next: {...},
         strip: {
           latest: { index, sc_id, date, grading, instructor_id, instructor_name },
           items: [ { sc_id, code, name, type, status, date, grading, instructor_id, instructor_name, scheduled_on } x up to 10 ],
           window: { start_index, end_index }
         }
       }, ...
     ]
   ---------------------------------------------------------
   Status values in strip.items:
     - "completed"     → has tracking entry
     - "incomplete"    → last attempt of that scenario incomplete (…I) or red (R…)
     - "repeat"        → explicit repeat after an incomplete latest attempt (next pill)
     - "scheduled"     → reservation exists with scenario_id for this student, not completed/canceled
     - "upcoming"      → not scheduled yet, future in sequence
========================================================= */

error_reporting(E_ALL);
ini_set('display_errors', 0); // set to 1 while debugging

/* --------- CONFIG --------- */
$DB_HOST = 'mysql056.hosting.combell.com';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$TZ      = 'America/Los_Angeles';
date_default_timezone_set($TZ);

/* --------- Small helpers --------- */
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
  $out = array(); if (!$rs) return $out;
  while ($row = mysqli_fetch_assoc($rs)) $out[] = $row;
  mysqli_free_result($rs);
  return $out;
}
function fetch_one_assoc($rs){
  if (!$rs) return null;
  $row = mysqli_fetch_assoc($rs);
  mysqli_free_result($rs);
  return $row ? $row : null;
}
function table_exists($mysqli, $table){
  $safe = mysqli_real_escape_string($mysqli, $table);
  $rs = mysqli_query($mysqli, "SHOW TABLES LIKE '".$safe."'");
  if ($rs && mysqli_num_rows($rs) > 0){ mysqli_free_result($rs); return true; }
  if ($rs) mysqli_free_result($rs);
  return false;
}
function normalize_sc_type($raw){
  $t = strtoupper(trim((string)$raw));
  if ($t === 'LB')     return 'BRIEFING';
  if ($t === 'FNPT')   return 'SIMULATOR';
  if ($t === 'FLIGHT') return 'FLIGHT';
  if ($t === 'SAB')    return 'SAB';
  return '';
}
function must_repeat($grading_raw){
  $g = strtoupper(trim((string)$grading_raw));
  if ($g !== '' && substr($g, -1) === 'I') return true; // incomplete
  if ($g !== '' && $g[0] === 'R') return true;          // red
  return false;
}

/* --------- Connect --------- */
$mysqli = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$mysqli) jerr('DB connect failed', array('mysqli_connect_error'=>mysqli_connect_error()));
@mysqli_set_charset($mysqli, 'utf8');

/* --------- Input --------- */
$student_id = isset($_GET['student_id']) ? safe_int($_GET['student_id']) : 0;
if ($student_id <= 0) jexit(array('ok'=>false, 'error'=>'Missing or invalid student_id'));
$DEBUG = !empty($_GET['debug']);

/* =========================================================
   1) Find active programs for the student (order by newest enrollment)
========================================================= */
$sql = "SELECT p.pr_id, p.pr_name, p.pr_db, p.pr_active, p.pr_location
        FROM programs_users pu
        INNER JOIN programs p ON p.pr_id = pu.pu_program
        WHERE pu.pu_user = ".(int)$student_id." AND UPPER(p.pr_active)='YES'
        ORDER BY pu.pu_start DESC, pu.pu_id DESC";
$rs = mysqli_query($mysqli, $sql);
if (!$rs) jerr('Query programs failed', array('sql'=>$sql, 'mysqli_error'=>mysqli_error($mysqli)));
$programs = fetch_all_assoc($rs);
if (!count($programs)) jexit(array('ok'=>true, 'progress'=>array()));

/* =========================================================
   Top-level helpers (no closures; PHP 5.3 safe)
========================================================= */
function build_tracking_table_name($raw_pr_db){
  $raw = preg_replace('/[^A-Za-z0-9_]/', '', (string)$raw_pr_db);
  if ($raw === '') return '';
  if (preg_match('/^scenario_tracking_/i', $raw)) return $raw;
  return 'scenario_tracking_' . $raw;
}
function map_instructor_names($mysqli, $user_ids){
  $out = array();
  if (!count($user_ids)) return $out;
  $ids = array();
  for ($i=0;$i<count($user_ids);$i++){
    $ids[] = (int)$user_ids[$i];
  }
  $sql = "SELECT userid, voornaam, naam FROM users WHERE userid IN (".implode(',', $ids).")";
  $rs = mysqli_query($mysqli, $sql);
  if ($rs){
    while ($r = mysqli_fetch_assoc($rs)){
      $nm = trim(($r['voornaam'] ? $r['voornaam'] : '').' '.($r['naam'] ? $r['naam'] : ''));
      $out[(int)$r['userid']] = $nm;
    }
    mysqli_free_result($rs);
  }
  return $out;
}
function type_for_scenario_row($row){
  // row has sc_type (LB/FNPT/FLIGHT/SAB)
  return normalize_sc_type(isset($row['sc_type']) ? $row['sc_type'] : '');
}
/* Last per bucket from tracking rows already joined to scenarios. */
function compute_last_per_bucket($joined_rows){
  $buckets = array('BRIEFING'=>null,'SIMULATOR'=>null,'FLIGHT'=>null);
  for ($i=0;$i<count($joined_rows);$i++){
    $t = type_for_scenario_row($joined_rows[$i]);
    if ($t && $buckets[$t] === null){
      $buckets[$t] = array(
        'code'    => (string)$joined_rows[$i]['sc_code'],
        'name'    => (string)$joined_rows[$i]['sc_name'],
        'date'    => (string)$joined_rows[$i]['sctr_date'],
        'grading' => (string)$joined_rows[$i]['sctr_grading']
      );
      if ($buckets['BRIEFING']!==null && $buckets['SIMULATOR']!==null && $buckets['FLIGHT']!==null) break;
    }
  }
  return $buckets;
}

/* =========================================================
   Iterate programs
========================================================= */
$out = array();

for ($p=0; $p<count($programs); $p++){
  $pr   = $programs[$p];
  $pid  = (int)$pr['pr_id'];
  $pdb  = (string)$pr['pr_db'];
  $pname= (string)$pr['pr_name'];
  $ploc = (string)$pr['pr_location'];

  /* 1) Build canonical ordered scenario list for this program
        Program → Stage (st_order) → Phase (ph_order) → Scenario (sc_order)
  */
  $qSc = "SELECT s.sc_id, s.sc_code, s.sc_name, s.sc_type, s.sc_order,
                 s.sc_stage, s.sc_phase,
                 st.st_order, ph.ph_order
          FROM scenarios s
          JOIN stages st ON st.st_id = s.sc_stage AND st.st_program = ".(int)$pid."
          JOIN phases ph ON ph.ph_id = s.sc_phase AND ph.ph_stage   = st.st_id
          WHERE s.sc_program = ".(int)$pid."
          ORDER BY st.st_order ASC, ph.ph_order ASC, s.sc_order ASC, s.sc_id ASC";
  $rsSc = mysqli_query($mysqli, $qSc);
  if (!$rsSc){
    $out[] = array(
      'program_id'=>$pid,'program_name'=>$pname,'program_db'=>$pdb,'program_location'=>$ploc,
      'table'=>'','exists'=>false,
      'last'=>new stdClass(),'next'=>new stdClass(),
      'error'=>'Failed to query scenarios'
    );
    continue;
  }
  $timeline = fetch_all_assoc($rsSc); // ordered scenarios
  if (!count($timeline)){
    $out[] = array(
      'program_id'=>$pid,'program_name'=>$pname,'program_db'=>$pdb,'program_location'=>$ploc,
      'table'=>'','exists'=>false,
      'last'=>new stdClass(),'next'=>new stdClass(),
      'strip'=>array('items'=>array(),'latest'=>null,'window'=>new stdClass())
    );
    continue;
  }

  // quick index: sc_id -> index in timeline
  $idx_by_id = array();
  for ($i=0;$i<count($timeline);$i++){
    $idx_by_id[(int)$timeline[$i]['sc_id']] = $i;
  }

  /* 2) Tracking table for this program */
  $track_table = build_tracking_table_name($pdb);
  $exists = ($track_table !== '' && table_exists($mysqli, $track_table)) ? true : false;

  // Pre-fill response entry
  $entry = array(
    'program_id'      => $pid,
    'program_name'    => $pname,
    'program_db'      => $pdb,
    'program_location'=> $ploc,
    'table'           => $track_table,
    'exists'          => $exists ? true : false,
    'last'            => new stdClass(),
    'next'            => new stdClass()
  );

  // Maps for quick lookups
  $latest_overall = null; // latest tracking row overall for student in this program
  $by_scenario_latest = array(); // sc_id => latest tracking row

  // Instructors encountered to map names later
  $instructor_ids = array();

  if ($exists){
    // Join tracking → scenarios to keep program-scoped
    $qTr = "SELECT t.sctr_id, t.sctr_scenario_id, t.sctr_type, t.sctr_student, t.sctr_instructor,
                   t.sctr_date, t.sctr_grading,
                   s.sc_id, s.sc_code, s.sc_name, s.sc_type, s.sc_order
            FROM `".$track_table."` t
            JOIN scenarios s ON s.sc_id = t.sctr_scenario_id
            WHERE t.sctr_student = ".(int)$student_id." AND s.sc_program = ".(int)$pid."
            ORDER BY t.sctr_date DESC, t.sctr_id DESC";
    $rsTr = mysqli_query($mysqli, $qTr);
    if ($rsTr){
      $rows = fetch_all_assoc($rsTr);

      // latest overall
      if (count($rows)) $latest_overall = $rows[0];

      // last per bucket for "lanes" compatibility
      $last_buckets = compute_last_per_bucket($rows);
      $entry['last'] = array(
        'BRIEFING'  => $last_buckets['BRIEFING'],
        'SIMULATOR' => $last_buckets['SIMULATOR'],
        'FLIGHT'    => $last_buckets['FLIGHT']
      );

      // latest per scenario (for strip statuses)
      for ($i=0;$i<count($rows);$i++){
        $scid = (int)$rows[$i]['sctr_scenario_id'];
        if (!isset($by_scenario_latest[$scid])){
          $by_scenario_latest[$scid] = $rows[$i];
        }
        if (!empty($rows[$i]['sctr_instructor'])){
          $iid = (int)$rows[$i]['sctr_instructor'];
          if (!in_array($iid, $instructor_ids, true)) $instructor_ids[] = $iid;
        }
      }

      // Compute "next" per bucket
      $types = array('BRIEFING','SIMULATOR','FLIGHT');
      $next_map = array('BRIEFING'=>null,'SIMULATOR'=>null,'FLIGHT'=>null);
      // Build a fast list of first scenario per type for start suggestion
      $first_per_type = array('BRIEFING'=>null,'SIMULATOR'=>null,'FLIGHT'=>null);
      for ($i=0;$i<count($timeline);$i++){
        $tt = normalize_sc_type($timeline[$i]['sc_type']);
        if ($tt && $first_per_type[$tt] === null) $first_per_type[$tt] = $timeline[$i];
      }

      for ($ti=0;$ti<count($types);$ti++){
        $T = $types[$ti];
        $last = $last_buckets[$T];
        if ($last && isset($idx_by_id)){
          // find index of last code in ordered timeline (by code OR id)
          $last_index = null;
          // Try by code first (codes are unique per program)
          if (!empty($last['code'])){
            for ($i=0;$i<count($timeline);$i++){
              if ($timeline[$i]['sc_code'] === $last['code']){
                $last_index = $i; break;
              }
            }
          }
          // fallback by matching name + type
          if ($last_index === null){
            for ($i=0;$i<count($timeline);$i++){
              if ($timeline[$i]['sc_name'] === $last['name'] &&
                  normalize_sc_type($timeline[$i]['sc_type']) === $T){
                $last_index = $i; break;
              }
            }
          }
          // Decide next
          if ($last_index !== null){
            if (must_repeat($last['grading'])){
              $next_map[$T] = array('code'=>$last['code'],'name'=>$last['name'],'rule'=>'repeat');
            } else {
              // next same-type after that index
              $nx = null;
              for ($j=$last_index+1; $j<count($timeline); $j++){
                if (normalize_sc_type($timeline[$j]['sc_type']) === $T){
                  $nx = $timeline[$j]; break;
                }
              }
              if ($nx){
                $next_map[$T] = array('code'=>$nx['sc_code'],'name'=>$nx['sc_name'],'rule'=>'proceed');
              } else {
                $next_map[$T] = array('code'=>'','name'=>'(end of sequence)','rule'=>'none');
              }
            }
          }
        } else {
          // No history → suggest first of that type
          $f = $first_per_type[$T];
          if ($f){
            $next_map[$T] = array('code'=>$f['sc_code'],'name'=>$f['sc_name'],'rule'=>'start');
          } else {
            $next_map[$T] = null;
          }
        }
      }
      $entry['next'] = $next_map;
    }
  }

  /* 3) Scheduled-but-not-completed from reservations (by scenario_id) */
  $scheduled_map = array(); // sc_id => date (start_dt)
  $qSch = "SELECT r.scenario_id AS sc_id, MIN(r.start_dt) AS min_start
           FROM reservations r
           JOIN reservation_students rs ON rs.res_id = r.res_id
           WHERE rs.student_user_id = ".(int)$student_id."
             AND r.scenario_id IS NOT NULL
             AND r.status NOT IN ('canceled','no-show','completed')
           GROUP BY r.scenario_id";
  $rsSch = mysqli_query($mysqli, $qSch);
  if ($rsSch){
    while ($s = mysqli_fetch_assoc($rsSch)){
      $sid = (int)$s['sc_id'];
      if ($sid > 0) $scheduled_map[$sid] = $s['min_start'];
    }
    mysqli_free_result($rsSch);
  }

  /* Also collect instructors from reservations, for name map if needed */
  $qSchI = "SELECT DISTINCT r.instructor_user_id AS iid
            FROM reservations r
            JOIN reservation_students rs ON rs.res_id = r.res_id
            WHERE rs.student_user_id = ".(int)$student_id."
              AND r.instructor_user_id IS NOT NULL";
  $rsSchI = mysqli_query($mysqli, $qSchI);
  if ($rsSchI){
    while ($s = mysqli_fetch_assoc($rsSchI)){
      $iid = (int)$s['iid'];
      if ($iid > 0 && !in_array($iid, $instructor_ids, true)) $instructor_ids[] = $iid;
    }
    mysqli_free_result($rsSchI);
  }

  /* Map instructor names */
  $instr_names = map_instructor_names($mysqli, $instructor_ids);

  /* 4) Build the 10-pill scenario strip */
  $strip = array(
    'latest' => null,
    'items'  => array(),
    'window' => array()
  );

  // Determine "latest attempt" (overall) within this program
  $center_index = null; // index in timeline for pill #5
  $latest_center_row = null;
  if ($latest_overall && isset($idx_by_id[(int)$latest_overall['sctr_scenario_id']])){
    $center_index = (int)$idx_by_id[(int)$latest_overall['sctr_scenario_id']];
    $latest_center_row = $latest_overall;
  } else {
    // no tracking yet → center on the very first scenario in program
    $center_index = 0;
  }

  // Window: 10 pills total → indices [center-4 ... center+5]
  $start_idx = $center_index - 4;
  $end_idx   = $center_index + 5;
  if ($start_idx < 0){ $end_idx += -$start_idx; $start_idx = 0; }
  if ($end_idx >= count($timeline)) $end_idx = count($timeline)-1;
  // Ensure up to 10 items if possible
  $count = $end_idx - $start_idx + 1;
  if ($count < 10 && count($timeline) >= 10){
    $missing = 10 - $count;
    $start_idx = max(0, $start_idx - $missing);
    $count = $end_idx - $start_idx + 1;
    if ($count < 10){
      $more = 10 - $count;
      $end_idx = min(count($timeline)-1, $end_idx + $more);
    }
  }

  // Assemble items
  for ($i=$start_idx; $i<=$end_idx; $i++){
    $row  = $timeline[$i];
    $sid  = (int)$row['sc_id'];
    $type = normalize_sc_type($row['sc_type']);

    $status = 'upcoming';
    $date   = null;
    $grade  = null;
    $iid    = null;
    $iname  = null;

    if (isset($by_scenario_latest[$sid])){
      $lr = $by_scenario_latest[$sid];
      $date = $lr['sctr_date'];
      $grade= $lr['sctr_grading'];
      $iid  = (int)$lr['sctr_instructor'];
      $iname= isset($instr_names[$iid]) ? $instr_names[$iid] : null;
      $status = must_repeat($grade) ? 'incomplete' : 'completed';
    } elseif (isset($scheduled_map[$sid])) {
      $status = 'scheduled';
    }

    $item = array(
      'sc_id'  => $sid,
      'code'   => (string)$row['sc_code'],
      'name'   => (string)$row['sc_name'],
      'type'   => (string)$type,
      'status' => (string)$status
    );
    if ($date !== null) $item['date'] = $date;
    if ($grade !== null) $item['grading'] = $grade;
    if ($iid) $item['instructor_id'] = $iid;
    if ($iname !== null) $item['instructor_name'] = $iname;
    if ($status === 'scheduled' && isset($scheduled_map[$sid])) $item['scheduled_on'] = $scheduled_map[$sid];

    $strip['items'][] = $item;
  }

  // Pill #5 repeat rule when latest attempt is incomplete/red
  if ($latest_center_row){
    $need_repeat = must_repeat($latest_center_row['sctr_grading']);
    if ($need_repeat){
      // repeat is the immediate next pill if it exists in the window and refers to same sc_id
      // If not present, append a synthetic "repeat" pill (bounded to 10 by trimming oldest)
      $center_sid = (int)$latest_center_row['sctr_scenario_id'];
      $has_repeat_in_window = false;
      for ($k=0;$k<count($strip['items']);$k++){
        if ($strip['items'][$k]['sc_id'] === $center_sid && $strip['items'][$k]['status'] !== 'incomplete'){
          // Already have another same scenario ahead (unlikely). Mark the next one explicitly as repeat
          $strip['items'][$k]['status'] = 'repeat';
          $has_repeat_in_window = true;
          break;
        }
      }
      if (!$has_repeat_in_window){
        // Inject a repeat pill after center (if timeline allows)
        $repeat_item = array(
          'sc_id'  => $center_sid,
          'code'   => (string)$latest_center_row['sc_code'],
          'name'   => (string)$latest_center_row['sc_name'],
          'type'   => (string)normalize_sc_type($latest_center_row['sc_type']),
          'status' => 'repeat',
          'grading'=> (string)$latest_center_row['sctr_grading'],
          'date'   => (string)$latest_center_row['sctr_date']
        );
        if (!empty($latest_center_row['sctr_instructor'])){
          $iidr = (int)$latest_center_row['sctr_instructor'];
          $repeat_item['instructor_id'] = $iidr;
          if (isset($instr_names[$iidr])) $repeat_item['instructor_name'] = $instr_names[$iidr];
        }

        // put it just after current center within window
        $insert_at = 5; // 0-based #5 means sixth element; but our items start from start_idx
        if (count($strip['items']) >= 10){
          // keep 10 by removing first if needed
          array_shift($strip['items']);
        }
        array_splice($strip['items'], $insert_at, 0, array($repeat_item));
      }
    }
  }

  // Trim to max 10 items
  while (count($strip['items']) > 10) array_pop($strip['items']);

  // latest metadata for strip
  $strip['latest'] = $latest_center_row ? array(
    'index'           => $center_index,
    'sc_id'           => (int)$latest_center_row['sctr_scenario_id'],
    'date'            => (string)$latest_center_row['sctr_date'],
    'grading'         => (string)$latest_center_row['sctr_grading'],
    'instructor_id'   => (int)$latest_center_row['sctr_instructor'],
    'instructor_name' => isset($instr_names[(int)$latest_center_row['sctr_instructor']]) ? $instr_names[(int)$latest_center_row['sctr_instructor']] : null
  ) : null;

  $strip['window'] = array('start_index'=>$start_idx, 'end_index'=>$end_idx);
  $entry['strip'] = $strip;

  $out[] = $entry;
}

/* --------- Done --------- */
$resp = array('ok'=>true, 'progress'=>$out);
if ($DEBUG) $resp['debug_hint'] = 'progress[].last/next feed your current UI; progress[].strip is for the 10-pill row.';
jexit($resp);