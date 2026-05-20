<?php
session_name('cakeouflage_sid');
session_start();

if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit;
}

// Static credentials
$valid_email = "admin@cakeouflage.com";
$valid_password = "admin123";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    if ($email === $valid_email && $password === $valid_password) {
        $_SESSION['admin'] = true;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid email or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login - Cakeouflage</title>

<style>
body {
  margin: 0;
  font-family: 'Segoe UI', sans-serif;
  background: #FAF7F2;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
}

.login-box {
  width: 400px;
  background: #fff;
  padding: 40px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

.logo {
  text-align: center;
  font-size: 26px;
  font-weight: 600;
  color: #6D1B3B;
}

.logo span {
  color: #D4AF37;
}

h2 {
  text-align: center;
  margin: 20px 0;
}

input {
  width: 100%;
  padding: 12px;
  margin-bottom: 15px;
  border-radius: 10px;
  border: 1px solid #ddd;
}

button {
  width: 100%;
  padding: 12px;
  background: #6D1B3B;
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
}

button:hover {
  background: #57142f;
}

.error {
  background: #ffe5e5;
  color: red;
  padding: 10px;
  border-radius: 8px;
  margin-bottom: 10px;
  text-align: center;
}
</style>

</head>

<body>

<div class="login-box">

  <div class="logo">Cake<span>ouflage</span></div>
  <h2>Admin Login</h2>

  <?php if ($error): ?>
    <div class="error"><?php echo $error; ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button>Login</button>
  </form>

</div>

</body>
</html>