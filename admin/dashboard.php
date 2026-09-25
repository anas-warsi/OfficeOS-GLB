<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
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
    <?php require_once('header.php') ?>
    <?php require_once('sidebar.php') ?>
    
   
</body>
</html>