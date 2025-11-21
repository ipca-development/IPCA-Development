<?php
/* ===============================================================
   IPCA Scheduler — Day View
   - Logo + SoCal name + 05–23 window
   - Vertical divider
   - Sample reservation on N446CS 10:00–14:00
   - Matching instructor pill (Kay Vereeken) 10:00–14:00
   - Centered large human-readable date in header
   - Tooltip hover for reservation pills
   =============================================================== */

ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

/* --------- CONFIG --------- */
$DB_HOST = 'mysql056.hosting.combell.com';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$TZ      = 'America/Los_Angeles';
date_default_timezone_set($TZ);

/* Time window (?hstart=&hend= overrides) */
$H_START = isset($_GET['hstart']) ? max(0, min(23, (int)$_GET['hstart'])) : 5;   // 05:00
$H_END   = isset($_GET['hend'])   ? max($H_START+1, min(24, (int)$_GET['hend'])) : 23; // 23:00

/* Left column width */
$LABEL_W = 240;

/* Location for sun lines (Thermal, CA) */
$LOC_NAME = 'SoCal Pilot Center – California (#SPCS024M)';
$LOC_LAT  = 33.6409;
$LOC_LON  = -116.1597;

/* Column maps (adjust if schema differs) */
$COLMAP_DEVICES = array(
  'table'=>'devices','id'=>'dev_id','code'=>'dev_code','name'=>'dev_name',
  'type'=>'dev_type','location'=>'dev_location','active'=>'dev_active','status'=>'dev_status'
);
$COLMAP_USERS = array(
  'table'=>'users','id'=>'id','fname'=>'first_name','lname'=>'last_name','role'=>'role','active'=>'active'
);
$COLMAP_EVENTS = array(
  'table'=>'events','id'=>'id','start'=>'start_dt','end'=>'end_dt','title'=>'title','notes'=>'notes',
  'device_id'=>'resource_dev_id','student_id'=>'student_id','instr_id'=>'instructor_user_id',
  'modality'=>'modality','role'=>'instruction_role','minutes'=>'expected_minutes','status'=>'status'
);
$TBL_BLOCKS='resource_blackouts';

/* --------- DB helpers --------- */
function db(){
  static $pdoInit=false,$pdo=null; global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS;
  if($pdoInit) return $pdo; $pdoInit=true;
  try{
    $pdo=new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8",$DB_USER,$DB_PASS,array(
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ));
  }catch(Exception $e){ $pdo=null; }
  return $pdo;
}
function q($sql,$args=array()){ $pdo=db(); if(!$pdo) throw new Exception('DB unavailable'); $st=$pdo->prepare($sql); $st->execute($args); return $st; }
function jexit($arr){ if(!headers_sent()){ header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); } echo json_encode($arr); exit; }
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function day_bounds($date){ $d=date('Y-m-d', strtotime($date)); return array("$d 00:00:00","$d 23:59:59"); }

/* --------- API --------- */
if(isset($_GET['api'])){
  $api=$_GET['api'];

  if($api==='list_day'){
    $date = isset($_GET['date'])? $_GET['date'] : date('Y-m-d');
    list($start,$end)=day_bounds($date);

    // Placeholders (9 devices + 4 staff)
    $PLACEHOLDER_DEVICES = array(
      array('dev_id'=>1,'dev_code'=>'N392EA','dev_name'=>'N392EA','dev_type'=>'aircraft','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>2,'dev_code'=>'N397EA','dev_name'=>'N397EA','dev_type'=>'aircraft','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>3,'dev_code'=>'N446CS','dev_name'=>'N446CS','dev_type'=>'aircraft','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>4,'dev_code'=>'AL172-M2','dev_name'=>'AL172 M2','dev_type'=>'sim','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>5,'dev_code'=>'AVP-I','dev_name'=>'Apple Vision Pro I','dev_type'=>'ar','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>6,'dev_code'=>'AVP-II','dev_name'=>'Apple Vision Pro II','dev_type'=>'ar','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>7,'dev_code'=>'CLS-I','dev_name'=>'Classroom I','dev_type'=>'classroom','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>8,'dev_code'=>'CLS-2','dev_name'=>'Classroom 2','dev_type'=>'classroom','dev_status'=>'ok','dev_active'=>1),
      array('dev_id'=>9,'dev_code'=>'MAIN-OFFICE','dev_name'=>'Main Office','dev_type'=>'office','dev_status'=>'ok','dev_active'=>1),
    );
    $PLACEHOLDER_STAFF = array(
      array('id'=>101,'first_name'=>'Maria','last_name'=>'Paz-Vereeken','role'=>'CEO','active'=>1),
      array('id'=>102,'first_name'=>'Kay','last_name'=>'Vereeken','role'=>'Instructor','active'=>1),
      array('id'=>103,'first_name'=>'John','last_name'=>'Doe','role'=>'Instructor','active'=>1),
      array('id'=>104,'first_name'=>'Unknown','last_name'=>'','role'=>'Instructor','active'=>1),
    );

    $devices=$staff=$events=$blocks=array();
    try{ $devices=q("SELECT * FROM `{$COLMAP_DEVICES['table']}` WHERE `{$COLMAP_DEVICES['active']}`=1 ORDER BY {$COLMAP_DEVICES['type']}, {$COLMAP_DEVICES['name']}")->fetchAll(); }catch(Exception $e){}
    try{ $staff  =q("SELECT * FROM `{$COLMAP_USERS['table']}` WHERE `{$COLMAP_USERS['active']}`=1 AND `{$COLMAP_USERS['role']}` IN ('instructor','admin','COO','Instructor') ORDER BY {$COLMAP_USERS['lname']}, {$COLMAP_USERS['fname']}")->fetchAll(); }catch(Exception $e){}
    try{ $events =q("SELECT * FROM `{$COLMAP_EVENTS['table']}` WHERE {$COLMAP_EVENTS['start']}<=? AND {$COLMAP_EVENTS['end']}>? ORDER BY {$COLMAP_EVENTS['start']}", array($end,$start))->fetchAll(); }catch(Exception $e){}
    try{ $blocks =q("SELECT * FROM `$TBL_BLOCKS` WHERE start_dt<=? AND end_dt>?", array($end,$start))->fetchAll(); }catch(Exception $e){}

    // Sunrise/Sunset
    $ts=strtotime($date.' 12:00:00');
    $sun=function_exists('date_sun_info') ? @date_sun_info($ts,$LOC_LAT,$LOC_LON) : false;
    $sunrise = ($sun && isset($sun['sunrise'])) ? date('Y-m-d H:i:s',$sun['sunrise']) : null;
    $sunset  = ($sun && isset($sun['sunset']))  ? date('Y-m-d H:i:s',$sun['sunset'])  : null;

    if(!$devices || !count($devices)) $devices=$PLACEHOLDER_DEVICES;
    if(!$staff   || !count($staff))   $staff  =$PLACEHOLDER_STAFF;

    jexit(array(
      'date'=>$date,'hstart'=>$H_START,'hend'=>$H_END,
      'sunrise'=>$sunrise,'sunset'=>$sunset,
      'location'=>array('name'=>$LOC_NAME,'lat'=>$LOC_LAT,'lon'=>$LOC_LON),
      'devices'=>$devices,'staff'=>$staff,'events'=>$events,'blocks'=>$blocks
    ));
  }

  if($api==='whoami'){ jexit(array('user'=>array('name'=>'Kay V','role'=>'admin'))); }
  exit;
}

/* --------- Page vars --------- */
$date       = isset($_GET['date'])? $_GET['date'] : date('Y-m-d');
$today      = date('Y-m-d');
$date_long  = date('l, F j, Y', strtotime($date)); // Sunday, October 5, 2025
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IPCA Scheduler – Day View</title>
<style>
  :root{
    --ipca-blue:#1e3c72; --ipca-blue2:#2a5298; --bg:#eef1f6; --grid:#e2e6f0; --divider:#d8dbe2;
    --text:#1a1f36; --muted:#7a8599; --danger:#d90429;

    /* Reservation colors */
    --resv-bg:#b7d2ff;   /* lighter blue */
    --resv-fg:#0c2b5a;
    --resv-border:#8fb7ff;

    /* Tooltip */
    --tip-bg:#0f172a; --tip-fg:#fff; --tip-border:#1f2a44;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Arial}

  /* Top bar with logo + location text */
  .topbar{
    background:linear-gradient(90deg,var(--ipca-blue),var(--ipca-blue2));
    color:#fff; display:flex; align-items:center; gap:12px; padding:10px 16px
  }
  .brand{display:flex; align-items:center; gap:10px; font-weight:600}
  .brand img.logo{height:24px; width:auto; display:block}
  .btn{background:#ffffff22;border:1px solid #ffffff30;color:#fff;padding:6px 10px;border-radius:8px;cursor:pointer}
  .btn:hover{background:#ffffff33}
  .ghost{background:transparent;border:1px solid #ffffff55}
  .menu{margin-left:auto;display:flex;gap:8px}

  .wrap{padding:8px 16px}
  .card{background:#fff;border:1px solid #dde3f0;border-radius:14px;overflow:hidden}

  /* Header (two real columns) */
  .timeHeader{display:flex;border-bottom:1px solid var(--grid);background:#fafbff}
  .hLeft{width:<?php echo (int)$LABEL_W; ?>px;flex:0 0 <?php echo (int)$LABEL_W; ?>px;border-right:1px solid var(--divider)}
  .hRight{flex:1 1 auto;display:grid;grid-template-columns:repeat(<?php echo (int)($H_END-$H_START); ?>,1fr)}
  .hRight div{padding:10px 0;text-align:center;color:var(--muted);font-weight:600;border-left:1px solid var(--grid)}
  .hRight div:first-child{border-left:none}

  /* BODY GRID — rows aligned */
  .rowsGrid{
    position:relative;
    display:grid;
    grid-template-columns: <?php echo (int)$LABEL_W; ?>px 1fr;
    grid-auto-rows: 44px;
    background:#fff;
    border-top:1px solid var(--divider);
    border-bottom:1px solid var(--divider);
  }
  .sectionLabel{grid-column:1; display:flex; align-items:center; padding:0 10px; font-weight:700; background:#f3f5fb; border-top:1px solid var(--divider); border-bottom:1px solid var(--divider);}
  .sectionSpacer{grid-column:2; background:#f3f5fb; border-top:1px solid var(--divider); border-bottom:1px solid var(--divider); border-left:1px solid var(--divider);} /* divider in header rows */

  .rlabel{grid-column:1; display:flex; align-items:center; padding:0 10px; border-bottom:1px solid var(--divider); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; background:#fff; z-index:2}
  .rcell{grid-column:2; position:relative; border-bottom:1px solid var(--divider); border-left:1px solid var(--divider); background:#fff} /* main vertical divider line */

  /* Reservation pill (slot) */
  .slot{
    position:absolute; top:6px; height:32px; border-radius:6px;
    display:flex; align-items:center; gap:8px; padding:0 10px;
    z-index:10; max-width:100%;
    border:1px solid var(--resv-border); background:var(--resv-bg); color:var(--resv-fg);
    box-shadow: 0 1px 0 rgba(0,0,0,.04);
    font-weight:600;

    /* Allow tooltip to escape, while keeping text ellipsis on inner span */
    overflow:visible;
  }
  .slot .slotText{
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:inline-block; max-width:100%;
  }

  /* Tooltip */
  .slot .tooltip{
    display:none;
    position:absolute; left:0; top:38px;
    background:var(--tip-bg); color:var(--tip-fg);
    padding:10px 12px; border-radius:8px; border:1px solid var(--tip-border);
    box-shadow:0 8px 24px rgba(2,6,23,.35); z-index:9999; min-width:260px;
    font-weight:500;
  }
  .slot .tooltip::after{
    content:''; position:absolute; top:-6px; left:14px; width:10px; height:10px;
    background:var(--tip-bg); border-left:1px solid var(--tip-border); border-top:1px solid var(--tip-border);
    transform:rotate(45deg);
  }
  .slot:hover .tooltip{ display:block; }

  /* Lines INSIDE right column only */
  #overlay{position:absolute; left:<?php echo (int)$LABEL_W; ?>px; right:0; top:0; bottom:0; pointer-events:none; z-index:4}
  .vline{position:absolute;top:0;bottom:0;width:2px;background:#e94141;opacity:.95}
  .vline.sun{background:#ffb703}

  .headerBar{display:flex;align-items:center;gap:12px;padding:12px 16px}
  .headerBar .dateWrap{display:flex;align-items:center;gap:10px}
  input[type=date]{padding:8px 10px;border:1px solid #cfd5e3;border-radius:8px}

  /* BIG centered date (2× size, dark blue, centered) */
  .dateTitle{
    flex:1;
    text-align:center;
    font-weight:800;
    font-size:28px;           /* ~2× base size */
    color:#1e3c72;            /* dark blue */
    letter-spacing:0.2px;
  }

  .spacer{flex:1}
  .foot{display:flex;justify-content:space-between;color:#7a8599;padding:10px 16px}
</style>
</head>
<body>
  <div class="topbar">
    <div class="brand">
      <img class="logo" src="img/IPCA.png" alt="IPCA">
      <span><?php echo safe($LOC_NAME); ?></span>
    </div>
    <button class="btn">☰ Schedule</button>
    <div class="menu">
      <button class="btn ghost" onclick="goToday()">Today</button>
      <button class="btn" onclick="navDay(-1)">←</button>
      <button class="btn" onclick="navDay(1)">→</button>
    </div>
    <div id="whoami" style="margin-left:12px;color:#e8eefc;"></div>
  </div>

  <div class="headerBar">
    <div class="dateWrap">
      <input type="date" id="pick" value="<?php echo safe($date); ?>" onchange="pickDate(this.value)">
    </div>
    <div id="dateHuman" class="dateTitle"><?php echo safe($date_long); ?></div>
    <div class="spacer"></div>
  </div>

  <div class="wrap">
    <div class="card">
      <!-- HEADER -->
      <div class="timeHeader">
        <div class="hLeft"></div>
        <div class="hRight" id="hRight"></div>
      </div>

      <!-- BODY GRID -->
      <div class="rowsGrid" id="rowsGrid">
        <div id="overlay"></div>
      </div>
    </div>
  </div>

  <div class="foot">
    <div id="clock"></div>
    <div></div>
  </div>

<script>
var H_START = <?php echo (int)$H_START; ?>;
var H_END   = <?php echo (int)$H_END; ?>;

function buildHourHeader(){
  var hr=document.getElementById('hRight'); hr.innerHTML='';
  for(var h=H_START; h<H_END; h++){
    var d=document.createElement('div'); d.textContent=(h<10?'0':'')+h+':00'; hr.appendChild(d);
  }
}
function timeToX(dt){
  var d=new Date(dt.replace(' ','T'));
  var mins=d.getHours()*60 + d.getMinutes();
  var dayStart=H_START*60, total=(H_END-H_START)*60;
  if(mins<dayStart) mins=dayStart; if(mins>dayStart+total) mins=dayStart+total;
  return (mins-dayStart)/total*100;
}
function minutesBetween(a,b){
  var aa=new Date(a.replace(' ','T')), bb=new Date(b.replace(' ','T'));
  return Math.max(0, Math.round((bb-aa)/60000));
}
function spanWidth(mins){
  var total=(H_END-H_START)*60;
  return (mins/total*100);
}
function esc(s){ return (s||'').toString().replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

var DATA=null;

function addSection(title){
  var grid=document.getElementById('rowsGrid');
  var L=document.createElement('div'); L.className='sectionLabel'; L.textContent=title;
  var R=document.createElement('div'); R.className='sectionSpacer';
  grid.appendChild(L); grid.appendChild(R);
}
function addRowLabel(text){
  var grid=document.getElementById('rowsGrid');
  var L=document.createElement('div'); L.className='rlabel'; L.innerHTML=text; grid.appendChild(L);
}
function addRowCell(childrenCb){
  var grid=document.getElementById('rowsGrid');
  var C=document.createElement('div'); C.className='rcell';
  if(typeof childrenCb==='function'){ childrenCb(C); }
  grid.appendChild(C);
}

function toLongDate(dStr){
  // Input: YYYY-MM-DD -> "Sunday, October 5, 2025"
  var d = new Date(dStr + 'T12:00:00');
  var opts = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
  return d.toLocaleDateString(undefined, opts);
}

function makeTooltipHtml(lines){
  return '<div class="tooltip">'+lines.map(esc).join('<br>')+'</div>';
}

function renderRows(){
  var grid=document.getElementById('rowsGrid'); grid.innerHTML='<div id="overlay"></div>';

  var date = (DATA && DATA.date) ? DATA.date : '<?php echo safe($date); ?>';
  var start = date + ' 10:00:00';
  var end   = date + ' 14:00:00';
  var leftPct = timeToX(start);
  var widthPct = spanWidth(minutesBetween(start,end));
  var detailsLines = [
    'Device: N446CS',
    'Instructor: Kay Vereeken',
    'Student: Lukas Vanderstraeten',
    'Mission: 1-1-2',
    'Time: 10:00 – 14:00'
  ];

  // DEVICES
  addSection('Devices');
  (DATA.devices||[]).forEach(function(d){
    var icon = d.dev_type==='aircraft' ? '✈️ ' :
               d.dev_type==='sim'      ? '🖥️ ' :
               d.dev_type==='classroom'? '🏫 ' :
               d.dev_type==='ar'       ? '🥽 ' :
               d.dev_type==='office'   ? '🏢 ' : '• ';
    addRowLabel(icon + (d.dev_name||''));
    addRowCell(function(cell){
      // Sample reservation only on N446CS, 10:00–14:00
      if ((d.dev_name||'') === 'N446CS') {
        var slot = document.createElement('div');
        slot.className = 'slot';
        slot.style.left = leftPct + '%';
        slot.style.width = widthPct + '%';

        // text with ellipsis
        var text = document.createElement('span');
        text.className = 'slotText';
        text.textContent = '10:00-14:00 • Lukas Vanderstraeten • 1-1-2';
        slot.appendChild(text);

        // tooltip
        slot.insertAdjacentHTML('beforeend', makeTooltipHtml(detailsLines));

        cell.appendChild(slot);
      }
    });
  });

  // STAFF
  addSection('Staff');
  (DATA.staff||[]).forEach(function(u){
    var label=((u.role?u.role+': ':'') + (u.first_name||'') + (u.last_name?' '+u.last_name:'')); 
    addRowLabel(label);
    addRowCell(function(cell){
      // If this is Kay Vereeken, add a matching occupied pill for 10:00–14:00
      var fn=(u.first_name||'').trim().toLowerCase(), ln=(u.last_name||'').trim().toLowerCase();
      if(fn==='kay' && ln==='vereeken'){
        var slot = document.createElement('div');
        slot.className = 'slot';
        slot.style.left = leftPct + '%';
        slot.style.width = widthPct + '%';

        var text = document.createElement('span');
        text.className = 'slotText';
        text.textContent = 'Busy: N446CS · 10:00–14:00';
        slot.appendChild(text);

        // tooltip
        slot.insertAdjacentHTML('beforeend', makeTooltipHtml([
          'Instructor: Kay Vereeken',
          'Device: N446CS',
          'Student: Lukas Vanderstraeten',
          'Mission: 1-1-2',
          'Time: 10:00 – 14:00'
        ]));

        cell.appendChild(slot);
      }
    });
  });

  drawLines();

  // Update the centered long date (in case API provided a different date)
  if (DATA && DATA.date){
    var human = toLongDate(DATA.date);
    var el = document.getElementById('dateHuman');
    if (el) el.textContent = human;
    var dp = document.getElementById('pick');
    if (dp && dp.value !== DATA.date) dp.value = DATA.date;
  }
}

function drawLines(){
  var ov=document.getElementById('overlay'); if(!ov) return; ov.innerHTML='';
  var now=new Date();
  var cur=now.toISOString().slice(0,10)+' '+now.toTimeString().slice(0,5)+':00';
  var x=timeToX(cur); var ln=document.createElement('div'); ln.className='vline'; ln.style.left=x+'%'; ov.appendChild(ln);
  if(DATA && DATA.sunrise){ var xs=timeToX(DATA.sunrise); var s1=document.createElement('div'); s1.className='vline sun'; s1.style.left=xs+'%'; ov.appendChild(s1); }
  if(DATA && DATA.sunset){ var xe=timeToX(DATA.sunset); var s2=document.createElement('div'); s2.className='vline sun'; s2.style.left=xe+'%'; ov.appendChild(s2); }
}

function fetchDay(){
  var params=new URLSearchParams(location.search);
  var date=params.get('date') || '<?php echo safe($date); ?>';
  Promise.all([
    fetch('?api=list_day&date='+encodeURIComponent(date)).then(function(r){ if(!r.ok){ throw new Error('api'); } return r.json(); }),
    fetch('?api=whoami').then(function(r){ return r.json(); })
  ]).then(function(all){
    DATA=all[0];
    document.getElementById('whoami').textContent=all[1].user.name+' · '+all[1].user.role;
    buildHourHeader(); renderRows();
    if(window._timer){ clearInterval(window._timer); } window._timer=setInterval(drawLines,60000);
  }).catch(function(){
    DATA={devices:[],staff:[],'date':'<?php echo safe($date); ?>'}; buildHourHeader(); renderRows();
  });
}

/* Navigation & clock */
function pickDate(v){
  var p=new URLSearchParams(location.search);
  var hs=p.get('hstart')||<?php echo (int)$H_START; ?>;
  var he=p.get('hend')||<?php echo (int)$H_END; ?>;
  location.search='?date='+encodeURIComponent(v)+'&hstart='+hs+'&hend='+he;
}
function navDay(delta){
  var d=new Date('<?php echo safe($date); ?>');
  d.setDate(d.getDate()+delta);
  var y=d.getFullYear(), m=('0'+(d.getMonth()+1)).slice(-2), da=('0'+d.getDate()).slice(-2);
  pickDate(y+'-'+m+'-'+da);
}
function goToday(){ pickDate('<?php echo safe($today); ?>'); }
function tick(){ var c=document.getElementById('clock'); if(c) c.textContent=new Date().toLocaleTimeString(); } setInterval(tick,1000); tick();

/* Boot */
buildHourHeader(); fetchDay();
addEventListener('resize', drawLines);
</script>
</body>
</html>