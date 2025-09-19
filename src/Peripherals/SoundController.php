<?php

declare(strict_types=1);

namespace Emulator\Peripherals;

use Emulator\Bus\PeripheralInterface;

class SoundController implements PeripheralInterface
{
  // Memory map
  public const SOUND_BASE = 0xC400;
  public const SOUND_END = 0xC40F;

  // Channel registers (4 channels, 4 bytes each)
  public const CH0_FREQ_LO = 0xC400;
  public const CH0_FREQ_HI = 0xC401;
  public const CH0_VOLUME = 0xC402;
  public const CH0_CONTROL = 0xC403;

  public const CH1_FREQ_LO = 0xC404;
  public const CH1_FREQ_HI = 0xC405;
  public const CH1_VOLUME = 0xC406;
  public const CH1_CONTROL = 0xC407;

  public const CH2_FREQ_LO = 0xC408;
  public const CH2_FREQ_HI = 0xC409;
  public const CH2_VOLUME = 0xC40A;
  public const CH2_CONTROL = 0xC40B;

  public const CH3_FREQ_LO = 0xC40C;
  public const CH3_FREQ_HI = 0xC40D;
  public const CH3_VOLUME = 0xC40E;
  public const CH3_CONTROL = 0xC40F;

  // Control bits
  public const CTRL_ENABLE = 0x01;
  public const CTRL_SQUARE = 0x00;    // Square wave (default)
  public const CTRL_NOISE = 0x02;     // Noise
  public const CTRL_TRIANGLE = 0x04;  // Triangle wave

  private array $channels = [];
  private int $masterVolume = 15;

  public function __construct()
  {
    $this->reset();
  }

  public function handlesAddress(int $address): bool
  {
    return $address >= self::SOUND_BASE && $address <= self::SOUND_END;
  }

  public function read(int $address): int
  {
    $channel = ($address - self::SOUND_BASE) >> 2; // 4 registers per channel
    $register = ($address - self::SOUND_BASE) & 3;

    if ($channel >= 4) return 0;

    switch ($register) {
      case 0: return $this->channels[$channel]['freq'] & 0xFF;
      case 1: return ($this->channels[$channel]['freq'] >> 8) & 0xFF;
      case 2: return $this->channels[$channel]['volume'];
      case 3: return $this->channels[$channel]['control'];
    }

    return 0;
  }

  public function write(int $address, int $value): void
  {
    $channel = ($address - self::SOUND_BASE) >> 2;
    $register = ($address - self::SOUND_BASE) & 3;

    if ($channel >= 4) return;

    switch ($register) {
      case 0: // Frequency low
        $this->channels[$channel]['freq'] =
          ($this->channels[$channel]['freq'] & 0xFF00) | ($value & 0xFF);
        break;
      case 1: // Frequency high
        $this->channels[$channel]['freq'] =
          ($this->channels[$channel]['freq'] & 0x00FF) | (($value & 0xFF) << 8);
        break;
      case 2: // Volume
        $this->channels[$channel]['volume'] = $value & 0x0F;
        break;
      case 3: // Control
        $this->channels[$channel]['control'] = $value & 0xFF;
        if ($value & self::CTRL_ENABLE) {
          $this->playTone($channel);
        }
        break;
    }
  }

  public function tick(): void
  {
    // Update sound generation - in a real implementation this would
    // generate audio samples, but for CLI we'll use simple beeps
  }

  public function reset(): void
  {
    for ($i = 0; $i < 4; $i++) {
      $this->channels[$i] = [
        'freq' => 0,
        'volume' => 0,
        'control' => 0,
        'phase' => 0
      ];
    }
  }

  private function playTone(int $channel): void
  {
    $freq = $this->channels[$channel]['freq'];
    $volume = $this->channels[$channel]['volume'];
    $control = $this->channels[$channel]['control'];

    if (!($control & self::CTRL_ENABLE) || $volume === 0 || $freq === 0) {
      return;
    }

    // Convert 6502 frequency to Hz (simple mapping)
    $hz = max(50, min(2000, $freq)); // Clamp to reasonable range

    // Generate sound based on platform
    if (php_sapi_name() === 'cli') {
      $this->generateBeep($hz, $volume);
    }
  }

  private function generateBeep(int $frequency, int $volume): void
  {
    // For CLI, we can use terminal bell or system beep
    // This is platform-specific and simplified

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      // Windows
      $duration = max(100, min(1000, $volume * 100));
      pclose(popen("powershell -c \"[console]::beep($frequency, $duration)\"", "r"));
    } else {
      // Unix-like systems - use speaker-test or play command if available
      $duration = $volume * 0.1; // Volume affects duration

      // Try different methods
      if (shell_exec('which speaker-test 2>/dev/null')) {
        // Use speaker-test for tone generation
        shell_exec("timeout {$duration}s speaker-test -t sine -f $frequency -l 1 >/dev/null 2>&1 &");
      } elseif (shell_exec('which play 2>/dev/null')) {
        // Use sox/play if available
        shell_exec("play -n synth {$duration} sine $frequency vol 0.1 >/dev/null 2>&1 &");
      } else {
        // Fallback to terminal bell
        echo "\a";
      }
    }
  }

  public function playNote(int $channel, int $note, int $volume = 10, int $duration = 500): void
  {
    // Convert MIDI note to frequency (A4 = 440Hz = note 69)
    $frequency = intval(440 * pow(2, ($note - 69) / 12));

    $this->channels[$channel]['freq'] = $frequency;
    $this->channels[$channel]['volume'] = $volume & 0x0F;
    $this->channels[$channel]['control'] = self::CTRL_ENABLE | self::CTRL_SQUARE;

    $this->playTone($channel);
  }

  public function silence(): void
  {
    for ($i = 0; $i < 4; $i++) {
      $this->channels[$i]['control'] &= ~self::CTRL_ENABLE;
    }
  }
}