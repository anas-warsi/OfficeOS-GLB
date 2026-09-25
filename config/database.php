<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "officeos_db";

$conn = new mysqli($host, $user, $password, $database);

if($conn->connect_error){
    die("Error".$conn->connect_error);
}


?>