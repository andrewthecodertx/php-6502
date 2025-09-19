<?php

declare(strict_types=1);

namespace Test;

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
  
  $this->cpu->status->set(StatusRegister::ZERO, true);
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0xF0); 
  $this->memory->writeByte(0x8001, 0x05); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x8007, $this->cpu->pc); 
  }

  public function testBEQNotTaken(): void
  {
  
  $this->cpu->status->set(StatusRegister::ZERO, false);
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0xF0); 
  $this->memory->writeByte(0x8001, 0x05); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x8002, $this->cpu->pc); 
  }

  public function testBNETaken(): void
  {
  
  $this->cpu->status->set(StatusRegister::ZERO, false);
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0xD0); 
  $this->memory->writeByte(0x8001, 0x10); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x8012, $this->cpu->pc); 
  }

  public function testBranchBackward(): void
  {
  
  $this->cpu->status->set(StatusRegister::ZERO, true);
  $this->cpu->pc = 0x8010;

  $this->memory->writeByte(0x8010, 0xF0); 
  $this->memory->writeByte(0x8011, 0xF0); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x8002, $this->cpu->pc); 
  }

  public function testBCCCarryClear(): void
  {
  
  $this->cpu->status->set(StatusRegister::CARRY, false);
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0x90); 
  $this->memory->writeByte(0x8001, 0x08); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x800A, $this->cpu->pc); 
  }

  public function testBCSCarrySet(): void
  {
  
  $this->cpu->status->set(StatusRegister::CARRY, true);
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0xB0); 
  $this->memory->writeByte(0x8001, 0x0C); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x800E, $this->cpu->pc); 
  }

  public function testBPLPositive(): void
  {
  
  $this->cpu->status->set(StatusRegister::NEGATIVE, false);
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0x10); 
  $this->memory->writeByte(0x8001, 0x04); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x8006, $this->cpu->pc); 
  }

  public function testBMINegative(): void
  {
  
  $this->cpu->status->set(StatusRegister::NEGATIVE, true);
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0x30); 
  $this->memory->writeByte(0x8001, 0x06); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x8008, $this->cpu->pc); 
  }

  public function testJMPAbsolute(): void
  {
  
  $this->cpu->pc = 0x8000;

  $this->memory->writeByte(0x8000, 0x4C); 
  $this->memory->writeByte(0x8001, 0x34); 
  $this->memory->writeByte(0x8002, 0x12); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x1234, $this->cpu->pc); 
  }

  public function testJMPIndirect(): void
  {
  
  $this->cpu->pc = 0x8000;

  
  $this->memory->writeByte(0x8000, 0x6C); 
  $this->memory->writeByte(0x8001, 0x20); 
  $this->memory->writeByte(0x8002, 0x30); 

  
  $this->memory->writeByte(0x3020, 0x78); 
  $this->memory->writeByte(0x3021, 0x56); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x5678, $this->cpu->pc); 
  }

  public function testJSRAndRTS(): void
  {
  
  $this->cpu->pc = 0x8000;
  $this->cpu->sp = 0xFF; 

  
  $this->memory->writeByte(0x8000, 0x20); 
  $this->memory->writeByte(0x8001, 0x34); 
  $this->memory->writeByte(0x8002, 0x12); 

  $this->executeCompleteInstruction();

  $this->assertEquals(0x1234, $this->cpu->pc); 
  $this->assertEquals(0xFD, $this->cpu->sp); 

  
  $returnAddr = $this->cpu->pullWord();
  $this->assertEquals(0x8002, $returnAddr); 

  
  $this->cpu->pushWord(0x8002);

  
  $this->memory->writeByte(0x1234, 0x60); 
  $this->cpu->pc = 0x1234;

  $this->executeCompleteInstruction();

  $this->assertEquals(0x8003, $this->cpu->pc); 
  $this->assertEquals(0xFF, $this->cpu->sp); 
  }

  public function testStackOperations(): void
  {
  
  $this->cpu->setAccumulator(0x42);
  $this->cpu->sp = 0xFF;

  
  $this->memory->writeByte(0x8000, 0x48); 
  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0xFE, $this->cpu->sp); 
  $this->assertEquals(0x42, $this->memory->readByte(0x01FF)); 

  
  $this->cpu->setAccumulator(0x00);

  
  $this->memory->writeByte(0x8001, 0x68); 
  $this->executeCompleteInstruction();

  $this->assertEquals(0x42, $this->cpu->getAccumulator()); 
  $this->assertEquals(0xFF, $this->cpu->sp); 
  $this->assertFalse($this->cpu->status->get(StatusRegister::ZERO)); 
  $this->assertFalse($this->cpu->status->get(StatusRegister::NEGATIVE));
  }

  public function testPHPAndPLP(): void
  {
  
  $this->cpu->sp = 0xFF;

  
  $this->cpu->status->set(StatusRegister::CARRY, true);
  $this->cpu->status->set(StatusRegister::ZERO, true);
  $this->cpu->status->set(StatusRegister::NEGATIVE, false);

  
  $this->memory->writeByte(0x8000, 0x08); 
  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();

  $this->assertEquals(0xFE, $this->cpu->sp); 

  
  $this->cpu->status->set(StatusRegister::CARRY, false);
  $this->cpu->status->set(StatusRegister::ZERO, false);

  
  $this->memory->writeByte(0x8001, 0x28); 
  $this->executeCompleteInstruction();

  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY)); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO)); 
  $this->assertEquals(0xFF, $this->cpu->sp); 
  }

  public function testFlagInstructions(): void
  {
  

  
  $this->memory->writeByte(0x8000, 0x38); 
  $this->cpu->pc = 0x8000;
  $this->executeCompleteInstruction();
  $this->assertTrue($this->cpu->status->get(StatusRegister::CARRY));

  
  $this->memory->writeByte(0x8001, 0x18); 
  $this->executeCompleteInstruction();
  $this->assertFalse($this->cpu->status->get(StatusRegister::CARRY));

  
  $this->memory->writeByte(0x8002, 0x78); 
  $this->executeCompleteInstruction();
  $this->assertTrue($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE));

  
  $this->memory->writeByte(0x8003, 0x58); 
  $this->executeCompleteInstruction();
  $this->assertFalse($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE));
  }

  public function testSimpleLoop(): void
  {
  
  $this->cpu->sp = 0xFF;

  
  
  
  
  $this->memory->initialize([
  0x8000 => 0xE8,       
  0x8001 => 0xE0,
  0x8002 => 0x05, 
  0x8003 => 0xD0,
  0x8004 => 0xFB, 
  ]);

  $this->cpu->pc = 0x8000;
  $this->cpu->setRegisterX(0x00);

  
  $maxIterations = 20; 
  $iterations = 0;

  while ($this->cpu->pc != 0x8005 && $iterations < $maxIterations) {
  $this->executeCompleteInstruction();
  $iterations++;
  }

  $this->assertEquals(0x05, $this->cpu->getRegisterX()); 
  $this->assertEquals(0x8005, $this->cpu->pc); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::ZERO)); 
  }
}
