# 6502 Emulator

This project aims to emulate the behavior of the MOS 6502 microprocessor,
commonly used in vintage computers like the Apple II, Commodore 64, and the
Atari 2600.

## Overview

The emulator includes:

- **CPU Emulation**: Simulates the 6502's instruction set, registers, and flag
behavior.
- **Memory Management**: Implements a 64KB address space with basic read/write
operations.
- **Instruction Handling**: Supports various addressing modes for instructions,
with a focus on accuracy and efficiency.

### Features

- **Instruction Execution**: Emulates individual instructions with their
respective addressing modes, cycles, and byte usage.
- **Register and Flag Manipulation**: Full control over the CPU's registers
(A, X, Y, SP, PC) and status flags (N, V, B, D, I, Z, C).
- **Memory Model**: A simple but extensible model of memory, with potential
for expanding to handle specific hardware configurations.
- **JSON Opcode Database**: Instructions are loaded from a JSON file, allowing
for easy updates and additions to the instruction set.

### Setup

#### Prerequisites

- PHP 8.0 or higher
- Composer for dependency management

#### Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/yourusername/php-6502.git
   cd php-6502

2. Install using Composer for autoloading:

   ```bash
   composer install

3. Ensure you have the `opcodes.json` file in the project root or update the path in the `CPU` class where instructions are loaded.

### Usage

To run the emulator:

```bash
php index.php
```

This will initialize the CPU, reset it, and display the initial state of the
registers and flags. You can manually invoke instructions through the `CPU`
instance in `index.php`.

#### Example

Here is how you might use the emulator:

```php
<?php
// in index.php
use Emulator\Memory;
use Emulator\CPU;

$memory = new Memory();
$cpu = new CPU($memory);

$cpu->reset();
echo $cpu->getRegistersState() . "\n";
echo $cpu->getFlagsState() . "\n";

// Execute an instruction
$cpu->execute('LDA', '#$01'); // Load immediate value 1 into accumulator
echo $cpu->getRegistersState() . "\n"; // Display updated state
echo $cpu->getFlagsState() . "\n"; // Check flag changes
```
