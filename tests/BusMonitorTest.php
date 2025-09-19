<?php

declare(strict_types=1);

namespace Test;

use PHPUnit\Framework\TestCase;
use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;
use Emulator\CPU;

class BusMonitorTest extends TestCase
{
  private BusMonitor $busMonitor;
  private MonitoredMemory $memory;

  protected function setUp(): void
  {
    $this->busMonitor = new BusMonitor();
    $this->memory = new MonitoredMemory($this->busMonitor);
  }

  public function testBusMonitorInitialization(): void
  {
    $this->assertInstanceOf(BusMonitor::class, $this->busMonitor);
    $this->assertEquals([], $this->busMonitor->getBusActivity());
    $this->assertEquals(0, $this->busMonitor->getCurrentCycle());
  }

  public function testMemoryReadMonitoring(): void
  {
    $this->memory->write_byte(0x1000, 0x42);
    $this->busMonitor->reset(); 

    $value = $this->memory->read_byte(0x1000);
    $this->assertEquals(0x42, $value);

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(1, $activity);

    $operation = $activity[0];
    $this->assertEquals(0x1000, $operation['address']);
    $this->assertEquals(0x42, $operation['data']);
    $this->assertEquals('R', $operation['operation']);
  }

  public function testMemoryWriteMonitoring(): void
  {
    $this->memory->write_byte(0x1000, 0x84);

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(1, $activity);

    $operation = $activity[0];
    $this->assertEquals(0x1000, $operation['address']);
    $this->assertEquals(0x84, $operation['data']);
    $this->assertEquals('W', $operation['operation']);
  }

  public function testMultipleOperationsMonitoring(): void
  {
    $this->memory->write_byte(0x1000, 0x42);
    $this->memory->write_byte(0x1001, 0x84);
    $this->memory->read_byte(0x1000);
    $this->memory->read_byte(0x1001);

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(4, $activity);

    
    $this->assertEquals('W', $activity[0]['operation']);
    $this->assertEquals(0x1000, $activity[0]['address']);
    $this->assertEquals(0x42, $activity[0]['data']);

    $this->assertEquals('W', $activity[1]['operation']);
    $this->assertEquals(0x1001, $activity[1]['address']);
    $this->assertEquals(0x84, $activity[1]['data']);

    
    $this->assertEquals('R', $activity[2]['operation']);
    $this->assertEquals(0x1000, $activity[2]['address']);
    $this->assertEquals(0x42, $activity[2]['data']);

    $this->assertEquals('R', $activity[3]['operation']);
    $this->assertEquals(0x1001, $activity[3]['address']);
    $this->assertEquals(0x84, $activity[3]['data']);
  }

  public function testWordOperationsMonitoring(): void
  {
    $this->memory->write_word(0x1000, 0x1234);

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(2, $activity); 

    
    $this->assertEquals('W', $activity[0]['operation']);
    $this->assertEquals(0x1000, $activity[0]['address']);
    $this->assertEquals(0x34, $activity[0]['data']); 

    $this->assertEquals('W', $activity[1]['operation']);
    $this->assertEquals(0x1001, $activity[1]['address']);
    $this->assertEquals(0x12, $activity[1]['data']); 
  }

  public function testBusMonitorReset(): void
  {
    $this->memory->write_byte(0x1000, 0x42);
    $this->memory->read_byte(0x1000);

    $this->assertCount(2, $this->busMonitor->getBusActivity());

    $this->busMonitor->reset();

    $this->assertCount(0, $this->busMonitor->getBusActivity());
    $this->assertEquals(0, $this->busMonitor->getCurrentCycle());
  }

  public function testCycleCountIncrement(): void
  {
    $this->assertEquals(0, $this->busMonitor->getCurrentCycle());

    $this->busMonitor->incrementCycle();
    $this->assertEquals(1, $this->busMonitor->getCurrentCycle());

    $this->busMonitor->incrementCycle();
    $this->assertEquals(2, $this->busMonitor->getCurrentCycle());

    $this->busMonitor->reset();
    $this->assertEquals(0, $this->busMonitor->getCurrentCycle());
  }

  public function testLogBusOperation(): void
  {
    $this->busMonitor->logBusOperation(0x2000, 0x99, 'R');

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(1, $activity);

    $operation = $activity[0];
    $this->assertEquals(0x2000, $operation['address']);
    $this->assertEquals(0x99, $operation['data']);
    $this->assertEquals('R', $operation['operation']);
  }

  public function testMonitoredCPUIntegration(): void
  {
    $cpu = new MonitoredCPU($this->memory, false);

    
    $this->memory->write_byte(0xFFFC, 0x00);
    $this->memory->write_byte(0xFFFD, 0x80);

    $this->busMonitor->reset();
    $cpu->reset(); 

    $activity = $this->busMonitor->getBusActivity();
    $this->assertEquals(7, count($activity)); 

    
    $this->assertEquals('R', $activity[0]['operation']); 
    $this->assertEquals('R', $activity[1]['operation']); 
    $this->assertEquals('R', $activity[2]['operation']); 
    $this->assertEquals('R', $activity[3]['operation']); 
    $this->assertEquals('R', $activity[4]['operation']); 
    $this->assertEquals('R', $activity[5]['operation']); 
    $this->assertEquals('R', $activity[6]['operation']); 

    
    $this->assertEquals(0xFFFC, $activity[5]['address']);
    $this->assertEquals(0x00, $activity[5]['data']);
    $this->assertEquals(0xFFFD, $activity[6]['address']);
    $this->assertEquals(0x80, $activity[6]['data']);
  }

  public function testStackOperationsMonitoring(): void
  {
    $cpu = new CPU($this->memory);
    $cpu->sp = 0xFF;

    $this->busMonitor->reset();

    
    $cpu->pushByte(0x42);
    $cpu->pushByte(0x84);

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(2, $activity);

    
    $this->assertEquals('W', $activity[0]['operation']);
    $this->assertEquals(0x01FF, $activity[0]['address']); 
    $this->assertEquals(0x42, $activity[0]['data']);

    $this->assertEquals('W', $activity[1]['operation']);
    $this->assertEquals(0x01FE, $activity[1]['address']);
    $this->assertEquals(0x84, $activity[1]['data']);

    $this->busMonitor->reset();

    
    $value1 = $cpu->pullByte();
    $value2 = $cpu->pullByte();

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(2, $activity);

    
    $this->assertEquals('R', $activity[0]['operation']);
    $this->assertEquals(0x01FE, $activity[0]['address']);
    $this->assertEquals(0x84, $activity[0]['data']);
    $this->assertEquals(0x84, $value1);

    $this->assertEquals('R', $activity[1]['operation']);
    $this->assertEquals(0x01FF, $activity[1]['address']);
    $this->assertEquals(0x42, $activity[1]['data']);
    $this->assertEquals(0x42, $value2);
  }

  public function testZeroPageOperationsMonitoring(): void
  {
    
    $this->memory->write_byte(0x00, 0x11);
    $this->memory->write_byte(0x80, 0x22);
    $this->memory->write_byte(0xFF, 0x33);

    $this->busMonitor->reset();

    $this->memory->read_byte(0x00);
    $this->memory->read_byte(0x80);
    $this->memory->read_byte(0xFF);

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(3, $activity);

    $this->assertEquals(0x00, $activity[0]['address']);
    $this->assertEquals(0x11, $activity[0]['data']);
    $this->assertEquals(0x80, $activity[1]['address']);
    $this->assertEquals(0x22, $activity[1]['data']);
    $this->assertEquals(0xFF, $activity[2]['address']);
    $this->assertEquals(0x33, $activity[2]['data']);
  }

  public function testHighMemoryOperationsMonitoring(): void
  {
    
    $this->memory->write_byte(0xFFFE, 0xAA);
    $this->memory->write_byte(0xFFFF, 0xBB);

    $this->busMonitor->reset();

    $this->memory->read_byte(0xFFFE);
    $this->memory->read_byte(0xFFFF);

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(2, $activity);

    $this->assertEquals(0xFFFE, $activity[0]['address']);
    $this->assertEquals(0xAA, $activity[0]['data']);
    $this->assertEquals(0xFFFF, $activity[1]['address']);
    $this->assertEquals(0xBB, $activity[1]['data']);
  }

  public function testActivityDataIntegrity(): void
  {
    
    $testData = [
      [0x1000, 0x42, 'W'],
      [0x2000, 0x84, 'R'],
      [0x0080, 0xFF, 'W'],
      [0xFFFC, 0x00, 'R'],
    ];

    foreach ($testData as [$address, $data, $operation]) {
      $this->busMonitor->logBusOperation($address, $data, $operation);
    }

    $activity = $this->busMonitor->getBusActivity();
    $this->assertCount(4, $activity);

    for ($i = 0; $i < 4; $i++) {
      $this->assertEquals($testData[$i][0], $activity[$i]['address']);
      $this->assertEquals($testData[$i][1], $activity[$i]['data']);
      $this->assertEquals($testData[$i][2], $activity[$i]['operation']);
    }
  }
}
