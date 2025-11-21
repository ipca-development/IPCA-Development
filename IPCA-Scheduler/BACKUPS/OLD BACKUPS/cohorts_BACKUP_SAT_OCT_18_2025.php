<?php
// ===================================================================
// C-01 — PHP 5.3: Header, DB, helpers (standalone)
// ===================================================================
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);

$DB_HOST = 'mysql056.hosting.combell.com';
$DB_NAME = 'ID127947_egl1';
$DB_USER = 'ID127947_egl1';
$DB_PASS = 'Plane123';
$TZ      = 'America/Los_Angeles';
date_default_timezone_set($TZ);

/* ---- DB helpers (PHP 5.3 safe) ---- */
function db(){
  static $pdo=null,$init=false; global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS;
  if($init) return $pdo; $init=true;
  try{
    $pdo=new PDO('mysql:host='.$DB_HOST.';dbname='.$DB_NAME, $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('SET NAMES utf8');
  }catch(Exception $e){ $pdo=null; }
  return $pdo;
}
function q($sql,$args=array()){ $st=db()->prepare($sql); $st->execute($args); return $st; }
function jexit($arr){ if(!headers_sent()){ header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); } echo json_encode($arr); exit; }
function safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===================================================================
// C-02 — PHP: API router
// ===================================================================
if (isset($_GET['api'])) {
  @ini_set('display_errors','0'); @error_reporting(E_ERROR | E_PARSE);
  $api = $_GET['api'];

  // ---- list cohorts (+ members count) ----
  if ($api==='cohort_list') {
    try{
      $rows = q('SELECT c.*, 
                        (SELECT COUNT(*) FROM cohort_members m WHERE m.cohort_id=c.id) AS member_count
                 FROM cohorts c
                 ORDER BY c.active DESC, c.start_date DESC, c.name ASC')->fetchAll();
      jexit(array('ok'=>true,'cohorts'=>$rows));
    }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

  // ---- create/update cohort ----
  if ($api==='cohort_save') {
    $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
    $id   = isset($in['id']) ? (int)$in['id'] : 0;
    $name = isset($in['name']) ? trim($in['name']) : '';
    if ($name==='') jexit(array('ok'=>false,'error'=>'Name required'));
    $program = isset($in['program']) ? trim($in['program']) : null;
    $sd = isset($in['start_date']) ? $in['start_date'] : null;
    $ed = isset($in['end_date']) ? $in['end_date'] : null;
    $desc = isset($in['description']) ? $in['description'] : null;
    $active = !empty($in['active']) ? 1 : 0;

    try{
      if ($id){
        q('UPDATE cohorts SET name=?, program=?, start_date=?, end_date=?, description=?, active=?, updated_at=NOW() WHERE id=?',
          array($name,$program,$sd,$ed,$desc,$active,$id));
      }else{
        q('INSERT INTO cohorts (name,program,start_date,end_date,description,active,created_at,updated_at)
           VALUES (?,?,?,?,?,?,NOW(),NOW())', array($name,$program,$sd,$ed,$desc,$active));
        $id = db()->lastInsertId();
      }
      jexit(array('ok'=>true,'id'=>$id));
    }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

  // ---- delete cohort ----
  if ($api==='cohort_delete') {
    $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
    $id = isset($in['id']) ? (int)$in['id'] : 0;
    if(!$id) jexit(array('ok'=>false,'error'=>'Missing id'));
    try{ q('DELETE FROM cohorts WHERE id=?', array($id)); jexit(array('ok'=>true)); }
    catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

  // ---- list members for cohort ----
  if ($api==='cohort_members') {
    $cid = isset($_GET['cohort_id']) ? (int)$_GET['cohort_id'] : 0;
    if(!$cid) jexit(array('ok'=>false,'error'=>'Missing cohort_id'));
    try{
      $rows = q('SELECT m.id, m.student_id, m.role,
                        u.userid AS user_id, u.voornaam AS first_name, u.naam AS last_name
                 FROM cohort_members m 
                 JOIN users u ON u.userid=m.student_id
                 WHERE m.cohort_id=? ORDER BY u.voornaam,u.naam', array($cid))->fetchAll();
      jexit(array('ok'=>true,'members'=>$rows));
    }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

  // ---- add/remove member(s) ----
  if ($api==='cohort_members_save') {
    $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
    $cid = isset($in['cohort_id']) ? (int)$in['cohort_id'] : 0;
    $student_ids = isset($in['student_ids']) && is_array($in['student_ids']) ? $in['student_ids'] : array();
    $role = isset($in['role']) ? $in['role'] : 'Student';
    if(!$cid || !count($student_ids)) jexit(array('ok'=>false,'error'=>'Missing cohort_id or student_ids'));

    try{
      for($i=0;$i<count($student_ids);$i++){
        $uid = (int)$student_ids[$i];
        // avoid duplicates
        $ex = q('SELECT id FROM cohort_members WHERE cohort_id=? AND student_id=?', array($cid,$uid))->fetch();
        if(!$ex){
          q('INSERT INTO cohort_members (cohort_id,student_id,role,created_at) VALUES (?,?,?,NOW())', array($cid,$uid,$role));
        }
      }
      jexit(array('ok'=>true));
    }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

  if ($api==='cohort_member_delete') {
    $in = json_decode(file_get_contents('php://input'), true); if(!is_array($in)) $in=array();
    $mid = isset($in['member_id']) ? (int)$in['member_id'] : 0;
    if(!$mid) jexit(array('ok'=>false,'error'=>'Missing member_id'));
    try{ q('DELETE FROM cohort_members WHERE id=?', array($mid)); jexit(array('ok'=>true)); }
    catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

  // ---- active users grouped like booking (US/ALL programs, active only) ----
  if ($api==='active_users') {
    try{
      // Programs (active + in US or ALL)
      $prows = q('SELECT pr_id AS id, pr_name AS name
                  FROM programs
                  WHERE TRIM(UPPER(pr_active)) IN ("Y","YES","1","TRUE","T")
                    AND TRIM(UPPER(pr_location)) IN ("US","ALL")
                  ORDER BY pr_name ASC')->fetchAll();
      $programs = array();
      for($i=0;$i<count($prows);$i++) $programs[(int)$prows[$i]['id']] = $prows[$i]['name'];

     // Active users (students and any non-expired users)
// NOTE: removed the strict work_location='US' filter so students appear.
$urows = q('SELECT userid AS id, voornaam AS first_name, naam AS last_name
            FROM users
            WHERE actief_tot <> "0000-00-00"
              AND actief_tot >= CURDATE()
            ORDER BY voornaam, naam')->fetchAll();
$userById = array();
for($i=0;$i<count($urows);$i++) $userById[(int)$urows[$i]['id']] = $urows[$i];

      // program ↔ users
      $links = q('SELECT pu_user AS uid, pu_program AS pid FROM programs_users')->fetchAll();
      $userPrograms = array();
      for($i=0;$i<count($links);$i++){
        $uid=(int)$links[$i]['uid']; $pid=(int)$links[$i]['pid'];
        if(!isset($programs[$pid]) || !isset($userById[$uid])) continue;
        if(!isset($userPrograms[$uid])) $userPrograms[$uid]=array();
        if(!in_array($pid,$userPrograms[$uid])) $userPrograms[$uid][]=$pid;
      }

      // Build groups
      $groups = array(); $unassigned=array();
      foreach($userById as $uid=>$u){
        if(isset($userPrograms[$uid])){
          for($k=0;$k<count($userPrograms[$uid]);$k++){
            $pid=$userPrograms[$uid][$k]; $pname=$programs[$pid];
            if(!isset($groups[$pname])) $groups[$pname]=array();
            $groups[$pname][]=$u;
          }
        }else{
          $unassigned[]=$u;
        }
      }

      // Sort program names naturally; sort users by first then last
      $order = array_keys($groups);
      if(function_exists('natcasesort')){ natcasesort($order); $order=array_values($order); }
      else sort($order);

      $cmp = function($a,$b){
        $fa=strtolower($a['first_name']); $fb=strtolower($b['first_name']);
        if($fa===$fb){ $la=strtolower($a['last_name']); $lb=strtolower($b['last_name']); return strcmp($la,$lb); }
        return strcmp($fa,$fb);
      };
      for($i=0;$i<count($order);$i++){ usort($groups[$order[$i]], $cmp); }
      usort($unassigned, $cmp);

      jexit(array('ok'=>true, 'student_groups'=>array('groups'=>$groups, 'group_order'=>$order, 'unassigned'=>$unassigned)));
    }catch(Exception $e){ jexit(array('ok'=>false,'error'=>$e->getMessage())); }
  }

  exit;
}

// ===================================================================
// C-03 — HTML: Cohorts UI
// ===================================================================
?><!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cohorts</title>
<style>
 body{margin:0;background:#eef1f6;font:14px/1.4 system-ui,-apple-system,Segoe UI,Roboto,Arial;color:#1a1f36}
 .top{background:linear-gradient(90deg,#1e3c72,#2a5298);color:#fff;padding:10px 16px;font-weight:700}
 .wrap{padding:12px 16px; max-width:1200px; margin:0 auto;}
 .card{background:#fff;border:1px solid #dde3f0;border-radius:12px;padding:12px}
 .row{display:flex;gap:12px;flex-wrap:wrap}
 .col{flex:1 1 320px; min-width:260px}
 input,select,textarea{width:100%;padding:8px;border:1px solid #cfd5e3;border-radius:8px;max-width:100%;box-sizing:border-box}
 table{width:100%;border-collapse:collapse;margin-top:10px}
 th,td{padding:8px;border-bottom:1px solid #e5e9f2}
 .btn{padding:8px 12px;border-radius:8px;border:1px solid #1e3c72;background:#1e3c72;color:#fff;cursor:pointer}
 .btn.secondary{background:#f3f6fb;color:#1a1f36;border-color:#cfd5e3}
 .pill{display:inline-block;background:#eef2ff;border:1px solid #cfe3ff;color:#244f87;border-radius:999px;padding:2px 8px;font-size:12px}
 /* Row hover + pointer for clickable cohort rows */
 #cohortTable tbody tr.coh-row { cursor: pointer; }
 #cohortTable tbody tr.coh-row:hover { background:#f6f8fc; }
	
	
/* === Layout tweaks requested === */
/* Put Cohort List on left and make it 2× wide */
#listCol { order: 1; flex: 2 1 0; }
#formCol { order: 2; flex: 1 1 0; }

/* Members section layout */
.membersRow{align-items:flex-start}

/* Left: available students list */
#membersLeft{order:1; flex:1 1 360px}

/* Role + Add panel UNDER the left list */
#membersActions{order:2; flex:1 1 100%; max-width:640px}

/* Right: current cohort members in a white field, width matches Cohort List (2×) */
#membersRight{
  order:3; flex:2 1 0;
  background:#fff; border:1px solid #dde3f0; border-radius:12px; padding:12px;
}

/* Keep things tidy on small screens */
@media (max-width:900px){
  #listCol, #formCol, #membersLeft, #membersRight{flex:1 1 100%}
}
</style>
</head><body>
<div class="top">Cohort Management</div>
<div class="wrap">

  <div class="row">
    <div class="col card" id="formCol">
      <h3 style="margin:0 0 8px 0">Create / Edit Cohort</h3>
      <div class="row">
        <div class="col"><label>Name<input id="c_name"></label></div>
        <div class="col"><label>Program<input id="c_program" placeholder="PPL / IR / …"></label></div>
      </div>
      <div class="row">
        <div class="col"><label>Start Date<input type="date" id="c_sd"></label></div>
        <div class="col"><label>End Date<input type="date" id="c_ed"></label></div>
      </div>
      <div class="row">
        <div class="col"><label>Description<textarea id="c_desc" rows="3"></textarea></label></div>
      </div>
      <div class="row">
        <div class="col"><label>Active
          <select id="c_active"><option value="1">Yes</option><option value="0">No</option></select>
        </label></div>
        <div class="col" style="display:flex;align-items:flex-end;gap:8px">
          <button class="btn" id="saveCohort">Save</button>
          <button class="btn secondary" id="resetForm">Reset</button>
        </div>
      </div>
      <input type="hidden" id="c_id" value="">
    </div>

    <div class="col card" id="listCol">
      <h3 style="margin:0 0 8px 0">Cohorts</h3>
      <table id="cohortTable"><thead>
        <tr><th>Name</th><th>Program</th><th>Dates</th><th>Status</th><th>Members</th><th></th></tr>
      </thead><tbody></tbody></table>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3 style="margin:0 0 8px 0">Members of Selected Cohort</h3>
    <div class="row membersRow">
      <!-- Left: Available users grouped by Program -->
      <div class="col" id="membersLeft">
        <select id="userPicker" multiple size="12" style="height:220px"><option>Loading…</option></select>
      </div>

      <!-- Role + Add goes UNDER the left list -->
      <div class="col" id="membersActions">
        <label>Role
          <select id="m_role"><option>Student</option><option>Lead Instructor</option><option>Assistant</option></select>
        </label>
        <div style="margin-top:8px">
          <button class="btn" id="addMembers">Add Selected</button>
        </div>
      </div>

      <!-- Right: Members in the cohort (white field), same width ratio as Cohort List -->
      <div class="col" id="membersRight">
        <table id="memberTable"><thead><tr><th>Name</th><th>Role</th><th></th></tr></thead><tbody></tbody></table>
      </div>
    </div>
  </div>
</div>

<script>
// ===== helpers =====
function $(id){ return document.getElementById(id); }
function api(url, body, cb){
  var opt = { method: body?'POST':'GET' };
  if (body){ opt.headers={'Content-Type':'application/json'}; opt.body=JSON.stringify(body); }
  fetch(url,opt).then(function(r){return r.json();}).then(cb||function(){}); 
}
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

// ===== load lists =====

// ===== helpers =====
function $(id){ return document.getElementById(id); }
function api(url, body, cb){
  var opt = { method: body?'POST':'GET' };
  if (body){ opt.headers={'Content-Type':'application/json'}; opt.body=JSON.stringify(body); }
  fetch(url,opt).then(function(r){return r.json();}).then(cb||function(){}); 
}
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

// ---- NEW: tiny helper so both row-click and Edit reuse the same logic
function loadCohortIntoForm(cohortId){
  fetch('?api=cohort_list')
    .then(function(r){return r.json();})
    .then(function(js){
      if(!js.ok) return;
      for(var i=0;i<js.cohorts.length;i++){
        var c=js.cohorts[i];
        if(String(c.id)===String(cohortId)){
          $('c_id').value=c.id;
          $('c_name').value=c.name||'';
          $('c_program').value=c.program||'';
          $('c_sd').value=c.start_date||'';
          $('c_ed').value=c.end_date||'';
          $('c_desc').value=c.description||'';
          $('c_active').value=c.active?'1':'0';
          loadMembers();
          break;
        }
      }
    });
}

// ===== load lists =====
function loadCohorts(){
  api('?api=cohort_list', null, function(js){
    var tb=$('cohortTable').querySelector('tbody'); tb.innerHTML='';
    if(!js.ok) return;

    for(var i=0;i<js.cohorts.length;i++){
      var c=js.cohorts[i];
      var tr=document.createElement('tr');
      tr.className = 'coh-row';
      tr.setAttribute('data-cid', c.id);

      tr.innerHTML =
        '<td>'+escapeHtml(c.name)+'</td>'+
        '<td>'+(c.program?escapeHtml(c.program):'')+'</td>'+
        '<td>'+(c.start_date||'')+' → '+(c.end_date||'')+'</td>'+
        '<td>'+(c.active?'Active':'Hidden')+'</td>'+
        '<td><span class="pill">'+(c.member_count||0)+' members</span></td>'+
        '<td>'+
          '<button class="btn secondary" data-id="'+c.id+'">Edit</button> '+
          '<button class="btn secondary" data-del="'+c.id+'">Delete</button>'+
        '</td>';

      tb.appendChild(tr);
    }
  });
}

function loadUsers(){
  api('?api=active_users', null, function(js){
    var sel=$('userPicker'); sel.innerHTML='';
    if(!js.ok){ sel.innerHTML='<option>Error loading users</option>'; return; }

    var sg = js.student_groups || {};
    var order = sg.group_order || [];
    var added = 0;

    // Programs in order
    for(var i=0;i<order.length;i++){
      var pg = order[i];
      var og = document.createElement('optgroup');
      og.label = pg + ' - (US)';
      sel.appendChild(og);
      var arr = (sg.groups && sg.groups[pg]) ? sg.groups[pg] : [];
      for(var k=0;k<arr.length;k++){
        var u = arr[k];
        var opt = document.createElement('option');
        opt.value = u.id;
        opt.text  = (u.first_name||'')+' '+(u.last_name||'');
        og.appendChild(opt);
        added++;
      }
    }

    // Unassigned
    var un = sg.unassigned || [];
    if(un.length){
      var og2 = document.createElement('optgroup');
      og2.label = 'Unassigned / Other';
      sel.appendChild(og2);
      for(var z=0;z<un.length;z++){
        var uu = un[z];
        var o2 = document.createElement('option');
        o2.value = uu.id;
        o2.text  = (uu.first_name||'')+' '+(uu.last_name||'');
        og2.appendChild(o2);
        added++;
      }
    }

    if(!added){
      sel.innerHTML = '<option>No active users</option>';
    }
  });
}

function loadMembers(){
  var cid=$('c_id').value; if(!cid){ $('memberTable').querySelector('tbody').innerHTML=''; return; }
  api('?api=cohort_members&cohort_id='+encodeURIComponent(cid), null, function(js){
    var tb=$('memberTable').querySelector('tbody'); tb.innerHTML='';
    if(!js.ok) return;
    for(var i=0;i<js.members.length;i++){
      var m=js.members[i];
      var tr=document.createElement('tr');
      tr.innerHTML='<td>'+escapeHtml((m.first_name||"")+' '+(m.last_name||""))+'</td>'+
                   '<td>'+escapeHtml(m.role||'Student')+'</td>'+
                   '<td><button class="btn secondary" data-rm="'+m.id+'">Remove</button></td>';
      tb.appendChild(tr);
    }
  });
}

// ===== events =====
document.addEventListener('click', function(e){
  /* NEW: click anywhere on a cohort row (except on its buttons) */
  var row = e.target.closest('#cohortTable tbody tr.coh-row');
  if (row && !e.target.closest('button')) {
    var cid = row.getAttribute('data-cid');
    if (cid) { loadCohortIntoForm(cid); }
    return; // stop here so we don't trigger other handlers
  }

  if(e.target && e.target.matches('#saveCohort')){
    var payload={
      id: parseInt($('c_id').value||'0',10)||0,
      name: $('c_name').value,
      program: $('c_program').value,
      start_date: $('c_sd').value||null,
      end_date: $('c_ed').value||null,
      description: $('c_desc').value||null,
      active: $('c_active').value==='1'?1:0
    };
    api('?api=cohort_save', payload, function(js){
      if(js.ok){ $('c_id').value=js.id; loadCohorts(); loadMembers(); alert('Saved.'); }
      else alert('Error: '+(js.error||'save failed'));
    });
  }

  if(e.target && e.target.matches('#resetForm')){
    ['c_id','c_name','c_program','c_sd','c_ed','c_desc'].forEach(function(id){ $(id).value=''; });
    $('c_active').value='1'; loadMembers();
  }

  /* CHANGED: Edit button now reuses the same loader as row-click */
  if(e.target && e.target.getAttribute('data-id')){
    loadCohortIntoForm(e.target.getAttribute('data-id'));
  }

  if(e.target && e.target.getAttribute('data-del')){
    if(!confirm('Delete this cohort?')) return;
    api('?api=cohort_delete', {id: parseInt(e.target.getAttribute('data-del'),10)}, function(js){
      if(js.ok){ loadCohorts(); if(String($('c_id').value)===e.target.getAttribute('data-del')) document.getElementById('resetForm').click(); }
      else alert('Error: '+(js.error||'delete failed'));
    });
  }

  if(e.target && e.target.matches('#addMembers')){
    var cid=parseInt($('c_id').value||'0',10)||0; if(!cid){ alert('Select or save a cohort first.'); return; }
    var ids=[], sel=$('userPicker').options;
    for(var i=0;i<sel.length;i++){ if(sel[i].selected && sel[i].value) ids.push(parseInt(sel[i].value,10)); }
    if(!ids.length){ alert('Select users to add.'); return; }
    api('?api=cohort_members_save', {cohort_id:cid, student_ids:ids, role:$('m_role').value}, function(js){
      if(js.ok){ loadMembers(); } else alert('Error: '+(js.error||'member add failed'));
    });
  }

  if(e.target && e.target.getAttribute('data-rm')){
    if(!confirm('Remove this member?')) return;
    api('?api=cohort_member_delete', {member_id: parseInt(e.target.getAttribute('data-rm'),10)}, function(js){
      if(js.ok){ loadMembers(); } else alert('Error: '+(js.error||'remove failed'));
    });
  }
});

// boot
loadCohorts(); loadUsers(); loadMembers();
</script>
</body></html>