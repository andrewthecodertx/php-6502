<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\Peripherals\GraphicsMode;

class GraphicsModeTest extends TestCase
{
  private GraphicsMode $display;

  protected function setUp(): void
  {
   $this->display = new GraphicsMode();
  }

  public function testInitialization(): void
  {
   $this->assertInstanceOf(GraphicsMode::class, $this->display);

   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_X));
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_Y));
   $this->assertEquals(7, $this->display->read(GraphicsMode::FG_COLOR));
   $this->assertEquals(0, $this->display->read(GraphicsMode::BG_COLOR));
  }

  public function testMemoryAddressing(): void
  {
   $this->assertTrue($this->display->handlesAddress(GraphicsMode::DISPLAY_BASE));
   $this->assertTrue($this->display->handlesAddress(GraphicsMode::DISPLAY_END));
   $this->assertTrue($this->display->handlesAddress(GraphicsMode::CURSOR_X));
   $this->assertTrue($this->display->handlesAddress(GraphicsMode::CURSOR_Y));
   $this->assertTrue($this->display->handlesAddress(GraphicsMode::FG_COLOR));
   $this->assertTrue($this->display->handlesAddress(GraphicsMode::BG_COLOR));
   $this->assertTrue($this->display->handlesAddress(GraphicsMode::CONTROL));

   $this->assertFalse($this->display->handlesAddress(GraphicsMode::DISPLAY_BASE - 1));
   $this->assertFalse($this->display->handlesAddress(GraphicsMode::CONTROL + 1));
  }

  public function testCursorPositioning(): void
  {
   $this->display->write(GraphicsMode::CURSOR_X, 10);
   $this->assertEquals(10, $this->display->read(GraphicsMode::CURSOR_X));

   $this->display->write(GraphicsMode::CURSOR_Y, 5);
   $this->assertEquals(5, $this->display->read(GraphicsMode::CURSOR_Y));

   $this->display->write(GraphicsMode::CURSOR_X, 255);
   $this->assertEquals(GraphicsMode::WIDTH - 1, $this->display->read(GraphicsMode::CURSOR_X));

   $this->display->write(GraphicsMode::CURSOR_Y, 255);
   $this->assertEquals(GraphicsMode::HEIGHT - 1, $this->display->read(GraphicsMode::CURSOR_Y));

   $this->display->write(GraphicsMode::CURSOR_X, -1);
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_X));

   $this->display->write(GraphicsMode::CURSOR_Y, -1);
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_Y));
  }

  public function testColorSettings(): void
  {
   $this->display->write(GraphicsMode::FG_COLOR, 15);
   $this->assertEquals(15, $this->display->read(GraphicsMode::FG_COLOR));

   $this->display->write(GraphicsMode::BG_COLOR, 8);
   $this->assertEquals(8, $this->display->read(GraphicsMode::BG_COLOR));

   $this->display->write(GraphicsMode::FG_COLOR, 0xFF);
   $this->assertEquals(0x0F, $this->display->read(GraphicsMode::FG_COLOR));

   $this->display->write(GraphicsMode::BG_COLOR, 0xF8);
   $this->assertEquals(0x08, $this->display->read(GraphicsMode::BG_COLOR));
  }

  public function testDisplayMemoryReadWrite(): void
  {
   $testAddress = GraphicsMode::DISPLAY_BASE + 100;

   $this->assertEquals(0x20, $this->display->read($testAddress));

   $this->display->write($testAddress, ord('A'));
   $this->assertEquals(ord('A'), $this->display->read($testAddress));

   $this->display->write($testAddress, 0x100);
   $this->assertEquals(0x00, $this->display->read($testAddress));
  }

  public function testControlCommands(): void
  {
   $this->display->write(GraphicsMode::DISPLAY_BASE, ord('T'));
   $this->display->write(GraphicsMode::CURSOR_X, 5);
   $this->display->write(GraphicsMode::CURSOR_Y, 3);

   $this->display->write(GraphicsMode::CONTROL, GraphicsMode::CTRL_CLEAR);

   $this->assertEquals(0x20, $this->display->read(GraphicsMode::DISPLAY_BASE));
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_X));
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_Y));
  }

  public function testCursorVisibility(): void
  {
   $control = $this->display->read(GraphicsMode::CONTROL);
   $this->assertTrue(($control & GraphicsMode::CTRL_SHOW_CURSOR) !== 0);

   $this->display->write(GraphicsMode::CONTROL, 0);
   $control = $this->display->read(GraphicsMode::CONTROL);
   $this->assertTrue(($control & GraphicsMode::CTRL_SHOW_CURSOR) === 0);

   $this->display->write(GraphicsMode::CONTROL, GraphicsMode::CTRL_SHOW_CURSOR);
   $control = $this->display->read(GraphicsMode::CONTROL);
   $this->assertTrue(($control & GraphicsMode::CTRL_SHOW_CURSOR) !== 0);
  }

  public function testWriteChar(): void
  {
   $this->display->writeChar(ord('A'));
   $this->assertEquals(ord('A'), $this->display->read(GraphicsMode::DISPLAY_BASE));
   $this->assertEquals(1, $this->display->read(GraphicsMode::CURSOR_X));

   $this->display->writeChar(0x0A);
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_X));
   $this->assertEquals(1, $this->display->read(GraphicsMode::CURSOR_Y));
  }

  public function testWriteCharWrapping(): void
  {
   $this->display->write(GraphicsMode::CURSOR_X, GraphicsMode::WIDTH - 1);
   $this->display->write(GraphicsMode::CURSOR_Y, 0);

   $this->display->writeChar(ord('A'));
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_X));
   $this->assertEquals(1, $this->display->read(GraphicsMode::CURSOR_Y));
  }

  public function testScrolling(): void
  {
   $this->display->write(GraphicsMode::DISPLAY_BASE, ord('T'));

   $this->display->write(GraphicsMode::CURSOR_X, 0);
   $this->display->write(GraphicsMode::CURSOR_Y, GraphicsMode::HEIGHT - 1);

   $this->display->writeChar(ord('B'));

   $this->assertEquals(GraphicsMode::HEIGHT - 1, $this->display->read(GraphicsMode::CURSOR_Y));

   $lastLineAddress = GraphicsMode::DISPLAY_BASE + (GraphicsMode::HEIGHT - 1) * GraphicsMode::WIDTH;
   $this->assertEquals(ord('B'), $this->display->read($lastLineAddress));
  }

  public function testReset(): void
  {
   $this->display->write(GraphicsMode::CURSOR_X, 10);
   $this->display->write(GraphicsMode::CURSOR_Y, 5);
   $this->display->write(GraphicsMode::FG_COLOR, 15);
   $this->display->write(GraphicsMode::BG_COLOR, 8);
   $this->display->write(GraphicsMode::DISPLAY_BASE, ord('A'));

   $this->display->reset();

   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_X));
   $this->assertEquals(0, $this->display->read(GraphicsMode::CURSOR_Y));
   $this->assertEquals(7, $this->display->read(GraphicsMode::FG_COLOR));
   $this->assertEquals(0, $this->display->read(GraphicsMode::BG_COLOR));
   $this->assertEquals(0x20, $this->display->read(GraphicsMode::DISPLAY_BASE));
  }

  public function testTickBehavior(): void
  {
   $this->assertNull($this->display->tick());
  }

  public function testInvalidCharacterHandling(): void
  {
   $originalCursor = $this->display->read(GraphicsMode::CURSOR_X);

   $this->display->writeChar(0x01);
   $this->assertEquals($originalCursor, $this->display->read(GraphicsMode::CURSOR_X));

   $this->display->writeChar(0x7F);
   $this->assertEquals($originalCursor, $this->display->read(GraphicsMode::CURSOR_X));
  }

  public function testMemoryBounds(): void
  {
   $invalidAddress = GraphicsMode::DISPLAY_END + 100;
   $this->assertEquals(0, $this->display->read($invalidAddress));

   $this->display->write($invalidAddress, 0xFF);
   $this->assertEquals(0, $this->display->read($invalidAddress));
  }

  public function testDisplayDimensions(): void
  {
   $this->assertEquals(40, GraphicsMode::WIDTH);
   $this->assertEquals(25, GraphicsMode::HEIGHT);

   $totalMemory = GraphicsMode::WIDTH * GraphicsMode::HEIGHT;
   $expectedEndAddress = GraphicsMode::DISPLAY_BASE + $totalMemory - 1;
   $this->assertEquals($expectedEndAddress, GraphicsMode::DISPLAY_END);
  }

  public function testControlRegisterReadWrite(): void
  {
   $this->display->write(GraphicsMode::CONTROL,
   GraphicsMode::CTRL_CLEAR | GraphicsMode::CTRL_SHOW_CURSOR | GraphicsMode::CTRL_REFRESH);

   $controlValue = $this->display->read(GraphicsMode::CONTROL);
   $this->assertTrue(($controlValue & GraphicsMode::CTRL_SHOW_CURSOR) !== 0);
  }

  public function testRefreshMethod(): void
  {
   ob_start();
   $this->display->refresh();
   $output = ob_get_clean();

   $this->assertIsString($output);
   $this->assertStringContainsString("\e[2J\e[H", $output);
  }
}