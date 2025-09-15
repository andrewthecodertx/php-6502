<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Instructions\LoadStore;

class CPU
{
  public int $pc = 0;
  public int $sp = 0;
  public int $accumulator = 0;
  public int $register_x = 0;
  public int $register_y = 0;
  public StatusRegister $status;
  public int $cycles = 0;

  private InstructionRegister $instructionRegister;
  private array $instructionHandlers = [];

  private LoadStore $loadStoreHandler;

  public function __construct(private Memory $memory)
  {
    $this->status = new StatusRegister();
    $this->instructionRegister = new InstructionRegister();
    $this->loadStoreHandler = new LoadStore($this);
    $this->initializeInstructionHandlers();
    $this->reset();
  }

  public function clock(): void
  {
    $this->cycles--;
  }

  public function run(): void
  {
    while (true) {
      $this->step();
    }
  }

  public function step(): void
  {
    if ($this->cycles === 0) {
      $opcode = $this->memory->read_byte($this->pc);
      $this->pc++;

      $opcodeData = $this->instructionRegister->getOpcode(sprintf('0x%02X', $opcode));

      if (!$opcodeData) {
        throw new \InvalidArgumentException(sprintf("Unknown opcode: 0x%02X", $opcode));
      }

      $mnemonic = $opcodeData->getMnemonic();

      if (!isset($this->instructionHandlers[$mnemonic])) {
        throw new \RuntimeException("Instruction {$mnemonic} not implemented");
      }

      $handler = $this->instructionHandlers[$mnemonic];
      $this->cycles += $handler($opcodeData);
    }
    $this->clock();
  }

  public function reset(): void
  {
    $this->pc = $this->memory->read_word(0xFFFC);
    $this->sp = 0x0100;
    $this->accumulator = 0;
    $this->register_x = 0;
    $this->register_y = 0;
    $this->status->fromInt(0b00100100);
    $this->cycles = 0;
  }

  private function initializeInstructionHandlers(): void
  {
    $this->instructionHandlers = [
      'LDA' => fn(Opcode $opcode) => $this->loadStoreHandler->lda($opcode),
      'STA' => fn(Opcode $opcode) => $this->loadStoreHandler->sta($opcode),
    ];
  }

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

  public function getAddress(string $addressingMode): int
  {
    return match ($addressingMode) {
      'Immediate' => $this->immediate(),
      'ZeroPage' => $this->zeroPage(),
      'ZeroPage,X' => $this->zeroPageX(),
      'Absolute' => $this->absolute(),
      'Absolute,X' => $this->absoluteX(),
      'Absolute,Y' => $this->absoluteY(),
      '(Indirect,X)' => $this->indirectX(),
      '(Indirect),Y' => $this->indirectY(),
      default => throw new \InvalidArgumentException("Invalid addressing mode: {$addressingMode}"),
    };
  }

  private function immediate(): int
  {
    $this->pc++;
    return $this->pc - 1;
  }

  private function zeroPage(): int
  {
    $address = $this->memory->read_byte($this->pc);
    $this->pc++;
    return $address;
  }

  private function zeroPageX(): int
  {
    $address = $this->memory->read_byte($this->pc) + $this->register_x;
    $this->pc++;
    return $address & 0xFF;
  }

  private function absolute(): int
  {
    $low = $this->memory->read_byte($this->pc);
    $this->pc++;
    $high = $this->memory->read_byte($this->pc);
    $this->pc++;
    return ($high << 8) | $low;
  }

  private function absoluteX(): int
  {
    $low = $this->memory->read_byte($this->pc);
    $this->pc++;
    $high = $this->memory->read_byte($this->pc);
    $this->pc++;
    $address = (($high << 8) | $low) + $this->register_x;
    return $address;
  }

  private function absoluteY(): int
  {
    $low = $this->memory->read_byte($this->pc);
    $this->pc++;
    $high = $this->memory->read_byte($this->pc);
    $this->pc++;
    $address = (($high << 8) | $low) + $this->register_y;
    return $address;
  }

  private function indirectX(): int
  {
    $zeroPageAddress = $this->memory->read_byte($this->pc) + $this->register_x;
    $this->pc++;
    $low = $this->memory->read_byte($zeroPageAddress & 0xFF);
    $high = $this->memory->read_byte(($zeroPageAddress + 1) & 0xFF);
    return ($high << 8) | $low;
  }

  private function indirectY(): int
  {
    $zeroPageAddress = $this->memory->read_byte($this->pc);
    $this->pc++;
    $low = $this->memory->read_byte($zeroPageAddress & 0xFF);
    $high = $this->memory->read_byte(($zeroPageAddress + 1) & 0xFF);
    $address = (($high << 8) | $low) + $this->register_y;
    return $address;
  }
}
