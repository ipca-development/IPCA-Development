// frontend-web/src/pages/ComplianceDashboard.jsx
import React from "react"
import { apiGet, apiPost, apiPatch } from "../api"

function severityColor(sev) {
  switch (sev) {
    case "LEVEL_1":
      return "bg-red-600 text-white"
    case "LEVEL_2":
      return "bg-orange-500 text-white"
    case "OBSERVATION":
      return "bg-yellow-400 text-black"
    default:
      return "bg-slate-300 text-slate-800"
  }
}

export default function ComplianceDashboard() {
  const [findings, setFindings] = React.useState([])
  const [selected, setSelected] = React.useState(null)

  const loadFindings = React.useCallback(() => {
    apiGet("/compliance/findings?status=open")
      .then(setFindings)
      .catch(console.error)
  }, [])

  React.useEffect(() => {
    loadFindings()
  }, [loadFindings])

  const sorted = [...findings].sort((a, b) => {
    const sevOrder = { LEVEL_1: 1, LEVEL_2: 2, OBSERVATION: 3, INFORMATION: 4 }
    const sa = sevOrder[a.classification] || 99
    const sb = sevOrder[b.classification] || 99
    if (sa !== sb) return sa - sb
    const da = a.target_date || "9999-12-31"
    const db = b.target_date || "9999-12-31"
    return da.localeCompare(db)
  })

  return (
    <div>
      <h2 className="text-xl font-semibold mb-4">Open Findings</h2>

      <div className="bg-white rounded-xl shadow overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-slate-100">
            <tr>
              <th className="px-2 py-1 border">Level</th>
              <th className="px-2 py-1 border">Category</th>
              <th className="px-2 py-1 border">Audit</th>
              <th className="px-2 py-1 border">Ref</th>
              <th className="px-2 py-1 border">Summary</th>
              <th className="px-2 py-1 border">Deadline</th>
              <th className="px-2 py-1 border">RCA</th>
              <th className="px-2 py-1 border">CAP</th>
            </tr>
          </thead>
          <tbody>
            {sorted.map((f) => (
              <tr
                key={f.id}
                className="hover:bg-slate-50 cursor-pointer"
                onClick={() => setSelected(f)}
              >
                <td className="border px-2 py-1">
                  <span
                    className={`px-2 py-0.5 rounded text-xs ${severityColor(
                      f.classification
                    )}`}
                  >
                    {f.classification}
                  </span>
                </td>
                <td className="border px-2 py-1">{f.category || "—"}</td>
                <td className="border px-2 py-1">{f.audit_ref || "—"}</td>
                <td className="border px-2 py-1">{f.reference}</td>
                <td className="border px-2 py-1">{f.title}</td>
                <td className="border px-2 py-1">{f.target_date || "—"}</td>
                <td className="border px-2 py-1">{f.rca_progress || "0/5"}</td>
                <td className="border px-2 py-1">
                  {f.cap_progress || "0 actions"}
                </td>
              </tr>
            ))}
            {sorted.length === 0 && (
              <tr>
                <td colSpan={8} className="text-center py-4 text-slate-500">
                  No open findings.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {selected && (
        <FindingModal
          finding={selected}
          onClose={() => setSelected(null)}
          onSaved={() => loadFindings()}
        />
      )}
    </div>
  )
}

// Modal with Edit + RCA + CAP + Manual/MCCF + Notes
function FindingModal({ finding, onClose, onSaved }) {
  // -----------------------
  // EDIT FINDING (B2)
  // -----------------------
  const [editForm, setEditForm] = React.useState({
    reference: finding.reference || "",
    title: finding.title || "",
    classification: finding.classification || "LEVEL_2",
    severity: finding.severity || "MEDIUM",
    target_date: finding.target_date || "",
    regulation_ref: finding.regulation_ref || "",
    domain_id: finding.domain_id || 1,
    status: finding.status || "OPEN",
    description: finding.description || "",
  })
  const [savingEdit, setSavingEdit] = React.useState(false)
  const [editError, setEditError] = React.useState(null)

  const handleEditChange = (e) => {
    setEditForm({ ...editForm, [e.target.name]: e.target.value })
  }

  const handleSaveFinding = async () => {
    try {
      setSavingEdit(true)
      setEditError(null)

      const payload = {
        reference: editForm.reference,
        title: editForm.title,
        classification: editForm.classification,
        severity: editForm.severity,
        target_date: editForm.target_date || null,
        regulation_ref: editForm.regulation_ref || null,
        domain_id: Number(editForm.domain_id) || 1,
        status: editForm.status,
        description: editForm.description,
      }

      await apiPatch(`/compliance/findings/${finding.id}`, payload)

      if (onSaved) onSaved()
      alert("Finding saved.")
    } catch (err) {
      console.error(err)
      setEditError("Failed to save finding (PATCH). Check Network → Response.")
    } finally {
      setSavingEdit(false)
    }
  }

  // -----------------------
  // RCA
  // -----------------------
  const [rcaSteps, setRcaSteps] = React.useState([])
  const [rcaLoading, setRcaLoading] = React.useState(false)
  const [rcaError, setRcaError] = React.useState(null)

  // -----------------------
  // CAP
  // -----------------------
  const [capOptions, setCapOptions] = React.useState([])
  const [capLoading, setCapLoading] = React.useState(false)
  const [capError, setCapError] = React.useState(null)

  // -----------------------
  // Manual/MCCF/Notes
  // -----------------------
  const [manualRef, setManualRef] = React.useState({
    manualType: "",
    section: "",
  })
  const [mccfOptions, setMccfOptions] = React.useState([])
  const [mccfId, setMccfId] = React.useState("")
  const [notes, setNotes] = React.useState(finding.notes || "")
  const [savingNotes, setSavingNotes] = React.useState(false)

  React.useEffect(() => {
    apiGet("/compliance/mccf")
      .then(setMccfOptions)
      .catch(() => {})
	  
	apiGet(`/compliance/findings/${finding.id}/rca`)
  .then((res) => setRcaSteps(res.steps || []))
  .catch(() => {})  
	  
  }, [finding.id])

  // RCA: generate next step
  const handleGenerateNextRcaStep = async () => {
  // Prevent more than 5 whys
  if (rcaSteps.length >= 5) return

  try {
    setRcaLoading(true)

    const res = await apiPost(
      `/compliance/findings/${finding.id}/rca/next-step`,
      { steps: rcaSteps }
    )

    // Validate the response
    if (!res || !res.question || !res.answer) {
      throw new Error("Invalid RCA step response")
    }

    // Success: clear error and append step
    setRcaError(null)
    setRcaSteps([...rcaSteps, res])
  } catch (err) {
    console.error(err)
    setRcaError("Could not generate next RCA step.")
  } finally {
    setRcaLoading(false)
  }
}

  const handleRcaAnswerChange = (idx, newAnswer) => {
    const updated = rcaSteps.map((s, i) =>
      i === idx ? { ...s, answer: newAnswer } : s
    )
    setRcaSteps(updated)
  }

  const handleSaveRca = async () => {
    try {
      setRcaLoading(true)
      setRcaError(null)
      await apiPost(`/compliance/findings/${finding.id}/rca`, {
        steps: rcaSteps,
      })
      alert("RCA saved.")
    } catch (err) {
      console.error(err)
      setRcaError("Could not save RCA.")
    } finally {
      setRcaLoading(false)
    }
  }

  // CAP: generate AI options
  const handleGenerateCap = async () => {
    try {
      setCapLoading(true)
      setCapError(null)
      const res = await apiPost(
        `/compliance/findings/${finding.id}/actions/suggest-ai`,
        { rcaSteps }
      )
      setCapOptions(res.options || [])
    } catch (err) {
      console.error(err)
      setCapError("Could not generate CAP options.")
    } finally {
      setCapLoading(false)
    }
  }

  const handleAdoptCapOption = async (opt) => {
    try {
      setCapLoading(true)
      setCapError(null)
      await apiPost(`/compliance/findings/${finding.id}/actions`, {
        option: opt,
      })
      alert("CAP actions created from selected option.")
    } catch (err) {
      console.error(err)
      setCapError("Could not adopt CAP option.")
    } finally {
      setCapLoading(false)
    }
  }

  // Manual + MCCF
  const handleSaveManualMccf = async () => {
    try {
      if (manualRef.manualType && manualRef.section) {
        await apiPost(`/compliance/findings/${finding.id}/manual-ref`, manualRef)
      }
      if (mccfId) {
        await apiPost(`/compliance/findings/${finding.id}/mccf-link`, { mccfId })
      }
      alert("Manual and MCCF references saved.")
    } catch (err) {
      console.error(err)
      alert("Failed to save manual/MCCF references.")
    }
  }

  // Notes
  const handleSaveNotes = async () => {
    try {
      setSavingNotes(true)
      await apiPost(`/compliance/findings/${finding.id}/notes`, { notes })
      alert("Notes saved.")
    } catch (err) {
      console.error(err)
      alert("Failed to save notes.")
    } finally {
      setSavingNotes(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl shadow-lg max-w-5xl w-full max-h-[90vh] overflow-auto p-6">
        <div className="flex justify-between items-center mb-3">
          <h3 className="text-lg font-semibold">
            Finding {finding.reference} – {finding.title}
          </h3>
          <button onClick={onClose} className="text-slate-500 text-sm">
            Close
          </button>
        </div>

        {/* EDIT SECTION */}
        <section className="mb-5">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">Edit Finding</h4>
            <button
              onClick={handleSaveFinding}
              disabled={savingEdit}
              className="px-3 py-1 text-xs rounded bg-ipcaBlue text-white"
            >
              {savingEdit ? "Saving…" : "Save Finding (PATCH)"}
            </button>
          </div>

          {editError && <p className="text-xs text-red-600 mb-2">{editError}</p>}

          <div className="grid md:grid-cols-2 gap-3 text-sm">
            <div>
              <label className="block text-[11px] text-slate-600">Reference</label>
              <input
                name="reference"
                value={editForm.reference}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Title</label>
              <input
                name="title"
                value={editForm.title}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Classification</label>
              <input
                name="classification"
                value={editForm.classification}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Severity</label>
              <input
                name="severity"
                value={editForm.severity}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Deadline (target date)</label>
              <input
                type="date"
                name="target_date"
                value={editForm.target_date}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Status</label>
              <select
                name="status"
                value={editForm.status}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              >
                <option value="OPEN">OPEN</option>
                <option value="IN_PROGRESS">IN_PROGRESS</option>
                <option value="CLOSED">CLOSED</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Regulation Ref</label>
              <input
                name="regulation_ref"
                value={editForm.regulation_ref}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Domain ID</label>
              <input
                type="number"
                name="domain_id"
                value={editForm.domain_id}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
              />
            </div>

            <div className="md:col-span-2">
              <label className="block text-[11px] text-slate-600">Description</label>
              <textarea
                name="description"
                value={editForm.description}
                onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm"
                rows={4}
              />
            </div>
          </div>
        </section>

        {/* RCA section */}
        <section className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">
              Root Cause Analysis (AI-assisted)
            </h4>
            <button
              onClick={handleGenerateNextRcaStep}
              disabled={rcaLoading || rcaSteps.length >= 5}
              className="px-2 py-1 text-xs rounded bg-ipcaBlue text-white"
            >
              {rcaLoading ? "Generating…" : (rcaSteps.length >= 5 ? "RCA Complete" : "Generate Next Why (AI)")}
            </button>
          </div>
          {rcaError && <p className="text-xs text-red-600 mb-1">{rcaError}</p>}
          {rcaSteps.length === 0 && (
            <p className="text-xs text-slate-500 mb-2">
              No RCA steps yet. Click “Generate Next Why (AI)” to start.
            </p>
          )}
          <div className="space-y-2">
            {rcaSteps.map((step, idx) => (
              <div key={idx} className="border rounded p-2 bg-slate-50">
                <div className="text-xs font-semibold mb-1">
                  Why {step.whyNumber}: {step.question}
                </div>
                <textarea
                  className="w-full text-xs border rounded p-1"
                  value={step.answer || ""}
                  onChange={(e) => handleRcaAnswerChange(idx, e.target.value)}
                  rows={2}
                />
              </div>
            ))}
          </div>
          {rcaSteps.length > 0 && (
            <button
              onClick={handleSaveRca}
              disabled={rcaLoading}
              className="mt-2 px-3 py-1 text-xs rounded border"
            >
              Save RCA
            </button>
          )}
        </section>

        {/* CAP section */}
        <section className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">CAP (Corrective Actions)</h4>
            <button
              onClick={handleGenerateCap}
              disabled={capLoading}
              className="px-2 py-1 text-xs rounded bg-ipcaBlue text-white"
            >
              {capLoading ? "Generating…" : "Generate CAP Options (AI)"}
            </button>
          </div>
          {capError && <p className="text-xs text-red-600 mb-1">{capError}</p>}
          {capOptions.length === 0 && (
            <p className="text-xs text-slate-500 mb-2">
              No CAP options yet. Click the button to let AI propose actions.
            </p>
          )}
          <div className="space-y-2">
            {capOptions.map((opt, idx) => (
  <div key={idx} className="border rounded p-3 bg-slate-50 space-y-2">
    {/* Header */}
    <div className="flex items-center justify-between">
      <div className="text-sm font-semibold">
        {opt.label || `Option ${String.fromCharCode(65 + idx)}`}
        {opt.effort && (
          <span className="ml-2 text-xs uppercase text-slate-500">
            ({opt.effort})
          </span>
        )}
      </div>
      <button
        onClick={() => handleAdoptCapOption(opt)}
        disabled={capLoading}
        className="px-3 py-1 text-xs rounded bg-ipcaBlue text-white"
      >
        Adopt this option as CAP
      </button>
    </div>

    {/* Optional summary */}
    {opt.summary && (
      <p className="text-xs text-slate-600 italic">
        {opt.summary}
      </p>
    )}

    {/* Actions list */}
    <div className="space-y-1">
      {(opt.actions || []).map((a, i) => (
        <div
          key={i}
          className="border rounded px-2 py-1 bg-white text-xs"
        >
          <div className="font-semibold">
            {a.action_type}
            {a.due_days && (
              <span className="ml-2 text-[10px] text-slate-500">
                (Due in {a.due_days} days)
              </span>
            )}
          </div>
          <div className="text-slate-700">
            {a.description}
          </div>
        </div>
      ))}
    </div>
  </div>
))}
          </div>
        </section>

        {/* Manual/MCCF section */}
        <section className="mb-4">
          <h4 className="font-semibold text-sm mb-1">
            Manual & MCCF References
          </h4>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2">
            <div>
              <label className="block text-[11px] text-slate-600">
                Manual Type
              </label>
              <select
                className="w-full text-xs border rounded px-2 py-1"
                value={manualRef.manualType}
                onChange={(e) =>
                  setManualRef({ ...manualRef, manualType: e.target.value })
                }
              >
                <option value="">— Select —</option>
                <option value="OM">OM</option>
                <option value="OMM">OMM</option>
                <option value="TM">TM</option>
                <option value="TCO">TCO / 141</option>
              </select>
            </div>
            <div>
              <label className="block text-[11px] text-slate-600">Section</label>
              <input
                type="text"
                className="w-full text-xs border rounded px-2 py-1"
                placeholder="e.g. 3.2.4"
                value={manualRef.section}
                onChange={(e) =>
                  setManualRef({ ...manualRef, section: e.target.value })
                }
              />
            </div>
            <div>
              <label className="block text-[11px] text-slate-600">MCCF Item</label>
              <select
                className="w-full text-xs border rounded px-2 py-1"
                value={mccfId}
                onChange={(e) => setMccfId(e.target.value)}
              >
                <option value="">— Select —</option>
                {mccfOptions.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.code || m.id} – {m.title || m.description}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <button
            onClick={handleSaveManualMccf}
            className="px-3 py-1 text-xs rounded border"
          >
            Save Manual/MCCF References
          </button>
        </section>

        {/* Notes */}
        <section>
          <div className="flex items-center justify-between mb-1">
            <h4 className="font-semibold text-sm">Notes</h4>
            <button
              onClick={handleSaveNotes}
              disabled={savingNotes}
              className="px-2 py-1 text-xs rounded border"
            >
              {savingNotes ? "Saving…" : "Save Notes"}
            </button>
          </div>
          <textarea
            className="w-full text-xs border rounded px-2 py-1"
            rows={3}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
          />
        </section>
      </div>
    </div>
  )
}