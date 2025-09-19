<?php

require_once 'vendor/autoload.php';

use Emulator\Assembler\Assembler;
use Emulator\Memory;
use Emulator\EnhancedCPU;
use Emulator\Bus\SystemBus;
use Emulator\Peripherals\TextDisplay;
use Emulator\Peripherals\SoundController;
use Emulator\Peripherals\EnhancedConsole;

echo "🎮 === AUTO-RUNNING 6502 ENHANCED SYSTEM DEMO === 🎮\n\n";
echo "This demo automatically showcases all system features\n";
echo "using assembly language instead of hard-coded opcodes!\n\n";

// Assemble the showcase program
$assembler = new Assembler();
$showcaseFile = 'examples/showcase.asm';

try {
  echo "🔧 Assembling showcase program...\n";
  $program = $assembler->assembleFile($showcaseFile);
  $labels = $assembler->getLabels();

  echo "✅ Assembly successful!\n";
  echo "📊 Program size: " . count($program) . " bytes\n";
  echo "🏷️  Labels found: " . count($labels) . "\n\n";

  // Show some labels for debugging
  if (!empty($labels)) {
    echo "📋 Key subroutines found:\n";
    foreach ($labels as $label => $address) {
      if (in_array($label, ['main', 'welcome', 'display_demo', 'sound_demo', 'color_demo', 'finale'])) {
        echo sprintf("   %s = $%04X\n", $label, $address);
      }
    }
    echo "\n";
  }

} catch (Exception $e) {
  echo "❌ Assembly failed: " . $e->getMessage() . "\n";
  exit(1);
}

// Create the enhanced system
echo "🔧 Creating enhanced 6502 system...\n";

$memory = new Memory();
$bus = new SystemBus($memory);

$display = new TextDisplay();
$sound = new SoundController();
$console = new EnhancedConsole($display);

$bus->addPeripheral($display);
$bus->addPeripheral($sound);
$bus->addPeripheral($console);

$cpu = new EnhancedCPU($bus);

// Load program into memory
foreach ($program as $addr => $byte) {
  $memory->write_byte($addr, $byte);
}

echo "💾 Program loaded into memory\n";
echo "▶️  Starting execution...\n";
echo "💡 The demo will run automatically and cycle through features\n\n";

$cpu->reset();

try {
  $instructionCount = 0;
  $lastRefresh = microtime(true);
  $maxInstructions = 100000; // Let it run longer for full demo

  while ($instructionCount < $maxInstructions) {
    $cpu->executeInstruction();
    $instructionCount++;

    // Refresh display more frequently for smooth animation
    $now = microtime(true);
    if ($now - $lastRefresh > 0.05) {  // 50ms refresh
      $console->refresh();
      $lastRefresh = $now;

      // Show status every few seconds
      if ($instructionCount % 1000 == 0) {
        echo sprintf("\r📈 Instructions: %d | 🎯 PC: 0x%04X | 🕒 Running...",
          $instructionCount, $cpu->pc);
        flush();
      }
    }

    // Faster execution for demo
    usleep(500); // 0.5ms delay
  }

  echo "\n\n⏱️  Demo completed (instruction limit reached)\n";

} catch (Exception $e) {
  echo "\n\n💥 Program error: " . $e->getMessage() . "\n";
  echo "🎯 PC: 0x" . sprintf('%04X', $cpu->pc) . "\n";
  echo "📊 Instructions executed: $instructionCount\n";
}

// Final display refresh
$console->refresh();

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 Enhanced 6502 System Demo Complete!\n";
echo "\n";
echo "Features demonstrated:\n";
echo "  ✅ Assembly language programming\n";
echo "  ✅ 40x25 text display with colors\n";
echo "  ✅ 4-channel sound synthesis\n";
echo "  ✅ Memory-mapped I/O\n";
echo "  ✅ Subroutine calls and program flow\n";
echo "  ✅ Real-time display updates\n";
echo "\n";
echo "🚀 System ready for development!\n";
echo str_repeat("=", 60) . "\n";