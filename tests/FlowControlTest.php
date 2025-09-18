<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\CPU;
use Emulator\Memory;
use Emulator\StatusRegister;

class FlowControlTest extends TestCase
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

  public function testBEQTaken(): void
  {
    // Test BEQ when zero flag is set (branch taken)
    $this->cpu->status->set(StatusRegister::ZERO, true);
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0xF0); // BEQ opcode
    $this->memory->write_byte(0x8001, 0x05); // Forward branch +5

    $this->executeCompleteInstruction();

    $this->assertEquals(0x8007, $this->cpu->pc); // 0x8002 + 5 = 0x8007
  }

  public function testBEQNotTaken(): void
  {
    // Test BEQ when zero flag is clear (branch not taken)
    $this->cpu->status->set(StatusRegister::ZERO, false);
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0xF0); // BEQ opcode
    $this->memory->write_byte(0x8001, 0x05); // Forward branch +5

    $this->executeCompleteInstruction();

    $this->assertEquals(0x8002, $this->cpu->pc); // No branch, PC = 0x8002
  }

  public function testBNETaken(): void
  {
    // Test BNE when zero flag is clear (branch taken)
    $this->cpu->status->set(StatusRegister::ZERO, false);
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0xD0); // BNE opcode
    $this->memory->write_byte(0x8001, 0x10); // Forward branch +16

    $this->executeCompleteInstruction();

    $this->assertEquals(0x8012, $this->cpu->pc); // 0x8002 + 16 = 0x8012
  }

  public function testBranchBackward(): void
  {
    // Test backward branch with negative offset
    $this->cpu->status->set(StatusRegister::ZERO, true);
    $this->cpu->pc = 0x8010;

    $this->memory->write_byte(0x8010, 0xF0); // BEQ opcode
    $this->memory->write_byte(0x8011, 0xF0); // Backward branch -16 (0xF0 = -16 in signed)

    $this->executeCompleteInstruction();

    $this->assertEquals(0x8002, $this->cpu->pc); // 0x8012 - 16 = 0x8002
  }

  public function testBCCCarryClear(): void
  {
    // Test BCC when carry is clear (branch taken)
    $this->cpu->status->set(StatusRegister::CARRY, false);
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0x90); // BCC opcode
    $this->memory->write_byte(0x8001, 0x08); // Forward branch +8

    $this->executeCompleteInstruction();

    $this->assertEquals(0x800A, $this->cpu->pc); // 0x8002 + 8 = 0x800A
  }

  public function testBCSCarrySet(): void
  {
    // Test BCS when carry is set (branch taken)
    $this->cpu->status->set(StatusRegister::CARRY, true);
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0xB0); // BCS opcode
    $this->memory->write_byte(0x8001, 0x0C); // Forward branch +12

    $this->executeCompleteInstruction();

    $this->assertEquals(0x800E, $this->cpu->pc); // 0x8002 + 12 = 0x800E
  }

  public function testBPLPositive(): void
  {
    // Test BPL when negative flag is clear (positive, branch taken)
    $this->cpu->status->set(StatusRegister::NEGATIVE, false);
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0x10); // BPL opcode
    $this->memory->write_byte(0x8001, 0x04); // Forward branch +4

    $this->executeCompleteInstruction();

    $this->assertEquals(0x8006, $this->cpu->pc); // 0x8002 + 4 = 0x8006
  }

  public function testBMINegative(): void
  {
    // Test BMI when negative flag is set (negative, branch taken)
    $this->cpu->status->set(StatusRegister::NEGATIVE, true);
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0x30); // BMI opcode
    $this->memory->write_byte(0x8001, 0x06); // Forward branch +6

    $this->executeCompleteInstruction();

    $this->assertEquals(0x8008, $this->cpu->pc); // 0x8002 + 6 = 0x8008
  }

  public function testJMPAbsolute(): void
  {
    // Test JMP absolute
    $this->cpu->pc = 0x8000;

    $this->memory->write_byte(0x8000, 0x4C); // JMP absolute opcode
    $this->memory->write_byte(0x8001, 0x34); // Target address low byte
    $this->memory->write_byte(0x8002, 0x12); // Target address high byte

    $this->executeCompleteInstruction();

    $this->assertEquals(0x1234, $this->cpu->pc); // Jump to 0x1234
  }

  public function testJMPIndirect(): void
  {
    // Test JMP indirect
    $this->cpu->pc = 0x8000;

    // Set up indirect address
    $this->memory->write_byte(0x8000, 0x6C); // JMP indirect opcode
    $this->memory->write_byte(0x8001, 0x20); // Indirect address low byte
    $this->memory->write_byte(0x8002, 0x30); // Indirect address high byte -> 0x3020

    // Set up target address at indirect location
    $this->memory->write_byte(0x3020, 0x78); // Target address low byte
    $this->memory->write_byte(0x3021, 0x56); // Target address high byte -> 0x5678

    $this->executeCompleteInstruction();

    $this->assertEquals(0x5678, $this->cpu->pc); // Jump to 0x5678
  }

  public function testJSRAndRTS(): void
  {
    // Test JSR (Jump to Subroutine) and RTS (Return from Subroutine)
    $this->cpu->pc = 0x8000;
    $this->cpu->sp = 0xFF; // Reset stack pointer

    // JSR instruction
    $this->memory->write_byte(0x8000, 0x20); // JSR opcode
    $this->memory->write_byte(0x8001, 0x34); // Subroutine address low
    $this->memory->write_byte(0x8002, 0x12); // Subroutine address high -> 0x1234

    $this->executeCompleteInstruction();

    $this->assertEquals(0x1234, $this->cpu->pc); // Jump to subroutine
    $this->assertEquals(0xFD, $this->cpu->sp); // SP decremented by 2 (word pushed)

    // Verify return address on stack (JSR pushes PC+2-1 = PC+1)
    $returnAddr = $this->cpu->pullWord();
    $this->assertEquals(0x8002, $returnAddr); // Should be address of last byte of JSR

    // Reset stack for RTS test
    $this->cpu->pushWord(0x8002);

    // RTS instruction
    $this->memory->write_byte(0x1234, 0x60); // RTS opcode at subroutine
    $this->cpu->pc = 0x1234;

    $this->executeCompleteInstruction();

    $this->assertEquals(0x8003, $this->cpu->pc); // Return to instruction after JSR
    $this->assertEquals(0xFF, $this->cpu->sp); // SP restored
  }

  public function testStackOperations(): void
  {
    // Test PHA and PLA
    $this->cpu->setAccumulator(0x42);
    $this->cpu->sp = 0xFF;

    // PHA - Push Accumulator
    $this->memory->write_byte(0x8000, 0x48); // PHA opcode
    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0xFE, $this->cpu->sp); // SP decremented
    $this->assertEquals(0x42, $this->memory->read_byte(0x01FF)); // Value on stack

    // Change accumulator
    $this->cpu->setAccumulator(0x00);

    // PLA - Pull Accumulator
    $this->memory->write_byte(0x8001, 0x68); // PLA opcode
    $this->executeCompleteInstruction();

    $this->assertEquals(0x42, $this->cpu->getAccumulator()); // Restored from stack
    $this->assertEquals(0xFF, $this->cpu->sp); // SP restored
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO)); // Flags set
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testPHPAndPLP(): void
  {
    // Test PHP and PLP
    $this->cpu->sp = 0xFF;

    // Set some flags
    $this->cpu->status->set(StatusRegister::CARRY, true);
    $this->cpu->status->set(StatusRegister::ZERO, true);
    $this->cpu->status->set(StatusRegister::NEGATIVE, false);

    // PHP - Push Processor Status
    $this->memory->write_byte(0x8000, 0x08); // PHP opcode
    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();

    $this->assertEquals(0xFE, $this->cpu->sp); // SP decremented

    // Clear all flags
    $this->cpu->status->set(StatusRegister::CARRY, false);
    $this->cpu->status->set(StatusRegister::ZERO, false);

    // PLP - Pull Processor Status
    $this->memory->write_byte(0x8001, 0x28); // PLP opcode
    $this->executeCompleteInstruction();

    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); // Restored
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO)); // Restored
    $this->assertEquals(0xFF, $this->cpu->sp); // SP restored
  }

  public function testFlagInstructions(): void
  {
    // Test flag set/clear instructions

    // SEC - Set Carry
    $this->memory->write_byte(0x8000, 0x38); // SEC opcode
    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();
    $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY));

    // CLC - Clear Carry
    $this->memory->write_byte(0x8001, 0x18); // CLC opcode
    $this->executeCompleteInstruction();
    $this->assertFalse($this->cpu->status->get(StatusRegister::CARRY));

    // SEI - Set Interrupt Disable
    $this->memory->write_byte(0x8002, 0x78); // SEI opcode
    $this->executeCompleteInstruction();
    $this->assertTrue($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE));

    // CLI - Clear Interrupt Disable
    $this->memory->write_byte(0x8003, 0x58); // CLI opcode
    $this->executeCompleteInstruction();
    $this->assertFalse($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE));
  }

  public function testSimpleLoop(): void
  {
    // Test a simple counting loop
    $this->cpu->sp = 0xFF;

    // Set up a simple loop program:
    // loop:   INX
    //         CPX #$05
    //         BNE loop
    $this->memory->initialize([
      0x8000 => 0xE8,       // INX
      0x8001 => 0xE0, 0x8002 => 0x05, // CPX #$05
      0x8003 => 0xD0, 0x8004 => 0xFB, // BNE loop (branch back -5)
    ]);

    $this->cpu->pc = 0x8000;
    $this->cpu->setRegisterX(0x00);

    // Execute the loop
    $maxIterations = 20; // Safety limit
    $iterations = 0;

    while ($this->cpu->pc != 0x8005 && $iterations < $maxIterations) {
      $this->executeCompleteInstruction();
      $iterations++;
    }

    $this->assertEquals(0x05, $this->cpu->getRegisterX()); // X should be 5
    $this->assertEquals(0x8005, $this->cpu->pc); // PC should be past the loop
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO)); // CPX should set zero flag
  }
}