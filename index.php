<?php

use Emulator\Memory;
use Emulator\CPU;

require_once "vendor/autoload.php";

$memory = new Memory;
$cpu = new CPU($memory);

$cpu->reset();
$cpu->execute('LDA', '#$01');

// Display CPU Status
echo "CPU Status:\n";
echo "-----------\n";
echo $cpu->getRegistersState() . "\n";
echo $cpu->getFlagsState() . "\n";

// Here you might execute some instructions to see changes
// $cpu->execute('LDA', '#$01'); // Example instruction

// Then you can check the status again
// echo "After operation:\n";
// echo $cpu->getRegistersState() . "\n";
// echo $cpu->getFlagsState() . "\n";

echo "Memory (First 16 bytes):\n";
echo "-----------------------\n";
echo "Address | Value\n";
echo "--------|------\n";
for ($i = 0; $i < 16; $i++) {
  echo sprintf('$%04X', $i) . " | " . sprintf('%02X', $memory->read_byte($i)) . "\n";
}
echo "\n";
