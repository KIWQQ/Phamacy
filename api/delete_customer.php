<?php
// Compatibility wrapper, forwards to api/customer.php action=delete
$_REQUEST['action'] = 'delete';
require_once __DIR__ . '/customer.php';
