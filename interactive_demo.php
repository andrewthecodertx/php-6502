<?php

require_once 'vendor/autoload.php';

use Emulator\Assembler\Assembler;
use Emulator\Memory;
use Emulator\CPU;
use Emulator\Bus\SystemBus;
use Emulator\Peripherals\TextDisplay;
use Emulator\Peripherals\SoundController;
use Emulator\Peripherals\EnhancedConsole;

echo "🎮 === THE 6502 EMULATOR - INTERACTIVE DEMO === 🎮\n\n";

function discoverPrograms()
{
  $demos = [];
  $files = glob('examples/*.asm');

  $index = 1;
  foreach ($files as $file) {
    $name = basename($file, '.asm');
    $demos[$index] = [
      'file' => $file,
      'name' => ucfirst($name)
    ];
    $index++;
  }

  return $demos;
}

function showMenu($demos)
{
  echo "Available Demo Programs:\n";
  echo "========================\n\n";

  foreach ($demos as $key => $demo) {
    echo sprintf("  %d) %s\n", $key, $demo['name']);
  }

  echo "\n  Q) Quit\n\n";
}

function getUserChoice()
{
  echo "Enter your choice: ";
  $handle = fopen("php://stdin", "r");
  $choice = trim(fgets($handle));
  fclose($handle);
  return strtolower($choice);
}

function runProgram($assemblyFile)
{
  echo "\n" . str_repeat("=", 50) . "\n";
  echo "🚀 Running: " . basename($assemblyFile) . "\n";
  echo str_repeat("=", 50) . "\n\n";

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

  echo "🔧 Creating enhanced 6502 system...\n";

  $memory = new Memory();
  $bus = new SystemBus($memory);

  $display = new TextDisplay();
  $sound = new SoundController();
  $console = new EnhancedConsole($display);

  $bus->addPeripheral($display);
  $bus->addPeripheral($sound);
  $bus->addPeripheral($console);

  $cpu = new CPU($bus);

  foreach ($program as $addr => $byte) {
    $memory->write_byte($addr, $byte);
  }

  if (!isset($program[0xFFFC]) && !isset($program[0xFFFD])) {
    $startAddr = min(array_keys($program));
    $memory->write_byte(0xFFFC, $startAddr & 0xFF);
    $memory->write_byte(0xFFFD, ($startAddr >> 8) & 0xFF);
    echo "🔄 Set reset vector to: 0x" . sprintf('%04X', $startAddr) . "\n";
  }

  echo "▶️  Starting execution...\n";
  echo "💡 Press 'q' + Enter to return to menu, or Ctrl+C to stop\n\n";

  stream_set_blocking(STDIN, false);

  $cpu->reset();

  // Initial refresh to show starting state
  $console->refresh();

  try {
    $instructionCount = 0;
    $lastRefresh = microtime(true);
    $maxInstructions = 50000;
    $lastPC = $cpu->pc;
    $stuckCount = 0;

    while ($instructionCount < $maxInstructions) {
      $input = fread(STDIN, 1024);
      if ($input !== false && trim(strtolower($input)) === 'q') {
        echo "\n\n🔙 Returning to main menu...\n";
        break;
      }

      $cpu->executeInstruction();
      $instructionCount++;

      if ($cpu->pc == $lastPC) {
        $stuckCount++;

        if ($stuckCount > 1000) {
          echo "\n\n✅ Program completed (infinite loop detected)\n";
          break;
        }
      } else {
        $stuckCount = 0;
        $lastPC = $cpu->pc;
      }

      $now = microtime(true);
      if ($now - $lastRefresh > 0.1) {
        $console->refresh();
        $lastRefresh = $now;
      }

      usleep(500);
    }

    if ($instructionCount >= $maxInstructions) {
      echo "\n\n⏱️  Program completed (instruction limit reached)\n";
    }
  } catch (Exception $e) {
    echo "\n\n💥 Program error: " . $e->getMessage() . "\n";
    echo "🎯 PC: 0x" . sprintf('%04X', $cpu->pc) . "\n";
  }

  $console->refresh();

  while (($buffer = fread(STDIN, 1024)) !== false && strlen($buffer) > 0) {
    
  }

  stream_set_blocking(STDIN, true);

  echo "\n" . str_repeat("=", 50) . "\n";
  echo "Press Enter to continue...";
  fgets(STDIN);
}

while (true) {
  echo "\033[2J\033[H";
  echo "🎮 === THE 6502 EMULATOR - INTERACTIVE DEMO === 🎮\n\n";

  $demos = discoverPrograms();
  showMenu($demos);
  $choice = getUserChoice();

  if ($choice === 'q') {
    echo "\n👋 Thanks for using the 6502 emulator!\n";
    break;
  }

  if (is_numeric($choice)) {
    $choice = (int)$choice;
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
