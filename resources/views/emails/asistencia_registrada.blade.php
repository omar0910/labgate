<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #003366;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .header h2 {
            color: #003366;
            margin: 0;
        }

        .details {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .details p {
            margin: 5px 0;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>Registro Exitoso</h2>
            <p>{{ config('marca.institucion') }}</p>
        </div>

        <p>Hola, <strong>{{ $asistencia->user->name }}</strong>.</p>
        <p>Tu entrada al laboratorio ha sido registrada correctamente en el sistema.</p>

        <div class="details">
            <p><strong>📅 Fecha:</strong> {{ \Carbon\Carbon::parse($asistencia->fecha)->format('d/m/Y') }}</p>
            <p><strong>⏰ Hora de Entrada:</strong>
                {{ \Carbon\Carbon::parse($asistencia->fecha_hora_registro)->format('H:i:s') }}</p>
            <p><strong>🖥️ Equipo:</strong> PC-{{ $asistencia->numero_maquina }}</p>
            <p><strong>📍 Laboratorio:</strong> {{ $asistencia->centroComputo->nombre_centro }}</p>

            @if ($asistencia->tipo == 'Clase')
                <p><strong>📚 Actividad:</strong> Clase
                    ({{ $asistencia->horario->materia->nombre_materia ?? 'Materia' }})</p>
            @else
                <p><strong>🚀 Actividad:</strong> Uso Libre</p>
            @endif
        </div>

        <p>Recuerda cerrar tu sesión al terminar para liberar el equipo.</p>

        <div class="footer">
            Este es un correo automático, por favor no respondas a este mensaje.
        </div>
    </div>
</body>

</html>
