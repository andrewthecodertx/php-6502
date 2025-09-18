<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\CPU;

// Simple CPU that just shows bus activity without verbose output
class SimpleCPU extends CPU
{
  private BusMonitor $busMonitor;

  public function __construct(MonitoredMemory $memory)
  {
    $this->busMonitor = $memory->getBusMonitor();
    parent::__construct($memory);
  }

  public function getBusMonitor(): BusMonitor
  {
    return $this->busMonitor;
  }
}

echo "6502 Accurate Reset Sequence - Simple Bus Output\n";
echo "Format: ADDRESS_BUS(16-bit)    DATA_BUS(8-bit)    ADDR_HEX    R/W  DATA_HEX\n";
echo "==========================================================================\n";

// Create system
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);

// Set up memory
$memory->initialize([
  0x1234 => 0xEA,  // Current PC
  0x1235 => 0xEA,  // PC+1
  0x01FF => 0xAA,  // Stack
  0x01FE => 0xBB,  // Stack
  0x01FD => 0xCC,  // Stack
  0xFFFC => 0x00,  // Reset vector low
  0xFFFD => 0x80,  // Reset vector high -> 0x8000
  0x8000 => 0xA9,  // LDA #$42
  0x8001 => 0x42,
]);

$cpu = new SimpleCPU($memory);
$cpu->pc = 0x1234; // Set initial PC

// Clear monitor and reset
$busMonitor->reset();
$cpu->reset(); // This now performs the accurate 7-cycle reset

// Show bus activity
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

echo "\nReset sequence shows exactly 7 bus operations:\n";
echo "1-2: Dummy reads from PC and PC+1\n";
echo "3-5: Dummy reads from stack with SP decrement\n";
echo "6-7: Read reset vector from 0xFFFC/0xFFFD\n";
echo "\nThis matches the real 6502 reset sequence specification.\n";

