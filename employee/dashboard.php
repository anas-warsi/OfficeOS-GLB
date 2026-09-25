<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'manager') {
    header("Location: ../index.php");
    exit();
}
?>

                


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    
</head>
<body>
   <h1>Manager Dashboard</h1>
   
</body>
</html>