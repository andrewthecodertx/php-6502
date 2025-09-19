; Hello World program for Enhanced 6502 System
; Uses memory-mapped console I/O at $D000

* = $8000           ; Start program at $8000

main:
    LDA #$01        ; Clear screen command
    STA $C3EC       ; Send to display control register

    LDX #$00        ; Initialize string index

print_loop:
    LDA message,X   ; Load character from message
    BEQ done        ; If zero, we're done
    STA $D000       ; Output to console
    INX             ; Next character
    JMP print_loop  ; Continue loop

done:
    JMP done        ; Infinite loop (halt)

message:
    .BYTE "Hello, World!", $0A, $00  ; String with newline and null terminator

; Set reset vector
* = $FFFC
.WORD main          ; Reset vector points to main
