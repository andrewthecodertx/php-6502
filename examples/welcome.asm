; Welcome message demo for Enhanced 6502 System
; Displays a welcome message with colors

* = $8000

main:
    LDA #$01        ; Clear screen command
    STA $C3EC       ; Send to display control register

    ; Set text color to bright green (10)
    LDA #$0A
    STA $C3ED

    ; Write "ENHANCED 6502 SYSTEM" centered at row 10
    LDX #$00
    LDY #$0A        ; Row 10

print_title:
    LDA title,X
    BEQ set_normal_color
    JSR write_char_at_pos
    INX
    INY
    JMP print_title

set_normal_color:
    ; Set back to white
    LDA #$0F
    STA $C3ED

    ; Write welcome message
    LDX #$00
    LDY #$F0        ; Row 12, col 0

print_message:
    LDA message,X
    BEQ done
    JSR write_char_at_pos
    INX
    INY
    JMP print_message

done:
    JMP done        ; Halt

write_char_at_pos:
    ; Calculate position: Y contains row*40 + col
    STA $C000,Y     ; Write to display memory
    RTS

title:
    .BYTE "ENHANCED 6502 SYSTEM", $00

message:
    .BYTE "Welcome to the PHP 6502 Emulator!", $00

; Set reset vector
* = $FFFC
.WORD main