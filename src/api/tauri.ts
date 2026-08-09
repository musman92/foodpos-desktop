import { invoke } from "@tauri-apps/api/core";

export type LicenseStatus =
  | {
      status: "valid";
      machine_id: string;
      license_id: string;
      seats: number;
      customer: string | null;
      activated_at: string;
    }
  | { status: "missing" }
  | { status: "invalid"; reason: string };

export interface AppConfig {
  mode: string;
  server_url: string | null;
  station_name: string | null;
  floor_id: number | null;
}

export interface SessionUser {
  id: number;
  username: string;
  role: string;
}

export interface UserRecord {
  id: number;
  username: string;
  role: string;
  created_at: string;
}

export interface BootstrapInfo {
  license: LicenseStatus;
  machine_id: string;
  session: SessionUser | null;
  config: AppConfig;
}

export function getBootstrap(): Promise<BootstrapInfo> {
  return invoke("get_bootstrap");
}

export function activateLicense(key: string): Promise<LicenseStatus> {
  return invoke("activate_license", { key });
}

export function launchFoodpos(): Promise<{ url: string; already_running: boolean }> {
  return invoke("launch_foodpos");
}

export function signIn(username: string, password: string): Promise<SessionUser> {
  return invoke("sign_in", { username, password });
}

export function signOut(): Promise<void> {
  return invoke("sign_out");
}

export function listUsers(): Promise<UserRecord[]> {
  return invoke("list_users");
}

export function addUser(
  username: string,
  password: string,
  role: string,
): Promise<UserRecord> {
  return invoke("add_user", { username, password, role });
}
