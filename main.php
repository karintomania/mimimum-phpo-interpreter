<?php

require __DIR__.'/interpreter.php';

// remove comments (only support line comments)
$code=preg_replace('/\/\/.*?\n/','',file_get_contents("php://stdin"));

$code=preg_replace('/\s/','',$code);
print($code);
// $pos = 0;
// evaluate($code, $pos);
