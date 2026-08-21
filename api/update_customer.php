<?php
// Compatibility wrapper, forwards to api/customer.php action=update
$_REQUEST['action'] = 'update';
require_once __DIR__ . '/customer.php';
