// frontend-web/src/pages/ComplianceAudits.jsx
import React from "react"
import { apiGet, apiPost, apiPatch } from "../api"

// Badge now based on AUDIT CATEGORY (INTERNAL / CAA)
function auditCategoryBadge(cat) {
  switch ((cat || "").toUpperCase()) {
    case "CAA":
      return "bg-blue-100 text-blue-800"
    case "INTERNAL":
      return "bg-slate-100 text-slate-700"
    default:
      return "bg-slate-100 text-slate-600"
  }
}

const AUDIT_STATUS_OPTIONS = [
  "PLANNED",
  "IN_PROGRESS",
  "AWAITING_REPORT",
  "REPORT_RECEIVED",
  "INTERNAL_REVIEW",
  "CAP_IN_PROGRESS",
  "CAP_INTERNAL_REVIEW",
  "CAP_SUBMITTED",
  "CLOSED",
]

export default function ComplianceAudits() {
  const [audits, setAudits] = React.useState([])
  const [selected, setSelected] = React.useState(null)
  const [showNewAudit, setShowNewAudit] = React.useState(false)

  const loadAudits = React.useCallback(() => {
    apiGet("/compliance/audits")
      .then((data) => setAudits(Array.isArray(data) ? data : []))
      .catch((err) => {
        console.error("Failed to load audits", err)
        setAudits([])
      })
  }, [])

  React.useEffect(() => {
    loadAudits()
  }, [loadAudits])

  return (
    <div>
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-xl font-semibold">Audits</h2>
        <button
          onClick={() => setShowNewAudit(true)}
          className="px-3 py-1.5 rounded bg-ipcaBlue text-white text-sm"
        >
          + New Audit
        </button>
      </div>

      <div className="bg-white rounded-xl shadow overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-slate-100">
            <tr>
              <th className="px-2 py-1 border">Audit Category</th>
              <th className="px-2 py-1 border">Audit Reference</th>
              <th className="px-2 py-1 border">Audit Entity</th>
              <th className="px-2 py-1 border">Audit Type</th>
              <th className="px-2 py-1 border">Audit Status</th>
              <th className="px-2 py-1 border">Dates</th>
              <th className="px-2 py-1 border">Findings</th>
            </tr>
          </thead>

          <tbody>
            {audits.map((a) => (
              <tr
                key={a.id}
                className="hover:bg-slate-50 cursor-pointer"
                onClick={() => setSelected(a)}
              >
                <td className="border px-2 py-1">
                  <span
                    className={`px-2 py-0.5 rounded text-xs ${auditCategoryBadge(
                      a.audit_category
                    )}`}
                  >
                    {a.audit_category || "—"}
                  </span>
                </td>

                <td className="border px-2 py-1">{a.external_ref || "—"}</td>
                <td className="border px-2 py-1">{a.audit_entity || "—"}</td>
                <td className="border px-2 py-1">{a.audit_type || "—"}</td>
                <td className="border px-2 py-1">{a.status || "—"}</td>

                <td className="border px-2 py-1">
                  {a.start_date || "—"} – {a.end_date || "—"}
                </td>

                <td className="border px-2 py-1">
                  {a.findings_open ?? 0} / {a.findings_total ?? 0}
                </td>
              </tr>
            ))}

            {audits.length === 0 && (
              <tr>
                <td colSpan={7} className="text-center py-4 text-slate-500">
                  No audits recorded yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {selected && (
        <AuditModal
          audit={selected}
          onClose={() => setSelected(null)}
          onAuditSaved={() => loadAudits()}
        />
      )}

      {showNewAudit && (
        <NewAuditModal
          onClose={() => setShowNewAudit(false)}
          onCreated={() => {
            setShowNewAudit(false)
            loadAudits()
          }}
        />
      )}
    </div>
  )
}

function AuditModal({ audit, onClose, onAuditSaved }) {
  // ---------------------
  // Edit Audit
  // ---------------------
  const [auditForm, setAuditForm] = React.useState({
    external_ref: audit.external_ref || "",
    title: audit.title || "",
    audit_category: audit.audit_category || "CAA", // INTERNAL | CAA
    audit_entity: audit.audit_entity || "", // BCAA/FAA/EPC/SPC/CAA name
    audit_type: audit.audit_type || "CMS",
    status: audit.status || "PLANNED",
    subject: audit.subject || "",
    start_date: audit.start_date || "",
    end_date: audit.end_date || "",
    closed_date: audit.closed_date || "",
  })

  const [savingAudit, setSavingAudit] = React.useState(false)
  const [auditError, setAuditError] = React.useState(null)

  const handleAuditChange = (e) => {
    setAuditForm({ ...auditForm, [e.target.name]: e.target.value })
  }

  const handleSaveAudit = async () => {
    try {
      setSavingAudit(true)
      setAuditError(null)

      await apiPatch(`/compliance/audits/${audit.id}`, {
        external_ref: auditForm.external_ref,
        title: auditForm.title,
        audit_category: auditForm.audit_category,
        audit_entity: auditForm.audit_entity,
        audit_type: auditForm.audit_type,
        status: auditForm.status,
        subject: auditForm.subject || null,
        start_date: auditForm.start_date || null,
        end_date: auditForm.end_date || null,
        closed_date: auditForm.closed_date || null,
      })

      if (onAuditSaved) onAuditSaved()
      alert("Audit saved.")
    } catch (err) {
      console.error(err)
      setAuditError("Failed to save audit. Check Network → Response.")
    } finally {
      setSavingAudit(false)
    }
  }

  // ---------------------
  // Findings list + add
  // ---------------------
  const [findings, setFindings] = React.useState([])
  const [loadingFindings, setLoadingFindings] = React.useState(false)
  const [errorFindings, setErrorFindings] = React.useState(null)

  const [findingForm, setFindingForm] = React.useState({
    reference: "",
    title: "",
    classification: "LEVEL_2",
    severity: "MEDIUM",
    description: "",
    regulation_ref: "",
    domain_id: 1,
    target_date: "",
  })

  const loadFindings = React.useCallback(async () => {
    try {
      setLoadingFindings(true)
      setErrorFindings(null)
      const data = await apiGet(`/compliance/audits/${audit.id}/findings`)
      setFindings(Array.isArray(data) ? data : [])
    } catch (err) {
      console.error(err)
      setErrorFindings("Failed to load findings for this audit.")
      setFindings([])
    } finally {
      setLoadingFindings(false)
    }
  }, [audit.id])

  React.useEffect(() => {
    loadFindings()
  }, [loadFindings])

  const handleFindingChange = (e) => {
    setFindingForm({ ...findingForm, [e.target.name]: e.target.value })
  }

  const handleCreateFinding = async (e) => {
    e.preventDefault()
    try {
      const payload = {
        reference: findingForm.reference,
        title: findingForm.title,
        classification: findingForm.classification,
        severity: findingForm.severity,
        description: findingForm.description,
        regulation_ref: findingForm.regulation_ref || null,
        domain_id: Number(findingForm.domain_id) || 1,
        target_date: findingForm.target_date || null,
      }

      await apiPost(`/compliance/audits/${audit.id}/findings`, payload)

      setFindingForm({
        reference: "",
        title: "",
        classification: "LEVEL_2",
        severity: "MEDIUM",
        description: "",
        regulation_ref: "",
        domain_id: 1,
        target_date: "",
      })

      await loadFindings()
      if (onAuditSaved) onAuditSaved()
    } catch (err) {
      console.error(err)
      alert("Failed to create finding. Check Network → Response for details.")
    }
  }

  const handleExport = () => {
    window.open(
      `http://localhost:8888/compliance/audits/${audit.id}/report`,
      "_blank"
    )
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl shadow-lg max-w-5xl w-full max-h-[90vh] overflow-auto p-6">
        <div className="flex justify-between items-center mb-3">
          <h3 className="text-lg font-semibold">
            Audit {audit.external_ref || audit.id} – {audit.title}
          </h3>
          <button onClick={onClose} className="text-slate-500 text-sm">
            Close
          </button>
        </div>

        {/* EDIT AUDIT */}
        <section className="mb-5">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">Edit Audit</h4>
            <button
              onClick={handleSaveAudit}
              disabled={savingAudit}
              className="px-3 py-1 text-xs rounded bg-ipcaBlue text-white"
            >
              {savingAudit ? "Saving…" : "Save Audit"}
            </button>
          </div>

          {auditError && <p className="text-xs text-red-600 mb-2">{auditError}</p>}

          <div className="grid md:grid-cols-2 gap-2 text-sm">
            <div>
              <label className="block text-[11px] text-slate-600">Audit Reference</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="external_ref"
                value={auditForm.external_ref}
                onChange={handleAuditChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Title</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="title"
                value={auditForm.title}
                onChange={handleAuditChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Audit Category</label>
              <select
                className="w-full border rounded px-2 py-1 text-sm"
                name="audit_category"
                value={auditForm.audit_category}
                onChange={handleAuditChange}
              >
                <option value="CAA">CAA</option>
                <option value="INTERNAL">INTERNAL</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Audit Entity</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="audit_entity"
                value={auditForm.audit_entity}
                onChange={handleAuditChange}
                placeholder="BCAA / FAA / EPC / SPC / CAA name"
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Audit Type</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="audit_type"
                value={auditForm.audit_type}
                onChange={handleAuditChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Audit Status</label>
              <select
                className="w-full border rounded px-2 py-1 text-sm"
                name="status"
                value={auditForm.status}
                onChange={handleAuditChange}
              >
                {AUDIT_STATUS_OPTIONS.map((s) => (
                  <option key={s} value={s}>
                    {s.replaceAll("_", " ")}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Subject</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="subject"
                value={auditForm.subject}
                onChange={handleAuditChange}
              />
            </div>

            <div />

            <div>
              <label className="block text-[11px] text-slate-600">Start Date</label>
              <input
                type="date"
                className="w-full border rounded px-2 py-1 text-sm"
                name="start_date"
                value={auditForm.start_date}
                onChange={handleAuditChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">End Date</label>
              <input
                type="date"
                className="w-full border rounded px-2 py-1 text-sm"
                name="end_date"
                value={auditForm.end_date}
                onChange={handleAuditChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Closed Date</label>
              <input
                type="date"
                className="w-full border rounded px-2 py-1 text-sm"
                name="closed_date"
                value={auditForm.closed_date}
                onChange={handleAuditChange}
              />
            </div>
          </div>
        </section>

        {/* FINDINGS LIST */}
        <section className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">Findings</h4>
            <button
              onClick={loadFindings}
              className="px-2 py-1 text-xs rounded border"
            >
              Refresh
            </button>
          </div>

          {errorFindings && (
            <p className="text-xs text-red-600 mb-2">{errorFindings}</p>
          )}

          {loadingFindings ? (
            <p className="text-sm text-slate-500">Loading findings…</p>
          ) : findings.length === 0 ? (
            <p className="text-sm text-slate-500">No findings yet for this audit.</p>
          ) : (
            <div className="bg-white rounded shadow overflow-hidden">
              <table className="w-full text-sm">
                <thead className="bg-slate-100">
                  <tr>
                    <th className="border px-2 py-1">Ref</th>
                    <th className="border px-2 py-1">Title</th>
                    <th className="border px-2 py-1">Class</th>
                    <th className="border px-2 py-1">Severity</th>
                    <th className="border px-2 py-1">Deadline</th>
                    <th className="border px-2 py-1">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {findings.map((f) => (
                    <tr key={f.id} className="hover:bg-slate-50">
                      <td className="border px-2 py-1">{f.reference}</td>
                      <td className="border px-2 py-1">{f.title}</td>
                      <td className="border px-2 py-1">{f.classification}</td>
                      <td className="border px-2 py-1">{f.severity}</td>
                      <td className="border px-2 py-1">{f.target_date || "—"}</td>
                      <td className="border px-2 py-1">{f.status}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {/* Add Finding (styled like modal) */}
        <section className="mb-6">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">Add Finding</h4>
            <button
              type="submit"
              form="addFindingForm"
              className="px-3 py-1.5 rounded bg-ipcaBlue text-white text-xs"
            >
              Save Finding
            </button>
          </div>

          <form
            id="addFindingForm"
            onSubmit={handleCreateFinding}
            className="grid md:grid-cols-2 gap-3 text-sm"
          >
            <div>
              <label className="block text-[11px] text-slate-600">Reference</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="reference"
                placeholder="e.g. BCAA.ATO.NC.330"
                value={findingForm.reference}
                onChange={handleFindingChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Title</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="title"
                placeholder="Finding title"
                value={findingForm.title}
                onChange={handleFindingChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Classification</label>
              <select
                name="classification"
                value={findingForm.classification}
                onChange={handleFindingChange}
                className="w-full border rounded px-2 py-1 text-sm"
              >
                <option value="LEVEL_1">Level 1</option>
                <option value="LEVEL_2">Level 2</option>
                <option value="OBSERVATION">Observation</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Severity</label>
              <select
                name="severity"
                value={findingForm.severity}
                onChange={handleFindingChange}
                className="w-full border rounded px-2 py-1 text-sm"
              >
                <option value="LOW">LOW</option>
                <option value="MEDIUM">MEDIUM</option>
                <option value="HIGH">HIGH</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Deadline (target date)</label>
              <input
                type="date"
                className="w-full border rounded px-2 py-1 text-sm"
                name="target_date"
                value={findingForm.target_date}
                onChange={handleFindingChange}
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Domain ID</label>
              <input
                type="number"
                className="w-full border rounded px-2 py-1 text-sm"
                name="domain_id"
                value={findingForm.domain_id}
                onChange={handleFindingChange}
              />
            </div>

            <div className="md:col-span-2">
              <label className="block text-[11px] text-slate-600">Regulation Ref</label>
              <input
                className="w-full border rounded px-2 py-1 text-sm"
                name="regulation_ref"
                placeholder="e.g. ORA.GEN.200"
                value={findingForm.regulation_ref}
                onChange={handleFindingChange}
              />
            </div>

            <div className="md:col-span-2">
              <label className="block text-[11px] text-slate-600">Description</label>
              <textarea
                className="w-full border rounded px-2 py-1 text-sm"
                name="description"
                placeholder="Details of the finding..."
                value={findingForm.description}
                onChange={handleFindingChange}
                rows={4}
              />
            </div>
          </form>
        </section>

        <section className="mb-2">
          <button
            onClick={handleExport}
            className="px-3 py-2 rounded border text-sm"
          >
            Export Audit RCA/CAP as PDF
          </button>
        </section>
      </div>
    </div>
  )
}

function NewAuditModal({ onClose, onCreated }) {
  const [form, setForm] = React.useState({
    audit_category: "CAA",
    audit_entity: "BCAA",
    external_ref: "",
    audit_type: "FCL/ATO/Outstation",
    title: "",
    subject: "",
    start_date: "",
    end_date: "",
    status: "PLANNED",
  })
  const [saving, setSaving] = React.useState(false)
  const [error, setError] = React.useState(null)

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    try {
      setSaving(true)
      setError(null)

      const payload = {
        audit_category: form.audit_category,
        audit_entity: form.audit_entity,
        external_ref: form.external_ref,
        title: form.title,
        audit_type: form.audit_type,
        status: form.status,
        subject: form.subject || null,
        start_date: form.start_date || null,
        end_date: form.end_date || null,
      }

      await apiPost("/compliance/audits", payload)
      onCreated()
    } catch (err) {
      console.error(err)
      setError("Could not create audit.")
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl shadow-lg max-w-lg w-full max-h-[90vh] overflow-auto p-6">
        <div className="flex justify-between items-center mb-3">
          <h3 className="text-lg font-semibold">New Audit</h3>
          <button onClick={onClose} className="text-slate-500 text-sm">
            Close
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-3 text-sm">
          {error && <p className="text-xs text-red-600">{error}</p>}

          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="block text-[11px] text-slate-600">Audit Category</label>
              <select
                name="audit_category"
                value={form.audit_category}
                onChange={handleChange}
                className="w-full border rounded px-2 py-1 text-xs"
              >
                <option value="CAA">CAA</option>
                <option value="INTERNAL">INTERNAL</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Audit Entity</label>
              <input
                name="audit_entity"
                value={form.audit_entity}
                onChange={handleChange}
                className="w-full border rounded px-2 py-1 text-xs"
                placeholder="BCAA / FAA / EPC / SPC / CAA name"
              />
            </div>
          </div>

          <div>
            <label className="block text-[11px] text-slate-600">Audit Type</label>
            <input
              name="audit_type"
              value={form.audit_type}
              onChange={handleChange}
              className="w-full border rounded px-2 py-1 text-xs"
            />
          </div>

          <div>
            <label className="block text-[11px] text-slate-600">Audit Reference</label>
            <input
              name="external_ref"
              value={form.external_ref}
              onChange={handleChange}
              className="w-full border rounded px-2 py-1 text-xs"
            />
          </div>

          <div>
            <label className="block text-[11px] text-slate-600">Title</label>
            <input
              name="title"
              value={form.title}
              onChange={handleChange}
              className="w-full border rounded px-2 py-1 text-xs"
            />
          </div>

          <div>
            <label className="block text-[11px] text-slate-600">Subject / Scope</label>
            <input
              name="subject"
              value={form.subject}
              onChange={handleChange}
              className="w-full border rounded px-2 py-1 text-xs"
            />
          </div>

          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="block text-[11px] text-slate-600">Start Date</label>
              <input
                type="date"
                name="start_date"
                value={form.start_date}
                onChange={handleChange}
                className="w-full border rounded px-2 py-1 text-xs"
              />
            </div>
            <div>
              <label className="block text-[11px] text-slate-600">End Date</label>
              <input
                type="date"
                name="end_date"
                value={form.end_date}
                onChange={handleChange}
                className="w-full border rounded px-2 py-1 text-xs"
              />
            </div>
          </div>

          <div>
            <label className="block text-[11px] text-slate-600">Audit Status</label>
            <select
              name="status"
              value={form.status}
              onChange={handleChange}
              className="w-full border rounded px-2 py-1 text-xs"
            >
              {AUDIT_STATUS_OPTIONS.map((s) => (
                <option key={s} value={s}>
                  {s.replaceAll("_", " ")}
                </option>
              ))}
            </select>
          </div>

          <button
            type="submit"
            disabled={saving}
            className="mt-3 px-3 py-1.5 rounded bg-ipcaBlue text-white text-xs"
          >
            {saving ? "Creating…" : "Create Audit"}
          </button>
        </form>
      </div>
    </div>
  )
}