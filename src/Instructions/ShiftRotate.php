<?php

declare(strict_types=1);

namespace Emulator\Instructions;

use Emulator\CPU;
use Emulator\Opcode;
use Emulator\StatusRegister;

class ShiftRotate
{
  public function __construct(private CPU $cpu) {}

  public function asl(Opcode $opcode): int
  {
    $addressingMode = $opcode->getAddressingMode();

    if ($addressingMode === 'Accumulator') {
      $value = $this->cpu->getAccumulator();
      $result = $value << 1;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x80) !== 0);
      $this->cpu->setAccumulator($result & 0xFF);
    } else {
      $address = $this->cpu->getAddress($addressingMode);
      $value = $this->cpu->getMemory()->read_byte($address);
      $result = $value << 1;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x80) !== 0);
      $this->cpu->getMemory()->write_byte($address, $result & 0xFF);
    }

    // Set flags
    $this->cpu->status->set(StatusRegister::ZERO, ($result & 0xFF) === 0);
    $this->cpu->status->set(StatusRegister::NEGATIVE, ($result & 0x80) !== 0);

    return $opcode->getCycles();
  }

  public function lsr(Opcode $opcode): int
  {
    $addressingMode = $opcode->getAddressingMode();

    if ($addressingMode === 'Accumulator') {
      $value = $this->cpu->getAccumulator();
      $result = $value >> 1;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x01) !== 0);
      $this->cpu->setAccumulator($result);
    } else {
      $address = $this->cpu->getAddress($addressingMode);
      $value = $this->cpu->getMemory()->read_byte($address);
      $result = $value >> 1;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x01) !== 0);
      $this->cpu->getMemory()->write_byte($address, $result);
    }

    // Set flags
    $this->cpu->status->set(StatusRegister::ZERO, $result === 0);
    $this->cpu->status->set(StatusRegister::NEGATIVE, false); // MSB always 0 after LSR

    return $opcode->getCycles();
  }

  public function rol(Opcode $opcode): int
  {
    $addressingMode = $opcode->getAddressingMode();
    $carry = $this->cpu->status->get(StatusRegister::CARRY) ? 1 : 0;

    if ($addressingMode === 'Accumulator') {
      $value = $this->cpu->getAccumulator();
      $result = ($value << 1) | $carry;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x80) !== 0);
      $this->cpu->setAccumulator($result & 0xFF);
    } else {
      $address = $this->cpu->getAddress($addressingMode);
      $value = $this->cpu->getMemory()->read_byte($address);
      $result = ($value << 1) | $carry;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x80) !== 0);
      $this->cpu->getMemory()->write_byte($address, $result & 0xFF);
    }

    // Set flags
    $this->cpu->status->set(StatusRegister::ZERO, ($result & 0xFF) === 0);
    $this->cpu->status->set(StatusRegister::NEGATIVE, ($result & 0x80) !== 0);

    return $opcode->getCycles();
  }

  public function ror(Opcode $opcode): int
  {
    $addressingMode = $opcode->getAddressingMode();
    $carry = $this->cpu->status->get(StatusRegister::CARRY) ? 0x80 : 0;

    if ($addressingMode === 'Accumulator') {
      $value = $this->cpu->getAccumulator();
      $result = ($value >> 1) | $carry;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x01) !== 0);
      $this->cpu->setAccumulator($result);
    } else {
      $address = $this->cpu->getAddress($addressingMode);
      $value = $this->cpu->getMemory()->read_byte($address);
      $result = ($value >> 1) | $carry;

      $this->cpu->status->set(StatusRegister::CARRY, ($value & 0x01) !== 0);
      $this->cpu->getMemory()->write_byte($address, $result);
    }

    // Set flags
    $this->cpu->status->set(StatusRegister::ZERO, $result === 0);
    $this->cpu->status->set(StatusRegister::NEGATIVE, ($result & 0x80) !== 0);

    return $opcode->getCycles();
  }
}