@extends('layouts.encargado')

@section('title', 'Lista de Asistencia')

@php
    // Se abre desde Gestión de Clases o desde la Semana: "Volver" regresa ahí y el
    // menú marca esa sección.
    $regreso = \App\Support\Origen::de(request()) ?? [
        'url'   => route('encargado.dashboard', ['centro_id' => request('centro_id', 'todos'), 'fecha' => $fecha]),
        'texto' => 'Volver a Gestión de Clases',
        'menu'  => 'clases',
    ];
@endphp

@section('menu', $regreso['menu'])

@section('content')
    {{-- La misma lista que "Clases de hoy" del administrador --}}
    @include('partials.lista-del-dia', ['regreso' => $regreso])
@endsection
