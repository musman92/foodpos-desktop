import { FormEvent, useState } from "react";
import { activateLicense, LicenseStatus } from "../api/tauri";

interface Props {
  machineId: string;
  onActivated: (license: LicenseStatus) => void;
}

export function LicenseScreen({ machineId, onActivated }: Props) {
  const [key, setKey] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [copied, setCopied] = useState(false);

  async function copyMachineId() {
    try {
      await navigator.clipboard.writeText(machineId);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      setError("Could not copy — select the Machine ID manually.");
    }
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const license = await activateLicense(key.trim());
      if (license.status === "valid") {
        onActivated(license);
      } else if (license.status === "invalid") {
        setError(license.reason);
      } else {
        setError("Activation failed.");
      }
    } catch (err) {
      setError(String(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="screen">
      <div className="panel wide-panel">
        <p className="eyebrow">Activation required</p>
        <h1>Activate license</h1>
        <p className="muted">
          Send your Machine ID to your vendor. They will issue a license token
          bound to this PC. The same token will not work on another computer.
        </p>

        <div className="machine-id" title={machineId}>
          <span>Machine ID — send this</span>
          <code>{machineId}</code>
          <button type="button" className="ghost compact" onClick={copyMachineId}>
            {copied ? "Copied" : "Copy"}
          </button>
        </div>

        <form onSubmit={onSubmit} className="stack">
          <label>
            License token
            <textarea
              value={key}
              onChange={(e) => setKey(e.target.value)}
              placeholder="FPOS1.…"
              rows={4}
              spellCheck={false}
              required
            />
          </label>
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={busy || !key.trim()}>
            {busy ? "Activating…" : "Activate"}
          </button>
        </form>
      </div>
    </div>
  );
}
