* = $8000

main:
  LDA #$48        ; ASCII 'H'
  STA $C000       ; Store at top-left
  LDA #$45        ; ASCII 'E'
  STA $C001
  LDA #$4C        ; ASCII 'L'
  STA $C002
  LDA #$4C        ; ASCII 'L'
  STA $C003
  LDA #$4F        ; ASCII 'O'
  STA $C004

done:
  JMP done        ; Infinite loop

* = $FFFC
.WORD main
