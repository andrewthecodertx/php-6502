; Showcase demo - automatically runs multiple features
; This is the assembly version of the old auto_demo.php

* = $8000

main:
    JSR welcome
    JSR display_demo
    JSR sound_demo
    JSR color_demo
    JSR finale

infinite_loop:
    JMP infinite_loop

; === WELCOME SUBROUTINE ===
welcome:
    LDA #$01        ; Clear screen
    STA $C3EC

    ; Set bright green color
    LDA #$0A
    STA $C3ED

    ; Display "6502 ENHANCED SYSTEM!" at row 5
    LDX #$00
    LDY #$C8       ; Row 5 * 40 = 200 = $C8

print_welcome:
    LDA welcome_msg,X
    BEQ welcome_delay
    STA $C000,Y
    INX
    INY
    JMP print_welcome

welcome_delay:
    JSR long_delay
    RTS

; === DISPLAY DEMO ===
display_demo:
    LDA #$01        ; Clear screen
    STA $C3EC

    ; Set white color
    LDA #$0F
    STA $C3ED

    ; Show display capabilities
    LDX #$00
    LDY #$50       ; Row 2

print_display_msg:
    LDA display_msg,X
    BEQ display_pattern
    STA $C000,Y
    INX
    INY
    JMP print_display_msg

display_pattern:
    ; Create a pattern on screen
    LDX #$00
    LDY #$A0       ; Row 4

pattern_loop:
    TXA
    AND #$07       ; Create repeating pattern
    CLC
    ADC #$30       ; Convert to ASCII
    STA $C000,Y
    INX
    INY
    CPX #$28       ; 40 characters
    BNE pattern_loop

    JSR long_delay
    RTS

; === SOUND DEMO ===
sound_demo:
    LDA #$01        ; Clear screen
    STA $C3EC

    ; Display sound message
    LDX #$00
    LDY #$50

print_sound_msg:
    LDA sound_msg,X
    BEQ play_sounds
    STA $C000,Y
    INX
    INY
    JMP print_sound_msg

play_sounds:
    ; Play ascending tones
    LDX #$20       ; Starting frequency

tone_loop:
    ; Set frequency
    STX $C400      ; Channel 0 frequency low
    LDA #$02
    STA $C401      ; Channel 0 frequency high

    ; Set volume
    LDA #$0F
    STA $C402      ; Max volume

    ; Brief delay
    JSR short_delay

    ; Stop sound
    LDA #$00
    STA $C402

    ; Next frequency
    INX
    CPX #$60
    BNE tone_loop

    JSR long_delay
    RTS

; === COLOR DEMO ===
color_demo:
    LDA #$01        ; Clear screen
    STA $C3EC

    ; Cycle through colors
    LDX #$00       ; Color index

color_loop:
    ; Set color
    STX $C3ED

    ; Display color name at row 10
    LDY #$90       ; Row 10 * 40 = 400 = $190
    LDA #$43       ; 'C'
    STA $C000,Y
    INY
    LDA #$4F       ; 'O'
    STA $C000,Y
    INY
    LDA #$4C       ; 'L'
    STA $C000,Y
    INY
    LDA #$4F       ; 'O'
    STA $C000,Y
    INY
    LDA #$52       ; 'R'
    STA $C000,Y
    INY
    LDA #$20       ; Space
    STA $C000,Y
    INY

    ; Display color number
    TXA
    CMP #$0A
    BCC single_digit
    SEC
    SBC #$0A
    CLC
    ADC #$31       ; '1'
    STA $C000,Y
    INY
    TXA
    AND #$0F
    SEC
    SBC #$0A

single_digit:
    CLC
    ADC #$30       ; Convert to ASCII
    STA $C000,Y

    JSR short_delay

    ; Next color
    INX
    CPX #$10       ; 16 colors
    BNE color_loop

    JSR long_delay
    RTS

; === FINALE ===
finale:
    LDA #$01        ; Clear screen
    STA $C3EC

    ; Set bright white
    LDA #$0F
    STA $C3ED

    ; Display finale message
    LDX #$00
    LDY #$78       ; Row 3

print_finale:
    LDA finale_msg,X
    BEQ finale_done
    STA $C000,Y
    INX
    INY
    JMP print_finale

finale_done:
    RTS

; === DELAY ROUTINES ===
short_delay:
    LDY #$80
short_outer:
    LDA #$FF
short_inner:
    SEC
    SBC #$01
    BNE short_inner
    DEY
    BNE short_outer
    RTS

long_delay:
    LDY #$FF
long_outer:
    JSR short_delay
    DEY
    BNE long_outer
    RTS

; === MESSAGES ===
welcome_msg:
    .BYTE "6502 ENHANCED SYSTEM!", $00

display_msg:
    .BYTE "DISPLAY TEST: 40x25 TEXT MODE", $00

sound_msg:
    .BYTE "SOUND TEST: 4-CHANNEL AUDIO", $00

finale_msg:
    .BYTE "DEMO COMPLETE - SYSTEM READY!", $00

; Set reset vector
* = $FFFC
.WORD main