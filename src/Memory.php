<?php

declare(strict_types=1);

namespace Emulator;

class Memory
{
  private const ZERO_PAGE_START = 0x0000;
  private const ZERO_PAGE_END = 0x00FF;

  private const STACK_START = 0x0100;
  private const STACK_END = 0x01FF;

  private const FREE_MEMORY_START = 0x0200;
  private const FREE_MEMORY_END = 0xFFFF;

  /** @var array<int, int> */
  private array $memory = [];

  public function __construct() {}

  public function initialize(array $rom = []): void
  {

    foreach ($rom as $addr => $value) {
      $this->memory[$addr] = $value & 0xFF;
    }
  }

  public function readByte(int $addr): int
  {
    $addr = $addr & 0xFFFF;
    if ($addr >= self::ZERO_PAGE_START && $addr <= self::FREE_MEMORY_END) {
      return $this->memory[$addr] ?? 0;
    }



    return 0;
  }

  public function writeByte(int $addr, int $value): void
  {
    $addr = $addr & 0xFFFF;
    if ($addr >= self::ZERO_PAGE_START && $addr <= self::FREE_MEMORY_END) {
      $this->memory[$addr] = $value & 0xFF;
    }
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


  public function push(int $value, &$stack_pointer): void
  {
    $this->writeByte(self::STACK_START + $stack_pointer--, $value);
  }

  public function pop(&$stack_pointer): int
  {
    return $this->readByte(self::STACK_START + ++$stack_pointer);
  }
}
