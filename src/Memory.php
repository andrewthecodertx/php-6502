<?php

declare(strict_types=1);

namespace Emulator;

class Memory
{
  private const STACK_START = 0x0100;

  /** @var array<int, int> */
  private array $memory = [];

  public function __construct() {}

  /** @param array<int, int> $rom */
  public function initialize(array $rom = []): void
  {

    foreach ($rom as $addr => $value) {
      $this->memory[$addr] = $value & 0xFF;
    }
  }

  public function readByte(int $addr): int
  {
    $addr = $addr & 0xFFFF;
    return $this->memory[$addr] ?? 0;
  }

  public function writeByte(int $addr, int $value): void
  {
    $addr = $addr & 0xFFFF;
    $this->memory[$addr] = $value & 0xFF;
  }

  public function readWord(int $addr): int
  {
    return ($this->readByte($addr + 1) << 8) | $this->readByte($addr);
  }

  public function writeWord(int $addr, int $value): void
  {
    $this->writeByte($addr, $value & 0xFF);
    $this->writeByte($addr + 1, ($value >> 8) & 0xFF);
  }


  public function push(int $value, int &$stack_pointer): void
  {
    $this->writeByte(self::STACK_START + $stack_pointer--, $value);
  }

  public function pop(int &$stack_pointer): int
  {
    return $this->readByte(self::STACK_START + ++$stack_pointer);
  }
}
