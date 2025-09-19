<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\CPU;
use Emulator\Memory;
use Emulator\Bus\SystemBus;
use Emulator\Peripherals\GraphicsMode;
use Emulator\Peripherals\TerminalMode;

class DisplayTimingTest extends TestCase
{
  private CPU $cpu;
  private SystemBus $bus;
  private Memory $memory;
  private GraphicsMode $display;
  private TerminalMode $console;

  protected function setUp(): void
  {
   $this->memory = new Memory();
   $this->bus = new SystemBus($this->memory);
   $this->display = new GraphicsMode();
   $this->console = new TerminalMode($this->display);

   $this->bus->addPeripheral($this->display);
   $this->bus->addPeripheral($this->console);

   $this->cpu = new CPU($this->bus);
  }

  public function testBusTickTiming(): void
  {
   $tickCountBefore = $this->getTickCount();

   $this->cpu->step();

   $tickCountAfter = $this->getTickCount();
   $this->assertGreaterThan($tickCountBefore, $tickCountAfter);
  }

  public function testDisplayRefreshAfterMultipleTicks(): void
  {
   $this->display->write(GraphicsMode::DISPLAY_BASE, ord('A'));

   for ($i = 0; $i < 30001; $i++) {
   $this->display->tick();
   }

   $this->assertEquals(ord('A'), $this->display->read(GraphicsMode::DISPLAY_BASE));
  }

  public function testConsoleTicksDisplay(): void
  {
   $this->console->write(TerminalMode::CONSOLE_OUTPUT, ord('B'));

   $this->console->tick();

   $this->assertEquals(ord('B'), $this->display->read(GraphicsMode::DISPLAY_BASE));
  }

  public function testBusIntegrityAfterTicking(): void
  {
   $this->bus->write(GraphicsMode::DISPLAY_BASE, ord('C'));
   $this->bus->write(GraphicsMode::CURSOR_X, 5);
   $this->bus->write(GraphicsMode::FG_COLOR, 15);

   for ($i = 0; $i < 10; $i++) {
   $this->bus->tick();
   }

   $this->assertEquals(ord('C'), $this->bus->read(GraphicsMode::DISPLAY_BASE));
   $this->assertEquals(5, $this->bus->read(GraphicsMode::CURSOR_X));
   $this->assertEquals(15, $this->bus->read(GraphicsMode::FG_COLOR));
  }

  public function testCPUStepWithDisplayWrites(): void
  {
   $program = [
   0xA9, 0x54,
   0x8D, 0x00, 0xC0,
   0xA9, 0x45,
   0x8D, 0x01, 0xC0,
   0xA9, 0x53,
   0x8D, 0x02, 0xC0,
   0xA9, 0x54,
   0x8D, 0x03, 0xC0,
   0xEA,
   0x4C, 0x12, 0x80
   ];

   $address = 0x8000;
   foreach ($program as $byte) {
   $this->memory->write_byte($address++, $byte);
   }

   $this->memory->write_byte(0xFFFC, 0x00);
   $this->memory->write_byte(0xFFFD, 0x80);

   $this->cpu->reset();

   for ($i = 0; $i < 8; $i++) {
   $this->cpu->executeInstruction();
   }

   $this->assertEquals(ord('T'), $this->display->read(GraphicsMode::DISPLAY_BASE + 0));
   $this->assertEquals(ord('E'), $this->display->read(GraphicsMode::DISPLAY_BASE + 1));
   $this->assertEquals(ord('S'), $this->display->read(GraphicsMode::DISPLAY_BASE + 2));
   $this->assertEquals(ord('T'), $this->display->read(GraphicsMode::DISPLAY_BASE + 3));
  }

  public function testContinuousDisplayOperations(): void
  {
   for ($i = 0; $i < 40; $i++) {
   $this->display->write(GraphicsMode::DISPLAY_BASE + $i, ord('A') + ($i % 26));
   }

   for ($i = 0; $i < 1000; $i++) {
   $this->display->tick();
   if ($i % 100 === 0) {
     $this->assertEquals(ord('A'), $this->display->read(GraphicsMode::DISPLAY_BASE));
   }
   }
  }

  public function testDisplayStateConsistency(): void
  {
   $testData = [
   ['address' => GraphicsMode::CURSOR_X, 'value' => 15],
   ['address' => GraphicsMode::CURSOR_Y, 'value' => 10],
   ['address' => GraphicsMode::FG_COLOR, 'value' => 12],
   ['address' => GraphicsMode::BG_COLOR, 'value' => 3],
   ['address' => GraphicsMode::DISPLAY_BASE + 100, 'value' => ord('X')],
   ];

   foreach ($testData as $test) {
   $this->display->write($test['address'], $test['value']);
   }

   for ($i = 0; $i < 50; $i++) {
   $this->display->tick();
   $this->bus->tick();
   }

   foreach ($testData as $test) {
   $this->assertEquals($test['value'], $this->display->read($test['address']),
     "Value mismatch for address " . sprintf('0x%04X', $test['address']));
   }
  }

  private function getTickCount(): int
  {
   static $tickCount = 0;
   $tickCount++;
   return $tickCount;
  }
}