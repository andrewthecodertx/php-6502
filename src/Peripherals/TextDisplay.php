<?php

declare(strict_types=1);

namespace Emulator\Peripherals;

use Emulator\Bus\PeripheralInterface;

class TextDisplay implements PeripheralInterface
{
  
  public const DISPLAY_BASE = 0xC000;       
  public const DISPLAY_END = 0xC3E7;        
  public const CURSOR_X = 0xC3E8;           
  public const CURSOR_Y = 0xC3E9;           
  public const FG_COLOR = 0xC3EA;           
  public const BG_COLOR = 0xC3EB;           
  public const CONTROL = 0xC3EC;            

  
  public const WIDTH = 40;
  public const HEIGHT = 25;

  
  public const CTRL_CLEAR = 0x01;           
  public const CTRL_SHOW_CURSOR = 0x02;     
  public const CTRL_REFRESH = 0x04;         

  private array $displayMemory = [];
  private int $cursorX = 0;
  private int $cursorY = 0;
  private int $fgColor = 7;  
  private int $bgColor = 0;  
  private bool $showCursor = true;
  private bool $needsRefresh = true;

  
  private array $colorMap = [
    0 => '30', 
    1 => '34', 
    2 => '32', 
    3 => '36', 
    4 => '31', 
    5 => '35', 
    6 => '33', 
    7 => '37', 
    8 => '90', 
    9 => '94', 
    10 => '92', 
    11 => '96', 
    12 => '91', 
    13 => '95', 
    14 => '93', 
    15 => '97', 
  ];

  public function __construct()
  {
    $this->reset();
  }

  public function handlesAddress(int $address): bool
  {
    return $address >= self::DISPLAY_BASE && $address <= self::CONTROL;
  }

  public function read(int $address): int
  {
    switch ($address) {
      case self::CURSOR_X:
        return $this->cursorX;
      case self::CURSOR_Y:
        return $this->cursorY;
      case self::FG_COLOR:
        return $this->fgColor;
      case self::BG_COLOR:
        return $this->bgColor;
      case self::CONTROL:
        return ($this->showCursor ? self::CTRL_SHOW_CURSOR : 0);
      default:
        if ($address >= self::DISPLAY_BASE && $address <= self::DISPLAY_END) {
          return $this->displayMemory[$address - self::DISPLAY_BASE] ?? 0x20; 
        }
        return 0;
    }
  }

  public function write(int $address, int $value): void
  {
    switch ($address) {
      case self::CURSOR_X:
        $this->cursorX = max(0, min(self::WIDTH - 1, $value));
        $this->needsRefresh = true;
        break;
      case self::CURSOR_Y:
        $this->cursorY = max(0, min(self::HEIGHT - 1, $value));
        $this->needsRefresh = true;
        break;
      case self::FG_COLOR:
        $this->fgColor = $value & 0x0F;
        $this->needsRefresh = true;
        break;
      case self::BG_COLOR:
        $this->bgColor = $value & 0x0F;
        $this->needsRefresh = true;
        break;
      case self::CONTROL:
        if ($value & self::CTRL_CLEAR) {
          $this->clearScreen();
        }
        $this->showCursor = ($value & self::CTRL_SHOW_CURSOR) !== 0;
        if ($value & self::CTRL_REFRESH) {
          $this->needsRefresh = true;
        }
        break;
      default:
        if ($address >= self::DISPLAY_BASE && $address <= self::DISPLAY_END) {
          $offset = $address - self::DISPLAY_BASE;
          $this->displayMemory[$offset] = $value & 0xFF;
          $this->needsRefresh = true;
        }
        break;
    }
  }

  public function tick(): void
  {
    
    static $tickCount = 0;
    $tickCount++;

    
    if ($tickCount % 30000 === 0) { 
      $this->needsRefresh = true;
    }
  }

  public function reset(): void
  {
    $this->displayMemory = array_fill(0, self::WIDTH * self::HEIGHT, 0x20); 
    $this->cursorX = 0;
    $this->cursorY = 0;
    $this->fgColor = 7;
    $this->bgColor = 0;
    $this->showCursor = true;
    $this->needsRefresh = true;
  }

  public function refresh(): void
  {
    if (!$this->needsRefresh) {
      return;
    }

    
    echo "\e[2J\e[H"; 
    echo "\e[" . $this->colorMap[$this->fgColor] . "m"; 
    echo "\e[" . ($this->colorMap[$this->bgColor] + 10) . "m"; 

    
    for ($y = 0; $y < self::HEIGHT; $y++) {
      for ($x = 0; $x < self::WIDTH; $x++) {
        $offset = $y * self::WIDTH + $x;
        $char = $this->displayMemory[$offset] ?? 0x20;

        
        if ($x === $this->cursorX && $y === $this->cursorY && $this->showCursor) {
          echo "\e[7m"; 
          echo chr($char);
          echo "\e[27m"; 
        } else {
          echo chr($char);
        }
      }
      echo "\n";
    }

    $this->needsRefresh = false;
  }

  public function clearScreen(): void
  {
    $this->displayMemory = array_fill(0, self::WIDTH * self::HEIGHT, 0x20);
    $this->cursorX = 0;
    $this->cursorY = 0;
    $this->needsRefresh = true;
  }

  public function writeChar(int $char): void
  {
    if ($char === 0x0A) { 
      $this->cursorX = 0;
      $this->cursorY++;
      if ($this->cursorY >= self::HEIGHT) {
        $this->scrollUp();
      }
    } else if ($char >= 0x20 && $char <= 0x7E) { 
      $offset = $this->cursorY * self::WIDTH + $this->cursorX;
      $this->displayMemory[$offset] = $char;
      $this->cursorX++;
      if ($this->cursorX >= self::WIDTH) {
        $this->cursorX = 0;
        $this->cursorY++;
        if ($this->cursorY >= self::HEIGHT) {
          $this->scrollUp();
        }
      }
    }
    $this->needsRefresh = true;
  }

  private function scrollUp(): void
  {
    
    for ($y = 0; $y < self::HEIGHT - 1; $y++) {
      for ($x = 0; $x < self::WIDTH; $x++) {
        $srcOffset = ($y + 1) * self::WIDTH + $x;
        $dstOffset = $y * self::WIDTH + $x;
        $this->displayMemory[$dstOffset] = $this->displayMemory[$srcOffset] ?? 0x20;
      }
    }

    
    for ($x = 0; $x < self::WIDTH; $x++) {
      $offset = (self::HEIGHT - 1) * self::WIDTH + $x;
      $this->displayMemory[$offset] = 0x20;
    }

    $this->cursorY = self::HEIGHT - 1;
  }
}
