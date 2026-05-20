<?php
session_name('cakeouflage_sid');
session_start();
session_destroy();
header("Location: login.php");