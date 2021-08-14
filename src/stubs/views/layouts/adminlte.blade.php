@extends('adminlte::page')

@section('adminlte_css')
    @stack('css')
    @yield('css')
@stop

@section('adminlte_js')
    <script>
        @if (session()->has('success'))
        toastr.success("{{ session('success') }}");
        @endif
        @if (session()->has('warning'))
        toastr.warn("{{ session('warning') }}");
        @endif
        @if (session()->has('info'))
        toastr.info("{{ session('info') }}");
        @endif
        @if (session()->has('error'))
        toastr.error("{{ session('error') }}");
        @endif

    </script>
    @stack('js')
    @yield('js')
@stop
