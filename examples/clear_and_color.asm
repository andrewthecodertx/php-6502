; Set foreground to bright white
LDA #$0F
STA $C3EA

; Set background to blue
LDA #$01
STA $C3EB

; Clear the screen
LDA #$01        ; CLEAR command
STA $C3EC       ; Execute clear
