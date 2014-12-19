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


namespace Verstaerker\I18nl10n\Pages;
use Verstaerker\I18nl10n\Classes\I18nl10n;


/**
 * Class I18nPageRegular
 *
 * @copyright   Verstärker, Patric Eberle 2014
 * @copyright   Krasimir Berov 2010-2013
 * @author      Patric Eberle <line-in@derverstaerker.ch>
 * @author      Krasimir Berov
 * @package     i18nl10n
 */
class PageI18nl10nRegular extends \PageRegular
{
    /**
     * Override TL_PTY.regular
     *
     * @param $objPage
     * @param bool $blnCheckRequest
     */
    function generate($objPage, $blnCheckRequest = false)
    {
        self::fixupCurrentLanguage();

        if ($GLOBALS['TL_LANGUAGE'] == \Config::get('i18nl10n_default_language')) {
            // if default language is not published, give error
            if (!$objPage->l10n_published)
            {
                $objError = new $GLOBALS['TL_PTY']['error_404']();
                $objError->generate($objPage->id);
            }
            parent::generate($objPage);

            return;
        }

        $objPage = I18nl10n::findPublishedL10nPage($objPage, $GLOBALS['TL_LANGUAGE']);

        // if translated page, replace given fields in page object
        if (!$objPage)
        {
            // if fallback is not published, show 404
            if (!$objPage->l10n_published)
            {
                $objError = new $GLOBALS['TL_PTY']['error_404']();
                $objError->generate($objPage->id);

                parent::generate($objPage);
                return;
            }
            else
            {
                // else at least keep current language to prevent language change and set flag
                $objPage->language = $GLOBALS['TL_LANGUAGE'];
                $objPage->useFallbackLanguage = true;
            }

        }

        // update root information
        $objL10nRootPage = self::getL10nRootPage($objPage);

        if ($objL10nRootPage)
        {
            $objPage->rootTitle = $objL10nRootPage->title;

            if ($objPage->pid == $objPage->rootId)
            {
                $objPage->parentTitle = $objL10nRootPage->title;
                $objPage->parentPageTitle = $objL10nRootPage->pageTitle;
            }
        }

        parent::generate($objPage, $blnCheckRequest);
    }

    /**
     * Fix up current language depending on momentary user preference.
     * Strangely $GLOBALS['TL_LANGUAGE'] is switched to the current user language if user is just
     * authenticating and has the language property set.
     * See system/libraries/User.php:202
     * We override this behavior and let the user temporarily use the selected by him language.
     * One workaround would be to not let the members have a language property.
     * Then this method will not be needed any more.
     */
    private function fixupCurrentLanguage()
    {
        // try to get language from post (committed by language select) or get
        $selectedLanguage = \Input::post('language') ? : \Input::get('language');

        // if language is added to url, get it from there
        if (\Config::get('i18nl10n_addLanguageToUrl') && !\Config::get('disableAlias')) {

            // if language is set, it must be given by post (committed by language select)
            if ($selectedLanguage) {
                $_SESSION['TL_LANGUAGE'] = $GLOBALS['TL_LANGUAGE'] = $selectedLanguage;
                return;
            } else {
                $this->import('Environment');
                $environment = $this->Environment;
                $basePath = preg_quote(\Config::get('rewriteURL')
                    ? \Config::get('websitePath')
                    : $environment->scriptName);

                $regex = "@^($basePath/)?([A-z]{2}(?=/)){1}(/.*)@";

                // only set language if found in url
                if (preg_match($regex, $environment->requestUri)) {
                    $language = preg_replace($regex, '$2', $environment->requestUri);

                    $_SESSION['TL_LANGUAGE'] = $language;
                    $GLOBALS['TL_LANGUAGE'] = $language;
                    return;
                }
            }
        }

        $i18nl10nLanguages = deserialize(\Config::get('i18nl10n_languages'));

        // if alias is disabled, get language from get param
        if ($selectedLanguage && \Config::get('disableAlias')) {
            $_SESSION['TL_LANGUAGE'] = $GLOBALS['TL_LANGUAGE'] = $selectedLanguage;
            return;
        }

        // if language is part of alias
        if (\Config::get('i18nl10n_alias_suffix')) {
            $this->import('Environment');
            $environment = $this->Environment;
            $strUrlSuffix = preg_quote( \Config::get('urlSuffix') );

            $regex = "@.*?\.([a-z]{2})$strUrlSuffix@";

            // only set language if found in url
            if (preg_match($regex, $environment->requestUri)) {
                $_SESSION['TL_LANGUAGE'] = $GLOBALS['TL_LANGUAGE'] = preg_replace($regex, '$1', $environment->requestUri);
                return;
            }
        }

        // if everything failed til now, try to use post or get language
        if ($selectedLanguage && in_array($selectedLanguage, $i18nl10nLanguages)) {
            $_SESSION['TL_LANGUAGE'] = $GLOBALS['TL_LANGUAGE'] = $selectedLanguage;
        } elseif (isset($_SESSION['TL_LANGUAGE'])) {
            // else use session language
            $GLOBALS['TL_LANGUAGE'] = $_SESSION['TL_LANGUAGE'];
        }
    }

    /**
     * Get localized root page by page object
     *
     * @param $objPage
     * @return \Database\Result|null
     */
    public function getL10nRootPage($objPage)
    {
        $sql = "
            SELECT title
            FROM tl_page_i18nl10n
            WHERE
              pid = ?
              AND language = ?
        ";

        if (!BE_USER_LOGGED_IN) {
            $time = time();
            $sql .= "
                AND (start = '' OR start < $time)
                AND (stop = '' OR stop > $time)
                AND l10n_published = 1
            ";
        }

        $objL10nRootPage = \Database::getInstance()
            ->prepare($sql)
            ->limit(1)
            ->execute($objPage->rootId, $GLOBALS['TL_LANGUAGE']);

        return $objL10nRootPage->row() ? $objL10nRootPage : null;
    }
}
