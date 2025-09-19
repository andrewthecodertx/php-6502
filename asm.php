<?php

require_once 'vendor/autoload.php';

use Emulator\Assembler\Assembler;
use Emulator\Memory;
use Emulator\CPU;
use Emulator\Bus\SystemBus;
use Emulator\Peripherals\TextDisplay;
use Emulator\Peripherals\SoundController;
use Emulator\Peripherals\EnhancedConsole;

function showUsage(): void
{
  echo "6502 Assembler Tool\n\n";
  echo "Usage:\n";
  echo "  php asm.php assemble <input.asm> [output.bin]  - Assemble source to binary\n";
  echo "  php asm.php run <input.asm>                    - Assemble and run program\n";
  echo "  php asm.php disasm <input.bin> [start_addr]    - Disassemble binary\n";
  echo "  php asm.php labels <input.asm>                 - Show label table\n\n";
  echo "Examples:\n";
  echo "  php asm.php run examples/hello.asm\n";
  echo "  php asm.php assemble program.asm program.bin\n";
  echo "  php asm.php disasm program.bin 0x8000\n";
}

function assembleFile(string $inputFile, ?string $outputFile = null): array
{
  $assembler = new Assembler();

  echo "Assembling: $inputFile\n";

  try {
    $program = $assembler->assembleFile($inputFile);
    $labels = $assembler->getLabels();

    echo "Assembly successful!\n";
    echo "Program size: " . count($program) . " bytes\n";
    echo "Labels found: " . count($labels) . "\n";

    if ($outputFile) {
      $data = '';
      $minAddr = min(array_keys($program));
      $maxAddr = max(array_keys($program));

      for ($addr = $minAddr; $addr <= $maxAddr; $addr++) {
        $data .= chr($program[$addr] ?? 0);
      }

      file_put_contents($outputFile, $data);
      echo "Binary saved to: $outputFile\n";
    }

    return $program;
  } catch (Exception $e) {
    echo "Assembly failed: " . $e->getMessage() . "\n";
    exit(1);
  }
}

function runProgram(string $inputFile): void
{
  $program = assembleFile($inputFile);

  echo "\nCreating enhanced 6502 system...\n";

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
    echo "Set reset vector to: 0x" . sprintf('%04X', $startAddr) . "\n";
  }

  echo "Running program...\n";
  echo "Press 'q' + Enter to quit\n\n";

  stream_set_blocking(STDIN, false);

  $cpu->reset();

  try {
    $instructionCount = 0;
    $lastRefresh = microtime(true);

    while ($instructionCount < 100000) {
      $input = fread(STDIN, 1024);
      if ($input !== false && trim(strtolower($input)) === 'q') {
        echo "\nQuitting...\n";
        break;
      }

      $cpu->executeInstruction();
      $instructionCount++;

      $now = microtime(true);
      if ($now - $lastRefresh > 0.05) {
        $console->refresh();
        $lastRefresh = $now;
      }

      usleep(100);
    }

    echo "\nProgram completed (instruction limit reached)\n";
  } catch (Exception $e) {
    echo "\nProgram error: " . $e->getMessage() . "\n";
    echo "PC: 0x" . sprintf('%04X', $cpu->pc) . "\n";
  }

  stream_set_blocking(STDIN, true);

  $console->refresh();
}

function disassembleFile(string $inputFile, int $startAddr = 0): void
{
  if (!file_exists($inputFile)) {
    echo "File not found: $inputFile\n";
    exit(1);
  }

  $data = file_get_contents($inputFile);
  $program = [];

  for ($i = 0; $i < strlen($data); $i++) {
    $program[$startAddr + $i] = ord($data[$i]);
  }

  $assembler = new Assembler();
  $disassembly = $assembler->disassemble($program, $startAddr);

  echo "Disassembly of $inputFile (starting at 0x" . sprintf('%04X', $startAddr) . "):\n\n";
  echo $disassembly . "\n";
}

function showLabels(string $inputFile): void
{
  $assembler = new Assembler();

  try {
    $assembler->assembleFile($inputFile);
    $labels = $assembler->getLabels();

    echo "Label table for $inputFile:\n\n";

    if (empty($labels)) {
      echo "No labels found.\n";
      return;
    }

    ksort($labels);

    foreach ($labels as $label => $address) {
      echo sprintf("%-20s = $%04X (%d)\n", $label, $address, $address);
    }
  } catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
  }
}

if ($argc < 2) {
  showUsage();
  exit(1);
}

$command = $argv[1];

switch ($command) {
  case 'assemble':
    if ($argc < 3) {
      echo "Error: Input file required\n";
      showUsage();
      exit(1);
    }
    $inputFile = $argv[2];
    $outputFile = $argv[3] ?? null;
    assembleFile($inputFile, $outputFile);
    break;

  case 'run':
    if ($argc < 3) {
      echo "Error: Input file required\n";
      showUsage();
      exit(1);
    }
    $inputFile = $argv[2];
    runProgram($inputFile);
    break;

  case 'disasm':
    if ($argc < 3) {
      echo "Error: Input file required\n";
      showUsage();
      exit(1);
    }
    $inputFile = $argv[2];
    $startAddr = isset($argv[3]) ? hexdec(str_replace('0x', '', $argv[3])) : 0;
    disassembleFile($inputFile, $startAddr);
    break;

  case 'labels':
    if ($argc < 3) {
      echo "Error: Input file required\n";
      showUsage();
      exit(1);
    }
    $inputFile = $argv[2];
    showLabels($inputFile);
    break;

  default:
    echo "Error: Unknown command '$command'\n";
    showUsage();
    exit(1);
}
