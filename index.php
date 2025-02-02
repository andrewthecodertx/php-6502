<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\CPU;
use Emulator\Memory;

// Initialize the system
$memory = new Memory();

// Load a simple program into memory
// Example program:
// LDA #$42    ; Load accumulator with 0x42
// LDA $20     ; Load accumulator with value at zero page address 0x20
// LDA $1234   ; Load accumulator with value at absolute address 0x1234

// First, let's put some values in memory
$memory->write_byte(0x20, 0x55);     // Value in zero page
$memory->write_byte(0x1234, 0x77);   // Value in absolute address

// Set up reset vector (where CPU looks for program start)
$memory->write_word(0xFFFC, 0x0600); // Program starts at $0600

// Write our program starting at $0600
$program = [
  0x0600 => 0xA9, // LDA Immediate
  0x0601 => 0x42, // #$42
  0x0602 => 0xA5, // LDA Zero Page
  0x0603 => 0x20, // $20
  0x0604 => 0xAD, // LDA Absolute
  0x0605 => 0x34, // $1234 (low byte)
  0x0606 => 0x12  // $1234 (high byte)
];

// Load program into memory
foreach ($program as $addr => $value) {
  $memory->write_byte($addr, $value);
}

// Create and initialize CPU
$cpu = new CPU($memory);

// Function to display CPU state
function displayCPUState(CPU $cpu): void
{
  echo "----------------------------------------\n";
  echo "CPU State:\n";
  echo $cpu->getRegistersState() . "\n";
  echo $cpu->getFlagsState() . "\n";
  echo "----------------------------------------\n";
}

// Execute our program
echo "Initial state:\n";
displayCPUState($cpu);

// Execute instructions one by one
echo "\nExecuting LDA #\$42:\n";
$cpu->execute('A9', '#$42');
displayCPUState($cpu);

echo "\nExecuting LDA \$20:\n";
$cpu->execute('A5', '$20');
displayCPUState($cpu);

echo "\nExecuting LDA \$1234:\n";
$cpu->execute('AD', '$1234');
displayCPUState($cpu);

// Example test of flags
echo "\nTesting zero flag - LDA #\$00:\n";
$cpu->execute('A9', '#$00');
displayCPUState($cpu);

echo "\nTesting negative flag - LDA #\$80:\n";
$cpu->execute('A9', '#$80');
displayCPUState($cpu);
