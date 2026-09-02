<?php

return [

    'gate' => [
        'required' => 'SOP-Bestätigung erforderlich.',
    ],

    'consent' => [
        'title' => 'SOP bestätigen',
        'intro' => 'Bevor du das Control Panel nutzen kannst, musst du die folgenden SOPs lesen und bestätigen.',
        'progress' => 'SOP :position von :total',
        'confirm' => 'Ich habe dieses SOP gelesen und stimme zu',
        'submit' => 'Bestätigen und fortfahren',
        'recorded' => 'Bestätigung gespeichert.',
        'stale' => 'Das SOP wurde zwischenzeitlich geändert. Bitte lies die aktuelle Fassung und bestätige sie erneut.',
    ],

    'unavailable' => [
        'title' => 'SOP-System nicht verfügbar',
        'body' => 'Die SOP-Datenbank ist derzeit nicht erreichbar, deshalb kann deine Freigabe für das Control Panel nicht geprüft werden. Bitte versuche es später erneut oder wende dich an die Administration.',
        'logout' => 'Abmelden',
    ],

    'nav' => [
        'sops' => 'SOPs',
    ],

    'permissions' => [
        'group' => 'SOPs',
        'manage' => 'SOPs verwalten',
    ],

    'crud' => [
        'created' => 'SOP erstellt.',
        'updated' => 'SOP gespeichert.',
        'deleted' => 'SOP gelöscht.',
        'edit' => 'Bearbeiten',
        'delete' => 'Löschen',

        'index' => [
            'title' => 'SOPs',
            'create' => 'SOP erstellen',
            'empty' => 'Es sind noch keine SOPs vorhanden.',
            'column_title' => 'Titel',
            'column_status' => 'Status',
            'column_version' => 'Version',
            'column_consents' => 'Zustimmungen',
            'column_updated' => 'Zuletzt geändert',
            'status_active' => 'Aktiv',
            'status_inactive' => 'Inaktiv',
        ],

        'form' => [
            'title' => 'Titel',
            'content' => 'Inhalt',
            'content_hint' => 'Markdown wird unterstützt. Jede inhaltliche Änderung erzeugt eine neue Version und macht bestehende Zustimmungen ungültig.',
            'active' => 'Aktiv',
            'active_hint' => 'Nur aktive SOPs mit einer veröffentlichten Version gaten das Control Panel.',
            'sort_order' => 'Reihenfolge',
            'save' => 'Speichern',
            'cancel' => 'Abbrechen',
        ],

        'create' => [
            'title' => 'SOP erstellen',
        ],

        'edit_page' => [
            'title' => 'SOP bearbeiten',
        ],

        'show' => [
            'back' => 'Zurück zur Übersicht',
            'delete_confirm' => 'Dieses SOP wirklich löschen? Versionen und Zustimmungen bleiben im Audit-Trail erhalten.',
            'current_version' => 'Aktuelle Version',
            'no_version' => 'Noch keine Version veröffentlicht.',
            'history' => 'Versionshistorie',
            'history_version' => 'Version',
            'history_created' => 'Erstellt',
            'history_author' => 'Autor',
            'history_hash' => 'Hash',
            'audit' => 'Zustimmungen',
            'audit_user' => 'Benutzer',
            'audit_ip' => 'IP-Adresse',
            'audit_consented_at' => 'Bestätigt am',
            'audit_empty' => 'Für diese Version liegen noch keine Zustimmungen vor.',
        ],
    ],

];
