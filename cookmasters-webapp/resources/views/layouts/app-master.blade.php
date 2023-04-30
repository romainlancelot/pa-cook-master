@extends('layouts.partials.html-head')

@section('styles')
<link href="{!! url('assets/css/app.css') !!}" rel="stylesheet">
@endsection

@section('body')
    <style>
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }
    </style>

    <body>
        
        @include('layouts.partials.navbar')

        <main class="container">
            @yield('content')
        </main>

        <script src="{!! url('assets/bootstrap/js/bootstrap.bundle.min.js') !!}"></script>
        
    </body>
@endsection