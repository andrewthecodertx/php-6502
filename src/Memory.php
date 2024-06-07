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

  private $memory = array();

  public function __construct()
  {
    $this->initialize();
  }

  private function initialize(): void
  {
    for ($addr = 0; $addr <= 0xFFFF; $addr++) {
      $this->memory[$addr] = 0;
    }
  }

  public function read_byte(int $addr): int
  {
    return $this->memory[$addr];
  }

  public function write_byte(int $addr, int $value): void
  {
    $this->memory[$addr] = $value;
  }

  public function read_word($addr): int
  {
    return ($this->read_byte($addr) << 8) | $this->read_byte($addr + 1);
  }

  public function write_word($addr, $value): void
  {
    $this->write_byte($addr, ($value >> 8) & 0xFF);
    $this->write_byte($addr + 1, $value & 0xFF);
  }
}
