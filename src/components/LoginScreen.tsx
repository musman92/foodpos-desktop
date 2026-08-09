import { FormEvent, useState } from "react";
import { SessionUser, signIn } from "../api/tauri";

interface Props {
  onSignedIn: (user: SessionUser) => void;
}

export function LoginScreen({ onSignedIn }: Props) {
  const [username, setUsername] = useState("admin");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const user = await signIn(username.trim(), password);
      onSignedIn(user);
    } catch (err) {
      setError(String(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="screen">
      <div className="panel">
        <p className="eyebrow">Local auth</p>
        <h1>Sign in</h1>
        <p className="muted">
          Default admin: <code>admin</code> / <code>admin123</code>
        </p>

        <form onSubmit={onSubmit} className="stack">
          <label>
            Username
            <input
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              autoComplete="username"
              required
            />
          </label>
          <label>
            Password
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              required
            />
          </label>
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={busy}>
            {busy ? "Signing in…" : "Sign in"}
          </button>
        </form>
      </div>
    </div>
  );
}
