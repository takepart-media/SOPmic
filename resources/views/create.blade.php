@extends('statamic::layout')

@section('title', __('sop::messages.crud.create.title'))

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="mb-6 text-2xl font-bold">{{ __('sop::messages.crud.create.title') }}</h1>

        <form method="POST" action="{{ cp_route('sop.store') }}">
            @csrf
            @include('sop::_form')
        </form>
    </div>
@endsection
