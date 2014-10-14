<?php

/**
 * i18nl10n Contao Module
 *
 * The i18nl10n module for Contao allows you to manage multilingual content
 * on the element level rather than with page trees.
 *
 *
 * PHP version 5
 * @copyright   Verstärker, Patric Eberle 2014
 * @copyright   Krasimir Berov 2010-2013
 * @author      Patric Eberle <line-in@derverstaerker.ch>
 * @author      Krasimir Berov
 * @package     i18nl10n
 * @license     LGPLv3 http://www.gnu.org/licenses/lgpl-3.0.html
 */


/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_page']['l10n_published'] = array
(
    'L10N veröffentlichen',
    'Diese Übersetzung veröffentlichen.'
);


/**
 * Messages
 */
$GLOBALS['TL_LANG']['tl_page']['msg_no_languages'] =
    'Es wurden noch keine alternativen Sprachen festgelegt. Bitte hole dies noch in den '
    . '%s Einstellungen %s nach.';

$GLOBALS['TL_LANG']['tl_page']['msg_multiple_root'] =
    'i18nl10n hat in deiner Seitenstruktur mehr als eine Root-Seite gefunden. '
    . 'Bitte beachte, dass das Modul nicht für die Verwendung mehrerer Root-Seiten ausgelegt ist!';

$GLOBALS['TL_LANG']['tl_page']['msg_localize_all'] =
    'Für alle Seiten in <span style="white-space:nowrap">[%s]</span> ohne Übersetzung '
    . 'werden Lokalisierungen angelegt. Bist du sicher, dass du für die folgenden Sprachen Seiten anlegen möchtest?';