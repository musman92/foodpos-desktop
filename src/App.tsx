import { useEffect, useState } from "react";
import {
  getBootstrap,
  launchFoodpos,
  LicenseStatus,
} from "./api/tauri";
import { LicenseScreen } from "./components/LicenseScreen";
import "./App.css";

type Phase =
  | "loading"
  | "license"
  | "license_invalid"
  | "launching"
  | "launch_error";

function App() {
  const [phase, setPhase] = useState<Phase>("loading");
  const [machineId, setMachineId] = useState("");
  const [license, setLicense] = useState<LicenseStatus | null>(null);
  const [bootError, setBootError] = useState<string | null>(null);
  const [launchError, setLaunchError] = useState<string | null>(null);

  async function openFoodpos() {
    setPhase("launching");
    setLaunchError(null);
    try {
      await launchFoodpos();
      // Window navigates to Laravel; this React tree unmounts.
    } catch (err) {
      setLaunchError(String(err));
      setPhase("launch_error");
    }
  }

  useEffect(() => {
    getBootstrap()
      .then((info) => {
        setMachineId(info.machine_id);
        setLicense(info.license);
        if (info.license.status === "valid") {
          void openFoodpos();
        } else if (info.license.status === "invalid") {
          setPhase("license_invalid");
        } else {
          setPhase("license");
        }
      })
      .catch((err) => {
        setBootError(String(err));
        setPhase("license_invalid");
      });
  }, []);

  if (phase === "loading" || phase === "launching") {
    return (
      <div className="screen">
        <div className="panel">
          <p className="eyebrow">FoodPOS Offline</p>
          <h1>{phase === "launching" ? "Starting restaurant…" : "Starting…"}</h1>
          <p className="muted">
            {phase === "launching"
              ? "Launching the local FoodPOS server. This may take a few seconds."
              : "Checking license…"}
          </p>
        </div>
      </div>
    );
  }

  if (phase === "license_invalid") {
    const reason =
      bootError ??
      (license && license.status === "invalid" ? license.reason : "Unknown error");
    return (
      <div className="screen">
        <div className="panel">
          <p className="eyebrow danger">Blocked</p>
          <h1>License invalid</h1>
          <p className="muted">
            The local license failed verification, or this machine no longer
            matches the bound fingerprint.
          </p>
          <p className="error">{reason}</p>
          {machineId && (
            <div className="machine-id">
              <span>Current machine ID</span>
              <code>{machineId}</code>
            </div>
          )}
        </div>
      </div>
    );
  }

  if (phase === "launch_error") {
    return (
      <div className="screen">
        <div className="panel">
          <p className="eyebrow danger">Backend error</p>
          <h1>Could not start FoodPOS</h1>
          <p className="muted">
            Make sure PHP is installed and <code>foodpos-backend</code> has been
            migrated (<code>php artisan migrate --seed</code>).
          </p>
          <p className="error">{launchError}</p>
          <button type="button" onClick={() => void openFoodpos()}>
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <LicenseScreen
      machineId={machineId}
      onActivated={(next) => {
        setLicense(next);
        void openFoodpos();
      }}
    />
  );
}

export default App;
