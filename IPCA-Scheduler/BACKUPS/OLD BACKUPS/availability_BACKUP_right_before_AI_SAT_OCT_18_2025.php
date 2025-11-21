<?php
// ===================================================================
// A-01 — PHP 5.3: Header, DB, helpers (standalone)
// ===================================================================
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

$DB_HOST = 'mysql056.hosting.combell.com';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$TZ      = 'America/Los_Angeles';
date_default_timezone_set($TZ);

function db(){ static $pdo=null,$i=false; global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS; if($i) return $pdo; $i=true;
  try{ $pdo=new PDO('mysql:host='.$DB_HOST.';dbname='.$DB_NAME, $DB_USER, $DB_PASS);
       $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
       $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
       $pdo->exec('SET NAMES utf8'); }catch(Exception $e){ $pdo=null; } return $pdo; }
function q($sql,$a=array()){ $st=db()->prepare($sql); $st->execute($a); return $st; }
function jexit($a){ if(!headers_sent()){ header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); } echo json_encode($a); exit; }
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===================================================================
// A-02 — PHP: API
// ===================================================================
if (isset($_GET['api'])) {
  @ini_set('display_errors','0'); @error_reporting(E_ERROR | E_PARSE);
  $api = $_GET['api'];

  // Load current mode + rules
  if ($api==='avail_load') {
    $uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if(!$uid) jexit(array('ok'=>false,'error'=>'Missing user_id'));
    $modeRow = q('SELECT mode FROM user_availability_mode WHERE user_id=?', array($uid))->fetch();
    $mode = $modeRow ? $modeRow['mode'] : 'unavailability';
    $rules = q('SELECT * FROM user_availability_rules WHERE user_id=? ORDER BY FIELD(day_of_week,"Mon","Tue","Wed","Thu","Fri","Sat","Sun"), start_time', array($uid))->fetchAll();
    jexit(array('ok'=>true,'mode'=>$mode,'rules'=>$rules));
  }

  // Save mode
  if ($api==='avail_set_mode') {
    $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
    $uid = isset($in['user_id']) ? (int)$in['user_id'] : 0;
    $mode = isset($in['mode']) && ($in['mode']==='availability' || $in['mode']==='unavailability') ? $in['mode'] : 'unavailability';
    if(!$uid) jexit(array('ok'=>false,'error'=>'Missing user_id'));
    try{
      $ex = q('SELECT user_id FROM user_availability_mode WHERE user_id=?', array($uid))->fetch();
      if($ex){ q('UPDATE user_availability_mode SET mode=?, updated_at=NOW() WHERE user_id=?', array($mode,$uid)); }
      else   { q('INSERT INTO user_availability_mode (user_id,mode,updated_at) VALUES (?,?,NOW())', array($uid,$mode)); }
      jexit(array('ok'=>true));
    }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }


// Create/update rule (validated + merges overlaps on same day/range)
if ($api==='avail_save_rule') {
  $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
  $id   = isset($in['id']) ? (int)$in['id'] : 0;
  $uid  = isset($in['user_id']) ? (int)$in['user_id'] : 0;
  $dow  = isset($in['day_of_week']) ? trim($in['day_of_week']) : '';
  $st   = isset($in['start_time']) ? trim($in['start_time']) : '';
  $et   = isset($in['end_time']) ? trim($in['end_time']) : '';
  if(!$uid || !$dow || !$st || !$et) jexit(array('ok'=>false,'error'=>'Missing fields'));

  // Normalize time to HH:MM:SS
  $norm = function($t){
    $t = substr($t,0,5);
    if(!preg_match('/^\d{2}:\d{2}$/',$t)) return null;
    return $t.':00';
  };
  $st = $norm($st); $et = $norm($et);
  if(!$st || !$et) jexit(array('ok'=>false,'error'=>'Invalid time format'));
  if($st >= $et)   jexit(array('ok'=>false,'error'=>'Start must be before end'));

  $sd   = isset($in['start_date']) && $in['start_date']!=='' ? $in['start_date'] : null;
  $ed   = isset($in['end_date'])   && $in['end_date']!==''   ? $in['end_date']   : null;
  $rep  = !empty($in['repeat_weekly']) ? 1 : 0;
  $note = isset($in['note']) ? $in['note'] : null;

  try{
    // 1) Exact duplicate guard (same window & same scope)
    $dup = q('SELECT id FROM user_availability_rules
              WHERE user_id=? AND day_of_week=? AND start_time=? AND end_time=? AND
                    IFNULL(start_date,\'\') <=> IFNULL(?,\'\') AND
                    IFNULL(end_date,\'\')   <=> IFNULL(?,\'\')   AND
                    repeat_weekly=?' . ($id?' AND id<>'.(int)$id:''), 
              array($uid,$dow,$st,$et,$sd,$ed,$rep))->fetch();
    if($dup){ jexit(array('ok'=>true,'id'=>$dup['id'])); }

    // 2) Merge overlaps on the same day/range
    // Overlap if (A.start < B.end) AND (B.start < A.end)
    $over = q('SELECT * FROM user_availability_rules
               WHERE user_id=? AND day_of_week=? AND repeat_weekly=? AND
                     IFNULL(start_date,\'\') <=> IFNULL(?,\'\') AND
                     IFNULL(end_date,\'\')   <=> IFNULL(?,\'\')   AND
                     NOT (end_time<=? OR start_time>=?)' . ($id?' AND id<>'.(int)$id:''), 
              array($uid,$dow,$rep,$sd,$ed,$st,$et))->fetchAll();

    $minSt = $st; $maxEt = $et; $idsToRemove = array();
    foreach($over as $r){
      if($r['start_time'] < $minSt) $minSt = $r['start_time'];
      if($r['end_time']   > $maxEt) $maxEt = $r['end_time'];
      $idsToRemove[] = (int)$r['id'];
    }

    if($id){
      // We are updating an existing rule: expand to merged window
      q('UPDATE user_availability_rules
         SET day_of_week=?, start_time=?, end_time=?, start_date=?, end_date=?, repeat_weekly=?, note=?, updated_at=NOW()
         WHERE id=? AND user_id=?',
        array($dow,$minSt,$maxEt,$sd,$ed,$rep,$note,$id,$uid));
      // Remove other overlappers
      if(count($idsToRemove)){
        q('DELETE FROM user_availability_rules WHERE user_id=? AND id IN ('.implode(',', $idsToRemove).')', array($uid));
      }
      jexit(array('ok'=>true,'id'=>$id));
    } else {
      // Create merged one
      q('INSERT INTO user_availability_rules
         (user_id,day_of_week,start_time,end_time,start_date,end_date,repeat_weekly,note,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())',
        array($uid,$dow,$minSt,$maxEt,$sd,$ed,$rep,$note));
      $newId = db()->lastInsertId();
      if(count($idsToRemove)){
        q('DELETE FROM user_availability_rules WHERE user_id=? AND id IN ('.implode(',', $idsToRemove).')', array($uid));
      }
      jexit(array('ok'=>true,'id'=>$newId));
    }
  }catch(Exception $e){
    jexit(array('ok'=>false,'error'=>$e->getMessage()));
  }
}
	
	

  // Delete rule
  if ($api==='avail_delete_rule') {
    $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
    $id  = isset($in['id']) ? (int)$in['id'] : 0;
    $uid = isset($in['user_id']) ? (int)$in['user_id'] : 0;
    if(!$id||!$uid) jexit(array('ok'=>false,'error'=>'Missing id/user_id'));
    try{ q('DELETE FROM user_availability_rules WHERE id=? AND user_id=?', array($id,$uid)); jexit(array('ok'=>true)); }
    catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

// ---- list active users for the picker ----
if ($api==='avail_users') {
  try{
    $rows = q('SELECT userid AS id, voornaam AS first_name, naam AS last_name
               FROM users
               WHERE actief_tot<>\'0000-00-00\' AND actief_tot>=CURDATE()
               ORDER BY voornaam, naam')->fetchAll();
    jexit(array('ok'=>true,'users'=>$rows));
  }catch(Exception $e){
    jexit(array('ok'=>false,'error'=>$e->getMessage()));
  }
}	
	
  exit;
}

// ===================================================================
// A-03 — HTML: Availability UI (simple weekly editor)
// ===================================================================
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0; // pass ?user_id=
?><!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Availability</title>
<style>
  /* Base */
  body{
    margin:0;
    background:#eef1f6;
    font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Arial;
    color:#1a1f36;
  }
 .top{
  background:linear-gradient(90deg,#1e3c72,#2a5298);
  color:#fff;
  padding:10px 16px;
  font-weight:700;
  display:flex;                 /* NEW */
  align-items:center;           /* NEW */
  justify-content:space-between;/* NEW */
}
	
/* Back-to-schedule pill */
.backBtn{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 14px;
  border-radius:12px;
  border:1px solid rgba(255,255,255,.55);
  color:#fff;
  text-decoration:none;
  background:rgba(255,255,255,.12);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.08);
  transition: background .15s ease, border-color .15s ease, transform .02s ease;
}
.backBtn:hover{
  background:rgba(255,255,255,.18);
  border-color:#fff;
}
.backBtn:active{ transform: translateY(1px); }	
	
  .wrap{ padding:12px 16px; }

  /* Cards */
  .card{
    background:#fff;
    border:1px solid #dde3f0;
    border-radius:12px;
    padding:12px;
  }

  /* Layout rows that DON'T overflow */
  .row{
    display:flex;
    gap:12px;
    flex-wrap:wrap;                 /* allow inputs to wrap instead of overflowing */
  }
  .col{
    flex:1 1 220px;                 /* grow, shrink, min ~220px per field */
    min-width:220px;
  }
  .col.col-actions{
    flex:0 0 220px;                 /* keep action buttons tidy on the right */
    display:flex;
    align-items:flex-end;
  }

  /* Inputs */
  input,select{
    width:100%;
    box-sizing:border-box;          /* include padding/border in width */
    padding:8px;
    border:1px solid #cfd5e3;
    border-radius:8px;
  }

  /* Tables */
  table{ width:100%; border-collapse:collapse; margin-top:10px; }
  th,td{ padding:10px 12px; border-bottom:1px solid #e5e9f2; }
  th{ text-align:left; font-weight:600; color:#1f2937; }

  /* Buttons */
  .btn{
    padding:8px 12px;
    border-radius:8px;
    border:1px solid #1e3c72;
    background:#1e3c72;
    color:#fff;
    cursor:pointer;
  }
  .btn.secondary{
    background:#f3f6fb;
    color:#1a1f36;
    border-color:#cfd5e3;
  }

  /* Tiny badge (unchanged) */
  .pill{
    display:inline-block;
    background:#eef2ff;
    border:1px solid #cfe3ff;
    color:#244f87;
    border-radius:999px;
    padding:2px 8px;
    font-size:12px;
  }

  /* Small screens: stack nicely */
  @media (max-width: 720px){
    .col{ flex:1 1 100%; min-width:260px; }
    .col.col-actions{ flex:1 1 100%; }
  }
</style>
</head><body>
<div class="top">
  <div>Availability — <span id="who">User #<?php echo (int)$user_id; ?></span></div>
  <a class="backBtn" href="schedule.php">← Back to Schedule</a>
</div>

<div class="wrap">

  <!-- Choose which user to edit -->
  <div class="card" style="margin-bottom:12px">
    <div class="row">
      <div class="col">
        <label>Manage Availability For
          <select id="userSel">
            <option value="">Loading…</option>
          </select>
        </label>
      </div>
      <div class="col col-actions">
        <button class="btn" id="switchUser">Load</button>
      </div>
    </div>
  </div>	
	
  <!-- Mode card -->
  <div class="card">
    <div class="row">
      <div class="col">
        <label>Mode (how to interpret blocks)
          <select id="modeSel">
            <option value="availability">Providing Availability (all other time = unavailable)</option>
            <option value="unavailability">Providing Unavailability (all other time = available)</option>
          </select>
        </label>
      </div>
      <div class="col col-actions">
        <button class="btn" id="saveMode">Save Mode</button>
      </div>
    </div>
  </div>

  <!-- Rule editor -->
  <div class="card" style="margin-top:12px">
    <div class="row">
      <div class="col">
        <label>Day of Week
          <select id="dow">
            <option>Mon</option><option>Tue</option><option>Wed</option>
            <option>Thu</option><option>Fri</option><option>Sat</option><option>Sun</option>
          </select>
        </label>
      </div>

      <div class="col">
        <label>Start Time
          <input type="time" id="st" value="08:00">
        </label>
      </div>

      <div class="col">
        <label>End Time
          <input type="time" id="et" value="12:00">
        </label>
      </div>

      <div class="col">
        <label>Effective From
          <input type="date" id="sd">
        </label>
      </div>

      <div class="col">
        <label>Effective To
          <input type="date" id="ed">
        </label>
      </div>

      <div class="col">
        <label>Repeats Weekly
          <select id="rep">
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </label>
      </div>

      <div class="col col-actions">
        <button class="btn" id="addRule">Add / Update Rule</button>
      </div>
    </div>

    <input type="hidden" id="rule_id" value="">
  </div>

  <!-- Rules table -->
  <div class="card" style="margin-top:12px">
    <h3 style="margin:0 0 8px 0">Rules</h3>
    <table id="rulesTable">
      <thead>
        <tr>
          <th>Day</th>
          <th>Time</th>
          <th>Range</th>
          <th>Repeat</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>

</div>

<script>
var USER_ID = <?php echo (int)$user_id; ?>;
function api(url, body, cb){
  var opt = { method: body?'POST':'GET' };
  if (body){ opt.headers={'Content-Type':'application/json'}; opt.body=JSON.stringify(body); }
  fetch(url,opt).then(function(r){return r.json();}).then(cb||function(){});
}
	
	
// --- UX helpers: live validate + row click to edit
(function(){
  var st = document.getElementById('st');
  var et = document.getElementById('et');
  var btn = document.getElementById('addRule');

  function hhmmToMin(s){ if(!s) return NaN; var h=+s.substr(0,2), m=+s.substr(3,2); return h*60+m; }
  function validate(){
    var ok = !!st.value && !!et.value && hhmmToMin(st.value) < hhmmToMin(et.value);
    btn.disabled = !ok;
  }
  st.addEventListener('input', function(){
    // If end empty or <= start, nudge to +60 min (clamped)
    var s = hhmmToMin(st.value), e = hhmmToMin(et.value);
    if(!et.value || isNaN(e) || e<=s){
      var n = Math.min(23*60+59, s+60);
      var hh = ('0'+Math.floor(n/60)).slice(-2), mm=('0'+(n%60)).slice(-2);
      et.value = hh+':'+mm;
    }
    validate();
  });
  et.addEventListener('input', validate);
  validate();

  // Delegate click on any rules row to edit
  document.addEventListener('click', function(ev){
    var tr = ev.target && ev.target.closest && ev.target.closest('#rulesTable tbody tr');
    if(!tr) return;
    var editBtn = tr.querySelector('[data-edit]');
    if(editBtn && !ev.target.hasAttribute('data-del')) editBtn.click();
  }, true);
})();	
	
var USER_ID = <?php echo (int)$user_id; ?>;

// Small helper to update the title with the selected user name/id
function setHeaderName(name, id){
  var who = document.getElementById('who');
  if (!who) return;
  if (name && name.trim()){
    who.textContent = name + ' (ID ' + id + ')';
  } else {
    who.textContent = 'User #' + id;
  }
}

// Load user list into the picker, select current USER_ID (or first) and update header
function loadUsersList(){
  fetch('?api=avail_users')
    .then(function(r){ return r.json(); })
    .then(function(js){
      var sel = document.getElementById('userSel');
      if (!sel) return;
      sel.innerHTML = '';

      if (!js.ok || !js.users || !js.users.length){
        var opt = document.createElement('option');
        opt.value = '';
        opt.text = 'No active users';
        sel.appendChild(opt);
        setHeaderName('', USER_ID||'');
        return;
      }

      var chosenIndex = 0;
      for (var i=0;i<js.users.length;i++){
        var u = js.users[i];
        var opt = document.createElement('option');
        opt.value = String(u.id);
        opt.text  = ((u.first_name||'') + ' ' + (u.last_name||'')).replace(/\s+/g,' ').trim();
        sel.appendChild(opt);
        if (USER_ID && String(u.id) === String(USER_ID)) chosenIndex = i;
      }
      sel.selectedIndex = chosenIndex;

      var cur = js.users[chosenIndex];
      setHeaderName(((cur.first_name||'')+' '+(cur.last_name||'')).trim(), cur.id);

      // If there was no user in the URL, adopt the first and load data
      if (!USER_ID && cur && cur.id){
        USER_ID = parseInt(cur.id,10);
        loadAll();
      }
    })
    .catch(function(){
      var sel = document.getElementById('userSel');
      if (sel){ sel.innerHTML = '<option value="">Load failed</option>'; }
      setHeaderName('', USER_ID||'');
    });
}

// Handle switching the selected user
document.addEventListener('click', function(e){
  if (e.target && e.target.matches('#switchUser')){
    var sel = document.getElementById('userSel');
    if (!sel || !sel.value){ alert('Pick a user first.'); return; }
    USER_ID = parseInt(sel.value, 10) || 0;

    // Update header with the currently selected option’s text
    var label = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    setHeaderName(label, USER_ID);

    // Reload availability data for the new user
    loadAll();
  }
});	
	
function loadAll(){
  api('?api=avail_load&user_id='+encodeURIComponent(USER_ID), null, function(js){
    if(!js.ok){ alert('Load failed'); return; }
    document.getElementById('modeSel').value = js.mode || 'unavailability';
    var tb = document.getElementById('rulesTable').querySelector('tbody'); tb.innerHTML='';
    var rows = js.rules||[];
    for(var i=0;i<rows.length;i++){
      var r=rows[i], tr=document.createElement('tr');
      tr.innerHTML =
  '<td>'+r.day_of_week+'</td>'+
  '<td>'+r.start_time.substr(0,5)+' – '+r.end_time.substr(0,5)+'</td>'+
  '<td>'+((r.start_date&&r.start_date!=='0000-00-00')?r.start_date:'…')+' → '+((r.end_date&&r.end_date!=='0000-00-00')?r.end_date:'∞')+'</td>'+
  '<td>'+(r.repeat_weekly?'Weekly':'One-off')+'</td>'+
  '<td><button class="btn secondary" data-edit="'+r.id+'">Edit</button> '+
      '<button class="btn secondary" data-del="'+r.id+'">Delete</button></td>';
      tb.appendChild(tr);
    }
  });
}
document.addEventListener('click', function(e){
  if(e.target && e.target.matches('#saveMode')){
    api('?api=avail_set_mode', { user_id:USER_ID, mode: document.getElementById('modeSel').value }, function(js){
      if(js.ok) alert('Mode saved'); else alert('Error: '+(js.error||'save failed'));
    });
  }

if(e.target && e.target.matches('#addRule')){
  var payload={
    id: parseInt(document.getElementById('rule_id').value||'0',10)||0,
    user_id: USER_ID,
    day_of_week: document.getElementById('dow').value,
    start_time: document.getElementById('st').value,
    end_time: document.getElementById('et').value,
    start_date: document.getElementById('sd').value||null,
    end_date: document.getElementById('ed').value||null,
    repeat_weekly: document.getElementById('rep').value==='1'?1:0
  };
  api('?api=avail_save_rule', payload, function(js){
    if(js.ok){
      document.getElementById('rule_id').value='';
      loadAll();
      alert('Rule saved.');   // <-- confirmation
    }else{
      alert('Error: '+(js.error||'save failed'));
    }
  });
}	
	
	
  if(e.target && e.target.getAttribute('data-del')){
    var id=parseInt(e.target.getAttribute('data-del'),10);
    if(!confirm('Delete this rule?')) return;
    api('?api=avail_delete_rule', { id:id, user_id:USER_ID }, function(js){
      if(js.ok) loadAll(); else alert('Error: '+(js.error||'delete failed'));
    });
  }
  if(e.target && e.target.getAttribute('data-edit')){
    var id=parseInt(e.target.getAttribute('data-edit'),10);
    // quick fetch via load data already in table: for demo, we reload then pick
    fetch('?api=avail_load&user_id='+encodeURIComponent(USER_ID)).then(function(r){return r.json();}).then(function(js){
      var rows=js.rules||[]; 
      for(var i=0;i<rows.length;i++){ var r=rows[i]; if(parseInt(r.id,10)===id){
        document.getElementById('rule_id').value=r.id;
        document.getElementById('dow').value=r.day_of_week;
        document.getElementById('st').value=r.start_time;
        document.getElementById('et').value=r.end_time;
        document.getElementById('sd').value=r.start_date||'';
        document.getElementById('ed').value=r.end_date||'';
        document.getElementById('rep').value=r.repeat_weekly?'1':'0';
        break;
      }}
    });
  }
});
loadUsersList();
loadAll();
</script>
</body></html>