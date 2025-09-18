<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\CPU;

// Create a 6502-accurate reset implementation
class AccurateCPU extends CPU
{
  private BusMonitor $busMonitor;

  public function __construct(MonitoredMemory $memory)
  {
    $this->busMonitor = $memory->getBusMonitor();
    parent::__construct($memory);
  }

  public function reset(): void
  {
    echo "=== ACCURATE 6502 RESET SEQUENCE ===\n";
    echo "Cycle | Address Bus      | Data Bus   | Operation\n";
    echo "------|------------------|------------|----------\n";

    // Save current PC for accurate reset simulation
    $currentPC = $this->pc;

    // Cycle 1: Read current PC location (dummy read)
    $this->busMonitor->incrementCycle();
    $data1 = $this->memory->read_byte($currentPC);
    echo sprintf(
      "  1   | %016b | %08b | Read PC (dummy)\n",
      $currentPC,
      $data1
    );

    // Cycle 2: Read current PC+1 location (dummy read)
    $this->busMonitor->incrementCycle();
    $data2 = $this->memory->read_byte($currentPC + 1);
    echo sprintf(
      "  2   | %016b | %08b | Read PC+1 (dummy)\n",
      $currentPC + 1,
      $data2
    );

    // Cycles 3-5: Read stack locations (dummy reads, SP decremented each time)
    $tempSP = $this->sp;
    for ($cycle = 3; $cycle <= 5; $cycle++) {
      $this->busMonitor->incrementCycle();
      $tempSP = ($tempSP - 1) & 0xFF; // Decrement SP
      $stackAddr = 0x0100 + $tempSP;
      $stackData = $this->memory->read_byte($stackAddr);
      echo sprintf(
        "  %d   | %016b | %08b | Read stack (dummy, SP dec)\n",
        $cycle,
        $stackAddr,
        $stackData
      );
    }

    // Cycle 6: Read reset vector low byte
    $this->busMonitor->incrementCycle();
    $resetLow = $this->memory->read_byte(0xFFFC);
    echo sprintf(
      "  6   | %016b | %08b | Read reset vector low\n",
      0xFFFC,
      $resetLow
    );

    // Cycle 7: Read reset vector high byte and load PC
    $this->busMonitor->incrementCycle();
    $resetHigh = $this->memory->read_byte(0xFFFD);
    echo sprintf(
      "  7   | %016b | %08b | Read reset vector high\n",
      0xFFFD,
      $resetHigh
    );

    // Set final CPU state
    $this->pc = ($resetHigh << 8) | $resetLow;
    $this->sp = 0xFD; // SP after 3 decrements from 0x00

    // Only the I flag is guaranteed to be set, others are undefined
    // For emulation purposes, we'll clear all flags except I
    $this->status->fromInt(0b00000100); // Only interrupt disable set

    // A, X, Y registers are undefined after reset in real hardware
    // For emulation, we'll leave them as they were

    $this->cycles = 0;

    echo "\n=== RESET COMPLETE ===\n";
    echo sprintf("PC: 0x%04X (from reset vector)\n", $this->pc);
    echo sprintf("SP: 0x%02X (decremented 3 times from 0x00)\n", $this->sp);
    echo sprintf("Status: 0b%08b (only I flag guaranteed set)\n", $this->status->toInt());
    echo sprintf("A: 0x%02X (undefined - could be any value)\n", $this->accumulator);
    echo sprintf("X: 0x%02X (undefined - could be any value)\n", $this->register_x);
    echo sprintf("Y: 0x%02X (undefined - could be any value)\n", $this->register_y);
  }

  public function clock(): void
  {
    parent::clock();
    $this->busMonitor->incrementCycle();
  }

  public function getBusMonitor(): BusMonitor
  {
    return $this->busMonitor;
  }
}

echo "6502 Accurate Reset Sequence Demonstration\n";
echo "==========================================\n\n";

// Create system
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);

// Set up memory with some initial values
$memory->initialize([
  0xFFFC => 0x00,  // Reset vector low byte
  0xFFFD => 0x80,  // Reset vector high byte -> 0x8000
  0x8000 => 0xEA,  // NOP instruction at reset location
  0x01FD => 0xAA,  // Some data on stack
  0x01FC => 0xBB,  // Some data on stack
  0x01FB => 0xCC,  // Some data on stack
]);

$cpu = new AccurateCPU($memory);

// Set initial CPU state to simulate power-on
$cpu->pc = 0x1234;     // Random PC value
$cpu->sp = 0x00;       // SP starts at 0x00
$cpu->accumulator = 0x55; // Random A value
$cpu->register_x = 0xAA;  // Random X value
$cpu->register_y = 0xFF;  // Random Y value

echo "Initial CPU state (power-on random values):\n";
echo sprintf("PC: 0x%04X\n", $cpu->pc);
echo sprintf("SP: 0x%02X\n", $cpu->sp);
echo sprintf(
  "A: 0x%02X, X: 0x%02X, Y: 0x%02X\n",
  $cpu->accumulator,
  $cpu->register_x,
  $cpu->register_y
);
echo "\n";

// Clear bus monitor and perform reset
$busMonitor->reset();
$cpu->reset();

echo "\n=== BUS ACTIVITY SUMMARY ===\n";
$busMonitor->displayBusActivity();

echo "\nNote: In real 6502 hardware:\n";
echo "- Cycles 1-5 perform dummy reads that are ignored\n";
echo "- Only cycles 6-7 load the reset vector into PC\n";
echo "- Stack pointer is decremented but no writes occur\n";
echo "- Registers A, X, Y retain undefined values\n";
echo "- Only the I (interrupt disable) flag is guaranteed set\n";

