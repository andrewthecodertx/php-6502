; Simple test program that outputs one character and exits
; This should help debug the display output issue

* = $8000

main:
    LDA #$01        ; Clear screen command
    STA $C3EC       ; Send to display control register

    LDA #$48        ; ASCII 'H'
    STA $D000       ; Output to console

    LDA #$69        ; ASCII 'i'
    STA $D000       ; Output to console

    BRK             ; Break - should stop execution cleanly

; Set reset vector
* = $FFFC
.WORD main