<?php
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
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_tslib.'class.tslib_pibase.php');

/**
 * Plugin 'JSliderNews' for the 't3s_jslidernews' extension.
 *
 * @author	Helmut Hackbarth <info@t3solution.de>
 * @package	TYPO3
 * @subpackage	tx_t3sjslidernews
 */
class tx_t3sjslidernews_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_t3sjslidernews_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_t3sjslidernews_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 't3s_jslidernews';	// The extension key.
	var $pi_checkCHash = true;

	var $sliderWidth = 700;
	var $sliderHeight = 300;                                                 
	var $imageWidth = 700;
	var $imageHeight = 300;
	var $extPath = '';
	var $noPath = false;
  var $DAM = false;
  var $extConf = array();
  var $variant = false; // option in sliderstyle 2 & 3 (slide out navigation)
  var $iframeOverlay = false;
  var $inlineOverlay = false;
  var $readmore = 0;

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	public function main( $content, $conf ) {
		$this->conf = $conf;
		$this->pi_loadLL();
    $this->extPath = t3lib_extMgm::siteRelPath($this->extKey);
    $this->init();
    $this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['t3s_jslidernews']);
    $this->variant = $this->conf['variant'] && ($this->conf['sliderStyle'] == 2 || $this->conf['sliderStyle'] == 3) ? true : false;

    switch ($this->conf['contentStyle']) {
      case 'tt_news':
        if( t3lib_extMgm::isLoaded('tt_news') == 'TRUE' ) {
          $this->DAM = t3lib_extMgm::isLoaded('dam_ttnews') ? true : false;
          $sliderRecords = $this->getSliderNewsRecords();
          if ( is_array($sliderRecords) && !empty($sliderRecords) ) {
            $content = $this->getNews($sliderRecords);
          } else {
            $content = '<p style="color:red;">ERROR - '.$this->extKey.': No news items -> select page(s) with tt_news records or set plugin.tt_news.pid_list in the constants!</p>';
          }
        } else {
          $content = '<p style="color:red;">ERROR - '.$this->extKey.': No plugin -> tt_news is not loaded!</p>';
        }
      break;
      case 'tt_content':
        $this->DAM = t3lib_extMgm::isLoaded('dam_ttcontent') ? true : false;
       $sliderRecords = $this->getSliderContentRecords();
        if ( is_array($sliderRecords) && !empty($sliderRecords) ) {
          $content = $this->getContent($sliderRecords);
        } else {
          $content = '<p style="color:red;">ERROR - '.$this->extKey.': No content-element(s) selected!</p>';
        }
      break;
      case 'pages':
        $this->DAM = t3lib_extMgm::isLoaded('dam_pages') ? true : false;
        $sliderRecords = $this->getSliderPagesRecords();
        if ( is_array($sliderRecords) && !empty($sliderRecords) ) {
          $content = $this->getMenu($sliderRecords);
        } else {
          $content = '<p style="color:red;">ERROR - '.$this->extKey.': No page(s) selected!</p>';
        }
      break;
    }

    $queryNoConflict = $this->extConf['jqueryNoConflict'] ? 'jQuery.noConflict()' : 'jQuery';
    $pagerender = $GLOBALS['TSFE']->getPageRenderer();
    $this->addJquery($pagerender);
    if ( $this->conf['sliderStyle'] == 7 ) {
      $this->addNivoHeaderParts($pagerender,$queryNoConflict);
    } else {
      $this->addHeaderParts($pagerender,$queryNoConflict);
    }

		return $this->pi_wrapInBaseClass($content);
	}
	
   
	protected function init(){
    $this->pi_initPIflexForm();
		$piFlexForm = $this->cObj->data['pi_flexform'];
 		foreach ( $piFlexForm['data'] as $sheet => $data ) {
			foreach ( $data as $lang => $value ) {
				foreach ( $value as $key => $val ) {
					if ( is_array($val) ) {
						$val = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
            if ( $val || !isset($this->conf[$key]) ) {
							$this->conf[$key] = $val;					
						} else {
              if ( $key == 'defaultImage' ) $this->noPath = true;			
            }
						if ( is_numeric($this->conf[$key]) ) $this->conf[$key] = intval( $this->conf[$key] );
					}
        }
      }
    }
  }	


	protected function getSliderNewsRecords() {
    // get tt_news setup & conf 
    $_tt_news_setup = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tt_news.'];
    $_tt_news_confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tt_news']);
		// makeInstance - tt_news
    $_tt_news_obj = t3lib_div::makeInstance('tx_ttnews');
    $_tt_news_obj->cObj = &$this->cObj; 
    $newsConf = array();
    // SELECT
    $newsConf['selectFields'] = $this->pi_prependFieldsWithTable('tt_news','uid,pid,title,datetime,image,imagecaption,imagealttext,imagetitletext,short,bodytext,author,author_email,category,type,ext_url,page,sys_language_uid,archivedate');
    if ( t3lib_extMgm::isLoaded('rgnewsce') ) $newsConf['selectFields'] .= ',tt_news.tx_rgnewsce_ce';
    if ( t3lib_extMgm::isLoaded('dam_ttnews') ) $newsConf['selectFields'] .= ',tt_news.tx_damnews_dam_images';
    if ( t3lib_extMgm::isLoaded('rgmediaimagesttnews') ) $newsConf['selectFields'] .= ',tt_news.tx_rgmediaimages_config';
    if ( $this->extConf['ownMarker_1'] && (strpos($this->extConf['ownMarker_1'],'tt_news.')!==false) ) $newsConf['selectFields'] .= ','.trim( $this->extConf['ownMarker_1'] );
    if ( $this->extConf['ownMarker_2'] && (strpos($this->extConf['ownMarker_2'],'tt_news.')!==false) ) $newsConf['selectFields'] .= ','.trim( $this->extConf['ownMarker_2'] );
    // WHERE
		$newsConf['where'] = ' 1=1';
    $newsConf['where'] .= $_tt_news_obj->getLanguageWhere();
		$newsConf['where'] .= '  AND tt_news.pid > 0';
    // ttnewsType 
    if( $this->conf['ttnewsType'] < 3 ) $newsConf['where'] .= ' AND tt_news.type = '.$this->conf['ttnewsType'];
    // ttnewsSysFolder 
		$newsConf['pidInList'] = $this->conf['ttnewsSysFolder'] ? $this->conf['ttnewsSysFolder'] : $_tt_news_setup['pid_list'];
    if ( $this->extConf['recLevel'] ) $newsConf['pidInList'] = $this->pi_getPidList($newsConf['pidInList'], $this->extConf['recLevel']);
    // sliderCat 
    if ( $this->conf['sliderCat'] ) {
      $newsConf['leftjoin'] = 'tt_news_cat_mm ON tt_news.uid = tt_news_cat_mm.uid_local';
      $newsConf['where'] .= ' AND (tt_news_cat_mm.uid_foreign IN ('.$this->conf['sliderCat'].'))';
    }
    // ascDesc
    $this->conf['ascDescField'] = !$this->conf['ascDescField'] ? 'datetime' : $this->conf['ascDescField'];
    $newsConf['orderBy'] = 'tt_news.'.$this->conf['ascDescField'].' DESC';
    if ( $this->conf['ascDesc'] == 1 ) $newsConf['orderBy'] = 'tt_news.'.$this->conf['ascDescField'].' ASC';
    if ( $this->conf['ascDesc'] == 2 ) $newsConf['orderBy'] = 'RAND()';
    // newsArchive
		if ( !$this->conf['newsArchive'] ) {
      $time = $GLOBALS['SIM_ACCESS_TIME'];
			$newsConf['where'] .= ' AND (tt_news.archivedate = 0 OR tt_news.archivedate > '. $time .')';
			if ( $_tt_news_setup['datetimeMinutesToArchive'] || $_tt_news_setup['datetimeHoursToArchive'] || $_tt_news_setup['datetimeDaysToArchive'] ) {
				if ( $_tt_news_setup['datetimeMinutesToArchive'] ) {
          $theTime = $time - intval($_tt_news_setup['datetimeMinutesToArchive'] * 60);
				} elseif ( $_tt_news_setup['datetimeHoursToArchive'] ) {
          $theTime = $time - intval($_tt_news_setup['datetimeHoursToArchive'] * 3600);
				} else {
          $theTime = $time - intval($_tt_news_setup['datetimeDaysToArchive'] * 86400);
				}
				$newsConf['where'] .= ' AND tt_news.datetime > ' . $theTime;
			}
		}
    // newsLimit
    $newsConf['max'] = $this->conf['newsLimit'] ? $this->conf['newsLimit'] : 20;
    // languageField
    if ( !$GLOBALS['TSFE']->sys_language_contentOL ) $newsConf['languageField'] = $GLOBALS['TSFE']->sys_language_uid ? $GLOBALS['TSFE']->sys_language_uid : 0;
    // showNewsWithoutDefaultTranslation
    if ( $_tt_news_setup['showNewsWithoutDefaultTranslation'] && $GLOBALS['TSFE']->sys_language_content ) {
    	$newsConf['where'] = '(' . $newsConf['where'] . ' OR (tt_news.sys_language_uid=' . $GLOBALS['TSFE']->sys_language_content . ' AND NOT tt_news.l18n_parent))';
    }
    // excludeAlreadyDisplayedNews
    if ( $this->conf['excludeAlreadyDisplayedNews'] && $GLOBALS['TSFE']->displayedNews ) {
      $excludeUids = implode(',', $GLOBALS['TSFE']->displayedNews);
      $newsConf['where'] .= ' AND tt_news.uid NOT IN (' . $GLOBALS['TYPO3_DB']->cleanIntList($excludeUids) . ')';
    }
    // get the result
    $newsRes = $_tt_news_obj->cObj->exec_getQuery('tt_news', $newsConf);
    $newsRow = array();
    $newsRecords = array();
    $categories = array(); 

    while ( $newsRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($newsRes) ) { 
      $nUid = $newsRow['uid']; 
      if ($this->conf['excludeAlreadyDisplayedNews']) $GLOBALS['TSFE']->displayedNews[] = $nUid;
      $newsRecords[$nUid] = $newsRow;
			if ( $GLOBALS['TSFE']->sys_language_content ) {
				// prevent link targets from being changed in localized records
				$tmpPage = $newsRecords[$nUid]['page'];
				$newsRecords[$nUid] = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tt_news', $newsRecords[$nUid], $GLOBALS['TSFE']->sys_language_content, $GLOBALS['TSFE']->sys_language_contentOL, '');
				$newsRecords[$nUid]['page'] = $tmpPage;
				// Localization mode for images
        if (!$_tt_news_confArr['l10n_mode_imageExclude'] && $newsRecords[$nUid]['_LOCALIZED_UID']) { 
          $llRec = $this->pi_getRecord('tt_news', $newsRecords[$nUid]['_LOCALIZED_UID']);
          if( $this->DAM ) {
            if ( $llRec['tx_damnews_dam_images'] ) $newsRecords[$nUid]['llDamImage'] = TRUE;
          } else {
            $newsRecords[$nUid]['image'] = $llRec['image'] ? $llRec['image'] : $newsRecords[$nUid]['image'];
          }
          unset ($llRec);
        }
			}
      // get the categories DETAILS
      $categories = $_tt_news_obj->getCategories($nUid);
      foreach ( $categories as $key=>$cat ) {
        $newsRecords[$nUid]['catDetail'][$key]['catid'] = intval($cat['catid']);
        $newsRecords[$nUid]['catDetail'][$key]['title'] = trim($cat['title']); 
        $newsRecords[$nUid]['catDetail'][$key]['single_pid'] = intval($cat['single_pid']);
        $newsRecords[$nUid]['catDetail'][$key]['mmsorting'] = intval($cat['mmsorting']);
        $newsRecords[$nUid]['catTitleList'] .= trim($cat['title']).', ';           
      }
      unset($categories);
      if( $this->DAM ) {
        if ($newsRecords[$nUid]['llDamImage']) {
          $tempUid = $newsRecords[$nUid]['uid'];
          $newsRecords[$nUid]['uid'] = $newsRecords[$nUid]['_LOCALIZED_UID'];
        }        
        $newsRecords[$nUid]['image'] = '';
        $newsRecords[$nUid] = $this->getDamData($newsRecords[$nUid], 'tt_news', 'tx_damnews_dam_images');
        if ( $newsRecords[$nUid]['llDamImage'] ) $newsRecords[$nUid]['uid'] = $tempUid;
      } else {
        $newsRecords[$nUid]['image'] = t3lib_div::trimExplode(',', $newsRecords[$nUid]['image']);
        $newsRecords[$nUid]['image'] = $newsRecords[$nUid]['image'][0];
      }
      if ( t3lib_extMgm::isLoaded('rgnewsce') == 'TRUE' && $newsRecords[$nUid]['tx_rgnewsce_ce'] && !$newsRecords[$nUid]['image'] ) {
        $newsRecords[$nUid] = $this->getRgnewsce($newsRecords[$nUid]);
      }                                               
      // addParams for singleLink
      if ($_tt_news_setup['useHRDates'] && $_tt_news_setup['useHRDatesSingle']) {
          if ($_tt_news_setup['useHRDatesSingleWithoutDay']) {
            $newsRecords[$nUid]['addParam'] = '&tx_ttnews[year]='.date('Y', $newsRecords[$nUid]['datetime']).'&tx_ttnews[month]='.date('m', $newsRecords[$nUid]['datetime']).'&tx_ttnews[tt_news]='.$newsRecords[$nUid]['uid'];
      		} else {
            $newsRecords[$nUid]['addParam'] = '&tx_ttnews[year]='.date('Y', $newsRecords[$nUid]['datetime']).'&tx_ttnews[month]='.date('m', $newsRecords[$nUid]['datetime']).'&tx_ttnews[day]='.date('d', $newsRecords[$nUid]['datetime']).'&tx_ttnews[tt_news]='.$newsRecords[$nUid]['uid'];
          }
      } else {
        $newsRecords[$nUid]['addParam'] = '&tx_ttnews[tt_news]='.$newsRecords[$nUid]['uid'];
      }
  		// sys_language_mode defines what to do if the requested translation is not found
      $sys_language_mode = ($_tt_news_setup['sys_language_mode'] ? $_tt_news_setup['sys_language_mode'] : $GLOBALS['TSFE']->sys_language_mode);
      // get the translated record if the content language is not the default language
      if ( $GLOBALS['TSFE']->sys_language_content ) {
        $OLmode = $sys_language_mode == 'strict' ? 'hideNonTranslated' : '';
        $ll = $newsRecords[$nUid]['sys_language_uid'] ? TRUE : FALSE;
  			$newsRecords[$nUid] = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tt_news', $newsRecords[$nUid], $GLOBALS['TSFE']->sys_language_content, $OLmode);
        if ( !$newsRecords[$nUid] && !$ll ) unset($newsRecords[$nUid]);
    	}
      // if an image is required ... unset all records without an image  
      if ( $this->conf['requireImage'] === 0 && !$newsRecords[$nUid]['image'] ) unset($newsRecords[$nUid]);
    }

    return $newsRecords;
  }


  protected function getSliderContentRecords() {  
    $whereCType = ' AND (tt_content.CType=\'textpic\' OR tt_content.CType=\'text\' OR tt_content.CType=\'media\' OR tt_content.CType=\'html\')';
  	// get content uids from selected pages
    if ( $this->conf['contentPages'] ) {    
  		$pageUids = t3lib_div::trimExplode(',', $this->conf['contentPages']);
  		foreach ($pageUids as $pageUid) {
        $wherePages = 'tt_content.pid='.$pageUid.' AND tt_content.sys_language_uid IN (-1,0)';  
			  $wherePages .= $whereCType;
   			$wherePages .= $this->cObj->enableFields('tt_content');
        $resPages = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tt_content.uid', 'tt_content', $wherePages);
   			while ($rowCe = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resPages)) $pageCeUids .= ','.$rowCe['uid']; 
        $GLOBALS['TYPO3_DB']->sql_free_result($resPages); 
  		}
  	}

		if ( $pageCeUids ) {
			if ( $this->conf['contentElement'] ) {
        $this->conf['contentElement'] = t3lib_div::uniqueList($this->conf['contentElement'] . $pageCeUids);
			} else {
				$this->conf['contentElement'] = substr($pageCeUids,1);
			}
		}  
    $select = $this->pi_prependFieldsWithTable('tt_content','uid,pid,header,bodytext,image,subheader,header_link,imagecaption,date,altText,titleText,sys_language_uid,CType,pi_flexform,section_frame');
    if ( $this->extConf['ownMarker_1'] && (strpos($this->extConf['ownMarker_1'],'tt_content.')!==false) ) $select .= ','.trim($this->extConf['ownMarker_1']);
    if ( $this->extConf['ownMarker_2'] && (strpos($this->extConf['ownMarker_2'],'tt_content.')!==false) ) $select .= ','.trim($this->extConf['ownMarker_2']);
   	$where = 'tt_content.uid IN('.$this->conf['contentElement'].') AND tt_content.sys_language_uid IN (-1,0)';
    $where .= $whereCType;
   	$where .= $this->cObj->enableFields('tt_content');
   	$orderBy = $this->conf['contentOrderBy'] ? 'RAND()' : ' FIELD(uid, '. $this->conf['contentElement'] .')';
  	$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select, 'tt_content', $where, '', $orderBy);
    $contentRecords = array();
		foreach( $rows as $key=>$row ) {
      $contentRecords[$key] = $row;
			if ( $GLOBALS['TSFE']->sys_language_content ) {
 				$contentRecords[$key] = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tt_content', $contentRecords[$key], $GLOBALS['TSFE']->sys_language_content, $GLOBALS['TSFE']->sys_language_contentOL, '');
        $contentRecords[$key]['uid'] = $contentRecords[$key]['_LOCALIZED_UID'] ? $contentRecords[$key]['_LOCALIZED_UID'] : $contentRecords[$key]['pid'];
			}
      if( $this->DAM ) {
        $contentRecords[$key]['image'] = '';
        $contentRecords[$key] = $this->getDamData($contentRecords[$key], 'tt_content', 'tx_damttcontent_files');
      } else {
        if ( t3lib_extMgm::isLoaded('rgmediaimages') ) {
          if ( strpos($contentRecords[$key]['image'],'.flv')!==false || strpos($contentRecords[$key]['image'],'.pdf')!==false || strpos($contentRecords[$key]['image'],'.ai')!==false || strpos($contentRecords[$key]['image'],'.swf')!==false || strpos($contentRecords[$key]['image'],'.mp3')!==false || strpos($contentRecords[$key]['image'],'.rgg')!==false ) {
            unset($contentRecords[$key]);
          }
        }
      }
      // get the translated record if the content language is not the default language
      if ( $GLOBALS['TSFE']->sys_language_content ) {
        $OLmode = $GLOBALS['TSFE']->sys_language_mode == 'strict' ? 'hideNonTranslated' : '';
        $ll = $contentRecords[$key]['sys_language_uid'] ? TRUE : FALSE;
    		$contentRecords[$key] = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tt_content', $contentRecords[$key], $GLOBALS['TSFE']->sys_language_content, $OLmode);
        if ( !$contentRecords[$key] && !$ll ) unset($contentRecords[$key]);
      }
		}
                                                                   
    return $contentRecords; 
  }  


	protected function getSliderPagesRecords() {
    $select = $this->pi_prependFieldsWithTable('pages','uid,pid,title,subtitle,abstract,media');
    if ( $this->extConf['ownMarker_1'] && (strpos($this->extConf['ownMarker_1'],'pages.')!==false) ) $select .= ','.trim($this->extConf['ownMarker_1']);
    if ( $this->extConf['ownMarker_2'] && (strpos($this->extConf['ownMarker_2'],'pages.')!==false) ) $select .= ','.trim($this->extConf['ownMarker_2']);
   	$where = 'pages.uid IN('.$this->conf['pages'].')';
   	$where .= $this->cObj->enableFields('pages');
  	$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($select, 'pages', $where, '', ' FIELD(uid, '. $this->conf['pages'] .')');
		$pageRecords = array();
    foreach( $rows as $key=>$row ) {
      $pageRecords[$key] = $row;
      $pageRecords[$key]['image'] = t3lib_div::trimExplode(',', $pageRecords[$key]['media']); 
      $pageRecords[$key]['image'] = $pageRecords[$key]['image']['0'];
      unset($pageRecords[$key]['media']);
      if ( $GLOBALS['TSFE']->sys_language_content ) {
        $origImg = $pageRecords[$key]['image'];
        $OLmode = $GLOBALS['TSFE']->sys_language_mode == 'strict' ? 'hideNonTranslated' : '';
    		$pageRecords[$key] = $GLOBALS['TSFE']->sys_page->getPageOverlay($pageRecords[$key], $GLOBALS['TSFE']->sys_language_content, $OLmode);
        $pageRecords[$key]['image'] = $pageRecords[$key]['image'] ? $pageRecords[$key]['image'] : $origImg;
        if ( $GLOBALS['TSFE']->sys_language_mode == 'strict' && !$pageRecords[$key]['_PAGES_OVERLAY'] ) $unset = TRUE;
      }
      if( $this->DAM ) {
        $pageRecords[$key]['image'] = '';
        $pageRecords[$key] = $this->getDamData($pageRecords[$key], 'pages', 'tx_dampages_files');
      }
      if ( $unset ) unset($pageRecords[$key]);
		}

    return $pageRecords; 
  }


  protected function getNews( $sliderRecords=array() ) {
    // if no template is loaded - use the default template
    $this->conf['templateFile'] = $this->conf['templateFile'] ? $this->conf['templateFile'] : $this->extPath.'slidernews_template.html';
    $templateFile = $this->cObj->fileResource($this->conf['templateFile']);
    $templateFile = ( $this->conf['topBottomNav'] && $this->conf['sliderStyle'] == 8 ) ? $this->cObj->getSubpart($templateFile,'###SLIDERSTYLE_'.$this->conf['sliderStyle'].'_TOP###') : $this->cObj->getSubpart($templateFile,'###SLIDERSTYLE_'.$this->conf['sliderStyle'].'###');   
     // get global marker
    $marks = $this->getGlobalMarker();  
    // general link configuration
    $linkConf = array();
    $linkConf['useCacheHash'] = 1;
    $emailLinkConf = array();
    $emailLinkConf['ATagParams'] = 'class="author-email"';
    // singlePid
    $parameter = $this->conf['ttnewsSinglePid'] ? $this->conf['ttnewsSinglePid'] :  $GLOBALS['TSFE']->tmpl->setup['plugin.']['tt_news.']['singlePid'];

    $marksItem = array();
    $imageConf = array();
    $thumbConf = array();
    $record = array();
    $sliderRecords = array_merge($sliderRecords);
    foreach ( $sliderRecords as $key=>$record ) {
      // ff option catSinglePid
      if($this->conf['catSinglePid']) {
        $single_pid = array_values($record['catDetail']);
        $single_pid = $single_pid['0']['single_pid'];
        $parameter = $single_pid ? $single_pid : $parameter;
      } 
      // link configuration
      $linkConf['parameter'] = $parameter;
      $linkConf['ATagParams'] = 'class="readmore"';
      $linkConf['additionalParams'] = $record['addParam'];
      $linkConf['title'] = $record['title'];
      // if tt_news type is an internal- or external link
      if ( $record['type'] == 1 || $record['type'] == 2 ) {
        $pkey = $record['type'] == 1 ? 'page' : 'ext_url';
        $linkConf['parameter'] = $record[$pkey];
        unset($linkConf['additionalParams']);
        unset($linkConf['useCacheHash']);
      }
      $emailLinkConf['parameter'] = $record['author_email'];
      $emailLinkConf['title'] = $this->pi_getLL('emailTo').' '.$record['author'];
      // text-fields conversion
      $record = $this->textConversion( $record );
      // image configuration
      $v = $v ? $v : 0;
      $randbefore = $randbefore ? $randbefore : 0;
      $imageFileConf = list ( $record, $imageConf, $thumbConf, $randbefore, $v ) = $this->imageConf( $record, $key, $randbefore, $v );
      $record = $imageFileConf[0]; 
      $imageConf = $this->conf['image.'] ? $this->conf['image.'] : $imageFileConf[1];	
      $imageConf['file'] = $imageFileConf[1]['file']; 
      $thumbConf = $this->conf['thumb.'] ? $this->conf['thumb.'] : $imageFileConf[2]; 
      $thumbConf['file'] = $imageFileConf[2]['file'];
      $imageConf['titleText'] = $imageConf['titleText'] ? $imageConf['titleText'] : $record['title']; 
      $imageConf['altText'] = $imageConf['altText'] ? $imageConf['altText'] :  $record['title'];  
      $thumbConf['titleText'] = $imageConf['titleText'];
      $thumbConf['altText'] = $imageConf['altText'];
      $randbefore = $imageFileConf[3];
      $v = $imageFileConf[4];
      // colorbox overlay
      $inlineOverlay .= '';
      if ( $this->conf['sliderStyle'] != 7 && !$record['type'] ) {
        if ( trim($record['tx_rgmediaimages_config']) ) {
          if ( $this->conf['videoOverlay'] ) {
            $videos = t3lib_div::trimExplode(',', str_replace(array("\r\n", "\n", "\r"), ",", $record['tx_rgmediaimages_config']));
            $record['iframe'] = true;
            $this->iframeOverlay = true;
            $record['mmFile'] = $videos[0];
            $record['mmFile'] = $this->checkVideo($record['mmFile']);
            $linkConf['parameter'] = $record['mmFile'];
            $linkConf['ATagParams'] = 'class="iframe-overlay'.$this->cObj->data['uid'].'"';
          } 
        } elseif ( $record['CType'] == 'media' ) {
          if ( $this->conf['videoOverlay'] ) {
            $flexformData = t3lib_div::xml2array($record['pi_flexform']);
            $mmType = t3lib_div::compat_version('4.7') ? $flexformData['data']['sGeneral']['lDEF']['mmType']['vDEF'] : $this->pi_getFFvalue($flexformData, 'mmType');
            if ( $mmType == 'video' ) {
              $record['iframe'] = true;
              $this->iframeOverlay = true;
              $record['mmFile'] = t3lib_div::compat_version('4.7') ? $flexformData['data']['sVideo']['lDEF']['mmFile']['vDEF'] : $this->pi_getFFvalue($flexformData, 'mmFile');
              $record['mmFile'] = $this->checkVideo($record['mmFile']);
              $linkConf['parameter'] = $record['mmFile'];
              $linkConf['ATagParams'] = 'class="iframe-overlay'.$this->cObj->data['uid'].'"';
            }
          }
        } else {
          // overlay == 1 = all CEs // overlay == 2 = selected CEs 
          if ( $this->conf['newsOverlay'] ) {
            $record['inline'] = true;
            $this->inlineOverlay = true;
            $ttContent['conf']= array(
              'tables' => 'tt_news',
              'source' => $record['uid'],
              'dontCheckPid' => 1,
              'wrap' => '<div id="inline_c'.$record['uid'].'" class="inline-record">|</div>'
            );
            if( $GLOBALS['TSFE']->config['config']['prefixLocalAnchors'] ) $GLOBALS['TSFE']->config['config']['prefixLocalAnchors'] = '0';
            $inlineOverlay .= $this->cObj->RECORDS($ttContent['conf']); 
          }
        }
      }
      // get the general record-marker
      $marksItem = $this->getRecordMarker( $record, $linkConf );
      // author-marker
      $marksItem['###AUTHOR###']  = $record['author'] ? '<span class="author">'.$record['author'].'</span>' : '';
      $marksItem['###AUTHOR_ICON###']  = $record['author'] ? '<span class="author_icon">'.$record['author'].'</span>' : '';
      $marksItem['###AUTHORLINK###']  = $record['author'] ? '<span class="author">'.$record['author'].'</span>' : '';
      $marksItem['###AUTHORLINK_ICON###']  = $record['author'] ? '<span class="author_icon">'.$record['author'].'</span>' : '';
      if ( $record['author_email'] ) {
        $marksItem['###AUTHORLINK###']  = $record['author'] ? '<span class="author">'.$this->cObj->typolink($record['author'], $emailLinkConf).'</span>' : '';
        $marksItem['###AUTHORLINK_ICON###']  = $record['author'] ? '<span class="author_icon">'.$this->cObj->typolink($record['author'], $emailLinkConf).'</span>' : '';
      }
      // overwrite if colorbox-overlay
      if ($record['inline']) {
        $marksItem['###TITLELINK###'] = '<a href="#inline_c'.$record['uid'].'" title="'.$record['title'].'" class="inline-overlay'.$this->cObj->data['uid'].'">'.$record['title'].'</a>';
        $marksItem['###READMORE###'] = '<a title="'.$record['title'].'" class="readmore-overlay'.$this->cObj->data['uid'].'" onclick="$(\'.active-slider a.inline-overlay'.$this->cObj->data['uid'].'\').colorbox({open:true});" style="cursor:pointer;">'.$this->pi_getLL('readmore').'</a>';
      }
      // Use thumbnails for Control Nav with nivo
      if ( $this->conf['sliderStyle'] == 7 && $this->conf['controlNav'] == 2 ) {
        unset ($thumbConf['stdWrap.']);
        $thumb = $this->cObj->IMG_RESOURCE($thumbConf); 
        $imageConf['stdWrap.']['addParams.']['data-thumb'] = $thumb;
      }
      // image-marker
      $marksItem['###IMAGE###'] = $this->cObj->IMAGE($imageConf);
      if ( $this->conf['imageLink'] ) {
        $linkConf['ATagParams'] = 'class="image-link"';
        if ( $record['iframe']) {
              $vLinkConf = $linkConf;
              $vLinkConf['ATagParams'] = 'class="iframe-overlay'.$this->cObj->data['uid'].'"';
              if ( $this->extConf['overlayPlayButton'] ) {
                $vLinkConf['ATagBeforeWrap'] = 1;
                $vLinkConf['wrap'] = '|<span class="video-overlay" style="width:'.$this->imageWidth.'px; height:'.$this->imageHeight.'px;">&nbsp;</span>';
              }
              $marksItem['###IMAGE###'] = $this->cObj->typolink($marksItem['###IMAGE###'], $vLinkConf);
          } elseif ($record['inline']) {
            $marksItem['###IMAGE###'] = '<a title="'.$record['title'].'" class="image-overlay'.$this->cObj->data['uid'].'" onclick="$(\'.active-slider a.inline-overlay'.$this->cObj->data['uid'].'\').colorbox({open:true});" style="cursor:pointer;">'.$marksItem['###IMAGE###'].'</a>';
          } else {
            $marksItem['###IMAGE###'] = $this->cObj->typolink($marksItem['###IMAGE###'], $linkConf);
          }
      }
      $marksItem['###THUMBNAIL###'] = $this->cObj->IMAGE($thumbConf);
      // number-marker
      $marksItem['###NUMBER###']  = $this->conf['sliderStyle'] == 7 ? ($this->cObj->data['uid'].'-'.($key+1)) : ($key+1);

  		$templateFileItem = $this->cObj->getSubpart($templateFile,'###SLIDER###');		
  		$subpartContentArray['###SLIDER###'] .= $this->cObj->substituteMarkerArrayCached($templateFileItem,$marksItem);
  		$templateFileItem = $this->cObj->getSubpart($templateFile,'###NAVIGATION###');		
  		$subpartContentArray['###NAVIGATION###'] .= $this->cObj->substituteMarkerArrayCached($templateFileItem,$marksItem);
      // ... pagination-marker
      if ( $this->conf['pagination'] ) {
        if ( $this->conf['pagination'] == 2 ) {
          $pagination .= '<li class="pagSelectorTitle"><a class="pagination-overlay'.$this->cObj->data['uid'].'" onclick="$(\'.active-slider a.inline-overlay'.$this->cObj->data['uid'].'\').colorbox({open:true});" style="cursor:pointer;">'.$marksItem['###TITLE###'].'</a></li>';

        } else {
          $pagination .= '<li class="pagSelector">'. ($key+1) .'</li>';	
        }
      }
    }
    $marks['###INLINE_OVERLAY###'] = '<div style="display:none">'.$inlineOverlay.'</div>';
    $marks['###PAGINATION###'] = $this->conf['pagination'] ? '<ul class="navigationControl" style="width:'.$this->sliderWidth.'px; overflow:hidden;">'. $pagination .'</ul>' : ''; 

    $content = $this->cObj->substituteMarkerArrayCached($templateFile, $marks, $subpartContentArray);    
 
    return $content;
  }

 
 	protected function getContent( $sliderRecords=array() ) {
    $recCount = count($sliderRecords);
    // if no template is loaded - use the default template
    $this->conf['templateFile'] = $this->conf['templateFile'] ? $this->conf['templateFile'] : $this->extPath.'slidercontent_template.html';
    $templateFile = $this->cObj->fileResource($this->conf['templateFile']);
    $templateFile = ( $this->conf['topBottomNav'] && $this->conf['sliderStyle'] == 8 ) ? $this->cObj->getSubpart($templateFile,'###SLIDERSTYLE_'.$this->conf['sliderStyle'].'_TOP###') : $this->cObj->getSubpart($templateFile,'###SLIDERSTYLE_'.$this->conf['sliderStyle'].'###');   
     // get global marker
    $marks = $this->getGlobalMarker();  
    // general link configuration
    $linkConf = array();
    $marksItem = array();
    $imageConf = array();
    $thumbConf = array();
    $record = array();
    foreach ( $sliderRecords as $key=>$record ) {
      $record['image'] = $this->conf['onlyDefaultImage'] ? '' : $record['image'];
      // link configuration
      $linkConf['title'] = $record['header'];
      $linkConf['ATagParams'] = 'class="readmore"';
      if ( $this->conf['defaultLink'] ) { 
        $output = false;   
        $defaultLinks = t3lib_div::trimExplode(',', $this->conf['defaultLink']);
        $linkConf['parameter'] = $defaultLinks[$key];  
        if ( count($defaultLinks) == ($key+1) ) {
          $output = true;
        }
      } else {
        $output = true;
        if ( $record['header_link'] && $this->extConf['useHeaderLink'] ) {
          $linkConf['parameter'] = $record['header_link'];
        } else {
          $linkConf['parameter'] = $record['pid'];
          $linkConf['section'] = $this->extConf['sectionlink'] ? $record['uid'] : '';
        }
      }
      // text-fields conversion
      $record = $this->textConversion( $record );
      // image configuration
      $v = $v ? $v : 0;
      $randbefore = $randbefore ? $randbefore : 0;
      $imageFileConf = list ( $record, $imageConf, $thumbConf, $randbefore, $v ) = $this->imageConf( $record, $key, $randbefore, $v );
      $record = $imageFileConf[0]; 
      $imageConf = ($this->conf['image.'] ? $this->conf['image.'] : $imageFileConf[1]);	
      $imageConf['file'] = $imageFileConf[1]['file']; 
      $thumbConf = ($this->conf['thumb.']?$this->conf['thumb.']:$imageFileConf[2]); 
      $thumbConf['file'] = $imageFileConf[2]['file'];
      $imageConf['titleText'] = $imageConf['titleText'] ? $imageConf['titleText'] : $record['title']; 
      $imageConf['altText'] = $imageConf['altText'] ? $imageConf['altText'] : $record['title']; 
      $thumbConf['titleText'] = $imageConf['titleText'];
      $thumbConf['altText'] = $imageConf['altText'];
      $randbefore = $imageFileConf[3]; 
      $v = $imageFileConf[4]; 
      // colorbox overlay
      $inlineOverlay .= '';
      if ( $this->conf['sliderStyle'] != 7 && !$this->conf['defaultLink'] ) {
        if ( $record['CType'] == 'media' ) {
          if ( $this->conf['videoOverlay'] ) {
            $flexformData = t3lib_div::xml2array($record['pi_flexform']);
            $mmType = t3lib_div::compat_version('4.7') ? $flexformData['data']['sGeneral']['lDEF']['mmType']['vDEF'] : $this->pi_getFFvalue($flexformData, 'mmType');
            if ( $mmType == 'video' ) {
              $record['iframe'] = true;
              $this->iframeOverlay = true;
              $record['mmFile'] = t3lib_div::compat_version('4.7') ? $flexformData['data']['sVideo']['lDEF']['mmFile']['vDEF'] : $this->pi_getFFvalue($flexformData, 'mmFile');
              $record['mmFile'] = $this->checkVideo($record['mmFile']);
              $linkConf['parameter'] = $record['mmFile'];
              $linkConf['ATagParams'] = 'class="iframe-overlay'.$this->cObj->data['uid'].'"';
            }
          }
        } else {
          // overlay == 1 = all CEs // overlay == 2 = selected CEs 
          if ( $this->conf['overlay'] == 1 || ( $this->conf['overlay'] == 2 && $record['section_frame'] == 84 ) ) {
            $record['inline'] = true;
            $this->inlineOverlay = true;
            $ttContent['conf']= array(
              'tables' => 'tt_content',
              'source' => $record['uid'],
              'dontCheckPid' => 1,
              'wrap' => '<div id="inline_c'.$record['uid'].'" class="inline-record">|</div>'
            );
            if( $GLOBALS['TSFE']->config['config']['prefixLocalAnchors'] ) $GLOBALS['TSFE']->config['config']['prefixLocalAnchors'] = '0';
            $inlineOverlay .= $this->cObj->RECORDS($ttContent['conf']); 
          }
        }
      }
      // get the general record-marker
      $marksItem = $this->getRecordMarker( $record, $linkConf );
      // overwrite if colorbox-overlay
      if ($record['inline']) {
        $marksItem['###TITLELINK###'] = '<a href="#inline_c'.$record['uid'].'" title="'.$record['header'].'" class="inline-overlay'.$this->cObj->data['uid'].'">'.$record['header'].'</a>';
        $marksItem['###READMORE###'] = '<a title="'.$record['header'].'" class="readmore-overlay'.$this->cObj->data['uid'].'" onclick="$(\'.active-slider a.inline-overlay'.$this->cObj->data['uid'].'\').colorbox({open:true});" style="cursor:pointer;">'.$this->pi_getLL('readmore').'</a>';
      }
      // subtitle-marker
      $marksItem['###SUBTITLE###'] = $record['subheader'] ? $record['subheader'] : '';
      // Use thumbnails for Control Nav with nivo
      if ( $this->conf['sliderStyle'] == 7 && $this->conf['controlNav'] == 2 ) {
        unset ($thumbConf['stdWrap.']);
        $thumb = $this->cObj->IMG_RESOURCE($thumbConf); 
        $imageConf['stdWrap.']['addParams.']['data-thumb'] = $thumb;
      }
      // image-marker
      $marksItem['###IMAGE###'] = $this->cObj->IMAGE($imageConf);
      if ( $this->conf['imageLink'] ) {
        $linkConf['ATagParams'] = 'class="image-link"';
        if ( $record['iframe']) {
              $vLinkConf = $linkConf;
              $vLinkConf['ATagParams'] = 'class="iframe-overlay'.$this->cObj->data['uid'].'"';
              if ( $this->extConf['overlayPlayButton'] ) {
                $vLinkConf['ATagBeforeWrap'] = 1;
                $vLinkConf['wrap'] = '|<span class="video-overlay" style="width:'.$this->imageWidth.'px; height:'.$this->imageHeight.'px;">&nbsp;</span>';
              }
              $marksItem['###IMAGE###'] = $this->cObj->typolink($marksItem['###IMAGE###'], $vLinkConf);
          } elseif ($record['inline']) {
            $marksItem['###IMAGE###'] = '<a title="'.$record['header'].'" class="image-overlay'.$this->cObj->data['uid'].'" onclick="$(\'.active-slider a.inline-overlay'.$this->cObj->data['uid'].'\').colorbox({open:true});" style="cursor:pointer;">'.$marksItem['###IMAGE###'].'</a>';
          } else {
            $marksItem['###IMAGE###'] = $this->cObj->typolink($marksItem['###IMAGE###'], $linkConf);
          }
      }
     
      $marksItem['###THUMBNAIL###'] = $this->cObj->IMAGE($thumbConf);
      // number-marker
      $marksItem['###NUMBER###']  = $this->conf['sliderStyle'] == 7 ? $this->cObj->data['uid'].'-'.($key+1) : ($key+1);

  		$templateFileItem = $this->cObj->getSubpart($templateFile,'###SLIDER###');		
  		$subpartContentArray['###SLIDER###'] .= $this->cObj->substituteMarkerArrayCached($templateFileItem,$marksItem);
  		$templateFileItem = $this->cObj->getSubpart($templateFile,'###NAVIGATION###');		
  		$subpartContentArray['###NAVIGATION###'] .= $this->cObj->substituteMarkerArrayCached($templateFileItem,$marksItem);
      // pagination-marker
      if ( $this->conf['pagination'] ) {
        if ( $this->conf['pagination'] == 2 ) {
          $pagination .= '<li class="pagSelectorTitle"><a class="pagination-overlay'.$this->cObj->data['uid'].'" onclick="$(\'.active-slider a.inline-overlay'.$this->cObj->data['uid'].'\').colorbox({open:true});" style="cursor:pointer;">'.$marksItem['###TITLE###'].'</a></li>';
        } else {
          $pagination .= '<li class="pagSelector">'. ($key+1) .'</li>';	
        }
      }
    }

    $marks['###INLINE_OVERLAY###'] = '<div style="display:none">'.$inlineOverlay.'</div>';
    $marks['###PAGINATION###'] = $this->conf['pagination'] ? '<ul class="navigationControl" style="width:'.$this->sliderWidth.'px; overflow:hidden;">'. $pagination .'</ul>' : ''; 

    $content = $this->cObj->substituteMarkerArrayCached($templateFile, $marks, $subpartContentArray);    
    if ( $output ) {  
      return $content;
    } else {
      if ($key == 0) $key = -1; 
      return '<p style="color:red;">ERROR - '.$this->extKey.': '.$this->pi_getLL('linkCount').' ('.($key+1).' / '.count($defaultLinks).')<p>';
    }
  }


 	protected function getMenu( $sliderRecords=array() ) {
    // if no template is loaded - use the default template
    $this->conf['templateFile'] = $this->conf['templateFile'] ? $this->conf['templateFile'] : $this->extPath.'slidermenu_template.html';
    $templateFile = $this->cObj->fileResource($this->conf['templateFile']);
    $templateFile = ( $this->conf['topBottomNav'] && $this->conf['sliderStyle'] == 8 ) ? $this->cObj->getSubpart($templateFile,'###SLIDERSTYLE_'.$this->conf['sliderStyle'].'_TOP###') : $this->cObj->getSubpart($templateFile,'###SLIDERSTYLE_'.$this->conf['sliderStyle'].'###');   
    // get global marker
    $marks = $this->getGlobalMarker();  
    // general link configuration
    $linkConf = array();
    $linkConf['ATagParams'] = 'class="readmore"';
    $marksItem = array();
    $imageConf = array();
    $thumbConf = array();
    $record = array();
    $defaultLinks = t3lib_div::trimExplode(',', $this->conf['defaultLink']);
    foreach ( $sliderRecords as $key=>$record ) {
      // link configuration
      $linkConf['title'] = $record['title'];
      $linkConf['parameter'] = $defaultLinks[$key] ? $defaultLinks[$key] : $record['uid'];
      // text-fields conversion
      $record = $this->textConversion( $record );
      // image configuration
      $v = $v ? $v : 0;
      $randbefore = $randbefore ? $randbefore : 0;
      $imageFileConf = list ( $record, $imageConf, $thumbConf, $randbefore, $v ) = $this->imageConf( $record, $key, $randbefore, $v );
      $record = $imageFileConf[0]; 
      $imageConf = ($this->conf['image.'] ? $this->conf['image.'] : $imageFileConf[1]);	
      $imageConf['file'] = $imageFileConf[1]['file']; 
      $thumbConf = ($this->conf['thumb.']?$this->conf['thumb.']:$imageFileConf[2]); 
      $thumbConf['file'] = $imageFileConf[2]['file'];
      $imageConf['titleText'] = $imageConf['titleText'] ? $imageConf['titleText'] : $record['title']; 
      $imageConf['altText'] = $imageConf['altText'] ? $imageConf['altText'] : $record['title']; 
      $thumbConf['titleText'] = $imageConf['titleText'];
      $thumbConf['altText'] = $imageConf['altText'];
      $randbefore = $imageFileConf[3]; 
      $v = $imageFileConf[4]; 
      // get the general record-marker
      $marksItem = $this->getRecordMarker( $record, $linkConf );
      // subtitle-marker
      $marksItem['###SUBTITLE###'] = $record['subtitle'] ? $record['subtitle'] : '';
      // Use thumbnails for Control Nav with nivo
      if ( $this->conf['sliderStyle'] == 7 && $this->conf['controlNav'] == 2 ) {
        unset ($thumbConf['stdWrap.']);
        $thumb = t3lib_div::trimExplode('"', $this->cObj->IMAGE($thumbConf)); 
        $imageConf['stdWrap.']['addParams.']['data-thumb'] = $thumb[1];
      }
      // image-marker
      $marksItem['###IMAGE###'] = $this->cObj->IMAGE($imageConf);
      if ( $this->conf['imageLink'] ) $marksItem['###IMAGE###'] = $this->cObj->typolink($marksItem['###IMAGE###'], $linkConf);
      $marksItem['###THUMBNAIL###'] = $this->cObj->IMAGE($thumbConf);
      // number-marker
      $marksItem['###NUMBER###']  = $this->conf['sliderStyle'] == 7 ? $this->cObj->data['uid'].'-'.($key+1) : ($key+1);

  		$templateFileItem = $this->cObj->getSubpart($templateFile,'###SLIDER###');		
  		$subpartContentArray['###SLIDER###'] .= $this->cObj->substituteMarkerArrayCached($templateFileItem,$marksItem);
  		$templateFileItem = $this->cObj->getSubpart($templateFile,'###NAVIGATION###');		
  		$subpartContentArray['###NAVIGATION###'] .= $this->cObj->substituteMarkerArrayCached($templateFileItem,$marksItem);
      // pagination-marker
      if ( $this->conf['pagination'] ) {
        if ( $this->conf['pagination'] == 2 ) {
          $pagination .= '<li class="pagSelectorTitle">'. $marksItem['###TITLELINK###'] .'</li>';
        } else {
          $pagination .= '<li class="pagSelector">'. ($key+1) .'</li>';	
        }
      }
    }   
    $marks['###PAGINATION###'] = $this->conf['pagination'] ? '<ul class="navigationControl" style="width:'.$this->sliderWidth.'px; overflow:hidden;">'. $pagination .'</ul>' : ''; 
    $content = $this->cObj->substituteMarkerArrayCached($templateFile, $marks, $subpartContentArray);    

    return $content;
  }


 	protected function getGlobalMarker() {
    // navigator -setting
    $this->conf['navigatorHeight'] = $this->conf['navigatorHeight'] ? $this->conf['navigatorHeight'] : 100;
    $this->conf['navigatorWidth'] = $this->conf['navigatorWidth'] ? $this->conf['navigatorWidth'] : 310;
    $this->conf['navigatorHeight'] = $this->inRange($this->conf['navigatorHeight'], 40, 400);
    $this->conf['navigatorWidth'] = $this->inRange($this->conf['navigatorWidth'], 40, 1000);
    if ( $this->conf['sliderStyle'] == 4 ) {
      $this->conf['navigatorHeight'] = 15;
      $this->conf['navigatorWidth'] = 25;
    }
    // slider -setting
    $this->sliderWidth = $this->conf['sliderWidth'] ? $this->conf['sliderWidth'] : 700;
    $this->sliderHeight = $this->conf['sliderHeight'] ? $this->conf['sliderHeight'] : 300;
    $this->conf['maxItemDisplay'] = !$this->conf['maxItemDisplay'] ? 3 : $this->conf['maxItemDisplay'];
    if ( $this->conf['sliderStyle'] == 2 || $this->conf['sliderStyle'] == 3 || $this->conf['sliderStyle'] == 6 ) { 
      $this->sliderHeight = $this->extConf['manualSliderHeight'] ? intval($this->extConf['manualSliderHeight']) : $this->conf['maxItemDisplay'] * $this->conf['navigatorHeight'];
    }
    // image -setting
    $this->imageWidth = $this->sliderWidth;
    $this->imageHeight = $this->sliderHeight;
    if ( $this->conf['sliderStyle'] == 2 && !$this->conf['variant'] ) $this->imageWidth = $this->sliderWidth - $this->conf['navigatorWidth'] + 15;
    if ( $this->conf['sliderStyle'] == 3 && !$this->conf['variant'] ) $this->imageWidth = $this->sliderWidth - $this->conf['navigatorWidth'] + 15;
    if ( $this->variant ) $this->imageWidth = $this->sliderWidth;
    if ( $this->conf['sliderStyle'] == 8 ) {
      if ($this->conf['topBottomNav']) {
        $nWidth = intval($this->sliderWidth / $this->conf['maxItemDisplay']);
        $this->sliderWidth = $nWidth * $this->conf['maxItemDisplay'];
        $this->conf['navigatorWidth'] = $nWidth;
        $this->imageWidth = $this->sliderWidth;
      } else {
        $this->sliderHeight = $this->conf['progressbar'] ? $this->imageHeight + $this->conf['navigatorHeight'] +2 : $this->imageHeight + $this->conf['navigatorHeight'];
        $nWidth = intval($this->sliderWidth / $this->conf['maxItemDisplay']);
        $this->sliderWidth = $nWidth * $this->conf['maxItemDisplay'];
        $this->conf['navigatorWidth'] = $nWidth;
        $this->imageWidth = $this->sliderWidth;
      }
    }
    if ( $this->conf['sliderStyle'] == 6 ) {
      $this->imageWidth = $this->sliderWidth - $this->conf['navigatorWidth'] - 30;
      $this->imageHeight = $this->conf['imageSixHeight'] ? $this->conf['imageSixHeight'] : 190;
    }
    if( $this->extConf['manualImageWidth'] ) $this->imageWidth = intval($this->extConf['manualImageWidth']);
    if( $this->extConf['manualImageHeight'] ) $this->imageHeight = intval($this->extConf['manualImageHeight']);
    // textarea -setting
    if ( $this->conf['textareaWidth'] ) {
      $this->conf['textareaWidth'] = intval($this->conf['textareaWidth']);
    } else {
      $this->conf['textareaWidth'] = 350;
      // 20 = 2 x 10px padding in css
      if ( $this->conf['sliderStyle'] == 5 ) $this->conf['textareaWidth'] = $this->sliderWidth - 20;
      if ( $this->conf['sliderStyle'] == 7 ) $this->conf['textareaWidth'] = $this->sliderWidth;
      if ( $this->variant ) $this->conf['textareaWidth'] = 200;
    }
    // thumbnail -setting
    $this->conf['thumbnailSize'] = $this->conf['thumbnailSize'] ? $this->conf['thumbnailSize'] : 60;
    $this->conf['thumbnailWidth'] = $this->inRange($this->conf['thumbnailSize'], 20, $this->conf['navigatorHeight'] - 14);
    $this->conf['thumbnailHeight'] = $this->conf['thumbnailWidth'];
    if ( $this->conf['sliderStyle'] == 1 ) {
      $this->conf['proportion'] = $this->conf['proportion'] ? $this->conf['proportion'] : 10;
      $this->conf['thumbnailWidth']  = intval($this->imageWidth / $this->conf['proportion']);
      $this->conf['thumbnailHeight']  = intval($this->imageHeight / $this->conf['proportion']);
      $this->conf['navigatorWidth'] = $this->conf['thumbnailWidth'] + 10;
      $this->conf['navigatorHeight'] = $this->conf['verticalNav'] ? $this->conf['thumbnailHeight'] + 9 : $this->conf['thumbnailHeight'] + 6;
    }
    if ( $this->conf['sliderStyle'] == 7 ) {
      $this->conf['thumbnailWidth']  = $this->conf['nivoThumbnailWidth'] ? intval($this->conf['nivoThumbnailWidth']) : 70;
      $this->conf['thumbnailHeight']  = $this->conf['nivoThumbnailHeight'] ? intval($this->conf['nivoThumbnailHeight']) : 50;
    }
    // textarea offset -setting
    if ( $this->conf['textareaOffsetBottom'] ) {
      $this->conf['textareaOffsetBottom'] = $this->conf['textareaOffsetBottom'];
    } else {
      $this->conf['textareaOffsetBottom'] = 50;
      if ($this->conf['sliderStyle'] == 5 || $this->conf['sliderStyle'] == 7) $this->conf['textareaOffsetBottom'] = 0;
    }
    if ($this->conf['textareaOffsetLeft']) {
      $this->conf['textareaOffsetLeft'] = $this->conf['textareaOffsetLeft'];
    } else {
      $this->conf['textareaOffsetLeft'] = 0;
      if ($this->conf['sliderStyle'] == 3) $this->conf['textareaOffsetLeft'] = 20;
    }
    if ($this->conf['textareaOffsetTop']) {
      $this->conf['textareaOffsetTop'] = $this->conf['textareaOffsetTop'];
    } else {
      $this->conf['textareaOffsetTop'] = 150;
      if ($this->variant) $this->conf['textareaOffsetTop'] = 0;
      $this->conf['marginTop'] = intval(($this->conf['navigatorHeight'] - $this->conf['thumbnailHeight']) / 2 - 3);
    }
    // marker
    $marks = array();
    $marks['###CEID###'] = $this->cObj->data['uid'];
    if ( $this->conf['sliderStyle'] == 7 ) {
      $marks['###PAUSEBUTTON###'] = ( !$this->conf['manualAdvance'] && $this->conf['pauseNivo'] == 2 ) ? '<div class="button-control"><span>&nbsp;</span></div>' : '';
      $marks['###PROGRESSBAR###'] = $this->conf['progressbar'] ? '<div class="progressbar" style="bottom:-'.($this->sliderHeight + 2).'px;">&nbsp;</div>' : '';
    } else {
      $marks['###PAUSEBUTTON###'] = ( $this->conf['auto'] && $this->conf['pause'] == 2 ) ? '<div class="button-control button-control'.$this->cObj->data['uid'].'"><span>&nbsp;</span></div>' : '';
      $marks['###PROGRESSBAR###'] = $this->conf['progressbar'] ? '<div class="progressbar progressbar'.$this->cObj->data['uid'].'">&nbsp;</div>' : '';
    }
      
    $marks['###SLIDERWIDTH###'] = $this->sliderWidth;
    $marks['###SLIDERHEIGHT###'] = $this->sliderHeight;
    $marks['###IMAGEHEIGHT###'] = $this->imageHeight;
    $marks['###IMAGEWIDTH###'] = $this->conf['sliderStyle'] == 6 ? $this->imageWidth +30 : $this->imageWidth;    
    $marks['###IMAGEMARGIN###'] = 0;
    if ( $this->conf['sliderStyle'] == 3 ) {
      $marks['###IMAGEMARGIN###'] = $this->conf['variant'] ? 0 : $this->conf['navigatorWidth'] - 15;
    }
    $marks['###CSS3###'] = $this->conf['css3'] ? 'lof-css3' : '';
    $this->conf['activeNavBg'] = !$this->conf['activeNavBg'] ? 'green' : $this->conf['activeNavBg'];
    $marks['###ACTNAVCOLOR###'] = $this->conf['activeNavBg'];
    $marks['###ARROW-STYLE###'] = '';
    $marks['###NAVIGATOR-BG###'] = '';
    $marks['###NAV-POSITION###'] = '';
    if( $this->conf['sliderStyle'] == 1 ) {
      $marks['###ARROW-STYLE###'] = $this->conf['verticalNav'] ? 'height:22px; width:'.($this->conf['thumbnailWidth'] + 10).'px;' : 'height:'.($this->conf['thumbnailHeight'] + 6).'px; width:22px;';
      if ($this->conf['noArrows']) $marks['###ARROW-STYLE###'] = '';
      $this->conf['textareaBg'] = !$this->conf['textareaBg'] ? 'black' : $this->conf['textareaBg'];
      $marks['###NAVIGATOR-BG###'] = 'lof-navigator-wapper_'.$this->conf['textareaBg'];
      $marks['###NAV-POSITION###'] = $this->conf['verticalNav'] ? 'lof-vertical' : 'lof-horizontal';
    }
    if( $this->conf['sliderStyle'] == 8 ) {
    $marks['###NAV-POSITION###'] = $this->conf['topBottomNav'] ? 'lof-top' : 'lof-bottom';
    }
    return $marks;
  }


 	protected function getDefaultImages() {
 	  $defaultImages = array();
    if ( $this->DAM ) {
     if ( $this->conf['defaultImageDAM'] ) {
        $fields = tx_dam_db::getMetaInfoFieldList();
        $fields .= ',tx_dam.caption,tx_dam.description,tx_dam.abstract,tx_dam.creator,tx_dam.publisher,tx_dam.copyright';
      	$defaultDamFiles = tx_dam_db::getReferencedFiles('tt_content', $this->cObj->data['uid'], 'tx_t3sjslidernews_images','',$fields);
        $defaultImages['files'] = array_merge($defaultDamFiles['files']);    
        $defaultImages['rows'] = array_merge($defaultDamFiles['rows']);
      } elseif ( $this->conf['defaultImage'] ) {
        $defaultImages['files'] = t3lib_div::trimExplode(',', $this->conf['defaultImage']);
      } else {
        $defaultImages['files'] = '';
      }
    } else {
      $defaultImages['files'] = $this->conf['defaultImage'] ? t3lib_div::trimExplode(',', $this->conf['defaultImage']) : '';
    }  
    return $defaultImages; 
  }


  protected function checkVideo( $mmFile ) {
    // check youtube
    if(strpos($mmFile,'watch?v=')!==false) {
      $record['mmFile'] = t3lib_div::trimExplode('watch?v=', $mmFile);
      $record['mmFile'] = 'http://youtube.com/v/'.$record['mmFile'][1];
    }
    // check vimeo
    if ( strpos($mmFile,'vimeo.com')!==false && strpos($mmFile,'player.vimeo.com')===false ) {
      $record['mmFile'] = t3lib_div::trimExplode('vimeo.com/', $mmFile);
      $record['mmFile'] = 'player.vimeo.com/video/'.$record['mmFile'][1];
    }
    return $record['mmFile'];
  }


 	protected function textConversion( $record=array() ) {      
    if ( $this->conf['contentStyle'] == 'tt_content' ) {
      $record['header'] = trim($record['header']);
      $record['header'] = $record['header'] ? htmlspecialchars($record['header']) : '';
      $record['subheader'] = trim($record['subheader']);
      $record['subheader'] = $record['subheader'] ? htmlspecialchars($record['subheader']) : '';
    } else {
      $record['title'] = trim($record['title']);
      $record['title'] = $record['title'] ? htmlspecialchars($record['title']) : '';
    }
    if ( $this->conf['contentStyle'] == 'tt_news' ) {
      $record['short'] = trim($record['short']);
      $record['short'] = $record['short'] ? t3lib_div::formatForTextarea($record['short']): '';
      $record['author_email'] = trim($record['author_email']);
      $record['author_email'] = t3lib_div::validEmail($record['author_email']) ? $record['author_email'] : '';       
      $record['author'] = trim($record['author']);
    }
    if ( $this->conf['contentStyle'] == 'pages' ) {
      $record['subtitle'] = trim($record['subtitle']);
      $record['subtitle'] = $record['subtitle'] ? htmlspecialchars($record['subtitle']) : '';
      $record['abstract'] = trim($record['abstract']);
      $record['abstract'] = $record['abstract'] ? t3lib_div::formatForTextarea($record['abstract']) : '';       
    } else {
      if ( $this->extConf['rteRendering'] ) {
        require_once(PATH_t3lib.'class.t3lib_parsehtml_proc.php');
        $bodytext_parsehtml_proc = t3lib_div::makeInstance('t3lib_parsehtml_proc');
  	    $record['bodytext'] = $bodytext_parsehtml_proc->TS_links_rte($record['bodytext']);
      } else {
  		  $record['bodytext'] = $this->pi_RTEcssText($record['bodytext']);    
      } 
    }

    return $record;
  }


 	protected function imageConf( $record=array(), $key, $randbefore, $v ) {
    $defaultImages = $this->getDefaultImages();
    $imageScaleModifier = ($this->extConf['imageResize']=='maximum' ? 'm' : ($this->extConf['imageResize']=='scale'?'':'c'));
    $thumbScaleModifier = ($this->extConf['thumbResize']=='maximum' ? 'm' : ($this->extConf['thumbResize']=='scale'?'':'c'));
    $dir = $this->conf['contentStyle'] == 'pages' ? 'media' : 'pics';
    $uploadDir = $this->DAM ? '' : 'uploads/'.$dir.'/';
    // images for videos
    if ( $record['CType'] == 'media' || $record['tx_rgmediaimages_config'] ) {
      if ($this->extConf['videoImages']) {
        $videoImages = t3lib_div::trimExplode(',', $this->extConf['videoImages']);
        for ($n=0; $n<count($videoImages); $n++) 
        $imageConf['file'] = $videoImages[$v];
        $v++; 
      } else {
        $record['image'] = t3lib_div::trimExplode(',', $record['image']);
        $imageConf['file'] = $record['image'] ? $uploadDir . $record['image'][0] : '';
      }
    } else {
      // ... only default images - no images from the tt_news-records
      if ( $this->conf['requireImage'] == 1 ) $record['image'] = '';
      $record['image'] = t3lib_div::trimExplode(',', $record['image']); 
      if ( !$this->DAM && t3lib_extMgm::isLoaded('rgmediaimages') ) {
        foreach ( $record['image'] as $k=>$recimg ) {
          if ( strpos($recimg,'.flv')!==false || strpos($recimg,'.pdf')!==false || strpos($recimg,'.ai')!==false || strpos($recimg,'.swf')!==false || strpos($recimg,'.mp3')!==false || strpos($recimg,'.rgg')!==false ) {
            $record['image'][$k] = '';
          }
        }
      }
      $imageConf['file'] = $record['image'] ? $uploadDir . $record['image'][0] : '';
    }
    // add a default image to records without an image
    if ( !$imageConf['file'] || $imageConf['file'] == 'uploads/pics/' || $imageConf['file'] == 'uploads/media/' ) {
      if ( $defaultImages['files'] ) {
        $uploadDir = $this->DAM || $this->noPath ? '' : 'uploads/tx_t3sjslidernews/';
        $imgCount = count($defaultImages['files']);
        if ( $imgCount == 1 ) {
          $record['image'] = $defaultImages['files'][0];
          $imageConf['file'] = $uploadDir . $defaultImages['files'][0];
          if ( $this->DAM ) $record = $this->getDefaultImagesDamData($record,$defaultImages,0);
        } else {
          if ( $this->conf['imageOrder']) {
            $imageConf['file'] = $uploadDir . $defaultImages['files'][$key];
            if ( $this->DAM ) $record = $this->getDefaultImagesDamData($record,$defaultImages,$key);
          } else {
            $random = mt_rand(1, $imgCount);
            if ( $random == $randbefore ) $random = $random == 1 ? $random + 1 : $random - 1;
            $randbefore = $random;
            $rKey = $random - 1;
            $record['image'] = $defaultImages['files'][$rKey];
            $imageConf['file'] = $uploadDir . $defaultImages['files'][$rKey];
            if ( $this->DAM ) $record = $this->getDefaultImagesDamData($record,$defaultImages,$rKey);
          }
        }
      } else {
        $imageConf['file'] = $this->extPath.'res/icons/typo3_logo.jpg';
      }
    }
    $thumbConf['file'] = $imageConf['file'];
    // image configuration - size & params
    if ( $this->extConf['manualImageWidth'] ) {
      $imageConf['file.']['width'] = intval($this->extConf['manualImageWidth']).$imageScaleModifier;
    } else {
     $imageConf['file.']['width'] = $this->variant ? $this->sliderWidth.$imageScaleModifier : $this->imageWidth.$imageScaleModifier; 
    }
    $imageConf['file.']['height'] = $this->extConf['manualImageHeight'] ? $this->extConf['manualImageHeight'].$imageScaleModifier : $this->imageHeight.$imageScaleModifier;
    // alt- and title-tags
    if ( $this->conf['contentStyle'] == 'tt_news' ) { 
      $altText = $record['imagealttext'];
      $titleText = $record['imagetitletext'];
    }
    if ( $this->conf['contentStyle'] == 'tt_content' ) { 
      $altText = $record['altText'];
      $titleText = $record['titleText'];
    }
    if ( $this->conf['contentStyle'] == 'pages' ) { 
      $altText = '';
      $titleText = '';
    }
    if ( $this->conf['contentStyle'] == 'tt_news' || $this->conf['contentStyle'] == 'tt_content' ) {
      $altText = nl2br($altText);
      $altRows = substr_count($altText, '<br />')+1;
      $altText = explode("\n", $altText, $altRows);
      $altText = strip_tags($altText['0']);
      $titleText = nl2br($titleText);
      $titleRows = substr_count($titleText, '<br />')+1;
      $titleText = explode("\n", $titleText, $titleRows);
      $titleText = strip_tags($titleText['0']);
    }
    // 7 = nivo-slider
    $imageConf['titleText'] = $this->conf['sliderStyle'] == 7 ? '#caption-'.$this->cObj->data['uid'].'-'.($key+1) : trim($titleText);
    $imageConf['altText'] = trim($altText);
    // thumbnails
    $thumbConf['file.']['height'] = $this->conf['thumbnailHeight'].$thumbScaleModifier;
    $thumbConf['file.']['width'] = $this->conf['thumbnailWidth'].$thumbScaleModifier;

    $thumbConf['stdWrap.']['addParams.']['style'] = $this->variant ? 'margin:'.$this->conf['marginTop'].'px 17px 0 17px' : 'margin:'.$this->conf['marginTop'].'px '.$this->conf['marginTop'].'px 0 0';

    return array($record, $imageConf, $thumbConf, $randbefore, $v);
  }


 	protected function getDefaultImagesDamData( $record=array(), $defaultImages=array(), $rKey ) {
    $record['dam_title'] = trim($defaultImages['rows'][$rKey]['title']);
    $record['dam_caption'] = trim($defaultImages['rows'][$rKey]['caption']);
    $record['dam_description'] = trim($defaultImages['rows'][$rKey]['description']);
    $record['dam_abstract'] = trim($defaultImages['rows'][$rKey]['abstract']);
    $record['dam_creator'] = trim($defaultImages['rows'][$rKey]['creator']);
    $record['dam_publisher'] = trim($defaultImages['rows'][$rKey]['publisher']);
    $record['dam_copyright'] = trim($defaultImages['rows'][$rKey]['copyright']); 

    return $record;
  }


 	protected function getRecordMarker( $record=array(), $linkConf=array() ) {
    // title-marker
    $marksItem['###TITLE###'] = $this->conf['contentStyle'] == 'tt_content' ? $record['header'] : $record['title'];
    $marksItem['###TITLELINK###']  = $this->cObj->typolink($marksItem['###TITLE###'], $linkConf);
    // text-marker
    $this->conf['fixedLgd'] = $this->conf['fixedLgd'] ? $this->conf['fixedLgd'] : 120;
    $fixedLgdpParameter = $this->conf['fixedLgd'] . '|...|1';
    if ( $record['short'] ) {   
	    $count = strlen($record['short']) - 3;
      if ( $count >= $this->conf['fixedLgd'] ) {
        $record['short'] = $this->cObj->crop(strip_tags($record['short']),$fixedLgdpParameter);
        $marksItem['###READMORE###']  = $this->cObj->typolink($this->pi_getLL('readmore'), $linkConf);
      } else {
        $marksItem['###READMORE###'] = $record['bodytext'] ? $this->cObj->typolink($this->pi_getLL('readmore'), $linkConf) : '';
      }
      $marksItem['###TEXT###'] = $record['short']; 

      if ( $this->extConf['readmoreInternal'] && ( $record['ext_url'] || $record['page'] ) ) {
        $marksItem['###READMORE###'] = $this->cObj->typolink($this->pi_getLL('readmore'), $linkConf);    
      }
    } elseif ( $record['bodytext'] ) {
    	$count = strlen($record['bodytext']) -3;        
    	if ( $count >= $this->conf['fixedLgd'] ) {
        $record['bodytext'] = $this->extConf['crop'] ? $this->cObj->cropHTML($record['bodytext'],$fixedLgdpParameter) : $this->cObj->crop(strip_tags($record['bodytext']),$fixedLgdpParameter);
        $marksItem['###READMORE###']  = $this->cObj->typolink($this->pi_getLL('readmore'), $linkConf);
    	} else {
       $marksItem['###READMORE###']  = '';
    	}
	    $marksItem['###TEXT###'] = $record['bodytext'];      
    }  elseif ( $record['abstract'] ) {
    	$count = strlen($record['abstract']);        
    	if ( $count >= $this->conf['fixedLgd'] ) {
				$record['abstract'] = $this->cObj->crop(strip_tags($record['abstract']),$fixedLgdpParameter);       
        $marksItem['###READMORE###']  = $this->cObj->typolink($this->pi_getLL('readmore'), $linkConf);
    	} else {
       $marksItem['###READMORE###']  = '';
    	}
	    $marksItem['###TEXT###'] = $record['abstract'];       
    } elseif ( $this->extConf['readmoreInternal'] && (  $record['ext_url'] || $record['page'] ) ) {
      $marksItem['###READMORE###'] = $this->cObj->typolink($this->pi_getLL('readmore'), $linkConf);    
    } else {
    	$marksItem['###TEXT###'] = ''; 
      $marksItem['###READMORE###']  = '';
    }
    // date-marker
    $dateField = 'datetime';
    if ( $this->conf['contentStyle'] == 'tt_content' ) $dateField = 'date';
    if ( $this->conf['contentStyle'] == 'pages' )      $dateField = 'lastUpdated';
    $this->conf['dateFormat'] = !$this->conf['dateFormat'] ? '%A %d.%m.%Y - %H:%M' : $this->conf['dateFormat'];
    if ( $GLOBALS['TSFE']->tmpl->setup['config.']['renderCharset'] == 'utf-8' ) {
      $marksItem['###DATE###'] = $this->extConf['disableUtf8Encode'] ? '<span class="date">'.strftime($this->conf['dateFormat'],$record[$dateField]).'</span>' : '<span class="date">'. utf8_encode(strftime($this->conf['dateFormat'],$record[$dateField])).'</span>'; 
    } else {
      $marksItem['###DATE###'] = '<span class="date">'.strftime($this->conf['dateFormat'],$record[$dateField]).'</span>';
    }
    $marksItem['###DATE_ICON###'] = '<span class="date_icon">'.strftime($this->conf['dateFormat'],$record[$dateField]).'</span>';
    // category-marker
    if ( $this->conf['contentStyle'] == 'tt_news' ) {
      $record['catTitleList'] = rtrim(trim($record['catTitleList']), ',');
      if ( $record['catTitleList'] ) {
            $marksItem['###CATEGORY###'] = '<span class="category">'.$this->pi_getLL('category').': '.$record['catTitleList'].'</span>';
            $marksItem['###CATEGORY_ICON###']  = '<span class="category_icon">'.$record['catTitleList'].'</span>';
      } else {
            $marksItem['###CATEGORY###'] = '';
            $marksItem['###CATEGORY_ICON###']  = '';
      }
    }
    // other marker
    $marksItem['###DESCRIPTION-STYLE###'] = 'width:'.$this->conf['textareaWidth'].'px; top:'.$this->conf['textareaOffsetTop'].'px; left:'.$this->conf['textareaOffsetLeft'].'px;';
    if($this->conf['sliderStyle'] == 1 || $this->conf['sliderStyle'] == 4 || $this->conf['sliderStyle'] == 5) {
      $marksItem['###DESCRIPTION-STYLE###'] = 'width:'.$this->conf['textareaWidth'].'px; bottom:'.$this->conf['textareaOffsetBottom'].'px; left:'.$this->conf['textareaOffsetLeft'].'px;';
    }
    if ( $this->extConf['hideDescription'] && $this->conf['sliderStyle'] != 7 ) { 
      $hideDescription = $this->extConf['hideDescription'] == 1 ? 'TITLE' : 'TEXT'; 
      if ( !$marksItem['###'.$hideDescription.'###'] ) { 
        $marksItem['###DESCRIPTION-STYLE###'] = 'display:none;';
      }
    }
    if( $this->conf['sliderStyle'] == 6 ) $marksItem['###DESCRIPTION-STYLE###'] = 'width:'.$this->imageWidth.'px;';
    if( $this->conf['sliderStyle'] == 2 && $this->conf['variant'] ) $marksItem['###DESCRIPTION-STYLE###'] = 'width:'.$this->conf['textareaWidth'].'px; top:0px; left:0px;';
    if( $this->conf['sliderStyle'] == 3 && $this->conf['variant'] ) $marksItem['###DESCRIPTION-STYLE###'] = 'width:'.$this->conf['textareaWidth'].'px; top:0px; right:0px;';
    if ( $this->extConf['hideVideoDescription'] && ( $record['CType'] == 'media' || $record['tx_rgmediaimages_config'] ) && $this->conf['sliderStyle'] != 7 ) {
      $marksItem['###DESCRIPTION-STYLE###'] = 'display:none;';
    }
    $this->conf['textareaBg'] = !$this->conf['textareaBg'] ? 'black' : $this->conf['textareaBg'];
    $marksItem['###DESCRIPTION-BG###'] = 'lof-description_'.$this->conf['textareaBg'];
    $paddingLeft = $this->inRange($this->conf['marginTop'], '10', '100');
    $marksItem['###NAVSELECTOR-STYLE###'] = 'padding-left: '.$paddingLeft.'px;';
    if( $this->conf['sliderStyle'] == 2 && $this->conf['variant'] ) $marksItem['###NAVSELECTOR-STYLE###'] = 'padding-left:0';
    $this->conf['fontUnit'] = !$this->conf['fontUnit'] ? '%'  : $this->conf['fontUnit'];
    $marksItem['###HEADER-STYLE###'] = $this->conf['fontSize'] ? 'font-size:'.intval($this->conf['fontSize']).$this->conf['fontUnit'].';' : 'font-size:120%;';
    // dam-marker
    if ( $this->DAM ) {
    	$marksItem['###DAM_TITLE###'] =  $record['dam_title']; 
    	$marksItem['###DAM_DESCRIPTION###'] =  $record['dam_description'];
    	$marksItem['###DAM_CAPTION###'] =  $record['dam_caption'];
    	$marksItem['###DAM_ABSTRACT###'] =  $record['dam_abstract'];
    	$marksItem['###DAM_CREATOR###'] =  $record['dam_creator'];
    	$marksItem['###DAM_PUBLISHER###'] =  $record['dam_publisher'];
    	$marksItem['###DAM_COPYRIGHT###'] =  $record['dam_copyright'];
    }
    // own-marker
    if ( $this->extConf['ownMarker_1'] || $this->extConf['ownMarker_2'] ) {
      $this->extConf['ownMarker_1'] = ltrim($this->extConf['ownMarker_1'], $this->conf['contentStyle'].'.');
      $this->extConf['ownMarker_2'] = ltrim($this->extConf['ownMarker_2'], $this->conf['contentStyle'].'.');
      $marksItem['###OWN_MARKER_ONE###'] = $record[$this->extConf['ownMarker_1']] ? $record[$this->extConf['ownMarker_1']] : '';
      $marksItem['###OWN_MARKER_TWO###'] = $record[$this->extConf['ownMarker_2']] ? $record[$this->extConf['ownMarker_2']] : '';
    }

    return $marksItem;
  }


  protected function getDamData( $record=array(), $table, $ref) {
    $fields = tx_dam_db::getMetaInfoFieldList();
    $fields .= ',tx_dam.caption,tx_dam.description,tx_dam.abstract,tx_dam.creator,tx_dam.publisher,tx_dam.copyright';
  	$damImgList = tx_dam_db::getReferencedFiles($table, $record['uid'], $ref,'',$fields);
    if ( $this->conf['contentStyle'] == 'tt_news' && t3lib_extMgm::isLoaded('rgmediaimagesttnews') ) {
      foreach ( $damImgList['files'] as $k=>$recimg ) {
        if ( strpos($recimg,'.flv')!==false || strpos($recimg,'.pdf')!==false || strpos($recimg,'.ai')!==false || strpos($recimg,'.swf')!==false || strpos($recimg,'.mp3')!==false || strpos($recimg,'.rgg')!==false ) {
          unset($damImgList['files'][$k]);
        }
      }
    }
  	if( count($damImgList['files']) ) {
      $record['image'] = array_shift( $damImgList['files']);
    }
    $damRows = array_shift($damImgList['rows']);
    $record['dam_title'] = $damRows['title'] ? trim($damRows['title']) : '';
    $record['dam_caption'] = $damRows['caption'] ? trim($damRows['caption']) : '';
    $record['dam_description'] = $damRows['description'] ? trim($damRows['description']) : '';
    $record['dam_abstract'] = $damRows['abstract'] ? trim($damRows['abstract']) : '';
    $record['dam_creator'] = $damRows['creator'] ? trim($damRows['creator']) : '';
    $record['dam_publisher'] = $damRows['publisher'] ? trim($damRows['publisher']) : '';
    $record['dam_copyright'] = $damRows['copyright'] ? trim($damRows['copyright']) : '';       

    return $record;
  }


  protected function getRgnewsce( $record=array() ) {
   	$where = 'uid IN('.$record['tx_rgnewsce_ce'].') AND ( CType = \'text\' OR CType = \'textpic\' OR CType = \'media\' OR CType = \'html\') AND deleted = 0 AND hidden = 0';
  	$ce_rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,header,bodytext,image,CType,pi_flexform,section_frame', 'tt_content', $where, '', ' FIELD(uid, '.$record['tx_rgnewsce_ce'] .')');
  	if ( $record['type'] == 0 || $record['type'] == 1 || $record['type'] == 2 ) {
  		foreach( $ce_rows as $ce_row ) {
        $record['CType'] = $ce_row['CType'];
        $record['pi_flexform'] = $ce_row['pi_flexform'];
        $record['section_frame'] = $ce_row['section_frame'];
        if( $this->extConf['rgnewsceText'] ) {
          $record['short'] = $ce_row['bodytext'] ? $ce_row['bodytext'] : $record['short'];
          $record['title'] = $ce_row['header'] ? $ce_row['header'] : $record['title'];
        }
        unset($ce_row['bodytext']);
        unset($ce_row['header']);
        if( t3lib_extMgm::isLoaded('dam_ttcontent') ) {
          $damData = $this->getDamData($ce_row, 'tt_content', 'tx_damttcontent_files' );
          unset($ce_row['uid']);
          unset($damData['uid']);
          $record = $damData ? t3lib_div::array_merge_recursive_overrule($record, $damData) : $record;
 				} else {
					if( strlen($ce_row['image']) ) {
            $record['image'] = t3lib_div::trimExplode(',' , $ce_row['image']);
            $record['image'] = $record['image'][0];
					}
				}
			}  
		}

    return $record;
  }


 	protected function inRange( $num,$min,$max ) {
    $range = range($min, $max);
    if ( in_array($num, $range) ) {
      $erg = $num;  
    } else {
      if ( $num <= $min ) $erg = $min;
      if ( $num >= $max ) $erg = $max;
    }
    
    return $erg; 
  }


  protected function addJquery( $pagerender ) {
    // checks if t3jquery is loaded
    if ( t3lib_extMgm::isLoaded('t3jquery') ) {
      require_once(t3lib_extMgm::extPath('t3jquery').'class.tx_t3jquery.php');
    }
    // if t3jquery is loaded and the custom Library had been created
    if ( T3JQUERY === true ) {
      tx_t3jquery::addJqJS();
    }
    if ( $this->extConf['includeJquery'] ) {
      $version = $this->extConf['jVersion'] ? $this->extConf['jVersion'] : 1;
      if ( $this->extConf['moveJsFromFooterToHeader'] ){
        $pagerender->addJsLibrary(' jQuery JavaScript Library ','https://ajax.googleapis.com/ajax/libs/jquery/'.$version.'/jquery.min.js', 'text/javascript', FALSE, FALSE, '', TRUE); 	
      } else {
        $pagerender->addJsFooterLibrary(' jQuery JavaScript Library ','https://ajax.googleapis.com/ajax/libs/jquery/'.$version.'/jquery.min.js', 'text/javascript', TRUE, FALSE, '', TRUE);
      }          
    }
  }


  protected function addHeaderParts( $pagerender, $queryNoConflict ) {
    // jQuery easing
    if( $this->extConf['includeJqueryEasing'] ) {
      if ( $this->extConf['moveJsFromFooterToHeader'] ) {
        $pagerender->addJsFile($this->extPath.'res/js/jquery.easing.js', 'text/javascript', TRUE, FALSE, '');
      } else {
        $pagerender->addJsFooterFile($this->extPath.'res/js/jquery.easing.js', 'text/javascript', TRUE, FALSE, '');
      }
    }
    // jslidernews
    if ( $this->extConf['moveJsFromFooterToHeader'] ) {
      $pagerender->addJsFile($this->extPath.'res/js/jslidernews.js', 'text/javascript', TRUE, FALSE, '');
    } else {
      $pagerender->addJsFooterFile($this->extPath.'res/js/jslidernews.js', 'text/javascript', TRUE, FALSE, '');
    }          
    // inlineJS setting
    // 2 x 15 image-margin
    if ($this->conf['sliderStyle'] == 6) $this->imageWidth = $this->imageWidth + 30;    

    $options = 'sliderId:'.$this->cObj->data['uid'].',';
    if ( $this->conf['direction'] ) $options .= 'direction:\'opacity\',';
    $this->conf['event'] = !$this->conf['event'] ? 'click' : $this->conf['event'];
    if ( $this->conf['event'] == 'mouseover' ) $options .= 'navigatorEvent:\''.$this->conf['event'].'\',';
    if ( $this->conf['interval'] && $this->conf['interval'] != 4000 ) $options .= 'interval:'.$this->conf['interval'].',';
    if ( !$this->conf['auto'] ) $options .= 'auto:false,';
    if ( $this->conf['maxItemDisplay'] != 3 ) $options .= 'maxItemDisplay:'.$this->conf['maxItemDisplay'].',';
    if ( ($this->conf['sliderStyle'] == 1 || $this->conf['sliderStyle'] == 4) && !$this->conf['verticalNav'] ) $options .= 'navPosition:\'horizontal\',';
    if ( $this->conf['sliderStyle'] == 8 ) {
      $options .= 'navPosition:\'horizontal\',';
      if ($this->conf['topBottomNav']) $options .= 'topBottom:\'top\',';  
    }
    if ( $this->conf['navigatorHeight'] != 100 ) $options .= 'navigatorHeight:'.$this->conf['navigatorHeight'].',';
    if ( $this->conf['navigatorWidth'] != 310 ) $options .= 'navigatorWidth:'.$this->conf['navigatorWidth'].',';
    if ( $this->conf['duration'] && $this->conf['duration'] != 600 ) $options .= 'duration:'.$this->conf['duration'].',';
    $this->conf['easing'] = !$this->conf['easing'] ? 'easeInOutExpo' : $this->conf['easing'];
    if ( $this->conf['easing'] != 'easeInOutQuad' ) $options .= 'easing:\''.$this->conf['easing'].'\',';    
    if ( ($this->conf['pause'] == 1) && $this->conf['auto'] ) $options .= 'pauseOnMouseOver:true,';    
    if ( ($this->conf['pause'] == 2) && $this->conf['auto'] ) $options .= 'pauseButton:true,';    
    if ( $this->conf['progressbar'] ) $options .= 'progressBar:true,';    
    if ( $this->conf['overlay'] || $this->conf['newsOverlay'] || $this->conf['videoOverlay'] ) $options .= 'colorbox:true,';
    $options .= 'mainWidth:'.$this->imageWidth;   

    $fire = 'jQuery(\'#lofslidecontent'.$this->cObj->data['uid'].'\').lofJSidernews( {'; 
    if ($this->conf['sliderStyle'] == 4) {
      $fire = 'jQueryobj = jQuery(\'#lofslidecontent'.$this->cObj->data['uid'].'\').lofJSidernews( {'; 
    }
    if ($this->conf['sliderStyle'] == 1 || $this->conf['sliderStyle'] == 5) {
      $fire = '          var buttons = { previous:jQuery(\'#lofslidecontent'.$this->cObj->data['uid'].' .lof-previous\') ,
next:jQuery(\'#lofslidecontent'.$this->cObj->data['uid'].' .lof-next\') };
jQueryobj = jQuery(\'#lofslidecontent'.$this->cObj->data['uid'].'\').lofJSidernews( {
buttons         : buttons,';  
    }
    // variant - slideOut navigation
    if ($this->variant) {
      $short1 = intval($this->conf['navigatorWidth'] - $this->conf['thumbnailHeight'] - 40);
      $short2 = intval($this->conf['navigatorWidth'] - 17);
      $short = $this->conf['variant'] == 2 ? $short2 : $short1;
      $tall = $this->conf['variant'] == 2 ? '-'.$short2 + $this->conf['thumbnailHeight'] + 17 : 0; 
      $leftRight = $this->conf['sliderStyle'] == 3 ? 'left' : 'right';
      $variantCode = 'jQuery(\'.lof-navigator-outer\').css(\''.$leftRight.'\', \'-'.$short.'px\');
jQuery(\'.lof-navigator-outer\').hoverIntent({
  over: makeTall,
  timeout: 500,
  out: makeShort
});';

      $variantCodeOut = 'function makeTall(){ jQuery(this).animate({\''.$leftRight.'\':'.$tall.'},200);}     
function makeShort(){ jQuery(this).animate({\''.$leftRight.'\':-'.$short.'},200);}';
    } 

    if( $this->extConf['includeInlineJS'] ) {
      $inlineJS = $queryNoConflict.'( function(){'.$fire.$options.'});'.$variantCode.'});'.$variantCodeOut;
    }
    // add CSS & inlineJS
    $cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : $this->extPath.'res/css/style'.$this->conf['sliderStyle'].'.css';
    if ( $this->variant ) $cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : $this->extPath.'res/css/style'.$this->conf['sliderStyle'].'v.css';
    if ( $this->conf['topBottomNav'] && $this->conf['sliderStyle'] == 8 ) $cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : $this->extPath.'res/css/style'.$this->conf['sliderStyle'].'-top.css'; 
    $pagerender->addCssFile($cssFile, $rel = 'stylesheet', $media = 'all', $title = '',	$compress = TRUE,	$forceOnTop = FALSE, $allWrap = ''); 
    if( $this->extConf['includeInlineJS'] ) {
      $name=' JavaScript for EXT '.$this->extKey.' CE-'.$this->cObj->data['uid'].' ';
      if ($this->extConf['moveInlineJsFromFooterToHeader']) {
        $pagerender->addJsInlineCode($name,$inlineJS);
      } else {
        $pagerender->addJsFooterInlineCode($name,$inlineJS);
      }
    }
    // colorbox-overlay 
    if ( $this->conf['contentStyle'] != 'pages' && ( $this->iframeOverlay || $this->inlineOverlay ) ) $this->colorboxConf($pagerender, $queryNoConflict);
  }


	protected function addNivoHeaderParts( $pagerender, $queryNoConflict ) {
    // Nivo Slider
    if ( $this->extConf['moveJsFromFooterToHeader' ]) {
      $pagerender->addJsFile($this->extPath.'res/js/jquery.nivo.slider.js', 'text/javascript', TRUE, FALSE, '');
    } else {
      $pagerender->addJsFooterFile($this->extPath.'res/js/jquery.nivo.slider.js', 'text/javascript', TRUE, FALSE, '');
    }
         
    if($this->conf['effect'])               $options = 'effect:\''.$this->conf['effect'].'\',';
    if($this->conf['slices'])               $options .= 'slices:'.$this->conf['slices'].',';
    if($this->conf['animSpeed'])            $options .= 'animSpeed:'.$this->conf['animSpeed'].',';
    if($this->conf['pauseTime'])            $options .= 'pauseTime:'.$this->conf['pauseTime'].',';
    if($this->conf['manualAdvance'])        $options .= 'manualAdvance:true,';
    if($this->conf['pauseNivo']!=1)         $options .= 'pauseOnHover:false,';
    if(!$this->conf['directionNav'])        $options .= 'directionNav:false,';
# removed in v3.1    if(!$this->conf['directionNavHide'])    $options .= 'directionNavHide:false,';
    if($this->conf['controlNav'] == 0)      $options .= 'controlNav:false,';
    if($this->conf['controlNav'] == 2)      $options .= 'controlNavThumbs:true,'; 
    if($this->conf['boxCols'])              $options .= 'boxCols:\''.$this->conf['boxCols'].'\',';
    if($this->conf['boxRows'])              $options .= 'boxRows:\''.$this->conf['boxRows'].'\',';

    $speed = $this->conf['animSpeed'] ? $this->conf['animSpeed']/2 : 250;
    $time = $this->conf['pauseTime'] ? $this->conf['pauseTime'] : 3000;
    
    $pauseButton = '';
    if ( $this->conf['pauseNivo'] == 2 && !$this->conf['manualAdvance'] ) {
      if( $this->conf['progressbar'] ) {
        $pauseButton = 'jQuery(\'.button-control\').addClass(\'action-start\');
    var timeline = jQuery(\'.progressbar\');      
    vars = jQuery(\'#nivoslider'.$this->cObj->data['uid'].'\').data(\'nivo:vars\');     
    jQuery(\'.button-control\').click(function(){ 
      timeline.stop();
      timeline.animate({width: \'0px\', opacity:\'0\'}, 0);   
      vars.stop = !vars.stop; 
      jQuery(this).toggleClass(\'action-stop action-start\');
    });
    jQuery(\'.action-stop\').click(function(){ 
      timeline.animate({width: \'100%\', opacity:\'1\'}, '.$time.' );   
    });    
    ';
      } else {
        $pauseButton = 'jQuery(\'.button-control\').addClass(\'action-start\');  
    vars = jQuery(\'#nivoslider'.$this->cObj->data['uid'].'\').data(\'nivo:vars\');     
    jQuery(\'.button-control\').click(function(){     
    vars.stop = !vars.stop;
    jQuery(this).toggleClass(\'action-stop action-start\');
    });';
      }
    }

    $myAfterLoadJS = $this->conf['myAfterLoadJS'] ? $this->conf['myAfterLoadJS'] : '';
    $progressbar = '';
    $progressbarHover = '';
    if( $this->conf['progressbar'] ) {
      $progressbar = 'var timeline = jQuery(\'.progressbar\');
    timeline.stop();
    timeline.animate({width: \'0px\', opacity:\'0\'}, 0);
    timeline.animate({width: \'100%\', opacity:\'1\'}, '.$time.' );    
    vars = jQuery(\'#nivoslider'.$this->cObj->data['uid'].'\').data(\'nivo:vars\');
    if (vars.stop) timeline.stop().css({width:\'0\',opacity:1},0);
    ';
    $progressbarHover = 'jQuery(\'#nivoslider'.$this->cObj->data['uid'].'\').hover(function(){
        timeline.stop();
        timeline.animate({width: \'0px\', opacity:\'0\'}, 0);
    }, function(){
    timeline.animate({width: \'100%\', opacity:\'1\'}, '.$time.' );
    }
    );';
    }
    if( $this->conf['pauseNivo']!=1 ) $progressbarHover = '';
    
    $options .= 'afterLoad: function(){
      '.$progressbar.$progressbarHover.$pauseButton.$myAfterLoadJS.'
    },';
    $options .= 'beforeChange: function(){
      jQuery(\'.nivo-caption\').fadeOut('.$speed.');
      '.$progressbar.' 
    },';
    $options .= 'afterChange: function(){
      jQuery(\'.nivo-caption\').fadeIn('.$speed.');
    },';
    $options = rtrim($options, ',');

    $name=' JavaScript for EXT '.$this->extKey.' ';
    if( $this->extConf['includeInlineJS'] ) {
      if ($options) {
        $inlineJs = $queryNoConflict.'(window).load(function() {jQuery(\'#nivoslider'.$this->cObj->data['uid'].'\').nivoSlider({'.$options.'});});';
      } else {
        $inlineJs = $queryNoConflict.'(window).load(function() {jQuery(\'#nivoslider'.$this->cObj->data['uid'].'\').nivoSlider();});';
      }
    }

    $cssFile = $this->conf['cssFile'] ? $this->conf['cssFile'] : $this->extPath.'res/css/nivo.css';

    $pagerender->addCssFile($cssFile, $rel = 'stylesheet', $media = 'all', $title = '',	$compress = TRUE,	$forceOnTop = FALSE, $allWrap = ''); 
    if ( $this->extConf['includeInlineJS'] ) {
      $name=' JavaScript for EXT '.$this->extKey.' CE-'.$this->cObj->data['uid'].' ';
      if ($this->extConf['moveInlineJsFromFooterToHeader']){
        $pagerender->addJsInlineCode($name,$inlineJs);
      } else {
        $pagerender->addJsFooterInlineCode($name,$inlineJs);
      }
    }      
      
  }


  protected function colorboxConf( $pagerender, $queryNoConflict ) {
    // jQuery colorbox
    if( $this->extConf['includeColorboxJS'] == 1 ) {
      if ( $this->extConf['moveJsFromFooterToHeader'] ) {
        $pagerender->addJsFile($this->extPath.'res/js/jquery.colorbox.js', 'text/javascript', TRUE, FALSE, '');
      } else {
        $pagerender->addJsFooterFile($this->extPath.'res/js/jquery.colorbox.js', 'text/javascript', TRUE, FALSE, '');
      }
    }
  	    $settings = '';    
    $this->extConf['opacity'] = '0.'.$this->extConf['opacity'];
    if ( $this->extConf['opacity'] != 0.85 ) $settings = 'opacity:'.$this->extConf['opacity'].',';
    if ( $this->extConf['transition'] != 'elastic' ) $settings .= 'transition:\''.$this->extConf['transition'].'\',';
    if ( $this->extConf['speed'] != 350 ) $settings .= 'speed:'.$this->extConf['speed'].',';
    if ( $settings ) $settings = ','.rtrim($settings,',');

    if ( $this->iframeOverlay ) {
      $inlineVideo = $queryNoConflict.'( function(){
        jQuery(\'.iframe-overlay'.$this->cObj->data['uid'].'\').colorbox({iframe:true, escKey:false, innerWidth:'.$this->extConf['overlayInnerWidth'].', innerHeight:'.$this->extConf['overlayInnerHeight'].$settings.'});
      });';
    }
    if ( $this->inlineOverlay ) {
      $current = $this->extConf['current'] ? ', current:\'{current} / {total}\'' : '';    
      $gallery = $this->conf['gallery'] ? ', rel:\'inline-overlay'.$this->cObj->data['uid'].'\''.$current : '';
      $inlineOverlay = $queryNoConflict.'( function(){
      	jQuery(\'.inline-overlay'.$this->cObj->data['uid'].'\').colorbox({inline:true, escKey:false, width:\''.$this->extConf['overlayWidth'].'\''.$settings.$gallery.'});
      });';
    }  
    if ( $this->extConf['includeColorboxCSS'] ) 
      $pagerender->addCssFile($this->extPath.'res/css/colorbox.css', $rel = 'stylesheet', $media = 'all', $title = '',	$compress = TRUE,	$forceOnTop = FALSE, $allWrap = ''); 
    if( $this->extConf['includeInlineJS'] ) {
      $name=' JavaScript for EXT '.$this->extKey.' CE-'.$this->cObj->data['uid'].' ';
      if ($this->extConf['moveInlineJsFromFooterToHeader']){
        if ( $this->inlineOverlay ) $pagerender->addJsInlineCode($name.'- inline',$inlineOverlay);
        if ( $this->iframeOverlay ) $pagerender->addJsInlineCode($name.'- iframe',$inlineVideo);
      } else {
        if ( $this->inlineOverlay ) $pagerender->addJsFooterInlineCode($name.'- inline',$inlineOverlay);
        if ( $this->iframeOverlay ) $pagerender->addJsFooterInlineCode($name.'- iframe',$inlineVideo);
      }
    }      
  }


}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3s_jslidernews/pi1/class.tx_t3sjslidernews_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/t3s_jslidernews/pi1/class.tx_t3sjslidernews_pi1.php']);
}

?>