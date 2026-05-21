<?php
session_name('cakeouflage_sid');
session_start();

require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once dirname(__DIR__) . '/app/Services/MailService.php';

// Already logged in
if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit;
}

function ensure_admin_identity(mysqli $conn): void
{
  $superName = 'Dcore';
  $superEmail = 'aibuntysystems@gmail.com';
  $subName = 'Ansh';
  $subEmail = 'cakeouflage@gmail.com';

  $randomHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

  // Ensure primary super admin account.
  $superByEmailStmt = $conn->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
  if ($superByEmailStmt) {
    $superByEmailStmt->bind_param('s', $superEmail);
    $superByEmailStmt->execute();
    $superByEmailResult = $superByEmailStmt->get_result();
    $superByEmail = $superByEmailResult ? $superByEmailResult->fetch_assoc() : null;
    $superByEmailStmt->close();

    if ($superByEmail) {
      $updateStmt = $conn->prepare('UPDATE admins SET full_name = ?, role = "super_admin", is_active = 1, updated_at = NOW() WHERE id = ?');
      if ($updateStmt) {
        $sid = (int) $superByEmail['id'];
        $updateStmt->bind_param('si', $superName, $sid);
        $updateStmt->execute();
        $updateStmt->close();
      }
    } else {
      $existingSuperStmt = $conn->prepare('SELECT id FROM admins WHERE role = "super_admin" ORDER BY id ASC LIMIT 1');
      if ($existingSuperStmt) {
        $existingSuperStmt->execute();
        $existingSuperResult = $existingSuperStmt->get_result();
        $existingSuper = $existingSuperResult ? $existingSuperResult->fetch_assoc() : null;
        $existingSuperStmt->close();

        if ($existingSuper) {
          $sid = (int) $existingSuper['id'];
          $updateStmt = $conn->prepare('UPDATE admins SET full_name = ?, email = ?, is_active = 1, updated_at = NOW() WHERE id = ?');
          if ($updateStmt) {
            $updateStmt->bind_param('ssi', $superName, $superEmail, $sid);
            $updateStmt->execute();
            $updateStmt->close();
          }
        } else {
          $insertSuper = $conn->prepare('INSERT INTO admins (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, "super_admin", 1)');
          if ($insertSuper) {
            $insertSuper->bind_param('sss', $superName, $superEmail, $randomHash);
            $insertSuper->execute();
            $insertSuper->close();
          }
        }
      }
    }
  }

  // Ensure requested sub user account.
  $subByEmailStmt = $conn->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
  if ($subByEmailStmt) {
    $subByEmailStmt->bind_param('s', $subEmail);
    $subByEmailStmt->execute();
    $subByEmailResult = $subByEmailStmt->get_result();
    $subByEmail = $subByEmailResult ? $subByEmailResult->fetch_assoc() : null;
    $subByEmailStmt->close();

    if ($subByEmail) {
      $subId = (int) $subByEmail['id'];
      $updateSub = $conn->prepare('UPDATE admins SET full_name = ?, role = "admin", is_active = 1, updated_at = NOW() WHERE id = ?');
      if ($updateSub) {
        $updateSub->bind_param('si', $subName, $subId);
        $updateSub->execute();
        $updateSub->close();
      }
    } else {
      $insertSub = $conn->prepare('INSERT INTO admins (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, "admin", 1)');
      if ($insertSub) {
        $insertSub->bind_param('sss', $subName, $subEmail, $randomHash);
        $insertSub->execute();
        $subId = (int) $conn->insert_id;
        $insertSub->close();

        if ($subId > 0) {
          $permissions = admin_label_presets()['crm'] ?? array('dashboard', 'follow_ups', 'crm_settings', 'crm_logs', 'crm_report', 'change_password');
          $permInsert = $conn->prepare('INSERT IGNORE INTO admin_permissions (admin_id, permission_key) VALUES (?, ?)');
          if ($permInsert) {
            foreach ($permissions as $perm) {
              $permInsert->bind_param('is', $subId, $perm);
              $permInsert->execute();
            }
            $permInsert->close();
          }
        }
      }
    }
  }
}

function find_active_admin_for_login(mysqli $conn, string $login): ?array
{
  $stmt = $conn->prepare('SELECT id, full_name, email, role, department_label FROM admins WHERE (email = ? OR full_name = ?) AND is_active = 1 LIMIT 1');
  if (!$stmt) {
    return null;
  }
  $stmt->bind_param('ss', $login, $login);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result ? $result->fetch_assoc() : null;
  $stmt->close();
  return $row ?: null;
}

/**
 * Store an OTP for the given email.
 * Returns 'ok', 'cooldown' (too soon after last request), or 'error'.
 * Expiry is computed entirely in MySQL to avoid PHP/MySQL timezone mismatch.
 */
function store_admin_otp(mysqli $conn, string $email, string $otp): string
{
  // Server-side cooldown: deny if an OTP was issued less than 60 seconds ago
  $coolStmt = $conn->prepare('SELECT created_at FROM otp_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1');
  if ($coolStmt) {
    $coolStmt->bind_param('s', $email);
    $coolStmt->execute();
    $coolResult = $coolStmt->get_result();
    $coolRow    = $coolResult ? $coolResult->fetch_assoc() : null;
    $coolStmt->close();
    if ($coolRow && (time() - strtotime((string)$coolRow['created_at'])) < 60) {
      return 'cooldown';
    }
  }

  $deleteStmt = $conn->prepare('DELETE FROM otp_verifications WHERE email = ?');
  if (!$deleteStmt) {
    return 'error';
  }
  $deleteStmt->bind_param('s', $email);
  $deleteStmt->execute();
  $deleteStmt->close();

  // Use MySQL's own clock so expiry is always consistent with verify's NOW() check
  $insertStmt = $conn->prepare('INSERT INTO otp_verifications (email, otp, expires_at) VALUES (?, ?, NOW() + INTERVAL 5 MINUTE)');
  if (!$insertStmt) {
    return 'error';
  }
  $insertStmt->bind_param('ss', $email, $otp);
  $ok = $insertStmt->execute();
  $insertStmt->close();
  return $ok ? 'ok' : 'error';
}

/**
 * Verify an OTP. Returns true on success.
 * $reason is set to: 'ok' | 'expired' | 'locked' (too many attempts) | 'invalid' | 'error'
 */
function verify_admin_otp(mysqli $conn, string $email, string $otp, string &$reason = ''): bool
{
  // Fetch the active (unexpired) OTP row for this email
  $stmt = $conn->prepare('SELECT id, otp, attempt_count FROM otp_verifications WHERE email = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
  if (!$stmt) {
    $reason = 'error';
    return false;
  }
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $result = $stmt->get_result();
  $row    = $result ? $result->fetch_assoc() : null;
  $stmt->close();

  if (!$row) {
    $reason = 'expired';
    return false;
  }

  // Brute-force protection: max 5 failed attempts per OTP
  if ((int)$row['attempt_count'] >= 5) {
    $reason = 'locked';
    return false;
  }

  // Compare OTP value
  if ($row['otp'] !== $otp) {
    $upd = $conn->prepare('UPDATE otp_verifications SET attempt_count = attempt_count + 1 WHERE id = ?');
    if ($upd) {
      $rowId = (int)$row['id'];
      $upd->bind_param('i', $rowId);
      $upd->execute();
      $upd->close();
    }
    $reason = 'invalid';
    return false;
  }

  // Correct — delete all OTPs for this email and allow login
  $del = $conn->prepare('DELETE FROM otp_verifications WHERE email = ?');
  if ($del) {
    $del->bind_param('s', $email);
    $del->execute();
    $del->close();
  }

  $reason = 'ok';
  return true;
}

$error = '';
$info = '';
$prefillLogin = trim((string) ($_POST['login'] ?? $_SESSION['admin_login_identifier'] ?? ''));

ensure_admin_identity($conn);

// Bootstrap the first admin account if table is empty.
$adminCountResult = $conn->query("SELECT COUNT(*) AS total FROM admins");
$adminCount = (int) (($adminCountResult && $adminCountResult->num_rows === 1)
  ? $adminCountResult->fetch_assoc()['total']
  : 0);

if ($adminCount === 0) {
  $seedStmt = $conn->prepare(
    "INSERT INTO admins (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, 'super_admin', 1)"
  );
  $seedName = 'admin';
  $seedEmail = 'admin@cakeouflage.com';
  $seedHash = password_hash('admin123', PASSWORD_DEFAULT);
  $seedStmt->bind_param('sss', $seedName, $seedEmail, $seedHash);
  $seedStmt->execute();
}

  // OTP login flow
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'send_otp'));
    $login = trim((string) ($_POST['login'] ?? ''));

    if ($login === '') {
      $error = 'Enter username or email.';
    } else {
      $admin = find_active_admin_for_login($conn, $login);

      if (!$admin) {
        $error = 'No active admin account found for this login.';
      } else {
        $adminEmail = (string) $admin['email'];
        $_SESSION['admin_login_identifier'] = $login;

        if ($action === 'verify_otp') {
          $otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? '')) ?? '';
          if (strlen($otp) !== 6) {
            $error = 'Enter a valid 6-digit OTP.';
          } else {
            $verifyReason = '';
            if (!verify_admin_otp($conn, $adminEmail, $otp, $verifyReason)) {
              if ($verifyReason === 'locked') {
                $error = 'Too many failed attempts. Please request a new OTP.';
              } else {
                $error = 'Invalid or expired OTP. Please request a new OTP.';
              }
            } else {
              $_SESSION['admin'] = (int) $admin['id'];
              $_SESSION['admin_id'] = (int) $admin['id'];
              $_SESSION['admin_name'] = $admin['full_name'];
              $_SESSION['admin_role'] = $admin['role'];
              $_SESSION['admin_department_label'] = $admin['department_label'] ?? '';
              unset($_SESSION['admin_login_identifier']);
              admin_load_permissions($conn, (int) $admin['id']);

              header('Location: dashboard.php');
              exit;
            }
          }
        } else {
          $otp = (string) random_int(100000, 999999);
          $storeResult = store_admin_otp($conn, $adminEmail, $otp);
          if ($storeResult === 'ok') {
            try {
              \App\Services\MailService::sendOtp($adminEmail, $otp, $admin['full_name'] ?? 'Admin');
              $info = 'OTP sent to ' . htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') . '. It is valid for 5 minutes.';
            } catch (\Throwable $e) {
              error_log('Admin OTP send failed for ' . $adminEmail . ': ' . $e->getMessage());
              $error = 'Unable to send OTP email right now. Please try again.';
            }
          } elseif ($storeResult === 'cooldown') {
            $error = 'Please wait 60 seconds before requesting a new OTP.';
          } else {
            $error = 'Unable to generate OTP right now. Please try again.';
          }
        }
      }
    }
}

$otpSentThisRequest = $info !== '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login - Cakeouflage</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: linear-gradient(135deg, #fff0f3, #f8d8de);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-container {
      width: 400px;
      background: #fff;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }

    .logo {
      text-align: center;
      margin-bottom: 20px;
    }

    .logo img {
      width: 160px;
    }

    h2 {
      text-align: center;
      margin-bottom: 25px;
      color: #80001F;
      font-family: 'DM Serif Display', serif;
    }

    .input-group {
      margin-bottom: 18px;
    }

    .input-group label {
      font-size: 14px;
      margin-bottom: 6px;
      display: block;
    }

    .input-group input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: 1px solid #ddd;
    }

    .btn {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #80001F, #b3003c);
      color: white;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: 0.3s;
    }

    .btn:hover {
      transform: translateY(-2px);
    }

    .error {
      background: #ffe5e5;
      color: #c0392b;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
      text-align: center;
    }

    .footer {
      text-align: center;
      margin-top: 20px;
      font-size: 12px;
      color: #888;
    }
  </style>
</head>

<body>

<div class="login-container">

  <div class="logo">
    <img src="../client/assets/images/mainlogo.svg" alt="Logo">
  </div>

  <h2>Admin Login</h2>

  <?php if ($error): ?>
    <div class="error"><?= $error ?></div>
  <?php endif; ?>
  <?php if ($info): ?>
    <div style="background:#e8f7ec;color:#17663b;padding:10px;border-radius:8px;margin-bottom:15px;text-align:center;"><?= $info ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="input-group">
      <label>Username or Email</label>
      <input type="text" name="login" value="<?= htmlspecialchars($prefillLogin, ENT_QUOTES, 'UTF-8') ?>" required>
    </div>

    <div class="input-group">
      <label>OTP</label>
      <input type="text" name="otp" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="Enter 6-digit OTP">
    </div>

    <div style="display:flex;gap:10px;">
      <button class="btn" name="action" value="send_otp" type="submit" id="adminSendOtpBtn">Send OTP</button>
      <button class="btn" name="action" value="verify_otp" type="submit">Verify OTP & Login</button>
    </div>
    <div id="adminOtpCooldownHint" style="margin-top:8px;font-size:12px;color:#666;"></div>
  </form>

  <div class="footer">
    © Cakeouflage Admin Panel
  </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const OTP_COOLDOWN_MS = 60000;
  const OTP_STORAGE_KEY = "otp_cooldown_admin_login_until";
  const otpSentThisRequest = <?= $otpSentThisRequest ? 'true' : 'false' ?>;

  const form = document.querySelector("form[method='POST']");
  const sendBtn = document.getElementById("adminSendOtpBtn");
  const loginInput = form ? form.querySelector('input[name="login"]') : null;
  const hintEl = document.getElementById("adminOtpCooldownHint");
  const defaultSendText = sendBtn ? sendBtn.textContent : "Send OTP";

  if (!form || !sendBtn || !loginInput || !hintEl) {
    return;
  }

  let cooldownTimer = null;

  const readCooldownUntil = () => {
    const value = Number(window.localStorage.getItem(OTP_STORAGE_KEY) || "0");
    return Number.isFinite(value) ? value : 0;
  };

  const startCooldownUi = (untilEpochMs) => {
    const tick = () => {
      const remainingMs = untilEpochMs - Date.now();
      if (remainingMs <= 0) {
        if (cooldownTimer) {
          window.clearInterval(cooldownTimer);
          cooldownTimer = null;
        }
        sendBtn.disabled = false;
        sendBtn.textContent = defaultSendText;
        hintEl.textContent = "";
        window.localStorage.removeItem(OTP_STORAGE_KEY);
        return;
      }

      const remainingSec = Math.ceil(remainingMs / 1000);
      sendBtn.disabled = true;
      sendBtn.textContent = `Resend OTP in ${remainingSec}s`;
      hintEl.textContent = `You can request a new OTP in ${remainingSec}s.`;
    };

    if (cooldownTimer) {
      window.clearInterval(cooldownTimer);
    }
    tick();
    cooldownTimer = window.setInterval(tick, 250);
  };

  if (otpSentThisRequest) {
    const cooldownUntil = Date.now() + OTP_COOLDOWN_MS;
    window.localStorage.setItem(OTP_STORAGE_KEY, String(cooldownUntil));
  }

  const existingCooldownUntil = readCooldownUntil();
  if (existingCooldownUntil > Date.now()) {
    startCooldownUi(existingCooldownUntil);
  }

  form.addEventListener("submit", (event) => {
    const submitter = event.submitter;
    if (!(submitter instanceof HTMLElement)) {
      return;
    }

    if (submitter.getAttribute("name") === "action" && submitter.getAttribute("value") === "send_otp") {
      if (!String(loginInput.value || "").trim()) {
        return;
      }
      sendBtn.disabled = true;
      sendBtn.textContent = "Sending...";
      hintEl.textContent = "Sending OTP...";
    }
  });
});
</script>

</body>
</html>