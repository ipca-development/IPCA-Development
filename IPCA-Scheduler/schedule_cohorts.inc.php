<?php
// schedule_cohorts.inc.php
?>
<style>
  /* Cohort students panel — blends with existing schedule styles */
  .cohortCard{background:#fff;border:1px solid #dde3f0;border-radius:12px;padding:12px;margin-top:12px}
  .coRow{display:flex;gap:12px;flex-wrap:wrap}
  .coCol{flex:1;min-width:260px}
  .coLabel{font-weight:600;margin:0 0 6px 0;color:#1a1f36}
  .coSelect, .coSearch{width:100%;padding:8px;border:1px solid #cfd5e3;border-radius:8px}
  .coList{max-height:340px;overflow:auto;border:1px solid #e5e9f2;border-radius:10px;padding:8px}
  .coGroup{margin:8px 0}
  .coGroup h4{margin:6px 0 4px 0;font-size:13px;color:#475569}
  .coItem{display:flex;justify-content:space-between;align-items:center;padding:6px 8px;border-radius:8px}
  .coItem:nth-child(odd){background:#f9fbff}
  .coName{font-size:14px}
  .coBtn{padding:6px 10px;border-radius:8px;border:1px solid #1e3c72;background:#1e3c72;color:#fff;cursor:pointer;font-size:12px}
  .coSmall{font-size:12px;color:#64748b;margin-left:8px}
</style>

<div class="cohortCard" id="cohortStudentsPanel">
  <div style="font-weight:700;margin-bottom:8px">Cohort Students</div>

  <div class="coRow">
    <div class="coCol" style="max-width:380px">
      <div class="coLabel">Choose cohort(s)</div>
      <select id="cohortPicker" class="coSelect" multiple size="8">
        <option>Loading…</option>
      </select>
      <div class="coSmall">Tip: hold ⌘/Ctrl to select multiple.</div>
    </div>

    <div class="coCol">
      <div class="coLabel">Students in selected cohort(s)</div>
      <input id="coSearch" class="coSearch" type="search" placeholder="Filter by name…">
      <div id="coStudentList" class="coList"><em>Select one or more cohorts…</em></div>
    </div>
  </div>
</div>

<script>
(function(){
  // Safe guard if schedule embeds multiple scripts
  if (window.__cohortPanelBooted) return; window.__cohortPanelBooted = true;

  // --- Helpers
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function api(url){ return fetch(url).then(function(r){return r.json();}); }

  var $pick = document.getElementById('cohortPicker');
  var $list = document.getElementById('coStudentList');
  var $search = document.getElementById('coSearch');

  // --- Load cohorts list
  function loadCohorts(){
    api('?api=cohort_list').then(function(js){
      if(!js || !js.ok){ $pick.innerHTML='<option>Failed to load</option>'; return; }
      // Active first, then by start desc, then name
      var rows = (js.cohorts||[]).slice().sort(function(a,b){
        if ((b.active|0)!==(a.active|0)) return (b.active|0)-(a.active|0);
        if (a.start_date!==b.start_date) return String(b.start_date).localeCompare(String(a.start_date));
        return String(a.name||'').localeCompare(String(b.name||''));
      });
      $pick.innerHTML='';
      for (var i=0;i<rows.length;i++){
        var c=rows[i], opt=document.createElement('option');
        opt.value = c.id;
        opt.text  = (c.active?'● ':'○ ') + (c.name||'') + (c.program?(' — '+c.program):'') +
                    (c.start_date?('  ('+c.start_date+(c.end_date?(' → '+c.end_date):'')+')'):'');
        $pick.appendChild(opt);
      }
    });
  }

  // --- Load members for selected cohorts, group by cohort, dedupe by user_id, sort by first name
  function loadStudentsForSelection(){
    var opts = $pick ? $pick.options : [];
    var picked = [];
    for (var i=0;i<opts.length;i++){ if(opts[i].selected && opts[i].value) picked.push(parseInt(opts[i].value,10)); }
    if (!picked.length){ $list.innerHTML='<em>Select one or more cohorts…</em>'; return; }

    // Fetch each cohort's members; then render
    var calls = picked.map(function(id){ return api('?api=cohort_members&cohort_id='+encodeURIComponent(id)).then(function(js){ return {id:id, js:js}; }); });
    Promise.all(calls).then(function(parts){
      // Build a map cohortId -> {name, members[]}, and a global dedupe map by user_id
      var byCohort = {};
      var all = {};
      // Need names of cohorts for group headings:
      var look = {};
      for (var i=0;i<$pick.options.length;i++){ look[$pick.options[i].value] = $pick.options[i].text; }

      for (var p=0;p<parts.length;p++){
        var cid = String(parts[p].id);
        var js  = parts[p].js||{};
        var members = (js.members||[]).slice().sort(function(a,b){
          return String(a.first_name||'').localeCompare(String(b.first_name||''));
        });
        byCohort[cid] = { label: look[cid]||('Cohort #'+cid), members: members };
        for (var m=0;m<members.length;m++){
          var u = members[m];
          if (!u || u.user_id==null) continue;
          all[String(u.user_id)] = u;
        }
      }

      renderStudents(byCohort, all);
    });
  }

  function renderStudents(byCohort, allMap){
    var filter = ($search.value||'').trim().toLowerCase();
    var html = '';

    // Grouped by cohort (left labels from picker)
    var keys = Object.keys(byCohort);
    keys.sort(function(a,b){ return byCohort[a].label.localeCompare(byCohort[b].label); });

    for (var i=0;i<keys.length;i++){
      var k = keys[i], grp = byCohort[k];
      var list = grp.members||[];

      // Apply filter
      var vis = [];
      for (var j=0;j<list.length;j++){
        var nm = ((list[j].first_name||'')+' '+(list[j].last_name||'')).replace(/\s+/g,' ').trim();
        if (!filter || nm.toLowerCase().indexOf(filter)>=0) vis.push(list[j]);
      }
      if (!vis.length) continue;

      html += '<div class="coGroup">';
      html += '<h4>'+esc(grp.label)+'</h4>';
      for (var t=0;t<vis.length;t++){
        var s = vis[t];
        var nm = ((s.first_name||'')+' '+(s.last_name||'')).replace(/\s+/g,' ').trim();
        html += ''+
          '<div class="coItem">'+
            '<div class="coName">'+esc(nm)+'</div>'+
            '<button class="coBtn" data-newres="'+esc(String(s.user_id))+'" title="Create reservation">New</button>'+
          '</div>';
      }
      html += '</div>';
    }

    if (!html) html = '<em>No students match the filter.</em>';
    $list.innerHTML = html;
  }

  // --- Click: “New” → open your existing modal with student pre-selected
  document.addEventListener('click', function(e){
    if (e.target && e.target.getAttribute('data-newres')){
      var uid = parseInt(e.target.getAttribute('data-newres'),10)||0;
      if (!uid) return;

      // Use your existing helper to open modal
      if (typeof openModalPrefill === 'function'){
        openModalPrefill(null,null,10,0,null); // standard 10:00-11:00 block
        // After options load, select the student
        var tries=0, t=setInterval(function(){
          tries++;
          try{
            if (window.f_student && f_student.options && f_student.options.length){
              // Select only this student
              for (var i=0;i<f_student.options.length;i++){
                var o=f_student.options[i];
                o.selected = (String(o.value)===String(uid));
              }
              if (typeof updateMissionField==='function') updateMissionField();
              clearInterval(t);
            }
          }catch(_){}
          if (tries>40) clearInterval(t); // ~2.4s safety
        }, 60);
      } else {
        alert('Reservation modal not available on this page.');
      }
    }
  });

  // --- Events
  if ($pick){
    $pick.addEventListener('change', loadStudentsForSelection, false);
  }
  if ($search){
    $search.addEventListener('input', function(){
      // Redisplay from the last fetched groups with filter; if nothing yet, just reload
      loadStudentsForSelection();
    }, false);
  }

  // Boot
  loadCohorts();
})();
</script>