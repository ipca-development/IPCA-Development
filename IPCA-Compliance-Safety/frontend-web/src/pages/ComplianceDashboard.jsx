// frontend-web/src/pages/ComplianceDashboard.jsx
import React from "react"
import { apiGet, apiPost, apiPatch } from "../api"

function classificationLabel(v) {
  if (!v) return "—"
  return v.replaceAll("_", " ")
}

function classificationPillClass(v) {
  switch (v) {
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

function entityPillClass(entity) {
  const e = (entity || "").toUpperCase()
  if (e === "INTERNAL") return "bg-slate-200 text-slate-800"
  return "bg-blue-100 text-blue-800"
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
              <th className="px-2 py-1 border">Classification</th>
              <th className="px-2 py-1 border text-center">Audit Entity</th>
              <th className="px-2 py-1 border text-center">Ref</th>
              <th className="px-2 py-1 border">Summary</th>
              <th className="px-2 py-1 border text-center">Deadline</th>
              <th className="px-2 py-1 border text-center">RCA</th>
              <th className="px-2 py-1 border text-center">CAP</th>
            </tr>
          </thead>

          <tbody>
            {sorted.map((f) => (
              <tr
                key={f.id}
                className="hover:bg-slate-50 cursor-pointer"
                onClick={() => setSelected(f)}
              >
                {/* Classification pill centered */}
                <td className="border px-2 py-1">
                  <div className="flex justify-center">
                    <span
                      className={`px-3 py-1 rounded text-xs font-semibold ${classificationPillClass(
                        f.classification
                      )}`}
                    >
                      {classificationLabel(f.classification)}
                    </span>
                  </div>
                </td>

                {/* Audit Entity pill centered */}
                <td className="border px-2 py-1">
                  <div className="flex justify-center">
                    {f.audit_entity ? (
                      <span
                        className={`px-2 py-0.5 rounded text-xs font-medium ${entityPillClass(
                          f.audit_entity
                        )}`}
                      >
                        {f.audit_entity}
                      </span>
                    ) : (
                      <span className="text-slate-400">—</span>
                    )}
                  </div>
                </td>

                {/* Ref centered */}
                <td className="border px-2 py-1 text-center">
                  {f.reference || "—"}
                </td>

                <td className="border px-2 py-1">{f.title || "—"}</td>

                <td className="border px-2 py-1 text-center">
                  {f.target_date || "—"}
                </td>

                <td className="border px-2 py-1 text-center">
                  {f.rca_progress || "0/5"}
                </td>

                <td className="border px-2 py-1 text-center">
                  {f.cap_progress || "0 actions"}
                </td>
              </tr>
            ))}

            {sorted.length === 0 && (
              <tr>
                <td colSpan={7} className="text-center py-4 text-slate-500">
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

// Modal with Edit + RCA + CAP + Saved Actions (Editable) + Manual/MCCF + Notes
function FindingModal({ finding, onClose, onSaved }) {
  // -----------------------
  // EDIT FINDING
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
  // CAP Options
  // -----------------------
  const [capOptions, setCapOptions] = React.useState([])
  const [capLoading, setCapLoading] = React.useState(false)
  const [capError, setCapError] = React.useState(null)

  // Selected CAP display
  const [capSelected, setCapSelected] = React.useState({
    option: finding.cap_selected_option || null,
    effort: finding.cap_selected_effort || null,
  })

  // -----------------------
  // SAVED CAP ACTIONS (Editable)
  // -----------------------
  const [savedActions, setSavedActions] = React.useState([])
  const [actionsError, setActionsError] = React.useState(null)

  const loadActions = React.useCallback(() => {
    apiGet(`/compliance/findings/${finding.id}/actions`)
      .then((data) => {
        setActionsError(null)
        setSavedActions(Array.isArray(data) ? data : [])
      })
      .catch((err) => {
        console.error(err)
        setActionsError("Could not load saved CAP actions.")
        setSavedActions([])
      })
  }, [finding.id])

  // -----------------------
  // Manual/MCCF/Notes (stubs)
  // -----------------------
  const [manualRef, setManualRef] = React.useState({ manualType: "", section: "" })
  const [mccfOptions, setMccfOptions] = React.useState([])
  const [mccfId, setMccfId] = React.useState("")
  const [notes, setNotes] = React.useState(finding.notes || "")
  const [savingNotes, setSavingNotes] = React.useState(false)

  React.useEffect(() => {
    apiGet("/compliance/mccf").then(setMccfOptions).catch(() => {})

    apiGet(`/compliance/findings/${finding.id}/rca`)
      .then((res) => setRcaSteps(res.steps || []))
      .catch(() => {})

    loadActions()
  }, [finding.id, loadActions])

  // RCA next step
  const handleGenerateNextRcaStep = async () => {
    if (rcaSteps.length >= 5) return
    try {
      setRcaLoading(true)
      const res = await apiPost(`/compliance/findings/${finding.id}/rca/next-step`, { steps: rcaSteps })
      if (!res || !res.question || !res.answer) throw new Error("Invalid RCA step response")
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
    setRcaSteps(rcaSteps.map((s, i) => (i === idx ? { ...s, answer: newAnswer } : s)))
  }

  const handleSaveRca = async () => {
    try {
      setRcaLoading(true)
      setRcaError(null)
      await apiPost(`/compliance/findings/${finding.id}/rca`, { steps: rcaSteps })
      alert("RCA saved.")
      if (onSaved) onSaved()
    } catch (err) {
      console.error(err)
      setRcaError("Could not save RCA.")
    } finally {
      setRcaLoading(false)
    }
  }

  // CAP generate
  const handleGenerateCap = async () => {
    try {
      setCapLoading(true)
      setCapError(null)
      const res = await apiPost(`/compliance/findings/${finding.id}/actions/suggest-ai`, { rcaSteps })
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

      await apiPost(`/compliance/findings/${finding.id}/actions`, { option: opt })

      const label = (opt.label || "").toUpperCase()
      const selected =
        label.includes("OPTION A") || label === "A" ? "A" :
        label.includes("OPTION B") || label === "B" ? "B" :
        label.includes("OPTION C") || label === "C" ? "C" : null

      setCapSelected({ option: selected, effort: opt.effort || null })
      loadActions()

      alert("CAP actions created from selected option.")
      if (onSaved) onSaved()
    } catch (err) {
      console.error(err)
      setCapError("Could not adopt CAP option.")
    } finally {
      setCapLoading(false)
    }
  }

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
        <div className="flex justify-between items-center mb-2">
          <div>
            <h3 className="text-lg font-semibold">
              Finding {finding.reference} – {finding.title}
            </h3>
            <div className="text-xs text-slate-600">
              <strong>Audit Reference:</strong> {finding.audit_ref || "—"}
            </div>
          </div>
          <button onClick={onClose} className="text-slate-500 text-sm">Close</button>
        </div>

        {/* EDIT FINDING */}
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
              <input name="reference" value={editForm.reference} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm" />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Title</label>
              <input name="title" value={editForm.title} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm" />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Classification</label>
              <select name="classification" value={editForm.classification} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm">
                <option value="LEVEL_1">Level 1</option>
                <option value="LEVEL_2">Level 2</option>
                <option value="OBSERVATION">Observation</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Severity</label>
              <select name="severity" value={editForm.severity} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm">
                <option value="LOW">LOW</option>
                <option value="MEDIUM">MEDIUM</option>
                <option value="HIGH">HIGH</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Deadline (target date)</label>
              <input type="date" name="target_date" value={editForm.target_date}
                onChange={handleEditChange} className="w-full border rounded px-2 py-1 text-sm" />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Status</label>
              <select name="status" value={editForm.status} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm">
                <option value="OPEN">OPEN</option>
                <option value="RCA_IN_PROGRESS">RCA IN PROGRESS</option>
                <option value="CAP_IN_PROGRESS">CAP IN PROGRESS</option>
                <option value="INTERNAL_REVIEW">INTERNAL REVIEW</option>
                <option value="CLOSED">CLOSED</option>
              </select>
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Regulation Ref</label>
              <input name="regulation_ref" value={editForm.regulation_ref} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm" />
            </div>

            <div>
              <label className="block text-[11px] text-slate-600">Domain ID</label>
              <input type="number" name="domain_id" value={editForm.domain_id} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm" />
            </div>

            <div className="md:col-span-2">
              <label className="block text-[11px] text-slate-600">Description</label>
              <textarea name="description" value={editForm.description} onChange={handleEditChange}
                className="w-full border rounded px-2 py-1 text-sm" rows={4} />
            </div>
          </div>
        </section>

        {/* RCA */}
        <section className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">Root Cause Analysis (AI-assisted)</h4>
            <button onClick={handleGenerateNextRcaStep}
              disabled={rcaLoading || rcaSteps.length >= 5}
              className="px-2 py-1 text-xs rounded bg-ipcaBlue text-white">
              {rcaLoading ? "Generating…" : (rcaSteps.length >= 5 ? "RCA Complete" : "Generate Next Why (AI)")}
            </button>
          </div>

          {rcaError && <p className="text-xs text-red-600 mb-1">{rcaError}</p>}

          <div className="space-y-2">
            {rcaSteps.map((step, idx) => (
              <div key={idx} className="border rounded p-2 bg-slate-50">
                <div className="text-xs font-semibold mb-1">
                  Why {step.whyNumber}: {step.question}
                </div>
                <textarea className="w-full text-xs border rounded p-1"
                  value={step.answer || ""} onChange={(e) => handleRcaAnswerChange(idx, e.target.value)}
                  rows={2} />
              </div>
            ))}
          </div>

          {rcaSteps.length > 0 && (
            <button onClick={handleSaveRca} disabled={rcaLoading}
              className="mt-2 px-3 py-1 text-xs rounded border">
              Save RCA
            </button>
          )}
        </section>

        {/* CAP */}
        <section className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">CAP (Corrective Actions)</h4>
            <button onClick={handleGenerateCap} disabled={capLoading}
              className="px-2 py-1 text-xs rounded bg-ipcaBlue text-white">
              {capLoading ? "Generating…" : "Generate CAP Options (AI)"}
            </button>
          </div>

          {(capSelected.option || capSelected.effort) && (
            <div className="text-xs mb-2">
              <strong>Selected CAP:</strong>{" "}
              {capSelected.option ? `Option ${capSelected.option}` : ""}
              {capSelected.effort ? ` (${capSelected.effort})` : ""}
            </div>
          )}

          {capError && <p className="text-xs text-red-600 mb-1">{capError}</p>}

          <div className="space-y-2">
            {capOptions.map((opt, idx) => (
              <div key={idx} className="border rounded p-3 bg-slate-50 space-y-2">
                <div className="flex items-center justify-between">
                  <div className="text-sm font-semibold">
                    {opt.label || `Option ${String.fromCharCode(65 + idx)}`}
                    {opt.effort && (
                      <span className="ml-2 text-xs uppercase text-slate-500">
                        ({opt.effort})
                      </span>
                    )}
                  </div>
                  <button onClick={() => handleAdoptCapOption(opt)} disabled={capLoading}
                    className="px-3 py-1 text-xs rounded bg-ipcaBlue text-white">
                    Adopt this option as CAP
                  </button>
                </div>

                <div className="space-y-1">
                  {(opt.actions || []).map((a, i) => (
                    <div key={i} className="border rounded px-2 py-1 bg-white text-xs">
                      <div className="font-semibold">
                        {a.action_type}
                        {a.due_days && (
                          <span className="ml-2 text-[10px] text-slate-500">
                            (Due in {a.due_days} days)
                          </span>
                        )}
                      </div>
                      <div className="text-slate-700">{a.description}</div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Saved CAP Actions (Editable) */}
        <section className="mb-5">
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-semibold text-sm">Saved CAP Actions (Editable)</h4>
            <button onClick={loadActions} className="px-2 py-1 text-xs rounded border">
              Refresh
            </button>
          </div>

          {actionsError && (
            <p className="text-xs text-red-600 mb-2">{actionsError}</p>
          )}

          {savedActions.length === 0 ? (
            <p className="text-xs text-slate-500">No CAP actions saved yet.</p>
          ) : (
            <div className="space-y-2">
              {savedActions.map((a) => (
                <CapActionEditor key={a.id} action={a} onSaved={loadActions} />
              ))}
            </div>
          )}
        </section>

        {/* Manual/MCCF/Notes (kept as-is; optional) */}
        <section className="mb-4">
          <h4 className="font-semibold text-sm mb-1">Manual & MCCF References</h4>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2">
            <div>
              <label className="block text-[11px] text-slate-600">Manual Type</label>
              <select
                className="w-full text-xs border rounded px-2 py-1"
                value={manualRef.manualType}
                onChange={(e) => setManualRef({ ...manualRef, manualType: e.target.value })}
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
                onChange={(e) => setManualRef({ ...manualRef, section: e.target.value })}
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
          <button onClick={handleSaveManualMccf} className="px-3 py-1 text-xs rounded border">
            Save Manual/MCCF References
          </button>
        </section>

        <section>
          <div className="flex items-center justify-between mb-1">
            <h4 className="font-semibold text-sm">Notes</h4>
            <button onClick={handleSaveNotes} disabled={savingNotes} className="px-2 py-1 text-xs rounded border">
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

function CapActionEditor({ action, onSaved }) {
  const [form, setForm] = React.useState({
    action_type: action.action_type || "CORRECTIVE",
    description: action.description || "",
    due_date: action.due_date || "",
  })
  const [saving, setSaving] = React.useState(false)

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  const handleSave = async () => {
    try {
      setSaving(true)
      await apiPatch(`/compliance/actions/${action.id}`, {
        action_type: form.action_type,
        description: form.description,
        due_date: form.due_date || null,
      })
      if (onSaved) onSaved()
      alert("CAP action saved.")
    } catch (err) {
      console.error(err)
      alert("Failed to save CAP action. Check Network → Response.")
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="border rounded p-3 bg-white">
      <div className="grid md:grid-cols-3 gap-2 text-xs">
        <div>
          <label className="block text-[11px] text-slate-600">Type</label>
          <select
            name="action_type"
            value={form.action_type}
            onChange={handleChange}
            className="w-full border rounded px-2 py-1 text-xs"
          >
            <option value="CORRECTIVE">CORRECTIVE</option>
            <option value="PREVENTIVE">PREVENTIVE</option>
            <option value="CONTAINMENT">CONTAINMENT</option>
          </select>
        </div>

        <div>
          <label className="block text-[11px] text-slate-600">Due date</label>
          <input
            type="date"
            name="due_date"
            value={form.due_date || ""}
            onChange={handleChange}
            className="w-full border rounded px-2 py-1 text-xs"
          />
        </div>

        <div className="flex items-end">
          <button
            onClick={handleSave}
            disabled={saving}
            className="w-full px-3 py-1 rounded bg-ipcaBlue text-white text-xs"
          >
            {saving ? "Saving…" : "Save"}
          </button>
        </div>

        <div className="md:col-span-3">
          <label className="block text-[11px] text-slate-600">Description</label>
          <textarea
            name="description"
            value={form.description}
            onChange={handleChange}
            className="w-full border rounded px-2 py-1 text-xs"
            rows={3}
          />
        </div>
      </div>
    </div>
  )
}