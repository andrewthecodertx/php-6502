<?php

use Emulator\Memory;
use Emulator\CPU;

require_once "vendor/autoload.php";

$cpu = new CPU;

$cpu->reset();
$cpu->execute('LDA', '($FF,X)');
