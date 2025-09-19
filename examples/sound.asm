; Sound demonstration for Enhanced 6502 System
; Plays a simple melody on multiple channels

* = $8000

main:
    LDA #$01        ; Clear screen
    STA $C3EC

    ; Display title
    LDX #$00
print_title:
    LDA title,X
    BEQ start_music
    STA $C000,X
    INX
    JMP print_title

start_music:
    LDX #$00        ; Note index

play_melody:
    ; Get frequency for channel 0
    LDA melody,X
    BEQ done        ; End of melody
    STA $C400       ; Channel 0 frequency low
    LDA #$02
    STA $C401       ; Channel 0 frequency high

    ; Set volume
    LDA #$0F        ; Max volume
    STA $C402       ; Channel 0 volume

    ; Play harmony on channel 1 (fifth above)
    LDA melody,X
    CLC
    ADC #$10        ; Add to frequency for harmony
    STA $C404       ; Channel 1 frequency low
    LDA #$02
    STA $C405       ; Channel 1 frequency high
    LDA #$08        ; Lower volume for harmony
    STA $C406       ; Channel 1 volume

    ; Delay for note duration
    LDY #$80
note_delay:
    LDA #$FF
inner_delay:
    SEC
    SBC #$01
    BNE inner_delay
    DEY
    BNE note_delay

    ; Stop sound
    LDA #$00
    STA $C402       ; Channel 0 volume off
    STA $C406       ; Channel 1 volume off

    ; Short pause between notes
    LDY #$20
pause_delay:
    DEY
    BNE pause_delay

    ; Next note
    INX
    JMP play_melody

done:
    JMP done

title:
    .BYTE "PLAYING MUSIC...", $00

melody:
    .BYTE $40, $45, $50, $45, $40, $38, $40, $45, $50, $55, $50, $45, $40, $00

; Set reset vector
* = $FFFC
.WORD main