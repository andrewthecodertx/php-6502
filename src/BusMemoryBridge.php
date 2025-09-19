<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Bus\BusInterface;

class BusMemoryBridge extends Memory
{
  private BusInterface $bus;

  public function __construct(BusInterface $bus)
  {
  $this->bus = $bus;
  parent::__construct();
  }

  public function read_byte(int $addr): int
  {
  return $this->bus->read($addr);
  }

  public function write_byte(int $addr, int $value): void
  {
  $this->bus->write($addr, $value);
  }
}
