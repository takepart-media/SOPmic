{{--
    Shared by create.blade.php and edit.blade.php. `$sop` is a bound Eloquent
    model on the edit screen and unset on create — every field falls back to
    old() input first (validation redisplay) and then to the model, so a
    failed submission never loses what was typed.
--}}
@php
    $sop = $sop ?? null;
@endphp

<div class="mb-4">
    <label for="title" class="mb-1 block text-sm font-medium">
        {{ __('sop::messages.crud.form.title') }}
    </label>
    <input
        type="text"
        name="title"
        id="title"
        value="{{ old('title', $sop->title ?? '') }}"
        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
    >
    @error('title')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div class="mb-4">
    <label for="content" class="mb-1 block text-sm font-medium">
        {{ __('sop::messages.crud.form.content') }}
    </label>
    <p class="mb-1 text-xs text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.form.content_hint') }}</p>
    <textarea
        name="content"
        id="content"
        rows="15"
        class="w-full resize-y rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
    >{{ old('content', $sop->currentVersion->content ?? '') }}</textarea>
    @error('content')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <details class="mt-2">
        <summary class="cursor-pointer text-xs text-gray-500 underline dark:text-gray-400">
            {{ __('sop::messages.crud.form.cheatsheet_title') }}
        </summary>
        <div class="mt-2 overflow-x-auto rounded-lg border border-gray-300 dark:border-gray-700">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-300 bg-gray-50 text-left dark:border-gray-700 dark:bg-gray-900">
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.form.cheatsheet_syntax') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.form.cheatsheet_result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        '# Text' => __('sop::messages.crud.form.cheatsheet_heading'),
                        '## Text' => __('sop::messages.crud.form.cheatsheet_subheading'),
                        '**Text**' => __('sop::messages.crud.form.cheatsheet_bold'),
                        '*Text*' => __('sop::messages.crud.form.cheatsheet_italic'),
                        '~~Text~~' => __('sop::messages.crud.form.cheatsheet_strikethrough'),
                        '- Text' => __('sop::messages.crud.form.cheatsheet_list'),
                        '1. Text' => __('sop::messages.crud.form.cheatsheet_ordered_list'),
                        '- [ ] Text' => __('sop::messages.crud.form.cheatsheet_task'),
                        '[Text](https://example.com)' => __('sop::messages.crud.form.cheatsheet_link'),
                        '> Text' => __('sop::messages.crud.form.cheatsheet_quote'),
                        '`Text`' => __('sop::messages.crud.form.cheatsheet_code'),
                        '| A | B |' => __('sop::messages.crud.form.cheatsheet_table'),
                        '---' => __('sop::messages.crud.form.cheatsheet_rule'),
                    ] as $syntax => $result)
                        <tr class="border-b border-gray-300 last:border-b-0 dark:border-gray-700">
                            <td class="whitespace-nowrap px-3 py-2 font-mono">{{ $syntax }}</td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $result }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>

<div class="mb-4">
    <label class="flex cursor-pointer items-center gap-2 text-sm">
        <input type="checkbox" name="active" value="1" @checked(old('active', $sop->active ?? false))>
        <span>{{ __('sop::messages.crud.form.active') }}</span>
    </label>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('sop::messages.crud.form.active_hint') }}</p>
</div>

<div class="mb-6">
    <label for="sort_order" class="mb-1 block text-sm font-medium">
        {{ __('sop::messages.crud.form.sort_order') }}
    </label>
    <input
        type="number"
        name="sort_order"
        id="sort_order"
        min="0"
        value="{{ old('sort_order', $sop->sort_order ?? 0) }}"
        class="w-32 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
    >
    @error('sort_order')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>

<div class="flex items-center gap-4">
    <button
        type="submit"
        class="inline-flex cursor-pointer items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white dark:bg-gray-200 dark:text-gray-900"
    >
        {{ __('sop::messages.crud.form.save') }}
    </button>
    <a href="{{ cp_route('sop.index') }}" class="text-sm text-gray-500 underline dark:text-gray-400">
        {{ __('sop::messages.crud.form.cancel') }}
    </a>
</div>
