<?php
// ===================================================================
// reminders.php — Reminders page (with Short Label + Edit support)
// ===================================================================

header('Content-Type: text/html; charset=utf-8');

// (Optional) you can pull these from schedule.php later; hardcode for now
$LOC_NAME = 'SoCal Pilot Center – California (#SPCS024M)';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>IPCA – Reminders</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- ================================================================
       R-01 — CSS: Base, cards, header, modal shell
       ================================================================ -->
  <style>
    :root{
      --ipca-blue:#1e3c72; --ipca-blue2:#2a5298; --bg:#eef1f6;
      --card:#fff; --muted:#6b7487; --border:#dde3f0;
    }
    *{ box-sizing:border-box }
    body{ margin:0; background:var(--bg); color:#1a1f36; font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial; }

    /* Top bar (match schedule.php tone) */
    .topbar{ background:linear-gradient(90deg,var(--ipca-blue),var(--ipca-blue2)); color:#fff;
             display:flex; align-items:center; gap:12px; padding:10px 16px; }
    .brand{ display:flex; align-items:center; gap:10px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .brand img.logo{ height:24px; width:auto; display:block }
    .menu{ margin-left:auto; display:flex; gap:8px; }
    .btn{ background:#ffffff22; border:1px solid #ffffff30; color:#fff; padding:6px 10px; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn:hover{ background:#ffffff33 }
    .ghost{ background:transparent; border:1px solid #ffffff55 }

    /* Layout */
    .wrap{ padding:12px 16px; }
    .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .headerBar{ display:flex; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid #e5e9f2; }
    .spacer{ flex:1 }
    .sectionHd{ padding:10px 12px; font-weight:700; border-bottom:1px solid #e5e9f2; background:#fafbff; }
    .pad{ padding:12px 16px; }
    .muted{ color:var(--muted); }

    /* Simple list */
    .remList{ display:grid; gap:8px; padding:12px 12px 16px; }
    .remRow{ display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border:1px solid var(--border); border-radius:10px; background:#fff; }
    .pill{ font-size:12px; padding:2px 8px; border-radius:999px; background:#eef2fb; border:1px solid #d8e0f2; color:#3a57a0; }

    /* Modal shell */
    .modalWrap{ position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center; z-index:99999; }
    .modal{ background:#fff; width:min(900px,92vw); max-height:92vh; overflow:auto; border-radius:14px; border:1px solid var(--border); box-shadow:0 20px 60px rgba(0,0,0,.25); }
    .modalHd{ display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #e5e9f2; }
    .modalHd h3{ margin:0; font-size:18px; }
    .modalBd{ padding:14px 16px; }
    .modalFt{ display:flex; justify-content:flex-end; gap:10px; padding:14px 16px; border-top:1px solid #e5e9f2; }
    .btnSec{ background:#f3f6fb; border:1px solid #cfd5e3; color:#1a1f36; padding:8px 12px; border-radius:8px; cursor:pointer; }
    .btnPri{ background:#1e3c72; border:1px solid #1e3c72; color:#fff; padding:8px 12px; border-radius:8px; cursor:pointer; font-weight:700; }
    .btnPri:hover{ background:#234686; }
    .btnMini{ font-size:12px; padding:4px 8px; border-radius:7px; }

    /* Form */
    .formGrid { display:grid; grid-template-columns: 1fr 1fr; gap:12px 16px; }
    .formRow { display:flex; flex-direction:column; gap:6px; }
    .formRow label { font-weight:700; color:#1e2a44; }
    .formRow input[type=text],
    .formRow input[type=number],
    .formRow input[type=date],
    .formRow select { padding:10px; border:1px solid #cfd5e3; border-radius:8px; }
    .help { color:#6b7487; font-size:12px; }
  </style>
</head>
<body>

  <!-- ================================================================
       R-02 — Top bar
       ================================================================ -->
  <div class="topbar">
    <div class="brand">
      <img class="logo" src="img/IPCA.png" alt="IPCA">
      <span>IPCA — Reminders</span>
    </div>
    <div class="menu">
      <a class="btn ghost" href="schedule.php">← Back to Schedule</a>
    </div>
  </div>

  <div class="wrap"><!-- open main wrapper -->

    <!-- Fuel card -->
    <div class="card" style="margin-bottom:16px;">
      <div class="headerBar" style="padding:10px 12px;">
        <div style="font-weight:700;">Fuel Station Status</div>
        <span class="spacer"></span>
        <input id="fuelGallons" type="number" step="0.1" placeholder="Gallons"
               style="padding:6px 8px; border:1px solid #cfd5e3; border-radius:8px; width:160px;">
        <button class="btnSec" id="saveFuelBtn" style="margin-left:8px;">Save</button>
      </div>
      <div id="fuelMeta" style="padding:0 16px 12px; color:#6b7487;"></div>
    </div>

    <!-- Header actions -->
    <div class="card" style="margin-bottom:12px;">
      <div class="headerBar">
        <div style="font-weight:800;">Reminders</div>
        <span class="spacer"></span>
        <button class="btnPri" id="addReminderBtn">+ Add Reminder</button>
      </div>
    </div>

    <!-- Devices -->
    <div class="card">
      <div class="sectionHd">Devices</div>
      <div class="remList" id="remDevWrap"></div>
    </div>

    <!-- Staff -->
    <div class="card">
      <div class="sectionHd">Personnel</div>
      <div class="remList" id="remStaffWrap"></div>
    </div>

  </div><!-- /wrap -->

  <!-- ================================================================
       R-07 — Modal: Create / Edit Reminder
       ================================================================ -->
  <div class="modalWrap" id="remModal" style="display:none;">
    <div class="modal">
      <div class="modalHd">
        <h3 id="remTitle">New Reminder</h3>
        <button class="btnSec" id="remClose">✕</button>
      </div>
      <div class="modalBd">
        <div class="formGrid">
          <div class="formRow">
            <label>Target</label>
            <select id="remTargetType">
              <option value="DEVICE">Device</option>
              <option value="STAFF">Staff</option>
            </select>
          </div>
          <div class="formRow">
            <label>Target Item</label>
            <select id="remTargetId"><option>Loading…</option></select>
          </div>

          <div class="formRow" style="grid-column:1 / span 2;">
            <label>Name</label>
            <input type="text" id="remName" placeholder="e.g. 100 h Inspection">
          </div>

          <!-- NEW: Short Label (max 4 chars) -->
          <div class="formRow">
            <label>Short Label</label>
            <input type="text" id="remShortLabel" maxlength="4" placeholder="e.g. 100h, CFI, MED">
            <div class="help">Max 4 characters. Shown on schedule (“… till <strong>100h</strong>”, “… till <strong>CFI</strong>”).</div>
          </div>

          <div class="formRow">
            <label>Tracking by</label>
            <select id="remTrackBy">
              <option value="HOURS_TACHO">By Tacho Hours</option>
              <option value="HOURS_HOBBS">By Hobbs Hours</option>
              <option value="DATE">By Date</option>
            </select>
          </div>

          <div class="formRow" id="rowDueNum">
            <label>Due @ Hours</label>
            <input type="number" step="0.1" id="remDueNum" placeholder="e.g. 1234.5">
          </div>
          <div class="formRow" id="rowDueDate" style="display:none;">
            <label>Due Date</label>
            <input type="date" id="remDueDate">
          </div>

          <div class="formRow" style="grid-column:1 / span 2;">
            <label id="lblCurrent">Current (derived)</label>
            <input type="text" id="remCurrent" disabled value="—">
            <div class="muted" style="font-size:12px;">Shown from device’s latest saved meter.</div>
          </div>

          <div class="formRow">
            <label>Last Completed</label>
            <div style="display:flex;gap:8px;">
              <input type="number" step="0.1" id="remLastNum" placeholder="e.g. 2300.0">
              <input type="date" id="remLastDate" style="display:none;">
              <button class="btnSec btnMini" id="btnLastCompleted">Set…</button>
            </div>
          </div>

          <div class="formRow">
            <label>Interval, every</label>
            <div style="display:flex;gap:8px;">
              <input type="number" id="remIntervalValue" placeholder="e.g. 100">
              <select id="remIntervalUnit">
                <option value="HOURS">Hours</option>
                <option value="DAYS">Days</option>
                <option value="MONTHS">Months</option>
              </select>
            </div>
            <label style="display:flex;align-items:center;gap:8px;margin-top:6px;">
              <input type="checkbox" id="remCalendarMonth"> Calendar month (snap to last day)
            </label>
          </div>

          <div class="formRow" style="grid-column:1 / span 2;">
            <label id="lblWarn">Remind by remaining</label>
            <div style="display:flex;gap:8px;">
              <input type="number" id="remWarnValue" placeholder="e.g. 10">
              <select id="remWarnUnit">
                <option value="HOURS_TACHO">Hours (Tacho)</option>
                <option value="HOURS_HOBBS">Hours (Hobbs)</option>
                <option value="DAYS">Days</option>
                <option value="MONTHS">Months</option>
              </select>
            </div>
          </div>

          <div class="formRow" style="grid-column:1 / span 2;">
            <label>Next Due (auto)</label>
            <div style="display:flex;gap:8px;">
              <input type="text" id="remNextNum" disabled placeholder="—">
              <input type="date" id="remNextDate" disabled>
            </div>
          </div>

          <div class="formRow" style="grid-column:1 / span 2; display:flex; gap:18px; align-items:center;">
            <label style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" id="remPrimary"> Show as Primary Reminder on Schedule
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" id="remEmail"> Send Reminder by Email
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" id="remSlack"> Send Reminder via Slack
            </label>
          </div>

          <div class="formRow" style="grid-column:1 / span 2;">
            <label>Notes</label>
            <input type="text" id="remNotes" placeholder="Optional">
          </div>
        </div>
      </div>
      <div class="modalFt">
        <button class="btnSec" id="remCancel">Cancel</button>
        <button class="btnPri" id="remSave">Save</button>
      </div>
    </div>
  </div>

  <!-- ================================================================
       R-07A — Mini-modal: Set “Last Completed”
       ================================================================ -->
  <div class="modalWrap" id="lcModal" style="display:none;">
    <div class="modal">
      <div class="modalHd">
        <h3>Last Completed</h3>
        <button class="btnSec" id="lcClose">✕</button>
      </div>
      <div class="modalBd">
        <div class="formGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;">
          <div class="formRow" id="lcRowNum">
            <label id="lblLcNum">Last Completed (hours)</label>
            <input type="number" step="0.1" id="lcNum" placeholder="e.g. 5282.2">
          </div>
          <div class="formRow" id="lcRowDate" style="display:none;">
            <label>Last Completed (date)</label>
            <input type="date" id="lcDate">
          </div>
          <div class="formRow" style="grid-column:1 / span 2;">
            <label>Notes</label>
            <input type="text" id="lcNotes" placeholder="Optional">
          </div>
        </div>
      </div>
      <div class="modalFt">
        <button class="btnSec" id="lcCancel">Cancel</button>
        <button class="btnPri" id="lcApply">Add</button>
      </div>
    </div>
  </div>

  <!-- ================================================================
       R-08 — JS: fetch + render + fuel
       ================================================================ -->
  <script>
  (function(){
    var devWrap   = document.getElementById('remDevWrap');
    var staffWrap = document.getElementById('remStaffWrap');
    var fuelMeta  = document.getElementById('fuelMeta');
    var fuelInput = document.getElementById('fuelGallons');
    var fuelBtn   = document.getElementById('saveFuelBtn');

    function esc(s){ return (s||'').toString().replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

    function statusBadge(sev, value, unit){
      var tone =
          (sev==='overdue') ? 'background:#fdeaea;border-color:#f3c1c1;color:#b02323'
        : (sev==='soon')    ? 'background:#fff5e6;border-color:#f3d6a8;color:#8a5600'
        : (sev==='notice')  ? 'background:#eef7ff;border-color:#cfe3ff;color:#244f87'
        : (sev==='ok')      ? 'background:#e7faef;border-color:#bfead0;color:#1b6b3a'
        : '';
      var txt='n/a';
      if (value!==null && value!==undefined && !isNaN(value)){
        if (unit==='HOURS')      txt = (Math.round(value*10)/10).toFixed(1)+' hrs';
        else if (unit==='MONTHS')txt = String(value)+' months';
        else                     txt = String(value)+' days';
      }
      return '<span class="pill" style="'+tone+'">'+esc(txt)+'</span>';
    }
    function severityPill(sev, value, unit){ return statusBadge(sev, value, unit||'DAYS'); }

    // Progress bar/tiny label used in list (kept simple)
    function statusBar(R, current){
      var isDate = (R.track_by === 'DATE');
      var rem = (R.remaining!==undefined && R.remaining!==null) ? Number(R.remaining) : null;

      // DATE
      if (isDate){
        var raw = R.next_due_date ? String(R.next_due_date).trim() : '';
        var nextD = null;
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) nextD = new Date(raw+'T12:00:00');
        else if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/.test(raw)) nextD = new Date(raw.replace(' ','T'));
        else if (raw && raw!=='0000-00-00') { var tmp=new Date(raw); if(!isNaN(tmp.getTime())) nextD = tmp; }
        var today = new Date(); today.setHours(12,0,0,0);
        if (nextD) rem = Math.ceil((nextD - today)/86400000);
        var total=null, used=null;
        var lastD = R.last_completed_date ? new Date(String(R.last_completed_date)+'T12:00:00') : null;
        if (lastD && nextD){ total=Math.max(1,Math.round((nextD-lastD)/86400000)); if(!isNaN(rem)) used=Math.max(0,total-rem); }
        else if (R.interval_unit==='DAYS' && R.interval_value){ total=Number(R.interval_value); if(!isNaN(rem)) used=Math.max(0,total-rem); }
        else if (R.interval_unit==='MONTHS' && R.interval_value){ total=Number(R.interval_value)*30; if(!isNaN(rem)) used=Math.max(0,total-rem); }
        var pct = (!isNaN(total) && total>0 && !isNaN(used)) ? Math.max(0,Math.min(100,(used/total)*100)) : (!isNaN(rem) && rem<=0 ? 100 : 0);
        var sev='notice'; if(!isNaN(rem)){ if(rem<=0) sev='overdue'; else if(!isNaN(total)&&total>0&&rem/total<=0.25) sev='soon'; else sev='ok'; }
        var color = (sev==='overdue')?'#e53935':(sev==='soon')?'#f59e0b':(sev==='notice')?'#3b82f6':'#22c55e';

        // prefer DB-provided short_label like "CFI"
        var shortTxt = (!isNaN(rem) ? rem+'d' : '—') + (R.short_label ? ' till '+esc(R.short_label) : '');
        return ''+
          '<div style="display:flex; align-items:center; gap:10px;">' +
            '<div style="flex:0 0 40%; max-width:340px; min-width:200px; height:10px; background:#e6e9ef; border-radius:999px; overflow:hidden;">' +
              '<div style="width:'+pct+'%; height:100%; background:'+color+'; border-radius:999px;"></div>' +
            '</div>' +
            '<div class="muted" style="margin-left:auto; font-size:12px;">'+shortTxt+'</div>' +
          '</div>';
      }

      // HOURS
      var next = (R.next_due_num!=null) ? Number(R.next_due_num) : null;
      if ((rem==null || isNaN(rem)) && next!=null && current!=null) rem = next - Number(current);

      var iv = (R.interval_unit==='HOURS' && R.interval_value!=null) ? Number(R.interval_value) : null;
      var last = (R.last_completed_num!=null) ? Number(R.last_completed_num) : null;
      var total=null, used=null;
      if (iv!=null){ total=iv; if(!isNaN(rem)) used=Math.max(0,total-rem); }
      else if (last!=null && next!=null){ total=next-last; if(current!=null) used=Number(current)-last; }
      var pct = (!isNaN(total) && total>0 && !isNaN(used)) ? Math.max(0,Math.min(100,(used/total)*100)) : (!isNaN(rem) && rem<=0 ? 100 : 0);
      var sev='notice'; if(!isNaN(rem)){ if(rem<=0) sev='overdue'; else if(!isNaN(total)&&total>0&&rem/total<=0.25) sev='soon'; else sev='ok'; }
      var color = (sev==='overdue')?'#e53935':(sev==='soon')?'#f59e0b':(sev==='notice')?'#3b82f6':'#22c55e';

      var remTxt = (!isNaN(rem)) ? (Math.round(rem*10)/10).toFixed(1)+'h' : '—';
      // prefer DB short_label like "100h"
      var tail = R.short_label ? (' till '+esc(R.short_label)) : ' till due';
      return ''+
        '<div style="display:flex; align-items:center; gap:10px;">' +
          '<div style="flex:0 0 40%; max-width:340px; min-width:200px; height:10px; background:#e6e9ef; border-radius:999px; overflow:hidden;">' +
            '<div style="width:'+pct+'%; height:100%; background:'+color+'; border-radius:999px;"></div>' +
          '</div>' +
          '<div class="muted" style="margin-left:auto; font-size:12px;">'+remTxt+tail+'</div>' +
        '</div>';
    }

    // Render: Devices
    function renderDevices(list){
      if(!devWrap) return;
      if(!list || !list.length){
        devWrap.innerHTML = '<div class="muted" style="padding:6px 4px;">No device reminders.</div>';
        return;
      }
      var html = [];
      for(var i=0;i<list.length;i++){
        var it   = list[i];
        var name = esc((it.name||'') + (it.model? ' ('+it.model+')':'' ));
        var meta = esc(it.maint_type || '');

        html.push(
          '<div class="remRow" style="flex-direction:column; align-items:stretch;">' +
            '<div style="display:flex;align-items:center;gap:10px;">' +
              '<strong>'+ name +'</strong>' +
              severityPill(it.severity, it.days_left, 'DAYS') +
              (meta? '<span class="muted">'+meta+'</span>' : '') +
              ( (it.latest_tacho!=null) ? '<span class="muted" style="margin-left:auto;">Tacho: '+esc(it.latest_tacho)+'</span>' : '' ) +
              ( (it.latest_hobbs!=null) ? '<span class="muted" style="margin-left:8px;">Hobbs: '+esc(it.latest_hobbs)+'</span>' : '' ) +
            '</div>'
        );

        var rems = it.reminders || [];
        if(rems.length){
          html.push('<div style="margin-top:8px; padding-left:6px; display:grid; gap:6px;">');
          for(var r=0;r<rems.length;r++){
            var R   = rems[r];
            var current = (R.track_by==='HOURS_TACHO') ? it.latest_tacho : (R.track_by==='HOURS_HOBBS') ? it.latest_hobbs : null;
            var bar = statusBar(R, current);

            html.push(
              '<div style="display:flex; flex-direction:column; gap:6px; padding:8px; border:1px solid #e6ebf5; border-radius:8px;">' +
                '<div style="display:flex; align-items:center; gap:8px;">' +
                  '<div style="font-weight:600;">'+esc(R.name)+'</div>' +
                  (R.primary ? '<span class="pill" style="margin-left:auto;">Primary</span>' : '<span class="spacer"></span>') +
                  '<button class="btnSec btnMini" onclick="openEditReminder(\'DEVICE\','+Number(R.id)+')">Edit</button>' +
                '</div>' +
                bar +
                (R.due_text ? '<div class="muted">'+esc(R.due_text)+'</div>' : '') +
              '</div>'
            );
          }
          html.push('</div>');
        }else{
          html.push('<div class="muted" style="margin-top:6px;">No saved reminders for this device.</div>');
        }

        html.push('</div>');
      }
      devWrap.innerHTML = html.join('');
    }

    // Render: Staff
    function renderStaff(list){
      if(!staffWrap) return;
      if(!list || !list.length){
        staffWrap.innerHTML='<div class="muted" style="padding:6px 4px;">No personnel reminders.</div>';
        return;
      }
      var html=[], it;
      for(var i=0;i<list.length;i++){
        it=list[i];

        html.push(
          '<div class="remRow" style="flex-direction:column; align-items:stretch;">' +
            '<div style="display:flex;align-items:center;gap:10px;">' +
              '<strong>'+esc(it.name||'—')+'</strong>' +
              severityPill(it.severity, it.days_left, 'DAYS') +
              (it.role ? '<span class="muted" style="text-transform:uppercase;">'+esc(it.role)+'</span>' : '') +
            '</div>'
        );

        var rems = it.reminders || [];
        if (rems.length){
          html.push('<div style="margin-top:8px; padding-left:6px; display:grid; gap:6px;">');
          for (var r=0;r<rems.length;r++){
            var R = rems[r];
            var bar = statusBar(R, null);
            html.push(
              '<div style="display:flex; flex-direction:column; gap:6px; padding:8px; border:1px solid #e6ebf5; border-radius:8px;">' +
                '<div style="display:flex; align-items:center; gap:8px;">' +
                  '<div style="font-weight:600;">'+esc(R.name||'—')+'</div>' +
                  (R.primary ? '<span class="pill" style="margin-left:auto;">Primary</span>' : '<span class="spacer"></span>') +
                  '<button class="btnSec btnMini" onclick="openEditReminder(\'STAFF\','+Number(R.id)+')">Edit</button>' +
                '</div>' +
                bar +
                (R.due_text ? '<div class="muted">'+esc(R.due_text)+'</div>' : '') +
              '</div>'
            );
          }
          html.push('</div>');
        }else{
          html.push('<div class="muted" style="margin-top:6px;">No saved reminders for this person.</div>');
        }

        html.push('</div>');
      }
      staffWrap.innerHTML = html.join('');
    }

    // Fuel render/save
    function renderFuel(f){
      if(!fuelMeta) return;
      if(!f || !f.has){ fuelMeta.innerHTML='<span class="muted">No fuel reading available.</span>'; return; }
      var g=(typeof f.gallons==='number')? f.gallons.toFixed(1) : String(f.gallons||'');
      var by=f.updated_by ? (' by '+esc(f.updated_by)) : '';
      var at=f.updated_at ? new Date(f.updated_at.replace(' ','T')).toLocaleString() : '';
      var when = at ? (' on '+esc(at)) : '';
      fuelMeta.innerHTML = '<strong>'+esc(g)+' gallons</strong>'+by+when;
    }
    if (fuelBtn){
      fuelBtn.addEventListener('click', function(){
        var gallons = parseFloat(fuelInput && fuelInput.value ? fuelInput.value : 'NaN');
        if (isNaN(gallons)){ alert('Enter gallons (number).'); return; }
        fetch('schedule.php?api=reminders_fuel_save', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ gallons:gallons, updated_by:'' })
        })
        .then(function(r){ return r.json(); })
        .then(function(js){
          if (js && js.ok && js.fuel){ renderFuel(js.fuel); }
          else alert('Fuel save failed.');
        })
        .catch(function(){ alert('Fuel save failed.'); });
      }, false);
    }

    function load(){
      fetch('schedule.php?api=reminders_list')
        .then(function(r){return r.json();})
        .then(function(js){
          if(!js || js.ok!==true) throw 0;
          renderDevices(js.devices||[]);
          renderStaff(js.staff||[]);
          renderFuel(js.fuel||null);

          // Expose for modal population
          window.DEVICES = (js.devices||[]).map(function(d){
            return { id:d.id, name:d.name+(d.model?(' ('+d.model+')'):'') };
          });
          window.STAFF   = (js.staff||[]).map(function(s){
            return { id:s.id, name:s.name };
          });
          window.DATA = {
            devices: (js.devices||[]).map(function(d){
              return {
                dev_id: d.id,
                tacho_last: (d.latest_tacho!=null ? d.latest_tacho : null),
                hobbs_last: (d.latest_hobbs!=null ? d.latest_hobbs : null)
              };
            })
          };
        })
        .catch(function(){
          if(devWrap) devWrap.innerHTML='<div class="muted" style="padding:6px 4px;">Failed to load device reminders.</div>';
          if(staffWrap) staffWrap.innerHTML='<div class="muted" style="padding:6px 4px;">Failed to load personnel reminders.</div>';
          if(fuelMeta) fuelMeta.innerHTML='<span class="muted">Failed to load fuel status.</span>';
        });
    }
    load();
  })();
  </script>

  <!-- ================================================================
       R-09 — JS: Modal logic (options, compute, save, EDIT)
       ================================================================ -->
  <script>
  (function(){
    var btnOpen   = document.getElementById('addReminderBtn');
    var modal     = document.getElementById('remModal');
    var btnClose  = document.getElementById('remClose');
    var btnCancel = document.getElementById('remCancel');
    var btnSave   = document.getElementById('remSave');

    var fTargetType = document.getElementById('remTargetType');
    var fTargetId   = document.getElementById('remTargetId');
    var fName       = document.getElementById('remName');
    var fShortLabel = document.getElementById('remShortLabel'); // NEW
    var fTrackBy    = document.getElementById('remTrackBy');

    var rowDueNum   = document.getElementById('rowDueNum');
    var rowDueDate  = document.getElementById('rowDueDate');
    var fDueNum     = document.getElementById('remDueNum');
    var fDueDate    = document.getElementById('remDueDate');

    var fCurrent    = document.getElementById('remCurrent');
    var lblCurrent  = document.getElementById('lblCurrent');

    var fLastNum    = document.getElementById('remLastNum');
    var fLastDate   = document.getElementById('remLastDate');
    var btnLast     = document.getElementById('btnLastCompleted');

    var fIntVal     = document.getElementById('remIntervalValue');
    var fIntUnit    = document.getElementById('remIntervalUnit');
    var fCalMonth   = document.getElementById('remCalendarMonth');

    var fWarnVal    = document.getElementById('remWarnValue');
    var fWarnUnit   = document.getElementById('remWarnUnit');
    var lblWarn     = document.getElementById('lblWarn');

    var fNextNum    = document.getElementById('remNextNum');
    var fNextDate   = document.getElementById('remNextDate');

    var fPrimary    = document.getElementById('remPrimary');
    var fEmail      = document.getElementById('remEmail');
    var fSlack      = document.getElementById('remSlack');
    var fNotes      = document.getElementById('remNotes');

    var titleEl     = document.getElementById('remTitle');

    var CURRENT_REM_ID = null; // null => create, number => edit

    function show(){ modal.style.display='flex'; }
    function hide(){ modal.style.display='none'; CURRENT_REM_ID=null; titleEl.textContent='New Reminder'; }
    if(btnOpen)  btnOpen.addEventListener('click', function(){ openCreate(); }, false);
    if(btnClose) btnClose.addEventListener('click', hide, false);
    if(btnCancel)btnCancel.addEventListener('click', hide, false);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') hide(); }, false);
    document.addEventListener('click', function(e){ if(e.target===modal) hide(); }, false);

    function resetForm(){
      fTargetType.value='DEVICE';
      fTargetId.innerHTML='<option>Loading…</option>';
      fName.value='';
      fShortLabel.value='';
      fTrackBy.value='HOURS_TACHO';
      fDueNum.value=''; fDueDate.value='';
      fCurrent.value='—';
      fLastNum.value=''; fLastDate.value='';
      fIntVal.value=''; fIntUnit.value='HOURS'; fCalMonth.checked=false;
      fWarnVal.value=''; fWarnUnit.value='HOURS_TACHO';
      fNextNum.value=''; fNextDate.value='';
      fPrimary.checked=false; fEmail.checked=false; fSlack.checked=false; fNotes.value='';
      applyMode();
    }

    function loadOptions(cb){
      fetch('schedule.php?api=reminders_form_options')
        .then(function(r){ return r.json(); })
        .then(function(js){
          renderTargets('DEVICE', js);
          if(typeof cb==='function') cb(js);
          fTargetType.onchange = function(){
            renderTargets(fTargetType.value, js);
            deriveCurrent();
          };
        });
    }

    function renderTargets(kind, js){
      var list = (kind==='STAFF') ? (js.staff||[]) : (js.devices||[]);
      fTargetId.innerHTML='';
      if(!list.length){
        var o=document.createElement('option'); o.value=''; o.text='No items'; fTargetId.appendChild(o);
      }else{
        for(var i=0;i<list.length;i++){
          var o=document.createElement('option'); o.value=String(list[i].id); o.text=list[i].label; fTargetId.appendChild(o);
        }
      }
    }

    function applyMode(){
      var m = fTrackBy.value;
      var isDate = (m==='DATE');
      rowDueNum.style.display  = isDate ? 'none' : '';
      rowDueDate.style.display = isDate ? '' : 'none';
      if (m==='HOURS_TACHO'){ lblCurrent.textContent='Current (Tacho, derived)'; fWarnUnit.value='HOURS_TACHO'; }
      else if (m==='HOURS_HOBBS'){ lblCurrent.textContent='Current (Hobbs, derived)'; fWarnUnit.value='HOURS_HOBBS'; }
      else { lblCurrent.textContent='Current (not applicable for Date)'; fWarnUnit.value='DAYS'; }
      fLastNum.style.display  = isDate ? 'none' : '';
      fLastDate.style.display = isDate ? '' : 'none';
      if (isDate && (fIntUnit.value==='HOURS')) fIntUnit.value='DAYS';
    }
    fTrackBy.addEventListener('change', function(){ applyMode(); deriveCurrent(); computeNextDue(); }, false);
    fIntVal.addEventListener('input', computeNextDue, false);
    fIntUnit.addEventListener('change', computeNextDue, false);
    fLastNum.addEventListener('input', computeNextDue, false);
    fLastDate.addEventListener('change', computeNextDue, false);
    fCalMonth.addEventListener('change', computeNextDue, false);

    function deriveCurrent(){
      var cur = '—';
      if (typeof DATA==='object' && DATA.devices && fTargetType.value==='DEVICE'){
        var id = fTargetId.value;
        for (var i=0;i<DATA.devices.length;i++){
          var d = DATA.devices[i];
          if (String(d.dev_id)===String(id)){
            if (fTrackBy.value==='HOURS_TACHO' && d.tacho_last!=null) cur = String(d.tacho_last);
            if (fTrackBy.value==='HOURS_HOBBS' && d.hobbs_last!=null) cur = String(d.hobbs_last);
            break;
          }
        }
      }
      fCurrent.value = cur;
    }
    fTargetId.addEventListener('change', deriveCurrent, false);

    function computeNextDue(){
      var mode = fTrackBy.value;
      if (mode==='DATE'){
        var d = fLastDate.value;
        var n = parseInt(fIntVal.value||'0',10);
        var unit = fIntUnit.value;
        if (d && n>0){
          var base = new Date(d+'T12:00:00');
          if (unit==='DAYS'){ base.setDate(base.getDate()+n); }
          else { base.setMonth(base.getMonth()+n); if (fCalMonth.checked){ var y=base.getFullYear(), m=base.getMonth()+1; base = new Date(y, m, 0); } }
          fNextDate.value = base.toISOString().slice(0,10);
        }else fNextDate.value = '';
        fNextNum.value = '';
      }else{
        var last = parseFloat(fLastNum.value||'NaN');
        var iv   = parseFloat(fIntVal.value||'NaN');
        if (!isNaN(last) && !isNaN(iv)) fNextNum.value = (Math.round((last+iv)*10)/10).toFixed(1);
        else fNextNum.value = '';
        fNextDate.value = '';
      }
    }

    if (btnLast) {
      btnLast.addEventListener('click', function(){
        if (fTrackBy.value==='DATE'){
          var today = new Date(); fLastDate.value = today.toISOString().slice(0,10);
        }else{
          var v = fCurrent.value && fCurrent.value !== '—' ? fCurrent.value : '';
          fLastNum.value = v;
        }
        computeNextDue();
      }, false);
    }

    function openCreate(){
      CURRENT_REM_ID = null;
      titleEl.textContent = 'New Reminder';
      resetForm();
      loadOptions(function(){ deriveCurrent(); show(); });
    }

    // Expose for list buttons
    window.openEditReminder = function(kind, id){
      CURRENT_REM_ID = Number(id);
      titleEl.textContent = 'Edit Reminder';
      resetForm();
      loadOptions(function(js){
        // Load single reminder
        fetch('schedule.php?api=reminders_get&id='+encodeURIComponent(id))
          .then(function(r){ return r.json(); })
          .then(function(rem){
            // Target type/id
            fTargetType.value = rem.target_type || kind || 'DEVICE';
            // Repopulate target list for selected type then set value
            renderTargets(fTargetType.value, js || {});
            fTargetId.value = String(rem.target_id||'');
            // Fields
            fName.value = rem.name || '';
            fShortLabel.value = (rem.short_label||'').substring(0,4);
            fTrackBy.value = rem.track_by || 'HOURS_TACHO';
            applyMode();

            if (rem.track_by==='DATE'){
              fDueDate.value = rem.next_due_date || rem.due_date || '';
              fLastDate.value = rem.last_completed_date || '';
            }else{
              fDueNum.value  = (rem.next_due_num != null ? rem.next_due_num : (rem.due_num||''));
              fLastNum.value = (rem.last_completed_num != null ? rem.last_completed_num : '');
            }

            fIntVal.value  = rem.interval_value != null ? rem.interval_value : '';
            fIntUnit.value = rem.interval_unit || (rem.track_by==='DATE' ? 'DAYS' : 'HOURS');
            fCalMonth.checked = !!Number(rem.calendar_month||0);

            fWarnVal.value  = rem.warn_value != null ? rem.warn_value : '';
            fWarnUnit.value = rem.warn_unit || (rem.track_by==='DATE' ? 'DAYS' : (rem.track_by==='HOURS_HOBBS'?'HOURS_HOBBS':'HOURS_TACHO'));

            fPrimary.checked = !!Number(rem.primary_flag||0);
            fEmail.checked   = !!Number(rem.send_email||0);
            fSlack.checked   = !!Number(rem.send_slack||0);
            fNotes.value     = rem.notes || '';

            deriveCurrent();
            computeNextDue();
            show();
          });
      });
    };

    if (btnOpen){ btnOpen.addEventListener('click', openCreate, false); }

    if (btnSave){
      btnSave.addEventListener('click', function(){
        if(!fTargetId.value){ alert('Please choose a target.'); return; }
        if(!fName.value.trim()){ alert('Please enter a reminder name.'); return; }
        function fnum(v){ var n=parseFloat(v); return isNaN(n)? null : n; }

        var nextDate = document.getElementById('remNextDate').value || (fDueDate.value || null);
        var nextNum  = fnum(document.getElementById('remNextNum').value) ?? fnum(fDueNum.value);

        var body = {
          id: CURRENT_REM_ID || null,
          target_type: fTargetType.value,
          target_id: parseInt(fTargetId.value,10),
          name: fName.value.trim(),
          short_label: (fShortLabel.value || '').trim().substring(0,4), // NEW
          track_by: fTrackBy.value,

          due_num:  fnum(fDueNum.value),
          due_date: fDueDate.value || null,

          last_completed_num:  (fTrackBy.value==='DATE') ? null : fnum(fLastNum.value),
          last_completed_date: (fTrackBy.value==='DATE') ? (fLastDate.value||null) : null,

          interval_value: document.getElementById('remIntervalValue').value? parseInt(document.getElementById('remIntervalValue').value,10): null,
          interval_unit: document.getElementById('remIntervalUnit').value,
          calendar_month: document.getElementById('remCalendarMonth').checked?1:0,

          warn_value: document.getElementById('remWarnValue').value? parseInt(document.getElementById('remWarnValue').value,10): null,
          warn_unit: document.getElementById('remWarnUnit').value,

          next_due_num:  (fTrackBy.value==='DATE') ? null : (nextNum ?? null),
          next_due_date: (fTrackBy.value==='DATE') ? (nextDate || null) : null,

          primary_flag: document.getElementById('remPrimary').checked?1:0,
          send_email:  document.getElementById('remEmail').checked?1:0,
          send_slack:  document.getElementById('remSlack').checked?1:0,
          notes: document.getElementById('remNotes').value || null
        };

        var api = CURRENT_REM_ID ? 'reminders_update' : 'reminders_save';
        fetch('schedule.php?api='+api, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify(body)
        })
        .then(r=>r.json())
        .then(function(js){
          if(js.ok){ location.reload(); }
          else alert('Save failed: '+(js.error||'Unknown error'));
        });
      }, false);
    }
  })();
  </script>
</body>
</html>