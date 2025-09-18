<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;
use Emulator\CPU;
use Emulator\Memory;
use Emulator\StatusRegister;

class CPUTest extends TestCase
{
  private CPU $cpu;
  private Memory $memory;

  protected function setUp(): void
  {
    $this->memory = new Memory();
    $this->cpu = new CPU($this->memory);
  }

  public function testCPUInitialization(): void
  {
    $this->assertInstanceOf(CPU::class, $this->cpu);
    $this->assertEquals(0, $this->cpu->cycles);
    $this->assertInstanceOf(StatusRegister::class, $this->cpu->status);
  }

  public function testRegisterConstraints(): void
  {
    // Test 8-bit register constraints
    $this->cpu->setAccumulator(0xFF);
    $this->assertEquals(0xFF, $this->cpu->getAccumulator());

    $this->cpu->setAccumulator(0x100); // Should wrap to 0x00
    $this->assertEquals(0x00, $this->cpu->getAccumulator());

    $this->cpu->setRegisterX(0xFF);
    $this->assertEquals(0xFF, $this->cpu->getRegisterX());

    $this->cpu->setRegisterX(0x100); // Should wrap to 0x00
    $this->assertEquals(0x00, $this->cpu->getRegisterX());

    $this->cpu->setRegisterY(0xFF);
    $this->assertEquals(0xFF, $this->cpu->getRegisterY());

    $this->cpu->setRegisterY(0x100); // Should wrap to 0x00
    $this->assertEquals(0x00, $this->cpu->getRegisterY());
  }

  public function testStackPointerConstraints(): void
  {
    // Test 8-bit stack pointer (0x00-0xFF)
    $this->cpu->sp = 0xFF;
    $this->assertEquals(0xFF, $this->cpu->sp);

    $this->cpu->sp = 0x80;
    $this->assertEquals(0x80, $this->cpu->sp);

    // Stack pointer should be within 8-bit range
    $this->assertTrue($this->cpu->sp >= 0x00 && $this->cpu->sp <= 0xFF);
  }

  public function testProgramCounterConstraints(): void
  {
    // Test 16-bit program counter (0x0000-0xFFFF)
    $this->cpu->pc = 0xFFFF;
    $this->assertEquals(0xFFFF, $this->cpu->pc);

    $this->cpu->pc = 0x8000;
    $this->assertEquals(0x8000, $this->cpu->pc);
  }

  public function testStackOperations(): void
  {
    // Test proper stack operations with 8-bit SP
    $this->cpu->sp = 0xFF; // Reset to top of stack

    // Push a byte
    $this->cpu->pushByte(0x42);
    $this->assertEquals(0xFE, $this->cpu->sp); // SP should decrement
    $this->assertEquals(0x42, $this->memory->read_byte(0x01FF)); // Check stack location

    // Push another byte
    $this->cpu->pushByte(0x84);
    $this->assertEquals(0xFD, $this->cpu->sp);
    $this->assertEquals(0x84, $this->memory->read_byte(0x01FE));

    // Pull bytes back (LIFO order)
    $pulled1 = $this->cpu->pullByte();
    $this->assertEquals(0x84, $pulled1);
    $this->assertEquals(0xFE, $this->cpu->sp);

    $pulled2 = $this->cpu->pullByte();
    $this->assertEquals(0x42, $pulled2);
    $this->assertEquals(0xFF, $this->cpu->sp);
  }

  public function testStackWordOperations(): void
  {
    $this->cpu->sp = 0xFF;

    // Push 16-bit word (high byte first, then low byte)
    $this->cpu->pushWord(0x1234);
    $this->assertEquals(0xFD, $this->cpu->sp);
    $this->assertEquals(0x12, $this->memory->read_byte(0x01FF)); // High byte
    $this->assertEquals(0x34, $this->memory->read_byte(0x01FE)); // Low byte

    // Pull 16-bit word (low byte first, then high byte)
    $pulled = $this->cpu->pullWord();
    $this->assertEquals(0x1234, $pulled);
    $this->assertEquals(0xFF, $this->cpu->sp);
  }

  public function testStackBoundaryConditions(): void
  {
    // Test stack pointer wrap-around
    $this->cpu->sp = 0x00;
    $this->cpu->pushByte(0x42);
    $this->assertEquals(0xFF, $this->cpu->sp); // Should wrap to 0xFF
    $this->assertEquals(0x42, $this->memory->read_byte(0x0100)); // Written to 0x0100

    $this->cpu->sp = 0xFF;
    $pulled = $this->cpu->pullByte();
    $this->assertEquals(0x00, $this->cpu->sp); // Should wrap to 0x00
  }

  public function testStandardReset(): void
  {
    // Set up reset vector
    $this->memory->write_byte(0xFFFC, 0x00); // Low byte
    $this->memory->write_byte(0xFFFD, 0x80); // High byte -> 0x8000

    // Set some initial state
    $this->cpu->pc = 0x1234;
    $this->cpu->setAccumulator(0x55);
    $this->cpu->setRegisterX(0xAA);
    $this->cpu->setRegisterY(0xFF);

    $this->cpu->reset();

    // Check final state
    $this->assertEquals(0x8000, $this->cpu->pc); // From reset vector
    $this->assertEquals(0xFD, $this->cpu->sp);   // After 3 decrements from 0x00
    $this->assertEquals(0x00, $this->cpu->getAccumulator()); // Cleared in emulator reset
    $this->assertEquals(0x00, $this->cpu->getRegisterX());   // Cleared in emulator reset
    $this->assertEquals(0x00, $this->cpu->getRegisterY());   // Cleared in emulator reset
    $this->assertTrue($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE)); // I flag set
    $this->assertTrue($this->cpu->status->get(StatusRegister::UNUSED)); // Unused bit set
    $this->assertEquals(0, $this->cpu->cycles);
  }

  public function testAccurateReset(): void
  {
    // Set up reset vector
    $this->memory->write_byte(0xFFFC, 0x00);
    $this->memory->write_byte(0xFFFD, 0x80);

    // Set initial state to test register preservation
    $this->cpu->pc = 0x1234;
    $this->cpu->setAccumulator(0x55);
    $this->cpu->setRegisterX(0xAA);
    $this->cpu->setRegisterY(0xFF);
    $this->cpu->status->fromInt(0b10110001);

    $this->cpu->accurateReset();

    // Check final state - registers should be preserved
    $this->assertEquals(0x8000, $this->cpu->pc); // From reset vector
    $this->assertEquals(0xFD, $this->cpu->sp);   // After 3 decrements
    $this->assertEquals(0x55, $this->cpu->getAccumulator()); // PRESERVED
    $this->assertEquals(0xAA, $this->cpu->getRegisterX());   // PRESERVED
    $this->assertEquals(0xFF, $this->cpu->getRegisterY());   // PRESERVED
    $this->assertTrue($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE)); // I flag set
    $this->assertTrue($this->cpu->status->get(StatusRegister::UNUSED)); // Unused bit set
  }

  public function testAddressingModeImmediate(): void
  {
    $this->cpu->pc = 0x8000;
    $this->memory->write_byte(0x8000, 0x42);

    $address = $this->cpu->getAddress('Immediate');
    $this->assertEquals(0x8000, $address); // Returns PC before increment
    $this->assertEquals(0x8001, $this->cpu->pc); // PC incremented
  }

  public function testAddressingModeZeroPage(): void
  {
    $this->cpu->pc = 0x8000;
    $this->memory->write_byte(0x8000, 0x42);

    $address = $this->cpu->getAddress('Zero Page');
    $this->assertEquals(0x42, $address);
    $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeZeroPageX(): void
  {
    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterX(0x05);
    $this->memory->write_byte(0x8000, 0x42);

    $address = $this->cpu->getAddress('X-Indexed Zero Page');
    $this->assertEquals(0x47, $address); // 0x42 + 0x05
    $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeZeroPageXWrap(): void
  {
    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterX(0x10);
    $this->memory->write_byte(0x8000, 0xFF);

    $address = $this->cpu->getAddress('X-Indexed Zero Page');
    $this->assertEquals(0x0F, $address); // 0xFF + 0x10 = 0x10F, wrapped to 0x0F
  }

  public function testAddressingModeZeroPageY(): void
  {
    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterY(0x05);
    $this->memory->write_byte(0x8000, 0x42);

    $address = $this->cpu->getAddress('Y-Indexed Zero Page');
    $this->assertEquals(0x47, $address); // 0x42 + 0x05
    $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeAbsolute(): void
  {
    $this->cpu->pc = 0x8000;
    $this->memory->write_byte(0x8000, 0x34); // Low byte
    $this->memory->write_byte(0x8001, 0x12); // High byte

    $address = $this->cpu->getAddress('Absolute');
    $this->assertEquals(0x1234, $address);
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeAbsoluteX(): void
  {
    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterX(0x05);
    $this->memory->write_byte(0x8000, 0x34);
    $this->memory->write_byte(0x8001, 0x12);

    $address = $this->cpu->getAddress('X-Indexed Absolute');
    $this->assertEquals(0x1239, $address); // 0x1234 + 0x05
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeAbsoluteY(): void
  {
    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterY(0x05);
    $this->memory->write_byte(0x8000, 0x34);
    $this->memory->write_byte(0x8001, 0x12);

    $address = $this->cpu->getAddress('Y-Indexed Absolute');
    $this->assertEquals(0x1239, $address); // 0x1234 + 0x05
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeIndirectX(): void
  {
    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterX(0x05);
    $this->memory->write_byte(0x8000, 0x20); // Zero page address
    $this->memory->write_byte(0x25, 0x34); // Low byte at 0x20 + 0x05
    $this->memory->write_byte(0x26, 0x12); // High byte

    $address = $this->cpu->getAddress('X-Indexed Zero Page Indirect');
    $this->assertEquals(0x1234, $address);
    $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeIndirectY(): void
  {
    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterY(0x05);
    $this->memory->write_byte(0x8000, 0x20); // Zero page address
    $this->memory->write_byte(0x20, 0x34); // Low byte
    $this->memory->write_byte(0x21, 0x12); // High byte

    $address = $this->cpu->getAddress('Zero Page Indirect Y-Indexed');
    $this->assertEquals(0x1239, $address); // 0x1234 + 0x05
    $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeAbsoluteIndirect(): void
  {
    $this->cpu->pc = 0x8000;
    $this->memory->write_byte(0x8000, 0x20); // Indirect address low
    $this->memory->write_byte(0x8001, 0x30); // Indirect address high -> 0x3020
    $this->memory->write_byte(0x3020, 0x34); // Target low byte
    $this->memory->write_byte(0x3021, 0x12); // Target high byte

    $address = $this->cpu->getAddress('Absolute Indirect');
    $this->assertEquals(0x1234, $address);
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeAbsoluteIndirectPageBoundaryBug(): void
  {
    // Test the famous 6502 page boundary bug in JMP indirect
    $this->cpu->pc = 0x8000;
    $this->memory->write_byte(0x8000, 0xFF); // Indirect address low
    $this->memory->write_byte(0x8001, 0x30); // Indirect address high -> 0x30FF
    $this->memory->write_byte(0x30FF, 0x34); // Target low byte
    $this->memory->write_byte(0x3000, 0x12); // High byte read from page start, not 0x3100!

    $address = $this->cpu->getAddress('Absolute Indirect');
    $this->assertEquals(0x1234, $address); // Should use bug behavior
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeRelative(): void
  {
    $this->cpu->pc = 0x8000;
    $this->memory->write_byte(0x8000, 0x05); // Positive offset

    $offset = $this->cpu->getAddress('Relative');
    $this->assertEquals(0x05, $offset); // Should return the raw offset
    $this->assertEquals(0x8001, $this->cpu->pc); // PC after reading offset
  }

  public function testAddressingModeRelativeNegative(): void
  {
    $this->cpu->pc = 0x8000;
    $this->memory->write_byte(0x8000, 0xFB); // -5 in two's complement

    $offset = $this->cpu->getAddress('Relative');
    $this->assertEquals(0xFB, $offset); // Should return the raw offset
    $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeImplied(): void
  {
    $address = $this->cpu->getAddress('Implied');
    $this->assertEquals(0, $address); // No address needed
  }

  public function testAddressingModeAccumulator(): void
  {
    $address = $this->cpu->getAddress('Accumulator');
    $this->assertEquals(0, $address); // No memory address
  }
}
