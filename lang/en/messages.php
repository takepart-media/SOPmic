<?php

return [

    'gate' => [
        'required' => 'SOP consent required.',
    ],

    'consent' => [
        'title' => 'Confirm SOP',
        'intro' => 'Before you can use the control panel, you have to read and confirm the following SOPs.',
        'progress' => 'SOP :position of :total',
        'confirm' => 'I have read this SOP and agree to it',
        'submit' => 'Confirm and continue',
        'recorded' => 'Consent recorded.',
        'stale' => 'This SOP changed in the meantime. Please read the current version and confirm it again.',
    ],

    'unavailable' => [
        'title' => 'SOP system unavailable',
        'body' => 'The SOP database cannot be reached right now, so your control panel access cannot be verified. Please try again later or contact an administrator.',
        'logout' => 'Log out',
    ],

    'nav' => [
        'sops' => 'SOPs',
    ],

    'permissions' => [
        'group' => 'SOPs',
        'manage' => 'Manage SOPs',
    ],

    'crud' => [
        'created' => 'SOP created.',
        'updated' => 'SOP saved.',
        'deleted' => 'SOP deleted.',
        'edit' => 'Edit',
        'delete' => 'Delete',

        'index' => [
            'title' => 'SOPs',
            'create' => 'Create SOP',
            'empty' => 'There are no SOPs yet.',
            'column_title' => 'Title',
            'column_status' => 'Status',
            'column_version' => 'Version',
            'column_consents' => 'Consents',
            'column_updated' => 'Last updated',
            'status_active' => 'Active',
            'status_inactive' => 'Inactive',
        ],

        'form' => [
            'title' => 'Title',
            'content' => 'Content',
            'content_hint' => 'Markdown is supported. Any content change creates a new version and invalidates existing consents.',
            'active' => 'Active',
            'active_hint' => 'Only active SOPs with a published version gate the control panel.',
            'sort_order' => 'Sort order',
            'save' => 'Save',
            'cancel' => 'Cancel',
        ],

        'create' => [
            'title' => 'Create SOP',
        ],

        'edit_page' => [
            'title' => 'Edit SOP',
        ],

        'show' => [
            'back' => 'Back to the list',
            'delete_confirm' => 'Really delete this SOP? Versions and consents remain in the audit trail.',
            'current_version' => 'Current version',
            'no_version' => 'No version published yet.',
            'history' => 'Version history',
            'history_version' => 'Version',
            'history_created' => 'Created',
            'history_author' => 'Author',
            'history_hash' => 'Hash',
            'audit' => 'Consents',
            'audit_user' => 'User',
            'audit_ip' => 'IP address',
            'audit_consented_at' => 'Consented at',
            'audit_empty' => 'No consents recorded for this version yet.',
        ],
    ],

];
