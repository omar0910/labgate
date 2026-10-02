{{--
    Franja que avisa que se está en la demostración pública. Sólo aparece con
    DEMO=true. También muestra el aviso cuando se intenta algo que la demostración
    no permite (ver App\Http\Middleware\ModoDemo).
--}}
@if (config('demo.activo'))
    <div style="background-color: #FFE900; color: #1a1a1a; font-size: 0.82rem; line-height: 1.35;"
        class="text-center fw-bold py-1 px-3">
        <i class="bi bi-info-circle-fill me-1"></i>
        Demostración con datos ficticios · se reinicia cada día a las {{ config('demo.hora_de_reinicio') }}
    </div>

    @if (session('aviso_demo'))
        <div class="alert alert-warning rounded-0 border-0 mb-0 text-center fw-bold" role="alert">
            <i class="bi bi-lock-fill me-1"></i> {{ session('aviso_demo') }}
        </div>
    @endif
@endif
