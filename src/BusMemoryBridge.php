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

  public function readByte(int $addr): int
  {
  return $this->bus->read($addr);
  }

  public function writeByte(int $addr, int $value): void
  {
  $this->bus->write($addr, $value);
  }
}
