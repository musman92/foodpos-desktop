import { FormEvent, useEffect, useState } from "react";
import {
  addUser,
  listUsers,
  SessionUser,
  signOut,
  UserRecord,
} from "../api/tauri";

interface Props {
  user: SessionUser;
  machineId: string;
  licenseId: string;
  seats: number;
  customer: string | null;
  onSignedOut: () => void;
}

export function Dashboard({
  user,
  machineId,
  licenseId,
  seats,
  customer,
  onSignedOut,
}: Props) {
  const [users, setUsers] = useState<UserRecord[]>([]);
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState("staff");
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function refresh() {
    const rows = await listUsers();
    setUsers(rows);
  }

  useEffect(() => {
    refresh().catch((err) => setError(String(err)));
  }, []);

  async function onAdd(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setMessage(null);
    setBusy(true);
    try {
      await addUser(username.trim(), password, role);
      setUsername("");
      setPassword("");
      setRole("staff");
      setMessage("User created.");
      await refresh();
    } catch (err) {
      setError(String(err));
    } finally {
      setBusy(false);
    }
  }

  async function onSignOut() {
    await signOut();
    onSignedOut();
  }

  return (
    <div className="screen wide">
      <header className="topbar">
        <div>
          <p className="eyebrow">Dashboard</p>
          <h1>Signed in as {user.username}</h1>
          <p className="muted">
            Role: <strong>{user.role}</strong>
            {" · "}
            License: <code>{licenseId}</code>
            {" · "}
            Seats: <strong>{seats}</strong>
            {customer ? (
              <>
                {" · "}
                {customer}
              </>
            ) : null}
          </p>
        </div>
        <button type="button" className="ghost" onClick={onSignOut}>
          Sign out
        </button>
      </header>

      <div className="grid">
        <section className="panel">
          <h2>Add user</h2>
          <p className="muted">Stored locally in SQLite with Argon2 password hashes.</p>
          <form onSubmit={onAdd} className="stack">
            <label>
              Username
              <input
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                required
              />
            </label>
            <label>
              Password
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength={4}
              />
            </label>
            <label>
              Role
              <select value={role} onChange={(e) => setRole(e.target.value)}>
                <option value="admin">admin</option>
                <option value="staff">staff</option>
              </select>
            </label>
            {error && <p className="error">{error}</p>}
            {message && <p className="ok">{message}</p>}
            <button type="submit" disabled={busy}>
              {busy ? "Saving…" : "Add user"}
            </button>
          </form>
        </section>

        <section className="panel">
          <h2>Users</h2>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id}>
                    <td>{u.id}</td>
                    <td>{u.username}</td>
                    <td>{u.role}</td>
                    <td>{u.created_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <footer className="footer muted">
        Mode: server · Machine ID: <code>{machineId}</code>
      </footer>
    </div>
  );
}
