<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Memory;
use Emulator\AddressingMode;

class CPU
{
  private int $program_counter; // 16bit
  private int $stack_pointer;   // 8bit in function but stored in 0x0100-0x01FF
  private int $accumulator, $register_x, $register_y; // 8bit

  private int $processor_status = 0; // 8bit flags
  private const C_FLAG = 0x01;
  private const Z_FLAG = 0x02;
  private const I_FLAG = 0x04;
  private const D_FLAG = 0x08;
  private const B_FLAG = 0x10;  // Break command
  private const U_FLAG = 0x20;  // Unused, always 1
  private const V_FLAG = 0x40;
  private const N_FLAG = 0x80;


  public function __construct(private Memory $memory)
  {
    $this->reset();
  }

  public function reset(): void
  {
    $this->program_counter = $this->memory->read_word(0xFFFC);
    $this->stack_pointer = 0x00FF;
    $this->processor_status = self::U_FLAG; // Reset all flags to 0, except U which is always set
    $this->accumulator = 0; // Reset registers
    $this->register_x = 0;
    $this->register_y = 0;
  }

  private function setFlag(int $flag): void
  {
    $this->processor_status |= $flag;
  }

  private function clearFlag(int $flag): void
  {
    $this->processor_status &= ~$flag;
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

  private function addressing_mode(string $operand): AddressingMode
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
