<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;

echo "===================================================================\n";
echo "                    6502 BUS MONITOR DEMONSTRATION\n";
echo "===================================================================\n\n";

// Create bus monitor and monitored memory
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);
$cpu = new MonitoredCPU($memory);

// Set up a simple program in memory
echo "Setting up test program in memory...\n";

// Reset vector points to 0x8000
$memory->write_word(0xFFFC, 0x8000);

// Simple test program at 0x8000
$memory->write_byte(0x8000, 0xA9); // LDA #$42
$memory->write_byte(0x8001, 0x42);
$memory->write_byte(0x8002, 0x8D); // STA $0200
$memory->write_byte(0x8003, 0x00);
$memory->write_byte(0x8004, 0x02);
$memory->write_byte(0x8005, 0xEA); // NOP

echo "Program loaded:\n";
echo "  0x8000: LDA #$42  ; Load accumulator with 0x42\n";
echo "  0x8002: STA $0200 ; Store accumulator to memory location 0x0200\n";
echo "  0x8005: NOP       ; No operation\n\n";

// Clear bus monitor before reset
$busMonitor->reset();

// Perform CPU reset and show bus activity
echo "Performing CPU reset...\n\n";
$cpu->reset();

// Show reset bus activity
echo "Bus activity during reset:\n";
$busMonitor->displayBusActivity();
echo "\n";

// Execute the test program instruction by instruction
echo "Executing test program...\n\n";

// Execute LDA #$42
echo "--- Executing: LDA #$42 ---\n";
$cpu->displayState();
$beforeCycle = $busMonitor->getCurrentCycle();
$cpu->executeInstruction();
$cpu->displayState();

echo "Bus activity for LDA #$42:\n";
$activity = $busMonitor->getBusActivity();
$newActivity = array_slice($activity, -2); // Show last 2 operations
foreach ($newActivity as $op) {
  $addressBinary = sprintf('%016b', $op['address']);
  $dataBinary = sprintf('%08b', $op['data']);
  $addressHex = sprintf('%04X', $op['address']);
  $dataHex = sprintf('%02X', $op['data']);
  $description = '';

  if ($op['address'] == 0x8000) {
    $description = 'Fetch opcode (LDA immediate)';
  } elseif ($op['address'] == 0x8001) {
    $description = 'Fetch operand (0x42)';
  }

  echo sprintf(
    "%5d  %s  %s    %s   %s     %s  %s\n",
    $op['cycle'],
    $addressBinary,
    $dataBinary,
    $addressHex,
    $op['operation'],
    $dataHex,
    $description
  );
}
echo "\n";

// Execute STA $0200
echo "--- Executing: STA $0200 ---\n";
$cpu->displayState();
$cpu->executeInstruction();
$cpu->displayState();

echo "Bus activity for STA $0200:\n";
$activity = $busMonitor->getBusActivity();
$newActivity = array_slice($activity, -3); // Show last 3 operations
foreach ($newActivity as $op) {
  $addressBinary = sprintf('%016b', $op['address']);
  $dataBinary = sprintf('%08b', $op['data']);
  $addressHex = sprintf('%04X', $op['address']);
  $dataHex = sprintf('%02X', $op['data']);
  $description = '';

  if ($op['address'] == 0x8002) {
    $description = 'Fetch opcode (STA absolute)';
  } elseif ($op['address'] == 0x8003) {
    $description = 'Fetch address low byte';
  } elseif ($op['address'] == 0x8004) {
    $description = 'Fetch address high byte';
  } elseif ($op['address'] == 0x0200) {
    $description = 'Store accumulator to target address';
  }

  echo sprintf(
    "%5d  %s  %s    %s   %s     %s  %s\n",
    $op['cycle'],
    $addressBinary,
    $dataBinary,
    $addressHex,
    $op['operation'],
    $dataHex,
    $description
  );
}
echo "\n";

// Execute NOP
echo "--- Executing: NOP ---\n";
$cpu->displayState();
$cpu->executeInstruction();
$cpu->displayState();

echo "Bus activity for NOP:\n";
$activity = $busMonitor->getBusActivity();
$newActivity = array_slice($activity, -1); // Show last 1 operation
foreach ($newActivity as $op) {
  $addressBinary = sprintf('%016b', $op['address']);
  $dataBinary = sprintf('%08b', $op['data']);
  $addressHex = sprintf('%04X', $op['address']);
  $dataHex = sprintf('%02X', $op['data']);
  $description = 'Fetch opcode (NOP)';

  echo sprintf(
    "%5d  %s  %s    %s   %s     %s  %s\n",
    $op['cycle'],
    $addressBinary,
    $dataBinary,
    $addressHex,
    $op['operation'],
    $dataHex,
    $description
  );
}
echo "\n";

// Show complete bus activity summary
echo "===================================================================\n";
echo "                    COMPLETE BUS ACTIVITY LOG\n";
echo "===================================================================\n";
$busMonitor->displayBusActivity();

echo "\n";
echo "===================================================================\n";
echo "                        FINAL MEMORY STATE\n";
echo "===================================================================\n";
echo sprintf("Memory at 0x0200: 0x%02X (should be 0x42)\n", $memory->read_byte(0x0200));
echo sprintf("Total bus cycles: %d\n", $busMonitor->getCurrentCycle());

echo "\nDemo completed!\n";

