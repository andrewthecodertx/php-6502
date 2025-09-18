<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;

echo "6502 Bus Monitor - Clock Cycle Output\n";
echo "Format: ADDRESS_BUS(16-bit)    DATA_BUS(8-bit)    ADDR_HEX    R/W  DATA_HEX\n";
echo "==========================================================================\n";

// Create monitored system
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);

// Set up program BEFORE creating CPU to avoid monitoring setup writes
$memory->initialize([
  0xFFFC => 0x00,  // Reset vector low byte
  0xFFFD => 0x80,  // Reset vector high byte -> 0x8000
  0x8000 => 0xA9,  // LDA #$42
  0x8001 => 0x42,
  0x8002 => 0x8D,  // STA $0200
  0x8003 => 0x00,  // Low byte of address
  0x8004 => 0x02,  // High byte of address
]);

// Now create CPU and reset monitor
$cpu = new MonitoredCPU($memory);
$busMonitor->reset();

// Perform reset (will read reset vector)
$cpu->reset();

// Execute two instructions
$cpu->executeInstruction(); // LDA #$42
$cpu->executeInstruction(); // STA $0200

// Display clean bus output
$activity = $busMonitor->getBusActivity();

foreach ($activity as $op) {
  echo sprintf(
    "%016b    %08b    %04X    %s  %02X\n",
    $op['address'],
    $op['data'],
    $op['address'],
    $op['operation'],
    $op['data']
  );
}

echo "\nBus Operations Explained:\n";
echo "1111111111111100 -> 0xFFFC (Reset vector low byte read)\n";
echo "1111111111111101 -> 0xFFFD (Reset vector high byte read)\n";
echo "1000000000000000 -> 0x8000 (Fetch LDA opcode)\n";
echo "1000000000000001 -> 0x8001 (Fetch immediate operand #$42)\n";
echo "1000000000000010 -> 0x8002 (Fetch STA opcode)\n";
echo "1000000000000011 -> 0x8003 (Fetch address low byte)\n";
echo "1000000000000100 -> 0x8004 (Fetch address high byte)\n";
echo "0000001000000000 -> 0x0200 (Write accumulator to target address)\n";
echo "\nTotal bus operations: " . count($activity) . "\n";
echo "Final accumulator value: 0x" . sprintf('%02X', $cpu->getAccumulator()) . "\n";
echo "Memory at 0x0200: 0x" . sprintf('%02X', $memory->read_byte(0x0200)) . "\n";

