{{--
    Punto rojo sobre la foto de perfil mientras el usuario siga usando la
    contraseña que le dio el sistema. Se apaga solo cuando la cambia.

    Va dentro del enlace de la foto, que tiene que ser 'position-relative'.
--}}
@if (session('password_por_defecto'))
    <span class="position-absolute rounded-circle"
        title="Tienes un aviso: cambia tu contraseña"
        style="top: -2px; right: -2px; width: 14px; height: 14px; background-color: #dc3545;
               border: 2px solid #ffffff; box-shadow: 0 0 0 2px rgba(220, 53, 69, .35);"></span>
@endif
