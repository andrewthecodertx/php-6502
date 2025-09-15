<?php

declare(strict_types=1);

namespace Tests;

use Emulator\CPU;
use Emulator\Memory;
use Emulator\StatusRegister;
use PHPUnit\Framework\TestCase;

class CPUTest extends TestCase
{
  private Memory $memory;
  private CPU $cpu;

  protected function setUp(): void
  {
    $this->memory = new Memory();
    $this->cpu = new CPU($this->memory);
  }

  public function testReset(): void
  {
    // Modify the CPU state
    $this->cpu->pc = 0x1234;
    $this->cpu->sp = 0x00FE;
    $this->cpu->setAccumulator(0xFF);
    $this->cpu->setRegisterX(0xFF);
    $this->cpu->setRegisterY(0xFF);
    $this->cpu->status->set(StatusRegister::CARRY, true);

    // Write the reset vector
    $this->memory->write_word(0xFFFC, 0x8000);

    $this->cpu->reset();

    // The PC should be loaded from the reset vector 0xFFFC
    $this->assertSame(0x8000, $this->cpu->pc);

    // SP is reset to 0xFD on a real 6502, but our implementation sets it to 0x0100
    $this->assertSame(0x0100, $this->cpu->sp);

    $this->assertSame(0, $this->cpu->getAccumulator());
    $this->assertSame(0, $this->cpu->getRegisterX());
    $this->assertSame(0, $this->cpu->getRegisterY());

    // Check status register reset state (IRQ disabled)
    $this->assertSame(0b00100100, $this->cpu->status->toInt());
  }

  public function testLDA(): void
  {
    // Test Immediate addressing: LDA #$42
    $this->memory->write_byte(0x8000, 0xA9); // LDA Immediate opcode
    $this->memory->write_byte(0x8001, 0x42);
    $this->cpu->pc = 0x8000;

    $this->cpu->step();

    $this->assertSame(0x42, $this->cpu->getAccumulator());
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));

    // Test Zero flag
    $this->memory->write_byte(0x8002, 0xA9); // LDA Immediate
    $this->memory->write_byte(0x8003, 0x00);
    $this->cpu->pc = 0x8002;
    $this->cpu->step();
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));

    // Test Negative flag
    $this->memory->write_byte(0x8004, 0xA9); // LDA Immediate
    $this->memory->write_byte(0x8005, 0x8F);
    $this->cpu->pc = 0x8004;
    $this->cpu->step();
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testSTA(): void
  {
    // Test Absolute addressing: STA $0200
    $this->cpu->setAccumulator(0xBE);
    $this->memory->write_byte(0x8000, 0x8D); // STA Absolute opcode
    $this->memory->write_byte(0x8001, 0x00);
    $this->memory->write_byte(0x8002, 0x02);
    $this->cpu->pc = 0x8000;

    $this->cpu->step();

    $this->assertSame(0xBE, $this->memory->read_byte(0x0200));
  }
}
