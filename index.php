<?php require getcwd() . '/include/config.inc.php'; ?>
<?php require getcwd() . '/include/functions.inc.php'; ?>
<?php
$f = fopen(getcwd() . DS . 'request.log', 'a');
fwrite($f, json_encode(array_merge($_POST, $_GET, $_FILES)));
fwrite($f, "\n\n");
fclose($f);
?>
<?php
processRequest();
?>