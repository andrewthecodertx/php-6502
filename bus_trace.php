<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;

echo "6502 CPU Bus Trace\n";
echo "Format: ADDRESS_BUS(16-bit)    DATA_BUS(8-bit)    ADDR_HEX    R/W  DATA_HEX\n";
echo "=======================================================================\n";

// Create the monitored system
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);
$cpu = new MonitoredCPU($memory);

// Set up a simple program
$memory->write_word(0xFFFC, 0x8000);  // Reset vector -> 0x8000

// Program at 0x8000:
$memory->write_byte(0x8000, 0xA9);    // LDA #$42
$memory->write_byte(0x8001, 0x42);
$memory->write_byte(0x8002, 0x85);    // STA $80 (zero page)
$memory->write_byte(0x8003, 0x80);
$memory->write_byte(0x8004, 0xA5);    // LDA $80 (zero page)
$memory->write_byte(0x8005, 0x80);
$memory->write_byte(0x8006, 0xEA);    // NOP

// Reset the bus monitor and CPU
$busMonitor->reset();

// Disable the verbose output from MonitoredCPU for clean trace
// We'll override the reset method for this demonstration

class CleanMonitoredCPU extends MonitoredCPU
{
  public function reset(): void
  {
    $this->resetInProgress = true;

    // Internal reset cycles (6502 takes 7 cycles total for reset)
    for ($cycle = 1; $cycle <= 5; $cycle++) {
      $this->getBusMonitor()->incrementCycle();
    }

    // Actual reset reads
    parent::reset();
    $this->resetInProgress = false;
  }

  public function step(): void
  {
    parent::step();
  }

  public function executeInstruction(): void
  {
    $startingPC = $this->pc;
    do {
      $this->step();
    } while ($this->cycles > 0 || $this->pc == $startingPC);
  }
}

$cpu = new CleanMonitoredCPU($memory);
$busMonitor = $cpu->getBusMonitor();

// Reset CPU (this will show reset vector reads)
$cpu->reset();

// Execute several instructions
for ($i = 0; $i < 4; $i++) {
  $cpu->executeInstruction();
}

// Output the bus trace in the exact format requested
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
echo "Final CPU state:\n";
echo sprintf("  PC: 0x%04X\n", $cpu->pc);
echo sprintf("  A:  0x%02X\n", $cpu->getAccumulator());
echo sprintf("  SP: 0x%02X\n", $cpu->sp);
echo sprintf("  Memory[0x80]: 0x%02X\n", $memory->read_byte(0x80));

