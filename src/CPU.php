<?php

declare(strict_types=1);

namespace Emulator;

class CPU
{
  private InstructionRegistry $instructionRegistry;
  private array $instructionHandlers = [];

  public function __construct(private Memory $memory)
  {
    $this->instructionRegistry = new InstructionRegistry();
    $this->initializeInstructionHandlers();
    $this->reset();
  }

  private function initializeInstructionHandlers(): void
  {
    // Map mnemonics to their handler classes
    $this->instructionHandlers = [
      'LDA' => new LDAInstruction($this, $this->instructionRegistry),
      // Add other instructions as they're implemented:
      // 'STA' => new STAInstruction($this, $this->instructionRegistry),
      // 'ADC' => new ADCInstruction($this, $this->instructionRegistry),
      // etc.
    ];
  }

  public function execute(string $opcode = 'NOP', ?string $operand = null): void
  {
    $opcodeHex = '0x' . ltrim($opcode, '0x');

    // Get instruction data from registry
    $opcodeData = $this->instructionRegistry->getOpcode($opcodeHex);

    if (!$opcodeData) {
      throw new \InvalidArgumentException("Unknown opcode: {$opcodeHex}");
    }

    $mnemonic = $opcodeData->getMnemonic();

    // Get the instruction handler
    if (!isset($this->instructionHandlers[$mnemonic])) {
      throw new \RuntimeException("Instruction {$mnemonic} not implemented");
    }

    // Get addressing mode and execute
    $addressing_mode = $this->addressing_mode($operand ?? '');
    $handler = $this->instructionHandlers[$mnemonic];

    try {
      $result = $handler->execute($operand ?? '', $addressing_mode);
      printf(
        "%s executed. Cycles: %d, Bytes: %d\n",
        $mnemonic,
        $result['cycles'],
        $result['bytes']
      );
    } catch (\Exception $e) {
      printf("Error executing %s: %s\n", $mnemonic, $e->getMessage());
    }
  }

  // Add getters/setters for registers that the instruction handlers will need
  public function getAccumulator(): int
  {
    return $this->accumulator;
  }

  public function setAccumulator(int $value): void
  {
    $this->accumulator = $value & 0xFF;
  }

  public function getRegisterX(): int
  {
    return $this->register_x;
  }

  public function setRegisterX(int $value): void
  {
    $this->register_x = $value & 0xFF;
  }

  public function getRegisterY(): int
  {
    return $this->register_y;
  }

  public function setRegisterY(int $value): void
  {
    $this->register_y = $value & 0xFF;
  }

  public function getMemory(): Memory
  {
    return $this->memory;
  }

  // ... rest of the CPU class implementation ...
}
