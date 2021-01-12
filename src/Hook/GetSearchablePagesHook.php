<?php

namespace Verstaerker\I18nl10nBundle\Hook;
use Contao\Database;
use Verstaerker\I18nl10nBundle\Classes\I18nl10n;

/**
 * Class GetSearchablePagesHook
 * @package Verstaerker\I18nl10nBundle\Hook
 *
 * Get all i18nl10n pages and add them to the search index.
 * https://docs.contao.org/books/api/extensions/hooks/getSearchablePages.html
 */
class GetSearchablePagesHook
{
    /**
     * @var Database $dbConnection
     */
    protected $dbConnection;

    /**
     * Add localized urls to search and sitemap indexing
     *
     * @param $arrPageUrls
     *
     * @return array
     * @throws \Exception
     */
    public function getSearchablePages($arrPageUrls)
    {
        $this->dbConnection = Database::getInstance();
        $time = time();

        $language = empty($arrRow['language']) || empty($arrRow['forceRowLanguage']) ? $GLOBALS['TL_LANGUAGE'] : $arrRow['language'];
        $arrLanguages = I18nl10n::getInstance()->getLanguagesByDomain();
        $defaultLanguage  = $arrLanguages['default'];

        $arrPageUrlsFinal = [];

        foreach ($arrPageUrls as $strPageUrl)
        {
            // Skip if alias is missing
            if (strpos($strPageUrl, "/.html"))
            {
                continue;
            }

            $arrPageUrlsFinal[] = $strPageUrl;

            $strPageAlias = $this->getAliasFromUrl($strPageUrl, $arrLanguages['localizations'] ?: array());

            if ($language !== $defaultLanguage)
            {
                try {
					// catch the exeption for the international page missing - not good, but not critical
					list($objI18nl10nPage, $strItem) = $this->getLocalizedPageAndItemFromAlias($strPageAlias, $language);

					if($objPage = $this->getPageFromLocalizedPage($objI18nl10nPage, $time))
					{
						$arrPageUrlsFinal[] = ($strItem ? $objPage->alias . "/" . $strItem : $objPage->alias) . ".html";
						foreach($arrLanguages['localizations'] as $strLanguage)
						{
							if($strLanguage === $objI18nl10nPage->language)
							{
								continue;
							}

							$objI18nl10nPage = $this->getLocalizedPageFromPage($objPage, $strLanguage, $time);
							if ($objI18nl10nPage) {
								$arrPageUrlsFinal[] =
									($strItem ? $objI18nl10nPage->alias . "/" . $strItem : $objI18nl10nPage->alias) . ".html";
							}
						}
					}
				}
				catch(\Exception $e){}
            } else {
                list($objPage, $strItem) = $this->getPageAndItemFromAlias($strPageAlias);
                foreach($arrLanguages['localizations'] as $strLanguage)
                {
                    $objI18nl10nPage = $this->getLocalizedPageFromPage($objPage, $strLanguage, $time);
                    if ($objI18nl10nPage && $objI18nl10nPage->alias) {
                        $arrPageUrlsFinal[] = $strLanguage . "/"
                            . ($strItem ? $objI18nl10nPage->alias . "/" . $strItem : $objI18nl10nPage->alias) . ".html";
                    }
                }
            }
        }

        return $arrPageUrlsFinal;
    }

    /**
     * @param string $strPageUrl
     * @param array $arrLocalizations
     * @return bool|string
     */
    protected function getAliasFromUrl($strPageUrl, $arrLocalizations)
    {
        // Remove .html from the
        if (strpos($strPageUrl, ".html") === strlen($strPageUrl) - 5)
        {
            $strPageAlias = substr($strPageUrl, 0, strlen($strPageUrl) - 5);
        } else {
            $strPageAlias = $strPageUrl;
        }
		
		// remove leading slash if first char
		if(strpos($strPageAlias, '/') === 0){
			$strPageAlias = substr($strPageAlias, 1);			
		}

        // Remove localization fragment from the beginning f.e en/, fr/, es/, etc.
        if ($strPageAlias[2] === "/" && in_array(substr($strPageAlias, 0, 2), $arrLocalizations))
        {
            $strPageAlias = substr($strPageAlias, 3, strlen($strPageAlias) - 3);
        }

        return $strPageAlias;
    }

    /**
     * @param string $alias
     * @param string $strLanguage
     * @return array
     * @throws \Exception
     */
    protected function getLocalizedPageAndItemFromAlias($alias, $strLanguage)
    {
        $strItem = '';

        $arrAliasFragments = explode("/", $alias);

        $objI18nl10nPage = false;

        for ($i = count($arrAliasFragments); $i >= 1; $i--)
        {
            $strCurrentAlias = implode("/", array_slice($arrAliasFragments, 0, $i));
            $objI18nl10nPage = $this->dbConnection->execute(
                "SELECT * FROM tl_page_i18nl10n WHERE alias = '$strCurrentAlias' AND language = '$strLanguage'"
            )->next();

            if ($objI18nl10nPage)
            {
                $strItem = implode("/", array_slice($arrAliasFragments, $i, count($arrAliasFragments)));
                break;
            }
        }

        if (!$objI18nl10nPage){
            throw new \Exception("cant find tl_page_i18nl10n by alias $alias");
        }

        return [$objI18nl10nPage, $strItem];
    }

    /**
     * @param string $alias
     * @return array
     * @throws \Exception
     */
    protected function getPageAndItemFromAlias($alias)
    {
        $strItem = '';

        $arrAliasFragments = explode("/", $alias);

        $objPage = false;

        for($i = count($arrAliasFragments); $i >= 1; $i--)
        {
            $strCurrentAlias = implode("/", array_slice($arrAliasFragments, 0, $i));
            $objPage = $this->dbConnection->execute(
                "SELECT * FROM tl_page WHERE alias = '$strCurrentAlias';"
            )->next();

            if ($objPage)
            {
                $strItem = implode("/", array_slice($arrAliasFragments, $i, count($arrAliasFragments)));
                break;
            }
        }

        if (!$objPage){
            throw new \Exception("can't find tl_page by alias $alias");
        }

        return [$objPage, $strItem];
    }

    /**
     * @param Database\Result $obj_I18NL10N_Page
     * @param integer $time
     * @return bool|Database\Result
     * @throws \Exception
     */
    protected function getPageFromLocalizedPage($obj_I18NL10N_Page, $time)
    {
        $objPage = $this->dbConnection
            ->execute("
              SELECT tl_page.* FROM tl_page
              LEFT JOIN tl_page_i18nl10n ON tl_page.id = tl_page_i18nl10n.pid
              WHERE tl_page_i18nl10n.alias = '{$obj_I18NL10N_Page->alias}'
                AND (tl_page.start = '' OR tl_page.start < $time)
                AND (tl_page.stop = '' OR tl_page.stop > $time)
                AND tl_page.published = 1
                AND tl_page.type != 'root'
                ");

        if ($objPage->numRows > 1)
        {
            throw new \Exception(
                "Too many pages in tl_page related to tl_page_i18nl10n with id {$obj_I18NL10N_Page->id}"
            );
        }

        return $objPage->next();
    }

    /**
     * @param Database\Result $objPage
     * @param string $strLanguage
     * @param integer $time
     * @return bool|Database\Result
     * @throws \Exception
     */
    protected function getLocalizedPageFromPage($objPage, $strLanguage, $time)
    {
        $objLocalizedPage = $this->dbConnection
            ->execute("
              SELECT tl_page_i18nl10n.* FROM tl_page_i18nl10n
              LEFT JOIN tl_page ON tl_page_i18nl10n.pid = tl_page.id
              WHERE tl_page.alias = '{$objPage->alias}'
                AND (tl_page_i18nl10n.start = '' OR tl_page_i18nl10n.start < $time)
                AND (tl_page_i18nl10n.stop = '' OR tl_page_i18nl10n.stop > $time)
                AND tl_page_i18nl10n.language = '$strLanguage'
                ");

        if ($objLocalizedPage->numRows > 1)
        {
            throw new \Exception(
                "Too many localized pages in tl_page_i18nl10n related to tl_page with id {$objPage->id}"
            );
        }

        return $objLocalizedPage->next();
    }
}
