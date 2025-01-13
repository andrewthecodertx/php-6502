<?php

use Emulator\Memory;
use Emulator\CPU;

require_once "vendor/autoload.php";

$memory = new Memory;
$cpu = new CPU($memory);

$cpu->reset();
$cpu->execute('LDA', '($FF,X)');
