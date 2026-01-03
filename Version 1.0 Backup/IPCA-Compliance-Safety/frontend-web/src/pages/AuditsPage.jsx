import React, { useEffect, useState } from "react"
import { apiGet, apiPost } from "../api"

const TEST_USER = "415712F4-C7D8-11F0-AD9A-84068FBD07E7"

export default function AuditsPage() {
  const [audits, setAudits] = useState([])
  const [selectedAudit, setSelectedAudit] = useState(null)
  const [findings, setFindings] = useState([])
  const [loadingFindings, setLoadingFindings] = useState(false)

  const [selectedFinding, setSelectedFinding] = useState(null)
  const [actions, setActions] = useState([])
  const [loadingActions, setLoadingActions] = useState(false)

  const [form, setForm] = useState({
    title: "Internal Test Audit",
    authority: "INTERNAL",
    audit_type: "CMS",
    external_ref: "INT-TEST-UI",
    subject: "Created from React UI",
  })

  const [findingForm, setFindingForm] = useState({
    reference: "",
    title: "",
    classification: "LEVEL_2",
    severity: "MEDIUM",
    description: "",
    regulation_ref: "",
    domain_id: 1,
  })

  const [actionForm, setActionForm] = useState({
    action_type: "CORRECTIVE",
    description: "",
    responsible_id: TEST_USER,
    due_date: "",
  })

  // ----------------------------
  // LOAD AUDITS
  // ----------------------------
  const loadAudits = async () => {
    const data = await apiGet("/compliance/audits")
    setAudits(data)
  }

  useEffect(() => {
    loadAudits()
  }, [])

  // ----------------------------
  // LOAD FINDINGS FOR AUDIT
  // ----------------------------
  const loadFindings = async (auditId) => {
    setLoadingFindings(true)
    const data = await apiGet(`/compliance/audits/${auditId}/findings`)
    setFindings(data)
    setLoadingFindings(false)
    setSelectedFinding(null)
    setActions([])
  }

  const handleSelectAudit = (audit) => {
    setSelectedAudit(audit)
    loadFindings(audit.id)
  }

  // ----------------------------
  // LOAD ACTIONS FOR FINDING
  // ----------------------------
  const loadActions = async (findingId) => {
    setLoadingActions(true)
    const data = await apiGet(`/compliance/findings/${findingId}/actions`)
    setActions(data)
    setLoadingActions(false)
  }

  const handleSelectFinding = (finding) => {
    setSelectedFinding(finding)
    loadActions(finding.id)
  }

  // ----------------------------
  // CREATE AUDIT
  // ----------------------------
  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    await apiPost("/compliance/audits", {
      ...form,
      created_by: TEST_USER,
    })
    await loadAudits()
  }

  // ----------------------------
  // CREATE FINDING
  // ----------------------------
  const handleFindingChange = (e) => {
    setFindingForm({ ...findingForm, [e.target.name]: e.target.value })
  }

  const handleCreateFinding = async (e) => {
    e.preventDefault()
    if (!selectedAudit) return

    await apiPost(`/compliance/audits/${selectedAudit.id}/findings`, {
      ...findingForm,
    })

    await loadFindings(selectedAudit.id)
  }

  // ----------------------------
  // CREATE ACTION
  // ----------------------------
  const handleActionChange = (e) => {
    setActionForm({ ...actionForm, [e.target.name]: e.target.value })
  }

  const handleCreateAction = async (e) => {
    e.preventDefault()
    if (!selectedFinding) return

    await apiPost(`/compliance/findings/${selectedFinding.id}/actions`, {
      ...actionForm,
    })

    await loadActions(selectedFinding.id)
  }

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      <h1 className="text-3xl font-semibold text-ipcaBlue mb-4">
        Compliance – Audits, Findings & Actions
      </h1>

      {/* AUDIT CREATION + LIST */}
      <div className="grid md:grid-cols-2 gap-6">
        <form
          onSubmit={handleSubmit}
          className="bg-white p-6 rounded-xl shadow space-y-3"
        >
          <h2 className="text-xl font-semibold mb-2">Create New Audit</h2>

          <input name="title" value={form.title} onChange={handleChange} className="w-full border p-2 rounded" />
          <input name="authority" value={form.authority} onChange={handleChange} className="w-full border p-2 rounded" />
          <input name="audit_type" value={form.audit_type} onChange={handleChange} className="w-full border p-2 rounded" />
          <input name="external_ref" value={form.external_ref} onChange={handleChange} className="w-full border p-2 rounded" />
          <input name="subject" value={form.subject} onChange={handleChange} className="w-full border p-2 rounded" />

          <button className="bg-ipcaBlue text-white px-4 py-2 rounded hover:bg-ipcaBlue-light">
            Create Audit
          </button>
        </form>

        <div className="bg-white p-6 rounded-xl shadow">
          <h2 className="text-xl font-semibold mb-3">Existing Audits</h2>

          <table className="w-full text-sm border">
            <thead>
              <tr className="bg-slate-100">
                <th className="border px-2 py-1">Authority</th>
                <th className="border px-2 py-1">Reference</th>
                <th className="border px-2 py-1">Title</th>
              </tr>
            </thead>
            <tbody>
              {audits.map((audit) => (
                <tr
                  key={audit.id}
                  className="cursor-pointer hover:bg-slate-100"
                  onClick={() => handleSelectAudit(audit)}
                >
                  <td className="border px-2 py-1">{audit.authority}</td>
                  <td className="border px-2 py-1">{audit.external_ref || "—"}</td>
                  <td className="border px-2 py-1">{audit.title}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* FINDINGS FOR SELECTED AUDIT */}
      {selectedAudit && (
        <div className="bg-white p-6 rounded-xl shadow mt-4">
          <h2 className="text-xl font-semibold text-ipcaBlue mb-4">
            Findings for: {selectedAudit.title}
          </h2>

          <form
            onSubmit={handleCreateFinding}
            className="grid md:grid-cols-2 gap-4 mb-5"
          >
            <input
              name="reference"
              placeholder="Reference (e.g. BCAA.ATO.NC.330)"
              value={findingForm.reference}
              onChange={handleFindingChange}
              className="border p-2 rounded"
            />
            <input
              name="title"
              placeholder="Finding Title"
              value={findingForm.title}
              onChange={handleFindingChange}
              className="border p-2 rounded"
            />
            <input
              name="classification"
              value={findingForm.classification}
              onChange={handleFindingChange}
              className="border p-2 rounded"
            />
            <input
              name="severity"
              value={findingForm.severity}
              onChange={handleFindingChange}
              className="border p-2 rounded"
            />
            <input
              name="regulation_ref"
              placeholder="Regulation Ref"
              value={findingForm.regulation_ref}
              onChange={handleFindingChange}
              className="border p-2 rounded"
            />
            <input
              name="domain_id"
              type="number"
              value={findingForm.domain_id}
              onChange={handleFindingChange}
              className="border p-2 rounded"
            />
            <textarea
              name="description"
              placeholder="Description"
              value={findingForm.description}
              onChange={handleFindingChange}
              className="border p-2 rounded col-span-2"
            />

            <button className="bg-ipcaBlue text-white px-4 py-2 rounded">
              Add Finding
            </button>
          </form>

          {loadingFindings ? (
            <p>Loading findings...</p>
          ) : findings.length === 0 ? (
            <p>No findings yet.</p>
          ) : (
            <table className="w-full text-sm border mb-4">
              <thead>
                <tr className="bg-slate-100">
                  <th className="border px-2 py-1">Ref</th>
                  <th className="border px-2 py-1">Title</th>
                  <th className="border px-2 py-1">Classification</th>
                  <th className="border px-2 py-1">Severity</th>
                </tr>
              </thead>
              <tbody>
                {findings.map((f) => (
                  <tr
                    key={f.id}
                    className="cursor-pointer hover:bg-slate-100"
                    onClick={() => handleSelectFinding(f)}
                  >
                    <td className="border px-2 py-1">{f.reference}</td>
                    <td className="border px-2 py-1">{f.title}</td>
                    <td className="border px-2 py-1">{f.classification}</td>
                    <td className="border px-2 py-1">{f.severity}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {/* ACTIONS FOR SELECTED FINDING */}
          {selectedFinding && (
            <div className="mt-4 border-t pt-4">
              <h3 className="text-lg font-semibold mb-2">
                Actions for finding: {selectedFinding.reference}
              </h3>

              <form
                onSubmit={handleCreateAction}
                className="grid md:grid-cols-4 gap-3 mb-4"
              >
                <input
                  name="action_type"
                  value={actionForm.action_type}
                  onChange={handleActionChange}
                  className="border p-2 rounded"
                />
                <input
                  name="responsible_id"
                  value={actionForm.responsible_id}
                  onChange={handleActionChange}
                  className="border p-2 rounded"
                />
                <input
                  name="due_date"
                  placeholder="YYYY-MM-DD"
                  value={actionForm.due_date}
                  onChange={handleActionChange}
                  className="border p-2 rounded"
                />
                <input
                  name="description"
                  placeholder="Action description"
                  value={actionForm.description}
                  onChange={handleActionChange}
                  className="border p-2 rounded col-span-2 md:col-span-4"
                />

                <button className="bg-ipcaBlue text-white px-4 py-2 rounded">
                  Add Action
                </button>
              </form>

              {loadingActions ? (
                <p>Loading actions...</p>
              ) : actions.length === 0 ? (
                <p>No actions yet.</p>
              ) : (
                <table className="w-full text-sm border">
                  <thead>
                    <tr className="bg-slate-100">
                      <th className="border px-2 py-1">Type</th>
                      <th className="border px-2 py-1">Description</th>
                      <th className="border px-2 py-1">Due Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    {actions.map((a) => (
                      <tr key={a.id}>
                        <td className="border px-2 py-1">{a.action_type}</td>
                        <td className="border px-2 py-1">{a.description}</td>
                        <td className="border px-2 py-1">
                          {a.due_date || "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}