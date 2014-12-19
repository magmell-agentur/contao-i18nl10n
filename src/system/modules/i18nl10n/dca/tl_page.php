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

use Verstaerker\I18nl10n\Classes\I18nl10n;

// load language translations
$this->loadLanguageFile('languages');


/**
 * Table tl_page
 */
$GLOBALS['TL_DCA']['tl_page']['list']['operations']['page_i18nl10n'] = array
(
    'label'           => 'L10N',
    'href'            => 'do=i18nl10n',
    'button_callback' => array('tl_page_l10n', 'editL10n')
);

$onLoadCallback = array
(
    0 => array
    (
        'tl_page_l10n',
        'setDefaultLanguage'
    ),
    1 => array
    (
        'tl_page_l10n',
        'displayMultipleRootMessage'
    ),
    2 => array
    (
        'tl_page_l10n',
        'displayLanguageMessage'
    )
);

array_insert(
    $GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'],
    count($GLOBALS['TL_DCA']['tl_page']['config']['onload_callback']) - 1,
    $onLoadCallback
);

$onSubmitCallback = array
(
    0 => array
    (
        'tl_page_l10n',
        'generatePageL10n'
    ),
    1 => array
    (
        'tl_page_l10n',
        'updateDefaultLanguage'
    )
);

array_insert(
    $GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback'],
    count($GLOBALS['TL_DCA']['tl_page']['config']['onsubmit_callback']) - 1,
    $onSubmitCallback
);

$GLOBALS['TL_DCA']['tl_page']['config']['ondelete_callback'][] = array
(
    'tl_page_l10n',
    'onDelete'
);

// only show l10n_published if not a new root page
if (\Input::get('pid') === NULL || \Input::get('pid') != 0)
{

    // switch field splicing based on Contao version
    if ( isset($GLOBALS['TL_DCA']['tl_page']['subpalettes']['published']) )
    {
        // is Contao 3.4+
        $GLOBALS['TL_DCA']['tl_page']['subpalettes']['published'] = 'l10n_published,' . $GLOBALS['TL_DCA']['tl_page']['subpalettes']['published'];
    } else {
        // is before Contao 3.4
        foreach ($GLOBALS['TL_DCA']['tl_page']['palettes'] as $k => $v)
        {
            $GLOBALS['TL_DCA']['tl_page']['palettes'][$k] = str_replace('published,', 'published,l10n_published,', $v);
        }
    }

    // update field class
    $GLOBALS['TL_DCA']['tl_page']['fields']['published']['eval']['tl_class'] = 'w50';
}

$GLOBALS['TL_DCA']['tl_page']['fields']['l10n_published'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_page']['l10n_published'],
    'default'   => true,
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => array(
        'doNotCopy' => true,
        'tl_class'  => 'w50'
    ),
    'sql'       => "char(1) NOT NULL default '1'"
);


/**
 * Class tl_page_l10n
 *
 * @copyright   Verstärker, Patric Eberle 2014
 * @copyright   Krasimir Berov 2010-2013
 * @author      Patric Eberle <line-in@derverstaerker.ch>
 * @author      Krasimir Berov
 * @package     tl_page
 * @license     LGPLv3 http://www.gnu.org/licenses/lgpl-3.0.html
 */
class tl_page_l10n extends tl_page
{

    public function editL10n($row, $href, $label, $title, $icon)
    {
        //TODO: think about a new page type: regular_localized
        $title = sprintf(
            $GLOBALS['TL_LANG']['MSC']['editL10n'],
            "\"{$row['title']}\""
        );

        $buttonURL = $this->addToUrl($href . '&amp;node=' . $row['id']);

        return sprintf(
            '<a href="%1$s" title="%2$s"><img src="system/modules/i18nl10n/assets/img/i18nl10n.png"></a>',
            $buttonURL,
            specialchars($title)
        );
    }


    /**
     * Apply the root page language to new pages
     *
     * @returns void
     */
    public function setDefaultLanguage()
    {
        if (\Input::get('act') != 'create')
        {
            return;
        }

        if (\Input::get('pid') == 0)
        {
            $GLOBALS['TL_DCA']['tl_page']['fields']['language']['default'] = \Config::get('i18nl10n_default_language');
        }
        else
        {
            $objPage = \PageModel::findWithDetails(\Input::get('pid'));
            $GLOBALS['TL_DCA']['tl_page']['fields']['language']['default'] = $objPage->rootLanguage;
        }
    }


    /**
     * Display message when only basic language is available
     *
     * @return  void
     */
    public function displayLanguageMessage()
    {

        // only show if treeview
        if (\Input::get('act') != '')
        {
            return;
        }

        $i18nl10nLanguages = deserialize(\Config::get('i18nl10n_languages'));

        // if no languages or count is smaller 2 (1 = default language)
        if (!$i18nl10nLanguages || count($i18nl10nLanguages) < 2)
        {

            $this->loadLanguageFile('tl_page_i18nl10n');

            // TODO: ref= would be nice for link
            $message = sprintf(
                $GLOBALS['TL_LANG']['tl_page']['msg_no_languages'],
                '<a class="tl_message_link" href="contao/main.php?do=settings">',
                '</a>'
            );

            \Message::addError($message);

        };
    }


    /**
     * Display message in case site has multiple root pages
     *
     * @return void
     */
    public function displayMultipleRootMessage()
    {

        // only show if treeview
        if (\Input::get('act') != '')
        {
            return;
        }

        $strSql = 'SELECT * FROM tl_page WHERE type = "root"';

        $objResult = \Database::getInstance()
            ->execute($strSql);

        if ($objResult->numRows > 1)
        {

            $this->loadLanguageFile('tl_page_i18nl10n');

            \Message::addError($GLOBALS['TL_LANG']['tl_page']['msg_multiple_root']);

        };

    }

    /**
     * Automatically create a new localization upon page creation
     * (triggered by on submit callback)
     *
     * @param DataContainer $dc
     */
    public function generatePageL10n(DataContainer $dc)
    {

        if (!$dc->activeRecord || $dc->activeRecord->tstamp > 0) return;

        $new_records = $this->Session->get('new_records');

        // Not a new page - copy/paste is a great way to share code :P
        if (!$new_records
            || is_array($new_records[$dc->table])
            && !in_array($dc->id, $new_records[$dc->table])
        )
        {
            return;
        }

        $i18nl10nLanguages = deserialize(\Config::get('i18nl10n_languages'));

        if (Config::get('folderUrl')) {
            $arrAlias = explode('/', $dc->activeRecord->alias);
            $strAlias = array_pop($arrAlias);
        }
        else
        {
            $strAlias = $dc->activeRecord->alias;
        }

        $fields = array(
            'pid'            => $dc->id,
            'sorting'        => 0,
            'tstamp'         => time(),
            'title'          => $dc->activeRecord->title,
            'type'           => $dc->activeRecord->type,
            'pageTitle'      => $dc->activeRecord->pageTitle,
            'description'    => $dc->activeRecord->description,
            'cssClass'       => $dc->activeRecord->cssClass,
            'l10n_published' => $dc->activeRecord->published,
            'start'          => $dc->activeRecord->start,
            'stop'           => $dc->activeRecord->stop,
            'dateFormat'     => $dc->activeRecord->dateFormat,
            'timeFormat'     => $dc->activeRecord->timeFormat,
            'datimFormat'    => $dc->activeRecord->datimFormat
        );

        //now make copies in each language.
        foreach ($i18nl10nLanguages as $language)
        {
            if ($language == \Config::get('i18nl10n_default_language')) continue;

            $strFolderUrl = '';
            $fields['sorting'] += 128;
            $fields['language'] = $language;

            // Create alias based on folder url setting
            if (Config::get('folderUrl'))
            {
                // Get translation for parent page
                $objL10nParentPage = I18nl10n::findL10nWithDetails($dc->activeRecord->pid, $language);

                // Create folder url
                $strFolderUrl = $objL10nParentPage->alias . '/';
            }

            $fields['alias'] = $strFolderUrl . $strAlias . '-' . $dc->id;

            $sql = 'INSERT INTO tl_page_i18nl10n %s';

            \Database::getInstance()
                ->prepare($sql)
                ->set($fields)
                ->execute();
        }
    }


    /**
     * Delete localizations for deleted page
     *
     * @param DataContainer $dc
     */
    public function onDelete(DataContainer $dc)
    {
        $objDatabase = \Database::getInstance();

        $arrChildRecords = $objDatabase->getChildRecords(array($dc->id), 'tl_page');

        // add actual page itself
        $arrChildRecords[] = $dc->id;

        // Delete all related localizations from tl_page_i18nl10n
        $objDatabase
            ->prepare('DELETE FROM tl_page_i18nl10n WHERE pid IN(' . implode(',', $arrChildRecords) . ')')
            ->execute();

    }


    /**
     * Set root page language as default language and update available languages
     *
     * @param DataContainer $dc
     */
    public function updateDefaultLanguage(DataContainer $dc)
    {

        if ($dc->activeRecord->type == 'root')
        {
            $defaultLanguage = $dc->activeRecord->language;
            $availableLanguages = deserialize(\Config::get('i18nl10n_languages'));

            // set root language as default language
            $config = \Config::getInstance();
            $config->update("\$GLOBALS['TL_CONFIG']['i18nl10n_default_language']", $defaultLanguage);

            // add language to available languages
            if (!in_array($defaultLanguage, $availableLanguages))
            {
                $availableLanguages[] = $defaultLanguage;

                $config->update("\$GLOBALS['TL_CONFIG']['i18nl10n_languages']", serialize($availableLanguages));
            }
        }
    }

}