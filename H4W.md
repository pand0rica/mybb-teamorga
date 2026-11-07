# h4w-Anpassungen

Dieser Branch ist `main` **plus** die Anpassungen für das h4w-Forum. Regeln: Dev-Kit
`standards/conventions.md` §12 – Neuerungen zuerst auf `main`, dann `main` → `h4w` mergen,
nie umgekehrt. Deploy ins h4w-Test-/Live-Forum von diesem Branch.

## Anpassungen

| Bereich | Dateien | Zweck |
|---|---|---|
| CSS-Variablen des h4w-Themes | `inc/plugins/teamorga/teamorga.css` | Statusfarben, Prioritäts-Pills, Textfarben auf Akzentflächen und Status-Punkte als `var(--h4w-…, Fallback)` (23 Stellen). Auf `main` stehen die neutralen `--to-*`-Werte. |
| Formular „Neues Thema/Antwort“ | `inc/plugins/teamorga/templates/teamorga_inputfields.tpl`, `teamorga_newreply_suggestion.tpl` | Das h4w-Theme baut `newthread`/`newreply` als `<div class="h4w-post-container">` (Label-/Feld-`h4w-post-item` im Wechsel). Die Standard-`<tr>`-Zeilen sind dort ungültiges HTML und wurden vom Browser zerlegt. Auf diesem Branch liefern beide Templates daher Label/Feld-Paare als `div.h4w-post-item`. |
