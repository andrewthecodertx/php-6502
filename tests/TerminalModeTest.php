<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\Peripherals\TerminalMode;
use Emulator\Peripherals\GraphicsMode;

class TerminalModeTest extends TestCase
{
  private TerminalMode $console;
  private GraphicsMode $display;

  protected function setUp(): void
  {
   $this->display = new GraphicsMode();
   $this->console = new TerminalMode($this->display);
  }

  public function testInitialization(): void
  {
   $this->assertInstanceOf(TerminalMode::class, $this->console);
  }

  public function testMemoryAddressing(): void
  {
   $this->assertTrue($this->console->handlesAddress(TerminalMode::CONSOLE_OUTPUT));
   $this->assertTrue($this->console->handlesAddress(TerminalMode::CONSOLE_INPUT_STATUS));
   $this->assertTrue($this->console->handlesAddress(TerminalMode::CONSOLE_INPUT_DATA));
   $this->assertTrue($this->console->handlesAddress(TerminalMode::CONSOLE_CONTROL));

   $this->assertFalse($this->console->handlesAddress(TerminalMode::CONSOLE_BASE - 1));
   $this->assertFalse($this->console->handlesAddress(TerminalMode::CONSOLE_CONTROL + 1));
  }

  public function testOutputToDisplay(): void
  {
   $this->console->write(TerminalMode::CONSOLE_OUTPUT, ord('H'));

   $this->assertEquals(ord('H'), $this->display->read(GraphicsMode::DISPLAY_BASE));
   $this->assertEquals(1, $this->display->read(GraphicsMode::CURSOR_X));
  }

  public function testControlRegister(): void
  {
   $initialControl = $this->console->read(TerminalMode::CONSOLE_CONTROL);
   $this->assertTrue(($initialControl & TerminalMode::CTRL_ECHO) !== 0);
   $this->assertTrue(($initialControl & TerminalMode::CTRL_LINE_MODE) === 0);

   $this->console->write(TerminalMode::CONSOLE_CONTROL, TerminalMode::CTRL_LINE_MODE);
   $controlValue = $this->console->read(TerminalMode::CONSOLE_CONTROL);
   $this->assertTrue(($controlValue & TerminalMode::CTRL_LINE_MODE) !== 0);
   $this->assertTrue(($controlValue & TerminalMode::CTRL_ECHO) === 0);

   $this->console->write(TerminalMode::CONSOLE_CONTROL,
   TerminalMode::CTRL_ECHO | TerminalMode::CTRL_LINE_MODE);
   $controlValue = $this->console->read(TerminalMode::CONSOLE_CONTROL);
   $this->assertTrue(($controlValue & TerminalMode::CTRL_ECHO) !== 0);
   $this->assertTrue(($controlValue & TerminalMode::CTRL_LINE_MODE) !== 0);
  }

  public function testInputStatusDefault(): void
  {
   $status = $this->console->read(TerminalMode::CONSOLE_INPUT_STATUS);
   $this->assertEquals(0x00, $status);
  }

  public function testInputDataDefault(): void
  {
   $data = $this->console->read(TerminalMode::CONSOLE_INPUT_DATA);
   $this->assertEquals(0x00, $data);
  }

  public function testReset(): void
  {
   $this->console->write(TerminalMode::CONSOLE_CONTROL, TerminalMode::CTRL_LINE_MODE);

   $this->console->reset();

   $controlValue = $this->console->read(TerminalMode::CONSOLE_CONTROL);
   $this->assertTrue(($controlValue & TerminalMode::CTRL_ECHO) !== 0);
   $this->assertTrue(($controlValue & TerminalMode::CTRL_LINE_MODE) === 0);
  }

  public function testTickBehavior(): void
  {
   $this->assertNull($this->console->tick());
  }

  public function testRefreshDelegation(): void
  {
   ob_start();
   $this->console->refresh();
   $output = ob_get_clean();

   $this->assertIsString($output);
   $this->assertStringContainsString("\e[2J\e[H", $output);
  }

  public function testInvalidAddressRead(): void
  {
   $this->assertEquals(0, $this->console->read(0xFFFF));
  }

  public function testWriteToInvalidAddress(): void
  {
   $this->console->write(0xFFFF, 0xFF);
   $this->assertEquals(0, $this->console->read(0xFFFF));
  }

  public function testClearInputBuffer(): void
  {
   $this->console->write(TerminalMode::CONSOLE_CONTROL, TerminalMode::CTRL_CLEAR_INPUT);

   $this->assertEquals(0x00, $this->console->read(TerminalMode::CONSOLE_INPUT_STATUS));
   $this->assertEquals(0x00, $this->console->read(TerminalMode::CONSOLE_INPUT_DATA));
  }

  public function testConsoleConstants(): void
  {
   $this->assertEquals(0xD000, TerminalMode::CONSOLE_BASE);
   $this->assertEquals(0xD000, TerminalMode::CONSOLE_OUTPUT);
   $this->assertEquals(0xD001, TerminalMode::CONSOLE_INPUT_STATUS);
   $this->assertEquals(0xD002, TerminalMode::CONSOLE_INPUT_DATA);
   $this->assertEquals(0xD003, TerminalMode::CONSOLE_CONTROL);

   $this->assertEquals(0x01, TerminalMode::CTRL_ECHO);
   $this->assertEquals(0x02, TerminalMode::CTRL_LINE_MODE);
   $this->assertEquals(0x04, TerminalMode::CTRL_CLEAR_INPUT);
  }

  public function testMultipleWrites(): void
  {
   $message = "HELLO";
   for ($i = 0; $i < strlen($message); $i++) {
   $this->console->write(TerminalMode::CONSOLE_OUTPUT, ord($message[$i]));
   }

   for ($i = 0; $i < strlen($message); $i++) {
   $address = GraphicsMode::DISPLAY_BASE + $i;
   $this->assertEquals(ord($message[$i]), $this->display->read($address));
   }

   $this->assertEquals(strlen($message), $this->display->read(GraphicsMode::CURSOR_X));
  }
}