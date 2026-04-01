import assert from 'node:assert/strict';
import { existsSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

function findPhpExe() {
  const phpRoot = 'C:\\laragon\\bin\\php';
  assert.ok(existsSync(phpRoot), `Laragon PHP root not found: ${phpRoot}`);

  const versions = readdirSync(phpRoot, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => path.join(phpRoot, entry.name, 'php.exe'))
    .filter((candidate) => existsSync(candidate))
    .sort()
    .reverse();

  assert.ok(versions.length > 0, 'No php.exe found under C:\\laragon\\bin\\php');
  return versions[0];
}

const phpExe = findPhpExe();
const command = "require 'db_connect.php'; echo 'connected'.PHP_EOL;";

const stdout = execFileSync(phpExe, ['-r', command], {
  cwd: process.cwd(),
  encoding: 'utf8',
  stdio: ['ignore', 'pipe', 'pipe'],
});

assert.match(stdout, /connected/i);
console.log('db_connect smoke test passed');
