<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Bus\SystemBus;
use Emulator\Bus\BusInterface;

class EnhancedCPU extends CPU
{
  private BusInterface $bus;

  public function __construct(BusInterface $bus)
  {
    $this->bus = $bus;

    // Create a bridge memory that uses the bus
    $bridgeMemory = new BusMemoryBridge($bus);
    parent::__construct($bridgeMemory);
  }

  public function step(): void
  {
    parent::step();

    // Tick the bus for peripheral updates
    $this->bus->tick();
  }

  public function getBus(): BusInterface
  {
    return $this->bus;
  }
}

// Bridge class to make BusInterface work with existing CPU Memory expectations
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