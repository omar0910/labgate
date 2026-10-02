{{--
    Armazón común de los avisos inmediatos.

    Mantiene la identidad del instituto: verde #009B4D, amarillo #FFE900 y el negro
    suave #1a1a1a, los mismos del sistema. Todo el estilo va en línea porque los
    clientes de correo descartan las hojas de estilo externas, y muchos también las
    etiquetas <style> del encabezado.

    Se usa así:
        @extends('emails.partials.base', ['titulo' => '...', 'color' => '#009B4D'])
        @section('contenido') ... @endsection
--}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? config('marca.area') . ' · ' . config('marca.sistema') }}</title>
</head>

<body
    style="margin:0; padding:0; background-color:#f4f6f9; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#1a1a1a;">

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
        style="background-color:#f4f6f9; padding:24px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                    style="max-width:600px; width:100%; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.08);">

                    {{-- Franja superior con el color del aviso --}}
                    <tr>
                        <td style="background-color:{{ $color ?? '#009B4D' }}; height:6px; line-height:6px;">&nbsp;</td>
                    </tr>

                    {{-- Encabezado institucional --}}
                    <tr>
                        <td style="background-color:#1a1a1a; padding:22px 30px;">
                            <div
                                style="color:#FFE900; font-size:12px; letter-spacing:1px; text-transform:uppercase; font-weight:bold;">
                                {{ config('marca.institucion_linea_1') }}
                            </div>
                            <div style="color:#ffffff; font-size:18px; font-weight:bold; margin-top:2px;">
                                {{ config('marca.institucion_linea_2') }}
                            </div>
                            <div style="color:#9e9e9e; font-size:12px; margin-top:6px;">
                                {{ config('marca.sistema') }} · {{ config('marca.area') }}
                            </div>
                        </td>
                    </tr>

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:30px;">
                            @yield('contenido')
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td
                            style="background-color:#f4f6f9; padding:18px 30px; border-top:1px solid #e6e6e6; color:#777777; font-size:12px; line-height:1.6;">
                            Este mensaje se generó automáticamente, no hace falta responderlo.
                            <br>
                            Si algo no coincide con lo que hiciste, avisa al personal del Centro de Cómputo.
                        </td>
                    </tr>

                </table>

                <div style="color:#9e9e9e; font-size:11px; margin-top:14px;">
                    {{ config('marca.area') }} · {{ config('marca.siglas') }}
                </div>

            </td>
        </tr>
    </table>

</body>

</html>
