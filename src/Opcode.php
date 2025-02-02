<?php

declare(strict_types=1);

namespace Emulator;

class Opcode
{
  public function __construct(
    private readonly string $opcode,
    private readonly string $mnemonic,
    private readonly string $addressingMode,
    private readonly int $bytes,
    private readonly int $cycles,
    private readonly ?string $additionalCycles = null,
    private readonly ?string $operation = null
  ) {}

  public function getOpcode(): string
  {
    return $this->opcode;
  }

  public function getMnemonic(): string
  {
    return $this->mnemonic;
  }

  public function getAddressingMode(): string
  {
    return $this->addressingMode;
  }

  public function getBytes(): int
  {
    return $this->bytes;
  }

  public function getCycles(): int
  {
    return $this->cycles;
  }

  public function getAdditionalCycles(): ?string
  {
    return $this->additionalCycles;
  }

  public function getOperation(): ?string
  {
    return $this->operation;
  }
}
