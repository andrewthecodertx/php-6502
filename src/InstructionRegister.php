<?php

declare(strict_types=1);

namespace Emulator;

class InstructionRegistry
{
  /** @var array<string, Opcode> */
  private array $opcodes = [];

  public function __construct()
  {
    $this->loadOpcodes();
  }

  private function loadOpcodes(): void
  {
    $json = file_get_contents('./src/opcodes.json');
    $data = json_decode($json, true);

    if (!isset($data['OPCODES'])) {
      throw new \RuntimeException('Invalid opcode JSON structure');
    }

    foreach ($data['OPCODES'] as $instruction) {
      $opcode = new Opcode(
        $instruction['opcode'],
        $instruction['mnemonic'],
        $instruction['addressing mode'],
        $instruction['bytes'],
        $instruction['cycles'],
        $instruction['additional cycles'] ?? null,
        $instruction['operation'] ?? null
      );

      $this->opcodes[$instruction['opcode']] = $opcode;
    }
  }

  public function getOpcode(string $opcode): ?Opcode
  {
    return $this->opcodes[$opcode] ?? null;
  }

  public function findOpcodesByMnemonic(string $mnemonic): array
  {
    return array_filter(
      $this->opcodes,
      fn(Opcode $opcode) =>
      $opcode->getMnemonic() === $mnemonic
    );
  }

  public function findOpcode(string $mnemonic, string $addressingMode): ?Opcode
  {
    foreach ($this->opcodes as $opcode) {
      if (
        $opcode->getMnemonic() === $mnemonic &&
        $opcode->getAddressingMode() === $addressingMode
      ) {
        return $opcode;
      }
    }
    return null;
  }
}
