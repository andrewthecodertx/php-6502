<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;
use Emulator\Memory;

class MemoryTest extends TestCase
{
  private Memory $memory;

  protected function setUp(): void
  {
  $this->memory = new Memory();
  }

  public function testMemoryInitialization(): void
  {
  $this->assertInstanceOf(Memory::class, $this->memory);
  }

  public function testBasicReadWrite(): void
  {
  $this->memory->write_byte(0x1000, 0x42);
  $this->assertEquals(0x42, $this->memory->read_byte(0x1000));
  }

  public function testWordOperations(): void
  {
  
  $this->memory->write_word(0x1000, 0x1234);

  
  $this->assertEquals(0x34, $this->memory->read_byte(0x1000)); 
  $this->assertEquals(0x12, $this->memory->read_byte(0x1001)); 

  
  $this->assertEquals(0x1234, $this->memory->read_word(0x1000));
  }

  public function testByteConstraints(): void
  {
  
  $this->memory->write_byte(0x1000, 0x100); 
  $this->assertEquals(0x00, $this->memory->read_byte(0x1000));

  $this->memory->write_byte(0x1000, 0xFF); 
  $this->assertEquals(0xFF, $this->memory->read_byte(0x1000));

  $this->memory->write_byte(0x1000, 0x1FF); 
  $this->assertEquals(0xFF, $this->memory->read_byte(0x1000));
  }

  public function testAddressConstraints(): void
  {
  
  $this->memory->write_byte(0x10000, 0x42); 
  $this->assertEquals(0x42, $this->memory->read_byte(0x0000));

  $this->memory->write_byte(0xFFFF, 0x84);
  $this->assertEquals(0x84, $this->memory->read_byte(0xFFFF));
  }

  public function testWordConstraints(): void
  {
  
  $this->memory->write_word(0x1000, 0x10000); 
  $this->assertEquals(0x0000, $this->memory->read_word(0x1000));

  $this->memory->write_word(0x1000, 0xFFFF); 
  $this->assertEquals(0xFFFF, $this->memory->read_word(0x1000));

  $this->memory->write_word(0x1000, 0x1FFFF); 
  $this->assertEquals(0xFFFF, $this->memory->read_word(0x1000));
  }

  public function testZeroPageOperations(): void
  {
  
  for ($addr = 0x00; $addr <= 0xFF; $addr++) {
  $value = $addr & 0xFF;
  $this->memory->write_byte($addr, $value);
  $this->assertEquals($value, $this->memory->read_byte($addr));
  }
  }

  public function testStackPageOperations(): void
  {
  
  for ($addr = 0x0100; $addr <= 0x01FF; $addr++) {
  $value = $addr & 0xFF;
  $this->memory->write_byte($addr, $value);
  $this->assertEquals($value, $this->memory->read_byte($addr));
  }
  }

  public function testGeneralMemoryOperations(): void
  {
  
  $testAddresses = [
  0x0200,
  0x1000,
  0x2000,
  0x4000,
  0x8000,
  0xC000,
  0xFFFE,
  0xFFFF
  ];

  foreach ($testAddresses as $addr) {
  $value = ($addr >> 8) & 0xFF; 
  $this->memory->write_byte($addr, $value);
  $this->assertEquals($value, $this->memory->read_byte($addr));
  }
  }

  public function testResetVectorArea(): void
  {
  
  $this->memory->write_byte(0xFFFC, 0x00); 
  $this->memory->write_byte(0xFFFD, 0x80); 

  $this->assertEquals(0x00, $this->memory->read_byte(0xFFFC));
  $this->assertEquals(0x80, $this->memory->read_byte(0xFFFD));

  
  $this->assertEquals(0x8000, $this->memory->read_word(0xFFFC));
  }

  public function testInterruptVectorArea(): void
  {
  
  $this->memory->write_word(0xFFFA, 0x1000); 
  $this->memory->write_word(0xFFFC, 0x8000); 
  $this->memory->write_word(0xFFFE, 0x9000); 

  $this->assertEquals(0x1000, $this->memory->read_word(0xFFFA));
  $this->assertEquals(0x8000, $this->memory->read_word(0xFFFC));
  $this->assertEquals(0x9000, $this->memory->read_word(0xFFFE));
  }

  public function testMemoryBulkInitialization(): void
  {
  $data = [
  0x1000 => 0x42,
  0x1001 => 0x84,
  0x2000 => 0xFF,
  0xFFFC => 0x00,
  0xFFFD => 0x80,
  ];

  $this->memory->initialize($data);

  foreach ($data as $address => $value) {
  $this->assertEquals($value, $this->memory->read_byte($address));
  }
  }

  public function testMemoryPersistence(): void
  {
  
  $this->memory->write_byte(0x1000, 0x42);
  $this->memory->write_byte(0x1001, 0x84);
  $this->memory->write_word(0x2000, 0x1234);

  
  $this->memory->write_byte(0x3000, 0xFF);
  $this->memory->read_byte(0x4000);

  
  $this->assertEquals(0x42, $this->memory->read_byte(0x1000));
  $this->assertEquals(0x84, $this->memory->read_byte(0x1001));
  $this->assertEquals(0x1234, $this->memory->read_word(0x2000));
  }

  public function testMemoryBoundaryConditions(): void
  {
  
  $this->assertEquals(0x00, $this->memory->read_byte(0x5000));
  $this->assertEquals(0x0000, $this->memory->read_word(0x5000));

  
  $this->memory->write_byte(0x0000, 0x42); 
  $this->memory->write_byte(0xFFFF, 0x84); 

  $this->assertEquals(0x42, $this->memory->read_byte(0x0000));
  $this->assertEquals(0x84, $this->memory->read_byte(0xFFFF));
  }

  public function testWordCrossingPageBoundary(): void
  {
  
  $this->memory->write_word(0x00FF, 0x1234);

  
  $this->assertEquals(0x34, $this->memory->read_byte(0x00FF)); 
  $this->assertEquals(0x12, $this->memory->read_byte(0x0100)); 

  $this->assertEquals(0x1234, $this->memory->read_word(0x00FF));
  }

  public function testWordAtAddressBoundary(): void
  {
  
  $this->memory->write_byte(0xFFFF, 0x42);
  $this->memory->write_byte(0x0000, 0x84); 

  
  $this->memory->write_word(0xFFFF, 0x1234);
  $this->assertEquals(0x34, $this->memory->read_byte(0xFFFF));
  $this->assertEquals(0x12, $this->memory->read_byte(0x0000));
  }

  public function testLargeDataOperations(): void
  {
  
  $data = [];
  for ($i = 0; $i < 256; $i++) {
  $addr = 0x2000 + $i;
  $value = $i;
  $data[$addr] = $value;
  }

  $this->memory->initialize($data);

  
  for ($i = 0; $i < 256; $i++) {
  $addr = 0x2000 + $i;
  $this->assertEquals($i, $this->memory->read_byte($addr));
  }
  }
}
