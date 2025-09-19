<?php

declare(strict_types=1);

namespace Emulator\Peripherals;

use Emulator\Bus\PeripheralInterface;

class EnhancedConsole implements PeripheralInterface
{
  
  public const CONSOLE_BASE = 0xD000;
  public const CONSOLE_OUTPUT = 0xD000;     
  public const CONSOLE_INPUT_STATUS = 0xD001; 
  public const CONSOLE_INPUT_DATA = 0xD002;   
  public const CONSOLE_CONTROL = 0xD003;      

  
  public const CTRL_ECHO = 0x01;            
  public const CTRL_LINE_MODE = 0x02;       
  public const CTRL_CLEAR_INPUT = 0x04;     

  private TextDisplay $display;
  private array $inputBuffer = [];
  private bool $inputReady = false;
  private bool $echo = true;
  private bool $lineMode = false;

  public function __construct(TextDisplay $display)
  {
    $this->display = $display;

    
    if (php_sapi_name() === 'cli') {
      stream_set_blocking(STDIN, false);
    }
  }

  public function handlesAddress(int $address): bool
  {
    return $address >= self::CONSOLE_BASE && $address <= self::CONSOLE_CONTROL;
  }

  public function read(int $address): int
  {
    switch ($address) {
      case self::CONSOLE_INPUT_STATUS:
        $this->checkForInput();
        return $this->inputReady ? 0x80 : 0x00;

      case self::CONSOLE_INPUT_DATA:
        return $this->readCharacter();

      case self::CONSOLE_CONTROL:
        return ($this->echo ? self::CTRL_ECHO : 0) |
               ($this->lineMode ? self::CTRL_LINE_MODE : 0);

      default:
        return 0;
    }
  }

  public function write(int $address, int $value): void
  {
    switch ($address) {
      case self::CONSOLE_OUTPUT:
        $this->display->writeChar($value);
        break;

      case self::CONSOLE_CONTROL:
        $this->echo = ($value & self::CTRL_ECHO) !== 0;
        $this->lineMode = ($value & self::CTRL_LINE_MODE) !== 0;
        if ($value & self::CTRL_CLEAR_INPUT) {
          $this->inputBuffer = [];
          $this->inputReady = false;
        }
        break;
    }
  }

  public function tick(): void
  {
    $this->checkForInput();
    $this->display->tick();
  }

  public function reset(): void
  {
    $this->inputBuffer = [];
    $this->inputReady = false;
    $this->echo = true;
    $this->lineMode = false;
    $this->display->reset();
  }

  private function checkForInput(): void
  {
    if (php_sapi_name() === 'cli') {
      $input = fread(STDIN, 1024);
      if ($input !== false && $input !== '') {
        for ($i = 0; $i < strlen($input); $i++) {
          $char = ord($input[$i]);
          $this->inputBuffer[] = $char;

          
          if ($this->echo && $char >= 0x20 && $char <= 0x7E) {
            $this->display->writeChar($char);
          }
        }
        $this->inputReady = !empty($this->inputBuffer);
      }
    }
  }

  private function readCharacter(): int
  {
    $this->checkForInput();

    if (!empty($this->inputBuffer)) {
      $char = array_shift($this->inputBuffer);
      $this->inputReady = !empty($this->inputBuffer);
      return $char;
    }

    return 0x00;
  }

  public function refresh(): void
  {
    $this->display->refresh();
  }
}
