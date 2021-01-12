<?php

namespace Verstaerker\I18nl10nBundle\Hook;

use Contao\Database;
use Contao\Input;
use Contao\PageModel;
use Contao\System;
use Verstaerker\I18nl10nBundle\Classes\I18nl10n;

/**
 * Class GenerateFrontendUrlHook
 * @package Verstaerker\I18nl10nBundle\Hook
 *
 * Create links to translated pages.
 */
class GenerateFrontendUrlHook
{
    /** @var Database $database */
    protected $database;

    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    /**
     * @param $arrRow
     * @param $strParams
     * @param $strUrl
     * @return mixed|string
     * @throws \Exception
     *
     * @todo:   Don't use TL_LANGUAGE in backend
     * @todo:   Get language by domain
     * @todo:   I18nl10n::getInstance()->getLanguagesByDomain() not valid in BE, since the domain is taken from url
     */
    public function generateFrontendUrl($arrRow, $strParams, $strUrl)
    {
        if (! is_array($arrRow)) {
            throw new \Exception('not an associative array.');
        }

        // Return immediately if indexing pages with solr command and ignore flag is present
        if (!empty($_SERVER['argv'])
            && in_array('solr:index', $_SERVER['argv'])
            && in_array('--ignore-contao-i18nl10n', $_SERVER['argv']))
        {
            return $strUrl;
        }

        // Fix broken contao backend crawler
//        if ('maintenance' === Input::get('do') && 'crawl' === Input::get('act') && !Input::get('jobId') && $arrRow['pid'] == 0)
//        {
//            return ($arrRow['useSSL'] ? 'https' : 'http') . '://' . $arrRow['dns'];
//        }
//
//        if (Input::get('jobId'))
//        {
//            return $strUrl;
//        }

        // Contao preview for localized pages
        if (strpos($strUrl, 'preview.php') > -1)
        {
            if (strpos(System::getReferer(), 'do=i18nl10n') > -1)
            {
                $arrAlias = explode('/preview.php/', $strUrl);
                $alias = str_replace('.html', '', $arrAlias[1]);
                $objPageModel = PageModel::findByAlias($alias);
                if ($objPageModel)
                {
                    $objPageL18nI10n = Database::getInstance()->prepare("SELECT `language`, `alias` FROM `tl_page_i18nl10n` WHERE `pid`=?;")->execute($objPageModel->id);
                    if ($objPageL18nI10n && $objPageL18nI10n->count())
                    {
                        $strUrl = $arrAlias[0] . '/preview.php/' . $objPageL18nI10n->language . '/' . $objPageL18nI10n->alias . '.html';
                        return $strUrl;
                    }
                }
            }

            return $strUrl;
        }

        global $objPage;

        $arrLanguages = I18nl10n::getInstance()->getLanguagesByDomain();
        $arrL10nAlias = null;
        $arrChangelanguageAlias = null;

        $language = empty(
            $arrRow['language'])
            || empty($arrRow['forceRowLanguage']
        ) ?
            $GLOBALS['TL_LANGUAGE']
            : $arrRow['language'];
        
		// override language if set in the params:
		// language logic for redirect is wrong, needs to be included here - 18.10.2018 MM
		if(strpos($strParams, '/language/de') !== false){
			$language = 'de';
		} elseif(strpos($strParams, '/language/en') !== false) {
			$language = 'en';
		}
		
		// Magmell 01.05.2018 - overwrite, I think the two lines above are wrong / no longer working
        // 29.05.2018. with this reverted links in the navigation are working properly
        // $language = $arrRow['language']; 

        // Try to get l10n alias iby language and pd
        if ($language !== $arrLanguages['default']) {
            $arrL10nAlias = $this->database
                ->prepare('SELECT alias FROM tl_page_i18nl10n WHERE pid = ? AND language = ?')
                ->execute($arrRow['id'], $language)
                ->fetchAssoc();

            // get me the "opposite" page info => for changelanguage plugin
            $arrChangelanguageAlias = $this->database
                ->prepare('SELECT alias FROM tl_page WHERE id = ? AND language = ?')
                ->execute($arrRow['id'], $arrLanguages['default'])
                ->fetchAssoc();
        } else {
            // get me the "opposite" page info => for changelanguage plugin
            $arrChangelanguageAlias = $this->database
                ->prepare('SELECT alias FROM tl_page_i18nl10n WHERE pid = ? AND language = ?')
                ->execute($arrRow['id'], $objPage->language)
                ->fetchAssoc();
        }

        $alias = is_array($arrL10nAlias)
            ? $arrL10nAlias['alias']
            : $arrRow['alias'];

        // Remove auto_item and language
        $regex = '@/auto_item|/language/[a-z]{2}|[\?&]language=[a-z]{2}@';
        $strParams = preg_replace($regex, '', $strParams);
        $strUrl    = preg_replace($regex, '', $strUrl);


        if (!isset($_GET['items']) && \Config::get('useAutoItem') && isset($_GET['auto_item']))
        {
            \Input::setGet('items', \Input::get('auto_item'));
        }

        // separate logic for pagelanguage - see if we have to add an alias for an item
        if($language !== $objPage->language) {
            if($arrChangelanguageAlias['alias'] === $objPage->alias && $item = \Input::get("items")) {
               $alias .= "/" . $item;
            }
        }

        // If alias is disabled add language to get param end return
        if (\Config::get('disableAlias')) {
            $missingValueRegex = '@(.*\?[^&]*&)([^&]*)=(?=$|&)(&.*)?@';

            if (\Config::get('useAutoItem') && preg_match($missingValueRegex, $strUrl) == 1) {
                $strUrl = preg_replace($missingValueRegex, '${1}auto_item=${2}${3}', $strUrl);
            }

            return $strUrl . '&language=' . $language;
        }

        if (\Config::get('i18nl10n_urlParam') === 'alias' && !\Config::get('disableAlias')) {
            $strL10nUrl = $alias . $strParams . '.' . $language . \Config::get('urlSuffix');

            // if rewrite is off, add environment
            if (!\Config::get('rewriteURL')) {
                $strL10nUrl = 'index.php/' . $strL10nUrl;
            }
        } elseif (\Config::get('i18nl10n_urlParam') === 'url') {
            $strL10nUrl = '';
            
            // only add folder if not default language!
            if($language != $arrLanguages["default"]){
                $strL10nUrl = $language . '/';
            }

            $strL10nUrl .= $alias . $strParams . \Config::get('urlSuffix');

            /*
            // if rewrite is off, add environment
            if (!\Config::get('rewriteURL')) {
                $strL10nUrl = 'index.php/' . $strL10nUrl;
            }
            */

            // if alias is missing (f.ex. index.html), add it (exclude news!)
            // search for
            // www.domain.com/
            // www.domain.com/foo/
            if (!\Config::get('disableAlias')
                && preg_match(
                    '@' . $arrRow['alias'] . '(?=\\' . \Config::get('urlSuffix') . '|/)@',
                    $strL10nUrl
                ) === false
            ) {
                $strL10nUrl .= $alias . \Config::get('urlSuffix');
            }
        } else {
            $strL10nUrl = str_replace($arrRow['alias'], $alias, $strUrl);

            // Check if params exist
            if (strpos($strL10nUrl, '?') !== false) {
                if (strpos($strL10nUrl, 'language=') !== false) {
                    // if variable 'language' replace it
                    $regex      = '@language=[a-z]{2}@';
                    $strL10nUrl = preg_replace(
                        $regex,
                        'language=' . $language,
                        $strL10nUrl
                    );
                } else {
                    // If no variable 'language' add it
                    $strL10nUrl .= '&language=' . $language;
                }
            } else {
                // If no variables define variable 'language'
                $strL10nUrl .= '?language=' . $language;
            }
        }

		// problem: absolute URLs - coming from a redirect contain a domain - it should at least really be an absolute link MM 18.10.2018
		if (strlen($strL10nUrl) > 0 && substr($strL10nUrl, 0, 1) != '/' && strpos(System::getReferer(), "contao?") !== 0) {
			$strL10nUrl = '/'. $strL10nUrl;
		}
		
        return $strL10nUrl;
    }
}
