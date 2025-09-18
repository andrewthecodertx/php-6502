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

    // SP is reset to 0xFD per 6502 specification (after 3 decrements from 0x00)
    $this->assertSame(0xFD, $this->cpu->sp);

    // Standard reset clears registers for emulation convenience
    $this->assertSame(0, $this->cpu->getAccumulator());
    $this->assertSame(0, $this->cpu->getRegisterX());
    $this->assertSame(0, $this->cpu->getRegisterY());

    // Check status register reset state (IRQ disabled, unused bit set)
    $this->assertSame(0b00100100, $this->cpu->status->toInt());
  }

  public function testAccurateReset(): void
  {
    // Test hardware-accurate reset behavior
    $this->cpu->pc = 0x1234;
    $this->cpu->sp = 0x00FE;
    $this->cpu->setAccumulator(0x55);
    $this->cpu->setRegisterX(0xAA);
    $this->cpu->setRegisterY(0xFF);
    $this->cpu->status->fromInt(0b10110001); // Various flags set

    // Write the reset vector
    $this->memory->write_word(0xFFFC, 0x9000);

    $this->cpu->accurateReset();

    // PC should be loaded from reset vector
    $this->assertSame(0x9000, $this->cpu->pc);

    // SP is reset to 0xFD
    $this->assertSame(0xFD, $this->cpu->sp);

    // Hardware-accurate: A, X, Y registers retain their values
    $this->assertSame(0x55, $this->cpu->getAccumulator());
    $this->assertSame(0xAA, $this->cpu->getRegisterX());
    $this->assertSame(0xFF, $this->cpu->getRegisterY());

    // Only I flag and unused bit are guaranteed to be set
    $this->assertTrue($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertTrue($this->cpu->status->get(StatusRegister::UNUSED));
  }

  public function testLDA(): void
  {
    // Test Immediate addressing: LDA #$42
    $this->memory->write_byte(0x8000, 0xA9); // LDA Immediate opcode
    $this->memory->write_byte(0x8001, 0x42);
    $this->cpu->pc = 0x8000;

    $this->cpu->executeInstruction();

    $this->assertSame(0x42, $this->cpu->getAccumulator());
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));

    // Test Zero flag
    $this->memory->write_byte(0x8002, 0xA9); // LDA Immediate
    $this->memory->write_byte(0x8003, 0x00);
    $this->cpu->pc = 0x8002;
    $this->cpu->executeInstruction();
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));

    // Test Negative flag
    $this->memory->write_byte(0x8004, 0xA9); // LDA Immediate
    $this->memory->write_byte(0x8005, 0x8F);
    $this->cpu->pc = 0x8004;
    $this->cpu->executeInstruction();
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

    $this->cpu->executeInstruction();

    $this->assertSame(0xBE, $this->memory->read_byte(0x0200));
  }

  public function testStackOperations(): void
  {
    // Set reset vector and reset CPU to ensure proper SP initialization
    $this->memory->write_word(0xFFFC, 0x8000);
    $this->cpu->reset();

    // Verify initial SP state
    $this->assertSame(0xFD, $this->cpu->sp);

    // Test byte operations
    $this->cpu->pushByte(0x42);
    $this->assertSame(0xFC, $this->cpu->sp);
    $this->assertSame(0x42, $this->memory->read_byte(0x01FD));

    $this->cpu->pushByte(0x24);
    $this->assertSame(0xFB, $this->cpu->sp);
    $this->assertSame(0x24, $this->memory->read_byte(0x01FC));

    $value1 = $this->cpu->pullByte();
    $this->assertSame(0x24, $value1);
    $this->assertSame(0xFC, $this->cpu->sp);

    $value2 = $this->cpu->pullByte();
    $this->assertSame(0x42, $value2);
    $this->assertSame(0xFD, $this->cpu->sp);

    // Test word operations
    $this->cpu->pushWord(0x1234);
    $this->assertSame(0xFB, $this->cpu->sp); // SP decremented by 2

    $word = $this->cpu->pullWord();
    $this->assertSame(0x1234, $word);
    $this->assertSame(0xFD, $this->cpu->sp); // SP back to original
  }

  public function testStatusRegisterFlags(): void
  {
    // Test individual flag operations
    $this->cpu->status->set(StatusRegister::CARRY, true);
    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY));

    $this->cpu->status->set(StatusRegister::ZERO, true);
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));

    $this->cpu->status->set(StatusRegister::NEGATIVE, true);
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));

    $this->cpu->status->set(StatusRegister::OVERFLOW, true);
    $this->assertTrue($this->cpu->status->get(StatusRegister::OVERFLOW));

    // Test unused bit is always set
    $this->assertTrue($this->cpu->status->get(StatusRegister::UNUSED));
  }
}
