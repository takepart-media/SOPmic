@extends('statamic::layout')

@section('title', __('sop::messages.crud.index.title'))

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-8">

        <div class="mb-6 flex items-center justify-between gap-2">
            <h1 class="text-2xl font-bold">{{ __('sop::messages.crud.index.title') }}</h1>
            <a
                href="{{ cp_route('sop.create') }}"
                class="inline-flex cursor-pointer items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-200 dark:text-gray-900"
            >
                {{ __('sop::messages.crud.index.create') }}
            </a>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-md bg-green-100 px-4 py-2 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($sops->isEmpty())
            <p class="text-sm text-gray-500">{{ __('sop::messages.crud.index.empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-300 bg-gray-50 text-left dark:border-gray-700">
                            <th class="px-3 py-2 font-medium text-gray-500">{{ __('sop::messages.crud.index.column_title') }}</th>
                            <th class="px-3 py-2 font-medium text-gray-500">{{ __('sop::messages.crud.index.column_status') }}</th>
                            <th class="px-3 py-2 font-medium text-gray-500">{{ __('sop::messages.crud.index.column_version') }}</th>
                            <th class="px-3 py-2 font-medium text-gray-500">{{ __('sop::messages.crud.index.column_consents') }}</th>
                            <th class="px-3 py-2 font-medium text-gray-500">{{ __('sop::messages.crud.index.column_updated') }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sops as $sop)
                            <tr class="border-b border-gray-300 last:border-b-0 dark:border-gray-700">
                                <td class="px-3 py-2">
                                    <a href="{{ cp_route('sop.show', $sop) }}" class="font-medium underline">
                                        {{ $sop->title }}
                                    </a>
                                </td>
                                <td class="px-3 py-2">
                                    @if ($sop->active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                            {{ __('sop::messages.crud.index.status_active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                                            {{ __('sop::messages.crud.index.status_inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $sop->currentVersion?->version_no ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $sop->consent_count }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $sop->updated_at?->diffForHumans() }}</td>
                                <td class="px-3 py-2 text-right space-x-2">
                                    <a href="{{ cp_route('sop.edit', $sop) }}" class="text-sm underline">
                                        {{ __('sop::messages.crud.edit') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
@endsection
