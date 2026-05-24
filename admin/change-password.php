<?php
$pageTitle = "Change Password";
include("layout.php");

require_once __DIR__ . '/includes/db.php';
$success = "";
$error = "";
$adminId = $_SESSION['admin'] ?? 0;

$adminEmail = '';

if ($adminId > 0) {

    $emailStmt = $conn->prepare("
        SELECT email 
        FROM admins 
        WHERE id = ?
        LIMIT 1
    ");

    $emailStmt->bind_param("i", $adminId);
    $emailStmt->execute();

    $emailResult = $emailStmt->get_result();

    if ($emailResult->num_rows === 1) {

        $adminData = $emailResult->fetch_assoc();

        $adminEmail = $adminData['email'];
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
$admin_email = trim($_POST['admin_email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 🔥 CHECK EMPTY
  if (
    empty($admin_email) ||
    empty($current_password) ||
    empty($new_password) ||
    empty($confirm_password)
) {

    $error = "All fields are required.";

}

    // 🔥 PASSWORD MATCH CHECK
    elseif ($new_password !== $confirm_password) {

        $error = "New passwords do not match.";

    }

    // 🔥 PASSWORD LENGTH
    elseif (strlen($new_password) < 6) {

        $error = "Password must be at least 6 characters.";

    }
elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {

    $error = "Invalid email format.";

}
    else {

        $adminId = $_SESSION['admin'] ?? 0;

        // 🔥 FETCH CURRENT ADMIN
        $stmt = $conn->prepare("
            SELECT password_hash 
            FROM admins 
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $adminId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $admin = $result->fetch_assoc();

            // 🔥 VERIFY CURRENT PASSWORD
            if (password_verify($current_password, $admin['password_hash'])) {

                // 🔥 HASH NEW PASSWORD
                $newHash = password_hash($new_password, PASSWORD_DEFAULT);

                // 🔥 UPDATE PASSWORD
                $update = $conn->prepare("
                 UPDATE admins 
SET email = ?, password_hash = ?, updated_at = NOW()
WHERE id = ?
                ");

              $update->bind_param(
    "ssi",
    $admin_email,
    $newHash,
    $adminId
);

                if ($update->execute()) {
                   
                    $_SESSION['admin_email'] = $admin_email;
                     $adminEmail = $admin_email;
                    $success = "Admin email and password updated successfully.";

                } else {

                    $error = "Failed to update password.";

                }

            } else {

                $error = "Current password is incorrect.";

            }

        } else {

            $error = "Admin not found.";

        }
    }
}
?>

<style>
.password-wrapper {
    max-width: 560px;
}

.password-card {
    background: #fff;
    border-radius: 20px;
    padding: 24px;
    border: 1px solid rgba(128, 0, 31, 0.12);
    box-shadow: 0 14px 30px rgba(68, 16, 34, 0.08);
}

.password-title {
    margin: 0 0 18px;
    color: #80001F;
    font-family: 'DM Serif Display', serif;
    font-size: 1.6rem;
}

.password-form {
    display: grid;
    gap: 16px;
}

.password-field label {
    display: block;
    margin-bottom: 6px;
    color: #6b4d59;
    font-size: 0.84rem;
    font-weight: 600;
}

.password-input {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid rgba(128, 0, 31, 0.16);
    font-size: 0.92rem;
    box-sizing: border-box;
}

.password-input:focus {
    outline: none;
    border-color: #80001F;
    box-shadow: 0 0 0 3px rgba(128, 0, 31, 0.12);
}

.password-btn {
    border: none;
    background: linear-gradient(135deg, #80001F, #a1002a);
    color: #fff;
    padding: 13px;
    border-radius: 12px;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s ease;
}

.password-btn:hover {
    transform: translateY(-1px);
}

.alert {
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 0.84rem;
    margin-bottom: 16px;
    font-weight: 500;
}

.alert-success {
    background: #effcf4;
    border: 1px solid #c8ead6;
    color: #1a6c3f;
}

.alert-error {
    background: #fff1f1;
    border: 1px solid #f1c4c4;
    color: #a12727;
}
</style>

<div class="password-wrapper">

    <div class="password-card">

        <h2 class="password-title">Change Password</h2>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="password-form">
                  

        <div class="password-field">
    <label>Admin Email</label>

    <input 
        type="email"
        name="admin_email"
        class="password-input"
        value="<?= htmlspecialchars($adminEmail) ?>"
        required
    >
</div>
            <div class="password-field">
                <label>Current Password</label>
                <input 
                    type="password" 
                    name="current_password" 
                    class="password-input"
                    required
                >
            </div>

            <div class="password-field">
                <label>New Password</label>
                <input 
                    type="password" 
                    name="new_password" 
                    class="password-input"
                    required
                >
            </div>

            <div class="password-field">
                <label>Confirm New Password</label>
                <input 
                    type="password" 
                    name="confirm_password" 
                    class="password-input"
                    required
                >
            </div>

            <button type="submit" class="password-btn">
                Update Password
            </button>

        </form>

    </div>

</div>

</div>
</div>

</body>
</html>