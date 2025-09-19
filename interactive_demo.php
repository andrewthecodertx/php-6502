<?php

require_once 'vendor/autoload.php';

use Emulator\Assembler\Assembler;
use Emulator\Memory;
use Emulator\CPU;
use Emulator\Bus\SystemBus;
use Emulator\Peripherals\GraphicsMode;
use Emulator\Peripherals\SoundController;
use Emulator\Peripherals\TerminalMode;
use Emulator\StatusRegister;
use Emulator\Bus\BusInterface;

echo "🎮 === THE 6502 EMULATOR - INTERACTIVE DEMO === 🎮\n\n";

function showCPUState($cpu, $instructionCount, $videoModeIndicator = "")
{
  // Position cursor at bottom of screen for debug info
  echo "\033[26;1H"; // Move to line 26
  echo "\033[K"; // Clear line
  echo "📊 CPU Registers: ";
  echo sprintf("PC=0x%04X ", $cpu->pc);
  echo sprintf("A=0x%02X ", $cpu->getAccumulator());
  echo sprintf("X=0x%02X ", $cpu->getRegisterX());
  echo sprintf("Y=0x%02X ", $cpu->getRegisterY());
  echo sprintf("SP=0x%02X ", $cpu->sp);
  echo sprintf("Instructions=%d ", $instructionCount);

  // Show flags
  echo "Flags: ";
  echo $cpu->status->get(StatusRegister::NEGATIVE) ? 'N' : '-';
  echo $cpu->status->get(StatusRegister::OVERFLOW) ? 'V' : '-';
  echo '-'; // Unused bit
  echo $cpu->status->get(StatusRegister::BREAK_COMMAND) ? 'B' : '-';
  echo $cpu->status->get(StatusRegister::DECIMAL_MODE) ? 'D' : '-';
  echo $cpu->status->get(StatusRegister::INTERRUPT_DISABLE) ? 'I' : '-';
  echo $cpu->status->get(StatusRegister::ZERO) ? 'Z' : '-';
  echo $cpu->status->get(StatusRegister::CARRY) ? 'C' : '-';

  // Show video mode indicator
  if ($videoModeIndicator) {
    echo "\033[27;1H"; // Move to line 27
    echo "\033[K"; // Clear line
    echo $videoModeIndicator;
  }

  // Position cursor back to display area
  // echo "\033[1;1H";
}

// Global variables to track video mode access
$GLOBALS['graphics_mode_used'] = false;
$GLOBALS['terminal_mode_used'] = false;

class VideoModeMonitorBus implements BusInterface
{
  private $wrappedBus;

  public function __construct(BusInterface $bus)
  {
    $this->wrappedBus = $bus;
  }

  public function read(int $address): int
  {
    return $this->wrappedBus->read($address);
  }

  public function write(int $address, int $value): void
  {
    // Monitor writes to detect video mode usage
    if ($address >= 0xC000 && $address <= 0xC3EC) {
      $GLOBALS['graphics_mode_used'] = true;
    } elseif ($address >= 0xD000 && $address <= 0xD003) {
      $GLOBALS['terminal_mode_used'] = true;
    }

    $this->wrappedBus->write($address, $value);
  }

  public function tick(): void
  {
    $this->wrappedBus->tick();
  }

  public function addPeripheral($peripheral): void
  {
    $this->wrappedBus->addPeripheral($peripheral);
  }
}

function detectVideoMode()
{
  $graphicsUsed = $GLOBALS['graphics_mode_used'];
  $terminalUsed = $GLOBALS['terminal_mode_used'];

  if ($graphicsUsed && $terminalUsed) {
    return "🎨📺 Video Mode: Mixed (Graphics + Terminal)";
  } elseif ($graphicsUsed) {
    return "🎨 Video Mode: Graphics Mode (\$C000-\$C3EC) - Direct Memory Mapping";
  } elseif ($terminalUsed) {
    return "📺 Video Mode: Terminal Mode (\$D000-\$D003) - Character Stream";
  } else {
    return "⚪ Video Mode: Auto-detecting...";
  }
}

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
    $modeHint = "";
    if (strpos(strtolower($demo['name']), 'hello_world') !== false) {
      $modeHint = " [Terminal Mode - \$D000]";
    } elseif (strpos(strtolower($demo['name']), 'hello') !== false && strpos(strtolower($demo['name']), 'hello_world') === false) {
      $modeHint = " [Graphics Mode - \$C000]";
    }
    echo sprintf("  %d) %s%s\n", $key, $demo['name'], $modeHint);
  }

  echo "\n💡 Graphics Mode: Direct video memory (\$C000-\$C3EC)\n";
  echo "💡 Terminal Mode: Character stream I/O (\$D000-\$D003)\n";
  echo "\n  Q) Quit\n\n";
}

function getUserChoice()
{
  echo "Enter your choice: ";
  $choice = trim(fgets(STDIN));
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

  // Reset video mode detection
  $GLOBALS['graphics_mode_used'] = false;
  $GLOBALS['terminal_mode_used'] = false;

  $memory = new Memory();
  $systemBus = new SystemBus($memory);
  $display = new GraphicsMode();
  $sound = new SoundController();
  $console = new TerminalMode($display);

  $systemBus->addPeripheral($display);
  $systemBus->addPeripheral($sound);
  $systemBus->addPeripheral($console);

  // Wrap the bus with video mode monitoring
  $bus = new VideoModeMonitorBus($systemBus);
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

  echo "▶️  Starting execution...\n\n";

  $cpu->reset();

  // Initial refresh to show starting state
  $console->refresh();

  echo "\n💡 Watch the CPU state at the bottom of the screen\n";
  echo "💡 Program will run automatically\n\n";

  try {
    $instructionCount = 0;
    $maxInstructions = 50000;
    $lastPC = $cpu->pc;
    $stuckCount = 0;

    while ($instructionCount < $maxInstructions) {
      // Execute one instruction
      $cpu->executeInstruction();
      $instructionCount++;

      // Refresh display and show CPU state every instruction
      $console->refresh();
      $videoModeIndicator = detectVideoMode();
      showCPUState($cpu, $instructionCount, $videoModeIndicator);

      // Check for infinite loop
      if ($cpu->pc == $lastPC) {
        $stuckCount++;
        if ($stuckCount > 5) {
          // Final refresh to show last display state
          $console->refresh();
          $videoModeIndicator = detectVideoMode();
          showCPUState($cpu, $instructionCount, $videoModeIndicator);
          echo "\033[30;1H"; // Move to line 30
          echo "🔄 Infinite loop detected at PC=0x" . sprintf('%04X', $cpu->pc) . "\n";
          echo "✅ Program completed successfully! Total instructions: $instructionCount\n";
          break;
        }
      } else {
        $stuckCount = 0;
        $lastPC = $cpu->pc;
      }
    }

    if ($instructionCount >= $maxInstructions) {
      echo "\n\n⏱️  Program completed (instruction limit reached)\n";
    }
  } catch (Exception $e) {
    echo "\n\n💥 Program error: " . $e->getMessage() . "\n";
    echo "🎯 PC: 0x" . sprintf('%04X', $cpu->pc) . "\n";
  }

  // Final refresh to ensure all output is shown
  $console->refresh();

  echo "\033[31;1H"; // Move to line 31
  echo str_repeat("=", 50) . "\n";
  echo "Returning to main menu in 3 seconds...\n";
  sleep(3);
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
    if (empty($choice)) {
      echo "\n❓ No choice entered. Please enter a number (1-" . count($demos) . ") or Q to quit.\n";
    } else {
      echo "\n❌ Invalid choice '$choice'. Please enter a number (1-" . count($demos) . ") or Q to quit.\n";
    }
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
