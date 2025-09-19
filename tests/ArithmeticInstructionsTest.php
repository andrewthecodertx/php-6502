<?php

declare(strict_types=1);

namespace Test;

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
  
  $this->cpu->setAccumulator(0x20);
  $this->cpu->status->set(StatusRegister::CARRY, false);

  $this->memory->writeByte(0x8000, 0x69); 
  $this->memory->writeByte(0x8001, 0x30); 

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x50, $this->cpu->getAccumulator()); 
  $this->assertFalse($this->cpu->status->get(StatusRegister::CARRY));
  $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
  $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  $this->assertFalse($this->cpu->status->get(StatusRegister::OVERFLOW));
  }

  public function testADCWithCarry(): void
  {
  
  $this->cpu->setAccumulator(0x20);
  $this->cpu->status->set(StatusRegister::CARRY, true);

  $this->memory->writeByte(0x8000, 0x69); 
  $this->memory->writeByte(0x8001, 0x30);

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x51, $this->cpu->getAccumulator()); 
  }

  public function testADCCarryOut(): void
  {
  
  $this->cpu->setAccumulator(0xFF);
  $this->cpu->status->set(StatusRegister::CARRY, false);

  $this->memory->writeByte(0x8000, 0x69); 
  $this->memory->writeByte(0x8001, 0x01);

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x00, $this->cpu->getAccumulator()); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY));
  $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
  }

  public function testADCOverflow(): void
  {
  
  $this->cpu->setAccumulator(0x7F); 
  $this->cpu->status->set(StatusRegister::CARRY, false);

  $this->memory->writeByte(0x8000, 0x69); 
  $this->memory->writeByte(0x8001, 0x01); 

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x80, $this->cpu->getAccumulator()); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::OVERFLOW));
  $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testSBCImmediate(): void
  {
  
  $this->cpu->setAccumulator(0x50);
  $this->cpu->status->set(StatusRegister::CARRY, true);

  $this->memory->writeByte(0x8000, 0xE9); 
  $this->memory->writeByte(0x8001, 0x20); 

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x30, $this->cpu->getAccumulator()); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); 
  $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
  $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testSBCWithBorrow(): void
  {
  
  $this->cpu->setAccumulator(0x20);
  $this->cpu->status->set(StatusRegister::CARRY, false); 

  $this->memory->writeByte(0x8000, 0xE9); 
  $this->memory->writeByte(0x8001, 0x10);

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x0F, $this->cpu->getAccumulator()); 
  }

  public function testCMPEqual(): void
  {
  
  $this->cpu->setAccumulator(0x42);

  $this->memory->writeByte(0x8000, 0xC9); 
  $this->memory->writeByte(0x8001, 0x42); 

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x42, $this->cpu->getAccumulator()); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));  
  $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testCMPGreater(): void
  {
  
  $this->cpu->setAccumulator(0x50);

  $this->memory->writeByte(0x8000, 0xC9); 
  $this->memory->writeByte(0x8001, 0x30);

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); 
  $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO)); 
  }

  public function testCMPLesser(): void
  {
  
  $this->cpu->setAccumulator(0x30);

  $this->memory->writeByte(0x8000, 0xC9); 
  $this->memory->writeByte(0x8001, 0x50);

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertFalse($this->cpu->status->get(StatusRegister::CARRY)); 
  $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));  
  $this->assertTrue($this->cpu->status->get(StatusRegister::NEGATIVE)); 
  }

  public function testCPX(): void
  {
  
  $this->cpu->setRegisterX(0x42);

  $this->memory->writeByte(0x8000, 0xE0); 
  $this->memory->writeByte(0x8001, 0x42);

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x42, $this->cpu->getRegisterX()); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY));
  $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO));
  }

  public function testCPY(): void
  {
  
  $this->cpu->setRegisterY(0x84);

  $this->memory->writeByte(0x8000, 0xC0); 
  $this->memory->writeByte(0x8001, 0x42);

  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0x84, $this->cpu->getRegisterY()); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); 
  $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO));
  }
}
