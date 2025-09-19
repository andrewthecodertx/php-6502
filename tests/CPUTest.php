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
  
  $this->cpu->setAccumulator(0xFF);
  $this->assertEquals(0xFF, $this->cpu->getAccumulator());

  $this->cpu->setAccumulator(0x100); 
  $this->assertEquals(0x00, $this->cpu->getAccumulator());

  $this->cpu->setRegisterX(0xFF);
  $this->assertEquals(0xFF, $this->cpu->getRegisterX());

  $this->cpu->setRegisterX(0x100); 
  $this->assertEquals(0x00, $this->cpu->getRegisterX());

  $this->cpu->setRegisterY(0xFF);
  $this->assertEquals(0xFF, $this->cpu->getRegisterY());

  $this->cpu->setRegisterY(0x100); 
  $this->assertEquals(0x00, $this->cpu->getRegisterY());
  }

  public function testStackPointerConstraints(): void
  {
  
  $this->cpu->sp = 0xFF;
  $this->assertEquals(0xFF, $this->cpu->sp);

  $this->cpu->sp = 0x80;
  $this->assertEquals(0x80, $this->cpu->sp);

  
  $this->assertTrue($this->cpu->sp >= 0x00 && $this->cpu->sp <= 0xFF);
  }

  public function testProgramCounterConstraints(): void
  {
  
  $this->cpu->pc = 0xFFFF;
  $this->assertEquals(0xFFFF, $this->cpu->pc);

  $this->cpu->pc = 0x8000;
  $this->assertEquals(0x8000, $this->cpu->pc);
  }

  public function testStackOperations(): void
  {
  
  $this->cpu->sp = 0xFF; 

  
  $this->cpu->pushByte(0x42);
  $this->assertEquals(0xFE, $this->cpu->sp); 
  $this->assertEquals(0x42, $this->memory->read_byte(0x01FF)); 

  
  $this->cpu->pushByte(0x84);
  $this->assertEquals(0xFD, $this->cpu->sp);
  $this->assertEquals(0x84, $this->memory->read_byte(0x01FE));

  
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

  
  $this->cpu->pushWord(0x1234);
  $this->assertEquals(0xFD, $this->cpu->sp);
  $this->assertEquals(0x12, $this->memory->read_byte(0x01FF)); 
  $this->assertEquals(0x34, $this->memory->read_byte(0x01FE)); 

  
  $pulled = $this->cpu->pullWord();
  $this->assertEquals(0x1234, $pulled);
  $this->assertEquals(0xFF, $this->cpu->sp);
  }

  public function testStackBoundaryConditions(): void
  {
  
  $this->cpu->sp = 0x00;
  $this->cpu->pushByte(0x42);
  $this->assertEquals(0xFF, $this->cpu->sp); 
  $this->assertEquals(0x42, $this->memory->read_byte(0x0100)); 

  $this->cpu->sp = 0xFF;
  $pulled = $this->cpu->pullByte();
  $this->assertEquals(0x00, $this->cpu->sp); 
  }

  public function testStandardReset(): void
  {
  
  $this->memory->write_byte(0xFFFC, 0x00); 
  $this->memory->write_byte(0xFFFD, 0x80); 

  
  $this->cpu->pc = 0x1234;
  $this->cpu->setAccumulator(0x55);
  $this->cpu->setRegisterX(0xAA);
  $this->cpu->setRegisterY(0xFF);

  $this->cpu->reset();

  
  $this->assertEquals(0x8000, $this->cpu->pc); 
  $this->assertEquals(0xFD, $this->cpu->sp);   
  $this->assertEquals(0x00, $this->cpu->getAccumulator()); 
  $this->assertEquals(0x00, $this->cpu->getRegisterX());   
  $this->assertEquals(0x00, $this->cpu->getRegisterY());   
  $this->assertTrue($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE)); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::UNUSED)); 
  $this->assertEquals(0, $this->cpu->cycles);
  }

  public function testAccurateReset(): void
  {
  
  $this->memory->write_byte(0xFFFC, 0x00);
  $this->memory->write_byte(0xFFFD, 0x80);

  
  $this->cpu->pc = 0x1234;
  $this->cpu->setAccumulator(0x55);
  $this->cpu->setRegisterX(0xAA);
  $this->cpu->setRegisterY(0xFF);
  $this->cpu->status->fromInt(0b10110001);

  $this->cpu->accurateReset();

  
  $this->assertEquals(0x8000, $this->cpu->pc); 
  $this->assertEquals(0xFD, $this->cpu->sp);   
  $this->assertEquals(0x55, $this->cpu->getAccumulator()); 
  $this->assertEquals(0xAA, $this->cpu->getRegisterX());   
  $this->assertEquals(0xFF, $this->cpu->getRegisterY());   
  $this->assertTrue($this->cpu->status->get(StatusRegister::INTERRUPT_DISABLE)); 
  $this->assertTrue($this->cpu->status->get(StatusRegister::UNUSED)); 
  }

  public function testAddressingModeImmediate(): void
  {
  $this->cpu->pc = 0x8000;
  $this->memory->write_byte(0x8000, 0x42);

  $address = $this->cpu->getAddress('Immediate');
  $this->assertEquals(0x8000, $address); 
  $this->assertEquals(0x8001, $this->cpu->pc); 
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
  $this->assertEquals(0x47, $address); 
  $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeZeroPageXWrap(): void
  {
  $this->cpu->pc = 0x8000;
  $this->cpu->setRegisterX(0x10);
  $this->memory->write_byte(0x8000, 0xFF);

  $address = $this->cpu->getAddress('X-Indexed Zero Page');
  $this->assertEquals(0x0F, $address); 
  }

  public function testAddressingModeZeroPageY(): void
  {
  $this->cpu->pc = 0x8000;
  $this->cpu->setRegisterY(0x05);
  $this->memory->write_byte(0x8000, 0x42);

  $address = $this->cpu->getAddress('Y-Indexed Zero Page');
  $this->assertEquals(0x47, $address); 
  $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeAbsolute(): void
  {
  $this->cpu->pc = 0x8000;
  $this->memory->write_byte(0x8000, 0x34); 
  $this->memory->write_byte(0x8001, 0x12); 

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
  $this->assertEquals(0x1239, $address); 
  $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeAbsoluteY(): void
  {
  $this->cpu->pc = 0x8000;
  $this->cpu->setRegisterY(0x05);
  $this->memory->write_byte(0x8000, 0x34);
  $this->memory->write_byte(0x8001, 0x12);

  $address = $this->cpu->getAddress('Y-Indexed Absolute');
  $this->assertEquals(0x1239, $address); 
  $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeIndirectX(): void
  {
  $this->cpu->pc = 0x8000;
  $this->cpu->setRegisterX(0x05);
  $this->memory->write_byte(0x8000, 0x20); 
  $this->memory->write_byte(0x25, 0x34); 
  $this->memory->write_byte(0x26, 0x12); 

  $address = $this->cpu->getAddress('X-Indexed Zero Page Indirect');
  $this->assertEquals(0x1234, $address);
  $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeIndirectY(): void
  {
  $this->cpu->pc = 0x8000;
  $this->cpu->setRegisterY(0x05);
  $this->memory->write_byte(0x8000, 0x20); 
  $this->memory->write_byte(0x20, 0x34); 
  $this->memory->write_byte(0x21, 0x12); 

  $address = $this->cpu->getAddress('Zero Page Indirect Y-Indexed');
  $this->assertEquals(0x1239, $address); 
  $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeAbsoluteIndirect(): void
  {
  $this->cpu->pc = 0x8000;
  $this->memory->write_byte(0x8000, 0x20); 
  $this->memory->write_byte(0x8001, 0x30); 
  $this->memory->write_byte(0x3020, 0x34); 
  $this->memory->write_byte(0x3021, 0x12); 

  $address = $this->cpu->getAddress('Absolute Indirect');
  $this->assertEquals(0x1234, $address);
  $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeAbsoluteIndirectPageBoundaryBug(): void
  {
  
  $this->cpu->pc = 0x8000;
  $this->memory->write_byte(0x8000, 0xFF); 
  $this->memory->write_byte(0x8001, 0x30); 
  $this->memory->write_byte(0x30FF, 0x34); 
  $this->memory->write_byte(0x3000, 0x12); 

  $address = $this->cpu->getAddress('Absolute Indirect');
  $this->assertEquals(0x1234, $address); 
  $this->assertEquals(0x8002, $this->cpu->pc);
  }

  public function testAddressingModeRelative(): void
  {
  $this->cpu->pc = 0x8000;
  $this->memory->write_byte(0x8000, 0x05); 

  $offset = $this->cpu->getAddress('Relative');
  $this->assertEquals(0x05, $offset); 
  $this->assertEquals(0x8001, $this->cpu->pc); 
  }

  public function testAddressingModeRelativeNegative(): void
  {
  $this->cpu->pc = 0x8000;
  $this->memory->write_byte(0x8000, 0xFB); 

  $offset = $this->cpu->getAddress('Relative');
  $this->assertEquals(0xFB, $offset); 
  $this->assertEquals(0x8001, $this->cpu->pc);
  }

  public function testAddressingModeImplied(): void
  {
  $address = $this->cpu->getAddress('Implied');
  $this->assertEquals(0, $address); 
  }

  public function testAddressingModeAccumulator(): void
  {
  $address = $this->cpu->getAddress('Accumulator');
  $this->assertEquals(0, $address); 
  }
}
