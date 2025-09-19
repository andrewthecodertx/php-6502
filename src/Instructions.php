<?php

declare(strict_types=1);

namespace Emulator;

use Emulator\AddressingMode;
use Emulator\BaseInstruction;

abstract class Instructions extends BaseInstruction
{
  protected InstructionRegistry $registry;

  public function __construct(CPU $cpu, InstructionRegistry $registry)
  {
    parent::__construct($cpu);
    $this->registry = $registry;
  }

  protected function getInstructionData(string $mnemonic, AddressingMode $addressingMode): ?Opcode
  {
    return $this->registry->findOpcode($mnemonic, $addressingMode->name);
  }

  abstract public function execute(string $operand, AddressingMode $addressingMode): array;
}
