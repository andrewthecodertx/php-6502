<?php

declare(strict_types=1);

namespace Emulator\Instructions;

use Emulator\CPU;
use Emulator\Opcode;
use Emulator\StatusRegister;

class LoadStore
{
  public function __construct(private CPU $cpu) {}

  public function lda(Opcode $opcode): int
  {
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $value = $this->cpu->getMemory()->readByte($address);
    $this->cpu->setAccumulator($value);

    $this->cpu->status->set(StatusRegister::ZERO, $value === 0);
    $this->cpu->status->set(StatusRegister::NEGATIVE, ($value & 0x80) !== 0);

    return $opcode->getCycles();
  }

  public function sta(Opcode $opcode): int
  {
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $this->cpu->getMemory()->writeByte($address, $this->cpu->getAccumulator());

    return $opcode->getCycles();
  }

  public function ldx(Opcode $opcode): int
  {
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $value = $this->cpu->getMemory()->readByte($address);
    $this->cpu->setRegisterX($value);

    $this->cpu->status->set(StatusRegister::ZERO, $value === 0);
    $this->cpu->status->set(StatusRegister::NEGATIVE, ($value & 0x80) !== 0);

    return $opcode->getCycles();
  }

  public function ldy(Opcode $opcode): int
  {
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $value = $this->cpu->getMemory()->readByte($address);
    $this->cpu->setRegisterY($value);

    $this->cpu->status->set(StatusRegister::ZERO, $value === 0);
    $this->cpu->status->set(StatusRegister::NEGATIVE, ($value & 0x80) !== 0);

    return $opcode->getCycles();
  }

  public function stx(Opcode $opcode): int
  {
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $this->cpu->getMemory()->writeByte($address, $this->cpu->getRegisterX());

    return $opcode->getCycles();
  }

  public function sty(Opcode $opcode): int
  {
    $address = $this->cpu->getAddress($opcode->getAddressingMode());
    $this->cpu->getMemory()->writeByte($address, $this->cpu->getRegisterY());

    return $opcode->getCycles();
  }
}
