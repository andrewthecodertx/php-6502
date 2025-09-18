<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\StatusRegister;

class StatusRegisterTest extends TestCase
{
  private StatusRegister $status;

  protected function setUp(): void
  {
    $this->status = new StatusRegister();
  }

  public function testStatusRegisterInitialization(): void
  {
    $this->assertInstanceOf(StatusRegister::class, $this->status);

    // Test initial state (should have UNUSED bit set per 6502 spec)
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
  }

  public function testStatusRegisterBitPositions(): void
  {
    // Test each flag's bit position according to 6502 specification
    $this->assertEquals(0, StatusRegister::CARRY);           // Bit 0
    $this->assertEquals(1, StatusRegister::ZERO);            // Bit 1
    $this->assertEquals(2, StatusRegister::INTERRUPT_DISABLE); // Bit 2
    $this->assertEquals(3, StatusRegister::DECIMAL_MODE);    // Bit 3
    $this->assertEquals(4, StatusRegister::BREAK_COMMAND);   // Bit 4
    $this->assertEquals(5, StatusRegister::UNUSED);         // Bit 5
    $this->assertEquals(6, StatusRegister::OVERFLOW);       // Bit 6 (corrected position)
    $this->assertEquals(7, StatusRegister::NEGATIVE);       // Bit 7
  }

  public function testIndividualFlagOperations(): void
  {
    // Test setting and clearing each flag
    $flags = [
      StatusRegister::CARRY,
      StatusRegister::ZERO,
      StatusRegister::INTERRUPT_DISABLE,
      StatusRegister::DECIMAL_MODE,
      StatusRegister::BREAK_COMMAND,
      StatusRegister::UNUSED,
      StatusRegister::OVERFLOW,
      StatusRegister::NEGATIVE,
    ];

    foreach ($flags as $flag) {
      // Set flag
      $this->status->set($flag, true);
      $this->assertTrue($this->status->get($flag), "Flag $flag should be set");

      // Clear flag
      $this->status->set($flag, false);
      $this->assertFalse($this->status->get($flag), "Flag $flag should be clear");
    }
  }

  public function testMultipleFlagsSimultaneously(): void
  {
    // Set multiple flags
    $this->status->set(StatusRegister::CARRY, true);
    $this->status->set(StatusRegister::ZERO, true);
    $this->status->set(StatusRegister::NEGATIVE, true);

    $this->assertTrue($this->status->get(StatusRegister::CARRY));
    $this->assertTrue($this->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->status->get(StatusRegister::NEGATIVE));
    $this->assertFalse($this->status->get(StatusRegister::OVERFLOW));
    $this->assertFalse($this->status->get(StatusRegister::INTERRUPT_DISABLE));
  }

  public function testFromIntConversion(): void
  {
    // Test converting from integer to flags
    $this->status->fromInt(0b11000011); // N=1, V=1, C=1, Z=1

    $this->assertTrue($this->status->get(StatusRegister::NEGATIVE));
    $this->assertTrue($this->status->get(StatusRegister::OVERFLOW));
    $this->assertTrue($this->status->get(StatusRegister::CARRY));
    $this->assertTrue($this->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertFalse($this->status->get(StatusRegister::DECIMAL_MODE));
    $this->assertFalse($this->status->get(StatusRegister::BREAK_COMMAND));
  }

  public function testToIntConversion(): void
  {
    // Test converting from flags to integer
    $this->status->set(StatusRegister::NEGATIVE, true);  // Bit 7
    $this->status->set(StatusRegister::OVERFLOW, true);  // Bit 6
    $this->status->set(StatusRegister::UNUSED, true);    // Bit 5
    $this->status->set(StatusRegister::CARRY, true);     // Bit 0

    $expected = 0b11100001; // Bits 7, 6, 5, 0 set
    $this->assertEquals($expected, $this->status->toInt());
  }

  public function testRoundTripConversion(): void
  {
    // Test that fromInt -> toInt preserves values
    $testValues = [
      0b00000000,
      0b11111111,
      0b10101010,
      0b01010101,
      0b11000011,
      0b00110000,
    ];

    foreach ($testValues as $value) {
      $this->status->fromInt($value);
      $this->assertEquals($value, $this->status->toInt());
    }
  }

  public function testResetState(): void
  {
    // Test the reset state as per 6502 specification
    $this->status->fromInt(0b00100100); // I flag + unused bit set (reset state)

    $this->assertTrue($this->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::NEGATIVE));
    $this->assertFalse($this->status->get(StatusRegister::OVERFLOW));
    $this->assertFalse($this->status->get(StatusRegister::BREAK_COMMAND));
    $this->assertFalse($this->status->get(StatusRegister::DECIMAL_MODE));
    $this->assertFalse($this->status->get(StatusRegister::ZERO));
    $this->assertFalse($this->status->get(StatusRegister::CARRY));
  }

  public function testBitMaskingConstraints(): void
  {
    // Test that fromInt properly masks to 8 bits
    $this->status->fromInt(0x100); // Should mask to 0x00
    $this->assertEquals(0x00, $this->status->toInt());

    $this->status->fromInt(0x1FF); // Should mask to 0xFF
    $this->assertEquals(0xFF, $this->status->toInt());
  }

  public function testOverflowFlagPosition(): void
  {
    // Specifically test that overflow flag is at bit 6 (not bit 5)
    $this->status->fromInt(0b01000000); // Only bit 6 set

    $this->assertTrue($this->status->get(StatusRegister::OVERFLOW));
    $this->assertFalse($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::NEGATIVE));
  }

  public function testUnusedBitPosition(): void
  {
    // Test that unused bit is at bit 5
    $this->status->fromInt(0b00100000); // Only bit 5 set

    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::OVERFLOW));
    $this->assertFalse($this->status->get(StatusRegister::BREAK_COMMAND));
  }

  public function testBreakCommandBitPosition(): void
  {
    // Test that break command bit is at bit 4
    $this->status->fromInt(0b00010000); // Only bit 4 set

    $this->assertTrue($this->status->get(StatusRegister::BREAK_COMMAND));
    $this->assertFalse($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::DECIMAL_MODE));
  }

  public function testAllFlagsPattern(): void
  {
    // Test all flags set
    $this->status->fromInt(0xFF);

    $this->assertTrue($this->status->get(StatusRegister::NEGATIVE));
    $this->assertTrue($this->status->get(StatusRegister::OVERFLOW));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
    $this->assertTrue($this->status->get(StatusRegister::BREAK_COMMAND));
    $this->assertTrue($this->status->get(StatusRegister::DECIMAL_MODE));
    $this->assertTrue($this->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertTrue($this->status->get(StatusRegister::ZERO));
    $this->assertTrue($this->status->get(StatusRegister::CARRY));
  }

  public function testCommonStatusPatterns(): void
  {
    // Test common status register patterns used in 6502 programming

    // After reset
    $this->status->fromInt(0b00100100); // I + unused
    $this->assertTrue($this->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));

    // After BRK instruction
    $this->status->fromInt(0b00110100); // I + B + unused
    $this->assertTrue($this->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertTrue($this->status->get(StatusRegister::BREAK_COMMAND));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));

    // Typical arithmetic result (negative, no carry)
    $this->status->fromInt(0b10100000); // N + unused
    $this->assertTrue($this->status->get(StatusRegister::NEGATIVE));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::CARRY));
  }
}

