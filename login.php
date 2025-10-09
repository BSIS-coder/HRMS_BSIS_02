<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

// Database connection
$host = 'localhost';
$dbname = 'hr_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Demo credentials array (fallback for demo users)
$demo_users = [
    [
        'username' => 'john.admin',
        'password' => 'admin123',
        'role' => 'admin',
        'user_id' => 1
    ],
    [
        'username' => 'sara.hr',
        'password' => 'hr123',
        'role' => 'hr',
        'user_id' => 2
    ],
    // Employee demo users from hr_system.sql
    [ 'username' => 'maria.santos', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 3 ],
    [ 'username' => 'roberto.cruz', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 4 ],
    [ 'username' => 'jennifer.reyes', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 5 ],
    [ 'username' => 'antonio.garcia', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 6 ],
    [ 'username' => 'lisa.mendoza', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 7 ],
    [ 'username' => 'michael.torres', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 8 ],
    [ 'username' => 'carmen.delacruz', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 9 ],
    [ 'username' => 'ricardo.villanueva', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 10 ],
    [ 'username' => 'sandra.pascual', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 11 ],
    [ 'username' => 'jose.ramos', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 12 ],
    [ 'username' => 'ana.morales', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 13 ],
    [ 'username' => 'pablo.fernandez', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 14 ],
    [ 'username' => 'grace.lopez', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 15 ],
    [ 'username' => 'eduardo.hernandez', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 16 ],
    [ 'username' => 'rosario.gonzales', 'password' => 'emp123', 'role' => 'employee', 'user_id' => 17 ],
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate user input
    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        $authenticated = false;
        
        // First, try to authenticate from database
        try {
            $stmt = $pdo->prepare("SELECT user_id, username, password, role, is_active FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Check if account is active
                if (!$user['is_active']) {
                    $error = "Your account has been deactivated. Please contact an administrator.";
                } else {
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Store data in session variables
                        $_SESSION['loggedin'] = true;
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        $authenticated = true;
                        
                        // Redirect based on role
                        if ($user['role'] === 'employee') {
                            header("location: employee_index.php");
                        } else {
                            header("location: index.php");
                        }
                        exit;
                    }
                }
            }
        } catch(PDOException $e) {
            // If database fails, fall back to demo users
            error_log("Database authentication failed: " . $e->getMessage());
        }
        
        // If database authentication failed or user not found, try demo users
        if (!$authenticated) {
            foreach ($demo_users as $user) {
                if ($username === $user['username'] && $password === $user['password']) {
                    // Store data in session variables
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    $authenticated = true;
                    
                    // Redirect based on role
                    if ($user['role'] === 'employee') {
                        header("location: employee_index.php");
                    } else {
                        header("location: index.php");
                    }
                    exit;
                }
            }
        }
        
        if (!$authenticated) {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR System Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css?v=rose">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #FCE4EC;  /* Updated to match --bg-primary */
        }
        
        /* Modern gradient background */
        .modern-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: linear-gradient(-45deg, #E91E63, #F06292, #F8BBD0, #C2185B);  /* Updated to rose red theme */
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        /* Glowing orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            animation: float 20s ease-in-out infinite;
        }
        
        .orb1 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(233, 30, 99, 0.8) 0%, rgba(233, 30, 99, 0) 70%);  /* E91E63 */
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .orb2 {
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(240, 98, 146, 0.8) 0%, rgba(240, 98, 146, 0) 70%);  /* F06292 */
            top: 60%;
            right: 15%;
            animation-delay: -5s;
        }
        
        .orb3 {
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(248, 187, 208, 0.8) 0%, rgba(248, 187, 208, 0) 70%);  /* F8BBD0 */
            bottom: 20%;
            left: 20%;
            animation-delay: -10s;
        }
        
        .orb4 {
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(194, 24, 91, 0.8) 0%, rgba(194, 24, 91, 0) 70%);  /* C2185B */
            top: 10%;
            right: 25%;
            animation-delay: -7s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px) translateX(0px) scale(1);
            }
            25% {
                transform: translateY(-40px) translateX(20px) scale(1.1);
            }
            50% {
                transform: translateY(-20px) translateX(-30px) scale(0.9);
            }
            75% {
                transform: translateY(-60px) translateX(10px) scale(1.05);
            }
        }
        
        /* Geometric shapes */
        .geometric-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            animation: rotate 20s linear infinite;
        }
        
        .triangle {
            width: 0;
            height: 0;
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
            border-bottom: 52px solid #E91E63;  /* Updated color */
            top: 25%;
            left: 70%;
            animation-delay: 0s;
        }
        
        .square {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #F06292, #C2185B);  /* Updated colors */
            top: 70%;
            left: 80%;
            animation-delay: -5s;
        }
        
        .circle {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #F8BBD0, #E91E63);  /* Updated colors */
            border-radius: 50%;
            top: 15%;
            left: 85%;
            animation-delay: -10s;
        }
        
        @keyframes rotate {
            0% {
                transform: rotate(0deg) scale(1);
            }
            50% {
                transform: rotate(180deg) scale(1.2);
            }
            100% {
                transform: rotate(360deg) scale(1);
            }
        }
        
        /* Grid pattern overlay */
        .grid-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 25s linear infinite;
        }
        
        @keyframes gridMove {
            0% {
                transform: translate(0, 0);
            }
            100% {
                transform: translate(50px, 50px);
            }
        }
        
        /* Particle effect */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            animation: particle 15s linear infinite;
        }
        
        .particle:nth-child(1) { left: 20%; animation-delay: 0s; }
        .particle:nth-child(2) { left: 40%; animation-delay: -2s; }
        .particle:nth-child(3) { left: 60%; animation-delay: -4s; }
        .particle:nth-child(4) { left: 80%; animation-delay: -6s; }
        .particle:nth-child(5) { left: 10%; animation-delay: -8s; }
        .particle:nth-child(6) { left: 30%; animation-delay: -10s; }
        .particle:nth-child(7) { left: 50%; animation-delay: -12s; }
        .particle:nth-child(8) { left: 70%; animation-delay: -14s; }
        .particle:nth-child(9) { left: 90%; animation-delay: -16s; }
        .particle:nth-child(10) { left: 15%; animation-delay: -18s; }
        
        @keyframes particle {
            0% {
                opacity: 0;
                transform: translateY(100vh) scale(0);
            }
            10% {
                opacity: 1;
                transform: translateY(90vh) scale(1);
            }
            90% {
                opacity: 1;
                transform: translateY(10vh) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(0vh) scale(0);
            }
        }
        
        /* Login container with glassmorphism effect */
        .login-container {
            max-width: 420px;
            padding: 50px 40px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 24px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            z-index: 10;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: containerFloat 6s ease-in-out infinite;
        }
        
        @keyframes containerFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            border-radius: 24px;
            z-index: -1;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 20px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .login-header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 8px 0;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.5px;
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            font-weight: 400;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group-text {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-right: none;
            color: rgba(255, 255, 255, 0.8);
            border-radius: 12px 0 0 12px;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            padding: 14px 16px;
            border-radius: 0 12px 12px 0;
            font-size: 15px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .form-control:focus {
            border-color: #E91E63;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.2);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(233, 30, 99, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #C2185B 0%, #880E4F 100%);
            box-shadow: 0 15px 35px rgba(233, 30, 99, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .alert {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
            border-radius: 12px;
            padding: 16px;
            backdrop-filter: blur(10px);
            margin-bottom: 20px;
        }
        
        /* Responsive design */
        @media (max-width: 480px) {
            .login-container {
                margin: 20px;
                padding: 30px 25px;
                max-width: none;
            }
            
            .login-header h1 {
                font-size: 28px;
            }
            
            .orb1, .orb2, .orb3, .orb4 {
                width: 150px;
                height: 150px;
            }
        }
        
        /* Additional modern touches */
        .login-container:hover {
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.15);
        }
        
        /* Subtle glow effect for the container */
        @keyframes glow {
            0%, 100% {
                box-shadow: 
                    0 25px 50px rgba(0, 0, 0, 0.25),
                    0 0 0 1px rgba(255, 255, 255, 0.1),
                    0 0 30px rgba(233, 30, 99, 0.1);  /* Updated color */
            }
            50% {
                box-shadow: 
                    0 25px 50px rgba(0, 0, 0, 0.25),
                    0 0 0 1px rgba(255, 255, 255, 0.1),
                    0 0 40px rgba(233, 30, 99, 0.2);  /* Updated color */
            }
        }
        
        .login-container {
            animation: containerFloat 6s ease-in-out infinite, glow 4s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <!-- Ultra-modern animated background -->
    <div class="modern-bg"></div>
    
    <!-- Glowing orbs -->
    <div class="orb orb1"></div>
    <div class="orb orb2"></div>
    <div class="orb orb3"></div>
    <div class="orb orb4"></div>
    
    <!-- Geometric shapes -->
    <div class="geometric-shapes">
        <div class="shape triangle"></div>
        <div class="shape square"></div>
        <div class="shape circle"></div>
    </div>
    
    <!-- Grid overlay -->
    <div class="grid-overlay"></div>
    
    <!-- Floating particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    
    <div class="login-container">
        <div class="login-header">
            <img src="image/GARAY.jpg" alt="HR System Logo">
            <h1>HR System</h1>
            <p>Enter your credentials to access the system</p>
        </div>
        
        <?php if (isset($error)) { echo '<div class="alert alert-danger">' . $error . '</div>'; } ?>
        
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>