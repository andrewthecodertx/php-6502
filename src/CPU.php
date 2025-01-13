<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Memory;
use Emulator\AddressingMode;

/**
 * CPU class to emulate the 6502 microprocessor behavior
 */
class CPU
{
  private int $program_counter; // 16bit
  private int $stack_pointer;   // 8bit, but stored in memory from $0100 to $01FF
  private int $accumulator, $register_x, $register_y; // 8bit

  private int $processor_status = 0; // 8bit flags
  private const C_FLAG = 0x01; // Carry Flag
  private const Z_FLAG = 0x02; // Zero Flag
  private const I_FLAG = 0x04; // Interrupt Disable Flag
  private const D_FLAG = 0x08; // Decimal Mode Flag
  private const B_FLAG = 0x10; // Break Command Flag
  private const U_FLAG = 0x20; // Unused, always 1
  private const V_FLAG = 0x40; // Overflow Flag
  private const N_FLAG = 0x80; // Negative Flag

  private array $instructions = []; // Store all instructions from JSON

  /**
   * Constructor for CPU
   *
   * @param Memory $memory Memory object to interact with
   */
  public function __construct(private Memory $memory)
  {
    $this->reset();
    $this->loadInstructions();
  }

  /**
   * Resets the CPU to initial state
   */
  public function reset(): void
  {
    $this->program_counter = $this->memory->read_word(0xFFFC);
    $this->stack_pointer = 0x00FF;
    $this->processor_status = self::U_FLAG; // Reset all flags to 0, except U which is always set
    $this->accumulator = 0; // Reset registers
    $this->register_x = 0;
    $this->register_y = 0;
  }

  /**
   * Loads instructions from JSON into an associative array
   */
  private function loadInstructions(): void
  {
    $json = file_get_contents('./src/opcodes.json');
    $data = json_decode($json, true);

    if (isset($data['OPCODES'])) {
      foreach ($data['OPCODES'] as $instruction) {
        $this->instructions[$instruction['opcode']] = $instruction;
      }
    } else {
      throw new \Exception("Invalid opcode JSON structure");
    }
  }

  public function getInstructionData(string $mnemonic, AddressingMode $addressing_mode): array
  {
    $opcode = array_filter($this->instructions, function ($instruction) use ($mnemonic, $addressing_mode) {
      return $instruction['mnemonic'] === $mnemonic && $instruction['addressing mode'] === $addressing_mode->name;
    });

    if (empty($opcode)) {
      throw new \InvalidArgumentException("No opcode found for {$mnemonic} with {$addressing_mode->name} addressing");
    }

    return reset($opcode);
  }

  /**
   * Sets a specific flag in the processor status register
   *
   * @param int $flag The flag to set (use constants like self::C_FLAG)
   */
  private function setFlag(int $flag): void
  {
    $this->processor_status |= $flag;
  }

  /**
   * Clears a specific flag in the processor status register
   *
   * @param int $flag The flag to clear
   */
  private function clearFlag(int $flag): void
  {
    $this->processor_status &= ~$flag;
  }

  /**
   * Returns a string representation of all registers' current state
   *
   * @return string
   */
  public function getRegistersState(): string
  {
    return sprintf(
      "PC: %04X, SP: %02X, A: %02X, X: %02X, Y: %02X",
      $this->program_counter,
      $this->stack_pointer,
      $this->accumulator & 0xFF,
      $this->register_x & 0xFF,
      $this->register_y & 0xFF
    );
  }

  /**
   * Gets the state of a specific flag in the processor status
   *
   * @param int $flag The flag to check
   * @return bool True if the flag is set, false otherwise
   */

  public function getFlagState(int $flag): bool
  {
    return ($this->processor_status & $flag) !== 0;
  }
  /**
   * Returns a string representation of the current state of all flags
   *
   * @return string
   */
  public function getFlagsState(): string
  {
    $flags = [
      'N' => $this->getFlagState(self::N_FLAG) ? '1' : '0',
      'V' => $this->getFlagState(self::V_FLAG) ? '1' : '0',
      'B' => $this->getFlagState(self::B_FLAG) ? '1' : '0',
      'D' => $this->getFlagState(self::D_FLAG) ? '1' : '0',
      'I' => $this->getFlagState(self::I_FLAG) ? '1' : '0',
      'Z' => $this->getFlagState(self::Z_FLAG) ? '1' : '0',
      'C' => $this->getFlagState(self::C_FLAG) ? '1' : '0',
    ];

    return sprintf(
      "Flags (NV-BDIZC): %s%s-%s%s%s%s%s",
      $flags['N'],
      $flags['V'],
      $flags['B'],
      $flags['D'],
      $flags['I'],
      $flags['Z'],
      $flags['C']
    );
  }

  /**
   * Determines the addressing mode based on the operand
   *
   * @param string $operand The operand string to analyze
   * @return AddressingMode The addressing mode determined
   */
  private function addressing_mode(string $operand): AddressingMode
  {
    if (empty($operand)) {
      return AddressingMode::Implied;
    }

    $operand = trim($operand, '"\'');

    if (strpos($operand, 'A') === 0) {
      return AddressingMode::Accumulator;
    }

    if (strpos($operand, '#') === 0) {
      return AddressingMode::Immediate;
    }

    // Since regex patterns are checked in sequence, order might affect performance
    if (preg_match('/^\\$[0-9A-Fa-f]{2}$/i', $operand)) {
      return AddressingMode::Zero_Page;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{2},X$/i', $operand)) {
      return AddressingMode::X_Indexed_Zero_Page;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{4}$/i', $operand)) {
      return AddressingMode::Absolute;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{4},X$/i', $operand)) {
      return AddressingMode::X_Indexed_Absolute;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{4},Y$/i', $operand)) {
      return AddressingMode::Y_Indexed_Absolute;
    }

    if (preg_match('/^\\(\\$[0-9A-Fa-f]{2},X\\)$/i', $operand)) {
      return AddressingMode::X_Indexed_Zero_Page_Indirect;
    }

    if (preg_match('/^\\(\\$[0-9A-Fa-f]{2}\\)\\,Y$/i', $operand)) { // Fixed pattern
      return AddressingMode::Zero_Page_Indirect_Y_Indexed;
    }

    if (preg_match('/^\\(\\$[0-9A-Fa-f]{4}\\)$/i', $operand)) {
      return AddressingMode::Absolute_Indirect;
    }

    if (preg_match('/^\\$[0-9A-Fa-f]{2},Y$/i', $operand)) {
      return AddressingMode::Y_Indexed_Zero_Page;
    }

    /** only if branch instruction! */
    if (preg_match('/^\\$[0-9A-Fa-f]{2}$/i', $operand)) { // Adjusted for relative addressing
      return AddressingMode::Relative;
    }

    // Consider throwing an exception here for debugging purposes
    return AddressingMode::Unknown;
  }

  /**
   * Executes an instruction based on opcode and operand
   *
   * @param string $opcode The instruction's opcode
   * @param null|string $operand The instruction's operand, if any
   */
  public function execute(string $opcode = 'NOP', ?string $operand = null): void
  {
    $opcodeHex = '0x' . ltrim($opcode, '0x');

    if (isset($this->instructions[$opcodeHex])) {
      $instruction = $this->instructions[$opcodeHex];
      $addressing_mode = $this->addressing_mode($operand ?? '');
      $instructions = new Instructions($this);

      switch ($instruction['mnemonic']) {
        case 'LDA':
          $result = $instructions->LDA($operand ?? '', $addressing_mode);
          printf("LDA executed. Cycles: %d, Bytes: %d\n", $result['cycles'], $result['bytes']);
          break;
          // Add other cases for other instructions here
        default:
          printf("Instruction %s not implemented yet\n", $instruction['mnemonic']);
      }
    } else {
      printf("Unknown opcode: %s\n", $opcodeHex);
    }
  }
}
