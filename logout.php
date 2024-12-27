<?php
require_once 'config/config.php';

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to home page
redirect('index.php'); 