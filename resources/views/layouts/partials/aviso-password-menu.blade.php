{{--
    Recordatorio dentro del menú de la foto de perfil. Acompaña al punto rojo:
    el punto llama la atención y esto explica por qué.
--}}
@if (session('password_por_defecto'))
    <a class="dropdown-item py-2" href="{{ route('profile.show') }}#seguridad"
        style="background-color: #fffbea; border-left: 3px solid var(--marca-yellow);">
        <span class="fw-bold d-block" style="color: #8a6d00;">
            <i class="bi bi-shield-exclamation me-2"></i>Cambia tu contraseña
        </span>
        <span class="d-block text-muted" style="font-size: .75rem; white-space: normal; max-width: 240px;">
            Sigues usando la que te dio el sistema.
        </span>
    </a>
    <div class="dropdown-divider"></div>
@endif
