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
