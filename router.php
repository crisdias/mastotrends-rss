<?php
// this file is needed for local PHP development with php -S 0.0.0.0:3000 router.php


$_SERVER['SCRIPT_NAME'] = 'index.php';
define('CACHE', true);
include 'index.php';
