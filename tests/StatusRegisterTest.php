<?php

declare(strict_types=1);

namespace Test;

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

    
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
  }

  public function testStatusRegisterBitPositions(): void
  {
    
    $this->assertEquals(0, StatusRegister::CARRY);           
    $this->assertEquals(1, StatusRegister::ZERO);            
    $this->assertEquals(2, StatusRegister::INTERRUPT_DISABLE); 
    $this->assertEquals(3, StatusRegister::DECIMAL_MODE);    
    $this->assertEquals(4, StatusRegister::BREAK_COMMAND);   
    $this->assertEquals(5, StatusRegister::UNUSED);         
    $this->assertEquals(6, StatusRegister::OVERFLOW);       
    $this->assertEquals(7, StatusRegister::NEGATIVE);       
  }

  public function testIndividualFlagOperations(): void
  {
    
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
      
      $this->status->set($flag, true);
      $this->assertTrue($this->status->get($flag), "Flag $flag should be set");

      
      $this->status->set($flag, false);
      $this->assertFalse($this->status->get($flag), "Flag $flag should be clear");
    }
  }

  public function testMultipleFlagsSimultaneously(): void
  {
    
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
    
    $this->status->fromInt(0b11000011); 

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
    
    $this->status->set(StatusRegister::NEGATIVE, true);  
    $this->status->set(StatusRegister::OVERFLOW, true);  
    $this->status->set(StatusRegister::UNUSED, true);    
    $this->status->set(StatusRegister::CARRY, true);     

    $expected = 0b11100001; 
    $this->assertEquals($expected, $this->status->toInt());
  }

  public function testRoundTripConversion(): void
  {
    
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
    
    $this->status->fromInt(0b00100100); 

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
    
    $this->status->fromInt(0x100); 
    $this->assertEquals(0x00, $this->status->toInt());

    $this->status->fromInt(0x1FF); 
    $this->assertEquals(0xFF, $this->status->toInt());
  }

  public function testOverflowFlagPosition(): void
  {
    
    $this->status->fromInt(0b01000000); 

    $this->assertTrue($this->status->get(StatusRegister::OVERFLOW));
    $this->assertFalse($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::NEGATIVE));
  }

  public function testUnusedBitPosition(): void
  {
    
    $this->status->fromInt(0b00100000); 

    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::OVERFLOW));
    $this->assertFalse($this->status->get(StatusRegister::BREAK_COMMAND));
  }

  public function testBreakCommandBitPosition(): void
  {
    
    $this->status->fromInt(0b00010000); 

    $this->assertTrue($this->status->get(StatusRegister::BREAK_COMMAND));
    $this->assertFalse($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::DECIMAL_MODE));
  }

  public function testAllFlagsPattern(): void
  {
    
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
    

    
    $this->status->fromInt(0b00100100); 
    $this->assertTrue($this->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));

    
    $this->status->fromInt(0b00110100); 
    $this->assertTrue($this->status->get(StatusRegister::INTERRUPT_DISABLE));
    $this->assertTrue($this->status->get(StatusRegister::BREAK_COMMAND));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));

    
    $this->status->fromInt(0b10100000); 
    $this->assertTrue($this->status->get(StatusRegister::NEGATIVE));
    $this->assertTrue($this->status->get(StatusRegister::UNUSED));
    $this->assertFalse($this->status->get(StatusRegister::CARRY));
  }
}
