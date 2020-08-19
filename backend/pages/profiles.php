<?php
/**
 * page searchindex :: profiles
 */
$oRenderer=new ressourcesrenderer($this->_sTab);

$aOptions = $this->_loadConfigfile();
// $aOptions = array('options'=>$this->getEffectiveOptions());
// TODO ?
// $aOptions['profiles'][currentid] = $this->getEffectiveProfile();
// echo '<pre>options: '.print_r($aOptions['profiles'], 1).'</pre><br>';

$sReturn = '';
$aTbl = array();
$sBtnBack='<br>'.$oRenderer->oHtml->getTag('button',array(
    'href' => '#',
    'class' => 'pure-button button-secondary',
    'onclick' => 'history.back(); return false;',
    'title' => $this->lB('button.back.hint'),
    'label' => $this->lB('button.back'),
));

// ----------------------------------------------------------------------
// handle POST DATA
// ----------------------------------------------------------------------

if(isset($_POST['action'])){
    // $sReturn.='DEBUG: <pre>POST '.print_r($_POST, 1).'</pre>';
    $aNewProfile=$_POST;
    $iProfileId=(int)$_POST['profile'];
    unset($aNewProfile['action']);
    unset($aNewProfile['profile']);
    
    switch($_POST['action']){
        case 'deleteprofile':
                $sReturn.= $oRenderer->renderMessagebox(sprintf($this->lB('profile.delete.confirm'), htmlentities($aNewProfile['label'])), 'warning')
                        .'<br><form class="" method="POST" action="?'.$_SERVER['QUERY_STRING'].'">'
                        . $oRenderer->oHtml->getTag('input', array(
                            'type'=>'hidden',
                            'name'=>'profile',
                            'value'=>$iProfileId,
                            ), false)
                        . $oRenderer->oHtml->getTag('input', array(
                            'type'=>'hidden',
                            'name'=>'label',
                            'value'=> htmlentities($aNewProfile['label']),
                            ), false)
                        .$sBtnBack
                        .' '
                        .$oRenderer->oHtml->getTag('button',array(
                            'href' => '#',
                            'class'=>'pure-button button-error',
                            'name'=>'action',
                            'label'=>$this->_getIcon('button.delete') . $this->lB('button.delete'), 
                            'value' => 'deleteprofileconfirmed',
                            
                        ))
                        .'</form>'
                        ;
                return $sReturn;
            break;;
            
        case 'deleteprofileconfirmed':
            if(!isset($aOptions['profiles'][$iProfileId])){
                $sReturn.= $oRenderer->renderMessagebox(sprintf($this->lB('profile.warning.wrongprofile'), $iProfileId), 'error')
                        .$sBtnBack;
                return $sReturn;
            }
            
            // --------------------------------------------------
            // delete data
            // --------------------------------------------------
            
            $this->flushData(array('full'), $iProfileId);
            
            // --------------------------------------------------
            // SAVE
            // --------------------------------------------------
           
            unset($aOptions['profiles'][$iProfileId]);
            // $sReturn.='<pre>new options: '.print_r($aOptions, 1).'</pre>';
            if ($this->_saveConfig($aOptions)){
                $sReturn.= $oRenderer->renderMessagebox(sprintf($this->lB('profile.delete.ok'), $aNewProfile['label']), 'ok');
                $iProfileId=false;
            } else {
                $sReturn.= $oRenderer->renderMessagebox(sprintf($this->lB('profile.delete.error'), $aNewProfile['label']), 'error');
            }
            break;;
        // set all aoptions
        case 'setprofile':
            
            // --------------------------------------------------
            // checks
            // --------------------------------------------------
            if(!$aNewProfile['label']){
                $sReturn.= $oRenderer->renderMessagebox(sprintf($this->lB('profile.warning.nolabel'), $iProfileId), 'error')
                        .$sBtnBack;
                return $sReturn;
            }

            if(!$iProfileId){
                $iProfileId=(isset($aOptions['profiles']) && is_array($aOptions['profiles']) && count($aOptions['profiles'])) 
                        ? max(array_keys($aOptions['profiles']))+1
                        : 1
                    ;
            }
            // fix array values - textareas with line by line values
            $aArrays=array(
                'searchindex'=>array('urls2crawl','include', 'includepath', 'exclude', 'regexToRemove'),
                'frontend'=>array('searchlang'),
                'ressources'=>array('blacklist'),
            );
            
            foreach($aArrays as $sIndex1=>$aSubArrays){
                foreach($aSubArrays as $sIndex2){                    
                    if(isset($aNewProfile[$sIndex1][$sIndex2]) && $aNewProfile[$sIndex1][$sIndex2]){
                        $sTaContent=$aNewProfile[$sIndex1][$sIndex2];
                        $sTaContent.=strpos($sTaContent, "\r")===false ? "\r":'';
                        $aNewProfile[$sIndex1][$sIndex2]=explode("\n", str_replace("\r", '', $sTaContent));
                    } else {
                        $aNewProfile[$sIndex1][$sIndex2]=array();
                    }
                }
            }
            // fix integer values
            $this->_configMakeInt($aNewProfile, 'searchindex.iDepth');
            $this->_configMakeInt($aNewProfile, 'searchindex.iMaxUrls');
            $this->_configMakeInt($aNewProfile, 'searchindex.simultanousRequests');
            $this->_configMakeInt($aNewProfile, 'ressources.simultanousRequests');

            // check json data in textarea
            if(isset($aNewProfile['frontend']['searchcategories']) 
                    && $aNewProfile['frontend']['searchcategories']
                    && json_decode($aNewProfile['frontend']['searchcategories'])
            ){
                $aNewProfile['frontend']['searchcategories'] = json_decode($aNewProfile['frontend']['searchcategories']);
            } else {
                $aNewProfile['frontend']['searchcategories'] = array();
            }
                    
            // --------------------------------------------------
            // new profile image
            // --------------------------------------------------
            
            if(isset($aNewProfile['profileimagedatanew']) && $aNewProfile['profileimagedatanew'] && strlen($aNewProfile['profileimagedatanew'])>100){
                
                $iMaxDimension=600;

                // source: https://stackoverflow.com/questions/45478827/resize-image-from-base64-encode
                $sPostedImg=str_replace('data:image/png;base64,', '', $aNewProfile['profileimagedatanew']);

                $sTempImage = imagecreatefromstring(base64_decode($sPostedImg));
                list($iWidthOrig, $iHeightOrig) = getimagesizefromstring(base64_decode($sPostedImg));

                if ($iWidthOrig<=$iMaxDimension && $iHeightOrig<=$iMaxDimension){
                    $sSmallImage=$sTempImage;
                } else {
                    
                    if($iWidthOrig>$iHeightOrig){
                        $iWidthNew=$iMaxDimension;
                        $iHeightNew=round($iWidthNew/$iWidthOrig*$iHeightOrig);
                    } else {
                        $iHeightNew=$iMaxDimension;
                        $iWidthNew=round($iHeightNew/$iHeightOrig*$iWidthOrig);
                    }

                    $sSmallImage = imagecreatetruecolor($iWidthNew, $iHeightNew);
                    imagecopyresampled($sSmallImage, $sTempImage, 0, 0, 0, 0, $iWidthNew, $iHeightNew, $iWidthOrig, $iHeightOrig);
                    // imagecopyresized($sSmallImage, $sTempImage, 0, 0, 0, 0, $iWidthNew, $iHeightNew, $iWidthOrig, $iHeightOrig);
                }

                ob_start();
                imagepng($sSmallImage);
                $sImageBase64 = base64_encode(ob_get_contents());
                ob_end_clean();

                $aNewProfile['profileimagedata']='data:image/png;base64,'.$sImageBase64;
            }
            unset($aNewProfile['profileimagedatanew']);
            
            // --------------------------------------------------
            // SAVE
            // --------------------------------------------------
           
            $aOptions['profiles'][$iProfileId]=$aNewProfile;
            if ($this->_saveConfig($aOptions)){
                $sReturn.= $oRenderer->renderMessagebox(sprintf($this->lB('profile.save.ok'), $aNewProfile['label']), 'ok');
                
            } else {
                $sReturn.= $oRenderer->renderMessagebox(sprintf($this->lB('profile.save.error'), $aNewProfile['label']), 'error');
            }
            break;
            ;;
        default: 
            $sReturn.= $oRenderer->renderMessagebox('ERRROR: unknown action ['.htmlentities($_POST['action']).'] :-/ skipping ... just in case', 'warning');
    }
    
    $sNextUrl=$_SERVER['QUERY_STRING'];
    $sNextUrl=preg_replace('/\&siteid=add/' , '', $sNextUrl);
    $sNextUrl=preg_replace('/\&siteid=[0-9]*/' , '', $sNextUrl);
    $sNextUrl.='&siteid='.$iProfileId;
    $sReturn.='<hr><br>'
        .$oRenderer->oHtml->getTag('a',array(
            'href' => '?'.$sNextUrl,
            'class' => 'pure-button button-secondary',
            'title' => $this->lB('button.continue.hint'),
            'label' => $this->lB('button.continue'),
        ));
    return $sReturn;
//    
}

// ----------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------

$sReturn.=$this->_getNavi2($this->_getProfiles(), true, '?page=home')
        .(!isset($_SERVER['HTTPS'])
            ? $oRenderer->renderMessagebox($this->lB('setup.error-no-ssl'), 'warning').'<br><br>'
            : ''
        )
            /*
            . ($this->_sTab==='add'
                ? '<h3>'.$this->lB('profile.new.searchprofile') . '</h3>'
                : '' 
            )
             * 
             */
            
        ;
$this->setSiteId($this->_sTab);
// $sReturn.='<pre>' . print_r($this->aProfile, 1) . '</pre>';


$sValueSearchCategories='';
if(isset($this->aProfileSaved['searchcategories']) && count($this->aProfileSaved['searchcategories'])){
    foreach($this->aProfileSaved['searchcategories'] as $sKey=>$value){
        $sValueSearchCategories.=$sKey.': "'.$value.'"' . "\n";
    }
}

$sReturn.='
        <br>
        <form class="pure-form pure-form-aligned" method="POST" action="?'.$_SERVER['QUERY_STRING'].'">
            '
            . $oRenderer->oHtml->getTag('input', array(
                'type'=>'hidden',
                'name'=>'action',
                'value'=>'setprofile',
                ), false)
            . $oRenderer->oHtml->getTag('input', array(
                'type'=>'hidden',
                'name'=>'profile',
                'value'=>$this->_sTab,
                ), false)
        
            // ------------------------------------------------------------
            // metadata
            // ------------------------------------------------------------
            
            .$oRenderer->renderExtendedView()
            . '<h3>'
                // . $oRenderer->oHtml->getTag('i', array('class'=>'fa fa-user')) 
                . ' '.$this->lB('profile.section.metadata')
            .'</h3>'
        
            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'label', 'label'=>$this->lB('profile.label')))
                . $oRenderer->oHtml->getTag('input', array(
                    'id'=>'label', 
                    'name'=>'label',
                    'value'=>isset($this->aProfileSaved['label']) ? $this->aProfileSaved['label'] : '',
                    ), false)
                . '</div>'
        
            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'description', 'label'=>$this->lB('profile.description')))
                . $oRenderer->oHtml->getTag('textarea', array(
                    'id'=>'description', 
                    'name'=>'description',
                    'cols'=>50,
                    'rows'=>3,
                    'label'=>isset($this->aProfileSaved['description']) ? $this->aProfileSaved['description'] : '',
                    ), true)
                . '</div>'

            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'profileimagedata', 'label'=>$this->lB('profile.image')))
                . '<div>'
                    . $this->getProfileImage()
                    . '</div>'
                . '</div>'
            . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array('label'=>''))
                . '<div>'
                    . $oRenderer->oHtml->getTag('input', array(
                        'type'=>'hidden', 
                        'id'=>'profileimagedata', 
                        'name'=>'profileimagedatanew', 
                        'placeholder'=>'',
                        'value'=>'',
                        ), true)
                    . '<br>'
                    . $oRenderer->oHtml->getTag('div', array(
                        'id'=>'profileimageinserter', 
                        'class'=>'insertimage', 
                        'contentEditable'=>'true',
                        'label'=>$this->lB('profile.image.add'),
                        ), true)
                    . '</div>'
                . '</div>'

            // ------------------------------------------------------------
            // search index
            // ------------------------------------------------------------
            . '<h3>'
                // . $oRenderer->oHtml->getTag('i', array('class'=>'fa fa-user')) 
                . ' '.$this->lB('profile.section.searchindex')
            .'</h3>'
        
            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'searchindex-urls2crawl', 'label'=>$this->lB('profile.searchindex.urls2crawl')))
                . $oRenderer->oHtml->getTag('textarea', array(
                    'id'=>'searchindex-urls2crawl', 
                    'name'=>'searchindex[urls2crawl]',
                    'cols'=>50,
                    'rows'=>isset($this->aProfileSaved['searchindex']['urls2crawl']) && count($this->aProfileSaved['searchindex']['urls2crawl']) ? count($this->aProfileSaved['searchindex']['urls2crawl'])+1 : 3 ,
                    'label'=>isset($this->aProfileSaved['searchindex']['urls2crawl']) && count($this->aProfileSaved['searchindex']['urls2crawl']) ? implode("\n", $this->aProfileSaved['searchindex']['urls2crawl']) : '',
                    ), true)
                . '</div>'
            . '<div class="hintextended">'.$this->lB('hint.extended').'</div>'
            . '<div class="extended">'
                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array('for'=>'searchindex-include', 'label'=>$this->lB('profile.searchindex.include')))
                    . $oRenderer->oHtml->getTag('textarea', array(
                        'id'=>'searchindex-include', 
                        'name'=>'searchindex[include]',
                        'cols'=>50,
                        'rows'=>isset($this->aProfileSaved['searchindex']['include']) && count($this->aProfileSaved['searchindex']['include']) ? count($this->aProfileSaved['searchindex']['include'])+1 : 3 ,
                        'label'=>isset($this->aProfileSaved['searchindex']['include']) && count($this->aProfileSaved['searchindex']['include']) ? implode("\n", $this->aProfileSaved['searchindex']['include']) : '',
                        ), true)
                    . '</div>'
                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array('for'=>'searchindex-includepath', 'label'=>$this->lB('profile.searchindex.includepath')))
                    . $oRenderer->oHtml->getTag('textarea', array(
                        'id'=>'searchindex-includepath', 
                        'name'=>'searchindex[includepath]',
                        'cols'=>50,
                        'rows'=>isset($this->aProfileSaved['searchindex']['includepath']) && count($this->aProfileSaved['searchindex']['includepath']) ? count($this->aProfileSaved['searchindex']['includepath'])+1 : 3 ,
                        'label'=>isset($this->aProfileSaved['searchindex']['includepath']) && count($this->aProfileSaved['searchindex']['includepath']) ? implode("\n", $this->aProfileSaved['searchindex']['includepath']) : '',
                        ), true)
                    . '</div>'

                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array('for'=>'searchindex-exclude', 'label'=>$this->lB('profile.searchindex.exclude')))
                    . $oRenderer->oHtml->getTag('textarea', array(
                        'id'=>'searchindex-exclude', 
                        'name'=>'searchindex[exclude]',
                        'cols'=>50,
                        'rows'=>isset($this->aProfileSaved['searchindex']['exclude']) && count($this->aProfileSaved['searchindex']['exclude']) ? count($this->aProfileSaved['searchindex']['exclude'])+1 : 3 ,
                        'label'=>isset($this->aProfileSaved['searchindex']['exclude']) && count($this->aProfileSaved['searchindex']['exclude']) ? implode("\n", $this->aProfileSaved['searchindex']['exclude']) : '',
                        ), true)
                    . '</div>'
            . '</div>'

            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'searchindex-iDepth', 'label'=>$this->lB('profile.searchindex.iDepth')))
                . $oRenderer->oHtml->getTag('input', array(
                    'id'=>'searchindex-iDepth', 
                    'name'=>'searchindex[iDepth]',
                    'placeholder'=>$this->aProfileDefault['searchindex']['iDepth'],
                    'value'=>isset($this->aProfileSaved['searchindex']['iDepth']) ? $this->aProfileSaved['searchindex']['iDepth'] : '',
                    ), false)
                . '</div>'
            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'userpwd', 'label'=>$this->lB('profile.userpwd')))
                . $oRenderer->oHtml->getTag('input', array(
                    'id'=>'userpwd', 
                    'name'=>'userpwd',
                    'placeholder'=>'',
                    'value'=>isset($this->aProfileSaved['userpwd']) ? $this->aProfileSaved['userpwd'] : '',
                    ), false)
                . '</div>'

            . '<div class="hintextended">'.$this->lB('hint.extended').'</div>'
            . '<div class="extended">'
                . '<div class="pure-control-group">'
                    . '<br><p>' . $this->lB('profile.overrideDefaults') . '</p>'
                . '</div>'
                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array('for'=>'searchindex-iMaxUrls', 'label'=>$this->lB('profile.searchindex.iMaxUrls')))
                    . $oRenderer->oHtml->getTag('input', array(
                        'id'=>'searchindex-iMaxUrls', 
                        'name'=>'searchindex[iMaxUrls]',
                        'placeholder'=>$this->aProfileDefault['searchindex']['iMaxUrls'],
                        'value'=>isset($this->aProfileSaved['searchindex']['iMaxUrls']) ? (int)$this->aProfileSaved['searchindex']['iMaxUrls'] : $this->aProfileDefault['searchindex']['iMaxUrls'],
                        ), false)
                    . '</div>'

                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array(
                        'for'=>'searchindex-simultanousRequests', 
                        'label'=>sprintf($this->lB('profile.searchindex.simultanousRequests'), $aOptions['options']['crawler']['searchindex']['simultanousRequests'])
                    ))
                    . $oRenderer->oHtml->getTag('input', array(
                        'id'=>'searchindex-simultanousRequests', 
                        'name'=>'searchindex[simultanousRequests]',
                        'placeholder'=>isset($aOptions['options']['crawler']['searchindex']['simultanousRequests']) ? $aOptions['options']['crawler']['searchindex']['simultanousRequests'] : '',
                        'value'=>isset($this->aProfileSaved['searchindex']['simultanousRequests']) ? $this->aProfileSaved['searchindex']['simultanousRequests'] : '',
                        ), false)
                    . '</div>'
                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array('for'=>'searchindex-regexToRemove', 'label'=>$this->lB('profile.searchindex.regexToRemove')))
                    . $oRenderer->oHtml->getTag('textarea', array(
                        'id'=>'searchindex-regexToRemove', 
                        'name'=>'searchindex[regexToRemove]',
                        'cols'=>50,
                        'placeholder'=>implode("\n", $aOptions['options']['searchindex']['regexToRemove']),
                        'rows'=>isset($this->aProfileSaved['searchindex']['regexToRemove']) && count($this->aProfileSaved['searchindex']['regexToRemove']) ? count($this->aProfileSaved['searchindex']['regexToRemove'])+1 : 3 ,
                        'label'=>isset($this->aProfileSaved['searchindex']['regexToRemove']) && count($this->aProfileSaved['searchindex']['regexToRemove']) ? implode("\n", $this->aProfileSaved['searchindex']['regexToRemove']) : '',
                        ), true)
                    . '</div>'
            . '</div>'
            // ------------------------------------------------------------
            // search frontend
            // ------------------------------------------------------------
            
            . '<h3>'
                // . $oRenderer->oHtml->getTag('i', array('class'=>'fa fa-user')) 
                . ' '.$this->lB('profile.section.frontend')
            .'</h3>'
        
            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'frontend-searchcategories', 'label'=>$this->lB('profile.frontend.searchcategories')))
                . $oRenderer->oHtml->getTag('textarea', array(
                    'id'=>'frontend-searchcategories', 
                    'name'=>'frontend[searchcategories]',
                    'cols'=>70,
                    'rows'=>isset($this->aProfileSaved['frontend']['searchcategories']) && is_array($this->aProfileSaved['frontend']['searchcategories']) && count($this->aProfileSaved['frontend']['searchcategories']) ? count($this->aProfileSaved['frontend']['searchcategories'])+3 : 3 ,
                    // 'label'=>$sValueSearchCategories,
                    'label'=> (isset($this->aProfileSaved['frontend']['searchcategories']) 
                            ? json_encode($this->aProfileSaved['frontend']['searchcategories'], JSON_PRETTY_PRINT) 
                            : ''
                        ),
                    ), true)
                . '</div>'
            . '<div class="pure-control-group">'
                . $oRenderer->oHtml->getTag('label', array('for'=>'frontend-searchlang', 'label'=>$this->lB('profile.frontend.searchlang')))
                . $oRenderer->oHtml->getTag('textarea', array(
                    'id'=>'frontend-searchlang', 
                    'name'=>'frontend[searchlang]',
                    'cols'=>50,
                    'rows'=>isset($this->aProfileSaved['frontend']['searchlang']) && count($this->aProfileSaved['frontend']['searchlang']) ? count($this->aProfileSaved['frontend']['searchlang'])+1 : 3 ,
                    'label'=>isset($this->aProfileSaved['frontend']['searchlang']) && count($this->aProfileSaved['frontend']['searchlang']) ? implode("\n", $this->aProfileSaved['frontend']['searchlang']) : '',
                    ), true)
                . '</div>'

            // ------------------------------------------------------------
            // ressources scan
            // ------------------------------------------------------------
            
                . '<h3>'
                    // . $oRenderer->oHtml->getTag('i', array('class'=>'fa fa-user')) 
                    . ' '.$this->lB('profile.section.ressources')
                .'</h3>'
            . '<div class="hintextended">'.$this->lB('hint.extended').'</div>'
            . '<div class="extended">'

                . '<div class="pure-control-group">'
                    . '<p>' . $this->lB('profile.overrideDefaults') . '</p>'
                . '</div>'
                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array(
                        'for'=>'ressources-simultanousRequests', 
                        'label'=>sprintf($this->lB('profile.ressources.simultanousRequests'), $aOptions['options']['crawler']['ressources']['simultanousRequests'])
                    ))
                    . $oRenderer->oHtml->getTag('input', array(
                        'id'=>'ressources-simultanousRequests', 
                        'name'=>'ressources[simultanousRequests]',
                        'placeholder'=>isset($aOptions['options']['crawler']['ressources']['simultanousRequests']) ? $aOptions['options']['crawler']['ressources']['simultanousRequests'] : '',
                        'value'=>isset($this->aProfileSaved['ressources']['simultanousRequests']) ? $this->aProfileSaved['ressources']['simultanousRequests'] : '',
                        ), false)
                    . '</div>'
                // blacklist
                . '<div class="pure-control-group">'
                    . $oRenderer->oHtml->getTag('label', array('for'=>'ressources-blacklist', 'label'=>$this->lB('profile.ressources.blacklist')))
                    . $oRenderer->oHtml->getTag('textarea', array(
                        'id'=>'ressources-blacklist', 
                        'name'=>'ressources[blacklist]',
                        'cols'=>50,
                        'rows'=>isset($this->aProfileSaved['ressources']['blacklist'])  && count($this->aProfileSaved['ressources']['blacklist'])  ? count($this->aProfileSaved['ressources']['blacklist'])+1      : 3 ,
                        'label'=>isset($this->aProfileSaved['ressources']['blacklist']) && count($this->aProfileSaved['ressources']['blacklist']) ? implode("\n", $this->aProfileSaved['ressources']['blacklist']) : '',
                        ), true)
                    . '</div>'
            . '</div>'

            // ------------------------------------------------------------
            // submit
            // ------------------------------------------------------------
            . '<br><hr><br>'
            . ($this->_sTab==='add'
                ? 
                    $oRenderer->oHtml->getTag('button', array('label'=>$this->_getIcon('button.create') . $this->lB('button.create'), 'class'=>'pure-button button-success'))
                : 
                    $oRenderer->oHtml->getTag('button', array('label'=>$this->_getIcon('button.save') . $this->lB('button.save'), 'class'=>'pure-button button-secondary'))
                    .' '
                    .$oRenderer->oHtml->getTag('button', array('label'=>$this->_getIcon('button.delete') . $this->lB('button.delete'), 'class'=>'pure-button button-error', 'name'=>'action', 'value'=>'deleteprofile'))
            )
        
            .'</form>'
        
            ;

/*
// foreach ($this->_getProfileConfig($this->_sTab) as $sVar => $val) {
foreach ($this->aProfile as $sVar => $val) {

    $sTdVal = '';
    if (is_array($val)){
        foreach($val as $sKey=>$subvalue){
            $sTdVal .= '<span class="key2">'.$sKey.'</span>:<br>'
                    .((is_array($subvalue)) ? ' - <span class="value">' . implode('</span><br> - <span class="value">', $subvalue) : '<span class="value">'.$subvalue.'</span>')
                    .'</span><br><br>'
                    ;                    
        }
    } else {
        $sTdVal .= (is_array($val)) ? '<span class="value">'.implode('</span><br> - <span class="value">', $val).'</span>' : '<span class="value">'.$val.'</span>';
    }

    $aTbl[] = array($this->lB("profile." . $sVar), '<span class="key">'.$sVar.'</span>', $sTdVal);
}
$sReturn.=$this->_getSimpleHtmlTable($aTbl);
 * 
 */


/*
$sReturn.='<h3>' . $this->lB('rawdata') . '</h3>'
        . '<pre>' . print_r($this->_getProfileConfig($this->_sTab), 1) . '</pre>';
;
 * 
 */
return $sReturn;
