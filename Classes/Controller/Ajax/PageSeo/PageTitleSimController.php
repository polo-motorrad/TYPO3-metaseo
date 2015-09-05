<?php

/*
 *  Copyright notice
 *
 *  (c) 2015 Markus Blaschke <typo3@markus-blaschke.de> (metaseo)
 *  (c) 2013 Markus Blaschke (TEQneers GmbH & Co. KG) <blaschke@teqneers.de> (tq_seo)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
 */

namespace Metaseo\Metaseo\Controller\Ajax\PageSeo;

use Metaseo\Metaseo\Controller\Ajax\AbstractPageSeoController;
use Metaseo\Metaseo\Controller\Ajax\PageSeoSimulateInterface;
use Metaseo\Metaseo\Exception\Ajax\AjaxException;
use Metaseo\Metaseo\Utility\DatabaseUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\AjaxRequestHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility as Typo3GeneralUtility;

class PageTitleSimController extends AbstractPageSeoController implements PageSeoSimulateInterface
{
    const LIST_TYPE = 'pagetitlesim';

    /**
     * @inheritDoc
     */
    protected function initFieldList()
    {
        $this->fieldList = array(
            'title',
            'tx_metaseo_pagetitle',
            'tx_metaseo_pagetitle_rel',
            'tx_metaseo_pagetitle_prefix',
            'tx_metaseo_pagetitle_suffix',
        );
    }

    /**
     * @inheritDoc
     */
    protected function getIndex(array $page, $depth, $sysLanguage)
    {
        $list = $this->pageSeoDao->index($page, $depth, $sysLanguage, $this->fieldList);

        $uidList = array_keys($list);

        if (!empty($uidList)) {
            // Check which pages have templates (for caching and faster building)
            $this->templatePidList = array();

            $query   = 'SELECT pid
                          FROM sys_template
                         WHERE pid IN (' . implode(',', $uidList) . ')
                           AND deleted = 0
                           AND hidden = 0';
            $pidList = DatabaseUtility::getCol($query);
            foreach ($pidList as $pid) {
                $this->templatePidList[$pid] = $pid;
            }

            // Build simulated title
            foreach ($list as &$row) {
                $row['title_simulated'] = $this->simulateTitle($row, $sysLanguage);
            }
        }

        return $list;
    }

    /**
     * Generate simulated page title
     *
     * @param   array   $page        Page
     * @param   integer $sysLanguage System language
     *
     * @return  string
     */
    protected function simulateTitle(array $page, $sysLanguage)
    {
        $this->initTsfe($page, null, $page, null, $sysLanguage);

        $pagetitle = $this->objectManager->get('Metaseo\\Metaseo\\Page\\Part\\PagetitlePart');
        $ret       = $pagetitle->main($page['title']);

        return $ret;
    }

    /**
     * @inheritDoc
     */
    public function simulateAction($params = array(), AjaxRequestHandler &$ajaxObj = null)
    {
        try {
            $this->init();
            $ajaxObj->setContent($this->executeSimulate());
        } catch (\Exception $exception) {
            $this->ajaxExceptionHandler($exception, $ajaxObj);
        }

        $ajaxObj->setContentFormat(self::CONTENT_FORMAT_JSON);
        $ajaxObj->render();
    }

    /**
     * @return array
     *
     * @throws AjaxException
     */
    protected function executeSimulate()
    {
        $pid = (int)$this->postVar['pid'];

        if (empty($pid)) {

            throw new AjaxException(
                'message.error.typo3_page_not_found',
                '[0x4FBF3C08]',
                self::HTTP_STATUS_BAD_REQUEST
            );
        }

        $page = BackendUtility::getRecord('pages', $pid);

        if (empty($page)) {

            throw new AjaxException(
                'message.error.typo3_page_not_found',
                '[0x4FBF3C09]',
                self::HTTP_STATUS_BAD_REQUEST
            );
        }

        // Load TYPO3 classes
        $this->initTsfe($page, null, $page, null);

        $pagetitle = Typo3GeneralUtility::makeInstance(
            'Metaseo\\Metaseo\\Page\\Part\\PagetitlePart'
        );

        return array(
            'title' => $pagetitle->main($page['title']),
        );
    }
}
