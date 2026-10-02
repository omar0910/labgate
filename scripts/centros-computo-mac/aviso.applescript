-- Centro de Computo LabGate - aviso en pantalla del vigilante para Mac.
--
-- Uso: osascript aviso.applescript "titulo" "mensaje" "boton" segundos
--
-- Los textos llegan como argumentos (y no escritos aqui) para que los acentos
-- se vean bien. La ventana se cierra sola al pasar los segundos indicados.

on run argv
	set titulo to item 1 of argv
	set mensaje to item 2 of argv
	set boton to item 3 of argv
	set segundos to (item 4 of argv) as integer

	activate
	try
		display dialog mensaje with title titulo buttons {boton} default button 1 with icon caution giving up after segundos
	end try
end run
