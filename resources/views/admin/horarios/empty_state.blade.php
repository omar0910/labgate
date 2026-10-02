@extends('layouts.admin')

@section('title', 'Bienvenido a Horarios')

@section('content')
    <div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 60vh;">
        <div class="text-center p-5 border rounded shadow-sm bg-white" style="max-width: 600px;">
            <div class="mb-4 text-marca-green opacity-50">
                <i class="bi bi-calendar-range" style="font-size: 5rem;"></i>
            </div>

            <h2 class="h3 mb-3 fw-bold text-dark">Configuración Inicial Requerida</h2>

            <p class="text-muted mb-4 fs-5">
                Para comenzar a gestionar los horarios y asistencias, el sistema necesita conocer el
                <strong>Semestre Actual</strong> (ej. 2026-1).
            </p>

            <div class="d-grid gap-2 col-md-8 mx-auto">
                {{-- Asegúrate que la ruta 'semestres.create' exista, si no, usa 'semestres.index' --}}
                <a href="{{ route('semestres.create') }}" class="btn btn-marca-green btn-lg shadow-sm">
                    <i class="bi bi-plus-circle-fill me-2"></i> Crear Semestre Actual
                </a>

                <a href="{{ route('admin.dashboard') }}" class="btn btn-link text-decoration-none text-muted">
                    Volver al Inicio
                </a>
            </div>
        </div>
    </div>
@endsection
