import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

const rootFiles = [
  'database/migrations/2026-03-31-wave1-foundation.sql',
  'includes/notification_service.php',
  'includes/realtime_signer.php',
  'modules/api/realtime_token.php',
  'modules/api/push_notification.php',
  'assets/js/user_header.js',
  'modules/staff/communications/notification_center.php',
  'package.json',
];

for (const filePath of rootFiles) {
  assert.ok(existsSync(filePath), `Expected ${filePath} to exist`);
}

const sql = readFileSync('database/migrations/2026-03-31-wave1-foundation.sql', 'utf8');
const notificationService = readFileSync('includes/notification_service.php', 'utf8');
const realtimeSigner = readFileSync('includes/realtime_signer.php', 'utf8');
const realtimeTokenApi = readFileSync('modules/api/realtime_token.php', 'utf8');
const pushNotificationApi = readFileSync('modules/api/push_notification.php', 'utf8');
const adminHeader = readFileSync('includes/admin_header.php', 'utf8');
const userHeader = readFileSync('includes/header.php', 'utf8');
const adminHeaderJs = readFileSync('assets/js/admin_header.js', 'utf8');
const userHeaderJs = readFileSync('assets/js/user_header.js', 'utf8');
const serverJs = readFileSync('assets/js/server.js', 'utf8');
const packageJson = readFileSync('package.json', 'utf8');
const adminDashboard = readFileSync('modules/admin/dashboard.php', 'utf8');
const staffDashboard = readFileSync('modules/staff/dashboard.php', 'utf8');
const userNotifications = readFileSync('modules/user/notifications.php', 'utf8');

assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `user_notifications`'));
assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `email_campaigns`'));
assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `gate_access_logs`'));
assert.ok(notificationService.includes('function createUserNotification'));
assert.ok(notificationService.includes('function fetchUserNotifications'));
assert.ok(notificationService.includes('function markUserNotificationRead'));
assert.ok(notificationService.includes('function fanOutNotificationToUsers'));
assert.ok(realtimeSigner.includes('function issueRealtimeToken'));
assert.ok(realtimeSigner.includes('function verifyRealtimeToken'));
assert.ok(!realtimeSigner.includes('wave1-dev-realtime-secret'));
assert.ok(realtimeTokenApi.includes('issueRealtimeToken'));
assert.ok(realtimeTokenApi.includes('parse_ini_file') || realtimeTokenApi.includes('REALTIME_WS_URL'));
assert.ok(pushNotificationApi.includes('createUserNotification') || pushNotificationApi.includes('fanOutNotificationToUsers'));
assert.ok(pushNotificationApi.includes('/api/realtime/publish') || pushNotificationApi.includes('REALTIME_PUBLISH_URL'));
assert.ok(adminHeader.includes('assets/js/admin_header.js'));
assert.ok(adminHeader.includes('notification_center.php'));
assert.ok(adminHeader.includes('mass_email.php'));
assert.ok(adminHeader.includes('report_hub.php'));
assert.ok(adminHeader.includes('gate_scanner.php'));
assert.ok(adminHeader.includes('gate_logs.php'));
assert.ok(userHeader.includes('assets/js/user_header.js'));
assert.ok(userHeader.includes('data-base='));
assert.ok(userHeader.includes('modules/user/access_qr.php'));
assert.ok(userHeader.includes('modules/user/gate_history.php'));
assert.ok(adminHeaderJs.includes('modules/api/realtime_token.php'));
assert.ok(adminHeaderJs.includes('new WebSocket'));
assert.ok(userHeaderJs.includes('modules/api/realtime_token.php'));
assert.ok(userHeaderJs.includes('new WebSocket'));
assert.ok(serverJs.includes('WebSocketServer'));
assert.ok(serverJs.includes('/api/realtime/publish'));
assert.ok(adminDashboard.includes('modules/staff/communications/mass_email.php'));
assert.ok(adminDashboard.includes('modules/staff/report/report_hub.php'));
assert.ok(staffDashboard.includes('modules/staff/access/gate_scanner.php'));
assert.ok(staffDashboard.includes('modules/staff/access/gate_logs.php'));
assert.ok(userNotifications.includes('fetchUserNotifications') || userNotifications.includes('FROM user_notifications'));
assert.ok(packageJson.includes('"test:wave1"'));
assert.ok(packageJson.includes('"ws"'));

console.log('notification/realtime contract smoke test passed');
