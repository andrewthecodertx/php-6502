<?php

declare(strict_types=1);

namespace Emulator\Assembler;

use Emulator\InstructionRegister;

class Assembler
{
  private InstructionRegister $instructionRegister;
  private array $labels = [];
  private array $program = [];
  private int $currentAddress = 0;
  private array $unresolvedReferences = [];

  public function __construct()
  {
    $this->instructionRegister = new InstructionRegister();
  }

  public function assemble(string $source): array
  {
    $this->reset();

    // First pass: collect labels and generate basic program
    $lines = $this->preprocessSource($source);
    $this->firstPass($lines);

    // Second pass: resolve label references
    $this->secondPass();

    return $this->program;
  }

  public function assembleFile(string $filename): array
  {
    if (!file_exists($filename)) {
      throw new \InvalidArgumentException("Assembly file not found: $filename");
    }

    $source = file_get_contents($filename);
    return $this->assemble($source);
  }

  private function reset(): void
  {
    $this->labels = [];
    $this->program = [];
    $this->currentAddress = 0;
    $this->unresolvedReferences = [];
  }

  private function preprocessSource(string $source): array
  {
    $lines = explode("\n", $source);
    $processed = [];

    foreach ($lines as $lineNum => $line) {
      // Remove comments
      $line = preg_replace('/;.*$/', '', $line);

      // Trim whitespace
      $line = trim($line);

      // Skip empty lines
      if (empty($line)) {
        continue;
      }

      $processed[] = [
        'line' => $line,
        'number' => $lineNum + 1,
        'original' => $line
      ];
    }

    return $processed;
  }

  private function firstPass(array $lines): void
  {
    foreach ($lines as $lineData) {
      $line = $lineData['line'];
      $lineNum = $lineData['number'];

      try {
        $this->processLine($line, $lineNum);
      } catch (\Exception $e) {
        throw new AssemblerException(
          "Line $lineNum: " . $e->getMessage() . "\n  -> $line"
        );
      }
    }
  }

  private function processLine(string $line, int $lineNum): void
  {
    // Handle origin directive
    if (preg_match('/^\*\s*=\s*\$([0-9A-Fa-f]+)/', $line, $matches)) {
      $this->currentAddress = hexdec($matches[1]);
      return;
    }

    // Handle labels
    if (preg_match('/^(\w+):(.*)$/', $line, $matches)) {
      $label = $matches[1];
      $this->labels[$label] = $this->currentAddress;

      // Process remainder of line if present
      $remainder = trim($matches[2]);
      if (!empty($remainder)) {
        $this->processInstruction($remainder, $lineNum);
      }
      return;
    }

    // Process instruction
    $this->processInstruction($line, $lineNum);
  }

  private function processInstruction(string $line, int $lineNum): void
  {
    // Parse instruction components
    $parts = preg_split('/\s+/', $line, 2);
    $mnemonic = strtoupper($parts[0]);
    $operand = isset($parts[1]) ? trim($parts[1]) : '';

    // Handle data directives
    if ($mnemonic === '.BYTE' || $mnemonic === 'DCB') {
      $this->processDataByte($operand);
      return;
    }

    if ($mnemonic === '.WORD' || $mnemonic === 'DCW') {
      $this->processDataWord($operand);
      return;
    }

    // Find matching opcode
    $addressingMode = $this->determineAddressingMode($operand, $mnemonic);
    $opcode = $this->instructionRegister->findOpcode($mnemonic, $addressingMode);

    if (!$opcode) {
      throw new \InvalidArgumentException("Unknown instruction: $mnemonic $operand (addressing mode: $addressingMode)");
    }

    // Generate instruction bytes
    $this->generateInstruction($opcode, $operand, $lineNum);
  }

  private function isBranchInstruction(string $mnemonic): bool
  {
    $branchInstructions = ['BEQ', 'BNE', 'BCC', 'BCS', 'BPL', 'BMI', 'BVC', 'BVS'];
    return in_array($mnemonic, $branchInstructions);
  }

  private function determineAddressingMode(string $operand, string $mnemonic = ''): string
  {
    if (empty($operand)) {
      return 'Implied';
    }

    // Immediate: #$42 or #42
    if (preg_match('/^#/', $operand)) {
      return 'Immediate';
    }

    // Accumulator: A
    if ($operand === 'A') {
      return 'Accumulator';
    }

    // Indirect: ($1234) or (label)
    if (preg_match('/^\(([^,)]+)\)$/', $operand)) {
      return 'Absolute Indirect';
    }

    // Indexed indirect: ($12,X)
    if (preg_match('/^\(\$?([0-9A-Fa-f]+),X\)$/i', $operand)) {
      return 'X-Indexed Zero Page Indirect';
    }

    // Indirect indexed: ($12),Y
    if (preg_match('/^\(\$?([0-9A-Fa-f]+)\),Y$/i', $operand)) {
      return 'Zero Page Indirect Y-Indexed';
    }

    // Check for indexed modes: ,X or ,Y
    if (preg_match('/,X$/i', $operand)) {
      $base = preg_replace('/,X$/i', '', $operand);
      if ($this->isZeroPage($base)) {
        return 'X-Indexed Zero Page';
      } else {
        return 'X-Indexed Absolute';
      }
    }

    if (preg_match('/,Y$/i', $operand)) {
      $base = preg_replace('/,Y$/i', '', $operand);
      if ($this->isZeroPage($base)) {
        return 'Y-Indexed Zero Page';
      } else {
        return 'Y-Indexed Absolute';
      }
    }

    // Check if it's zero page or absolute
    if ($this->isZeroPage($operand)) {
      return 'Zero Page';
    }

    // Check if this is a branch instruction with a label
    if ($this->isBranchInstruction($mnemonic) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $operand)) {
      return 'Relative';
    }

    // Default to absolute
    return 'Absolute';
  }

  private function isZeroPage(string $operand): bool
  {
    // Remove $ prefix if present
    $operand = ltrim($operand, '$');

    // Check if it's a hex number <= 0xFF
    if (preg_match('/^[0-9A-Fa-f]+$/', $operand)) {
      $value = hexdec($operand);
      return $value <= 0xFF;
    }

    // For labels, we can't determine zero page in first pass
    return false;
  }

  private function generateInstruction($opcode, string $operand, int $lineNum): void
  {
    $opcodeValue = $opcode->getOpcode();
    // Convert hex string to integer if needed
    if (is_string($opcodeValue) && strpos($opcodeValue, '0x') === 0) {
      $opcodeValue = hexdec(str_replace('0x', '', $opcodeValue));
    }


    $bytes = [$opcodeValue];
    $instructionSize = $opcode->getBytes();

    // Add operand bytes
    if ($instructionSize > 1) {
      $operandValue = $this->parseOperand($operand, $opcode->getAddressingMode(), $lineNum);

      if ($instructionSize === 2) {
        $bytes[] = $operandValue & 0xFF;
      } else if ($instructionSize === 3) {
        $bytes[] = $operandValue & 0xFF;        // Low byte
        $bytes[] = ($operandValue >> 8) & 0xFF; // High byte
      }
    }

    // Store in program
    foreach ($bytes as $byte) {
      if (is_string($byte) && strpos($byte, '0x') === 0) {
        $byte = hexdec(str_replace('0x', '', $byte));
      }
      $this->program[$this->currentAddress] = $byte;
      $this->currentAddress++;
    }
  }

  private function parseOperand(string $operand, string $addressingMode, int $lineNum): int
  {
    // Handle immediate values
    if (preg_match('/^#\$?([0-9A-Fa-f]+)$/i', $operand, $matches)) {
      return hexdec($matches[1]);
    }

    // Handle accumulator
    if ($operand === 'A') {
      return 0;
    }

    // Handle absolute/zero page values
    if (preg_match('/^\$([0-9A-Fa-f]+)$/i', $operand, $matches)) {
      return hexdec($matches[1]);
    }

    // Handle decimal values
    if (preg_match('/^([0-9]+)$/', $operand, $matches)) {
      return intval($matches[1]);
    }

    // Handle indexed addressing
    if (preg_match('/^(.+),([XY])$/i', $operand, $matches)) {
      return $this->parseOperand($matches[1], $addressingMode, $lineNum);
    }

    // Handle indirect addressing
    if (preg_match('/^\((.+)\)$/', $operand, $matches)) {
      return $this->parseOperand($matches[1], $addressingMode, $lineNum);
    }

    // Handle labels (unresolved reference)
    if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $operand)) {
      $size = 1; // Default for relative addressing
      if (strpos($addressingMode, 'Absolute') !== false) {
        $size = 2;
      }

      // The operand starts after the opcode byte, so current address + 1
      $operandAddress = $this->currentAddress + 1;

      $this->unresolvedReferences[] = [
        'address' => $operandAddress,
        'label' => $operand,
        'line' => $lineNum,
        'mode' => $addressingMode,
        'size' => $size
      ];
      return 0; // Placeholder
    }

    throw new \InvalidArgumentException("Cannot parse operand: $operand");
  }

  private function processDataByte(string $operand): void
  {
    // Handle string literals
    if (preg_match('/^"([^"]*)"(.*)$/', $operand, $matches)) {
      $string = $matches[1];
      $remaining = trim($matches[2]);

      // Add each character as a byte
      for ($i = 0; $i < strlen($string); $i++) {
        $this->program[$this->currentAddress] = ord($string[$i]);
        $this->currentAddress++;
      }

      // Process remaining values after the string
      if (!empty($remaining) && $remaining[0] === ',') {
        $remaining = trim(substr($remaining, 1));
        if (!empty($remaining)) {
          $this->processDataByte($remaining);
        }
      }
      return;
    }

    $values = explode(',', $operand);
    foreach ($values as $value) {
      $value = trim($value);

      if (preg_match('/^\$([0-9A-Fa-f]+)$/i', $value, $matches)) {
        $this->program[$this->currentAddress] = hexdec($matches[1]);
      } elseif (preg_match('/^([0-9]+)$/', $value)) {
        $this->program[$this->currentAddress] = intval($value);
      } else {
        throw new \InvalidArgumentException("Invalid byte value: $value");
      }

      $this->currentAddress++;
    }
  }

  private function processDataWord(string $operand): void
  {
    $values = explode(',', $operand);
    foreach ($values as $value) {
      $value = trim($value);

      if (preg_match('/^\$([0-9A-Fa-f]+)$/i', $value, $matches)) {
        $word = hexdec($matches[1]);
      } elseif (preg_match('/^([0-9]+)$/', $value)) {
        $word = intval($value);
      } elseif (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
        // Handle label reference
        $this->unresolvedReferences[] = [
          'address' => $this->currentAddress,
          'label' => $value,
          'line' => 0, // Line number not available in this context
          'mode' => 'Absolute',
          'size' => 2
        ];
        $word = 0; // Placeholder
      } else {
        throw new \InvalidArgumentException("Invalid word value: $value");
      }

      // Store as little-endian
      $this->program[$this->currentAddress] = $word & 0xFF;
      $this->program[$this->currentAddress + 1] = ($word >> 8) & 0xFF;
      $this->currentAddress += 2;
    }
  }

  private function secondPass(): void
  {
    foreach ($this->unresolvedReferences as $ref) {
      $label = $ref['label'];

      if (!isset($this->labels[$label])) {
        throw new AssemblerException("Undefined label: $label on line {$ref['line']}");
      }

      $value = $this->labels[$label];

      // Handle relative addressing for branches
      if (strpos($ref['mode'], 'Relative') !== false) {
        $offset = $value - ($ref['address'] + 1); // +1 because relative to next instruction
        if ($offset < -128 || $offset > 127) {
          throw new AssemblerException("Branch target too far: $label on line {$ref['line']}");
        }
        $this->program[$ref['address']] = $offset & 0xFF;
      } else {
        // Absolute addressing
        if ($ref['size'] === 1) {
          $this->program[$ref['address']] = $value & 0xFF;
        } else {
          $this->program[$ref['address']] = $value & 0xFF;
          $this->program[$ref['address'] + 1] = ($value >> 8) & 0xFF;
        }
      }
    }
  }

  public function getLabels(): array
  {
    return $this->labels;
  }

  public function disassemble(array $program, int $startAddress = 0): string
  {
    $output = [];
    $address = $startAddress;

    foreach ($program as $addr => $byte) {
      if ($addr < $address) continue;

      $opcodeHex = sprintf('0x%02X', $byte);
      $opcode = $this->instructionRegister->getOpcode($opcodeHex);

      if ($opcode) {
        $line = sprintf('%04X: %s', $addr, $opcode->getMnemonic());
        $bytes = [$byte];

        // Add operand bytes
        $size = $opcode->getBytes();
        for ($i = 1; $i < $size; $i++) {
          if (isset($program[$addr + $i])) {
            $bytes[] = $program[$addr + $i];
          }
        }

        // Format operand
        if ($size > 1) {
          $operand = $this->formatOperand($opcode->getAddressingMode(), array_slice($bytes, 1));
          $line .= " $operand";
        }

        $output[] = $line;
        $address = $addr + $size;
      } else {
        $output[] = sprintf('%04X: .BYTE $%02X', $addr, $byte);
        $address = $addr + 1;
      }
    }

    return implode("\n", $output);
  }

  private function formatOperand(string $mode, array $bytes): string
  {
    switch ($mode) {
      case 'Immediate':
        return sprintf('#$%02X', $bytes[0]);
      case 'Zero Page':
        return sprintf('$%02X', $bytes[0]);
      case 'Absolute':
        return sprintf('$%04X', $bytes[0] | ($bytes[1] << 8));
      case 'X-Indexed Zero Page':
        return sprintf('$%02X,X', $bytes[0]);
      case 'Y-Indexed Zero Page':
        return sprintf('$%02X,Y', $bytes[0]);
      case 'X-Indexed Absolute':
        return sprintf('$%04X,X', $bytes[0] | ($bytes[1] << 8));
      case 'Y-Indexed Absolute':
        return sprintf('$%04X,Y', $bytes[0] | ($bytes[1] << 8));
      case 'Absolute Indirect':
        return sprintf('($%04X)', $bytes[0] | ($bytes[1] << 8));
      case 'X-Indexed Zero Page Indirect':
        return sprintf('($%02X,X)', $bytes[0]);
      case 'Zero Page Indirect Y-Indexed':
        return sprintf('($%02X),Y', $bytes[0]);
      case 'Relative':
        $offset = $bytes[0] > 127 ? $bytes[0] - 256 : $bytes[0];
        return sprintf('*%+d', $offset);
      default:
        return '';
    }
  }
}
