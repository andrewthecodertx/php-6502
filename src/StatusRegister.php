<?php

declare(strict_types=1);

namespace Emulator;

class StatusRegister
{
  // Flag positions
  public const CARRY = 0;
  public const ZERO = 1;
  public const INTERRUPT_DISABLE = 2;
  public const DECIMAL_MODE = 3;
  public const BREAK_COMMAND = 4;
  public const OVERFLOW = 5;
  public const NEGATIVE = 7;

  private int $flags = 0b00100000; // Default state

  public function set(int $flag, bool $value): void
  {
    if ($value) {
      $this->flags |= (1 << $flag);
    } else {
      $this->flags &= ~(1 << $flag);
    }
  }

  public function get(int $flag): bool
  {
    return ($this->flags & (1 << $flag)) !== 0;
  }

  public function toInt(): int
  {
    return $this->flags;
  }

  public function fromInt(int $value): void
  {
    $this->flags = $value;
  }
}
