<?php
$f = 'api_push_subscribe.php';
$c = file_get_contents($f);
$new = str_replace("require_once 'includes/db_connect.php';", "require_once __DIR__ . '/includes/db_connect.php';", $c);
file_put_contents($f, $new);
echo "API Fixed!";
?>
