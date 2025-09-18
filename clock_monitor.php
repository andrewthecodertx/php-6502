<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\CPU;
use Emulator\StatusRegister;
use Emulator\InstructionRegister;
use Emulator\Instructions\LoadStore;

// Create a completely clean CPU implementation for bus monitoring
class BusTraceCPU extends CPU
{
  private BusMonitor $busMonitor;

  public function __construct(MonitoredMemory $memory)
  {
    $this->busMonitor = $memory->getBusMonitor();
    parent::__construct($memory);
  }

  public function reset(): void
  {
    // Reset vector read
    $this->pc = $this->memory->read_word(0xFFFC);
    $this->sp = 0xFD;
    $this->accumulator = 0;
    $this->register_x = 0;
    $this->register_y = 0;
    $this->status->fromInt(0b00110100);
    $this->cycles = 0;
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

echo "6502 Clock Cycle Bus Monitor\n";
echo "Each line shows: ADDRESS_BUS(16-bit)    DATA_BUS(8-bit)    ADDR_HEX    R/W  DATA_HEX\n";
echo "================================================================================\n";

// Create system
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);
$cpu = new BusTraceCPU($memory);

// Set up program
$memory->write_word(0xFFFC, 0x8000);  // Reset vector
$memory->write_byte(0x8000, 0xA9);    // LDA #$42
$memory->write_byte(0x8001, 0x42);
$memory->write_byte(0x8002, 0x8D);    // STA $0200
$memory->write_byte(0x8003, 0x00);
$memory->write_byte(0x8004, 0x02);

// Clear monitor before starting
$busMonitor->reset();

// Reset CPU
$cpu->reset();

// Execute two instructions
$cpu->executeInstruction(); // LDA #$42
$cpu->executeInstruction(); // STA $0200

// Display the bus activity
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

echo "\nExplanation:\n";
echo "- Reset vector read: FFFC (low byte), FFFD (high byte)\n";
echo "- LDA #\$42: 8000 (opcode A9), 8001 (operand 42)\n";
echo "- STA \$0200: 8002 (opcode 8D), 8003 (addr low 00), 8004 (addr high 02), 0200 (write 42)\n";
echo "\nTotal operations: " . count($activity) . "\n";

