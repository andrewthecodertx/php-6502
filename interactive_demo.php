<?php

require_once 'vendor/autoload.php';

use Emulator\Assembler\Assembler;
use Emulator\Memory;
use Emulator\EnhancedCPU;
use Emulator\Bus\SystemBus;
use Emulator\Peripherals\TextDisplay;
use Emulator\Peripherals\SoundController;
use Emulator\Peripherals\EnhancedConsole;

echo "🎮 === ENHANCED 6502 SYSTEM - INTERACTIVE DEMO === 🎮\n\n";

// Available demo programs
$demos = [
  'hello' => [
    'file' => 'examples/hello.asm',
    'name' => 'Hello World',
    'description' => 'Classic hello world with string output'
  ],
  'welcome' => [
    'file' => 'examples/welcome.asm',
    'name' => 'Welcome Message',
    'description' => 'Colorful welcome message demonstration'
  ],
  'colors' => [
    'file' => 'examples/colors.asm',
    'name' => 'Color Demo',
    'description' => 'Cycles through all 16 text colors'
  ],
  'sound' => [
    'file' => 'examples/sound.asm',
    'name' => 'Sound Demo',
    'description' => 'Plays a melody with harmony on multiple channels'
  ],
  'counter' => [
    'file' => 'examples/counter.asm',
    'name' => 'Counter Demo',
    'description' => 'Live counting display from 0 to 99'
  ]
];

function showMenu($demos) {
  echo "Available Demo Programs:\n";
  echo "========================\n\n";

  foreach ($demos as $key => $demo) {
    echo sprintf("  %s) %s\n", strtoupper($key), $demo['name']);
    echo sprintf("     %s\n\n", $demo['description']);
  }

  echo "  Q) Quit\n\n";
}

function getUserChoice() {
  echo "Enter your choice: ";
  $handle = fopen("php://stdin", "r");
  $choice = trim(fgets($handle));
  fclose($handle);
  return strtolower($choice);
}

function runProgram($assemblyFile) {
  echo "\n" . str_repeat("=", 50) . "\n";
  echo "🚀 Running: " . basename($assemblyFile) . "\n";
  echo str_repeat("=", 50) . "\n\n";

  // Assemble the program
  $assembler = new Assembler();

  try {
    echo "Assembling program...\n";
    $program = $assembler->assembleFile($assemblyFile);
    $labels = $assembler->getLabels();

    echo "✅ Assembly successful!\n";
    echo "📊 Program size: " . count($program) . " bytes\n";
    echo "🏷️  Labels found: " . count($labels) . "\n\n";

  } catch (Exception $e) {
    echo "❌ Assembly failed: " . $e->getMessage() . "\n\n";
    return;
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

  // Set reset vector if not specified
  if (!isset($program[0xFFFC]) && !isset($program[0xFFFD])) {
    $startAddr = min(array_keys($program));
    $memory->write_byte(0xFFFC, $startAddr & 0xFF);
    $memory->write_byte(0xFFFD, ($startAddr >> 8) & 0xFF);
    echo "🔄 Set reset vector to: 0x" . sprintf('%04X', $startAddr) . "\n";
  }

  echo "▶️  Starting execution...\n";
  echo "💡 Press Ctrl+C to stop\n\n";

  $cpu->reset();

  try {
    $instructionCount = 0;
    $lastRefresh = microtime(true);
    $maxInstructions = 50000; // Reasonable limit for demos

    while ($instructionCount < $maxInstructions) {
      $cpu->executeInstruction();
      $instructionCount++;

      // Refresh display periodically
      $now = microtime(true);
      if ($now - $lastRefresh > 0.1) {
        $console->refresh();
        $lastRefresh = $now;

        // Show status
        echo sprintf("\r📈 Instructions: %d | 🎯 PC: 0x%04X",
          $instructionCount, $cpu->pc);
        flush();
      }

      // Realistic timing
      usleep(1000); // 1ms delay
    }

    echo "\n\n⏱️  Program completed (instruction limit reached)\n";

  } catch (Exception $e) {
    echo "\n\n💥 Program error: " . $e->getMessage() . "\n";
    echo "🎯 PC: 0x" . sprintf('%04X', $cpu->pc) . "\n";
  }

  // Final display refresh
  $console->refresh();

  echo "\n" . str_repeat("=", 50) . "\n";
  echo "Press Enter to continue...";
  fgets(STDIN);
}

// Main program loop
while (true) {
  // Clear screen
  echo "\033[2J\033[H";

  echo "🎮 === ENHANCED 6502 SYSTEM - INTERACTIVE DEMO === 🎮\n\n";

  showMenu($demos);
  $choice = getUserChoice();

  if ($choice === 'q') {
    echo "\n👋 Thanks for using the Enhanced 6502 System!\n";
    break;
  }

  if (!isset($demos[$choice])) {
    echo "\n❌ Invalid choice. Please try again.\n";
    echo "Press Enter to continue...";
    fgets(STDIN);
    continue;
  }

  $demo = $demos[$choice];

  if (!file_exists($demo['file'])) {
    echo "\n❌ Demo file not found: " . $demo['file'] . "\n";
    echo "Press Enter to continue...";
    fgets(STDIN);
    continue;
  }

  runProgram($demo['file']);
}