<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\Peripherals\EnhancedConsole;
use Emulator\Peripherals\TextDisplay;

class EnhancedConsoleTest extends TestCase
{
    private EnhancedConsole $console;
    private TextDisplay $display;

    protected function setUp(): void
    {
        $this->display = new TextDisplay();
        $this->console = new EnhancedConsole($this->display);
    }

    public function testInitialization(): void
    {
        $this->assertInstanceOf(EnhancedConsole::class, $this->console);
    }

    public function testMemoryAddressing(): void
    {
        $this->assertTrue($this->console->handlesAddress(EnhancedConsole::CONSOLE_OUTPUT));
        $this->assertTrue($this->console->handlesAddress(EnhancedConsole::CONSOLE_INPUT_STATUS));
        $this->assertTrue($this->console->handlesAddress(EnhancedConsole::CONSOLE_INPUT_DATA));
        $this->assertTrue($this->console->handlesAddress(EnhancedConsole::CONSOLE_CONTROL));

        $this->assertFalse($this->console->handlesAddress(EnhancedConsole::CONSOLE_BASE - 1));
        $this->assertFalse($this->console->handlesAddress(EnhancedConsole::CONSOLE_CONTROL + 1));
    }

    public function testOutputToDisplay(): void
    {
        $this->console->write(EnhancedConsole::CONSOLE_OUTPUT, ord('H'));

        $this->assertEquals(ord('H'), $this->display->read(TextDisplay::DISPLAY_BASE));
        $this->assertEquals(1, $this->display->read(TextDisplay::CURSOR_X));
    }

    public function testControlRegister(): void
    {
        $initialControl = $this->console->read(EnhancedConsole::CONSOLE_CONTROL);
        $this->assertTrue(($initialControl & EnhancedConsole::CTRL_ECHO) !== 0);
        $this->assertTrue(($initialControl & EnhancedConsole::CTRL_LINE_MODE) === 0);

        $this->console->write(EnhancedConsole::CONSOLE_CONTROL, EnhancedConsole::CTRL_LINE_MODE);
        $controlValue = $this->console->read(EnhancedConsole::CONSOLE_CONTROL);
        $this->assertTrue(($controlValue & EnhancedConsole::CTRL_LINE_MODE) !== 0);
        $this->assertTrue(($controlValue & EnhancedConsole::CTRL_ECHO) === 0);

        $this->console->write(EnhancedConsole::CONSOLE_CONTROL,
            EnhancedConsole::CTRL_ECHO | EnhancedConsole::CTRL_LINE_MODE);
        $controlValue = $this->console->read(EnhancedConsole::CONSOLE_CONTROL);
        $this->assertTrue(($controlValue & EnhancedConsole::CTRL_ECHO) !== 0);
        $this->assertTrue(($controlValue & EnhancedConsole::CTRL_LINE_MODE) !== 0);
    }

    public function testInputStatusDefault(): void
    {
        $status = $this->console->read(EnhancedConsole::CONSOLE_INPUT_STATUS);
        $this->assertEquals(0x00, $status);
    }

    public function testInputDataDefault(): void
    {
        $data = $this->console->read(EnhancedConsole::CONSOLE_INPUT_DATA);
        $this->assertEquals(0x00, $data);
    }

    public function testReset(): void
    {
        $this->console->write(EnhancedConsole::CONSOLE_CONTROL, EnhancedConsole::CTRL_LINE_MODE);

        $this->console->reset();

        $controlValue = $this->console->read(EnhancedConsole::CONSOLE_CONTROL);
        $this->assertTrue(($controlValue & EnhancedConsole::CTRL_ECHO) !== 0);
        $this->assertTrue(($controlValue & EnhancedConsole::CTRL_LINE_MODE) === 0);
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
        $this->console->write(EnhancedConsole::CONSOLE_CONTROL, EnhancedConsole::CTRL_CLEAR_INPUT);

        $this->assertEquals(0x00, $this->console->read(EnhancedConsole::CONSOLE_INPUT_STATUS));
        $this->assertEquals(0x00, $this->console->read(EnhancedConsole::CONSOLE_INPUT_DATA));
    }

    public function testConsoleConstants(): void
    {
        $this->assertEquals(0xD000, EnhancedConsole::CONSOLE_BASE);
        $this->assertEquals(0xD000, EnhancedConsole::CONSOLE_OUTPUT);
        $this->assertEquals(0xD001, EnhancedConsole::CONSOLE_INPUT_STATUS);
        $this->assertEquals(0xD002, EnhancedConsole::CONSOLE_INPUT_DATA);
        $this->assertEquals(0xD003, EnhancedConsole::CONSOLE_CONTROL);

        $this->assertEquals(0x01, EnhancedConsole::CTRL_ECHO);
        $this->assertEquals(0x02, EnhancedConsole::CTRL_LINE_MODE);
        $this->assertEquals(0x04, EnhancedConsole::CTRL_CLEAR_INPUT);
    }

    public function testMultipleWrites(): void
    {
        $message = "HELLO";
        for ($i = 0; $i < strlen($message); $i++) {
            $this->console->write(EnhancedConsole::CONSOLE_OUTPUT, ord($message[$i]));
        }

        for ($i = 0; $i < strlen($message); $i++) {
            $address = TextDisplay::DISPLAY_BASE + $i;
            $this->assertEquals(ord($message[$i]), $this->display->read($address));
        }

        $this->assertEquals(strlen($message), $this->display->read(TextDisplay::CURSOR_X));
    }
}