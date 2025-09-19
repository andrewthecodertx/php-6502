# MOS 6502 Microprocessor Emulator

A hardware-accurate PHP implementation of the MOS 6502 microprocessor, the
legendary 8-bit CPU that powered iconic systems like the Apple II, Commodore 64,
Atari 2600, and Nintendo Entertainment System.

## Features

### 🎯 Hardware Accuracy

- **Cycle-accurate 7-cycle reset sequence** with proper dummy reads
- **Correct status register bit positions** per MOS 6502 specification
- **Proper 8-bit stack pointer** implementation (0x00-0xFF)
- **All 13 addressing modes** including the famous page boundary bug
- **Both emulator-friendly and hardware-accurate** reset behaviors

### 🔧 Core Components

- **CPU Emulation**: Complete 6502 instruction set with accurate timing
- **Memory Management**: Full 64KB address space (0x0000-0xFFFF)
- **Bus Monitoring**: Real-time tracking of address/data bus activity
- **Status Register**: Proper flag handling (N, V, -, B, D, I, Z, C)
- **Stack Operations**: Hardware-accurate stack pointer and operations

### 📊 Bus Monitoring System

- **Complete bus activity logging** (address, data, read/write operations)
- **Cycle-by-cycle execution tracking**
- **Reset sequence visualization**
- **Memory access pattern analysis**

### 🧪 Comprehensive Testing

- **73 test cases** with 1,167 assertions
- **100% test coverage** of core functionality
- **Hardware compliance verification**
- **Integration testing** with bus monitoring

## Architecture

### Memory Layout

```
0x0000-0x00FF  Zero Page (fast access)
0x0100-0x01FF  Stack Page (SP points here)
0x0200-0xFFEF  General Memory
0xFFFA-0xFFFB  NMI Vector
0xFFFC-0xFFFD  Reset Vector
0xFFFE-0xFFFF  IRQ/BRK Vector
```

### Addressing Modes (All 13)

- Immediate: `#$42`
- Zero Page: `$80`
- X-Indexed Zero Page: `$80,X`
- Y-Indexed Zero Page: `$80,Y`
- Absolute: `$1234`
- X-Indexed Absolute: `$1234,X`
- Y-Indexed Absolute: `$1234,Y`
- X-Indexed Zero Page Indirect: `($80,X)`
- Zero Page Indirect Y-Indexed: `($80),Y`
- Absolute Indirect: `($1234)` (with page boundary bug)
- Relative: `BEQ $+5`
- Implied: `NOP`
- Accumulator: `ROL A`

## Installation

### Prerequisites

- PHP 8.0 or higher
- Composer for dependency management

### Setup

```bash
git clone https://github.com/andrewthecoder/php-6502.git
cd php-6502
composer install
```

## 6502 Assembler

### Assembly Language Programming

The emulator includes a complete 6502 assembler for writing programs in assembly language instead of hand-coding opcodes.

```bash
# Assemble source to binary
php asm.php assemble program.asm program.bin

# Assemble and run program directly
php asm.php run program.asm

# Disassemble binary back to assembly
php asm.php disasm program.bin 0x8000

# Show label table from assembly
php asm.php labels program.asm
```

### Assembly Language Features

- **All 6502 addressing modes**: Immediate, Zero Page, Absolute, Indexed, Indirect
- **Labels and references**: `main:`, `JMP main`, `BEQ loop`
- **Data directives**: `.BYTE "Hello"`, `.WORD $8000`
- **Origin directive**: `* = $8000`
- **String literals**: `.BYTE "Hello, World!", $0A, $00`
- **Comments**: `; This is a comment`

### Example Assembly Program

```assembly
; Simple Hello World program
* = $8000

main:
    LDA #$01        ; Clear screen command
    STA $C3EC       ; Send to display control register

    LDX #$00        ; Initialize string index

print_loop:
    LDA message,X   ; Load character from message
    BEQ done        ; If zero, we're done
    STA $D000       ; Output to console
    INX             ; Next character
    JMP print_loop  ; Continue loop

done:
    JMP done        ; Infinite loop (halt)

message:
    .BYTE "Hello, World!", $0A, $00

; Set reset vector
* = $FFFC
.WORD main
```

## Usage

### Basic CPU Operation

```php
<?php
require_once 'vendor/autoload.php';

use Emulator\CPU;
use Emulator\Memory;

// Create basic system
$memory = new Memory();
$cpu = new CPU($memory);

// Set up reset vector
$memory->write_byte(0xFFFC, 0x00); // Reset vector low
$memory->write_byte(0xFFFD, 0x80); // Reset vector high -> 0x8000

// Place some code at 0x8000
$memory->write_byte(0x8000, 0xA9); // LDA #$42
$memory->write_byte(0x8001, 0x42);

// Reset and execute
$cpu->reset();
echo sprintf("PC: 0x%04X, SP: 0x%02X\n", $cpu->pc, $cpu->sp);
```

### Bus Monitoring System

```php
<?php
require_once 'vendor/autoload.php';

use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;

// Create monitored system
$busMonitor = new BusMonitor();
$memory = new MonitoredMemory($busMonitor);
$cpu = new MonitoredCPU($memory);

// Set up and reset
$memory->write_byte(0xFFFC, 0x00);
$memory->write_byte(0xFFFD, 0x80);

$busMonitor->reset();
$cpu->reset(); // Generates exactly 7 bus operations

// Display bus activity
$activity = $busMonitor->getBusActivity();
foreach ($activity as $i => $op) {
    printf("Cycle %d: %04X %s %02X\n",
        $i + 1, $op['address'], $op['operation'], $op['data']);
}
```

## Demo Programs

### 🎮 Assembly-Based Interactive Demos

- **`interactive_demo.php`** - **Interactive menu to choose and run assembly programs**
  - Select from multiple demo programs
  - Each demo written in 6502 assembly language
  - Real-time assembly and execution

- **`auto_demo_asm.php`** - **Automatic showcase using assembly language**
  - Demonstrates all system features automatically
  - Written in pure 6502 assembly (examples/showcase.asm)
  - Shows assembly programming capabilities

### Available Assembly Programs

- **`examples/hello.asm`** - Classic Hello World with string output
- **`examples/welcome.asm`** - Colorful welcome message demonstration
- **`examples/colors.asm`** - Cycles through all 16 text colors
- **`examples/sound.asm`** - Plays melodies on multiple audio channels
- **`examples/counter.asm`** - Live counting display from 0 to 99
- **`examples/showcase.asm`** - Complete system feature demonstration

### Running Demos

```bash
# Run interactive demo menu (choose any program)
php interactive_demo.php

# Run automatic assembly-based showcase
php auto_demo_asm.php

# Run specific assembly programs directly
php asm.php run examples/hello.asm
php asm.php run examples/colors.asm
```

**Features Demonstrated:**
- ✅ MOS 6502 CPU with all instructions implemented
- ✅ 64KB memory space with memory-mapped I/O
- ✅ 40x25 text display with ANSI color support
- ✅ 4-channel sound controller
- ✅ Modular bus architecture ready for NES development

## Testing

Run the comprehensive test suite:

```bash
# Run all tests
vendor/bin/phpunit tests/

# Run specific test categories
vendor/bin/phpunit tests/CPUTest.php
vendor/bin/phpunit tests/BusMonitorTest.php
vendor/bin/phpunit tests/IntegrationTest.php
```

### Test Coverage

- **CPUTest**: 25 tests covering registers, addressing modes, reset sequences
- **MemoryTest**: 17 tests covering memory operations and constraints
- **StatusRegisterTest**: 17 tests covering 6502-accurate flag behavior
- **BusMonitorTest**: 13 tests covering bus monitoring and cycle tracking
- **IntegrationTest**: 4 tests covering complete system integration

## 6502 Compliance

This emulator prioritizes hardware accuracy:

### ✅ Implemented Correctly

- 7-cycle reset sequence with dummy reads
- Status register bit positions (V at bit 6, not 5)
- 8-bit stack pointer (0x00-0xFF)
- Stack operations in page 1 (0x0100-0x01FF)
- Page boundary bug in JMP ($xxFF)
- Little-endian memory storage
- All 13 addressing modes

### ⚠️ Emulator Differences

- **Standard reset**: Clears A,X,Y registers (real 6502: undefined)
- **Accurate reset**: Available via `accurateReset()` method
- **Instruction timing**: Simplified for basic emulation

### 🔧 Reset Behaviors

- **`reset()`**: Emulator-friendly (clears registers)
- **`accurateReset()`**: Hardware-accurate (preserves A,X,Y)

## Development

### Project Structure

```
src/
├── CPU.php              # Main CPU implementation
├── Memory.php           # 64KB memory system
├── StatusRegister.php   # 6502-accurate status flags
├── BusMonitor.php       # Bus activity monitoring
├── MonitoredMemory.php  # Memory with bus logging
├── MonitoredCPU.php     # CPU with verbose output
├── InstructionRegister.php # Opcode management
└── Instructions/        # Instruction implementations
    └── LoadStore.php    # LDA, STA instructions

tests/
├── CPUTest.php          # CPU functionality tests
├── MemoryTest.php       # Memory system tests
├── StatusRegisterTest.php # Status register tests
├── BusMonitorTest.php   # Bus monitoring tests
└── IntegrationTest.php  # End-to-end integration tests
```

### Adding Instructions

1. Add opcode definition to `InstructionRegister.php`
2. Implement handler in appropriate `Instructions/` class
3. Register handler in `CPU::initializeInstructionHandlers()`
4. Add tests covering the new instruction

## License

MIT License - see LICENSE file for details.

## References

- [MOS 6502 Programming Manual](http://archive.6502.org/books/mcs6500_family_programming_manual.pdf)
- [6502 Instruction Set](http://www.6502.org/tutorials/6502opcodes.html)
- [Visual 6502](http://www.visual6502.org/) - Transistor-level simulation
- [6502.org](http://www.6502.org/) - The definitive 6502 resource

---

*Built with attention to hardware accuracy and educational value. Perfect for
understanding how the 6502 microprocessor works at the bus level.*

