; Counter demonstration for Enhanced 6502 System
; Displays a counting number that updates continuously

* = $8000

main:
    LDA #$01        ; Clear screen
    STA $C3EC

    ; Display title
    LDX #$00
print_title:
    LDA title,X
    BEQ init_counter
    STA $C028,X     ; Write at row 1
    INX
    JMP print_title

init_counter:
    LDA #$00        ; Initialize counter
    STA $80         ; Store counter in zero page

counter_loop:
    ; Convert counter to decimal and display
    LDA $80         ; Load counter
    JSR display_number

    ; Increment counter
    INC $80
    LDA $80
    CMP #$64        ; Stop at 100
    BNE delay
    LDA #$00        ; Reset to 0
    STA $80

delay:
    ; Delay loop
    LDY #$FF
delay_outer:
    LDX #$FF
delay_inner:
    DEX
    BNE delay_inner
    DEY
    BNE delay_outer

    JMP counter_loop

display_number:
    ; Display number at position $C050 (row 2)
    ; Convert A register to two-digit decimal
    LDX #$00        ; Clear tens digit

divide_by_ten:
    CMP #$0A
    BCC display_digits
    SEC
    SBC #$0A
    INX
    JMP divide_by_ten

display_digits:
    ; X = tens, A = ones
    TXA
    CLC
    ADC #$30        ; Convert to ASCII
    STA $C050       ; Display tens

    CLC
    ADC #$30        ; Convert ones to ASCII
    STA $C051       ; Display ones

    RTS

title:
    .BYTE "COUNTER DEMO:", $00

; Set reset vector
* = $FFFC
.WORD main