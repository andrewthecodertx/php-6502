# Video Mode Guide

This guide covers how to use the video system in the 6502 emulator, including
both Graphics Mode and Terminal Mode.

## Overview

The emulator provides two video modes:

- **GraphicsMode**: Direct memory-mapped graphics with 40x25 character grid
- **TerminalMode**: Higher-level terminal interface that uses GraphicsMode

## Graphics Mode (Direct Video)

### Memory Map

| Address Range | Purpose | Description |
|---------------|---------|-------------|
| `$C000-$C3E7` | Display Memory | 40x25 character grid (1000 bytes) |
| `$C3E8` | Cursor X | Horizontal cursor position (0-39) |
| `$C3E9` | Cursor Y | Vertical cursor position (0-24) |
| `$C3EA` | Foreground Color | Text color (0-15) |
| `$C3EB` | Background Color | Background color (0-15) |
| `$C3EC` | Control Register | Display control commands |

### Display Dimensions

- **Width**: 40 characters
- **Height**: 25 lines
- **Total**: 1000 character positions

### Memory Layout

The display memory is organized as a linear array:

```
Position = Y * 40 + X
Address = $C000 + Position
```

Examples:

- Top-left corner (0,0): `$C000`
- Top-right corner (39,0): `$C027`
- Bottom-left corner (0,24): `$C3C0`
- Bottom-right corner (39,24): `$C3E7`

### Control Commands

Write to `$C3EC` to execute control commands:

| Bit | Value | Command | Description |
|-----|-------|---------|-------------|
| 0 | `$01` | CLEAR | Clear screen and reset cursor to (0,0) |
| 1 | `$02` | SHOW_CURSOR | Enable/disable cursor visibility |
| 2 | `$04` | REFRESH | Force display refresh |

### Color Values

| Value | Color | Value | Color |
|-------|-------|-------|-------|
| 0 | Black | 8 | Dark Gray |
| 1 | Blue | 9 | Light Blue |
| 2 | Green | 10 | Light Green |
| 3 | Cyan | 11 | Light Cyan |
| 4 | Red | 12 | Light Red |
| 5 | Magenta | 13 | Light Magenta |
| 6 | Yellow | 14 | Light Yellow |
| 7 | White | 15 | Bright White |

## Basic Usage Examples

### Example 1: Display "HELLO" at top-left

```assembly
; Load 'H' and store at position (0,0)
LDA #$48        ; ASCII 'H'
STA $C000       ; Store at top-left

; Load 'E' and store at position (1,0)
LDA #$45        ; ASCII 'E'
STA $C001       ; Store at (1,0)

; Continue for remaining characters...
LDA #$4C        ; ASCII 'L'
STA $C002
LDA #$4C        ; ASCII 'L'
STA $C003
LDA #$4F        ; ASCII 'O'
STA $C004
```

### Example 2: Clear screen and set colors

```assembly
; Set foreground to bright white
LDA #$0F
STA $C3EA

; Set background to blue
LDA #$01
STA $C3EB

; Clear the screen
LDA #$01        ; CLEAR command
STA $C3EC       ; Execute clear
```

### Example 3: Position cursor and display character

```assembly
; Set cursor to position (10, 5)
LDA #$0A        ; X = 10
STA $C3E8
LDA #$05        ; Y = 5
STA $C3E9

; Calculate memory address: $C000 + (5 * 40) + 10 = $C0CA
LDA #$41        ; ASCII 'A'
STA $C0CA       ; Store at calculated position
```

### Example 4: Hide cursor

```assembly
; Read current control register
LDA $C3EC
; Clear the SHOW_CURSOR bit (bit 1)
AND #$FD        ; Clear bit 1, keep others
STA $C3EC       ; Write back
```

## Terminal Mode (Character I/O)

### Memory Map

| Address | Purpose | Description |
|---------|---------|-------------|
| `$D000` | Console Output | Write characters here for display |
| `$D001` | Input Status | Read: 0x80 if input ready, 0x00 if not |
| `$D002` | Input Data | Read: next character from input buffer |
| `$D003` | Control Register | Console control flags |

### Control Flags

| Bit | Value | Flag | Description |
|-----|-------|------|-------------|
| 0 | `$01` | ECHO | Echo input characters to display |
| 1 | `$02` | LINE_MODE | Line-buffered input mode |
| 2 | `$04` | CLEAR_INPUT | Clear input buffer |

### Usage Examples

#### Simple Text Output

```assembly
; Display "HI" using console
LDA #$48        ; ASCII 'H'
STA $D000       ; Write to console output
LDA #$49        ; ASCII 'I'
STA $D000       ; Write to console output
```

#### Check for Input

```assembly
; Check if input is available
LDA $D001       ; Read input status
AND #$80        ; Check ready bit
BEQ NO_INPUT    ; Branch if no input

; Read the character
LDA $D002       ; Read input data
; Process character in A register...

NO_INPUT:
; Continue program...
```

## Assembly Programming Tips

### Calculating Display Positions

To display text at specific coordinates:

```assembly
; Function: Calculate display address for position (X,Y)
; Input: X in $00, Y in $01
; Output: Address in $02,$03

CALC_POS:
    LDA $01         ; Load Y coordinate
    ASL A           ; Y * 2
    ASL A           ; Y * 4
    ASL A           ; Y * 8
    STA $02         ; Store Y * 8
    LDA $01         ; Load Y again
    ASL A           ; Y * 2
    ASL A           ; Y * 4
    ASL A           ; Y * 8
    ASL A           ; Y * 16
    ASL A           ; Y * 32
    CLC
    ADC $02         ; Add Y * 8 = Y * 40
    CLC
    ADC $00         ; Add X coordinate
    STA $02         ; Store low byte
    LDA #$C0        ; High byte of $C000
    STA $03         ; Store high byte
    RTS
```

### String Display Function

```assembly
; Display null-terminated string
; String address in $04,$05
PRINT_STRING:
    LDY #$00        ; String index
PRINT_LOOP:
    LDA ($04),Y     ; Load character
    BEQ PRINT_DONE  ; Exit if null terminator
    STA $D000       ; Output to console
    INY             ; Next character
    JMP PRINT_LOOP
PRINT_DONE:
    RTS

; Example usage:
    LDA #<MESSAGE   ; Low byte of string address
    STA $04
    LDA #>MESSAGE   ; High byte of string address
    STA $05
    JSR PRINT_STRING

MESSAGE: .BYTE "HELLO WORLD", 0
```

### Scrolling Text

```assembly
; Simple scrolling by writing past screen bottom
; The display will automatically scroll when cursor reaches bottom
SCROLL_DEMO:
    LDX #$00        ; Line counter
SCROLL_LOOP:
    LDA #$41        ; ASCII 'A'
    CLC
    ADC LINE_NUM    ; Add line number to make unique
    STA $D000       ; Display character
    LDA #$0A        ; Newline
    STA $D000       ; This will cause scroll at bottom
    INX
    CPX #$30        ; Display 30 lines
    BNE SCROLL_LOOP
    RTS
```

## Common Patterns

### Clear Screen and Home Cursor

```assembly
LDA #$01        ; CLEAR command
STA $C3EC       ; Clear screen
```

### Set Display Colors

```assembly
LDA #$0E        ; Yellow foreground
STA $C3EA
LDA #$04        ; Red background
STA $C3EB
```

### Display Single Character at Cursor

```assembly
; Character in A register
STA $D000       ; Use console for automatic cursor advancement
```

### Read Cursor Position

```assembly
LDA $C3E8       ; Read cursor X
STA CURSOR_X    ; Save X position
LDA $C3E9       ; Read cursor Y
STA CURSOR_Y    ; Save Y position
```

## Notes

- The display automatically refreshes periodically during CPU execution
- Characters outside the printable ASCII range (0x20-0x7E) may not display properly
- The TerminalMode automatically handles cursor advancement and line wrapping
- Direct GraphicsMode access gives you complete control but requires manual
cursor management
- Both peripherals share the same underlying display buffer

## Troubleshooting

- **No display output**: Check that your program is writing to the correct
memory addresses
- **Garbled display**: Ensure you're writing valid ASCII character codes (0x20-0x7E)
- **Cursor not visible**: Check the SHOW_CURSOR bit in the control register ($C3EC)
- **Colors not working**: Verify color values are in range 0-15 and written to
correct addresses
