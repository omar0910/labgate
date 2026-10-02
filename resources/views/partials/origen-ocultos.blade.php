{{--
    Campos ocultos con el origen de la pantalla (ver App\Support\Origen), para que
    un formulario de filtros no pierda a dónde regresa "Volver".
--}}
@foreach (\App\Support\Origen::parametros(request()) as $nombre => $valor)
    <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
@endforeach
