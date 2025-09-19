<?php

declare(strict_types=1);

namespace Emulator\Peripherals;

use Emulator\Bus\PeripheralInterface;

class TextDisplay implements PeripheralInterface
{
  // Memory map
  public const DISPLAY_BASE = 0xC000;       // Start of display memory
  public const DISPLAY_END = 0xC3E7;        // End (40x25 = 1000 bytes)
  public const CURSOR_X = 0xC3E8;           // Cursor X position
  public const CURSOR_Y = 0xC3E9;           // Cursor Y position
  public const FG_COLOR = 0xC3EA;           // Foreground color
  public const BG_COLOR = 0xC3EB;           // Background color
  public const CONTROL = 0xC3EC;            // Control register

  // Display dimensions
  public const WIDTH = 40;
  public const HEIGHT = 25;

  // Control bits
  public const CTRL_CLEAR = 0x01;           // Clear screen
  public const CTRL_SHOW_CURSOR = 0x02;     // Show cursor
  public const CTRL_REFRESH = 0x04;         // Force refresh

  private array $displayMemory = [];
  private int $cursorX = 0;
  private int $cursorY = 0;
  private int $fgColor = 7;  // White
  private int $bgColor = 0;  // Black
  private bool $showCursor = true;
  private bool $needsRefresh = true;

  // ANSI color map
  private array $colorMap = [
    0 => '30', // Black
    1 => '34', // Blue
    2 => '32', // Green
    3 => '36', // Cyan
    4 => '31', // Red
    5 => '35', // Magenta
    6 => '33', // Yellow
    7 => '37', // White
    8 => '90', // Bright Black (Gray)
    9 => '94', // Bright Blue
    10 => '92', // Bright Green
    11 => '96', // Bright Cyan
    12 => '91', // Bright Red
    13 => '95', // Bright Magenta
    14 => '93', // Bright Yellow
    15 => '97', // Bright White
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
          return $this->displayMemory[$address - self::DISPLAY_BASE] ?? 0x20; // Space
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
    // Could implement blinking cursor here
    static $tickCount = 0;
    $tickCount++;

    // Refresh display occasionally for cursor blink
    if ($tickCount % 30000 === 0) { // Approximately every half second at 60Hz
      $this->needsRefresh = true;
    }
  }

  public function reset(): void
  {
    $this->displayMemory = array_fill(0, self::WIDTH * self::HEIGHT, 0x20); // Fill with spaces
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

    // Clear screen and set colors
    echo "\e[2J\e[H"; // Clear screen, home cursor
    echo "\e[" . $this->colorMap[$this->fgColor] . "m"; // Set foreground
    echo "\e[" . ($this->colorMap[$this->bgColor] + 10) . "m"; // Set background

    // Draw display
    for ($y = 0; $y < self::HEIGHT; $y++) {
      for ($x = 0; $x < self::WIDTH; $x++) {
        $offset = $y * self::WIDTH + $x;
        $char = $this->displayMemory[$offset] ?? 0x20;

        // Show cursor
        if ($x === $this->cursorX && $y === $this->cursorY && $this->showCursor) {
          echo "\e[7m"; // Reverse video
          echo chr($char);
          echo "\e[27m"; // Normal video
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
    if ($char === 0x0A) { // Newline
      $this->cursorX = 0;
      $this->cursorY++;
      if ($this->cursorY >= self::HEIGHT) {
        $this->scrollUp();
      }
    } else if ($char >= 0x20 && $char <= 0x7E) { // Printable ASCII
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
    // Move all lines up by one
    for ($y = 0; $y < self::HEIGHT - 1; $y++) {
      for ($x = 0; $x < self::WIDTH; $x++) {
        $srcOffset = ($y + 1) * self::WIDTH + $x;
        $dstOffset = $y * self::WIDTH + $x;
        $this->displayMemory[$dstOffset] = $this->displayMemory[$srcOffset] ?? 0x20;
      }
    }

    // Clear bottom line
    for ($x = 0; $x < self::WIDTH; $x++) {
      $offset = (self::HEIGHT - 1) * self::WIDTH + $x;
      $this->displayMemory[$offset] = 0x20;
    }

    $this->cursorY = self::HEIGHT - 1;
  }
}