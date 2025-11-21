<?php
// ===================================================================
// AI Scheduler — Unified Matching Helper (PHP 5.3 compatible)
// Combines credential parsing + eligibility + DB-level filtering
// ===================================================================

if (!defined('AI_SCHEDULER_HELPER')) define('AI_SCHEDULER_HELPER', 1);

// ---------------- CSV helpers ----------------
function ai_csv_to_set($csv) {
    $out = array();
    foreach (explode(',', (string)$csv) as $p) {
        $p = strtoupper(trim($p));
        if ($p !== '') $out[$p] = true;
    }
    return $out;
}
function ai_set_contains_all($have, $need) {
    foreach ($need as $k => $v) if (!isset($have[$k])) return false;
    return true;
}
function ai_csv_contains_all($hayCsv, $needCsv) {
    return ai_set_contains_all(ai_csv_to_set($hayCsv), ai_csv_to_set($needCsv));
}

// ---------------- Device capability check ----------------
function device_supports_scenario_csv($deviceCsvCaps, $scenarioCsvReq) {
    if (trim((string)$scenarioCsvReq) === '') return true;
    return ai_csv_contains_all($deviceCsvCaps, $scenarioCsvReq);
}

// ---------------- Instructor credential validity check (mysqli; legacy use) ----------------
function instructor_is_eligible($db_mysqli, $instructorId, $requiredCsv, $when /* 'Y-m-d H:i:s' */) {
    $required = array_keys(ai_csv_to_set($requiredCsv));
    if (!$required) return true;

    $t = strtotime($when);
    $have = array();

    if ($stmt = mysqli_prepare($db_mysqli, "SELECT credential_code, valid_from, valid_to, status
                                            FROM user_credentials
                                            WHERE user_id = ? AND status = 'active'")) {
        mysqli_stmt_bind_param($stmt, 'i', $instructorId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $ok = true;
                if (!empty($row['valid_from']) && $t < strtotime($row['valid_from'])) $ok = false;
                if (!empty($row['valid_to'])   && $t > strtotime($row['valid_to']))   $ok = false;
                if ($ok) $have[strtoupper($row['credential_code'])] = true;
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);
    }

    if (!$have) {
        if ($stmt = mysqli_prepare($db_mysqli, "SELECT credentials FROM users WHERE userid = ?")) {
            mysqli_stmt_bind_param($stmt, 'i', $instructorId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                $r = mysqli_fetch_assoc($res);
                mysqli_free_result($res);
                if ($r && isset($r['credentials'])) $have = ai_csv_to_set($r['credentials']);
            }
            mysqli_stmt_close($stmt);
        }
    }

    foreach ($required as $code) if (empty($have[$code])) return false;
    return true;
}

// ---------------- Helper (PDO): does instructor have ALL required codes? ----------------
if (!function_exists('ai_instructor_has_all_credentials')) {
  function ai_instructor_has_all_credentials($dbh, $userId, $reqList) {
    if (!is_array($reqList) || !count($reqList)) return true;

    $have = array();

    // 1) Normalized table
    try {
      $stmt = $dbh->prepare("SELECT UPPER(credential_code) AS code
                             FROM instructor_credentials
                             WHERE user_id = :uid");
      $stmt->execute(array(':uid' => (int)$userId));
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['code'])) $have[$row['code']] = true;
      }
    } catch (Exception $e) {
      // ignore if table missing
    }

    // 2) Fallback: users.credentials CSV
    if (!count($have)) {
      $stmt = $dbh->prepare("SELECT credentials FROM users WHERE userid = :uid LIMIT 1");
      $stmt->execute(array(':uid' => (int)$userId));
      $r = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($r && isset($r['credentials'])) {
        foreach (explode(',', $r['credentials']) as $c) {
          $k = strtoupper(trim($c));
          if ($k !== '') $have[$k] = true;
        }
      }
    }

    foreach ($reqList as $code) {
      if (empty($have[$code])) return false;
    }
    return true;
  }
}

// ===================================================================
// Database-level filters and availability logic (PDO q())
// Threaded with optional $location (string like 'US', 'BE', etc.)
// Use latin1 conversions where comparing to avoid collation errors.
// ===================================================================

function getQualifiedInstructors($scenarioId, $dbh = null, $location = null) {
    if (!$dbh) $dbh = db();

    // Required instructor credentials for the scenario
    $row = q('SELECT required_instr_credentials FROM scenarios WHERE sc_id=? LIMIT 1',
             array((int)$scenarioId))->fetch();
    $reqList = array();
    if ($row && isset($row['required_instr_credentials'])) {
      foreach (explode(',', $row['required_instr_credentials']) as $c) {
        $k = strtoupper(trim($c));
        if ($k !== '') $reqList[] = $k;
      }
    }

    // Base candidate list: active instructors (+ optional location)
    $sql  = "SELECT u.userid
             FROM users u
             WHERE (LOWER(u.type) LIKE '%instruct%')
               AND (u.actief_tot = '0000-00-00' OR u.actief_tot IS NULL OR u.actief_tot >= CURDATE())";
    $args = array();

    if ($location !== null && $location !== '') {
      $sql .= " AND UPPER(CONVERT(u.work_location USING latin1)) = UPPER(CONVERT(:loc USING latin1))";
      $args[':loc'] = $location;
    }

    $cand = q($sql, $args)->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!count($cand)) return array();

    // No credential requirements → done
    if (!count($reqList)) {
      $out = array();
      foreach ($cand as $x) $out[] = (int)$x;
      return $out;
    }

    // Else keep only those who possess ALL required credentials
    $out = array();
    foreach ($cand as $uid) {
      if (ai_instructor_has_all_credentials($dbh, (int)$uid, $reqList)) {
        $out[] = (int)$uid;
      }
    }
    return $out;
}

function getQualifiedDevices($scenarioId, $dbh = null, $location = null) {
    if (!$dbh) $dbh = db();

    $row = q('SELECT required_device_credentials FROM scenarios WHERE sc_id=? LIMIT 1',
             array((int)$scenarioId))->fetch();
    $req = $row ? strtoupper(trim($row['required_device_credentials'])) : '';

    // Base device query: ACTIVE ONLY
    $sql  = "SELECT d.dev_id
             FROM devices d
             WHERE UPPER(d.dev_active) = 'YES'";
    $args = array();

    // Optional device location filter (explicit column exists)
    if ($location !== null && $location !== '') {
      $sql .= " AND UPPER(CONVERT(d.dev_location USING latin1)) = UPPER(CONVERT(:devloc USING latin1))";
      $args[':devloc'] = $location;
    }

    // Capability requirement
    if ($req !== '' && $req !== 'ANY') {
      $sql .= " AND UPPER(d.dev_type) = :req";
      $args[':req'] = $req;
    }

    $rows = q($sql, $args)->fetchAll(PDO::FETCH_COLUMN, 0);
    $out = array();
    foreach ($rows as $x) $out[] = (int)$x;
    return $out;
}

function getAvailableInstructors($date, $startTime, $endTime, $dbh = null, $location = null) {
    if (!$dbh) $dbh = db();

    $start = $date . ' ' . $startTime . ':00';
    $end   = $date . ' ' . $endTime   . ':00';

    $busy = q('SELECT DISTINCT r.instructor_user_id
               FROM reservations r
               WHERE r.instructor_user_id IS NOT NULL
                 AND r.start_dt < ? AND r.end_dt > ?',
               array($end, $start))->fetchAll(PDO::FETCH_COLUMN, 0);
    $busyList = count($busy) ? implode(',', array_map('intval', $busy)) : '0';

    $sql  = "SELECT u.userid
             FROM users u
             WHERE (LOWER(u.type) LIKE '%instruct%')
               AND (u.actief_tot = '0000-00-00' OR u.actief_tot IS NULL OR u.actief_tot >= CURDATE())
               AND u.userid NOT IN ($busyList)";
    $args = array();

    if ($location !== null && $location !== '') {
      $sql .= " AND UPPER(CONVERT(u.work_location USING latin1)) = UPPER(CONVERT(:loc USING latin1))";
      $args[':loc'] = $location;
    }

    $rows = q($sql, $args)->fetchAll(PDO::FETCH_COLUMN, 0);
    $out = array();
    foreach ($rows as $x) $out[] = (int)$x;
    return $out;
}

function getAvailableDevices($date, $startTime, $endTime, $dbh = null, $location = null) {
    if (!$dbh) $dbh = db();

    $start = $date . ' ' . $startTime . ':00';
    $end   = $date . ' ' . $endTime   . ':00';

    $busy = q('SELECT DISTINCT device_id
               FROM reservations
               WHERE start_dt < ? AND end_dt > ?',
               array($end, $start))->fetchAll(PDO::FETCH_COLUMN, 0);
    $busyList = count($busy) ? implode(',', array_map('intval', $busy)) : '0';

    // ACTIVE devices only; optional location
    $sql  = "SELECT d.dev_id
             FROM devices d
             WHERE UPPER(d.dev_active) = 'YES'
               AND d.dev_id NOT IN ($busyList)";
    $args = array();

    if ($location !== null && $location !== '') {
      $sql .= " AND UPPER(CONVERT(d.dev_location USING latin1)) = UPPER(CONVERT(:devloc USING latin1))";
      $args[':devloc'] = $location;
    }

    $rows = q($sql, $args)->fetchAll(PDO::FETCH_COLUMN, 0);
    $out = array();
    foreach ($rows as $x) $out[] = (int)$x;
    return $out;
}

function getEligibleInstructorDevicePairs($scenarioId, $date, $startTime, $endTime, $dbh = null, $location = null) {
    if (!$dbh) $dbh = db();

    $qualifiedInstr = getQualifiedInstructors($scenarioId, $dbh, $location);
    $qualifiedDevs  = getQualifiedDevices($scenarioId, $dbh, $location);

    if (!count($qualifiedInstr) || !count($qualifiedDevs)) {
      return array('instructors' => array(), 'devices' => array());
    }

    $freeInstr = getAvailableInstructors($date, $startTime, $endTime, $dbh, $location);
    $freeDevs  = getAvailableDevices($date, $startTime, $endTime, $dbh, $location);

    $instr = array_values(array_intersect($qualifiedInstr, $freeInstr));
    $devs  = array_values(array_intersect($qualifiedDevs,  $freeDevs));

    return array('instructors' => $instr, 'devices' => $devs);
}
?>