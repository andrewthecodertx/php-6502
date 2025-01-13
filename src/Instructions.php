<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\AddressingMode;
use Emulator\BaseInstruction;

class Instructions extends BaseInstruction
{
  public function LDA(string $operand, AddressingMode $addressing_mode): array
  {
    $cycles = 0;
    $bytes = 0;

    // Assume we have a method in CPU to find instruction metadata
    $instructionData = $this->cpu->getInstructionData('LDA', $addressing_mode);
    $bytes = $instructionData['bytes'];
    $cycles = $instructionData['cycles'];

    switch ($addressing_mode) {
      case AddressingMode::Immediate:
        $value = hexdec(substr($operand, 2)); // '#$XX'
        $this->cpu->accumulator = $value & 0xFF;
        break;

      case AddressingMode::Zero_Page:
        $addr = hexdec(substr($operand, 1)); // '$XX'
        $this->cpu->accumulator = $this->cpu->memory->read_byte($addr);
        break;

      case AddressingMode::Zero_Page_X:
        $addr = hexdec(substr($operand, 1, 2)); // '$XX,X'
        $addr = ($addr + $this->cpu->register_x) & 0xFF;
        $this->cpu->accumulator = $this->cpu->memory->read_byte($addr);
        break;

      case AddressingMode::Absolute:
        $addr = hexdec(substr($operand, 1)); // '$XXXX'
        $this->cpu->accumulator = $this->cpu->memory->read_byte($addr);
        break;

      case AddressingMode::Absolute_X:
        $addr = hexdec(substr($operand, 1, 4)); // '$XXXX,X'
        $new_addr = $addr + $this->cpu->register_x;
        $cycles += $this->pageCrossed($addr, $new_addr) ? 1 : 0;
        $this->cpu->accumulator = $this->cpu->memory->read_byte($new_addr);
        break;

      case AddressingMode::Absolute_Y:
        $addr = hexdec(substr($operand, 1, 4)); // '$XXXX,Y'
        $new_addr = $addr + $this->cpu->register_y;
        $cycles += $this->pageCrossed($addr, $new_addr) ? 1 : 0;
        $this->cpu->accumulator = $this->cpu->memory->read_byte($new_addr);
        break;

      case AddressingMode::Zero_Page_Indirect_X:
        $addr = hexdec(substr($operand, 2, 2)); // '($XX,X)'
        $addr = ($addr + $this->cpu->register_x) & 0xFF;
        $effective_addr = $this->cpu->memory->read_word($addr);
        $this->cpu->accumulator = $this->cpu->memory->read_byte($effective_addr);
        break;

      case AddressingMode::Zero_Page_Indirect_Y:
        $addr = hexdec(substr($operand, 2, 2)); // '($XX),Y'
        $effective_addr = $this->cpu->memory->read_word($addr) + $this->cpu->register_y;
        $cycles += $this->pageCrossed($this->cpu->memory->read_word($addr), $effective_addr) ? 1 : 0;
        $this->cpu->accumulator = $this->cpu->memory->read_byte($effective_addr);
        break;

      default:
        throw new \InvalidArgumentException("Unsupported addressing mode for LDA: " . $addressing_mode->name);
    }

    $this->updateFlags($this->cpu->accumulator);
    return ['cycles' => $cycles, 'bytes' => $bytes];
  }

  // Here you would add methods for other instructions, like STA, ADC, etc.
}
