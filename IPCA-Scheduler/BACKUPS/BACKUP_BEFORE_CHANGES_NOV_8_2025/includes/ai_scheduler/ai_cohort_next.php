<?php
// ===================================================================
// AI Scheduler Helper — Next Scenario Finder per Cohort
// Compatible with PHP 5.3 (mysqli)
// Depends on: programs_users, programs, scenarios, scenario_tracking_*
// ===================================================================

if (!defined('AI_COHORT_NEXT_HELPER')) define('AI_COHORT_NEXT_HELPER', 1);

/* -------------------------------------------------------------------
   Basic helpers (from your progress logic)
------------------------------------------------------------------- */
if (!function_exists('normalize_sc_type')) {
  function normalize_sc_type($raw){
    $t = strtoupper(trim((string)$raw));
    if ($t === 'LB') return 'BRIEFING';
    if ($t === 'FNPT' || $t === 'SAB' || $t === 'SIM') return 'SIMULATOR';
    if ($t === 'FLIGHT') return 'FLIGHT';
    return '';
  }
}
if (!function_exists('is_incomplete')) {
  function is_incomplete($g){
    $g = strtoupper(trim((string)$g));
    return ($g !== '' && substr($g, -1) === 'I');
  }
}
if (!function_exists('is_red')) {
  function is_red($g){
    $g = strtoupper(trim((string)$g));
    return ($g !== '' && $g[0] === 'R');
  }
}
if (!function_exists('must_repeat')) {
  function must_repeat($g){ return is_incomplete($g) || is_red($g); }
}

/* -------------------------------------------------------------------
   Find next scenario after a given one (curriculum order)
------------------------------------------------------------------- */
if (!function_exists('get_next_after_bucket')) {
  function get_next_after_bucket($mysqli, $program_id, $after_order, $after_stage, $after_phase){
    $q = "SELECT sc_id, sc_code, sc_name, sc_type, sc_stage, sc_phase, sc_order
          FROM scenarios
          WHERE sc_program = ".(int)$program_id."
            AND (sc_stage > ".(int)$after_stage."
              OR (sc_stage = ".(int)$after_stage." AND sc_phase > ".(int)$after_phase.")
              OR (sc_stage = ".(int)$after_stage." AND sc_phase = ".(int)$after_phase." AND sc_order > ".(int)$after_order."))
          ORDER BY sc_stage ASC, sc_phase ASC, sc_order ASC
          LIMIT 1";
    $rs = @mysqli_query($mysqli, $q);
    if ($rs) {
      $row = mysqli_fetch_assoc($rs);
      mysqli_free_result($rs);
      return $row ? $row : null;
    }
    return null;
  }
}

/* -------------------------------------------------------------------
   get_next_scenario_for_student($mysqli, $student_id)
------------------------------------------------------------------- */
if (!function_exists('get_next_scenario_for_student')) {
  function get_next_scenario_for_student($mysqli, $student_id){
    $out = array(
      'student_id'       => (int)$student_id,
      'next_scenario_id' => null,
      'sc_code'          => null,
      'sc_name'          => null,
      'sc_type'          => null,
      'rule'             => null
    );

    // --- Find student's latest active program ---
    $sql = "SELECT p.pr_id, p.pr_db
            FROM programs_users pu
            INNER JOIN programs p ON p.pr_id = pu.pu_program
            WHERE pu.pu_user = ".(int)$student_id."
            ORDER BY pu.pu_start DESC, pu.pu_id DESC
            LIMIT 1";
    $rs = @mysqli_query($mysqli, $sql);
    if (!$rs) return $out;
    $pr = mysqli_fetch_assoc($rs);
    mysqli_free_result($rs);
    if (!$pr) return $out;

    $pid   = (int)$pr['pr_id'];
    $rawDb = preg_replace('/[^A-Za-z0-9_]/', '', (string)$pr['pr_db']);
    $track = ($rawDb=='' ? '' : (preg_match('/^scenario_tracking_/i',$rawDb)?$rawDb:('scenario_tracking_'.$rawDb)));
    if ($track === '') return $out;

    // --- Latest attempt overall ---
    $q = "SELECT t.sctr_scenario_id, t.sctr_grading,
                 s.sc_id, s.sc_code, s.sc_name, s.sc_type,
                 s.sc_stage, s.sc_phase, s.sc_order
          FROM `".$track."` t
          INNER JOIN scenarios s ON s.sc_id = t.sctr_scenario_id AND s.sc_program = ".$pid."
          WHERE t.sctr_student = ".(int)$student_id."
          ORDER BY t.sctr_date DESC, t.sctr_id DESC
          LIMIT 1";
    $r  = @mysqli_query($mysqli, $q);
    $latest = $r ? mysqli_fetch_assoc($r) : null;
    if ($r) mysqli_free_result($r);

    // --- Decide next ---
    if ($latest) {
      if (must_repeat($latest['sctr_grading'])) {
        $out['next_scenario_id'] = (int)$latest['sc_id'];
        $out['sc_code'] = (string)$latest['sc_code'];
        $out['sc_name'] = (string)$latest['sc_name'];
        $out['sc_type'] = normalize_sc_type($latest['sc_type']);
        $out['rule']    = 'repeat';
        return $out;
      }

      $nx = get_next_after_bucket($mysqli,$pid,
                                  (int)$latest['sc_order'],
                                  (int)$latest['sc_stage'],
                                  (int)$latest['sc_phase']);
      if ($nx) {
        $out['next_scenario_id'] = (int)$nx['sc_id'];
        $out['sc_code'] = (string)$nx['sc_code'];
        $out['sc_name'] = (string)$nx['sc_name'];
        $out['sc_type'] = normalize_sc_type($nx['sc_type']);
        $out['rule']    = 'proceed';
        return $out;
      }
      return $out; // end of curriculum
    }

    // --- No history → first scenario ---
    $q2 = "SELECT sc_id, sc_code, sc_name, sc_type
           FROM scenarios
           WHERE sc_program = ".$pid."
           ORDER BY sc_stage ASC, sc_phase ASC, sc_order ASC
           LIMIT 1";
    $r2 = @mysqli_query($mysqli, $q2);
    if ($r2) {
      $row = mysqli_fetch_assoc($r2);
      mysqli_free_result($r2);
      if ($row) {
        $out['next_scenario_id'] = (int)$row['sc_id'];
        $out['sc_code'] = (string)$row['sc_code'];
        $out['sc_name'] = (string)$row['sc_name'];
        $out['sc_type'] = normalize_sc_type($row['sc_type']);
        $out['rule']    = 'start';
      }
    }
    return $out;
  }
}

/* -------------------------------------------------------------------
   get_cohort_next_scenarios($mysqli, $cohort_id)
------------------------------------------------------------------- */
if (!function_exists('get_cohort_next_scenarios')) {
  function get_cohort_next_scenarios($mysqli, $cohort_id){
    $out = array();
    $sql = "SELECT cm.student_id, u.voornaam, u.naam
            FROM cohort_members cm
            INNER JOIN users u ON u.userid = cm.student_id
            WHERE cm.cohort_id = ".(int)$cohort_id."
              AND cm.role = 'Student'";
    $rs = @mysqli_query($mysqli, $sql);
    if (!$rs) return $out;

    while ($row = mysqli_fetch_assoc($rs)) {
      $sid  = (int)$row['student_id'];
      $name = trim((string)$row['voornaam'].' '.(string)$row['naam']);
      $N = get_next_scenario_for_student($mysqli, $sid);

      $nextPack = null;
      if ($N && $N['next_scenario_id']) {
        $nextPack = array(
          'sc_id'   => (int)$N['next_scenario_id'],
          'code'    => (string)$N['sc_code'],
          'name'    => (string)$N['sc_name'],
          'sc_type' => (string)$N['sc_type']
        );
      }

      $out[] = array(
        'student_id' => $sid,
        'name'       => $name,
        'next'       => $nextPack,
        'rule'       => $N ? $N['rule'] : null
      );
    }
    mysqli_free_result($rs);
    return $out;
  }
}

/* -------------------------------------------------------------------
   Stand-alone fallback for planner: get_next_for_student($student_id)
   Opens its own mysqli connection if needed.
------------------------------------------------------------------- */
if (!function_exists('get_next_for_student')) {
  function get_next_for_student($student_id) {
    // --- Reuse or open connection ---
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli']) {
      $mysqli = $GLOBALS['mysqli'];
    } else {
      // Load DB constants if not already loaded
      if (!isset($GLOBALS['DB_HOST'])) {
        $GLOBALS['DB_HOST'] = 'com-linmysql056.srv.combell-ops.net';
        $GLOBALS['DB_NAME'] = 'ID127947_egl1';
        $GLOBALS['DB_USER'] = 'ID127947_egl1';
        $GLOBALS['DB_PASS'] = 'Plane123';
      }
      $mysqli = @mysqli_connect(
        $GLOBALS['DB_HOST'],
        $GLOBALS['DB_USER'],
        $GLOBALS['DB_PASS'],
        $GLOBALS['DB_NAME']
      );
      if (!$mysqli) return null;
      @mysqli_set_charset($mysqli, 'utf8');
    }

    // --- Get next scenario ---
    $nx = get_next_scenario_for_student($mysqli, (int)$student_id);
    if (!$nx || empty($nx['next_scenario_id'])) {
      if (!isset($GLOBALS['mysqli']) || !$GLOBALS['mysqli']) @mysqli_close($mysqli);
      return null;
    }

    // --- Ensure name/type available ---
    $sid = (int)$nx['next_scenario_id'];
    $row = null;
    $rs = @mysqli_query($mysqli, "SELECT sc_name, sc_type FROM scenarios WHERE sc_id=".$sid." LIMIT 1");
    if ($rs) {
      $tmp = mysqli_fetch_assoc($rs);
      if ($tmp) $row = $tmp;
      mysqli_free_result($rs);
    }

    if (!isset($GLOBALS['mysqli']) || !$GLOBALS['mysqli']) @mysqli_close($mysqli);

    return array(
      'sc_id'   => $sid,
      'code'    => (string)$nx['sc_code'],
      'name'    => $row ? (string)$row['sc_name'] : (string)$nx['sc_name'],
      'sc_type' => $row ? normalize_sc_type($row['sc_type']) : (string)$nx['sc_type']
    );
  }
}
?>