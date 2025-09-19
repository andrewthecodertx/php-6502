; Color demonstration for Enhanced 6502 System
; Cycles through different text colors

* = $8000

main:
    LDA #$01        ; Clear screen command
    STA $C3EC       ; Send to display control register

    LDX #$00        ; Color index

color_loop:
    ; Set color
    TXA
    STA $C3ED       ; Set text color

    ; Write color number at position
    TXA
    CLC
    ADC #$30        ; Convert to ASCII digit
    STA $C000,X     ; Write to display at position X

    ; Write "COLOR" after the digit
    LDA #$20        ; Space
    STA $C001,X
    LDA #$43        ; 'C'
    STA $C002,X
    LDA #$4F        ; 'O'
    STA $C003,X
    LDA #$4C        ; 'L'
    STA $C004,X
    LDA #$4F        ; 'O'
    STA $C005,X
    LDA #$52        ; 'R'
    STA $C006,X

    ; Delay loop
    LDY #$FF
delay_outer:
    LDA #$FF
delay_inner:
    SEC
    SBC #$01
    BNE delay_inner
    DEY
    BNE delay_outer

    ; Next color
    INX
    CPX #$10        ; 16 colors (0-15)
    BNE color_loop

    ; Reset to white
    LDA #$0F
    STA $C3ED

done:
    JMP done

; Set reset vector
* = $FFFC
.WORD main