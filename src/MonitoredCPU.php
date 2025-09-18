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

    // 6502 reset sequence takes 7 cycles
    // Cycles 1-6: Internal operations (stack pointer decrement, etc.)
    // Cycle 7: Read reset vector low byte
    // Cycle 8: Read reset vector high byte

    echo "=== CPU RESET SEQUENCE ===\n";
    echo "CYCLE  ADDRESS_BUS       DATA_BUS  ADDR_HEX  OP  DATA_HEX  DESCRIPTION\n";
    echo "-----  ----------------  --------  --------  --  --------  -----------\n";

    // Simulate reset sequence
    for ($cycle = 1; $cycle <= 6; $cycle++) {
      $this->busMonitor->incrementCycle();
      echo sprintf(
        "%5d  ----------------  --------    ----   --     --    Internal reset operations\n",
        $this->busMonitor->getCurrentCycle()
      );
    }

    // Now perform actual reset with bus monitoring
    parent::reset();

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

