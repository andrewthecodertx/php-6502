<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Emulator\BusMonitor;
use Emulator\MonitoredMemory;
use Emulator\MonitoredCPU;

class IntegrationTest extends TestCase
{
    public function testComplete6502SystemIntegration(): void
    {
        // Create a complete monitored 6502 system
        $busMonitor = new BusMonitor();
        $memory = new MonitoredMemory($busMonitor);
        $cpu = new MonitoredCPU($memory);

        // Set up a realistic memory layout
        $memory->initialize([
            // Some initial program code
            0x8000 => 0xA9, // LDA #$42
            0x8001 => 0x42,
            0x8002 => 0xEA, // NOP
            0x8003 => 0xEA, // NOP

            // Reset vector pointing to our program
            0xFFFC => 0x00, // Reset vector low byte
            0xFFFD => 0x80, // Reset vector high byte -> 0x8000

            // Some data in zero page
            0x00 => 0x11,
            0x01 => 0x22,
            0xFF => 0x33,

            // Some data in stack area
            0x01FF => 0xAA,
            0x01FE => 0xBB,
            0x01FD => 0xCC,
        ]);

        // Set initial CPU state
        $cpu->pc = 0x1234;
        $cpu->sp = 0x00;
        $cpu->setAccumulator(0x55);
        $cpu->setRegisterX(0xAA);
        $cpu->setRegisterY(0xFF);

        // Clear bus monitor and perform reset
        $busMonitor->reset();
        $cpu->reset();

        // Verify reset completed correctly
        $this->assertEquals(0x8000, $cpu->pc);
        $this->assertEquals(0xFD, $cpu->sp);
        $this->assertEquals(0x00, $cpu->getAccumulator()); // Cleared by emulator reset
        $this->assertEquals(0x00, $cpu->getRegisterX());
        $this->assertEquals(0x00, $cpu->getRegisterY());

        // Verify bus activity shows exactly 7 operations for reset
        $resetActivity = $busMonitor->getBusActivity();
        $this->assertEquals(7, count($resetActivity));

        // Verify the reset sequence pattern
        $this->assertEquals('R', $resetActivity[0]['operation']); // Dummy read PC
        $this->assertEquals('R', $resetActivity[1]['operation']); // Dummy read PC+1
        $this->assertEquals('R', $resetActivity[2]['operation']); // Stack read 1
        $this->assertEquals('R', $resetActivity[3]['operation']); // Stack read 2
        $this->assertEquals('R', $resetActivity[4]['operation']); // Stack read 3
        $this->assertEquals('R', $resetActivity[5]['operation']); // Reset vector low
        $this->assertEquals('R', $resetActivity[6]['operation']); // Reset vector high

        // Verify reset vector was read correctly
        $this->assertEquals(0xFFFC, $resetActivity[5]['address']);
        $this->assertEquals(0x00, $resetActivity[5]['data']);
        $this->assertEquals(0xFFFD, $resetActivity[6]['address']);
        $this->assertEquals(0x80, $resetActivity[6]['data']);

        // Test stack operations
        $busMonitor->reset();
        $cpu->sp = 0xFF;

        $cpu->pushByte(0x12);
        $cpu->pushWord(0x3456);
        $cpu->pushByte(0x78);

        $stackActivity = $busMonitor->getBusActivity();
        $this->assertEquals(4, count($stackActivity)); // 1 byte + 1 word + 1 byte = 4 operations

        // Verify stack operations
        $this->assertEquals('W', $stackActivity[0]['operation']);
        $this->assertEquals(0x01FF, $stackActivity[0]['address']);
        $this->assertEquals(0x12, $stackActivity[0]['data']);

        $this->assertEquals('W', $stackActivity[1]['operation']);
        $this->assertEquals(0x01FE, $stackActivity[1]['address']);
        $this->assertEquals(0x34, $stackActivity[1]['data']); // High byte first

        $this->assertEquals('W', $stackActivity[2]['operation']);
        $this->assertEquals(0x01FD, $stackActivity[2]['address']);
        $this->assertEquals(0x56, $stackActivity[2]['data']); // Low byte second

        $this->assertEquals('W', $stackActivity[3]['operation']);
        $this->assertEquals(0x01FC, $stackActivity[3]['address']);
        $this->assertEquals(0x78, $stackActivity[3]['data']);

        $this->assertEquals(0xFB, $cpu->sp); // After 4 pushes from 0xFF

        // Test pulling back
        $busMonitor->reset();
        $pulled1 = $cpu->pullByte();
        $pulled2 = $cpu->pullWord();
        $pulled3 = $cpu->pullByte();

        $this->assertEquals(0x78, $pulled1);
        $this->assertEquals(0x3456, $pulled2);
        $this->assertEquals(0x12, $pulled3);
        $this->assertEquals(0xFF, $cpu->sp); // Back to original

        $pullActivity = $busMonitor->getBusActivity();
        $this->assertEquals(4, count($pullActivity));

        // Verify all operations were reads
        foreach ($pullActivity as $op) {
            $this->assertEquals('R', $op['operation']);
        }
    }

    public function testMemorySystemBoundaries(): void
    {
        $busMonitor = new BusMonitor();
        $memory = new MonitoredMemory($busMonitor);

        // Test all major memory areas
        $testAreas = [
            // Zero Page
            [0x00, 0x11],
            [0x80, 0x22],
            [0xFF, 0x33],

            // Stack Page
            [0x0100, 0x44],
            [0x0180, 0x55],
            [0x01FF, 0x66],

            // General Memory
            [0x0200, 0x77],
            [0x1000, 0x88],
            [0x8000, 0x99],

            // High Memory
            [0xFFFE, 0xAA],
            [0xFFFF, 0xBB],
        ];

        $busMonitor->reset();

        foreach ($testAreas as [$address, $value]) {
            $memory->write_byte($address, $value);
        }

        $writeActivity = $busMonitor->getBusActivity();
        $this->assertEquals(count($testAreas), count($writeActivity));

        $busMonitor->reset();

        foreach ($testAreas as [$address, $expectedValue]) {
            $actualValue = $memory->read_byte($address);
            $this->assertEquals($expectedValue, $actualValue);
        }

        $readActivity = $busMonitor->getBusActivity();
        $this->assertEquals(count($testAreas), count($readActivity));

        // Verify all operations were logged correctly
        for ($i = 0; $i < count($testAreas); $i++) {
            $this->assertEquals($testAreas[$i][0], $readActivity[$i]['address']);
            $this->assertEquals($testAreas[$i][1], $readActivity[$i]['data']);
            $this->assertEquals('R', $readActivity[$i]['operation']);
        }
    }

    public function testStatusRegister6502Compliance(): void
    {
        $busMonitor = new BusMonitor();
        $memory = new MonitoredMemory($busMonitor);
        $cpu = new MonitoredCPU($memory);

        // Test reset state compliance
        $memory->write_byte(0xFFFC, 0x00);
        $memory->write_byte(0xFFFD, 0x80);

        $cpu->reset();

        // Verify I flag is set and unused bit is set per 6502 spec
        $this->assertTrue($cpu->status->get($cpu->status::INTERRUPT_DISABLE));
        $this->assertTrue($cpu->status->get($cpu->status::UNUSED));

        // Test accurate reset preserves registers
        $cpu->setAccumulator(0x42);
        $cpu->setRegisterX(0x84);
        $cpu->setRegisterY(0xC6);

        $cpu->accurateReset();

        // Registers should be preserved in accurate reset
        $this->assertEquals(0x42, $cpu->getAccumulator());
        $this->assertEquals(0x84, $cpu->getRegisterX());
        $this->assertEquals(0xC6, $cpu->getRegisterY());

        // But I flag should still be set
        $this->assertTrue($cpu->status->get($cpu->status::INTERRUPT_DISABLE));
        $this->assertTrue($cpu->status->get($cpu->status::UNUSED));
    }

    public function testAddressingModeCompliance(): void
    {
        $busMonitor = new BusMonitor();
        $memory = new MonitoredMemory($busMonitor);
        $cpu = new MonitoredCPU($memory);

        // Set up test data for various addressing modes
        $memory->initialize([
            // Immediate mode test
            0x8000 => 0x42,

            // Zero page test
            0x8010 => 0x20,
            0x20 => 0x84,

            // Absolute test
            0x8020 => 0x34,
            0x8021 => 0x12,
            0x1234 => 0xC6,

            // Indirect test data
            0x8030 => 0x40,
            0x40 => 0x78,
            0x41 => 0x56,
            0x5678 => 0x9A,
        ]);

        $cpu->pc = 0x8000;
        $addr = $cpu->getAddress('Immediate');
        $this->assertEquals(0x8000, $addr);
        $this->assertEquals(0x8001, $cpu->pc);

        $cpu->pc = 0x8010;
        $addr = $cpu->getAddress('Zero Page');
        $this->assertEquals(0x20, $addr);
        $this->assertEquals(0x8011, $cpu->pc);

        $cpu->pc = 0x8020;
        $addr = $cpu->getAddress('Absolute');
        $this->assertEquals(0x1234, $addr);
        $this->assertEquals(0x8022, $cpu->pc);

        // Test indexed modes with register values
        $cpu->setRegisterX(0x05);
        $cpu->setRegisterY(0x03);

        $cpu->pc = 0x8010;
        $addr = $cpu->getAddress('X-Indexed Zero Page');
        $this->assertEquals(0x25, $addr); // 0x20 + 0x05

        $cpu->pc = 0x8010;
        $addr = $cpu->getAddress('Y-Indexed Zero Page');
        $this->assertEquals(0x23, $addr); // 0x20 + 0x03

        $cpu->pc = 0x8020;
        $addr = $cpu->getAddress('X-Indexed Absolute');
        $this->assertEquals(0x1239, $addr); // 0x1234 + 0x05

        $cpu->pc = 0x8020;
        $addr = $cpu->getAddress('Y-Indexed Absolute');
        $this->assertEquals(0x1237, $addr); // 0x1234 + 0x03

        // Test indirect modes properly
        // For X-Indexed Zero Page Indirect: ($40,X) where X=5
        // This reads from zero page address 0x40+0x05=0x45 and 0x46
        $memory->write_byte(0x45, 0x78); // Low byte of target address
        $memory->write_byte(0x46, 0x56); // High byte of target address

        $cpu->pc = 0x8030;
        $cpu->setRegisterX(0x05);
        $addr = $cpu->getAddress('X-Indexed Zero Page Indirect');
        $this->assertEquals(0x5678, $addr); // Target address from 0x45/0x46

        // For Zero Page Indirect Y-Indexed: ($40),Y where Y=2
        // This reads address from 0x40/0x41, then adds Y
        $cpu->pc = 0x8030;
        $cpu->setRegisterY(0x02);
        $addr = $cpu->getAddress('Zero Page Indirect Y-Indexed');
        $this->assertEquals(0x567A, $addr); // 0x5678 + 0x02
    }
}