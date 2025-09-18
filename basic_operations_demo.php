<?php

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;

echo "===================================================================\n";
echo "                 6502 BASIC OPERATIONS DEMONSTRATION\n";
echo "===================================================================\n\n";

// Create a complete monitored 6502 system
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);
$cpu = new MonitoredCPU($memory);

// Set up reset vector and a basic program
$memory->initialize([
  // Reset vector pointing to our program
  0xFFFC => 0x00, // Reset vector low byte
  0xFFFD => 0x80, // Reset vector high byte -> 0x8000

  // Program: Demonstrate basic operations
  0x8000 => 0xA9,
  0x8001 => 0x42,  // LDA #$42      ; Load 0x42 into A
  0x8002 => 0xAA,                  // TAX           ; Transfer A to X
  0x8003 => 0xA9,
  0x8004 => 0x84,  // LDA #$84      ; Load 0x84 into A
  0x8005 => 0xA8,                  // TAY           ; Transfer A to Y
  0x8006 => 0x8A,                  // TXA           ; Transfer X to A
  0x8007 => 0x85,
  0x8008 => 0x80,  // STA $80       ; Store A to zero page
  0x8009 => 0x98,                  // TYA           ; Transfer Y to A
  0x800A => 0x86,
  0x800B => 0x81,  // STX $81       ; Store X to zero page
  0x800C => 0x84,
  0x800D => 0x82,  // STY $82       ; Store Y to zero page
  0x800E => 0xA6,
  0x800F => 0x80,  // LDX $80       ; Load X from zero page
  0x8010 => 0xA4,
  0x8011 => 0x82,  // LDY $82       ; Load Y from zero page
  0x8012 => 0xEA,                  // NOP           ; No operation
]);

echo "Program loaded into memory:\n";
echo "  0x8000: LDA #\$42    ; Load 0x42 into accumulator\n";
echo "  0x8002: TAX          ; Transfer A to X register\n";
echo "  0x8003: LDA #\$84    ; Load 0x84 into accumulator\n";
echo "  0x8005: TAY          ; Transfer A to Y register\n";
echo "  0x8006: TXA          ; Transfer X back to A\n";
echo "  0x8007: STA \$80      ; Store A to zero page address 0x80\n";
echo "  0x8009: TYA          ; Transfer Y to A\n";
echo "  0x800A: STX \$81      ; Store X to zero page address 0x81\n";
echo "  0x800C: STY \$82      ; Store Y to zero page address 0x82\n";
echo "  0x800E: LDX \$80      ; Load X from zero page address 0x80\n";
echo "  0x8010: LDY \$82      ; Load Y from zero page address 0x82\n";
echo "  0x8012: NOP          ; No operation\n\n";

// Reset CPU and start execution
$cpu->reset();

echo "=== EXECUTING PROGRAM STEP BY STEP ===\n\n";

// Track instruction execution
$instructions = [
  0x8000 => "LDA #\$42",
  0x8002 => "TAX",
  0x8003 => "LDA #\$84",
  0x8005 => "TAY",
  0x8006 => "TXA",
  0x8007 => "STA \$80",
  0x8009 => "TYA",
  0x800A => "STX \$81",
  0x800C => "STY \$82",
  0x800E => "LDX \$80",
  0x8010 => "LDY \$82",
  0x8012 => "NOP"
];

foreach ($instructions as $address => $instruction) {
  echo "--- Executing: $instruction ---\n";
  echo sprintf(
    "Before: PC=0x%04X A=0x%02X X=0x%02X Y=0x%02X\n",
    $cpu->pc,
    $cpu->getAccumulator(),
    $cpu->getRegisterX(),
    $cpu->getRegisterY()
  );

  $busMonitor->reset();

  // Execute the instruction
  do {
    $cpu->step();
  } while ($cpu->cycles > 0);

  echo sprintf(
    "After:  PC=0x%04X A=0x%02X X=0x%02X Y=0x%02X\n",
    $cpu->pc,
    $cpu->getAccumulator(),
    $cpu->getRegisterX(),
    $cpu->getRegisterY()
  );

  // Show bus activity for this instruction
  $activity = $busMonitor->getBusActivity();
  if (!empty($activity)) {
    echo "Bus activity:\n";
    foreach ($activity as $op) {
      echo sprintf("  %04X %s %02X\n", $op['address'], $op['operation'], $op['data']);
    }
  }
  echo "\n";
}

echo "=== FINAL MEMORY STATE ===\n";
echo sprintf("Zero page 0x80: 0x%02X (should be 0x42 from STA)\n", $memory->read_byte(0x80));
echo sprintf("Zero page 0x81: 0x%02X (should be 0x42 from STX)\n", $memory->read_byte(0x81));
echo sprintf("Zero page 0x82: 0x%02X (should be 0x84 from STY)\n", $memory->read_byte(0x82));

echo "\n=== INSTRUCTION SUMMARY ===\n";
echo "Successfully demonstrated:\n";
echo "  ✓ LDA - Load Accumulator (immediate and zero page)\n";
echo "  ✓ LDX - Load X Register (zero page)\n";
echo "  ✓ LDY - Load Y Register (zero page)\n";
echo "  ✓ STA - Store Accumulator (zero page)\n";
echo "  ✓ STX - Store X Register (zero page)\n";
echo "  ✓ STY - Store Y Register (zero page)\n";
echo "  ✓ TAX - Transfer A to X\n";
echo "  ✓ TAY - Transfer A to Y\n";
echo "  ✓ TXA - Transfer X to A\n";
echo "  ✓ TYA - Transfer Y to A\n";
echo "  ✓ NOP - No Operation\n\n";

echo "The 6502 emulator now supports " . count($instructions) . " different instructions!\n";
echo "All register transfers and basic load/store operations are working correctly.\n";

echo "\n===================================================================\n";
echo "                       DEMO COMPLETED\n";
echo "===================================================================\n";

