<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;

echo "6502 Bus Monitor - Simple Output Format\n";
echo "Format: ADDRESS_BUS(16-bit binary)  DATA_BUS(8-bit binary)  ADDR_HEX  R/W  DATA_HEX\n";
echo "================================================================================\n";

// Create components
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);
$cpu = new MonitoredCPU($memory);

// Set up program
$memory->write_word(0xFFFC, 0x8000);  // Reset vector
$memory->write_byte(0x8000, 0xA9);    // LDA #$42
$memory->write_byte(0x8001, 0x42);
$memory->write_byte(0x8002, 0x85);    // STA $80 (zero page)
$memory->write_byte(0x8003, 0x80);
$memory->write_byte(0x8004, 0xEA);    // NOP

// Clear monitor and reset CPU
$busMonitor->reset();

// CPU Reset (reads reset vector)
$cpu->reset();

// Execute a few instructions
for ($i = 0; $i < 3; $i++) {
  $cpu->executeInstruction();
}

// Display all bus activity in simple format
$activity = $busMonitor->getBusActivity();

foreach ($activity as $op) {
  $addressBinary = sprintf('%016b', $op['address']);
  $dataBinary = sprintf('%08b', $op['data']);
  $addressHex = sprintf('%04X', $op['address']);
  $dataHex = sprintf('%02X', $op['data']);

  echo sprintf(
    "%s    %s    %s    %s  %s\n",
    $addressBinary,
    $dataBinary,
    $addressHex,
    $op['operation'],
    $dataHex
  );
}

echo "\nTotal bus operations: " . count($activity) . "\n";

