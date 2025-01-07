<?php
// auth.php
include('config.php');

if(!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
?>
