<?php

declare(strict_types=1);

namespace Emulator;

class MonitoredCPU extends CPU
{
  private BusMonitor $busMonitor;
  private bool $resetInProgress = false;

  public function __construct(MonitoredMemory $memory)
  {
    $this->busMonitor = $memory->getBusMonitor();
    parent::__construct($memory);
  }

  public function clock(): void
  {
    parent::clock();
    $this->busMonitor->incrementCycle();
  }

  public function reset(): void
  {
    $this->resetInProgress = true;

    echo "=== CPU RESET SEQUENCE ===\n";
    echo "CYCLE  ADDRESS_BUS       DATA_BUS  ADDR_HEX  OP  DATA_HEX  DESCRIPTION\n";
    echo "-----  ----------------  --------  --------  --  --------  -----------\n";

    // Clear any previous bus activity
    $this->busMonitor->reset();

    // Now perform actual reset with bus monitoring
    parent::reset();

    // Display the actual bus activity that was logged
    $activity = $this->busMonitor->getBusActivity();
    foreach ($activity as $i => $op) {
      $addressBinary = sprintf('%016b', $op['address']);
      $dataBinary = sprintf('%08b', $op['data']);
      $addressHex = sprintf('%04X', $op['address']);
      $dataHex = sprintf('%02X', $op['data']);

      // Determine description based on cycle
      $description = "";
      switch ($i + 1) {
        case 1:
          $description = "Dummy read PC";
          break;
        case 2:
          $description = "Dummy read PC+1";
          break;
        case 3:
        case 4:
        case 5:
          $description = "Dummy stack read";
          break;
        case 6:
          $description = "Reset vector low";
          break;
        case 7:
          $description = "Reset vector high";
          break;
        default:
          $description = "Reset operation";
      }

      echo sprintf(
        "%5d  %s  %s    %s   %s     %s  %s\n",
        $i + 1,
        $addressBinary,
        $dataBinary,
        $addressHex,
        $op['operation'],
        $dataHex,
        $description
      );
    }

    $this->resetInProgress = false;

    echo "\n=== RESET COMPLETE ===\n";
    echo sprintf("PC: 0x%04X\n", $this->pc);
    echo sprintf("SP: 0x%02X\n", $this->sp);
    echo sprintf("Status: 0b%08b\n", $this->status->toInt());
    echo "\n";
  }

  public function step(): void
  {
    if ($this->cycles === 0) {
      echo sprintf(
        "=== EXECUTING INSTRUCTION AT PC=0x%04X ===\n",
        $this->pc
      );
    }

    parent::step();
  }

  public function executeInstruction(): void
  {
    $startingPC = $this->pc;
    $startCycle = $this->busMonitor->getCurrentCycle();

    echo sprintf("=== STARTING INSTRUCTION EXECUTION AT PC=0x%04X ===\n", $this->pc);

    do {
      $this->step();
    } while ($this->cycles > 0 || $this->pc == $startingPC);

    $endCycle = $this->busMonitor->getCurrentCycle();
    echo sprintf("=== INSTRUCTION COMPLETED (took %d cycles) ===\n\n", $endCycle - $startCycle);
  }

  public function getBusMonitor(): BusMonitor
  {
    return $this->busMonitor;
  }

  public function displayState(): void
  {
    echo sprintf(
      "CPU State: PC=0x%04X SP=0x%02X A=0x%02X X=0x%02X Y=0x%02X Status=0b%08b Cycles=%d\n",
      $this->pc,
      $this->sp,
      $this->getAccumulator(),
      $this->getRegisterX(),
      $this->getRegisterY(),
      $this->status->toInt(),
      $this->cycles
    );
  }
}

