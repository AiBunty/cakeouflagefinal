<?php
$pageTitle = 'Form Test Harness Report';
require_once __DIR__ . '/layout.php';
require_admin_permission('maintenance');

$qaContractVersion = 'BYOC-QA-v1.1-storage-lock';
$allowedOutcomes = [
  'accepted_201_created',
  'rejected_422_required_fields',
  'rejected_422_invalid_email',
  'rejected_422_invalid_country_code',
  'rejected_422_phone_india_10_digits',
  'rejected_422_phone_intl_6_15_digits',
  'rejected_422_servings_numeric',
  'rejected_422_privacy_consent',
  'rejected_422_invalid_event_information',
  'rejected_422_invalid_diet_preference',
];

$constraintName = 'chk_qa_form_actual_outcome_policy_v1';
$fallbackOutcome = 'rejected_422_required_fields';
$policyOutcomesSql = implode(', ', array_map(static function ($outcome) use ($conn): string {
  return "'" . $conn->real_escape_string((string)$outcome) . "'";
}, $allowedOutcomes));

$conn->query(
    'CREATE TABLE IF NOT EXISTS qa_form_test_runs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        test_case_id VARCHAR(60) NOT NULL,
        layer_label VARCHAR(30) NOT NULL DEFAULT "second",
        form_action VARCHAR(120) NOT NULL,
        expected_outcome VARCHAR(120) NOT NULL,
        actual_outcome VARCHAR(120) NOT NULL,
        verdict ENUM("pass","fail") NOT NULL,
        evidence_ref VARCHAR(255) NULL,
        notes TEXT NULL,
        tested_by_admin_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_qa_form_runs_case (test_case_id),
        INDEX idx_qa_form_runs_verdict (verdict),
        INDEX idx_qa_form_runs_created (created_at),
        CONSTRAINT ' . $constraintName . ' CHECK (actual_outcome IN (' . $policyOutcomesSql . '))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

    // Ensure DB-level policy enforcement also applies to pre-existing tables.
    $checkStmt = $conn->prepare(
      'SELECT COUNT(*) AS c
       FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = "qa_form_test_runs"
         AND CONSTRAINT_NAME = ?
         AND CONSTRAINT_TYPE = "CHECK"'
    );
    $checkExists = 0;
    if ($checkStmt) {
      $checkStmt->bind_param('s', $constraintName);
      $checkStmt->execute();
      $checkRes = $checkStmt->get_result();
      $checkRow = $checkRes ? $checkRes->fetch_assoc() : null;
      $checkExists = (int)($checkRow['c'] ?? 0);
      $checkStmt->close();
    }

    if ($checkExists === 0) {
      // Normalize historical outcomes from earlier harness versions before enforcing CHECK.
      $conn->query('UPDATE qa_form_test_runs SET actual_outcome = "accepted_201_created" WHERE actual_outcome = "accepted_queued"');
      $conn->query('UPDATE qa_form_test_runs SET actual_outcome = "rejected_422_required_fields" WHERE actual_outcome IN ("validation_error", "rejected", "server_error")');

      $conn->query(
        'UPDATE qa_form_test_runs
         SET notes = CONCAT("[AUTO-NORMALIZED to policy outcome] Previous value: ", COALESCE(actual_outcome, ""), " | ", COALESCE(notes, "")),
           actual_outcome = "' . $fallbackOutcome . '"
         WHERE actual_outcome NOT IN (' . $policyOutcomesSql . ')'
      );

      $conn->query(
        'ALTER TABLE qa_form_test_runs
         ADD CONSTRAINT ' . $constraintName . ' CHECK (actual_outcome IN (' . $policyOutcomesSql . '))'
      );
    }

    $constraintActive = false;
    $postCheckStmt = $conn->prepare(
      'SELECT COUNT(*) AS c
       FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = "qa_form_test_runs"
         AND CONSTRAINT_NAME = ?
         AND CONSTRAINT_TYPE = "CHECK"'
    );
    if ($postCheckStmt) {
      $postCheckStmt->bind_param('s', $constraintName);
      $postCheckStmt->execute();
      $postCheckRes = $postCheckStmt->get_result();
      $postCheckRow = $postCheckRes ? $postCheckRes->fetch_assoc() : null;
      $constraintActive = (int)($postCheckRow['c'] ?? 0) > 0;
      $postCheckStmt->close();
    }

$matrix = [
  ['id' => 'BYOC-QA-001', 'layer' => 'second', 'action' => 'submit_valid_payload', 'expected' => 'accepted_201_created'],
  ['id' => 'BYOC-QA-002', 'layer' => 'second', 'action' => 'submit_missing_required_fields', 'expected' => 'rejected_422_required_fields'],
  ['id' => 'BYOC-QA-003', 'layer' => 'second', 'action' => 'submit_invalid_email_format', 'expected' => 'rejected_422_invalid_email'],
  ['id' => 'BYOC-QA-004', 'layer' => 'second', 'action' => 'submit_invalid_phone_country_code_format', 'expected' => 'rejected_422_invalid_country_code'],
  ['id' => 'BYOC-QA-005', 'layer' => 'second', 'action' => 'submit_invalid_phone_india_non_10_digit', 'expected' => 'rejected_422_phone_india_10_digits'],
  ['id' => 'BYOC-QA-006', 'layer' => 'second', 'action' => 'submit_invalid_phone_non_india_out_of_range', 'expected' => 'rejected_422_phone_intl_6_15_digits'],
  ['id' => 'BYOC-QA-007', 'layer' => 'second', 'action' => 'submit_servings_non_numeric', 'expected' => 'rejected_422_servings_numeric'],
  ['id' => 'BYOC-QA-008', 'layer' => 'second', 'action' => 'submit_without_privacy_consent', 'expected' => 'rejected_422_privacy_consent'],
  ['id' => 'BYOC-QA-009', 'layer' => 'second', 'action' => 'submit_invalid_event_information_enum', 'expected' => 'rejected_422_invalid_event_information'],
  ['id' => 'BYOC-QA-010', 'layer' => 'second', 'action' => 'submit_invalid_diet_preference_enum', 'expected' => 'rejected_422_invalid_diet_preference'],
  ['id' => 'BYOC-QA-011', 'layer' => 'second', 'action' => 'submit_valid_payload_with_reference_image', 'expected' => 'accepted_201_created'],
  ['id' => 'BYOC-QA-012', 'layer' => 'second', 'action' => 'submit_valid_payload_even_if_image_move_fails', 'expected' => 'accepted_201_created'],
];

$matrixById = [];
foreach ($matrix as $row) {
    $matrixById[(string)$row['id']] = $row;
}
$policyDigest = hash('sha256', json_encode([
  'version' => $qaContractVersion,
  'allowed_outcomes' => $allowedOutcomes,
  'matrix' => $matrix,
], JSON_UNESCAPED_SLASHES));

$flash = '';
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'log_result') {
        $testCaseId = trim((string)($_POST['test_case_id'] ?? ''));
        $actual = trim((string)($_POST['actual_outcome'] ?? ''));
        $evidence = trim((string)($_POST['evidence_ref'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
      $postedPolicyVersion = trim((string)($_POST['policy_version'] ?? ''));
      $postedPolicyDigest = trim((string)($_POST['policy_digest'] ?? ''));

      if ($postedPolicyVersion !== $qaContractVersion || $postedPolicyDigest !== $policyDigest) {
        $flash = 'Policy lock mismatch. Refresh and retry; policy cases are non-editable.';
        $flashType = 'err';
      } elseif (!isset($matrixById[$testCaseId])) {
            $flash = 'Unknown test case id.';
            $flashType = 'err';
      } elseif (!in_array($actual, $allowedOutcomes, true)) {
        $flash = 'Actual outcome is outside locked QA policy enums.';
        $flashType = 'err';
        } else {
            $case = $matrixById[$testCaseId];
            $expected = (string)$case['expected'];
            $verdict = $actual === $expected ? 'pass' : 'fail';

            $stmt = $conn->prepare('INSERT INTO qa_form_test_runs (test_case_id, layer_label, form_action, expected_outcome, actual_outcome, verdict, evidence_ref, notes, tested_by_admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $adminId = isset($_SESSION['admin']) ? (int)$_SESSION['admin'] : null;
                $layer = (string)$case['layer'];
                $formAction = (string)$case['action'];
                $adminIdParam = $adminId ?: null;
                $stmt->bind_param('ssssssssi', $testCaseId, $layer, $formAction, $expected, $actual, $verdict, $evidence, $notes, $adminIdParam);
                $stmt->execute();
                $stmt->close();
                $flash = 'Policy-locked result recorded with strict verdict: ' . strtoupper($verdict);
                $flashType = $verdict === 'pass' ? 'ok' : 'err';
            } else {
                $flash = 'Unable to save test result.';
                $flashType = 'err';
            }
        }
    }
}

$verdictFilter = trim((string)($_GET['verdict'] ?? ''));
$caseFilter = trim((string)($_GET['case'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($verdictFilter !== '' && in_array($verdictFilter, ['pass', 'fail'], true)) {
    $where[] = 'verdict = ?';
    $params[] = $verdictFilter;
    $types .= 's';
}
if ($caseFilter !== '' && isset($matrixById[$caseFilter])) {
    $where[] = 'test_case_id = ?';
    $params[] = $caseFilter;
    $types .= 's';
}

$sql = 'SELECT id, test_case_id, layer_label, form_action, expected_outcome, actual_outcome, verdict, evidence_ref, notes, created_at FROM qa_form_test_runs';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC LIMIT 400';

$stmt = $conn->prepare($sql);
if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
}
$items = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

$summary = ['pass' => 0, 'fail' => 0, 'total' => 0];
$sumResult = $conn->query('SELECT verdict, COUNT(*) AS c FROM qa_form_test_runs GROUP BY verdict');
while ($sumResult && ($sum = $sumResult->fetch_assoc())) {
    $key = (string)($sum['verdict'] ?? '');
    if (isset($summary[$key])) {
        $summary[$key] = (int)($sum['c'] ?? 0);
    }
}
$summary['total'] = $summary['pass'] + $summary['fail'];
?>
<style>
.harness-shell { display: grid; gap: 14px; }
.harness-card { background: #fff; border: 1px solid #eed7df; border-radius: 12px; padding: 14px; }
.harness-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
.harness-grid select, .harness-grid input, .harness-grid textarea { width: 100%; min-height: 38px; border: 1px solid #e1c4ce; border-radius: 8px; padding: 7px 10px; }
.harness-grid textarea { min-height: 90px; }
.harness-btn { border: 0; border-radius: 8px; min-height: 36px; padding: 0 12px; background: #80001f; color: #fff; cursor: pointer; }
.harness-summary { display: flex; gap: 10px; flex-wrap: wrap; }
.harness-pill { border-radius: 999px; padding: 6px 12px; font-size: 12px; }
.harness-pill.pass { background: #eaf8ee; color: #165b34; }
.harness-pill.fail { background: #fff0f0; color: #8e1f1f; }
.harness-pill.total { background: #f4ebee; color: #5f3040; }
.harness-table { width: 100%; border-collapse: collapse; }
.harness-table th, .harness-table td { border-bottom: 1px solid #f2e3e8; padding: 8px; text-align: left; font-size: 12px; vertical-align: top; }
.harness-status.ok { color: #165b34; }
.harness-status.err { color: #8e1f1f; }
.harness-constraint-banner { border-radius: 10px; padding: 10px 12px; font-size: 12px; font-weight: 600; margin-bottom: 10px; }
.harness-constraint-banner.ok { background: #edf9f1; color: #1f6b3d; border: 1px solid #cdeed7; }
.harness-constraint-banner.err { background: #fff1f1; color: #932020; border: 1px solid #f2c7c7; }
@media (max-width: 960px) { .harness-grid { grid-template-columns: 1fr; } }
</style>

<div class="content-header">
  <h1>Second-Layer Form Test Harness</h1>
  <p>Strict matrix report (Format A): policy-locked cases and enums, expected vs actual decides PASS/FAIL automatically.</p>
</div>

<div class="harness-shell">
  <div class="harness-constraint-banner <?= $constraintActive ? 'ok' : 'err' ?>">
    CHECK Constraint Status: <strong><?= $constraintActive ? 'ACTIVE' : 'INACTIVE' ?></strong>
    (<?= htmlspecialchars($constraintName, ENT_QUOTES, 'UTF-8') ?>)
  </div>

  <?php if ($flash !== ''): ?>
    <div class="harness-card harness-status <?= $flashType === 'err' ? 'err' : 'ok' ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="harness-card">
    <div class="harness-status ok" style="margin-bottom:10px;">
      QA Contract: <strong><?= htmlspecialchars($qaContractVersion, ENT_QUOTES, 'UTF-8') ?></strong>
      | Policy Digest: <strong><?= htmlspecialchars(substr($policyDigest, 0, 12), ENT_QUOTES, 'UTF-8') ?></strong>
      | Policy Mode: <strong>LOCKED (non-editable cases)</strong>
    </div>
    <div class="harness-summary">
      <span class="harness-pill total">Total: <?= (int)$summary['total'] ?></span>
      <span class="harness-pill pass">PASS: <?= (int)$summary['pass'] ?></span>
      <span class="harness-pill fail">FAIL: <?= (int)$summary['fail'] ?></span>
    </div>
  </div>

  <div class="harness-card">
    <h3>Log Test Result</h3>
    <form method="post" class="harness-grid">
      <input type="hidden" name="action" value="log_result" />
      <input type="hidden" name="policy_version" value="<?= htmlspecialchars($qaContractVersion, ENT_QUOTES, 'UTF-8') ?>" />
      <input type="hidden" name="policy_digest" value="<?= htmlspecialchars($policyDigest, ENT_QUOTES, 'UTF-8') ?>" />
      <div>
        <label>Test Case ID</label>
        <select name="test_case_id" required>
          <option value="">Select test case</option>
          <?php foreach ($matrix as $case): ?>
            <option value="<?= htmlspecialchars((string)$case['id'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string)$case['id'] . ' - ' . (string)$case['action'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Actual Outcome</label>
        <select name="actual_outcome" required>
          <option value="">Select actual outcome</option>
          <?php foreach ($allowedOutcomes as $outcome): ?>
            <option value="<?= htmlspecialchars((string)$outcome, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$outcome, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Evidence URL / Log Reference</label>
        <input type="text" name="evidence_ref" placeholder="/admin/crm_push_logs.php#123 or test log path" />
      </div>
      <div style="grid-column:1/-1;">
        <label>Notes</label>
        <textarea name="notes" placeholder="Observed behavior, payload details, validation message, etc."></textarea>
      </div>
      <div>
        <button type="submit" class="harness-btn">Record Strict Verdict</button>
      </div>
    </form>
  </div>

  <div class="harness-card">
    <h3>Test Matrix (Expected Outcomes)</h3>
    <table class="harness-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Layer</th>
          <th>Action</th>
          <th>Expected</th>
          <th>Policy</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matrix as $case): ?>
          <tr>
            <td><?= htmlspecialchars((string)$case['id'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$case['layer'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$case['action'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)$case['expected'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>Locked</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="harness-card">
    <h3>Pass/Fail Sheet</h3>
    <form method="get" class="harness-grid" style="margin-bottom:10px;">
      <div>
        <label>Filter by verdict</label>
        <select name="verdict">
          <option value="">All</option>
          <option value="pass" <?= $verdictFilter === 'pass' ? 'selected' : '' ?>>PASS</option>
          <option value="fail" <?= $verdictFilter === 'fail' ? 'selected' : '' ?>>FAIL</option>
        </select>
      </div>
      <div>
        <label>Filter by case</label>
        <select name="case">
          <option value="">All</option>
          <?php foreach ($matrix as $case): ?>
            <option value="<?= htmlspecialchars((string)$case['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $caseFilter === (string)$case['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$case['id'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;align-items:end;"><button class="harness-btn" type="submit">Apply Filters</button></div>
    </form>

    <table class="harness-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Layer</th>
          <th>Action</th>
          <th>Expected</th>
          <th>Actual</th>
          <th>Verdict</th>
          <th>Evidence</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="8">No harness executions logged yet.</td></tr>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= htmlspecialchars((string)($item['test_case_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($item['layer_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($item['form_action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($item['expected_outcome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($item['actual_outcome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><strong class="harness-status <?= (string)($item['verdict'] ?? '') === 'pass' ? 'ok' : 'err' ?>"><?= strtoupper(htmlspecialchars((string)($item['verdict'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></strong></td>
            <td><?= htmlspecialchars((string)($item['evidence_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($item['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
