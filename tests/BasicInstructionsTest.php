<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\CPU;
use Emulator\Memory;
use Emulator\StatusRegister;

class BasicInstructionsTest extends TestCase
{
  private CPU $cpu;
  private Memory $memory;

  protected function setUp(): void
  {
    $this->memory = new Memory();
    $this->cpu = new CPU($this->memory);
  }

  private function executeCompleteInstruction(): void
  {
    do {
      $this->cpu->step();
    } while ($this->cpu->cycles > 0);
  }

  public function testLDXImmediate(): void
  {
    // Set up LDX #$42 instruction
    $this->memory->write_byte(0x8000, 0xA2); // LDX immediate opcode
    $this->memory->write_byte(0x8001, 0x42); // Operand

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x8002, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDXZero(): void
  {
    // Test LDX with zero value sets zero flag
    $this->memory->write_byte(0x8000, 0xA2); // LDX immediate
    $this->memory->write_byte(0x8001, 0x00);

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x00, $this->cpu->getRegisterX());
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDXNegative(): void
  {
    // Test LDX with negative value sets negative flag
    $this->memory->write_byte(0x8000, 0xA2); // LDX immediate
    $this->memory->write_byte(0x8001, 0x80); // -128 in signed

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x80, $this->cpu->getRegisterX());
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDYImmediate(): void
  {
    // Set up LDY #$84 instruction
    $this->memory->write_byte(0x8000, 0xA0); // LDY immediate opcode
    $this->memory->write_byte(0x8001, 0x84); // Operand

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x8002, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDXZeroPage(): void
  {
    // Set up memory and instruction
    $this->memory->write_byte(0x80, 0x42); // Data in zero page
    $this->memory->write_byte(0x8000, 0xA6); // LDX zero page opcode
    $this->memory->write_byte(0x8001, 0x80); // Zero page address

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testLDYZeroPage(): void
  {
    // Set up memory and instruction
    $this->memory->write_byte(0x80, 0x84); // Data in zero page
    $this->memory->write_byte(0x8000, 0xA4); // LDY zero page opcode
    $this->memory->write_byte(0x8001, 0x80); // Zero page address

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testSTXZeroPage(): void
  {
    // Set register value
    $this->cpu->setRegisterX(0x42);

    // Set up STX zero page instruction
    $this->memory->write_byte(0x8000, 0x86); // STX zero page opcode
    $this->memory->write_byte(0x8001, 0x80); // Zero page address

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->memory->read_byte(0x80));
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testSTYZeroPage(): void
  {
    // Set register value
    $this->cpu->setRegisterY(0x84);

    // Set up STY zero page instruction
    $this->memory->write_byte(0x8000, 0x84); // STY zero page opcode
    $this->memory->write_byte(0x8001, 0x80); // Zero page address

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->memory->read_byte(0x80));
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testTAX(): void
  {
    // Set accumulator value
    $this->cpu->setAccumulator(0x42);

    // Set up TAX instruction
    $this->memory->write_byte(0x8000, 0xAA); // TAX opcode

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x42, $this->cpu->getAccumulator()); // A unchanged
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTAXZero(): void
  {
    // Set accumulator to zero
    $this->cpu->setAccumulator(0x00);

    $this->memory->write_byte(0x8000, 0xAA); // TAX opcode

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x00, $this->cpu->getRegisterX());
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTAY(): void
  {
    // Set accumulator value
    $this->cpu->setAccumulator(0x84);

    // Set up TAY instruction
    $this->memory->write_byte(0x8000, 0xA8); // TAY opcode

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x84, $this->cpu->getAccumulator()); // A unchanged
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTXA(): void
  {
    // Set X register value
    $this->cpu->setRegisterX(0x42);

    // Set up TXA instruction
    $this->memory->write_byte(0x8000, 0x8A); // TXA opcode

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getAccumulator());
    $this->assertEquals(0x42, $this->cpu->getRegisterX()); // X unchanged
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTYA(): void
  {
    // Set Y register value
    $this->cpu->setRegisterY(0x80);

    // Set up TYA instruction
    $this->memory->write_byte(0x8000, 0x98); // TYA opcode

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x80, $this->cpu->getAccumulator());
    $this->assertEquals(0x80, $this->cpu->getRegisterY()); // Y unchanged
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTSX(): void
  {
    // Set stack pointer value
    $this->cpu->sp = 0xFD;

    // Set up TSX instruction
    $this->memory->write_byte(0x8000, 0xBA); // TSX opcode

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0xFD, $this->cpu->getRegisterX());
    $this->assertEquals(0xFD, $this->cpu->sp); // SP unchanged
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTXS(): void
  {
    // Set X register value
    $this->cpu->setRegisterX(0xFF);

    // Set up TXS instruction
    $this->memory->write_byte(0x8000, 0x9A); // TXS opcode

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0xFF, $this->cpu->sp);
    $this->assertEquals(0xFF, $this->cpu->getRegisterX()); // X unchanged
    $this->assertEquals(0x8001, $this->cpu->pc);
    // TXS does not affect flags
  }

  public function testLDXAbsolute(): void
  {
    // Set up memory and instruction
    $this->memory->write_byte(0x1234, 0x42); // Data at absolute address
    $this->memory->write_byte(0x8000, 0xAE); // LDX absolute opcode
    $this->memory->write_byte(0x8001, 0x34); // Address low byte
    $this->memory->write_byte(0x8002, 0x12); // Address high byte

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x8003, $this->cpu->pc);
  }

  public function testLDYAbsolute(): void
  {
    // Set up memory and instruction
    $this->memory->write_byte(0x1234, 0x84); // Data at absolute address
    $this->memory->write_byte(0x8000, 0xAC); // LDY absolute opcode
    $this->memory->write_byte(0x8001, 0x34); // Address low byte
    $this->memory->write_byte(0x8002, 0x12); // Address high byte

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x8003, $this->cpu->pc);
  }

  public function testComplexRegisterOperations(): void
  {
    // Test a sequence of register operations
    $this->cpu->setAccumulator(0x42);

    // TAX - transfer A to X
    $this->memory->write_byte(0x8000, 0xAA);
    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();
    $this->assertEquals(0x42, $this->cpu->getRegisterX());

    // TAY - transfer A to Y
    $this->memory->write_byte(0x8001, 0xA8);
    $this->executeCompleteInstruction();
    $this->assertEquals(0x42, $this->cpu->getRegisterY());

    // LDA #$84 - load new value to A
    $this->memory->write_byte(0x8002, 0xA9);
    $this->memory->write_byte(0x8003, 0x84);

    $this->executeCompleteInstruction();

    $this->assertEquals(0x84, $this->cpu->getAccumulator());
    $this->assertEquals(0x42, $this->cpu->getRegisterX()); // X unchanged
    $this->assertEquals(0x42, $this->cpu->getRegisterY()); // Y unchanged

    // TXA - transfer X back to A
    $this->memory->write_byte(0x8004, 0x8A);
    $this->executeCompleteInstruction();
    $this->assertEquals(0x42, $this->cpu->getAccumulator());
  }

  public function testStackPointerTransfers(): void
  {
    // Test TSX and TXS operations
    $this->cpu->sp = 0x80;

    // TSX - transfer SP to X
    $this->memory->write_byte(0x8000, 0xBA);
    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();
    $this->assertEquals(0x80, $this->cpu->getRegisterX());

    // Modify X
    $this->cpu->setRegisterX(0x90);

    // TXS - transfer X to SP
    $this->memory->write_byte(0x8001, 0x9A);
    $this->executeCompleteInstruction();
    $this->assertEquals(0x90, $this->cpu->sp);
  }
}

