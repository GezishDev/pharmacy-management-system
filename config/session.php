<?php
// config/session.php

function requireLogin() {
    session_start();
    if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: ../login.php');
        exit;
    }
}

function requireAdmin() {
    session_start();
    if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../dashboard.php');
        exit;
    }
}
?>