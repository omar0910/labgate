@extends('layouts.admin')

@section('title', 'Lista de Asistencia')

@php
    // Se abre desde Clases de hoy o desde la Semana: "Volver" regresa ahí. Las dos
    // viven en el Inicio del menú.
    $regreso = \App\Support\Origen::de(request()) ?? [
        'url'   => route('admin.reportes.clases-hoy', ['centro_id' => request('centro_id', 'todos'), 'fecha' => $fecha]),
        'texto' => 'Volver a Clases de Hoy',
        'menu'  => 'inicio',
    ];
@endphp

@section('menu', $regreso['menu'])

@section('content')
    {{-- La misma lista que "Gestión de Clases" del encargado --}}
    @include('partials.lista-del-dia', ['regreso' => $regreso])
@endsection
