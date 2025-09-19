<?php

declare(strict_types=1);

namespace Emulator;

class MonitoredMemory extends Memory
{
  private BusMonitor $busMonitor;

  public function __construct(BusMonitor $busMonitor)
  {
    parent::__construct();
    $this->busMonitor = $busMonitor;
  }

  public function readByte(int $addr): int
  {
    $value = parent::readByte($addr);
    $this->busMonitor->logBusOperation($addr, $value, 'R');
    return $value;
  }

  public function writeByte(int $addr, int $value): void
  {
    parent::writeByte($addr, $value);
    $this->busMonitor->logBusOperation($addr, $value, 'W');
  }

  public function readWord(int $addr): int
  {

    $low = $this->readByte($addr);
    $high = $this->readByte($addr + 1);
    return ($high << 8) | $low;
  }

  public function writeWord(int $addr, int $value): void
  {

    $this->writeByte($addr, $value & 0xFF);
    $this->writeByte($addr + 1, ($value >> 8) & 0xFF);
  }

  public function getBusMonitor(): BusMonitor
  {
    return $this->busMonitor;
  }
}
