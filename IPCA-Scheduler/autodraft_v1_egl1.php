<?php
/* =======================================================================
   autodraft_v1_egl1.php — Unified Auto-Draft Engine (Brief / SIM / Flight)
   - Supports Students and Cohorts
   - Groups Briefings & SIMs by shared next-eligible scenario
   - Flight: 1 student (+ optional backseat)
   - Enforces "Brief before Sim/Flight" (scenario order)
   - Uses scenario_tracking_{pr_db} per student program (column-agnostic)
   - Device/Instructor mutex checks in reservation_drafts
   - Preview (dry=1) vs Insert (dry=0)
   - Canceller: action=cancel with filters
   - PHP 5.3 compatible

   Assumptions:
   - Tables present:
       reservation_drafts(id, created_at, res_type, start_dt, end_dt, device_id, instructor_user_id, route, mission_code, mission_name, student_ids, created_by)
       programs_users(pu_user, pu_program, pu_start, pu_id, ...)
       programs(pr_id, pr_name, pr_db, ...)
       scenarios(sc_id, sc_program, sc_code, sc_name, sc_type, sc_stage, sc_phase, sc_order, ...)
       cohorts(id, name, program, start_date, end_date, ...)
       cohort_members(id, cohort_id, student_id, role, created_at)

   - Tracking tables per program: scenario_tracking_{pr_db}
     (columns vary; we only require sctr_student + a date-ish column)

   ======================================================================= */

ini_set('display_errors','1'); error_reporting(E_ALL);

/* ----------------- DB CONFIG: adjust if needed ------------------------ */
$DB_HOST = 'com-linmysql056.srv.combell-ops.net';
$DB_PORT = '3306';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123'; // replace if you rotated
/* ---------------------------------------------------------------------- */

date_default_timezone_set('Europe/Brussels'); // server timestamps rule

/* ----------------- PDO + tiny helpers -------------------------------- */
function pdo() {
    static $pdo;
    if ($pdo) return $pdo;
    $dsn = "mysql:host={$GLOBALS['DB_HOST']};port={$GLOBALS['DB_PORT']};dbname={$GLOBALS['DB_NAME']}";
    $pdo = new PDO($dsn, $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES latin1"
    ));
    return $pdo;
}
function q($sql,$params=array()) { $st=pdo()->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
function one($sql,$params=array()) { $st=pdo()->prepare($sql); $st->execute($params); $r=$st->fetch(PDO::FETCH_ASSOC); return $r ? $r : null; }
function jexit($arr){ header('Content-Type: application/json'); echo json_encode($arr); exit; }
function get($k,$def=null){ return isset($_GET[$k]) ? $_GET[$k] : $def; }
function to_dt($date,$time){ return trim($date.' '.$time); }
function parse_slot($slot) {
    // "HH:MM-HH:MM"
    if (!preg_match('/^([0-2]\d:[0-5]\d)-([0-2]\d:[0-5]\d)$/', $slot, $m)) return null;
    return array($m[1], $m[2]);
}
function minutes_between($start_dt,$end_dt){
    return intval( (strtotime($end_dt) - strtotime($start_dt))/60 );
}

/* ----------------- Inputs -------------------------------------------- */
// Primary action
$action          = strtoupper(get('action','DRAFT')); // DRAFT | CANCEL

// Targeting
$student_ids_csv = trim((string)get('student_ids',''));
$cohort_id       = get('cohort_id', null);

// Types requested (A–G combos)
$type            = strtoupper(get('type','SIM')); // BRIEF | SIM | FLIGHT | MIX
$mix             = strtoupper(get('mix',''));     // e.g. BRIEF+SIM, BRIEF+FLIGHT, SIM+FLIGHT, BRIEF+SIM+FLIGHT

// Grouping & packs
$group_brief     = intval(get('group_brief',1));  // group students into one Briefing reservation
$group_sim       = intval(get('group_sim',0));    // 0 => individual SIM slots (sequential if window)
$pack_size       = max(1, intval(get('pack_size',1))); // SIM per-hour packs per student if splitting window
$backseat_id     = get('backseat_id', null);      // For FLIGHT only (one extra student)

// Time window
$date_from       = get('date_from', date('Y-m-d'));
$days            = max(1, intval(get('days',1)));
$slot            = get('slot', null);             // "HH:MM-HH:MM" required for DRAFT
$slot_parts      = $slot ? parse_slot($slot) : null;

// Resources
$device_id       = get('device_id', null);  // required for SIM, optional for BRIEF, required for FLIGHT (if device-bound AATD/aircraft id)
$instructor_id   = get('instructor_id', null); // optional for BRIEF, required for SIM/FLIGHT unless solo
$route           = get('route', null);

// Modes
$dry             = intval(get('dry',1));     // 1 preview, 0 insert
$created_by      = get('created_by', null);  // optional user id

// Policy flags
$require_dev_inst_sim   = intval(get('require_dev_inst_sim',1));  // require device & instructor for SIM
$require_inst_flight    = intval(get('require_inst_flight',1));   // require instructor for FLIGHT (unless solo token provided)
$solo_flight            = intval(get('solo',0));                  // allow flight without instructor

/* ----------------- Canceller (action=CANCEL) -------------------------- */
if ($action === 'CANCEL') {
    // Filters: by id(s) or by date/type/device/instructor/student_ids contains
    $filters = array();
    $params  = array();

    if ($id = get('id',null)) {
        $filters[] = "id = ?";
        $params[]  = $id;
    }
    if ($start = get('start_from',null)) {
        $filters[] = "start_dt >= ?";
        $params[]  = $start;
    }
    if ($end = get('end_before',null)) {
        $filters[] = "end_dt <= ?";
        $params[]  = $end;
    }
    if ($ftype = get('res_type',null)) {
        $filters[] = "res_type = ?";
        $params[]  = $ftype;
    }
    if ($did = get('device_id',null)) {
        $filters[] = "device_id = ?";
        $params[]  = $did;
    }
    if ($iid = get('instructor_id',null)) {
        $filters[] = "instructor_user_id = ?";
        $params[]  = $iid;
    }
    if ($sid = get('student_id',null)) {
        // NOTE: student_ids is a CSV text; simple LIKE containment
        $filters[] = "student_ids LIKE ?";
        $params[]  = "%".$sid."%";
    }
    if (!$filters) jexit(array('ok'=>false,'error'=>'no_filters','note'=>'Provide at least one filter for CANCEL'));

    $sql = "DELETE FROM reservation_drafts WHERE ".implode(" AND ", $filters);
    $preview = array('sql'=>$sql,'params'=>$params);
    if ($dry) {
        jexit(array('ok'=>true,'dry'=>1,'action'=>'CANCEL_PREVIEW','debug'=>$preview));
    } else {
        $st = pdo()->prepare($sql);
        $st->execute($params);
        jexit(array('ok'=>true,'dry'=>0,'action'=>'CANCEL','deleted'=>$st->rowCount(),'debug'=>$preview));
    }
}

/* ----------------- Guardrails ---------------------------------------- */
if (!$slot_parts) {
    jexit(array('ok'=>false,'error'=>'missing_slot','note'=>"Pass slot=HH:MM-HH:MM"));
}
list($slot_from,$slot_to) = $slot_parts;
$window_start = to_dt($date_from, $slot_from);
$window_end   = to_dt($date_from, $slot_to);
$slot_minutes = minutes_between($window_start, $window_end);
if ($slot_minutes <= 0) jexit(array('ok'=>false,'error'=>'bad_slot','note'=>'End must be after start'));

/* ----------------- Resolve targets: students ------------------------- */
$student_ids = array();
if ($student_ids_csv) {
    foreach (explode(',', $student_ids_csv) as $sid) {
        $sid = intval(trim($sid));
        if ($sid>0) $student_ids[] = $sid;
    }
}
if ($cohort_id && !$student_ids) {
    $rows = q("SELECT student_id FROM cohort_members WHERE cohort_id = ?", array($cohort_id));
    foreach ($rows as $r) $student_ids[] = intval($r['student_id']);
}
$student_ids = array_values(array_unique($student_ids));
if (!$student_ids) {
    jexit(array('ok'=>true,'dry'=>1,'inserted'=>0,'items'=>array(),'note'=>'No target students'));
}

/* ----------------- Program + tracking helpers ------------------------ */
function latest_program_for($student_id){
    return one("
      SELECT p.pr_id, p.pr_name, p.pr_db
        FROM programs_users pu
        JOIN programs p ON p.pr_id = pu.pu_program
       WHERE pu.pu_user = ?
    ORDER BY pu.pu_start DESC, pu.pu_id DESC
       LIMIT 1
    ", array($student_id));
}
function tracking_table_for_prog($prog){
    if (!$prog || !isset($prog['pr_db'])) return null;
    // The pr_db for you already stores the full suffix e.g. scenario_tracking_EASAACP
    // so we use it as-is.
    return $prog['pr_db'];
}
function inspect_tracking_schema($track_table){
    // Returns: array('ok'=>bool, 'student_col'=>..., 'when_col'=>..., 'columns'=>..., 'error'=>? )
    // Discover columns
    try {
        $cols = q("SHOW COLUMNS FROM `{$track_table}`");
    } catch (Exception $e){
        return array('ok'=>false,'error'=>'track_missing','columns'=>array());
    }
    $map = array();
    foreach ($cols as $c) $map[$c['Field']] = strtolower($c['Type']);

    // Required: student column + a date-ish column; and a scenario foreign key
    $student_candidates = array('sctr_student','student_id','stu_id');
    $when_candidates    = array('sctr_when','sctr_date','event_dt','created_at','updated_at','date');
    $scenario_candidates= array('sctr_scenario_id','sctr_scenario','scenario_id');

    $student_col = null; foreach($student_candidates as $c){ if(isset($map[$c])) {$student_col=$c; break;} }
    $when_col    = null; foreach($when_candidates as $c){ if(isset($map[$c])) {$when_col=$c; break;} }
    $scenario_fk = null; foreach($scenario_candidates as $c){ if(isset($map[$c])) {$scenario_fk=$c; break;} }

    $ok = ($student_col && $when_col && $scenario_fk);
    return array('ok'=>$ok,'student_col'=>$student_col,'when_col'=>$when_col,'scenario_fk'=>$scenario_fk,'columns'=>$map,'error'=> $ok ? null : 'track_columns_incomplete');
}

/* ----------------- Scenario queries ---------------------------------- */
function list_program_scenarios($pr_id, $types=null){
    $sql = "SELECT sc_id, sc_code, sc_name, sc_type, sc_stage, sc_phase, sc_order
              FROM scenarios
             WHERE sc_program = ?
    ";
    $params = array($pr_id);
    if ($types) {
        $upper = array_map('strtoupper',$types);
        $in = implode("','",$upper);
        $sql .= " AND UPPER(sc_type) IN ('{$in}')";
    }
    $sql .= " ORDER BY sc_stage, sc_phase, sc_order";
    return q($sql, $params);
}
function latest_attempts_map($track_table, $student_id, $schema){
    // returns sc_id => last_row (status-ish fields if present)
    // We only need to know *which scenarios* have entries to infer done/repeat/etc.
    $student_col = $schema['student_col'];
    $when_col    = $schema['when_col'];
    $scenario_fk = $schema['scenario_fk'];

    // Pull last entry per scenario id for the student
    $rows = q("
        SELECT t.*
          FROM `{$track_table}` t
          JOIN (
                SELECT {$scenario_fk} AS scn, MAX({$when_col}) AS mx
                  FROM `{$track_table}`
                 WHERE {$student_col} = ?
              GROUP BY {$scenario_fk}
          ) pivot ON pivot.scn = t.{$scenario_fk} AND pivot.mx = t.{$when_col}
         WHERE t.{$student_col} = ?
    ", array($student_id, $student_id));

    $out = array();
    foreach ($rows as $r) {
        $sid_key = isset($r[$scenario_fk]) ? intval($r[$scenario_fk]) : null;
        if ($sid_key) $out[$sid_key] = $r;
    }
    return $out;
}

/* Determine the next eligible scenario respecting "Brief before Sim/Flight" */
function next_eligible_for_type($student_id, $type, &$debug){
    // 1) Latest program
    $prog  = latest_program_for($student_id);
    if (!$prog) {
        $debug['program'] = null;
        return array('error'=>'no_program');
    }
    $debug['program'] = $prog;

    // 2) Tracking table discovery
    $track = tracking_table_for_prog($prog);
    $debug['track'] = $track;
    $schema = $track ? inspect_tracking_schema($track) : array('ok'=>false,'error'=>'no_track');
    $debug['schema'] = $schema;
    if (!$schema['ok']) {
        return array('error'=>'track_unusable');
    }

    // 3) Program scenarios in order
    $all = list_program_scenarios($prog['pr_id'], null);
    if (!$all) return array('error'=>'no_scenarios');

    // Create index by sc_id & by code
    $by_id = array();
    foreach ($all as $r) $by_id[intval($r['sc_id'])] = $r;

    // 4) Latest attempts map
    $attempts = latest_attempts_map($track, $student_id, $schema);
    $debug['attempts_count'] = count($attempts);

    // 5) Figure position in the ordered list → first not-performed Brief is the “anchor”
    // Heuristic: a scenario is “done” if the student has any tracking row for that sc_id
    $anchor_index = null;
    for ($i=0; $i<count($all); $i++){
        $sc = $all[$i];
        if (!isset($attempts[intval($sc['sc_id'])])) { $anchor_index = $i; break; }
    }
    if ($anchor_index === null) {
        // all scenarios have an attempt — nothing to propose
        return array('error'=>'all_done','prog'=>$prog);
    }

    // 6) Enforce "Brief before Sim/Flight":
    // Walk forward from anchor; for SIM/FLIGHT we only propose if the immediate previous Brief is done
    // Also support type=BRIEF directly.
    $want = strtoupper($type);
    $debug['anchor_at'] = $all[$anchor_index];

    if ($want === 'BRIEF') {
        // Propose the next Briefing from anchor forward
        for ($i=$anchor_index; $i<count($all); $i++){
            $sc = $all[$i];
            if (strtoupper($sc['sc_type']) === 'BRIEF') return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog);
        }
        return array('error'=>'no_next_brief');
    }

    if ($want === 'SIM') {
        // First, ensure the last required BRIEF before a SIM is completed.
        // From anchor forward, find the first SIM such that the nearest prior Brief has an attempt.
        for ($i=$anchor_index; $i<count($all); $i++){
            $sc = $all[$i];
            if (in_array(strtoupper($sc['sc_type']), array('SIM','FNPT','SAB'))) {
                // find prior brief
                $brief_ok = true;
                for ($j=$i-1; $j>=0; $j--){
                    $prev = $all[$j];
                    if (strtoupper($prev['sc_type']) === 'BRIEF') {
                        $brief_ok = isset($attempts[intval($prev['sc_id'])]);
                        break;
                    }
                }
                if ($brief_ok) return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog);
            }
        }
        return array('error'=>'no_eligible_sim');
    }

    if ($want === 'FLIGHT') {
        // Ensure nearest prior SIM (if any) is completed; otherwise ensure prior Brief is completed.
        for ($i=$anchor_index; $i<count($all); $i++){
            $sc = $all[$i];
            if (in_array(strtoupper($sc['sc_type']), array('FLIGHT','SE','ME'))) {
                // Check if a SIM exists immediately before it; if yes, require attempt.
                $need_sim_ok = false;
                $has_sim     = false;
                for ($j=$i-1; $j>=0; $j--){
                    $prev = $all[$j];
                    $pt   = strtoupper($prev['sc_type']);
                    if ($pt === 'BRIEF') {
                        // brief gate: must be completed either way
                        $brief_ok = isset($attempts[intval($prev['sc_id'])]);
                        if (!$brief_ok) { $has_sim=true; $need_sim_ok=false; break; }
                        // keep scanning for SIM just before flight as well
                    }
                    if (in_array($pt, array('SIM','FNPT','SAB'))) {
                        $has_sim = true;
                        $need_sim_ok = isset($attempts[intval($prev['sc_id'])]);
                        break;
                    }
                }
                // Validate gates
                $gates_ok = true;
                // brief gate
                $nearest_brief_ok = true;
                for ($j=$i-1; $j>=0; $j--){
                    $prev = $all[$j];
                    if (strtoupper($prev['sc_type'])==='BRIEF'){
                        $nearest_brief_ok = isset($attempts[intval($prev['sc_id'])]);
                        break;
                    }
                }
                if (!$nearest_brief_ok) $gates_ok = false;
                if ($has_sim && !$need_sim_ok) $gates_ok = false;

                if ($gates_ok) return array('ok'=>true,'scenario'=>$sc,'prog'=>$prog);
            }
        }
        return array('error'=>'no_eligible_flight');
    }

    // Unknown type
    return array('error'=>'bad_type');
}

/* ----------------- Conflict checks (reservation_drafts) --------------- */
function device_busy($device_id,$start_dt,$end_dt){
    $row = one("SELECT id FROM reservation_drafts WHERE device_id=? AND NOT (end_dt <= ? OR start_dt >= ?) LIMIT 1",
               array($device_id, $start_dt, $end_dt));
    return $row ? intval($row['id']) : 0;
}
function instructor_busy($instructor_id,$start_dt,$end_dt){
    $row = one("SELECT id FROM reservation_drafts WHERE instructor_user_id=? AND NOT (end_dt <= ? OR start_dt >= ?) LIMIT 1",
               array($instructor_id, $start_dt, $end_dt));
    return $row ? intval($row['id']) : 0;
}

/* ----------------- Build reservations --------------------------------- */
$items = array();
$inserted = 0;
$debug_root = array(
    'type'=>$type,
    'mix'=>$mix,
    'window'=>array('start'=>$window_start,'end'=>$window_end,'minutes'=>$slot_minutes),
    'group_brief'=>$group_brief,
    'group_sim'=>$group_sim,
    'pack_size'=>$pack_size,
    'solo_flight'=>$solo_flight
);

/* Helper: insert or preview */
function preview_or_insert($rec, $dry){
    if ($dry) return array_merge($rec, array('action'=>'PREVIEW'));
    // Insert
    $sql = "INSERT INTO reservation_drafts
              (created_by, res_type, start_dt, end_dt, device_id, instructor_user_id, route, mission_code, mission_name, student_ids)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
    $p = array(
        isset($rec['created_by'])?$rec['created_by']:null,
        $rec['res_type'], $rec['start_dt'], $rec['end_dt'],
        isset($rec['device_id'])?$rec['device_id']:null,
        isset($rec['instructor_user_id'])?$rec['instructor_user_id']:null,
        isset($rec['route'])?$rec['route']:null,
        isset($rec['mission_code'])?$rec['mission_code']:null,
        isset($rec['mission_name'])?$rec['mission_name']:null,
        $rec['student_ids']
    );
    $st = pdo()->prepare($sql);
    $st->execute($p);
    return array('student_id'=>$rec['student_id'],'mission_code'=>$rec['mission_code'],'start_dt'=>$rec['start_dt'],'end_dt'=>$rec['end_dt'],'action'=>'INSERTED');
}

/* ----------------- Dispatch per student & type ------------------------ */
if ($type !== 'MIX') {
    // Single type pipeline for each student (BRIEF | SIM | FLIGHT)
    foreach ($student_ids as $SID) {
        $dbg = array('student'=>$SID);
        $pick = next_eligible_for_type($SID, $type, $dbg);

        if (!isset($pick['ok'])) {
            // explain why
            $items[] = array('student_id'=>$SID,'action'=>'NO_PROPOSALS','reason'=>$pick['error'],'debug'=>$dbg);
            continue;
        }

        $sc  = $pick['scenario'];
        $res_type = ($type==='BRIEF' ? 'Briefing' : ($type==='SIM' ? 'Simulator' : 'Flight Training'));
        $mission_code = $sc['sc_code'];
        $mission_name = $sc['sc_name'];

        // time slicing (SIM packs) — if group_sim==0 we keep single block; pack_size>1 will split window equally for student
        $start_dt = $GLOBALS['window_start'];
        $end_dt   = $GLOBALS['window_end'];

        // Requirements
        if ($type==='SIM') {
            if ($GLOBALS['require_dev_inst_sim'] && (!$GLOBALS['device_id'] || !$GLOBALS['instructor_id'])) {
                $items[] = array('student_id'=>$SID,'action'=>'NO_PROPOSALS','reason'=>'require_device_instructor','debug'=>$dbg);
                continue;
            }
            // Mutex checks
            $confD = device_busy($GLOBALS['device_id'], $start_dt, $end_dt);
            if ($confD) { $items[] = array('student_id'=>$SID,'action'=>'SKIP_DEVICE_CONFLICT','start_dt'=>$start_dt,'end_dt'=>$end_dt); continue; }
            $confI = instructor_busy($GLOBALS['instructor_id'], $start_dt, $end_dt);
            if ($confI) { $items[] = array('student_id'=>$SID,'action'=>'SKIP_INSTR_CONFLICT','start_dt'=>$start_dt,'end_dt'=>$end_dt); continue; }
        }
        if ($type==='FLIGHT') {
            if (!$GLOBALS['solo_flight'] && $GLOBALS['require_inst_flight'] && !$GLOBALS['instructor_id']) {
                $items[] = array('student_id'=>$SID,'action'=>'NO_PROPOSALS','reason'=>'require_instructor_flight','debug'=>$dbg);
                continue;
            }
            if ($GLOBALS['device_id']) {
                $confD = device_busy($GLOBALS['device_id'], $start_dt, $end_dt);
                if ($confD) { $items[] = array('student_id'=>$SID,'action'=>'SKIP_DEVICE_CONFLICT','start_dt'=>$start_dt,'end_dt'=>$end_dt); continue; }
            }
            if (!$GLOBALS['solo_flight'] && $GLOBALS['instructor_id']) {
                $confI = instructor_busy($GLOBALS['instructor_id'], $start_dt, $end_dt);
                if ($confI) { $items[] = array('student_id'=>$SID,'action'=>'SKIP_INSTR_CONFLICT','start_dt'=>$start_dt,'end_dt'=>$end_dt); continue; }
            }
        }

        $rec = array(
            'student_id'=>$SID,
            'res_type'=>$res_type,
            'start_dt'=>$start_dt,
            'end_dt'=>$end_dt,
            'device_id'=>$GLOBALS['device_id'],
            'instructor_user_id'=>$GLOBALS['instructor_id'],
            'route'=>$GLOBALS['route'],
            'mission_code'=>$mission_code,
            'mission_name'=>$mission_name,
            'student_ids'=>(string)$SID,
            'created_by'=>$GLOBALS['created_by']
        );
        $out = preview_or_insert($rec, $GLOBALS['dry']);
        if ($out['action']==='INSERTED') $inserted++;
        $items[] = $out;
    }

} else {
    // MIX: interpret 'mix' flags (e.g. BRIEF+SIM, BRIEF+SIM+FLIGHT)
    $want = array();
    foreach (array('BRIEF','SIM','FLIGHT') as $k) {
        if (strpos($mix, $k)!==false) $want[$k]=1;
    }
    if (!$want) $want['SIM']=1; // default

    // Grouping behavior:
    // - Briefings: if group_brief=1, group students by shared next Brief scenario code → one reservation
    // - SIMs: if group_sim=1 we still create individual reservations but you can chain them by calling multiple slots or days
    // Here, we’ll propose per student, but also return a grouping map to let the chat client chain/stack them.

    $brief_groups = array(); // code => [ {SID,mission}, ... ]
    $sim_groups   = array();

    $per_student_outputs = array();

    foreach ($student_ids as $SID) {
        $student_plan = array('student_id'=>$SID,'proposals'=>array(),'debug'=>array());
        foreach ($want as $T=>$yes) if ($yes){
            $dbg = array('student'=>$SID,'type'=>$T);
            $pick = next_eligible_for_type($SID, $T, $dbg);
            if (!isset($pick['ok'])) {
                $student_plan['proposals'][] = array('action'=>'NO_PROPOSALS','reason'=>$pick['error'],'debug'=>$dbg);
                continue;
            }
            $sc = $pick['scenario'];
            $res_type = ($T==='BRIEF' ? 'Briefing' : ($T==='SIM'?'Simulator':'Flight Training'));
            $mission_code = $sc['sc_code'];
            $mission_name = $sc['sc_name'];

            // QUICK conflict checks for SIM/FLIGHT
            if ($T==='SIM' && $GLOBALS['require_dev_inst_sim']) {
                if (!$GLOBALS['device_id'] || !$GLOBALS['instructor_id']) {
                    $student_plan['proposals'][] = array('action'=>'NO_PROPOSALS','reason'=>'require_device_instructor','debug'=>$dbg);
                    continue;
                }
                if (device_busy($GLOBALS['device_id'],$GLOBALS['window_start'],$GLOBALS['window_end'])) {
                    $student_plan['proposals'][] = array('action'=>'SKIP_DEVICE_CONFLICT','start_dt'=>$GLOBALS['window_start'],'end_dt'=>$GLOBALS['window_end']);
                    continue;
                }
                if (instructor_busy($GLOBALS['instructor_id'],$GLOBALS['window_start'],$GLOBALS['window_end'])) {
                    $student_plan['proposals'][] = array('action'=>'SKIP_INSTR_CONFLICT','start_dt'=>$GLOBALS['window_start'],'end_dt'=>$GLOBALS['window_end']);
                    continue;
                }
            }
            if ($T==='FLIGHT'){
                if (!$GLOBALS['solo_flight'] && $GLOBALS['require_inst_flight'] && !$GLOBALS['instructor_id']){
                    $student_plan['proposals'][] = array('action'=>'NO_PROPOSALS','reason'=>'require_instructor_flight','debug'=>$dbg);
                    continue;
                }
            }

            $rec = array(
                'student_id'=>$SID,
                'res_type'=>$res_type,
                'start_dt'=>$GLOBALS['window_start'],
                'end_dt'=>$GLOBALS['window_end'],
                'device_id'=>$GLOBALS['device_id'],
                'instructor_user_id'=>$GLOBALS['instructor_id'],
                'route'=>$GLOBALS['route'],
                'mission_code'=>$mission_code,
                'mission_name'=>$mission_name,
                'student_ids'=>(string)$SID,
                'created_by'=>$GLOBALS['created_by']
            );

            // Grouping buckets
            if ($T==='BRIEF' && $GLOBALS['group_brief']){
                if (!isset($brief_groups[$mission_code])) $brief_groups[$mission_code]=array('mission_name'=>$mission_name,'students'=>array());
                $brief_groups[$mission_code]['students'][] = $rec;
            } elseif ($T==='SIM' && $GLOBALS['group_sim']){
                if (!isset($sim_groups[$mission_code])) $sim_groups[$mission_code]=array('mission_name'=>$mission_name,'students'=>array());
                $sim_groups[$mission_code]['students'][] = $rec;
            } else {
                // push now as individual (preview/insert)
                $out = preview_or_insert($rec, $GLOBALS['dry']);
                if ($out['action']==='INSERTED') $GLOBALS['inserted']++;
                $GLOBALS['items'][] = $out;
                $student_plan['proposals'][] = $out;
            }
        }
        $per_student_outputs[] = $student_plan;
    }

    // Build grouped Briefing reservations (one per mission_code)
    if ($group_brief && $brief_groups){
        foreach ($brief_groups as $code => $bag){
            $students = $bag['students'];
            if (!$students) continue;
            // Merge students into one CSV
            $ids = array(); foreach ($students as $r){ $ids[] = $r['student_id']; }
            $rec = $students[0];
            $rec['student_ids'] = implode(',', $ids);
            // For Briefing we can allow no instructor/device (unless provided)
            $rec['res_type'] = 'Briefing';
            $rec['mission_code'] = $code;
            $rec['mission_name'] = $bag['mission_name'].' (Group)';
            $out = preview_or_insert($rec, $dry);
            if ($out['action']==='INSERTED') $inserted++;
            $items[] = $out;
        }
    }

    // Build grouped SIM reservations sequentially inside the same window (simple splitter)
    // Example: window 10:00-12:00, 3 students → each gets 40 minutes
    if ($group_sim && $sim_groups){
        foreach ($sim_groups as $code => $bag){
            $students = $bag['students'];
            if (!$students) continue;
            $n = count($students);
            $winStart = strtotime($window_start);
            $winEnd   = strtotime($window_end);
            $totalMin = intval(($winEnd-$winStart)/60);
            $eachMin  = max(15, floor($totalMin / $n)); // minimum 15min slice

            for ($i=0; $i<$n; $i++){
                $st = date('Y-m-d H:i:s', $winStart + ($i * $eachMin * 60));
                $en = date('Y-m-d H:i:s', $winStart + (($i+1) * $eachMin * 60));
                if (strtotime($en) > $winEnd) $en = $window_end;

                $base = $students[$i];
                $base['start_dt'] = $st;
                $base['end_dt']   = $en;
                $base['mission_code'] = $code;
                $base['mission_name'] = $bag['mission_name'];
                // Mutex checks again per split
                if ($require_dev_inst_sim){
                    if (device_busy($device_id,$st,$en)) { $items[] = array('student_id'=>$base['student_id'],'action'=>'SKIP_DEVICE_CONFLICT','start_dt'=>$st,'end_dt'=>$en); continue; }
                    if (instructor_busy($instructor_id,$st,$en)) { $items[] = array('student_id'=>$base['student_id'],'action'=>'SKIP_INSTR_CONFLICT','start_dt'=>$st,'end_dt'=>$en); continue; }
                }
                $out = preview_or_insert($base, $dry);
                if ($out['action']==='INSERTED') $inserted++;
                $items[] = $out;
            }
        }
    }

    // Also return per-student plan (debug aid)
    $debug_root['per_student'] = $per_student_outputs;
}

/* ----------------- Output -------------------------------------------- */
jexit(array('ok'=>true,'dry'=>$dry,'inserted'=>$inserted,'items'=>$items,'debug'=>$debug_root));