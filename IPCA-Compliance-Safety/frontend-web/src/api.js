// Production: set VITE_API_BASE_URL=/api in DigitalOcean Static Site env vars
// Dev fallback: localhost (adjust if your dev API runs elsewhere)
export const BASE_URL =
  (import.meta?.env?.VITE_API_BASE_URL || "").replace(/\/$/, "") ||
  "http://localhost:8888";

function buildUrl(path) {
  // Ensure exactly one slash between BASE_URL and path
  const p = path.startsWith("/") ? path : `/${path}`;
  return `${BASE_URL}${p}`;
}

export async function apiGet(path) {
  const res = await fetch(buildUrl(path), {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) {
    const txt = await res.text().catch(() => "");
    throw new Error(txt || `GET ${path} failed with ${res.status}`);
  }
  return res.json();
}

export async function apiPost(path, body) {
  const res = await fetch(buildUrl(path), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body || {}),
  });

  const txt = await res.text().catch(() => "");

  if (!res.ok) {
    console.error("POST failed", path, "HTTP", res.status, txt);
    throw new Error(txt || `POST ${path} failed with ${res.status}`);
  }

  // Some endpoints may return empty body; handle safely
  if (!txt) return {};

  try {
    return JSON.parse(txt);
  } catch (e) {
    console.error("POST non-JSON response", path, txt);
    throw new Error("Server returned non-JSON response: " + txt.slice(0, 200));
  }
}

export async function apiPatch(path, body) {
  const res = await fetch(buildUrl(path), {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(body || {}),
  });

  const txt = await res.text().catch(() => "");

  if (!res.ok) {
    console.error("PATCH failed", path, "HTTP", res.status, txt);
    throw new Error(txt || `PATCH ${path} failed with ${res.status}`);
  }

  // Some PATCH endpoints may return empty body
  if (!txt) return {};

  try {
    return JSON.parse(txt);
  } catch (e) {
    return {};
  }
}

export async function apiDelete(path) {
  const res = await fetch(buildUrl(path), {
    method: "DELETE",
    headers: { Accept: "application/json" },
  });

  const txt = await res.text().catch(() => "");

  if (!res.ok) {
    console.error("DELETE failed", path, "HTTP", res.status, txt);
    throw new Error(txt || `DELETE ${path} failed with ${res.status}`);
  }

  if (!txt) return {};
  try {
    return JSON.parse(txt);
  } catch (e) {
    return {};
  }
}
