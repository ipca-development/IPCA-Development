<?php
require_once __DIR__.'/_init.php';  // your PDO+auth bootstrap
if($_SERVER['REQUEST_METHOD']==='POST'){
  foreach($_POST as $k=>$v){ if($k==='save')continue;
    q('REPLACE INTO ai_settings (k,v) VALUES (?,?)',array($k,trim($v)));
  }
  header('Location: ai_settings.php?saved=1');exit;
}
$rows=q('SELECT k,v FROM ai_settings ORDER BY k')->fetchAll(PDO::FETCH_KEY_PAIR);
function val($k,$d){global $rows;return isset($rows[$k])?$rows[$k]:$d;}
?>
<!DOCTYPE html><html><head><meta charset="utf-8">
<title>AI Scheduler Settings</title>
<style>
body{font:14px/1.4 -apple-system,Segoe UI,Arial;padding:20px;max-width:760px;margin:auto}
label{display:block;margin:10px 0 4px}input{width:320px;padding:6px}
</style></head><body>
<h2>AI Scheduler Settings</h2>
<?php if(isset($_GET['saved'])) echo '<div style="color:green">Saved.</div>'; ?>
<form method="post">
  <label>Default location (US/BE)</label>
  <input name="default_location" value="<?php echo htmlspecialchars(val('default_location','US')); ?>">

  <label>Duration Briefing (min)</label>
  <input name="duration_minutes_briefing" value="<?php echo htmlspecialchars(val('duration_minutes_briefing','60')); ?>">

  <label>Duration Simulator (min)</label>
  <input name="duration_minutes_simulator" value="<?php echo htmlspecialchars(val('duration_minutes_simulator','90')); ?>">

  <label>Duration Flight (min)</label>
  <input name="duration_minutes_flight" value="<?php echo htmlspecialchars(val('duration_minutes_flight','120')); ?>">

  <label>Min gap between sessions (min)</label>
  <input name="min_gap_minutes_between_sessions" value="<?php echo htmlspecialchars(val('min_gap_minutes_between_sessions','10')); ?>">

  <label>Load-balance Instructors (YES/NO)</label>
  <input name="load_balance_instructors" value="<?php echo htmlspecialchars(val('load_balance_instructors','YES')); ?>">

  <label>Load-balance Devices (YES/NO)</label>
  <input name="load_balance_devices" value="<?php echo htmlspecialchars(val('load_balance_devices','YES')); ?>">

  <label>Max Duty Time in 24 h (hrs)</label>
  <input name="max_duty_hours_24h" value="<?php echo htmlspecialchars(val('max_duty_hours_24h','12')); ?>">

  <label>Max Flight Instruction (airplane) in 24 h (hrs)</label>
  <input name="max_flight_instruction_hours_24h" value="<?php echo htmlspecialchars(val('max_flight_instruction_hours_24h','8')); ?>">

  <p><button name="save" value="1">Save Settings</button></p>
</form>
</body></html>