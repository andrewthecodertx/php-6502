<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;

echo "6502 RESET SEQUENCE SPECIFICATION\n";
echo "==================================\n\n";

echo "According to MOS 6502 Programming Manual:\n\n";

echo "RESET Sequence (7 cycles total):\n";
echo "---------------------------------\n";
echo "Cycle 1: Read from current PC address (dummy read, ignored)\n";
echo "Cycle 2: Read from PC+1 address (dummy read, ignored)\n";
echo "Cycle 3: Read from stack 0x0100+SP, decrement SP (dummy read)\n";
echo "Cycle 4: Read from stack 0x0100+SP, decrement SP (dummy read)\n";
echo "Cycle 5: Read from stack 0x0100+SP, decrement SP, set I flag\n";
echo "Cycle 6: Read reset vector low byte from 0xFFFC\n";
echo "Cycle 7: Read reset vector high byte from 0xFFFD, load PC\n\n";

echo "Expected Bus Activity Pattern:\n";
echo "------------------------------\n";

// Simulate the expected bus activity for a reset sequence
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);

// Set up memory with example values
$memory->initialize([
    0x1234 => 0xEA,  // Some instruction at current PC
    0x1235 => 0xEA,  // Next instruction
    0x01FD => 0xAA,  // Stack data
    0x01FC => 0xBB,  // Stack data
    0x01FB => 0xCC,  // Stack data
    0xFFFC => 0x00,  // Reset vector low
    0xFFFD => 0x80,  // Reset vector high -> 0x8000
]);

// Simulate the 7-cycle reset sequence
echo "ADDRESS_BUS(16-bit)    DATA_BUS(8-bit)    ADDR_HEX    R/W  DATA_HEX  CYCLE  DESCRIPTION\n";
echo "===================    ===============    ========    ===  ========  =====  ===========\n";

$currentPC = 0x1234;  // Example current PC
$sp = 0x00;          // SP starts at 0x00

// Cycle 1: Read current PC
$busMonitor->incrementCycle();
$data = $memory->read_byte($currentPC);
echo sprintf("%016b    %08b    %04X    R    %02X      1     Read PC (dummy)\n",
    $currentPC, $data, $currentPC, $data);

// Cycle 2: Read PC+1
$busMonitor->incrementCycle();
$data = $memory->read_byte($currentPC + 1);
echo sprintf("%016b    %08b    %04X    R    %02X      2     Read PC+1 (dummy)\n",
    $currentPC + 1, $data, $currentPC + 1, $data);

// Cycles 3-5: Stack reads with SP decrement
for ($cycle = 3; $cycle <= 5; $cycle++) {
    $busMonitor->incrementCycle();
    $sp = ($sp - 1) & 0xFF;  // Decrement SP
    $stackAddr = 0x0100 + $sp;
    $data = $memory->read_byte($stackAddr);
    echo sprintf("%016b    %08b    %04X    R    %02X      %d     Read stack, SP dec to 0x%02X\n",
        $stackAddr, $data, $stackAddr, $data, $cycle, $sp);
}

// Cycle 6: Reset vector low
$busMonitor->incrementCycle();
$resetLow = $memory->read_byte(0xFFFC);
echo sprintf("%016b    %08b    %04X    R    %02X      6     Read reset vector low\n",
    0xFFFC, $resetLow, 0xFFFC, $resetLow);

// Cycle 7: Reset vector high
$busMonitor->incrementCycle();
$resetHigh = $memory->read_byte(0xFFFD);
echo sprintf("%016b    %08b    %04X    R    %02X      7     Read reset vector high\n",
    0xFFFD, $resetHigh, 0xFFFD, $resetHigh);

$finalPC = ($resetHigh << 8) | $resetLow;

echo "\nReset Sequence Results:\n";
echo "-----------------------\n";
echo sprintf("New PC: 0x%04X (from reset vector 0x%02X%02X)\n", $finalPC, $resetHigh, $resetLow);
echo sprintf("Final SP: 0x%02X (decremented 3 times from 0x00)\n", $sp);
echo "Status Register: I flag set (0b00000100), other flags undefined\n";
echo "A, X, Y registers: Undefined (retain previous values)\n\n";

echo "Key Differences from Power-On:\n";
echo "------------------------------\n";
echo "- RESET doesn't clear registers A, X, Y (they retain values)\n";
echo "- RESET doesn't clear all status flags (only sets I flag)\n";
echo "- RESET takes exactly 7 cycles with specific bus activity\n";
echo "- Stack operations are dummy reads, no actual writes\n";
echo "- Only PC and SP are set to known values\n\n";

echo "Our Emulator vs Real 6502:\n";
echo "---------------------------\n";
echo "Real 6502: A,X,Y undefined, only I flag guaranteed set\n";
echo "Our Emulator: A,X,Y cleared to 0, all flags set to known state\n";
echo "Real 6502: 7-cycle reset with dummy reads\n";
echo "Our Emulator: Simplified reset just reads reset vector\n\n";

echo "Total bus operations in real reset: 7\n";
echo "Our current implementation operations: 2 (just reset vector)\n";