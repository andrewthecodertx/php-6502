<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Memory;

class CPU
{
  private int $program_counter; // 16bit
  private int $stack_pointer;   // 16bit
  private int $accumulator, $register_x, $register_y; // 8bit
  private $processor_status = ['C', 'Z', 'I', 'D', 'B', 'U', 'V', 'N'];

  private Memory $memory;

  public function __construct()
  {
    $this->reset();
  }

  public function reset(): void
  {
    $this->program_counter = 0xFFFC;
    $this->stack_pointer = 0x00FF;
    $this->processor_status['D'] = 0;
    $this->processor_status['C'] = 0;
  }

  /**
   * if nothing is sent to the CPU,
   * we execute NOP instruction
   */
  public function execute(string $opcode = 'NOP', string $operand = null): void
  {
    $addressing_mode = $this->addressing_mode($operand);
    printf($addressing_mode->name);
  }

  private function addressing_mode($operand)
  {
    if (empty($operand)) {
      return AddressingMode::Implied;
    }

    $operand = trim($operand, '"\'');

    if (strpos($operand, 'A') === 0) {
      return AddressingMode::Accumulator;
    }

    if (strpos($operand, '#') === 0) {
      return AddressingMode::Immediate;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{2}$/i', $operand)) {
      return AddressingMode::Zero_Page;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{2},X$/i', $operand)) {
      return AddressingMode::X_Indexed_Zero_Page;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{4}$/i', $operand)) {
      return AddressingMode::Absolute;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{4},X$/i', $operand)) {
      return AddressingMode::X_Indexed_Absolute;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{4},Y$/i', $operand)) {
      return AddressingMode::Y_Indexed_Absolute;
    }

    if (preg_match('/^\\(\\$[0-9A-Fa-f]{2},X\\)$/i', $operand)) {
      return AddressingMode::X_Indexed_Zero_Page_Indirect;
    }

    if (preg_match('/^\\(\\[0-9A-Fa-f]{2}\\),Y$/i', $operand)) {
      return AddressingMode::Zero_Page_Indirect_Y_Indexed;
    }

    if (preg_match('/^\\(\\$[0-9A-Fa-f]{4}\\)$/i', $operand)) {
      return AddressingMode::Absolute_Indirect;
    }

    /** only if branch instruction! */
    if (preg_match('/^\\$[0-9A-Fa-f]{4}$/i', $operand)) {
      return AddressingMode::Relative;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{2},Y$/i', $operand)) {
      return AddressingMode::Y_Indexed_Zero_Page;
    }

    return AddressingMode::Unknown;
  }
}
