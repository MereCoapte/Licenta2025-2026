<?php
if(!isset($_SESSION['user_id'])) {
    header('Location: /Ecommerce_site/login.php');
    exit;
}