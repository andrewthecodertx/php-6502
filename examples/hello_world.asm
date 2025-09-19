* = $8000

start:
    ; Display "HELLO" using console
    LDA #$48        ; ASCII 'H'
    STA $D000       ; Write to console output
    LDA #$45        ; ASCII 'E'
    STA $D000
    LDA #$4C        ; ASCII 'L'
    STA $D000
    LDA #$4C        ; ASCII 'L'
    STA $D000
    LDA #$4F        ; ASCII 'O'
    STA $D000

    ; Add a space
    LDA #$20        ; ASCII space
    STA $D000

    ; Display "WORLD"
    LDA #$57        ; ASCII 'W'
    STA $D000
    LDA #$4F        ; ASCII 'O'
    STA $D000
    LDA #$52        ; ASCII 'R'
    STA $D000
    LDA #$4C        ; ASCII 'L'
    STA $D000
    LDA #$44        ; ASCII 'D'
    STA $D000

    ; Small delay to ensure display refresh
    LDX #$FF
delay:
    DEX
    BNE delay

    ; Infinite loop
loop:
    JMP loop

* = $FFFC
.WORD start