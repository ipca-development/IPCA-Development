<!DOCTYPE html>
<html>
<head>
    <title>IPCA Compliance API Test UI</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        section { margin-bottom: 40px; padding: 20px; border: 1px solid #ccc; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; }
        button { padding: 10px 15px; }
        pre { background: #f8f8f8; padding: 10px; }
    </style>
</head>
<body>

<h1>IPCA Compliance API – Browser Test Page</h1>
<p>You can now test ALL endpoints directly from your browser.</p>

<hr>

<!-- ======================================================= -->
<!-- 1. CREATE AUDIT                                         -->
<!-- ======================================================= -->
<section>
<h2>1. Create Audit (POST /compliance/audits)</h2>

<form id="createAuditForm">
    <label>Title</label>
    <input type="text" name="title" value="Internal Test Audit">

    <label>Authority</label>
    <input type="text" name="authority" value="INTERNAL">

    <label>Audit Type</label>
    <input type="text" name="audit_type" value="CMS">

    <label>External Ref (optional)</label>
    <input type="text" name="external_ref" value="INT-TEST-001">

    <label>Subject</label>
    <input type="text" name="subject" value="Testing API">

    <label>Created By (UUID)</label>
    <input type="text" name="created_by" value="415712F4-C7D8-11F0-AD9A-84068FBD07E7">

    <button type="button" onclick="createAudit()">Create Audit</button>
</form>

<pre id="createAuditResult"></pre>
</section>

<hr>

<!-- ======================================================= -->
<!-- 2. LIST AUDITS                                          -->
<!-- ======================================================= -->
<section>
<h2>2. List Audits (GET /compliance/audits)</h2>

<button onclick="listAudits()">Load Audits</button>
<pre id="listAuditsResult"></pre>
</section>

<hr>

<!-- ======================================================= -->
<!-- 3. CREATE FINDING                                       -->
<!-- ======================================================= -->
<section>
<h2>3. Create Finding (POST /compliance/audits/{id}/findings)</h2>

<form id="createFindingForm">

    <label>Audit ID (UUID)</label>
    <input type="text" name="audit_id" placeholder="Paste audit ID here">

    <label>Reference</label>
    <input type="text" name="reference" value="BCAA.ATO.NC.330">

    <label>Title</label>
    <input type="text" name="title" value="Missing AM Responsibilities">

    <label>Classification</label>
    <input type="text" name="classification" value="LEVEL_2">

    <label>Severity</label>
    <input type="text" name="severity" value="MEDIUM">

    <label>Description</label>
    <textarea name="description">Details of the finding...</textarea>

    <label>Regulation Ref</label>
    <input type="text" name="regulation_ref" value="ORA.GEN.200">

    <label>Domain ID</label>
    <input type="number" name="domain_id" value="1">

    <button type="button" onclick="createFinding()">Create Finding</button>

</form>
<pre id="createFindingResult"></pre>
</section>

<hr>

<!-- ======================================================= -->
<!-- 4. ADD ACTION                                           -->
<!-- ======================================================= -->
<section>
<h2>4. Add Corrective/Preventive Action</h2>

<form id="createActionForm">

    <label>Finding ID (UUID)</label>
    <input type="text" name="finding_id" placeholder="Paste finding ID here">

    <label>Action Type</label>
    <input type="text" name="action_type" value="CORRECTIVE">

    <label>Description</label>
    <textarea name="description">Fix the issue by updating OMM...</textarea>

    <label>Responsible ID</label>
    <input type="text" name="responsible_id" value="415712F4-C7D8-11F0-AD9A-84068FBD07E7">

    <label>Due Date</label>
    <input type="text" name="due_date" value="2026-01-15">

    <button type="button" onclick="createAction()">Add Action</button>
</form>

<pre id="createActionResult"></pre>
</section>

<hr>

<!-- ======================================================= -->
<!-- 5. ADD RCA                                              -->
<!-- ======================================================= -->
<section>
<h2>5. Add RCA (5-Whys)</h2>

<form id="createRcaForm">
    <label>Finding ID (UUID)</label>
    <input type="text" name="finding_id">

    <label>Why 1</label>
    <input type="text" name="why1" value="Manual was not updated">

    <label>Why 2</label>
    <input type="text" name="why2" value="No assigned responsibility">

    <label>Why 3</label>
    <input type="text" name="why3" value="No formal change process">

    <label>Why 4</label>
    <input type="text" name="why4" value="Lack of oversight">

    <label>Why 5</label>
    <input type="text" name="why5" value="ATO lacked ORA.GEN.130 procedure">

    <label>Root Cause</label>
    <input type="text" name="root_cause" value="Missing change management procedure">

    <label>Preventive Theme</label>
    <input type="text" name="preventive_theme" value="Implement ORA.GEN.130 compliant CM system">

    <label>Created By</label>
    <input type="text" name="created_by" value="415712F4-C7D8-11F0-AD9A-84068FBD07E7">

    <button type="button" onclick="createRca()">Submit RCA</button>
</form>

<pre id="createRcaResult"></pre>
</section>

<script>
const api = "http://localhost:8888";

async function createAudit() {
    const data = Object.fromEntries(new FormData(document.getElementById("createAuditForm")));
    const res = await fetch(api + "/compliance/audits", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    document.getElementById("createAuditResult").textContent = await res.text();
}

async function listAudits() {
    const res = await fetch(api + "/compliance/audits");
    document.getElementById("listAuditsResult").textContent = await res.text();
}

async function createFinding() {
    const form = document.getElementById("createFindingForm");
    const data = Object.fromEntries(new FormData(form));
    const auditId = data.audit_id;
    delete data.audit_id;
    const res = await fetch(api + "/compliance/audits/" + auditId + "/findings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    document.getElementById("createFindingResult").textContent = await res.text();
}

async function createAction() {
    const data = Object.fromEntries(new FormData(document.getElementById("createActionForm")));
    const findingId = data.finding_id;
    delete data.finding_id;
    const res = await fetch(api + "/compliance/findings/" + findingId + "/actions", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    document.getElementById("createActionResult").textContent = await res.text();
}

async function createRca() {
    const data = Object.fromEntries(new FormData(document.getElementById("createRcaForm")));
    const findingId = data.finding_id;
    delete data.finding_id;
    const res = await fetch(api + "/compliance/findings/" + findingId + "/rca", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
    document.getElementById("createRcaResult").textContent = await res.text();
}
</script>

</body>
</html>