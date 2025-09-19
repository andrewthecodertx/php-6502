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

  public function read_byte(int $addr): int
  {
  $value = parent::read_byte($addr);
  $this->busMonitor->logBusOperation($addr, $value, 'R');
  return $value;
  }

  public function write_byte(int $addr, int $value): void
  {
  parent::write_byte($addr, $value);
  $this->busMonitor->logBusOperation($addr, $value, 'W');
  }

  public function read_word(int $addr): int
  {
  
  $low = $this->read_byte($addr);
  $high = $this->read_byte($addr + 1);
  return ($high << 8) | $low;
  }

  public function write_word(int $addr, int $value): void
  {
  
  $this->write_byte($addr, $value & 0xFF);
  $this->write_byte($addr + 1, ($value >> 8) & 0xFF);
  }

  public function getBusMonitor(): BusMonitor
  {
  return $this->busMonitor;
  }
}
