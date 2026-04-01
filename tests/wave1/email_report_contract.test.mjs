import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

const files = [
  'database/migrations/2026-03-31-wave1-foundation.sql',
  'includes/mass_email_service.php',
  'includes/report_service.php',
  'includes/report_pdf.php',
  'includes/vendor/fpdf/fpdf.php',
  'modules/api/admin_send_mass_email.php',
  'modules/api/admin_email_campaigns.php',
  'modules/api/admin_email_campaign_detail.php',
  'modules/api/report_data.php',
  'modules/api/report_export.php',
  'modules/staff/communications/mass_email.php',
  'modules/staff/report/report_hub.php',
];

for (const filePath of files) {
  assert.ok(existsSync(filePath), `Expected ${filePath} to exist`);
}

const sql = readFileSync('database/migrations/2026-03-31-wave1-foundation.sql', 'utf8');
const massEmailService = readFileSync('includes/mass_email_service.php', 'utf8');
const reportService = readFileSync('includes/report_service.php', 'utf8');
const reportPdf = readFileSync('includes/report_pdf.php', 'utf8');
const fpdf = readFileSync('includes/vendor/fpdf/fpdf.php', 'utf8');
const massEmailApi = readFileSync('modules/api/admin_send_mass_email.php', 'utf8');
const massEmailHistoryApi = readFileSync('modules/api/admin_email_campaigns.php', 'utf8');
const massEmailDetailApi = readFileSync('modules/api/admin_email_campaign_detail.php', 'utf8');
const massEmailPage = readFileSync('modules/staff/communications/mass_email.php', 'utf8');
const reportDataApi = readFileSync('modules/api/report_data.php', 'utf8');
const reportExportApi = readFileSync('modules/api/report_export.php', 'utf8');
const reportHubPage = readFileSync('modules/staff/report/report_hub.php', 'utf8');

assert.ok(sql.includes('CREATE TABLE IF NOT EXISTS `email_campaign_recipients`'));
assert.ok(massEmailService.includes('function resolveCampaignRecipients'));
assert.ok(massEmailService.includes('function createEmailCampaign'));
assert.ok(massEmailService.includes('function sendImmediateMassEmail'));
assert.ok(reportService.includes('function buildReportDataset'));
assert.ok(reportService.includes('function buildFinanceSummaryDataset'));
assert.ok(reportService.includes('function buildStudentListDataset'));
assert.ok(reportService.includes("'meta'"));
assert.ok(reportService.includes('Unknown report query execution failure.'));
assert.ok(reportService.includes('Unable to read report result set.'));
assert.ok(reportService.includes("Status = 'Hiệu lực'"));
assert.ok(reportService.includes('ORDER BY CASE WHEN c2.Status = \'Hiệu lực\' THEN 0 ELSE 1 END'));
assert.ok(reportPdf.includes('function outputReportPdf'));
assert.ok(reportPdf.includes('require_once __DIR__ . \'/vendor/fpdf/fpdf.php\''));
assert.ok(reportPdf.includes('function reportPdfResolveFontPath'));
assert.ok(reportPdf.includes('function reportPdfBuildLayoutContext'));
assert.ok(reportPdf.includes('function reportPdfRenderPageImages'));
assert.ok(reportPdf.includes('imagejpeg'));
assert.ok(reportPdf.includes('imagettftext'));
assert.ok(!reportPdf.includes('pageBudget = 18'));
assert.ok(reportPdf.includes("meta['generatedAt']") || reportPdf.includes("['generatedAt']"));
assert.ok(massEmailService.includes('Content-Type: text/html; charset=UTF-8'));
assert.ok(massEmailService.includes('$htmlBody'));
assert.ok(massEmailService.includes('Email campaign insert did not return an ID.'));
assert.ok(massEmailService.includes('Unknown campaign recipient insert failure.'));
assert.ok(massEmailApi.includes('sendImmediateMassEmail'));
assert.ok(massEmailApi.includes('requireRole([\'Admin\', \'Manager\'])'));
assert.ok(massEmailHistoryApi.includes('FROM email_campaigns'));
assert.ok(massEmailDetailApi.includes('FROM email_campaign_recipients'));
assert.ok(massEmailPage.includes('admin_send_mass_email.php'));
assert.ok(massEmailPage.includes('admin_email_campaigns.php'));
assert.ok(reportDataApi.includes('buildReportDataset'));
assert.ok(reportDataApi.includes('report_key'));
assert.ok(reportExportApi.includes('outputReportPdf'));
assert.ok(reportExportApi.includes('SimpleXLSXGen'));
assert.ok(reportHubPage.includes('report_data.php'));
assert.ok(reportHubPage.includes('report_export.php'));
assert.ok(fpdf.includes('class FPDF'));
assert.ok(fpdf.includes('public function Image'));

console.log('email/report contract smoke test passed');
