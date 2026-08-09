#!/usr/bin/env node
/**
 * Stage foodpos-backend into src-tauri/resources for the installer.
 * Invoked by Tauri `beforeBuildCommand`.
 *
 * Requires: vendor/, public/build/, and .env (or .env.example).
 */
import {
  cpSync,
  existsSync,
  mkdirSync,
  readdirSync,
  rmSync,
  statSync,
  readFileSync,
  writeFileSync,
} from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const src = join(root, 'foodpos-backend');
const dest = join(root, 'src-tauri', 'resources', 'foodpos-backend');

const skipNames = new Set([
  'node_modules',
  '.git',
  'tests',
  '.phpunit.result.cache',
  'phpunit.xml',
]);

function fail(msg) {
  console.error(`error: ${msg}`);
  process.exit(1);
}

if (!existsSync(join(src, 'artisan'))) {
  fail('foodpos-backend/artisan not found');
}
if (!existsSync(join(src, 'vendor'))) {
  fail('foodpos-backend/vendor missing — run: cd foodpos-backend && composer install --no-dev');
}
if (!existsSync(join(src, 'public', 'build'))) {
  fail('foodpos-backend/public/build missing — run: cd foodpos-backend && npm install && npm run build');
}

const envSrc = existsSync(join(src, '.env'))
  ? join(src, '.env')
  : join(src, '.env.example');
if (!existsSync(envSrc)) {
  fail('Need foodpos-backend/.env or .env.example');
}

console.log('Staging foodpos-backend → src-tauri/resources/foodpos-backend …');
rmSync(dest, { recursive: true, force: true });
mkdirSync(dest, { recursive: true });

function copyTree(from, to) {
  mkdirSync(to, { recursive: true });
  for (const name of readdirSync(from)) {
    if (skipNames.has(name)) continue;
    const a = join(from, name);
    const b = join(to, name);
    if (name === 'storage') {
      copyStorage(a, b);
      continue;
    }
    if (statSync(a).isDirectory()) copyTree(a, b);
    else cpSync(a, b);
  }
}

function copyStorage(from, to) {
  mkdirSync(to, { recursive: true });
  for (const name of readdirSync(from)) {
    if (name === 'logs' || name === 'framework') {
      mkdirSync(join(to, name), { recursive: true });
      if (name === 'framework') {
        for (const sub of ['cache', 'sessions', 'views', 'testing']) {
          mkdirSync(join(to, name, sub), { recursive: true });
          writeFileSync(join(to, name, sub, '.gitignore'), '*\n!.gitignore\n');
        }
      } else {
        writeFileSync(join(to, name, '.gitignore'), '*\n!.gitignore\n');
      }
      continue;
    }
    const a = join(from, name);
    const b = join(to, name);
    if (statSync(a).isDirectory()) copyTree(a, b);
    else cpSync(a, b);
  }
}

copyTree(src, dest);

const destEnv = join(dest, '.env');
if (!existsSync(destEnv)) {
  cpSync(envSrc, destEnv);
}
let envText = readFileSync(destEnv, 'utf8');
if (!/OFFLINE_EDITION\s*=/.test(envText)) {
  envText += '\nOFFLINE_EDITION=true\n';
}
if (/APP_URL=/.test(envText)) {
  envText = envText.replace(/APP_URL=.*/g, 'APP_URL=http://127.0.0.1:8000');
} else {
  envText += '\nAPP_URL=http://127.0.0.1:8000\n';
}
writeFileSync(destEnv, envText);

const sqliteDest = join(dest, 'database', 'database.sqlite');
const sqliteSrc = join(src, 'database', 'database.sqlite');
mkdirSync(join(dest, 'database'), { recursive: true });
if (!existsSync(sqliteDest) && existsSync(sqliteSrc)) {
  cpSync(sqliteSrc, sqliteDest);
} else if (!existsSync(sqliteDest)) {
  writeFileSync(sqliteDest, '');
}

console.log('Backend bundle ready.');
