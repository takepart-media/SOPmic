/*
 * SOP addon — control panel script. Plain JS, no build step.
 *
 * Registered via AddonServiceProvider::$scripts, which publishes this file to
 * public/vendor/statamic-sop/js/cp.js and renders it as a
 * deferred <script> after the CP bundle but before Statamic.start(). At that
 * point window.Statamic exists and Statamic.component() queues the component
 * until the Vue app boots.
 *
 * <sop-multi-select> wraps Statamic's native <ui-combobox> (searchable
 * multiselect). The combobox is fully controlled and renders no form inputs,
 * so this component mirrors the selection into hidden inputs for a classic
 * form POST:
 *   - one `name[]` input per selected value, or
 *   - a single empty `name` input when nothing is selected, so the server can
 *     tell "cleared" apart from "field not submitted at all".
 */
(function () {
    'use strict';

    var definition = {
        props: {
            name: { type: String, required: true },
            options: { type: Array, default: function () { return []; } },
            initial: { type: Array, default: function () { return []; } },
            placeholder: { type: String, default: null },
            inputId: { type: String, default: null },
        },

        data: function () {
            // Strings throughout: the combobox matches values with ===.
            return { selected: this.initial.map(String) };
        },

        computed: {
            // The combobox's clear button emits null, not [].
            values: function () {
                return Array.isArray(this.selected) ? this.selected.map(String) : [];
            },

            // Only pass what is set, so the combobox's own defaults (e.g. its
            // translated "Select..." placeholder) still apply otherwise.
            extraProps: function () {
                var props = {};
                if (this.placeholder) props.placeholder = this.placeholder;
                if (this.inputId) props.id = this.inputId;
                return props;
            },
        },

        template:
            '<div>' +
                '<ui-combobox v-model="selected" v-bind="extraProps" :options="options" multiple searchable clearable></ui-combobox>' +
                '<input v-for="value in values" :key="value" type="hidden" :name="name + \'[]\'" :value="value">' +
                '<input v-if="values.length === 0" type="hidden" :name="name" value="">' +
            '</div>',
    };

    var Statamic = window.Statamic;

    if (!Statamic || typeof Statamic.component !== 'function') {
        // eslint-disable-next-line no-console
        console.warn('[sop] window.Statamic is not available; <sop-multi-select> was not registered.');
        return;
    }

    // Statamic.component() queues before the app boots and registers directly
    // afterwards, so it is safe at the top level of this deferred script —
    // no Statamic.booting() wrapper needed (which would never fire if start()
    // had already run).
    Statamic.component('sop-multi-select', definition);
})();
