@extends('layouts.partials.html-head')
{{-- @routes --}}

@section('styles')
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

        @include('layouts.partials.navbar-admin')
        @include('layouts.partials.messages')

        <main class="container">
            @yield('content')
        </main>

        @include('layouts.partials.footer')
    </body>


    @yield('scripts')
@endsection