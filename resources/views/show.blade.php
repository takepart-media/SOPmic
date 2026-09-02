@extends('statamic::layout')

@section('title', $sop->title)

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-8">

        <div class="mb-2">
            <a href="{{ cp_route('sop.index') }}" class="text-sm text-gray-500 underline dark:text-gray-400">
                {{ __('sop::messages.crud.show.back') }}
            </a>
        </div>

        <div class="mb-6 flex items-center justify-between gap-2">
            <h1 class="text-2xl font-bold">{{ $sop->title }}</h1>
            <div class="flex items-center gap-4">
                <a href="{{ cp_route('sop.edit', $sop) }}" class="text-sm underline">
                    {{ __('sop::messages.crud.edit') }}
                </a>
                <form
                    method="POST"
                    action="{{ cp_route('sop.destroy', $sop) }}"
                    onsubmit="return confirm('{{ __('sop::messages.crud.show.delete_confirm') }}')"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cursor-pointer text-sm font-medium text-red-600 dark:text-red-400">
                        {{ __('sop::messages.crud.delete') }}
                    </button>
                </form>
            </div>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
                {{ session('success') }}
            </div>
        @endif

        {{-- Current version --}}
        <h2 class="mb-2 text-lg font-medium">{{ __('sop::messages.crud.show.current_version') }}</h2>

        @if ($sop->currentVersion)
            <div class="mb-8 rounded-lg border border-gray-300 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="prose prose-sm dark:text-gray-300">
                    {!! $content !!}
                </div>
            </div>
        @else
            <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.no_version') }}</p>
        @endif

        {{-- Version history --}}
        <h2 class="mb-2 text-lg font-medium">{{ __('sop::messages.crud.show.history') }}</h2>

        <div class="mb-8 overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-left dark:border-gray-700 dark:bg-gray-900">
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.history_version') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.history_created') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.history_author') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.history_hash') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($versions as $version)
                        <tr class="border-b border-gray-300 last:border-b-0 dark:border-gray-700">
                            <td class="px-3 py-2">
                                {{ $version->version_no }}
                                @if ($sop->current_version_id === $version->id)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">
                                        {{ __('sop::messages.crud.index.status_active') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $version->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2">{{ $emails[$version->created_by] ?? $version->created_by ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ substr($version->content_hash, 0, 8) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Consent audit, one table per version so the version each consent
             belongs to is unambiguous. --}}
        <h2 class="mb-2 text-lg font-medium">{{ __('sop::messages.crud.show.audit') }}</h2>

        @foreach ($versions as $version)
            @php $consents = $consentsByVersion->get($version->id, collect()); @endphp

            <div class="mb-6">
                <h3 class="mb-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('sop::messages.crud.show.history_version') }} {{ $version->version_no }}
                </h3>

                @if ($consents->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.audit_empty') }}</p>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-300 bg-gray-50 text-left dark:border-gray-700 dark:bg-gray-900">
                                    <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.audit_user') }}</th>
                                    <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.show.audit_consented_at') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($consents as $consent)
                                    <tr class="border-b border-gray-300 last:border-b-0 dark:border-gray-700">
                                        <td class="px-3 py-2">{{ $emails[$consent->user_id] ?? $consent->user_id }}</td>
                                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $consent->consented_at?->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach

    </div>
@endsection
