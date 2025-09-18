<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Instructions\LoadStore;
use Emulator\Instructions\Transfer;
use Emulator\Instructions\Arithmetic;
use Emulator\Instructions\Logic;
use Emulator\Instructions\ShiftRotate;
use Emulator\Instructions\IncDec;

class CPU
{
  public int $pc = 0;
  public int $sp = 0; // 8-bit stack pointer (0x00-0xFF)
  public int $accumulator = 0;
  public int $register_x = 0;
  public int $register_y = 0;
  public int $cycles = 0;
  public StatusRegister $status;

  private InstructionRegister $instructionRegister;
  private array $instructionHandlers = [];
  private LoadStore $loadStoreHandler;
  private Transfer $transferHandler;
  private Arithmetic $arithmeticHandler;
  private Logic $logicHandler;
  private ShiftRotate $shiftRotateHandler;
  private IncDec $incDecHandler;

  public function __construct(private Memory $memory)
  {
    $this->status = new StatusRegister();
    $this->instructionRegister = new InstructionRegister();
    $this->loadStoreHandler = new LoadStore($this);
    $this->transferHandler = new Transfer($this);
    $this->arithmeticHandler = new Arithmetic($this);
    $this->logicHandler = new Logic($this);
    $this->shiftRotateHandler = new ShiftRotate($this);
    $this->incDecHandler = new IncDec($this);
    $this->initializeInstructionHandlers();
    $this->reset();
  }

  public function clock(): void
  {
    $this->cycles--;
  }

  public function run(): void
  {
    while (true) {
      $this->step();
    }
  }

  public function step(): void
  {
    if ($this->cycles === 0) {
      $opcode = $this->memory->read_byte($this->pc);
      $this->pc++;

      $opcodeData = $this->instructionRegister->getOpcode(sprintf('0x%02X', $opcode));

      if (!$opcodeData) {
        throw new \InvalidArgumentException(sprintf("Unknown opcode: 0x%02X", $opcode));
      }

      $mnemonic = $opcodeData->getMnemonic();

      if (!isset($this->instructionHandlers[$mnemonic])) {
        throw new \RuntimeException("Instruction {$mnemonic} not implemented");
      }

      $handler = $this->instructionHandlers[$mnemonic];
      $this->cycles += $handler($opcodeData);
    }
    $this->clock();
  }

  public function executeInstruction(): void
  {
    $startingPC = $this->pc;
    do {
      $this->step();
    } while ($this->cycles > 0 || $this->pc == $startingPC);
  }

  public function reset(): void
  {
    // 6502 reset sequence takes exactly 7 cycles with specific bus activity

    // Store current PC for dummy reads (cycles 1-2)
    $currentPC = $this->pc;

    // Cycle 1: Read current PC location (dummy read, ignored)
    $this->memory->read_byte($currentPC);

    // Cycle 2: Read current PC+1 location (dummy read, ignored)
    $this->memory->read_byte($currentPC + 1);

    // Cycles 3-5: Read stack locations while decrementing SP (dummy reads)
    // Note: SP starts at unknown value, we'll simulate starting from 0x00
    $tempSP = 0x00;
    for ($cycle = 3; $cycle <= 5; $cycle++) {
      $tempSP = ($tempSP - 1) & 0xFF; // Decrement SP
      $this->memory->read_byte(0x0100 + $tempSP); // Dummy stack read
    }

    // Cycle 6: Read reset vector low byte
    $resetLow = $this->memory->read_byte(0xFFFC);

    // Cycle 7: Read reset vector high byte and load PC
    $resetHigh = $this->memory->read_byte(0xFFFD);

    // Set final CPU state per 6502 specification
    $this->pc = ($resetHigh << 8) | $resetLow;
    $this->sp = 0xFD; // SP after 3 decrements from 0x00

    // 6502 specification: Only I flag is guaranteed set, others undefined
    // For emulation consistency, we'll set known state but note this difference
    // Real hardware: A, X, Y retain previous values (undefined)
    // Real hardware: Only I flag guaranteed set, other flags undefined
    $this->accumulator = 0; // Emulator choice: clear (real HW: undefined)
    $this->register_x = 0;  // Emulator choice: clear (real HW: undefined)
    $this->register_y = 0;  // Emulator choice: clear (real HW: undefined)

    // Set status register: only I flag guaranteed, others undefined
    // We'll set unused bit and I flag for emulation consistency
    $this->status->fromInt(0b00100100); // I flag + unused bit set

    $this->cycles = 0;
  }

  public function accurateReset(): void
  {
    // 6502 reset sequence with hardware-accurate undefined register behavior
    // This version leaves A, X, Y registers unchanged (as real hardware does)

    $currentPC = $this->pc;

    // Cycle 1: Read current PC location (dummy read, ignored)
    $this->memory->read_byte($currentPC);

    // Cycle 2: Read current PC+1 location (dummy read, ignored)
    $this->memory->read_byte($currentPC + 1);

    // Cycles 3-5: Read stack locations while decrementing SP (dummy reads)
    $tempSP = 0x00; // Simulate starting SP (real hardware: undefined)
    for ($cycle = 3; $cycle <= 5; $cycle++) {
      $tempSP = ($tempSP - 1) & 0xFF;
      $this->memory->read_byte(0x0100 + $tempSP);
    }

    // Cycle 6: Read reset vector low byte
    $resetLow = $this->memory->read_byte(0xFFFC);

    // Cycle 7: Read reset vector high byte and load PC
    $resetHigh = $this->memory->read_byte(0xFFFD);

    // Set final CPU state exactly per 6502 specification
    $this->pc = ($resetHigh << 8) | $resetLow;
    $this->sp = 0xFD;

    // Hardware-accurate behavior: A, X, Y registers are NOT modified
    // (they retain whatever values they had before reset)

    // Only set I flag, leave other flags in undefined state
    // For practical emulation, we'll preserve current flags but ensure I is set
    $this->status->set(StatusRegister::INTERRUPT_DISABLE, true);
    $this->status->set(StatusRegister::UNUSED, true); // Always set per spec

    $this->cycles = 0;
  }

  private function initializeInstructionHandlers(): void
  {
    $this->instructionHandlers = [
      // Load/Store Operations
      'LDA' => fn(Opcode $opcode) => $this->loadStoreHandler->lda($opcode),
      'LDX' => fn(Opcode $opcode) => $this->loadStoreHandler->ldx($opcode),
      'LDY' => fn(Opcode $opcode) => $this->loadStoreHandler->ldy($opcode),
      'STA' => fn(Opcode $opcode) => $this->loadStoreHandler->sta($opcode),
      'STX' => fn(Opcode $opcode) => $this->loadStoreHandler->stx($opcode),
      'STY' => fn(Opcode $opcode) => $this->loadStoreHandler->sty($opcode),

      // Register Transfers
      'TAX' => fn(Opcode $opcode) => $this->transferHandler->tax($opcode),
      'TAY' => fn(Opcode $opcode) => $this->transferHandler->tay($opcode),
      'TXA' => fn(Opcode $opcode) => $this->transferHandler->txa($opcode),
      'TYA' => fn(Opcode $opcode) => $this->transferHandler->tya($opcode),
      'TSX' => fn(Opcode $opcode) => $this->transferHandler->tsx($opcode),
      'TXS' => fn(Opcode $opcode) => $this->transferHandler->txs($opcode),

      // Arithmetic Operations
      'ADC' => fn(Opcode $opcode) => $this->arithmeticHandler->adc($opcode),
      'SBC' => fn(Opcode $opcode) => $this->arithmeticHandler->sbc($opcode),
      'CMP' => fn(Opcode $opcode) => $this->arithmeticHandler->cmp($opcode),
      'CPX' => fn(Opcode $opcode) => $this->arithmeticHandler->cpx($opcode),
      'CPY' => fn(Opcode $opcode) => $this->arithmeticHandler->cpy($opcode),

      // Logic Operations
      'AND' => fn(Opcode $opcode) => $this->logicHandler->and($opcode),
      'ORA' => fn(Opcode $opcode) => $this->logicHandler->ora($opcode),
      'EOR' => fn(Opcode $opcode) => $this->logicHandler->eor($opcode),
      'BIT' => fn(Opcode $opcode) => $this->logicHandler->bit($opcode),

      // Shift/Rotate Operations
      'ASL' => fn(Opcode $opcode) => $this->shiftRotateHandler->asl($opcode),
      'LSR' => fn(Opcode $opcode) => $this->shiftRotateHandler->lsr($opcode),
      'ROL' => fn(Opcode $opcode) => $this->shiftRotateHandler->rol($opcode),
      'ROR' => fn(Opcode $opcode) => $this->shiftRotateHandler->ror($opcode),

      // Increment/Decrement Operations
      'INC' => fn(Opcode $opcode) => $this->incDecHandler->inc($opcode),
      'DEC' => fn(Opcode $opcode) => $this->incDecHandler->dec($opcode),
      'INX' => fn(Opcode $opcode) => $this->incDecHandler->inx($opcode),
      'DEX' => fn(Opcode $opcode) => $this->incDecHandler->dex($opcode),
      'INY' => fn(Opcode $opcode) => $this->incDecHandler->iny($opcode),
      'DEY' => fn(Opcode $opcode) => $this->incDecHandler->dey($opcode),

      // System
      'NOP' => fn(Opcode $opcode) => $opcode->getCycles(), // NOP just takes cycles, does nothing
    ];
  }

  public function getAccumulator(): int
  {
    return $this->accumulator;
  }

  public function setAccumulator(int $value): void
  {
    $this->accumulator = $value & 0xFF;
  }

  public function getRegisterX(): int
  {
    return $this->register_x;
  }

  public function setRegisterX(int $value): void
  {
    $this->register_x = $value & 0xFF;
  }

  public function getRegisterY(): int
  {
    return $this->register_y;
  }

  public function setRegisterY(int $value): void
  {
    $this->register_y = $value & 0xFF;
  }

  public function getMemory(): Memory
  {
    return $this->memory;
  }

  public function pushByte(int $value): void
  {
    $this->memory->write_byte(0x0100 + $this->sp, $value & 0xFF);
    $this->sp = ($this->sp - 1) & 0xFF;
  }

  public function pullByte(): int
  {
    $this->sp = ($this->sp + 1) & 0xFF;
    return $this->memory->read_byte(0x0100 + $this->sp);
  }

  public function pushWord(int $value): void
  {
    $this->pushByte(($value >> 8) & 0xFF); // High byte first
    $this->pushByte($value & 0xFF);        // Low byte second
  }

  public function pullWord(): int
  {
    $low = $this->pullByte();              // Low byte first
    $high = $this->pullByte();             // High byte second
    return ($high << 8) | $low;
  }

  public function getAddress(string $addressingMode): int
  {
    return match ($addressingMode) {
      'Immediate' => $this->immediate(),
      'Zero Page' => $this->zeroPage(),
      'X-Indexed Zero Page' => $this->zeroPageX(),
      'Y-Indexed Zero Page' => $this->zeroPageY(),
      'Absolute' => $this->absolute(),
      'X-Indexed Absolute' => $this->absoluteX(),
      'Y-Indexed Absolute' => $this->absoluteY(),
      'X-Indexed Zero Page Indirect' => $this->indirectX(),
      'Zero Page Indirect Y-Indexed' => $this->indirectY(),
      'Absolute Indirect' => $this->absoluteIndirect(),
      'Relative' => $this->relative(),
      'Implied' => $this->implied(),
      'Accumulator' => $this->accumulator(),
      default => throw new \InvalidArgumentException("Invalid addressing mode: {$addressingMode}"),
    };
  }

  private function immediate(): int
  {
    $this->pc++;
    return $this->pc - 1;
  }

  private function zeroPage(): int
  {
    $address = $this->memory->read_byte($this->pc);
    $this->pc++;
    return $address;
  }

  private function zeroPageX(): int
  {
    $address = $this->memory->read_byte($this->pc) + $this->register_x;
    $this->pc++;
    return $address & 0xFF;
  }

  private function zeroPageY(): int
  {
    $address = $this->memory->read_byte($this->pc) + $this->register_y;
    $this->pc++;
    return $address & 0xFF;
  }

  private function absolute(): int
  {
    $low = $this->memory->read_byte($this->pc);
    $this->pc++;
    $high = $this->memory->read_byte($this->pc);
    $this->pc++;
    return ($high << 8) | $low;
  }

  private function absoluteX(): int
  {
    $low = $this->memory->read_byte($this->pc);
    $this->pc++;
    $high = $this->memory->read_byte($this->pc);
    $this->pc++;
    $address = (($high << 8) | $low) + $this->register_x;
    return $address & 0xFFFF;
  }

  private function absoluteY(): int
  {
    $low = $this->memory->read_byte($this->pc);
    $this->pc++;
    $high = $this->memory->read_byte($this->pc);
    $this->pc++;
    $address = (($high << 8) | $low) + $this->register_y;
    return $address & 0xFFFF;
  }

  private function indirectX(): int
  {
    $zeroPageAddress = $this->memory->read_byte($this->pc) + $this->register_x;
    $this->pc++;
    $low = $this->memory->read_byte($zeroPageAddress & 0xFF);
    $high = $this->memory->read_byte(($zeroPageAddress + 1) & 0xFF);
    return ($high << 8) | $low;
  }

  private function indirectY(): int
  {
    $zeroPageAddress = $this->memory->read_byte($this->pc);
    $this->pc++;
    $low = $this->memory->read_byte($zeroPageAddress & 0xFF);
    $high = $this->memory->read_byte(($zeroPageAddress + 1) & 0xFF);
    $address = (($high << 8) | $low) + $this->register_y;
    return $address & 0xFFFF;
  }

  private function absoluteIndirect(): int
  {
    $low = $this->memory->read_byte($this->pc);
    $this->pc++;
    $high = $this->memory->read_byte($this->pc);
    $this->pc++;
    $indirectAddress = ($high << 8) | $low;

    // 6502 bug: if indirect address is at page boundary (xxFF),
    // high byte is read from xx00 instead of (xx+1)00
    if (($indirectAddress & 0xFF) == 0xFF) {
      $targetLow = $this->memory->read_byte($indirectAddress);
      $targetHigh = $this->memory->read_byte($indirectAddress & 0xFF00);
      return ($targetHigh << 8) | $targetLow;
    } else {
      return $this->memory->read_word($indirectAddress);
    }
  }

  private function relative(): int
  {
    $offset = $this->memory->read_byte($this->pc);
    $this->pc++;

    // Sign extend the 8-bit offset to 16-bit
    if ($offset & 0x80) {
      $offset |= 0xFF00; // Negative offset
    }

    return ($this->pc + $offset) & 0xFFFF;
  }

  private function implied(): int
  {
    // No address calculation needed for implied mode
    return 0;
  }

  private function accumulator(): int
  {
    // Accumulator addressing mode - no memory address
    return 0;
  }

  public function getRegistersState(): string
  {
    return sprintf(
      "PC: 0x%04X, SP: 0x%04X, A: 0x%02X, X: 0x%02X, Y: 0x%02X",
      $this->pc,
      $this->sp,
      $this->accumulator,
      $this->register_x,
      $this->register_y
    );
  }

  public function getFlagsState(): string
  {
    return sprintf(
      "Flags: %s%s%s%s%s%s%s%s",
      $this->status->get(StatusRegister::NEGATIVE) ? 'N' : '-',
      $this->status->get(StatusRegister::OVERFLOW) ? 'V' : '-',
      '-',
      $this->status->get(StatusRegister::BREAK_COMMAND) ? 'B' : '-',
      $this->status->get(StatusRegister::DECIMAL_MODE) ? 'D' : '-',
      $this->status->get(StatusRegister::INTERRUPT_DISABLE) ? 'I' : '-',
      $this->status->get(StatusRegister::ZERO) ? 'Z' : '-',
      $this->status->get(StatusRegister::CARRY) ? 'C' : '-'
    );
  }
}
