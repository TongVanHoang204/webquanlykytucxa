import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

const files = [
  'database/migrations/2026-03-31-wave1-foundation.sql',
  'includes/gate_qr_service.php',
  'modules/api/student_qr_token.php',
  'modules/api/scan_gate_qr.php',
  'modules/api/gate_access_logs.php',
  'modules/api/student_gate_history.php',
  'modules/user/access_qr.php',
  'modules/user/gate_history.php',
  'modules/staff/access/gate_scanner.php',
  'modules/staff/access/gate_logs.php',
];

for (const filePath of files) {
  assert.ok(existsSync(filePath), `Expected ${filePath} to exist`);
}

const sql = readFileSync('database/migrations/2026-03-31-wave1-foundation.sql', 'utf8');
const gateService = readFileSync('includes/gate_qr_service.php', 'utf8');
const studentQrApi = readFileSync('modules/api/student_qr_token.php', 'utf8');
const gateScanApi = readFileSync('modules/api/scan_gate_qr.php', 'utf8');
const gateLogsApi = readFileSync('modules/api/gate_access_logs.php', 'utf8');
const studentHistoryApi = readFileSync('modules/api/student_gate_history.php', 'utf8');
const accessQrPage = readFileSync('modules/user/access_qr.php', 'utf8');
const gateHistoryPage = readFileSync('modules/user/gate_history.php', 'utf8');
const gateScannerPage = readFileSync('modules/staff/access/gate_scanner.php', 'utf8');
const gateLogsPage = readFileSync('modules/staff/access/gate_logs.php', 'utf8');

assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `gate_qr_tokens`'));
assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `gate_access_logs`'));
assert.ok(sql.includes('uniq_gate_qr_active_student'));
assert.ok(gateService.includes('function issueStudentGateQrToken'));
assert.ok(gateService.includes('function validateGateQrToken'));
assert.ok(gateService.includes('function gateQrLockTokenRow'));
assert.ok(gateService.includes('function registerGateScan'));
assert.ok(gateService.includes('FOR UPDATE'));
assert.ok(gateService.includes('UPDATE gate_qr_tokens SET IsUsed = 1 WHERE StudentID = ? AND IsUsed = 0'));
assert.ok(gateService.includes('begin_transaction'));
assert.ok(gateService.includes('duplicate_scan_window'));
assert.ok(gateService.includes('Gate access log insert did not return an ID.'));
assert.ok(gateService.includes('token_used'));
assert.ok(gateService.includes('parse_ini_file') || gateService.includes("dirname(__DIR__)"));
assert.ok(!gateService.includes('wave1-dev-gate-secret'));
assert.ok(studentQrApi.includes('issueStudentGateQrToken'));
assert.ok(gateScanApi.includes('registerGateScan'));
assert.ok(gateLogsApi.includes('FROM gate_access_logs'));
assert.ok(studentHistoryApi.includes('FROM gate_access_logs'));
assert.ok(accessQrPage.includes('student_qr_token.php'));
assert.ok(gateHistoryPage.includes('student_gate_history.php'));
assert.ok(gateScannerPage.includes('scan_gate_qr.php'));
assert.ok(gateLogsPage.includes('gate_access_logs.php'));

console.log('gate qr contract smoke test passed');
