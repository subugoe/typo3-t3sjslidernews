<?php

declare(strict_types=1);

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Helmut Hackbarth <info@t3solution.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class that adds the wizard icon.
 *
 * @author    Helmut Hackbarth <info@t3solution.de>
 */
class tx_t3sjslidernews_pi1_wizicon
{
    /**
     * @var \TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser
     */
    protected $llxmlParser;

    /**
     * Processing the wizard items array.
     *
     * @param array $wizardItems: The wizard items
     *
     * @return array Modified array with wizard items
     */
    public function proc(array $wizardItems): array
    {
        $LL = $this->includeLocalLang();

        $wizardItems['plugins_tx_t3sjslidernews_pi1'] = [
            'icon' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('t3s_jslidernews').'pi1/ce_wiz.gif',
            'title' => $LL['pi1_title'],
            'description' => $GLOBALS['LANG']->getLLL('pi1_plus_wiz_description', $LL),
            'params' => '&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=t3s_jslidernews_pi1',
        ];

        return $wizardItems;
    }

    /**
     * Reads the [extDir]/locallang.xml and returns the $LOCAL_LANG array found in that file.
     *
     * @return array The array with language labels
     */
    public function includeLocalLang(): array
    {
        $llFile = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('t3s_jslidernews').'locallang.xml';

        return $this->getLlxmlParser()->getParsedData($llFile, $GLOBALS['LANG']->lang, 'utf-8');
    }

    /**
     * @return \TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser
     */
    protected function getLlxmlParser()
    {
        if (!isset($this->llxmlParser)) {
            $this->llxmlParser = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Parser\LocallangXmlParser::class);
        }

        return $this->llxmlParser;
    }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3s_jslidernews/pi1/class.tx_t3sjslidernews_pi1_wizicon.php']) {
    include_once $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3s_jslidernews/pi1/class.tx_t3sjslidernews_pi1_wizicon.php'];
}
