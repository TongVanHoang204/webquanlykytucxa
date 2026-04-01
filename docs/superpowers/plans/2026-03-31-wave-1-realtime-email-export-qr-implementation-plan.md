# Wave 1 Realtime, Mass Email, Report Export, And QR Gate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the approved Wave 1 bundle for the dormitory system: unified realtime notifications for students and manager/admin users, immediate mass email from the admin UI, shared Excel/PDF report export, and QR-based dorm gate entry/exit with secure short-lived tokens.

**Architecture:** Keep PHP + MySQL as the system of record and add focused service helpers under `includes/` for notifications, email delivery, report datasets, and QR gate access. Extend the existing Node server in `assets/js/server.js` with a lightweight WebSocket hub so PHP can persist events to MySQL first and then push realtime updates to connected browsers without replacing the current application flow.

**Tech Stack:** PHP, MySQL, JavaScript, Node.js, Express, `ws`, existing `SimpleXLSXGen`, vendored FPDF-style PDF helper, PHP CLI lint, Node.js static contract smoke tests

---

## Scope Note

Wave 1 spans four user-facing subsystems, but they share a common foundation:

- one normalized user-targeted notification model
- one websocket delivery path
- one reporting/export service
- one access control log model for QR gate scans

That shared foundation is why this is one plan instead of four separate plans.

## File Structure

- Create: `database/migrations/2026-03-31-wave1-foundation.sql`
  Responsibility: manual SQL migration for new notification, email campaign, and QR gate tables plus compatibility indexes/backfills.
- Create: `includes/notification_service.php`
  Responsibility: create, fan out, and mark user notifications while hiding table details from pages and APIs.
- Create: `includes/realtime_signer.php`
  Responsibility: issue and validate short-lived HMAC websocket auth tokens shared between PHP and Node.
- Create: `includes/mass_email_service.php`
  Responsibility: resolve recipient groups, send immediate emails, and write campaign + recipient logs.
- Create: `includes/report_service.php`
  Responsibility: build normalized datasets for finance and dorm operations reports from one code path.
- Create: `includes/report_pdf.php`
  Responsibility: render report datasets into downloadable PDF output using a small vendored PDF helper.
- Vendor: `includes/vendor/fpdf/fpdf.php`
  Responsibility: bundled PDF dependency used by `includes/report_pdf.php`.
- Create: `includes/gate_qr_service.php`
  Responsibility: issue QR tokens, validate scans, infer IN/OUT, and write gate logs safely.
- Create: `modules/api/realtime_token.php`
  Responsibility: return a short-lived websocket token for the current logged-in user.
- Modify: `modules/api/get_notifications.php`
  Responsibility: switch notification reads to the new `user_notifications` model keyed by `UserID`.
- Modify: `modules/api/mark_read_notifications.php`
  Responsibility: mark one or many unified notifications as read for the current user.
- Create: `modules/api/push_notification.php`
  Responsibility: accept manager/admin notification composer requests and fan out rows into `user_notifications`.
- Create: `modules/api/admin_send_mass_email.php`
  Responsibility: accept the admin/manager “send now” action and execute the immediate campaign flow.
- Create: `modules/api/admin_email_campaigns.php`
  Responsibility: return campaign history and summary counts.
- Create: `modules/api/admin_email_campaign_detail.php`
  Responsibility: return per-recipient delivery detail for a single campaign.
- Create: `modules/api/report_data.php`
  Responsibility: return report preview JSON for a given `report_key` and filter set.
- Create: `modules/api/report_export.php`
  Responsibility: export a `report_key` to `xlsx` or `pdf` using the shared report service.
- Create: `modules/api/student_qr_token.php`
  Responsibility: issue a short-lived QR token for the logged-in student.
- Create: `modules/api/scan_gate_qr.php`
  Responsibility: validate a QR token, create a gate log, and emit realtime events.
- Create: `modules/api/gate_access_logs.php`
  Responsibility: return manager/admin gate access logs with filters.
- Create: `modules/api/student_gate_history.php`
  Responsibility: return gate access history for the logged-in student.
- Modify: `includes/admin_header.php`
  Responsibility: load new notification/realtime client logic and expose links to manager/admin communication and gate pages.
- Modify: `includes/header.php`
  Responsibility: load a dedicated user notification/realtime client and add links for QR + gate history.
- Modify: `assets/js/admin_header.js`
  Responsibility: replace 60-second polling with websocket-aware notification updates while keeping fetch fallback.
- Create: `assets/js/user_header.js`
  Responsibility: fetch realtime auth tokens, connect to websocket, and refresh user notification dropdown state.
- Modify: `assets/js/server.js`
  Responsibility: add websocket support, validate PHP-signed tokens, and expose a protected publish endpoint for PHP.
- Modify: `package.json`
  Responsibility: add `ws` dependency and scripts for Wave 1 smoke tests.
- Create: `modules/staff/communications/notification_center.php`
  Responsibility: manager/admin page for sending in-app notifications and reviewing recent pushes.
- Create: `modules/staff/communications/mass_email.php`
  Responsibility: manager/admin page for composing “send now” email campaigns and browsing campaign history.
- Create: `modules/staff/report/report_hub.php`
  Responsibility: manager/admin page for previewing and exporting the approved finance and dorm operations reports.
- Modify: `modules/admin/report/finance_report.php`
  Responsibility: preserve the current finance page but link to the new shared export/report hub.
- Create: `modules/user/access_qr.php`
  Responsibility: student page that renders the short-lived QR with countdown and refresh logic.
- Create: `modules/user/gate_history.php`
  Responsibility: student page showing personal dorm gate history.
- Create: `modules/staff/access/gate_scanner.php`
  Responsibility: manager/admin/staff scanner page for gate QR processing.
- Create: `modules/staff/access/gate_logs.php`
  Responsibility: filtered manager/admin log view for all dorm gate scans.
- Modify: `modules/admin/dashboard.php`
  Responsibility: expose entry points to the new Wave 1 communication/report features.
- Modify: `modules/staff/dashboard.php`
  Responsibility: expose entry points to mass email, report hub, scanner, and gate logs.
- Create: `tests/wave1/notification_realtime_contract.test.mjs`
  Responsibility: static contract checks for unified notification files, websocket token API, and shared client wiring.
- Create: `tests/wave1/email_report_contract.test.mjs`
  Responsibility: static contract checks for mass email, report APIs, and shared report helper wiring.
- Create: `tests/wave1/gate_qr_contract.test.mjs`
  Responsibility: static contract checks for QR gate pages, APIs, and SQL migration table presence.

## Task 1: Build The Wave 1 Foundation And Migration Layer

**Files:**
- Create: `database/migrations/2026-03-31-wave1-foundation.sql`
- Create: `includes/notification_service.php`
- Create: `includes/realtime_signer.php`
- Create: `includes/mass_email_service.php`
- Create: `includes/report_service.php`
- Create: `includes/report_pdf.php`
- Create: `includes/gate_qr_service.php`
- Vendor: `includes/vendor/fpdf/fpdf.php`
- Create: `tests/wave1/notification_realtime_contract.test.mjs`
- Create: `tests/wave1/email_report_contract.test.mjs`
- Create: `tests/wave1/gate_qr_contract.test.mjs`
- Modify: `package.json`

- [ ] **Step 1: Write the failing Wave 1 contract smoke tests**

```js
// tests/wave1/notification_realtime_contract.test.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

function read(filePath) {
  return readFileSync(filePath, 'utf8');
}

function expectIncludes(filePath, needle) {
  const content = read(filePath);
  assert.ok(content.includes(needle), `Expected ${filePath} to include: ${needle}`);
}

assert.ok(existsSync('database/migrations/2026-03-31-wave1-foundation.sql'), 'Expected wave1 migration file');
assert.ok(existsSync('includes/notification_service.php'), 'Expected notification service');
assert.ok(existsSync('includes/realtime_signer.php'), 'Expected realtime signer');
assert.ok(existsSync('modules/api/realtime_token.php'), 'Expected realtime token API');
assert.ok(existsSync('assets/js/user_header.js'), 'Expected user header realtime client');
expectIncludes('database/migrations/2026-03-31-wave1-foundation.sql', 'CREATE TABLE IF NOT EXISTS `user_notifications`');
expectIncludes('database/migrations/2026-03-31-wave1-foundation.sql', 'CREATE TABLE IF NOT EXISTS `email_campaigns`');
expectIncludes('database/migrations/2026-03-31-wave1-foundation.sql', 'CREATE TABLE IF NOT EXISTS `gate_access_logs`');
expectIncludes('package.json', '"test:wave1"');
console.log('notification/realtime contract smoke test passed');
```

```js
// tests/wave1/email_report_contract.test.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

function read(filePath) {
  return readFileSync(filePath, 'utf8');
}

assert.ok(existsSync('includes/mass_email_service.php'), 'Expected mass email service');
assert.ok(existsSync('includes/report_service.php'), 'Expected report service');
assert.ok(existsSync('includes/report_pdf.php'), 'Expected report pdf helper');
assert.ok(existsSync('modules/api/admin_send_mass_email.php'), 'Expected mass email API');
assert.ok(existsSync('modules/api/report_export.php'), 'Expected report export API');
assert.ok(read('database/migrations/2026-03-31-wave1-foundation.sql').includes('CREATE TABLE IF NOT EXISTS `email_campaign_recipients`'));
console.log('email/report contract smoke test passed');
```

```js
// tests/wave1/gate_qr_contract.test.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

const sql = readFileSync('database/migrations/2026-03-31-wave1-foundation.sql', 'utf8');
assert.ok(existsSync('includes/gate_qr_service.php'), 'Expected gate qr service');
assert.ok(existsSync('modules/api/student_qr_token.php'), 'Expected student qr token API');
assert.ok(existsSync('modules/api/scan_gate_qr.php'), 'Expected gate scan API');
assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `gate_qr_tokens`'));
assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `gate_access_logs`'));
console.log('gate qr contract smoke test passed');
```

- [ ] **Step 2: Run the smoke tests and verify they fail**

Run:

```bash
node tests/wave1/notification_realtime_contract.test.mjs
node tests/wave1/email_report_contract.test.mjs
node tests/wave1/gate_qr_contract.test.mjs
```

Expected:

```text
FAIL because the migration file and Wave 1 helper files do not exist yet.
```

- [ ] **Step 3: Write the migration and shared foundation helpers**

Create `database/migrations/2026-03-31-wave1-foundation.sql`:

```sql
CREATE TABLE IF NOT EXISTS `user_notifications` (
  `NotificationID` INT NOT NULL AUTO_INCREMENT,
  `UserID` INT NOT NULL,
  `Title` VARCHAR(255) NOT NULL,
  `Message` TEXT NOT NULL,
  `Type` VARCHAR(50) NOT NULL DEFAULT 'system',
  `Severity` VARCHAR(20) NOT NULL DEFAULT 'info',
  `Link` VARCHAR(255) DEFAULT NULL,
  `PayloadJson` JSON DEFAULT NULL,
  `IsRead` TINYINT(1) NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ReadAt` DATETIME DEFAULT NULL,
  PRIMARY KEY (`NotificationID`),
  KEY `idx_user_notifications_user_created` (`UserID`, `CreatedAt`),
  CONSTRAINT `fk_user_notifications_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `email_campaigns` (
  `CampaignID` INT NOT NULL AUTO_INCREMENT,
  `Subject` VARCHAR(255) NOT NULL,
  `BodyHtml` MEDIUMTEXT NOT NULL,
  `AudienceType` VARCHAR(50) NOT NULL,
  `CreatedByUserID` INT NOT NULL,
  `Status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `TotalRecipients` INT NOT NULL DEFAULT 0,
  `SuccessCount` INT NOT NULL DEFAULT 0,
  `FailCount` INT NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `SentAt` DATETIME DEFAULT NULL,
  PRIMARY KEY (`CampaignID`),
  CONSTRAINT `fk_email_campaigns_created_by` FOREIGN KEY (`CreatedByUserID`) REFERENCES `users` (`UserID`) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS `email_campaign_recipients` (
  `RecipientID` INT NOT NULL AUTO_INCREMENT,
  `CampaignID` INT NOT NULL,
  `UserID` INT NOT NULL,
  `Email` VARCHAR(255) NOT NULL,
  `SendStatus` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `ErrorMessage` TEXT DEFAULT NULL,
  `SentAt` DATETIME DEFAULT NULL,
  PRIMARY KEY (`RecipientID`),
  UNIQUE KEY `uniq_campaign_user` (`CampaignID`, `UserID`),
  CONSTRAINT `fk_email_campaign_recipients_campaign` FOREIGN KEY (`CampaignID`) REFERENCES `email_campaigns` (`CampaignID`) ON DELETE CASCADE,
  CONSTRAINT `fk_email_campaign_recipients_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `gate_qr_tokens` (
  `TokenID` INT NOT NULL AUTO_INCREMENT,
  `StudentID` INT NOT NULL,
  `TokenHash` CHAR(64) NOT NULL,
  `ExpiresAt` DATETIME NOT NULL,
  `IsUsed` TINYINT(1) NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`TokenID`),
  UNIQUE KEY `uniq_gate_qr_token_hash` (`TokenHash`),
  KEY `idx_gate_qr_tokens_student_created` (`StudentID`, `CreatedAt`),
  CONSTRAINT `fk_gate_qr_tokens_student` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `gate_access_logs` (
  `LogID` INT NOT NULL AUTO_INCREMENT,
  `StudentID` INT NOT NULL,
  `CardID` INT DEFAULT NULL,
  `GateName` VARCHAR(100) NOT NULL DEFAULT 'Cổng chính',
  `Direction` ENUM('IN', 'OUT') NOT NULL,
  `ScannedByUserID` INT DEFAULT NULL,
  `ScanMethod` VARCHAR(20) NOT NULL DEFAULT 'QR',
  `ScanToken` CHAR(64) DEFAULT NULL,
  `Status` VARCHAR(20) NOT NULL DEFAULT 'accepted',
  `RejectReason` VARCHAR(255) DEFAULT NULL,
  `CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`LogID`),
  KEY `idx_gate_access_logs_student_created` (`StudentID`, `CreatedAt`),
  KEY `idx_gate_access_logs_gate_created` (`GateName`, `CreatedAt`),
  CONSTRAINT `fk_gate_access_logs_student` FOREIGN KEY (`StudentID`) REFERENCES `students` (`StudentID`) ON DELETE CASCADE,
  CONSTRAINT `fk_gate_access_logs_card` FOREIGN KEY (`CardID`) REFERENCES `accesscards` (`CardID`) ON DELETE SET NULL,
  CONSTRAINT `fk_gate_access_logs_scanned_by` FOREIGN KEY (`ScannedByUserID`) REFERENCES `users` (`UserID`) ON DELETE SET NULL
);
```

Create `includes/notification_service.php`:

```php
<?php
function createUserNotification(mysqli $conn, array $row): int
{
    $stmt = $conn->prepare("
        INSERT INTO user_notifications
            (UserID, Title, Message, Type, Severity, Link, PayloadJson)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $payload = $row['PayloadJson'] ?? null;
    $stmt->bind_param(
        'issssss',
        $row['UserID'],
        $row['Title'],
        $row['Message'],
        $row['Type'],
        $row['Severity'],
        $row['Link'],
        $payload
    );
    $stmt->execute();
    return (int)$stmt->insert_id;
}
```

Create `includes/realtime_signer.php`:

```php
<?php
function issueRealtimeToken(int $userId, string $role, string $secret, int $ttlSeconds = 120): string
{
    $payload = [
        'uid' => $userId,
        'role' => $role,
        'exp' => time() + $ttlSeconds,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $body, $secret);
    return $body . '.' . $sig;
}
```

Create `includes/mass_email_service.php`:

```php
<?php
function resolveCampaignRecipients(mysqli $conn, string $audienceType): array
{
    $sql = match ($audienceType) {
        'students' => "SELECT UserID, Email, FullName FROM users WHERE Role = 'Student' AND IsActive = 1 AND Email IS NOT NULL AND Email <> ''",
        'staff_admin' => "SELECT UserID, Email, FullName FROM users WHERE Role IN ('Admin', 'Manager') AND IsActive = 1 AND Email IS NOT NULL AND Email <> ''",
        default => throw new InvalidArgumentException('Unsupported audience type'),
    };
    $rows = [];
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}
```

Create `includes/report_service.php`:

```php
<?php
function buildReportDataset(mysqli $conn, string $reportKey, array $filters): array
{
    return match ($reportKey) {
        'finance_detail' => buildFinanceDetailDataset($conn, $filters),
        'students_list' => buildStudentListDataset($conn, $filters),
        'contracts_list' => buildContractListDataset($conn, $filters),
        'rooms_occupancy' => buildRoomOccupancyDataset($conn, $filters),
        default => throw new InvalidArgumentException('Unsupported report key'),
    };
}
```

Create `includes/report_pdf.php`:

```php
<?php
require_once __DIR__ . '/vendor/fpdf/fpdf.php';

function outputSimpleReportPdf(string $title, array $columns, array $rows, string $filename): void
{
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, iconv('UTF-8', 'windows-1252//TRANSLIT', $title), 0, 1);
    $pdf->SetFont('Arial', '', 9);
    foreach ($columns as $column) {
        $pdf->Cell(35, 8, iconv('UTF-8', 'windows-1252//TRANSLIT', $column), 1, 0, 'C');
    }
    $pdf->Ln();
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $pdf->Cell(35, 8, iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$value), 1);
        }
        $pdf->Ln();
    }
    $pdf->Output('D', $filename);
}
```

Vendor `includes/vendor/fpdf/fpdf.php` from the FPDF 1.86 distribution so the helper above can stay small and the repo does not need Composer for PDF generation.

Create `includes/gate_qr_service.php`:

```php
<?php
function hashGateToken(string $plainToken): string
{
    return hash('sha256', $plainToken);
}

function inferGateDirection(?string $lastDirection): string
{
    return $lastDirection === 'IN' ? 'OUT' : 'IN';
}
```

Modify `package.json`:

```json
{
  "scripts": {
    "start": "node assets/js/server.js",
    "web": "node web-server.js",
    "test:wave1": "node tests/wave1/notification_realtime_contract.test.mjs && node tests/wave1/email_report_contract.test.mjs && node tests/wave1/gate_qr_contract.test.mjs"
  },
  "dependencies": {
    "@google/genai": "^1.30.0",
    "cors": "^2.8.5",
    "dotenv": "^17.2.3",
    "express": "^5.1.0",
    "mysql2": "^3.15.3",
    "ws": "^8.18.0"
  }
}
```

- [ ] **Step 4: Run the smoke tests again and lint the new PHP helpers**

Run:

```bash
node tests/wave1/notification_realtime_contract.test.mjs
node tests/wave1/email_report_contract.test.mjs
node tests/wave1/gate_qr_contract.test.mjs
php -l includes/notification_service.php
php -l includes/realtime_signer.php
php -l includes/mass_email_service.php
php -l includes/report_service.php
php -l includes/report_pdf.php
php -l includes/gate_qr_service.php
```

Expected:

```text
All three smoke tests pass.
No syntax errors detected in the PHP helper files.
```

- [ ] **Step 5: Commit the foundation layer**

```bash
git add package.json database/migrations/2026-03-31-wave1-foundation.sql includes/notification_service.php includes/realtime_signer.php includes/mass_email_service.php includes/report_service.php includes/report_pdf.php includes/gate_qr_service.php tests/wave1/notification_realtime_contract.test.mjs tests/wave1/email_report_contract.test.mjs tests/wave1/gate_qr_contract.test.mjs
git commit -m "feat: add wave1 foundation services and schema"
```

## Task 2: Implement Unified Notifications And WebSocket Realtime Delivery

**Files:**
- Create: `modules/api/realtime_token.php`
- Modify: `modules/api/get_notifications.php`
- Modify: `modules/api/mark_read_notifications.php`
- Create: `modules/api/push_notification.php`
- Modify: `includes/admin_header.php`
- Modify: `includes/header.php`
- Modify: `assets/js/admin_header.js`
- Create: `assets/js/user_header.js`
- Modify: `assets/js/server.js`
- Create: `modules/staff/communications/notification_center.php`
- Modify: `tests/wave1/notification_realtime_contract.test.mjs`

- [ ] **Step 1: Extend the realtime contract smoke test to assert client/server wiring**

```js
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const adminHeader = readFileSync('includes/admin_header.php', 'utf8');
const userHeader = readFileSync('includes/header.php', 'utf8');
const adminHeaderJs = readFileSync('assets/js/admin_header.js', 'utf8');
const serverJs = readFileSync('assets/js/server.js', 'utf8');
const pushNotificationApi = readFileSync('modules/api/push_notification.php', 'utf8');

assert.ok(adminHeader.includes('assets/js/admin_header.js'));
assert.ok(userHeader.includes('assets/js/user_header.js'));
assert.ok(adminHeaderJs.includes('modules/api/realtime_token.php'));
assert.ok(adminHeaderJs.includes('new WebSocket'));
assert.ok(serverJs.includes('/api/realtime/publish'));
assert.ok(serverJs.includes('WebSocketServer'));
assert.ok(pushNotificationApi.includes('createUserNotification'));
console.log('notification/realtime wiring contract passed');
```

- [ ] **Step 2: Run the notification contract smoke test and verify it fails on missing wiring**

Run:

```bash
node tests/wave1/notification_realtime_contract.test.mjs
```

Expected:

```text
FAIL because user_header.js, realtime_token.php, and websocket wiring do not exist yet.
```

- [ ] **Step 3: Implement the unified notification APIs, websocket token endpoint, client wiring, and publish path**

Create `modules/api/realtime_token.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/realtime_signer.php';

if (empty($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED']);
    exit;
}

$secret = getenv('REALTIME_SHARED_SECRET') ?: 'wave1-local-secret';
$token = issueRealtimeToken((int)$_SESSION['UserID'], (string)($_SESSION['Role'] ?? 'Guest'), $secret);

echo json_encode([
    'ok' => true,
    'token' => $token,
    'wsUrl' => (getenv('REALTIME_WS_URL') ?: 'ws://localhost:3000/ws'),
]);
```

Rewrite `modules/api/get_notifications.php` around `user_notifications`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';

if (empty($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$userId = (int)$_SESSION['UserID'];
$stmt = $conn->prepare("
    SELECT NotificationID, Title, Message, Type, Severity, Link, IsRead, CreatedAt
    FROM user_notifications
    WHERE UserID = ?
    ORDER BY CreatedAt DESC
    LIMIT 30
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode($rows);
```

Rewrite `modules/api/mark_read_notifications.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';

if (empty($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED']);
    exit;
}

$userId = (int)$_SESSION['UserID'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;

if ($notificationId > 0) {
    $stmt = $conn->prepare("UPDATE user_notifications SET IsRead = 1, ReadAt = NOW() WHERE NotificationID = ? AND UserID = ?");
    $stmt->bind_param('ii', $notificationId, $userId);
} else {
    $stmt = $conn->prepare("UPDATE user_notifications SET IsRead = 1, ReadAt = NOW() WHERE UserID = ? AND IsRead = 0");
    $stmt->bind_param('i', $userId);
}

$stmt->execute();
echo json_encode(['ok' => true]);
```

Create `assets/js/user_header.js`:

```js
document.addEventListener('DOMContentLoaded', () => {
  const notifyWrap = document.querySelector('.notify');
  if (!notifyWrap) return;

  async function loadSocket() {
    const res = await fetch(`${document.body.dataset.base || '/'}modules/api/realtime_token.php`, { cache: 'no-store' });
    const data = await res.json();
    if (!data.ok) return;
    const socket = new WebSocket(`${data.wsUrl}?token=${encodeURIComponent(data.token)}`);
    socket.addEventListener('message', () => {
      window.dispatchEvent(new CustomEvent('wave1:notification-refresh'));
    });
  }

  loadSocket().catch(console.warn);
});
```

Update `assets/js/admin_header.js` notification block so it fetches a realtime token and refreshes on socket events:

```js
window.addEventListener('wave1:notification-refresh', fetchNotifications);

async function connectRealtime() {
  const apiUrl = `${baseUrl}modules/api/realtime_token.php`;
  const res = await fetch(apiUrl, { cache: 'no-store' });
  const data = await res.json();
  if (!data.ok) return;

  const ws = new WebSocket(`${data.wsUrl}?token=${encodeURIComponent(data.token)}`);
  ws.addEventListener('message', () => {
    fetchNotifications();
  });
  ws.addEventListener('close', () => {
    setTimeout(connectRealtime, 5000);
  });
}

connectRealtime().catch(console.warn);
```

Update `includes/header.php`:

```php
<body<?= $pageBodyClass !== '' ? ' class="' . htmlspecialchars($pageBodyClass) . '"' : '' ?> data-base="<?= htmlspecialchars($base) ?>">
<script src="<?= $base ?>assets/js/user_header.js" defer></script>
```

Extend `assets/js/server.js`:

```js
import { WebSocketServer } from 'ws';
import crypto from 'node:crypto';

const realtimeSecret = process.env.REALTIME_SHARED_SECRET || 'wave1-local-secret';
const wss = new WebSocketServer({ noServer: true });
const socketsByUser = new Map();

function verifyRealtimeToken(token) {
  const [body, sig] = token.split('.');
  const expected = crypto.createHmac('sha256', realtimeSecret).update(body).digest('hex');
  if (sig !== expected) throw new Error('BAD_SIGNATURE');
  const payload = JSON.parse(Buffer.from(body, 'base64url').toString('utf8'));
  if (payload.exp < Math.floor(Date.now() / 1000)) throw new Error('TOKEN_EXPIRED');
  return payload;
}

const server = app.listen(3000, () => {
  console.log('Wave 1 realtime server listening at http://localhost:3000');
});

server.on('upgrade', (request, socket, head) => {
  const url = new URL(request.url, 'http://localhost:3000');
  if (url.pathname !== '/ws') {
    socket.destroy();
    return;
  }

  try {
    const payload = verifyRealtimeToken(url.searchParams.get('token') || '');
    wss.handleUpgrade(request, socket, head, (ws) => {
      ws.userId = String(payload.uid);
      const bucket = socketsByUser.get(ws.userId) || [];
      bucket.push(ws);
      socketsByUser.set(ws.userId, bucket);
      ws.on('close', () => {
        const next = (socketsByUser.get(ws.userId) || []).filter((client) => client !== ws);
        socketsByUser.set(ws.userId, next);
      });
    });
  } catch (error) {
    socket.destroy();
  }
});

app.post('/api/realtime/publish', (req, res) => {
  const internalKey = req.headers['x-wave1-internal-key'];
  if (internalKey !== (process.env.REALTIME_INTERNAL_KEY || 'wave1-internal-key')) {
    return res.status(403).json({ ok: false });
  }

  const { userIds = [], event = 'notification' } = req.body || {};
  userIds.forEach((userId) => {
    const clients = socketsByUser.get(String(userId)) || [];
    clients.forEach((socket) => socket.send(JSON.stringify({ event })));
  });
  res.json({ ok: true });
});
```

Create `modules/api/push_notification.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/notification_service.php';
requireRole(['Admin', 'Manager']);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$title = trim($input['title'] ?? '');
$message = trim($input['message'] ?? '');
$audienceType = trim($input['audience_type'] ?? 'students');

if ($title === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'TITLE_AND_MESSAGE_REQUIRED']);
    exit;
}

$sql = $audienceType === 'staff_admin'
    ? "SELECT UserID FROM users WHERE Role IN ('Admin', 'Manager') AND IsActive = 1"
    : "SELECT UserID FROM users WHERE Role = 'Student' AND IsActive = 1";

$result = $conn->query($sql);
$userIds = [];
while ($row = $result->fetch_assoc()) {
    $userIds[] = (int)$row['UserID'];
}

foreach ($userIds as $userId) {
    createUserNotification($conn, [
        'UserID' => $userId,
        'Title' => $title,
        'Message' => $message,
        'Type' => 'system',
        'Severity' => 'info',
        'Link' => null,
        'PayloadJson' => null,
    ]);
}

echo json_encode(['ok' => true, 'count' => count($userIds)]);
```

Create `modules/staff/communications/notification_center.php` with a manager/admin-only form:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once '../../../includes/admin_header.php';
?>
<section class="mod-container">
  <div class="mod-header">
    <div class="mod-header-left">
      <h2><i class="fas fa-bell"></i> Trung tâm thông báo</h2>
    </div>
  </div>
  <form id="notificationCenterForm" class="mod-card">
    <label>Tiêu đề</label>
    <input type="text" name="title" required>
    <label>Nội dung</label>
    <textarea name="message" rows="6" required></textarea>
    <label>Nhóm nhận</label>
    <select name="audience_type">
      <option value="students">Sinh viên</option>
      <option value="staff_admin">Manager/Admin</option>
    </select>
    <button type="button" class="mod-btn mod-btn-primary">Gửi thông báo</button>
  </form>
</section>
```

- [ ] **Step 4: Run the notification contract smoke test and lint all touched PHP files**

Run:

```bash
node tests/wave1/notification_realtime_contract.test.mjs
php -l modules/api/realtime_token.php
php -l modules/api/get_notifications.php
php -l modules/api/mark_read_notifications.php
php -l modules/api/push_notification.php
php -l modules/staff/communications/notification_center.php
```

Expected:

```text
The notification realtime contract smoke test passes.
No syntax errors detected in the touched PHP files.
```

- [ ] **Step 5: Commit the notification and websocket slice**

```bash
git add modules/api/realtime_token.php modules/api/get_notifications.php modules/api/mark_read_notifications.php modules/api/push_notification.php includes/admin_header.php includes/header.php assets/js/admin_header.js assets/js/user_header.js assets/js/server.js modules/staff/communications/notification_center.php tests/wave1/notification_realtime_contract.test.mjs
git commit -m "feat: add unified notifications and websocket updates"
```

## Task 3: Implement Immediate Mass Email Campaigns With Delivery Logging

**Files:**
- Create: `modules/staff/communications/mass_email.php`
- Create: `modules/api/admin_send_mass_email.php`
- Create: `modules/api/admin_email_campaigns.php`
- Create: `modules/api/admin_email_campaign_detail.php`
- Modify: `includes/mass_email_service.php`
- Modify: `tests/wave1/email_report_contract.test.mjs`

- [ ] **Step 1: Extend the email/report contract test to cover the mass email UI and APIs**

```js
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

assert.ok(existsSync('modules/staff/communications/mass_email.php'), 'Expected mass email page');
assert.ok(existsSync('modules/api/admin_send_mass_email.php'), 'Expected send now mass email API');
assert.ok(existsSync('modules/api/admin_email_campaigns.php'), 'Expected campaign list API');
assert.ok(existsSync('modules/api/admin_email_campaign_detail.php'), 'Expected campaign detail API');
assert.ok(readFileSync('includes/mass_email_service.php', 'utf8').includes('resolveCampaignRecipients'));
console.log('mass email contract passed');
```

- [ ] **Step 2: Run the email/report contract test and verify it fails**

Run:

```bash
node tests/wave1/email_report_contract.test.mjs
```

Expected:

```text
FAIL because the mass email page and APIs do not exist yet.
```

- [ ] **Step 3: Implement the campaign page, send-now API, and delivery log endpoints**

Extend `includes/mass_email_service.php`:

```php
<?php
function createEmailCampaign(mysqli $conn, string $subject, string $bodyHtml, string $audienceType, int $createdByUserId): int
{
    $stmt = $conn->prepare("
        INSERT INTO email_campaigns (Subject, BodyHtml, AudienceType, CreatedByUserID, Status)
        VALUES (?, ?, ?, ?, 'sending')
    ");
    $stmt->bind_param('sssi', $subject, $bodyHtml, $audienceType, $createdByUserId);
    $stmt->execute();
    return (int)$stmt->insert_id;
}

function sendCampaignNow(mysqli $conn, int $campaignId, array $recipients, string $subject, string $bodyHtml): array
{
    $success = 0;
    $fail = 0;

    foreach ($recipients as $recipient) {
        $status = @mail($recipient['Email'], $subject, strip_tags($bodyHtml), "Content-Type: text/plain; charset=UTF-8");
        $stmt = $conn->prepare("
            INSERT INTO email_campaign_recipients (CampaignID, UserID, Email, SendStatus, ErrorMessage, SentAt)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $sendStatus = $status ? 'sent' : 'failed';
        $error = $status ? null : 'mail() returned false';
        $sentAt = $status ? date('Y-m-d H:i:s') : null;
        $stmt->bind_param('iissss', $campaignId, $recipient['UserID'], $recipient['Email'], $sendStatus, $error, $sentAt);
        $stmt->execute();
        $status ? $success++ : $fail++;
    }

    $stmt = $conn->prepare("
        UPDATE email_campaigns
        SET Status = ?, TotalRecipients = ?, SuccessCount = ?, FailCount = ?, SentAt = NOW()
        WHERE CampaignID = ?
    ");
    $finalStatus = 'completed';
    $total = count($recipients);
    $stmt->bind_param('siiii', $finalStatus, $total, $success, $fail, $campaignId);
    $stmt->execute();

    return ['total' => $total, 'success' => $success, 'fail' => $fail];
}
```

Create `modules/api/admin_send_mass_email.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/mass_email_service.php';
requireRole(['Admin', 'Manager']);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$subject = trim($input['subject'] ?? '');
$bodyHtml = trim($input['body_html'] ?? '');
$audienceType = trim($input['audience_type'] ?? 'students');

if ($subject === '' || $bodyHtml === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'SUBJECT_AND_BODY_REQUIRED']);
    exit;
}

$createdBy = (int)$_SESSION['UserID'];
$campaignId = createEmailCampaign($conn, $subject, $bodyHtml, $audienceType, $createdBy);
$recipients = resolveCampaignRecipients($conn, $audienceType);
$summary = sendCampaignNow($conn, $campaignId, $recipients, $subject, $bodyHtml);

echo json_encode(['ok' => true, 'campaign_id' => $campaignId, 'summary' => $summary]);
```

Create `modules/api/admin_email_campaigns.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$result = $conn->query("
    SELECT c.CampaignID, c.Subject, c.AudienceType, c.Status, c.TotalRecipients, c.SuccessCount, c.FailCount, c.CreatedAt, c.SentAt, u.FullName AS CreatedByName
    FROM email_campaigns c
    JOIN users u ON u.UserID = c.CreatedByUserID
    ORDER BY c.CreatedAt DESC
    LIMIT 30
");

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode(['ok' => true, 'rows' => $rows]);
```

Create `modules/api/admin_email_campaign_detail.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$campaignId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("
    SELECT RecipientID, UserID, Email, SendStatus, ErrorMessage, SentAt
    FROM email_campaign_recipients
    WHERE CampaignID = ?
    ORDER BY RecipientID ASC
");
$stmt->bind_param('i', $campaignId);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode(['ok' => true, 'rows' => $rows]);
```

Create `modules/staff/communications/mass_email.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once '../../../includes/admin_header.php';
?>
<section class="mod-container">
  <div class="mod-header">
    <div class="mod-header-left">
      <h2><i class="fas fa-envelope"></i> Gửi email hàng loạt</h2>
    </div>
  </div>
  <form id="massEmailForm" class="mod-card">
    <label>Nhóm nhận</label>
    <select name="audience_type">
      <option value="students">Sinh viên</option>
      <option value="staff_admin">Manager/Admin</option>
    </select>
    <label>Tiêu đề</label>
    <input type="text" name="subject" required>
    <label>Nội dung</label>
    <textarea name="body_html" rows="10" required></textarea>
    <button type="submit" class="mod-btn mod-btn-primary">Gửi ngay</button>
  </form>
</section>
```

- [ ] **Step 4: Run the email/report contract test and lint the mass email PHP files**

Run:

```bash
node tests/wave1/email_report_contract.test.mjs
php -l includes/mass_email_service.php
php -l modules/api/admin_send_mass_email.php
php -l modules/api/admin_email_campaigns.php
php -l modules/api/admin_email_campaign_detail.php
php -l modules/staff/communications/mass_email.php
```

Expected:

```text
The email/report contract smoke test passes.
No syntax errors detected in the mass email PHP files.
```

- [ ] **Step 5: Commit the mass email slice**

```bash
git add includes/mass_email_service.php modules/api/admin_send_mass_email.php modules/api/admin_email_campaigns.php modules/api/admin_email_campaign_detail.php modules/staff/communications/mass_email.php tests/wave1/email_report_contract.test.mjs
git commit -m "feat: add immediate mass email campaigns"
```

## Task 4: Build The Shared Report Preview And Excel/PDF Export Hub

**Files:**
- Create: `modules/api/report_data.php`
- Create: `modules/api/report_export.php`
- Create: `modules/staff/report/report_hub.php`
- Modify: `includes/report_service.php`
- Modify: `includes/report_pdf.php`
- Modify: `modules/admin/report/finance_report.php`
- Modify: `tests/wave1/email_report_contract.test.mjs`

- [ ] **Step 1: Extend the report contract test to cover the shared report hub and export endpoints**

```js
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

assert.ok(existsSync('modules/staff/report/report_hub.php'), 'Expected report hub page');
assert.ok(existsSync('modules/api/report_data.php'), 'Expected report preview API');
assert.ok(existsSync('modules/api/report_export.php'), 'Expected report export API');
assert.ok(readFileSync('includes/report_service.php', 'utf8').includes('buildReportDataset'));
assert.ok(readFileSync('includes/report_pdf.php', 'utf8').includes('outputSimpleReportPdf'));
console.log('shared report contract passed');
```

- [ ] **Step 2: Run the report contract test and verify it fails**

Run:

```bash
node tests/wave1/email_report_contract.test.mjs
```

Expected:

```text
FAIL because the shared report hub and export APIs do not exist yet.
```

- [ ] **Step 3: Implement the shared report preview and export flow**

Extend `includes/report_service.php` with concrete dataset builders:

```php
<?php
function buildFinanceDetailDataset(mysqli $conn, array $filters): array
{
    $sql = "
        SELECT i.InvoiceID, s.FullName, s.StudentCode, b.BuildingName, r.RoomNumber, i.TotalAmount, i.Status, i.DueDate, i.Month, i.Year
        FROM invoices i
        JOIN contracts c ON c.ContractID = i.ContractID
        JOIN students s ON s.StudentID = c.StudentID
        JOIN rooms r ON r.RoomID = c.RoomID
        JOIN buildings b ON b.BuildingID = r.BuildingID
        ORDER BY i.Year DESC, i.Month DESC, i.InvoiceID DESC
    ";
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return [
        'title' => 'Báo cáo tài chính chi tiết',
        'columns' => ['Hóa đơn', 'Sinh viên', 'MSSV', 'Tòa', 'Phòng', 'Tổng tiền', 'Trạng thái', 'Hạn', 'Tháng', 'Năm'],
        'rows' => array_map(fn($row) => [
            $row['InvoiceID'],
            $row['FullName'],
            $row['StudentCode'],
            $row['BuildingName'],
            $row['RoomNumber'],
            $row['TotalAmount'],
            $row['Status'],
            $row['DueDate'],
            $row['Month'],
            $row['Year'],
        ], $rows),
    ];
}
```

Create `modules/api/report_data.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/report_service.php';
requireRole(['Admin', 'Manager']);

$reportKey = trim($_GET['report_key'] ?? '');
$dataset = buildReportDataset($conn, $reportKey, $_GET);
echo json_encode(['ok' => true, 'dataset' => $dataset]);
```

Create `modules/api/report_export.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/report_service.php';
require_once '../../includes/report_pdf.php';
require_once '../../includes/SimpleXLSXGen.php';
requireRole(['Admin', 'Manager']);

$reportKey = trim($_GET['report_key'] ?? '');
$format = trim($_GET['format'] ?? 'xlsx');
$dataset = buildReportDataset($conn, $reportKey, $_GET);

if ($format === 'pdf') {
    outputSimpleReportPdf($dataset['title'], $dataset['columns'], $dataset['rows'], $reportKey . '.pdf');
    exit;
}

$sheetRows = array_merge([$dataset['columns']], $dataset['rows']);
$xlsx = Shuchkin\SimpleXLSXGen::fromArray($sheetRows, 'Du lieu');
$xlsx->downloadAs($reportKey . '.xlsx');
```

Create `modules/staff/report/report_hub.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once '../../../includes/admin_header.php';
?>
<section class="mod-container">
  <div class="mod-header">
    <div class="mod-header-left">
      <h2><i class="fas fa-file-export"></i> Trung tâm báo cáo</h2>
    </div>
  </div>
  <div class="mod-card">
    <label>Nhóm báo cáo</label>
    <select id="reportKey">
      <option value="finance_detail">Tài chính chi tiết</option>
      <option value="students_list">Danh sách sinh viên</option>
      <option value="contracts_list">Danh sách hợp đồng</option>
      <option value="rooms_occupancy">Lấp đầy phòng ở</option>
    </select>
    <div class="mod-actions">
      <a class="mod-btn mod-btn-outline" href="#" id="exportXlsxBtn">Xuất Excel</a>
      <a class="mod-btn mod-btn-primary" href="#" id="exportPdfBtn">Xuất PDF</a>
    </div>
    <div id="reportPreview"></div>
  </div>
</section>
```

Update `modules/admin/report/finance_report.php` header actions:

```php
<a href="<?= $base ?>modules/staff/report/report_hub.php?report_key=finance_detail" class="mod-btn mod-btn-outline mod-btn-sm">
    <i class="fas fa-file-export"></i> Trung tâm export mới
</a>
```

- [ ] **Step 4: Run the report contract test and lint the report PHP files**

Run:

```bash
node tests/wave1/email_report_contract.test.mjs
php -l includes/report_service.php
php -l includes/report_pdf.php
php -l modules/api/report_data.php
php -l modules/api/report_export.php
php -l modules/staff/report/report_hub.php
php -l modules/admin/report/finance_report.php
```

Expected:

```text
The shared report contract passes.
No syntax errors detected in the report PHP files.
```

- [ ] **Step 5: Commit the shared report export slice**

```bash
git add includes/report_service.php includes/report_pdf.php modules/api/report_data.php modules/api/report_export.php modules/staff/report/report_hub.php modules/admin/report/finance_report.php tests/wave1/email_report_contract.test.mjs
git commit -m "feat: add shared report preview and export hub"
```

## Task 5: Implement Student QR Tokens, Gate Scanner, And Gate Log Screens

**Files:**
- Create: `modules/api/student_qr_token.php`
- Create: `modules/api/scan_gate_qr.php`
- Create: `modules/api/gate_access_logs.php`
- Create: `modules/api/student_gate_history.php`
- Create: `modules/user/access_qr.php`
- Create: `modules/user/gate_history.php`
- Create: `modules/staff/access/gate_scanner.php`
- Create: `modules/staff/access/gate_logs.php`
- Modify: `includes/gate_qr_service.php`
- Modify: `tests/wave1/gate_qr_contract.test.mjs`

- [ ] **Step 1: Extend the gate QR contract smoke test to cover pages and APIs**

```js
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

assert.ok(existsSync('modules/user/access_qr.php'), 'Expected student QR page');
assert.ok(existsSync('modules/user/gate_history.php'), 'Expected student gate history page');
assert.ok(existsSync('modules/staff/access/gate_scanner.php'), 'Expected gate scanner page');
assert.ok(existsSync('modules/staff/access/gate_logs.php'), 'Expected gate logs page');
assert.ok(readFileSync('includes/gate_qr_service.php', 'utf8').includes('inferGateDirection'));
console.log('gate qr pages contract passed');
```

- [ ] **Step 2: Run the gate QR contract test and verify it fails**

Run:

```bash
node tests/wave1/gate_qr_contract.test.mjs
```

Expected:

```text
FAIL because the student and staff QR pages do not exist yet.
```

- [ ] **Step 3: Implement QR token issuance, scanner validation, and log pages**

Extend `includes/gate_qr_service.php`:

```php
<?php
function issueStudentGateQrToken(mysqli $conn, int $studentId): array
{
    $plainToken = bin2hex(random_bytes(24));
    $tokenHash = hashGateToken($plainToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 60);
    $stmt = $conn->prepare("
        INSERT INTO gate_qr_tokens (StudentID, TokenHash, ExpiresAt, IsUsed)
        VALUES (?, ?, ?, 0)
    ");
    $stmt->bind_param('iss', $studentId, $tokenHash, $expiresAt);
    $stmt->execute();
    return ['token' => $plainToken, 'expires_at' => $expiresAt];
}

function registerGateScan(mysqli $conn, int $studentId, string $tokenHash, int $scannedByUserId, string $gateName = 'Cổng chính'): array
{
    $stmt = $conn->prepare("
        SELECT Direction
        FROM gate_access_logs
        WHERE StudentID = ? AND Status = 'accepted'
        ORDER BY CreatedAt DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lastDirection = ($row = $result->fetch_assoc()) ? $row['Direction'] : null;
    $direction = inferGateDirection($lastDirection);

    $insert = $conn->prepare("
        INSERT INTO gate_access_logs (StudentID, GateName, Direction, ScannedByUserID, ScanMethod, ScanToken, Status)
        VALUES (?, ?, ?, ?, 'QR', ?, 'accepted')
    ");
    $insert->bind_param('issis', $studentId, $gateName, $direction, $scannedByUserId, $tokenHash);
    $insert->execute();

    return ['direction' => $direction];
}
```

Create `modules/api/student_qr_token.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/gate_qr_service.php';

if (empty($_SESSION['UserID']) || ($_SESSION['Role'] ?? '') !== 'Student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
    exit;
}

$userId = (int)$_SESSION['UserID'];
$stmt = $conn->prepare("SELECT StudentID FROM students WHERE UserID = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$payload = issueStudentGateQrToken($conn, (int)$row['StudentID']);

echo json_encode(['ok' => true, 'token' => $payload['token'], 'expires_at' => $payload['expires_at']]);
```

Create `modules/api/scan_gate_qr.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/gate_qr_service.php';
requireRole(['Admin', 'Manager']);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$plainToken = trim($input['token'] ?? '');
$tokenHash = hashGateToken($plainToken);

$stmt = $conn->prepare("
    SELECT TokenID, StudentID, ExpiresAt, IsUsed
    FROM gate_qr_tokens
    WHERE TokenHash = ?
    ORDER BY TokenID DESC
    LIMIT 1
");
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$result = $stmt->get_result();
$tokenRow = $result->fetch_assoc();

if (!$tokenRow || $tokenRow['IsUsed'] || strtotime($tokenRow['ExpiresAt']) < time()) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'INVALID_OR_EXPIRED_TOKEN']);
    exit;
}

$scan = registerGateScan($conn, (int)$tokenRow['StudentID'], $tokenHash, (int)$_SESSION['UserID']);
$conn->query("UPDATE gate_qr_tokens SET IsUsed = 1 WHERE TokenID = " . (int)$tokenRow['TokenID']);

echo json_encode(['ok' => true, 'direction' => $scan['direction']]);
```

Create `modules/user/access_qr.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';
?>
<section class="mod-container">
  <div class="mod-header">
    <div class="mod-header-left">
      <h2><i class="fas fa-qrcode"></i> Mã QR ra/vào cổng</h2>
    </div>
  </div>
  <div class="mod-card">
    <div id="studentQrToken">Đang tải mã QR...</div>
  </div>
</section>
```

Create `modules/staff/access/gate_scanner.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once '../../../includes/admin_header.php';
?>
<section class="mod-container">
  <div class="mod-header">
    <div class="mod-header-left">
      <h2><i class="fas fa-door-open"></i> Quét QR cổng ký túc xá</h2>
    </div>
  </div>
  <div class="mod-card">
    <video id="gateScannerVideo" autoplay playsinline></video>
    <div id="gateScannerResult">Sẵn sàng quét mã QR.</div>
  </div>
</section>
```

Create `modules/staff/access/gate_logs.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../../db_connect.php';
require_once '../../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);
require_once '../../../includes/admin_header.php';
?>
<section class="mod-container">
  <div class="mod-header">
    <div class="mod-header-left">
      <h2><i class="fas fa-list"></i> Nhật ký ra/vào cổng</h2>
    </div>
  </div>
  <div id="gateLogsTable" class="mod-card"></div>
</section>
```

Create `modules/api/gate_access_logs.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$result = $conn->query("
    SELECT l.LogID, l.GateName, l.Direction, l.Status, l.RejectReason, l.CreatedAt, s.FullName, s.StudentCode
    FROM gate_access_logs l
    JOIN students s ON s.StudentID = l.StudentID
    ORDER BY l.CreatedAt DESC
    LIMIT 100
");

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode(['ok' => true, 'rows' => $rows]);
```

Create `modules/api/student_gate_history.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';

if (empty($_SESSION['UserID']) || ($_SESSION['Role'] ?? '') !== 'Student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN']);
    exit;
}

$stmt = $conn->prepare("
    SELECT l.LogID, l.GateName, l.Direction, l.Status, l.RejectReason, l.CreatedAt
    FROM gate_access_logs l
    JOIN students s ON s.StudentID = l.StudentID
    WHERE s.UserID = ?
    ORDER BY l.CreatedAt DESC
    LIMIT 50
");
$stmt->bind_param('i', $_SESSION['UserID']);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
echo json_encode(['ok' => true, 'rows' => $rows]);
```

Create `modules/user/gate_history.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/auth_check.php';
?>
<section class="mod-container">
  <div class="mod-header">
    <div class="mod-header-left">
      <h2><i class="fas fa-clock-rotate-left"></i> Lịch sử ra/vào</h2>
    </div>
  </div>
  <div id="studentGateHistoryTable" class="mod-card"></div>
</section>
```

- [ ] **Step 4: Run the gate QR contract test and lint the QR PHP files**

Run:

```bash
node tests/wave1/gate_qr_contract.test.mjs
php -l includes/gate_qr_service.php
php -l modules/api/student_qr_token.php
php -l modules/api/scan_gate_qr.php
php -l modules/api/gate_access_logs.php
php -l modules/api/student_gate_history.php
php -l modules/user/access_qr.php
php -l modules/user/gate_history.php
php -l modules/staff/access/gate_scanner.php
php -l modules/staff/access/gate_logs.php
```

Expected:

```text
The gate QR contract test passes.
No syntax errors detected in the QR gate PHP files.
```

- [ ] **Step 5: Commit the QR gate slice**

```bash
git add includes/gate_qr_service.php modules/api/student_qr_token.php modules/api/scan_gate_qr.php modules/api/gate_access_logs.php modules/api/student_gate_history.php modules/user/access_qr.php modules/user/gate_history.php modules/staff/access/gate_scanner.php modules/staff/access/gate_logs.php tests/wave1/gate_qr_contract.test.mjs
git commit -m "feat: add qr gate access workflow"
```

## Task 6: Wire Navigation, Dashboards, Regression Checks, And Manual Verification

**Files:**
- Modify: `modules/admin/dashboard.php`
- Modify: `modules/staff/dashboard.php`
- Modify: `includes/admin_header.php`
- Modify: `includes/header.php`
- Modify: `modules/user/notifications.php`
- Modify: `tests/wave1/notification_realtime_contract.test.mjs`
- Modify: `tests/wave1/email_report_contract.test.mjs`
- Modify: `tests/wave1/gate_qr_contract.test.mjs`

- [ ] **Step 1: Add a final smoke test pass that checks all entry points are linked from the shell**

```js
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const adminDashboard = readFileSync('modules/admin/dashboard.php', 'utf8');
const staffDashboard = readFileSync('modules/staff/dashboard.php', 'utf8');
const userHeader = readFileSync('includes/header.php', 'utf8');

assert.ok(adminDashboard.includes('modules/staff/report/report_hub.php'));
assert.ok(staffDashboard.includes('modules/staff/communications/mass_email.php'));
assert.ok(staffDashboard.includes('modules/staff/access/gate_scanner.php'));
assert.ok(userHeader.includes('modules/user/access_qr.php'));
assert.ok(userHeader.includes('modules/user/gate_history.php'));
console.log('wave1 navigation contract passed');
```

- [ ] **Step 2: Run the navigation contract test and verify it fails before wiring**

Run:

```bash
node tests/wave1/notification_realtime_contract.test.mjs
node tests/wave1/email_report_contract.test.mjs
node tests/wave1/gate_qr_contract.test.mjs
```

Expected:

```text
At least one contract fails because the dashboards and shells do not yet link to the new Wave 1 pages.
```

- [ ] **Step 3: Add the final page links and update the full notifications page to use unified data**

Update `modules/admin/dashboard.php` and `modules/staff/dashboard.php` quick links:

```php
<a href="<?= $base ?>modules/staff/communications/mass_email.php" class="bento-btn">
    <i class="fas fa-envelope-open-text"></i> Mass Email
</a>
<a href="<?= $base ?>modules/staff/report/report_hub.php" class="bento-btn">
    <i class="fas fa-file-export"></i> Export báo cáo
</a>
<a href="<?= $base ?>modules/staff/access/gate_scanner.php" class="bento-btn">
    <i class="fas fa-qrcode"></i> Quét cổng
</a>
<a href="<?= $base ?>modules/staff/access/gate_logs.php" class="bento-btn">
    <i class="fas fa-door-open"></i> Nhật ký ra/vào
</a>
```

Update `includes/header.php` navigation:

```php
<a href="<?= $base ?>modules/user/access_qr.php" class="<?= nav_active($base . 'modules/user/access_qr.php') ?>">
    <i class="fas fa-qrcode"></i>
    <span>Mã QR cổng</span>
</a>
<a href="<?= $base ?>modules/user/gate_history.php" class="<?= nav_active($base . 'modules/user/gate_history.php') ?>">
    <i class="fas fa-clock-rotate-left"></i>
    <span>Lịch sử ra/vào</span>
</a>
```

Refactor `modules/user/notifications.php` to read from `user_notifications`:

```php
$stmt = $conn->prepare("
    SELECT NotificationID, Title, Message, Type, Severity, Link, IsRead, CreatedAt
    FROM user_notifications
    WHERE UserID = ?
    ORDER BY CreatedAt DESC
    LIMIT 50
");
$stmt->bind_param('i', $userId);
```

- [ ] **Step 4: Run all Wave 1 smoke tests, lint the changed pages, and perform the manual verification checklist**

Run:

```bash
npm install
npm run test:wave1
php -l includes/admin_header.php
php -l includes/header.php
php -l modules/admin/dashboard.php
php -l modules/staff/dashboard.php
php -l modules/user/notifications.php
```

Manual verification checklist:

```text
1. Log in as Student and confirm the bell dropdown refreshes after inserting a row into user_notifications.
2. Log in as Manager and confirm the bell dropdown refreshes after using notification_center.php.
3. Open mass_email.php, send to students, and verify campaign + recipient log counts update.
4. Open report_hub.php, preview finance_detail, export XLSX, and open the file in Excel.
5. Export the same report as PDF and confirm row order matches the preview.
6. Open access_qr.php as Student, refresh the token, scan from gate_scanner.php as Manager, and confirm an IN or OUT row appears in gate_logs.php.
7. Re-scan the same QR after expiry and confirm the API returns INVALID_OR_EXPIRED_TOKEN.
```

Expected:

```text
All smoke tests pass.
All touched PHP files lint cleanly.
The manual verification checklist completes without fatal errors.
```

- [ ] **Step 5: Commit the Wave 1 integration and verification pass**

```bash
git add modules/admin/dashboard.php modules/staff/dashboard.php includes/admin_header.php includes/header.php modules/user/notifications.php tests/wave1/notification_realtime_contract.test.mjs tests/wave1/email_report_contract.test.mjs tests/wave1/gate_qr_contract.test.mjs
git commit -m "feat: wire wave1 features into dashboards and navigation"
```
