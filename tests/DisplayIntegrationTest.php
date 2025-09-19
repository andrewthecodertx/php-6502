<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\CPU;
use Emulator\Memory;
use Emulator\Bus\SystemBus;
use Emulator\Peripherals\TextDisplay;
use Emulator\Peripherals\EnhancedConsole;

class DisplayIntegrationTest extends TestCase
{
    private CPU $cpu;
    private SystemBus $bus;
    private Memory $memory;
    private TextDisplay $display;
    private EnhancedConsole $console;

    protected function setUp(): void
    {
        $this->memory = new Memory();
        $this->bus = new SystemBus($this->memory);
        $this->display = new TextDisplay();
        $this->console = new EnhancedConsole($this->display);

        $this->bus->addPeripheral($this->display);
        $this->bus->addPeripheral($this->console);

        $this->cpu = new CPU($this->bus);
    }

    public function testBusMemoryIntegration(): void
    {
        $this->assertNotNull($this->cpu->getBus());
        $this->assertSame($this->bus, $this->cpu->getBus());
    }

    public function testTextDisplayMemoryMappedIO(): void
    {
        $this->memory->write_byte(0x8000, 0xA9);
        $this->memory->write_byte(0x8001, 0x48);
        $this->memory->write_byte(0x8002, 0x8D);
        $this->memory->write_byte(0x8003, 0x00);
        $this->memory->write_byte(0x8004, 0xC0);

        $this->memory->write_byte(0xFFFC, 0x00);
        $this->memory->write_byte(0xFFFD, 0x80);

        $this->cpu->reset();

        $this->cpu->executeInstruction();

        $this->assertEquals(0x48, $this->cpu->getAccumulator());

        $this->cpu->executeInstruction();

        $this->assertEquals(0x48, $this->display->read(TextDisplay::DISPLAY_BASE));
    }

    public function testEnhancedConsoleMemoryMappedIO(): void
    {
        $this->memory->write_byte(0x8000, 0xA9);
        $this->memory->write_byte(0x8001, 0x41);
        $this->memory->write_byte(0x8002, 0x8D);
        $this->memory->write_byte(0x8003, 0x00);
        $this->memory->write_byte(0x8004, 0xD0);

        $this->memory->write_byte(0xFFFC, 0x00);
        $this->memory->write_byte(0xFFFD, 0x80);

        $this->cpu->reset();

        $this->cpu->executeInstruction();
        $this->cpu->executeInstruction();

        $this->assertEquals(0x41, $this->display->read(TextDisplay::DISPLAY_BASE));
    }

    public function testDisplayControlCommands(): void
    {
        $this->bus->write(TextDisplay::DISPLAY_BASE, ord('T'));
        $this->bus->write(TextDisplay::CURSOR_X, 5);

        $this->assertEquals(ord('T'), $this->bus->read(TextDisplay::DISPLAY_BASE));
        $this->assertEquals(5, $this->bus->read(TextDisplay::CURSOR_X));

        $this->bus->write(TextDisplay::CONTROL, TextDisplay::CTRL_CLEAR);

        $this->assertEquals(0x20, $this->bus->read(TextDisplay::DISPLAY_BASE));
        $this->assertEquals(0, $this->bus->read(TextDisplay::CURSOR_X));
    }

    public function testColorSettings(): void
    {
        $this->bus->write(TextDisplay::FG_COLOR, 15);
        $this->bus->write(TextDisplay::BG_COLOR, 8);

        $this->assertEquals(15, $this->bus->read(TextDisplay::FG_COLOR));
        $this->assertEquals(8, $this->bus->read(TextDisplay::BG_COLOR));
    }

    public function testBusTickingBehavior(): void
    {
        $initialValue = $this->bus->read(TextDisplay::CURSOR_X);

        $this->bus->tick();

        $this->assertEquals($initialValue, $this->bus->read(TextDisplay::CURSOR_X));
    }

    public function testPeripheralPriority(): void
    {
        $textDisplayBase = TextDisplay::DISPLAY_BASE;
        $this->assertEquals(0x20, $this->bus->read($textDisplayBase));

        $this->bus->write($textDisplayBase, ord('X'));
        $this->assertEquals(ord('X'), $this->bus->read($textDisplayBase));

        $this->bus->write(0x1000, 0xFF);
        $this->assertEquals(0xFF, $this->bus->read(0x1000));
    }

    public function testCPUWithDisplayProgram(): void
    {
        $program = [
            0xA9, 0x48,
            0x8D, 0x00, 0xC0,
            0xA9, 0x45,
            0x8D, 0x01, 0xC0,
            0xA9, 0x4C,
            0x8D, 0x02, 0xC0,
            0xA9, 0x4C,
            0x8D, 0x03, 0xC0,
            0xA9, 0x4F,
            0x8D, 0x04, 0xC0,
            0x4C, 0x16, 0x80
        ];

        $address = 0x8000;
        foreach ($program as $byte) {
            $this->memory->write_byte($address++, $byte);
        }

        $this->memory->write_byte(0xFFFC, 0x00);
        $this->memory->write_byte(0xFFFD, 0x80);

        $this->cpu->reset();

        for ($i = 0; $i < 10; $i++) {
            $this->cpu->executeInstruction();
        }

        $this->assertEquals(ord('H'), $this->display->read(TextDisplay::DISPLAY_BASE + 0));
        $this->assertEquals(ord('E'), $this->display->read(TextDisplay::DISPLAY_BASE + 1));
        $this->assertEquals(ord('L'), $this->display->read(TextDisplay::DISPLAY_BASE + 2));
        $this->assertEquals(ord('L'), $this->display->read(TextDisplay::DISPLAY_BASE + 3));
        $this->assertEquals(ord('O'), $this->display->read(TextDisplay::DISPLAY_BASE + 4));
    }

    public function testBusMemoryBridgeReadWrite(): void
    {
        $cpuMemory = $this->cpu->getMemory();

        $cpuMemory->write_byte(0x1000, 0xAB);
        $this->assertEquals(0xAB, $cpuMemory->read_byte(0x1000));

        $cpuMemory->write_byte(TextDisplay::DISPLAY_BASE, ord('Z'));
        $this->assertEquals(ord('Z'), $cpuMemory->read_byte(TextDisplay::DISPLAY_BASE));
        $this->assertEquals(ord('Z'), $this->display->read(TextDisplay::DISPLAY_BASE));
    }

    public function testDisplayAndConsoleIntegration(): void
    {
        $this->console->write(EnhancedConsole::CONSOLE_OUTPUT, ord('A'));
        $this->assertEquals(ord('A'), $this->display->read(TextDisplay::DISPLAY_BASE));

        $this->display->write(TextDisplay::CURSOR_X, 10);
        $this->assertEquals(10, $this->display->read(TextDisplay::CURSOR_X));

        $this->console->write(EnhancedConsole::CONSOLE_OUTPUT, ord('B'));
        $this->assertEquals(ord('B'), $this->display->read(TextDisplay::DISPLAY_BASE + 10));
    }

    public function testMemoryRanges(): void
    {
        $this->assertTrue($this->display->handlesAddress(TextDisplay::DISPLAY_BASE));
        $this->assertTrue($this->display->handlesAddress(TextDisplay::DISPLAY_END));
        $this->assertTrue($this->display->handlesAddress(TextDisplay::CONTROL));

        $this->assertTrue($this->console->handlesAddress(EnhancedConsole::CONSOLE_BASE));
        $this->assertTrue($this->console->handlesAddress(EnhancedConsole::CONSOLE_CONTROL));

        $this->assertFalse($this->display->handlesAddress(EnhancedConsole::CONSOLE_BASE));
        $this->assertFalse($this->console->handlesAddress(TextDisplay::DISPLAY_BASE));
    }
}