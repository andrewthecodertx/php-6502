<?php

declare(strict_types=1);

namespace Tests;

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
    // Write 16-bit word (little-endian)
    $this->memory->write_word(0x1000, 0x1234);

    // Read back as bytes to verify little-endian storage
    $this->assertEquals(0x34, $this->memory->read_byte(0x1000)); // Low byte first
    $this->assertEquals(0x12, $this->memory->read_byte(0x1001)); // High byte second

    // Read back as word
    $this->assertEquals(0x1234, $this->memory->read_word(0x1000));
  }

  public function testByteConstraints(): void
  {
    // Test 8-bit value masking
    $this->memory->write_byte(0x1000, 0x100); // Should mask to 0x00
    $this->assertEquals(0x00, $this->memory->read_byte(0x1000));

    $this->memory->write_byte(0x1000, 0xFF); // Should remain 0xFF
    $this->assertEquals(0xFF, $this->memory->read_byte(0x1000));

    $this->memory->write_byte(0x1000, 0x1FF); // Should mask to 0xFF
    $this->assertEquals(0xFF, $this->memory->read_byte(0x1000));
  }

  public function testAddressConstraints(): void
  {
    // Test 16-bit address masking
    $this->memory->write_byte(0x10000, 0x42); // Should mask to 0x0000
    $this->assertEquals(0x42, $this->memory->read_byte(0x0000));

    $this->memory->write_byte(0xFFFF, 0x84);
    $this->assertEquals(0x84, $this->memory->read_byte(0xFFFF));
  }

  public function testWordConstraints(): void
  {
    // Test 16-bit word value masking
    $this->memory->write_word(0x1000, 0x10000); // Should mask to 0x0000
    $this->assertEquals(0x0000, $this->memory->read_word(0x1000));

    $this->memory->write_word(0x1000, 0xFFFF); // Should remain 0xFFFF
    $this->assertEquals(0xFFFF, $this->memory->read_word(0x1000));

    $this->memory->write_word(0x1000, 0x1FFFF); // Should mask to 0xFFFF
    $this->assertEquals(0xFFFF, $this->memory->read_word(0x1000));
  }

  public function testZeroPageOperations(): void
  {
    // Test Zero Page (0x0000-0x00FF)
    for ($addr = 0x00; $addr <= 0xFF; $addr++) {
      $value = $addr & 0xFF;
      $this->memory->write_byte($addr, $value);
      $this->assertEquals($value, $this->memory->read_byte($addr));
    }
  }

  public function testStackPageOperations(): void
  {
    // Test Stack Page (0x0100-0x01FF)
    for ($addr = 0x0100; $addr <= 0x01FF; $addr++) {
      $value = $addr & 0xFF;
      $this->memory->write_byte($addr, $value);
      $this->assertEquals($value, $this->memory->read_byte($addr));
    }
  }

  public function testGeneralMemoryOperations(): void
  {
    // Test various addresses throughout memory
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
      $value = ($addr >> 8) & 0xFF; // Use high byte as test value
      $this->memory->write_byte($addr, $value);
      $this->assertEquals($value, $this->memory->read_byte($addr));
    }
  }

  public function testResetVectorArea(): void
  {
    // Test reset vector at 0xFFFC/0xFFFD
    $this->memory->write_byte(0xFFFC, 0x00); // Reset vector low
    $this->memory->write_byte(0xFFFD, 0x80); // Reset vector high

    $this->assertEquals(0x00, $this->memory->read_byte(0xFFFC));
    $this->assertEquals(0x80, $this->memory->read_byte(0xFFFD));

    // Read as word should give 0x8000 (little-endian)
    $this->assertEquals(0x8000, $this->memory->read_word(0xFFFC));
  }

  public function testInterruptVectorArea(): void
  {
    // Test interrupt vectors
    $this->memory->write_word(0xFFFA, 0x1000); // NMI vector
    $this->memory->write_word(0xFFFC, 0x8000); // Reset vector
    $this->memory->write_word(0xFFFE, 0x9000); // IRQ/BRK vector

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
    // Write data and verify it persists through multiple operations
    $this->memory->write_byte(0x1000, 0x42);
    $this->memory->write_byte(0x1001, 0x84);
    $this->memory->write_word(0x2000, 0x1234);

    // Perform other operations
    $this->memory->write_byte(0x3000, 0xFF);
    $this->memory->read_byte(0x4000);

    // Original data should still be there
    $this->assertEquals(0x42, $this->memory->read_byte(0x1000));
    $this->assertEquals(0x84, $this->memory->read_byte(0x1001));
    $this->assertEquals(0x1234, $this->memory->read_word(0x2000));
  }

  public function testMemoryBoundaryConditions(): void
  {
    // Test reading from uninitialized memory (should return 0)
    $this->assertEquals(0x00, $this->memory->read_byte(0x5000));
    $this->assertEquals(0x0000, $this->memory->read_word(0x5000));

    // Test address boundary conditions
    $this->memory->write_byte(0x0000, 0x42); // First address
    $this->memory->write_byte(0xFFFF, 0x84); // Last address

    $this->assertEquals(0x42, $this->memory->read_byte(0x0000));
    $this->assertEquals(0x84, $this->memory->read_byte(0xFFFF));
  }

  public function testWordCrossingPageBoundary(): void
  {
    // Test word operations that cross page boundaries
    $this->memory->write_word(0x00FF, 0x1234);

    // Should be stored as little-endian across page boundary
    $this->assertEquals(0x34, $this->memory->read_byte(0x00FF)); // Low byte
    $this->assertEquals(0x12, $this->memory->read_byte(0x0100)); // High byte

    $this->assertEquals(0x1234, $this->memory->read_word(0x00FF));
  }

  public function testWordAtAddressBoundary(): void
  {
    // Test word operations at the very end of address space
    $this->memory->write_byte(0xFFFF, 0x42);
    $this->memory->write_byte(0x0000, 0x84); // Should wrap around

    // Read word from 0xFFFF should read 0xFFFF and 0x0000 (wrapped)
    $this->memory->write_word(0xFFFF, 0x1234);
    $this->assertEquals(0x34, $this->memory->read_byte(0xFFFF));
    $this->assertEquals(0x12, $this->memory->read_byte(0x0000));
  }

  public function testLargeDataOperations(): void
  {
    // Test operations with larger datasets
    $data = [];
    for ($i = 0; $i < 256; $i++) {
      $addr = 0x2000 + $i;
      $value = $i;
      $data[$addr] = $value;
    }

    $this->memory->initialize($data);

    // Verify all data was written correctly
    for ($i = 0; $i < 256; $i++) {
      $addr = 0x2000 + $i;
      $this->assertEquals($i, $this->memory->read_byte($addr));
    }
  }
}

