// frontend-web/src/pages/ComplianceAudits.jsx
import React from "react"
import { apiGet, apiPost } from "../api"

function categoryBadge(cat) {
  switch (cat) {
    case "BCAA":
      return "bg-blue-100 text-blue-800"
    case "FAA":
      return "bg-green-100 text-green-800"
    case "INTERNAL":
      return "bg-slate-100 text-slate-700"
    case "FSTD":
      return "bg-purple-100 text-purple-800"
    default:
      return "bg-slate-100 text-slate-600"
  }
}

export default function ComplianceAudits() {
  const [audits, setAudits] = React.useState([])
  const [selected, setSelected] = React.useState(null)
  const [showNewAudit, setShowNewAudit] = React.useState(false)

  const loadAudits = React.useCallback(() => {
    apiGet("/compliance/audits")
      .then(setAudits)
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
              <th className="px-2 py-1 border">Category</th>
              <th className="px-2 py-1 border">Ref</th>
              <th className="px-2 py-1 border">Authority</th>
              <th className="px-2 py-1 border">Title</th>
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
                    className={`px-2 py-0.5 rounded text-xs ${categoryBadge(
                      a.category
                    )}`}
                  >
                    {a.category || "—"}
                  </span>
                </td>
                <td className="border px-2 py-1">{a.external_ref || "—"}</td>
                <td className="border px-2 py-1">{a.authority || "—"}</td>
                <td className="border px-2 py-1">{a.title}</td>
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
                <td colSpan={6} className="text-center py-4 text-slate-500">
                  No audits recorded yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {selected && (
        <AuditModal audit={selected} onClose={() => setSelected(null)} />
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

function AuditModal({ audit, onClose }) {
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
      setFindings(data)
    } catch (err) {
      console.error(err)
      setErrorFindings("Failed to load findings for this audit.")
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

      // reset minimal fields
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

        {/* Audit details */}
        <section className="mb-4">
          <h4 className="font-semibold text-sm mb-1">Details</h4>
          <p className="text-sm">
            Authority: {audit.authority || "—"} <br />
            Type: {audit.audit_type || "—"} <br />
            Dates: {audit.start_date || "—"} – {audit.end_date || "—"} <br />
            Subject: {audit.subject || "—"}
          </p>
        </section>

        {/* Findings list */}
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

        {/* Add finding form */}
        <section className="mb-6">
          <h4 className="font-semibold text-sm mb-2">Add Finding</h4>
          <form onSubmit={handleCreateFinding} className="grid md:grid-cols-2 gap-3">
            <input
              className="border rounded px-2 py-1 text-sm"
              name="reference"
              placeholder="Reference (e.g. NC.330)"
              value={findingForm.reference}
              onChange={handleFindingChange}
            />
            <input
              className="border rounded px-2 py-1 text-sm"
              name="title"
              placeholder="Finding title"
              value={findingForm.title}
              onChange={handleFindingChange}
            />

            <input
              className="border rounded px-2 py-1 text-sm"
              name="classification"
              value={findingForm.classification}
              onChange={handleFindingChange}
              placeholder="LEVEL_1 / LEVEL_2 / OBSERVATION"
            />
            <input
              className="border rounded px-2 py-1 text-sm"
              name="severity"
              value={findingForm.severity}
              onChange={handleFindingChange}
              placeholder="LOW / MEDIUM / HIGH"
            />

            <input
              className="border rounded px-2 py-1 text-sm"
              name="regulation_ref"
              value={findingForm.regulation_ref}
              onChange={handleFindingChange}
              placeholder="Regulation ref (e.g. ORA.GEN.200)"
            />
            <input
              type="number"
              className="border rounded px-2 py-1 text-sm"
              name="domain_id"
              value={findingForm.domain_id}
              onChange={handleFindingChange}
              placeholder="Domain ID"
            />

            <input
              type="date"
              className="border rounded px-2 py-1 text-sm"
              name="target_date"
              value={findingForm.target_date}
              onChange={handleFindingChange}
              placeholder="Target date"
            />

            <textarea
              className="border rounded px-2 py-1 text-sm md:col-span-2"
              name="description"
              placeholder="Description"
              value={findingForm.description}
              onChange={handleFindingChange}
              rows={3}
            />

            <button className="bg-ipcaBlue text-white px-3 py-2 rounded text-sm md:col-span-2">
              Save Finding
            </button>
          </form>
        </section>

        {/* Audit-level actions */}
        <section className="mb-4">
          <h4 className="font-semibold text-sm mb-1">Audit-Level RCA & CAP (AI)</h4>
          <p className="text-xs text-slate-500 mb-2">
            These buttons will later aggregate finding-level RCA/CAP into audit summaries.
          </p>
          <div className="space-x-2">
            <button className="px-3 py-1 rounded bg-ipcaBlue text-white text-xs">
              Generate Audit RCA Summary (AI)
            </button>
            <button className="px-3 py-1 rounded bg-ipcaBlue text-white text-xs">
              Generate Audit CAP Summary (AI)
            </button>
            <button onClick={handleExport} className="px-3 py-1 rounded border text-xs">
              Export Audit RCA/CAP as PDF
            </button>
          </div>
        </section>
      </div>
    </div>
  )
}

function NewAuditModal({ onClose, onCreated }) {
  const [form, setForm] = React.useState({
    category: "BCAA",
    external_ref: "",
    authority: "BCAA",
    audit_type: "FCL/ATO/Outstation",
    title: "",
    subject: "",
    start_date: "",
    end_date: "",
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
        external_ref: form.external_ref,
        title: form.title,
        authority: form.authority,
        audit_type: form.audit_type,
        subject: form.subject,
        start_date: form.start_date || null,
        end_date: form.end_date || null,
        category: form.category,
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
              <label className="block text-[11px] text-slate-600">Category</label>
              <select
                name="category"
                value={form.category}
                onChange={handleChange}
                className="w-full border rounded px-2 py-1 text-xs"
              >
                <option value="BCAA">BCAA</option>
                <option value="FAA">FAA</option>
                <option value="INTERNAL">Internal</option>
                <option value="FSTD">FSTD</option>
              </select>
            </div>
            <div>
              <label className="block text-[11px] text-slate-600">Authority</label>
              <input
                name="authority"
                value={form.authority}
                onChange={handleChange}
                className="w-full border rounded px-2 py-1 text-xs"
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
            <label className="block text-[11px] text-slate-600">External Ref</label>
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