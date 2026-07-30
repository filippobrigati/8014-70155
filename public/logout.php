<?php
setcookie('auth_token', '', time() - 3600, '/');
header("Location: auth/login.php");
exit;