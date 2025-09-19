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


    foreach ($this->peripherals as $peripheral) {
      if ($peripheral->handlesAddress($address)) {
        return $peripheral->read($address);
      }
    }


    return $this->memory->read_byte($address);
  }

  public function write(int $address, int $value): void
  {
    $address = $address & 0xFFFF;
    $value = $value & 0xFF;


    foreach ($this->peripherals as $peripheral) {
      if ($peripheral->handlesAddress($address)) {
        $peripheral->write($address, $value);
        return;
      }
    }


    $this->memory->write_byte($address, $value);
  }

  public function tick(): void
  {

    foreach ($this->peripherals as $peripheral) {
      $peripheral->tick();
    }
  }

  public function getMemory(): Memory
  {
    return $this->memory;
  }
}
