<?php
// Compatibility wrapper, forwards to api/customer.php action=create
$_REQUEST['action'] = 'create';
require_once __DIR__ . '/customer.php';
