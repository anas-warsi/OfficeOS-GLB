<?php

session_start();


require_once 'config/database.php'; 

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        
        $query = "SELECT id, full_name, password_hash, role FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password_hash'])) {
            
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['full_name'];
                $_SESSION['email'] = $email;
                $_SESSION['role'] =  $user['role'];
                $_SESSION['logged_in'] = true;
                
                

                if($_SESSION['role'] == "admin"){
                        header("Location: /OfficeOS-GLB/admin/dashboard.php");
                        exit();
                }

                  if($_SESSION['role'] == "manager"){
                        header("Location: /OfficeOS-GLB/manager/dashboard.php");
                        exit();
                }
                  if($_SESSION['role'] == "employee"){
                        header("Location: /OfficeOS-GLB/employee/dashboard.php");
                        exit();
                }

               

            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
        
        $stmt->close();
    }
}
require_once('login.html')
?>
