<?php
// ===================================================================
// AI Scheduler Settings Helper (PHP 5.3 compatible)
// Reads/writes simple k/v from ai_settings with memoization.
// ===================================================================

if (!defined('AI_SCHEDULER_SETTINGS_HELPER')) define('AI_SCHEDULER_SETTINGS_HELPER', 1);

if (!function_exists('ai_settings_get_raw')) {
  function ai_settings_get_raw($key, $default) {
    static $cache = null;
    if ($cache === null) {
      $cache = array();
      try {
        $rows = q('SELECT k,v FROM ai_settings')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $cache[$r['k']] = $r['v'];
      } catch (Exception $e) {}
    }
    return isset($cache[$key]) ? $cache[$key] : $default;
  }
}

if (!function_exists('ai_setting')) {
  function ai_setting($k,$d){ return (string)ai_settings_get_raw($k,$d); }
}
if (!function_exists('ai_setting_int')) {
  function ai_setting_int($k,$d){ return (int)ai_settings_get_raw($k,$d); }
}
if (!function_exists('ai_setting_yes')) {
  function ai_setting_yes($k,$d){
    $v=strtoupper(trim(ai_settings_get_raw($k,$d)));
    return ($v==='YES'||$v==='Y'||$v==='1'||$v==='TRUE');
  }
}

// ---------------- Duration helpers ----------------
if (!function_exists('ai_duration_for_type_min')) {
  function ai_duration_for_type_min($t,$fallback){
    $t=strtoupper(trim($t));
    if($t==='BRIEFING')  return ai_setting_int('duration_minutes_briefing',60);
    if($t==='SIMULATOR') return ai_setting_int('duration_minutes_simulator',90);
    if($t==='FLIGHT')    return ai_setting_int('duration_minutes_flight',120);
    return (int)$fallback;
  }
}

// ---------------- Duty & flight-time limits ----------------
if (!function_exists('ai_minutes_to_hours')) {
  function ai_minutes_to_hours($m){ return round($m/60.0,1); }
}
if (!function_exists('ai_max_duty_hours')) {
  function ai_max_duty_hours(){ return ai_setting_int('max_duty_hours_24h',12); }
}
if (!function_exists('ai_max_flight_instr_hours')) {
  function ai_max_flight_instr_hours(){ return ai_setting_int('max_flight_instruction_hours_24h',8); }
}

// Compute instructor duty/flight time in trailing 24 h
if (!function_exists('ai_instructor_recent_hours')) {
  function ai_instructor_recent_hours($iid,$refStart,$refEnd){
    $rows=q("
      SELECT res_type, TIMESTAMPDIFF(MINUTE,start_dt,end_dt) AS mins
      FROM (
        SELECT res_type,start_dt,end_dt FROM reservations
          WHERE instructor_user_id=:i
            AND end_dt>DATE_SUB(:e,INTERVAL 24 HOUR) AND start_dt<:e
        UNION ALL
        SELECT res_type,start_dt,end_dt FROM reservation_drafts
          WHERE instructor_user_id=:i
            AND end_dt>DATE_SUB(:e,INTERVAL 24 HOUR) AND start_dt<:e
      )x
    ",array(':i'=>$iid,':e'=>$refEnd))->fetchAll(PDO::FETCH_ASSOC);

    $duty=0;$flight=0;
    foreach($rows as $r){
      $m=(int)$r['mins']; $duty+=$m;
      if(stripos($r['res_type'],'Flight')!==false) $flight+=$m;
    }
    return array('duty_hr'=>ai_minutes_to_hours($duty),
                 'flight_hr'=>ai_minutes_to_hours($flight));
  }
}
?>