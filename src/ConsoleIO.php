<?php

declare(strict_types=1);

namespace Emulator;

class ConsoleIO
{
  public const CONSOLE_OUTPUT = 0xD000;
  public const CONSOLE_INPUT_STATUS = 0xD001;
  public const CONSOLE_INPUT_DATA = 0xD002;

  private array $inputBuffer = [];
  private bool $inputReady = false;

  public function __construct()
  {
    // Set non-blocking mode for stdin if running in CLI
    if (php_sapi_name() === 'cli') {
      stream_set_blocking(STDIN, false);
    }
  }

  public function writeCharacter(int $character): void
  {
    $char = chr($character & 0x7F); // Mask to 7-bit ASCII

    // Handle special characters
    switch ($character) {
      case 0x0A: // Line feed
        echo "\n";
        break;
      case 0x0D: // Carriage return
        echo "\r";
        break;
      case 0x08: // Backspace
        echo "\b";
        break;
      case 0x07: // Bell
        echo "\a";
        break;
      default:
        if ($character >= 0x20 && $character <= 0x7E) {
          echo $char;
        }
        break;
    }

    // Flush output immediately for interactive experience
    if (ob_get_level()) {
      ob_flush();
    }
    flush();
  }

  public function getInputStatus(): int
  {
    $this->checkForInput();
    return $this->inputReady ? 0x80 : 0x00; // Bit 7 set when character available
  }

  public function readCharacter(): int
  {
    $this->checkForInput();

    if (!empty($this->inputBuffer)) {
      $char = array_shift($this->inputBuffer);
      $this->inputReady = !empty($this->inputBuffer);
      return ord($char);
    }

    return 0x00; // No character available
  }

  private function checkForInput(): void
  {
    if (php_sapi_name() === 'cli') {
      $input = fread(STDIN, 1024);
      if ($input !== false && $input !== '') {
        // Add characters to buffer
        for ($i = 0; $i < strlen($input); $i++) {
          $this->inputBuffer[] = $input[$i];
        }
        $this->inputReady = !empty($this->inputBuffer);
      }
    }
  }

  public function hasInput(): bool
  {
    $this->checkForInput();
    return $this->inputReady;
  }
}