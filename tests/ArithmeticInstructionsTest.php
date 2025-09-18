<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\CPU;
use Emulator\Memory;
use Emulator\StatusRegister;

class ArithmeticInstructionsTest extends TestCase
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

  public function testADCImmediate(): void
  {
    // Test ADC #$30 with A=$20
    $this->cpu->setAccumulator(0x20);
    $this->cpu->status->set(StatusRegister::CARRY, false);

    $this->memory->write_byte(0x8000, 0x69); // ADC immediate opcode
    $this->memory->write_byte(0x8001, 0x30); // Operand

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x50, $this->cpu->getAccumulator()); // 0x20 + 0x30 = 0x50
    $this->assertFalse($this->cpu->status->get(StatusRegister::CARRY));
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
    $this->assertFalse($this->cpu->status->get(StatusRegister::OVERFLOW));
  }

  public function testADCWithCarry(): void
  {
    // Test ADC with carry flag set
    $this->cpu->setAccumulator(0x20);
    $this->cpu->status->set(StatusRegister::CARRY, true);

    $this->memory->write_byte(0x8000, 0x69); // ADC immediate
    $this->memory->write_byte(0x8001, 0x30);

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x51, $this->cpu->getAccumulator()); // 0x20 + 0x30 + 1 = 0x51
  }

  public function testADCCarryOut(): void
  {
    // Test ADC that generates carry
    $this->cpu->setAccumulator(0xFF);
    $this->cpu->status->set(StatusRegister::CARRY, false);

    $this->memory->write_byte(0x8000, 0x69); // ADC immediate
    $this->memory->write_byte(0x8001, 0x01);

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x00, $this->cpu->getAccumulator()); // 0xFF + 0x01 = 0x100 -> 0x00
    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY));
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
  }

  public function testADCOverflow(): void
  {
    // Test ADC overflow (positive + positive = negative)
    $this->cpu->setAccumulator(0x7F); // +127
    $this->cpu->status->set(StatusRegister::CARRY, false);

    $this->memory->write_byte(0x8000, 0x69); // ADC immediate
    $this->memory->write_byte(0x8001, 0x01); // +1

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x80, $this->cpu->getAccumulator()); // +127 + 1 = -128 (overflow)
    $this->assertTrue($this->cpu->status->get(StatusRegister::OVERFLOW));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testSBCImmediate(): void
  {
    // Test SBC #$20 with A=$50, carry set (no borrow)
    $this->cpu->setAccumulator(0x50);
    $this->cpu->status->set(StatusRegister::CARRY, true);

    $this->memory->write_byte(0x8000, 0xE9); // SBC immediate opcode
    $this->memory->write_byte(0x8001, 0x20); // Operand

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x30, $this->cpu->getAccumulator()); // 0x50 - 0x20 = 0x30
    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); // No borrow
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testSBCWithBorrow(): void
  {
    // Test SBC with borrow (carry clear)
    $this->cpu->setAccumulator(0x20);
    $this->cpu->status->set(StatusRegister::CARRY, false); // Borrow

    $this->memory->write_byte(0x8000, 0xE9); // SBC immediate
    $this->memory->write_byte(0x8001, 0x10);

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x0F, $this->cpu->getAccumulator()); // 0x20 - 0x10 - 1 = 0x0F
  }

  public function testCMPEqual(): void
  {
    // Test CMP with equal values
    $this->cpu->setAccumulator(0x42);

    $this->memory->write_byte(0x8000, 0xC9); // CMP immediate opcode
    $this->memory->write_byte(0x8001, 0x42); // Same value

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x42, $this->cpu->getAccumulator()); // Accumulator unchanged
    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); // A >= M
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));  // A == M
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testCMPGreater(): void
  {
    // Test CMP with A > M
    $this->cpu->setAccumulator(0x50);

    $this->memory->write_byte(0x8000, 0xC9); // CMP immediate
    $this->memory->write_byte(0x8001, 0x30);

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); // A >= M
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO)); // A != M
  }

  public function testCMPLesser(): void
  {
    // Test CMP with A < M
    $this->cpu->setAccumulator(0x30);

    $this->memory->write_byte(0x8000, 0xC9); // CMP immediate
    $this->memory->write_byte(0x8001, 0x50);

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertFalse($this->cpu->status->get(StatusRegister::CARRY)); // A < M
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));  // A != M
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE)); // Result negative
  }

  public function testCPX(): void
  {
    // Test CPX instruction
    $this->cpu->setRegisterX(0x42);

    $this->memory->write_byte(0x8000, 0xE0); // CPX immediate opcode
    $this->memory->write_byte(0x8001, 0x42);

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x42, $this->cpu->getRegisterX()); // X unchanged
    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY));
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
  }

  public function testCPY(): void
  {
    // Test CPY instruction
    $this->cpu->setRegisterY(0x84);

    $this->memory->write_byte(0x8000, 0xC0); // CPY immediate opcode
    $this->memory->write_byte(0x8001, 0x42);

    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0x84, $this->cpu->getRegisterY()); // Y unchanged
    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); // 0x84 > 0x42
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
  }
}