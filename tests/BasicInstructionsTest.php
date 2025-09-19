<?php

declare(strict_types=1);

namespace Test;

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
    
    $this->memory->write_byte(0x8000, 0xA2); 
    $this->memory->write_byte(0x8001, 0x42); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x8002, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDXZero(): void
  {
    
    $this->memory->write_byte(0x8000, 0xA2); 
    $this->memory->write_byte(0x8001, 0x00);

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x00, $this->cpu->getRegisterX());
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDXNegative(): void
  {
    
    $this->memory->write_byte(0x8000, 0xA2); 
    $this->memory->write_byte(0x8001, 0x80); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x80, $this->cpu->getRegisterX());
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDYImmediate(): void
  {
    
    $this->memory->write_byte(0x8000, 0xA0); 
    $this->memory->write_byte(0x8001, 0x84); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x8002, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testLDXZeroPage(): void
  {
    
    $this->memory->write_byte(0x80, 0x42); 
    $this->memory->write_byte(0x8000, 0xA6); 
    $this->memory->write_byte(0x8001, 0x80); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testLDYZeroPage(): void
  {
    
    $this->memory->write_byte(0x80, 0x84); 
    $this->memory->write_byte(0x8000, 0xA4); 
    $this->memory->write_byte(0x8001, 0x80); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testSTXZeroPage(): void
  {
    
    $this->cpu->setRegisterX(0x42);

    
    $this->memory->write_byte(0x8000, 0x86); 
    $this->memory->write_byte(0x8001, 0x80); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->memory->read_byte(0x80));
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testSTYZeroPage(): void
  {
    
    $this->cpu->setRegisterY(0x84);

    
    $this->memory->write_byte(0x8000, 0x84); 
    $this->memory->write_byte(0x8001, 0x80); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->memory->read_byte(0x80));
    $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testTAX(): void
  {
    
    $this->cpu->setAccumulator(0x42);

    
    $this->memory->write_byte(0x8000, 0xAA); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x42, $this->cpu->getAccumulator()); 
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTAXZero(): void
  {
    
    $this->cpu->setAccumulator(0x00);

    $this->memory->write_byte(0x8000, 0xAA); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x00, $this->cpu->getRegisterX());
    $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTAY(): void
  {
    
    $this->cpu->setAccumulator(0x84);

    
    $this->memory->write_byte(0x8000, 0xA8); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x84, $this->cpu->getAccumulator()); 
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTXA(): void
  {
    
    $this->cpu->setRegisterX(0x42);

    
    $this->memory->write_byte(0x8000, 0x8A); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getAccumulator());
    $this->assertEquals(0x42, $this->cpu->getRegisterX()); 
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTYA(): void
  {
    
    $this->cpu->setRegisterY(0x80);

    
    $this->memory->write_byte(0x8000, 0x98); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x80, $this->cpu->getAccumulator());
    $this->assertEquals(0x80, $this->cpu->getRegisterY()); 
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTSX(): void
  {
    
    $this->cpu->sp = 0xFD;

    
    $this->memory->write_byte(0x8000, 0xBA); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0xFD, $this->cpu->getRegisterX());
    $this->assertEquals(0xFD, $this->cpu->sp); 
    $this->assertEquals(0x8001, $this->cpu->pc);
    $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testTXS(): void
  {
    
    $this->cpu->setRegisterX(0xFF);

    
    $this->memory->write_byte(0x8000, 0x9A); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0xFF, $this->cpu->sp);
    $this->assertEquals(0xFF, $this->cpu->getRegisterX()); 
    $this->assertEquals(0x8001, $this->cpu->pc);
    
  }

  public function testLDXAbsolute(): void
  {
    
    $this->memory->write_byte(0x1234, 0x42); 
    $this->memory->write_byte(0x8000, 0xAE); 
    $this->memory->write_byte(0x8001, 0x34); 
    $this->memory->write_byte(0x8002, 0x12); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x42, $this->cpu->getRegisterX());
    $this->assertEquals(0x8003, $this->cpu->pc);
  }

  public function testLDYAbsolute(): void
  {
    
    $this->memory->write_byte(0x1234, 0x84); 
    $this->memory->write_byte(0x8000, 0xAC); 
    $this->memory->write_byte(0x8001, 0x34); 
    $this->memory->write_byte(0x8002, 0x12); 

    $this->cpu->pc = 0x8000;
    $this->cpu->step();

    $this->assertEquals(0x84, $this->cpu->getRegisterY());
    $this->assertEquals(0x8003, $this->cpu->pc);
  }

  public function testComplexRegisterOperations(): void
  {
    
    $this->cpu->setAccumulator(0x42);

    
    $this->memory->write_byte(0x8000, 0xAA);
    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();
    $this->assertEquals(0x42, $this->cpu->getRegisterX());

    
    $this->memory->write_byte(0x8001, 0xA8);
    $this->executeCompleteInstruction();
    $this->assertEquals(0x42, $this->cpu->getRegisterY());

    
    $this->memory->write_byte(0x8002, 0xA9);
    $this->memory->write_byte(0x8003, 0x84);

    $this->executeCompleteInstruction();

    $this->assertEquals(0x84, $this->cpu->getAccumulator());
    $this->assertEquals(0x42, $this->cpu->getRegisterX()); 
    $this->assertEquals(0x42, $this->cpu->getRegisterY()); 

    
    $this->memory->write_byte(0x8004, 0x8A);
    $this->executeCompleteInstruction();
    $this->assertEquals(0x42, $this->cpu->getAccumulator());
  }

  public function testStackPointerTransfers(): void
  {
    
    $this->cpu->sp = 0x80;

    
    $this->memory->write_byte(0x8000, 0xBA);
    $this->cpu->pc = 0x8000;
    $this->executeCompleteInstruction();
    $this->assertEquals(0x80, $this->cpu->getRegisterX());

    
    $this->cpu->setRegisterX(0x90);

    
    $this->memory->write_byte(0x8001, 0x9A);
    $this->executeCompleteInstruction();
    $this->assertEquals(0x90, $this->cpu->sp);
  }
}
