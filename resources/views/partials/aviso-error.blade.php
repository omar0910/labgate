{{-- Por qué no se hizo algo. Varias pantallas sólo mostraban los mensajes de
     éxito: si algo se rechazaba, parecía que no había pasado nada. --}}
@if (session('error') || $errors->any())
    <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') ?? $errors->first() }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif
