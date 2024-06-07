<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Memory;

enum AddressingMode
{
  case Implied;
  case Accumulator;
  case Immediate;
  case Absolute;
  case X_Indexed_Absolute;
  case Y_Indexed_Absolute;
  case Absolute_Indirect;
  case Zero_Page;
  case X_Indexed_Zero_Page;
  case Y_Indexed_Zero_Page;
  case X_Indexed_Zero_Page_Indirect;
  case Zero_Page_Indirect_Y_Indexed;
  case Relative;
  case Unknown;
}

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

  function addressing_mode($operand)
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

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{2})$/', $operand)) {
      return AddressingMode::Zero_Page;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{2}),X$/', $operand)) {
      return AddressingMode::X_Indexed_Zero_Page;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{4})$/', $operand)) {
      return AddressingMode::Absolute;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{4}),X$/', $operand)) {
      return AddressingMode::X_Indexed_Absolute;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{4}),Y$/', $operand)) {
      return AddressingMode::Y_Indexed_Absolute;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{2}),X\s*\($/', $operand)) {
      return AddressingMode::X_Indexed_Zero_Page_Indirect;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{2})\s*\(\s*,Y$/', $operand)) {
      return AddressingMode::Zero_Page_Indirect_Y_Indexed;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{4})\s*\($/', $operand)) {
      return AddressingMode::Absolute_Indirect;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$(\w{1,2})$/', $operand)) {
      return AddressingMode::Relative;
    }

    if (strpos($operand, '$') === 0 && preg_match('/^\$([0-9A-Fa-f]{2}),Y$/', $operand)) {
      return AddressingMode::Y_Indexed_Zero_Page;
    }

    return AddressingMode::Unknown;
  }
}
