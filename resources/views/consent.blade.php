@extends('statamic::layout')

@section('title', __('sop::messages.consent.title'))

@section('content')
    {{--
        Plain Tailwind only. Every utility used here is verified to exist in the
        CP's compiled stylesheet — the addon ships no CSS build of its own, so a
        class the CP never compiled would simply do nothing.
    --}}
    <div class="max-w-3xl mx-auto px-4 py-8">

        <div class="mb-4 flex items-baseline justify-between gap-2">
            <h1 class="text-2xl font-bold">{{ __('sop::messages.consent.title') }}</h1>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('sop::messages.consent.progress', ['position' => $position, 'total' => $total]) }}
            </span>
        </div>

        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">{{ __('sop::messages.consent.intro') }}</p>

        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-700 dark:bg-red-300/6 dark:text-red-400">
                {{ session('error') }}
            </div>
        @endif

        @if (session('success'))
            <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-100 px-4 py-2 text-sm text-red-700 dark:bg-red-300/6 dark:text-red-400">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg border border-gray-300 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-medium">{{ $version->title }}</h2>

            <div class="max-h-96 overflow-y-auto">
                <div class="prose prose-sm dark:text-gray-300">
                    {!! $content !!}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ cp_route('sop.consent.store') }}" class="mt-6">
            @csrf
            <input type="hidden" name="sop_id" value="{{ $sop->id }}">
            <input type="hidden" name="sop_version_id" value="{{ $version->id }}">

            <label class="mb-4 flex cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" name="confirmed" value="1" required>
                <span>{{ __('sop::messages.consent.confirm') }}</span>
            </label>

            <button
                type="submit"
                class="inline-flex cursor-pointer items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-200 dark:text-gray-900"
            >
                {{ __('sop::messages.consent.submit') }}
            </button>
        </form>

    </div>
@endsection
