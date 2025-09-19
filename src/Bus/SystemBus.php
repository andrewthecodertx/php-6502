<?php

declare(strict_types=1);

namespace Emulator\Bus;

use Emulator\Memory;

class SystemBus implements BusInterface
{
  private Memory $memory;
  private array $peripherals = [];

  public function __construct(Memory $memory)
  {
    $this->memory = $memory;
  }

  public function addPeripheral(PeripheralInterface $peripheral): void
  {
    $this->peripherals[] = $peripheral;
  }

  public function read(int $address): int
  {
    $address = $address & 0xFFFF;

    // Check if any peripheral handles this address
    foreach ($this->peripherals as $peripheral) {
      if ($peripheral->handlesAddress($address)) {
        return $peripheral->read($address);
      }
    }

    // Default to memory
    return $this->memory->read_byte($address);
  }

  public function write(int $address, int $value): void
  {
    $address = $address & 0xFFFF;
    $value = $value & 0xFF;

    // Check if any peripheral handles this address
    foreach ($this->peripherals as $peripheral) {
      if ($peripheral->handlesAddress($address)) {
        $peripheral->write($address, $value);
        return;
      }
    }

    // Default to memory
    $this->memory->write_byte($address, $value);
  }

  public function tick(): void
  {
    // Update all peripherals each CPU cycle
    foreach ($this->peripherals as $peripheral) {
      $peripheral->tick();
    }
  }

  public function getMemory(): Memory
  {
    return $this->memory;
  }
}