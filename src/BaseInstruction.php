<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\CPU;
use Emulator\Memory;
use Emulator\AddressingMode;

abstract class BaseInstruction
{
  protected CPU $cpu;

  public function __construct(CPU $cpu)
  {
    $this->cpu = $cpu;
  }

  /**
   * Updates the CPU flags based on the given value
   *
   * @param int $value The value to check for flag updates
   */
  protected function updateFlags(int $value): void
  {
    $this->cpu->setFlagState(CPU::Z_FLAG, $value == 0);
    $this->cpu->setFlagState(CPU::N_FLAG, ($value & 0x80) !== 0);
  }

  /**
   * Checks if a page boundary has been crossed
   *
   * @param int $old_addr Old address
   * @param int $new_addr New address
   * @return bool Returns true if the page was crossed, false otherwise
   */
  protected function pageCrossed(int $old_addr, int $new_addr): bool
  {
    return ($old_addr & 0xFF00) !== ($new_addr & 0xFF00);
  }

  /**
   * Abstract method to execute the instruction
   *
   * @param string $operand The instruction's operand
   * @param AddressingMode $addressing_mode The addressing mode for this instruction
   * @return array Information about cycle and byte usage
   */
  abstract public function execute(string $operand, AddressingMode $addressing_mode): array;
}
