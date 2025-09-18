<?php

declare(strict_types=1);

namespace Emulator\Instructions;

use Emulator\CPU;
use Emulator\Opcode;
use Emulator\StatusRegister;

class FlowControl
{
  public function __construct(private CPU $cpu) {}

  // Branch Instructions
  public function beq(Opcode $opcode): int
  {
    return $this->branch($opcode, $this->cpu->status->get(StatusRegister::ZERO));
  }

  public function bne(Opcode $opcode): int
  {
    return $this->branch($opcode, !$this->cpu->status->get(StatusRegister::ZERO));
  }

  public function bcc(Opcode $opcode): int
  {
    return $this->branch($opcode, !$this->cpu->status->get(StatusRegister::CARRY));
  }

  public function bcs(Opcode $opcode): int
  {
    return $this->branch($opcode, $this->cpu->status->get(StatusRegister::CARRY));
  }

  public function bpl(Opcode $opcode): int
  {
    return $this->branch($opcode, !$this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function bmi(Opcode $opcode): int
  {
    return $this->branch($opcode, $this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function bvc(Opcode $opcode): int
  {
    return $this->branch($opcode, !$this->cpu->status->get(StatusRegister::OVERFLOW));
  }

  public function bvs(Opcode $opcode): int
  {
    return $this->branch($opcode, $this->cpu->status->get(StatusRegister::OVERFLOW));
  }

  // Jump Instructions
  public function jmp(Opcode $opcode): int
  {
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $this->cpu->pc = $address;

    return $opcode->getCycles();
  }

  public function jsr(Opcode $opcode): int
  {
    // Push return address minus 1 to stack (6502 quirk)
    $returnAddress = $this->cpu->pc + 2; // Points to last byte of JSR instruction
    $this->cpu->pushWord($returnAddress - 1);

    // Jump to subroutine
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $this->cpu->pc = $address;

    return $opcode->getCycles();
  }

  public function rts(Opcode $opcode): int
  {
    // Pull return address from stack and add 1 (6502 quirk)
    $returnAddress = $this->cpu->pullWord();
    $this->cpu->pc = $returnAddress + 1;

    return $opcode->getCycles();
  }

  // Break Instruction
  public function brk(Opcode $opcode): int
  {
    // Push PC + 2 to stack (points past BRK instruction)
    $this->cpu->pushWord($this->cpu->pc + 1);

    // Push status register with B flag set
    $status = $this->cpu->status->toInt() | (1 << StatusRegister::BREAK_COMMAND);
    $this->cpu->pushByte($status);

    // Set interrupt disable flag
    $this->cpu->status->set(StatusRegister::INTERRUPT_DISABLE, true);

    // Jump to interrupt vector
    $interruptVector = $this->cpu->getMemory()->read_word(0xFFFE);
    $this->cpu->pc = $interruptVector;

    return $opcode->getCycles();
  }

  public function rti(Opcode $opcode): int
  {
    // Pull status register from stack
    $status = $this->cpu->pullByte();
    $this->cpu->status->fromInt($status);

    // Pull program counter from stack
    $this->cpu->pc = $this->cpu->pullWord();

    return $opcode->getCycles();
  }

  // Private helper method for branch instructions
  private function branch(Opcode $opcode, bool $condition): int
  {
    $cycles = $opcode->getCycles();
    $offset = $this->cpu->getAddress($opcode->getAddressingMode());

    if ($condition) {
      $oldPC = $this->cpu->pc;

      // Apply signed offset to PC
      if ($offset & 0x80) {
        // Negative offset
        $offset -= 256;
      }
      $this->cpu->pc = ($oldPC + $offset) & 0xFFFF;

      // Add extra cycle for taken branch
      $cycles++;

      // Add extra cycle if page boundary crossed
      if (($oldPC & 0xFF00) !== ($this->cpu->pc & 0xFF00)) {
        $cycles++;
      }
    }

    return $cycles;
  }
}