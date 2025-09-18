<?php

declare(strict_types=1);

namespace Emulator;

class BusMonitor
{
  private array $busActivity = [];
  private int $cycleCount = 0;

  public function logBusOperation(int $address, int $data, string $operation): void
  {
    $this->busActivity[] = [
      'cycle' => $this->cycleCount,
      'address' => $address & 0xFFFF,
      'data' => $data & 0xFF,
      'operation' => $operation, // 'R' for read, 'W' for write
    ];
  }

  public function incrementCycle(): void
  {
    $this->cycleCount++;
  }

  public function reset(): void
  {
    $this->busActivity = [];
    $this->cycleCount = 0;
  }

  public function getBusActivity(): array
  {
    return $this->busActivity;
  }

  public function getCurrentCycle(): int
  {
    return $this->cycleCount;
  }

  public function displayBusActivity(): void
  {
    echo "CYCLE  ADDRESS_BUS       DATA_BUS  ADDR_HEX  OP  DATA_HEX\n";
    echo "-----  ----------------  --------  --------  --  --------\n";

    foreach ($this->busActivity as $activity) {
      $addressBinary = sprintf('%016b', $activity['address']);
      $dataBinary = sprintf('%08b', $activity['data']);
      $addressHex = sprintf('%04X', $activity['address']);
      $dataHex = sprintf('%02X', $activity['data']);

      echo sprintf(
        "%5d  %s  %s    %s   %s     %s\n",
        $activity['cycle'],
        $addressBinary,
        $dataBinary,
        $addressHex,
        $activity['operation'],
        $dataHex
      );
    }
  }

  public function getLastBusOperation(): ?array
  {
    return end($this->busActivity) ?: null;
  }
}

