<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\Instructions\LoadStore;
use Emulator\Instructions\Transfer;
use Emulator\Instructions\Arithmetic;
use Emulator\Instructions\Logic;
use Emulator\Instructions\ShiftRotate;
use Emulator\Instructions\IncDec;
use Emulator\Instructions\FlowControl;
use Emulator\Instructions\Stack;
use Emulator\Instructions\Flags;
use Emulator\Bus\BusInterface;

class CPU
{
  public int $pc = 0;
  public int $sp = 0;
  public int $accumulator = 0;
  public int $register_x = 0;
  public int $register_y = 0;
  public int $cycles = 0;
  public StatusRegister $status;
  public bool $halted = false;

  private InstructionRegister $instructionRegister;
  private array $instructionHandlers = [];
  private LoadStore $loadStoreHandler;
  private Transfer $transferHandler;
  private Arithmetic $arithmeticHandler;
  private Logic $logicHandler;
  private ShiftRotate $shiftRotateHandler;
  private IncDec $incDecHandler;
  private FlowControl $flowControlHandler;
  private Stack $stackHandler;
  private Flags $flagsHandler;

  private Memory $memory;
  private ?BusInterface $bus = null;

  public function __construct(Memory|BusInterface $memoryOrBus)
  {
    if ($memoryOrBus instanceof BusInterface) {
      $this->bus = $memoryOrBus;
      $this->memory = new BusMemoryBridge($memoryOrBus);
    } else {
      $this->memory = $memoryOrBus;
    }

    $this->status = new StatusRegister();
    $this->instructionRegister = new InstructionRegister();
    $this->loadStoreHandler = new LoadStore($this);
    $this->transferHandler = new Transfer($this);
    $this->arithmeticHandler = new Arithmetic($this);
    $this->logicHandler = new Logic($this);
    $this->shiftRotateHandler = new ShiftRotate($this);
    $this->incDecHandler = new IncDec($this);
    $this->flowControlHandler = new FlowControl($this);
    $this->stackHandler = new Stack($this);
    $this->flagsHandler = new Flags($this);
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

    if ($this->bus !== null) {
      $this->bus->tick();
    }
  }

  public function executeInstruction(): void
  {
    $startingPC = $this->pc;
    do {
      $this->step();
    } while ($this->cycles > 0 || $this->pc == $startingPC);
  }

  public function halt(): void
  {
    $this->halted = true;
  }

  public function resume(): void
  {
    $this->halted = false;
  }

  public function isHalted(): bool
  {
    return $this->halted;
  }

  public function reset(): void
  {
    $currentPC = $this->pc;
    $this->memory->read_byte($currentPC);
    $this->memory->read_byte($currentPC + 1);

    $tempSP = 0x00;
    for ($cycle = 3; $cycle <= 5; $cycle++) {
      $tempSP = ($tempSP - 1) & 0xFF;
      $this->memory->read_byte(0x0100 + $tempSP);
    }

    $resetLow = $this->memory->read_byte(0xFFFC);
    $resetHigh = $this->memory->read_byte(0xFFFD);

    $this->pc = ($resetHigh << 8) | $resetLow;
    $this->sp = 0xFD;
    $this->accumulator = 0;
    $this->register_x = 0;
    $this->register_y = 0;
    $this->status->fromInt(0b00100100);
    $this->cycles = 0;
    $this->halted = false;
  }

  public function accurateReset(): void
  {
    $currentPC = $this->pc;

    $this->memory->read_byte($currentPC);
    $this->memory->read_byte($currentPC + 1);

    $tempSP = 0x00;

    for ($cycle = 3; $cycle <= 5; $cycle++) {
      $tempSP = ($tempSP - 1) & 0xFF;
      $this->memory->read_byte(0x0100 + $tempSP);
    }

    $resetLow = $this->memory->read_byte(0xFFFC);
    $resetHigh = $this->memory->read_byte(0xFFFD);

    $this->pc = ($resetHigh << 8) | $resetLow;
    $this->sp = 0xFD;
    $this->status->set(StatusRegister::INTERRUPT_DISABLE, true);
    $this->status->set(StatusRegister::UNUSED, true);

    $this->cycles = 0;
  }

  private function initializeInstructionHandlers(): void
  {
    $this->instructionHandlers = [
      'LDA' => fn(Opcode $opcode) => $this->loadStoreHandler->lda($opcode),
      'LDX' => fn(Opcode $opcode) => $this->loadStoreHandler->ldx($opcode),
      'LDY' => fn(Opcode $opcode) => $this->loadStoreHandler->ldy($opcode),
      'STA' => fn(Opcode $opcode) => $this->loadStoreHandler->sta($opcode),
      'STX' => fn(Opcode $opcode) => $this->loadStoreHandler->stx($opcode),
      'STY' => fn(Opcode $opcode) => $this->loadStoreHandler->sty($opcode),

      'TAX' => fn(Opcode $opcode) => $this->transferHandler->tax($opcode),
      'TAY' => fn(Opcode $opcode) => $this->transferHandler->tay($opcode),
      'TXA' => fn(Opcode $opcode) => $this->transferHandler->txa($opcode),
      'TYA' => fn(Opcode $opcode) => $this->transferHandler->tya($opcode),
      'TSX' => fn(Opcode $opcode) => $this->transferHandler->tsx($opcode),
      'TXS' => fn(Opcode $opcode) => $this->transferHandler->txs($opcode),

      'ADC' => fn(Opcode $opcode) => $this->arithmeticHandler->adc($opcode),
      'SBC' => fn(Opcode $opcode) => $this->arithmeticHandler->sbc($opcode),
      'CMP' => fn(Opcode $opcode) => $this->arithmeticHandler->cmp($opcode),
      'CPX' => fn(Opcode $opcode) => $this->arithmeticHandler->cpx($opcode),
      'CPY' => fn(Opcode $opcode) => $this->arithmeticHandler->cpy($opcode),

      'AND' => fn(Opcode $opcode) => $this->logicHandler->and($opcode),
      'ORA' => fn(Opcode $opcode) => $this->logicHandler->ora($opcode),
      'EOR' => fn(Opcode $opcode) => $this->logicHandler->eor($opcode),
      'BIT' => fn(Opcode $opcode) => $this->logicHandler->bit($opcode),

      'ASL' => fn(Opcode $opcode) => $this->shiftRotateHandler->asl($opcode),
      'LSR' => fn(Opcode $opcode) => $this->shiftRotateHandler->lsr($opcode),
      'ROL' => fn(Opcode $opcode) => $this->shiftRotateHandler->rol($opcode),
      'ROR' => fn(Opcode $opcode) => $this->shiftRotateHandler->ror($opcode),

      'INC' => fn(Opcode $opcode) => $this->incDecHandler->inc($opcode),
      'DEC' => fn(Opcode $opcode) => $this->incDecHandler->dec($opcode),
      'INX' => fn(Opcode $opcode) => $this->incDecHandler->inx($opcode),
      'DEX' => fn(Opcode $opcode) => $this->incDecHandler->dex($opcode),
      'INY' => fn(Opcode $opcode) => $this->incDecHandler->iny($opcode),
      'DEY' => fn(Opcode $opcode) => $this->incDecHandler->dey($opcode),

      'BEQ' => fn(Opcode $opcode) => $this->flowControlHandler->beq($opcode),
      'BNE' => fn(Opcode $opcode) => $this->flowControlHandler->bne($opcode),
      'BCC' => fn(Opcode $opcode) => $this->flowControlHandler->bcc($opcode),
      'BCS' => fn(Opcode $opcode) => $this->flowControlHandler->bcs($opcode),
      'BPL' => fn(Opcode $opcode) => $this->flowControlHandler->bpl($opcode),
      'BMI' => fn(Opcode $opcode) => $this->flowControlHandler->bmi($opcode),
      'BVC' => fn(Opcode $opcode) => $this->flowControlHandler->bvc($opcode),
      'BVS' => fn(Opcode $opcode) => $this->flowControlHandler->bvs($opcode),
      'JMP' => fn(Opcode $opcode) => $this->flowControlHandler->jmp($opcode),
      'JSR' => fn(Opcode $opcode) => $this->flowControlHandler->jsr($opcode),
      'RTS' => fn(Opcode $opcode) => $this->flowControlHandler->rts($opcode),
      'BRK' => fn(Opcode $opcode) => $this->flowControlHandler->brk($opcode),
      'RTI' => fn(Opcode $opcode) => $this->flowControlHandler->rti($opcode),

      'PHA' => fn(Opcode $opcode) => $this->stackHandler->pha($opcode),
      'PLA' => fn(Opcode $opcode) => $this->stackHandler->pla($opcode),
      'PHP' => fn(Opcode $opcode) => $this->stackHandler->php($opcode),
      'PLP' => fn(Opcode $opcode) => $this->stackHandler->plp($opcode),

      'SEC' => fn(Opcode $opcode) => $this->flagsHandler->sec($opcode),
      'CLC' => fn(Opcode $opcode) => $this->flagsHandler->clc($opcode),
      'SEI' => fn(Opcode $opcode) => $this->flagsHandler->sei($opcode),
      'CLI' => fn(Opcode $opcode) => $this->flagsHandler->cli($opcode),
      'SED' => fn(Opcode $opcode) => $this->flagsHandler->sed($opcode),
      'CLD' => fn(Opcode $opcode) => $this->flagsHandler->cld($opcode),
      'CLV' => fn(Opcode $opcode) => $this->flagsHandler->clv($opcode),

      'NOP' => fn(Opcode $opcode) => $opcode->getCycles(),
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
    $this->pushByte(($value >> 8) & 0xFF);
    $this->pushByte($value & 0xFF);
  }

  public function pullWord(): int
  {
    $low = $this->pullByte();
    $high = $this->pullByte();
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
    return $offset;
  }

  private function implied(): int
  {
    return 0;
  }

  private function accumulator(): int
  {
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

  public function getBus(): ?BusInterface
  {
    return $this->bus;
  }
}

