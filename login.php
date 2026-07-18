<?php
session_start();

// --- NEW: Use config_loader ---
// This file includes db_config.php AND populates the $settings array
require_once 'config_loader.php';

$error = '';

// --- NEW: Use password from database ---
$admin_password = $settings['login_password']; 

// If already logged in, redirect to admin panel
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: admin.php');
    exit;
}

// Handle login form submission
if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        // Password is correct!
        $_SESSION['loggedin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Incorrect password. Try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - il-hub.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-gray-200 antialiased flex items-center justify-center min-h-screen">

    <form action="login.php" method="POST" class="bg-gray-800 p-8 rounded-lg shadow-lg w-full max-w-sm">
        <h1 class="text-2xl font-bold text-white text-center mb-6">
            <span class="text-orange-400">il-hub</span>.com Admin
        </h1>
        
        <?php if ($error): ?>
            <div class="bg-red-600 p-3 rounded-lg text-white text-center mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-300">Password</label>
            <input type="password" name="password" id="password" required class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md shadow-sm text-white focus:border-orange-400 focus:ring focus:ring-orange-300 focus:ring-opacity-50">
        </div>

        <button type="submit" class="mt-6 w-full bg-orange-500 hover:bg-orange-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition-colors">
            Log In
        </button>
    </form>

</body>
</html>