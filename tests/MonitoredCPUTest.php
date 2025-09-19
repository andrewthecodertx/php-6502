<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\MonitoredCPU;
use Emulator\MonitoredMemory;
use Emulator\BusMonitor;

class MonitoredCPUTest extends TestCase
{
  private MonitoredCPU $cpu;
  private MonitoredMemory $memory;
  private BusMonitor $busMonitor;

  protected function setUp(): void
  {
   $this->busMonitor = new BusMonitor();
   $this->memory = new MonitoredMemory($this->busMonitor);
   $this->cpu = new MonitoredCPU($this->memory, false);
  }

  public function testVerboseOutputControl(): void
  {
   $this->assertFalse($this->cpu->isVerboseOutput());

   $this->cpu->setVerboseOutput(true);
   $this->assertTrue($this->cpu->isVerboseOutput());

   $this->cpu->setVerboseOutput(false);
   $this->assertFalse($this->cpu->isVerboseOutput());
  }

  public function testSilentReset(): void
  {
   ob_start();
   $this->cpu->reset();
   $output = ob_get_clean();

   $this->assertEquals('', $output);
  }

  public function testVerboseReset(): void
  {
   $this->cpu->setVerboseOutput(true);

   ob_start();
   $this->cpu->reset();
   $output = ob_get_clean();

   $this->assertStringContainsString('=== CPU RESET SEQUENCE ===', $output);
   $this->assertStringContainsString('=== RESET COMPLETE ===', $output);
  }

  public function testSilentStep(): void
  {
   $this->memory->write_byte(0x8000, 0xEA);
   $this->memory->write_byte(0xFFFC, 0x00);
   $this->memory->write_byte(0xFFFD, 0x80);

   $this->cpu->reset();

   ob_start();
   $this->cpu->step();
   $output = ob_get_clean();

   $this->assertEquals('', $output);
  }

  public function testSilentExecuteInstruction(): void
  {
   $this->memory->write_byte(0x8000, 0xEA);
   $this->memory->write_byte(0xFFFC, 0x00);
   $this->memory->write_byte(0xFFFD, 0x80);

   $this->cpu->reset();

   ob_start();
   $this->cpu->executeInstruction();
   $output = ob_get_clean();

   $this->assertEquals('', $output);
  }

  public function testDisplayState(): void
  {
   ob_start();
   $this->cpu->displayState();
   $output = ob_get_clean();

   $this->assertStringContainsString('CPU State:', $output);
   $this->assertStringContainsString('PC=0x', $output);
   $this->assertStringContainsString('SP=0x', $output);
  }

  public function testBusMonitorAccess(): void
  {
   $monitor = $this->cpu->getBusMonitor();
   $this->assertSame($this->busMonitor, $monitor);
  }
}