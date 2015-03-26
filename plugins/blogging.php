<?php
/*----
  PURPOSE: wiki-based blogging for w3tpl
  HISTORY:
    2012-11-02 rewriting essentially from scratch after Kate/server glitch
*/

require_once('smw-links.php');

class w3tpl_module_Blogging extends w3tpl_module_SMWLinks {
    /*----
      ACTION: show summaries for applicable blog entries
      INPUT:
	TODO - max: maximum number of entries to show
	TODO - start: starting index (i.e. which entry to start with)
	TODO - user: show only entries by this user; if NULL or not set, show all users
    */
    public function w3f_ShowBlogEntryBriefs(array $iArgs) {
    
	$w3oData = $this->Engine();
	$ar = $w3oData->GetPages_forPropVal('Page type','Blog post');

	if (is_array($ar)) {
	    $out = '';
	    $w3oPage = new Blog_Entry($this);

	    // sort by date -- show newest entries first
	    foreach ($ar as $idSMW => $arRow) {
		$strRTitle = $arRow['s_title'];		// raw title
		$intNSpace = $arRow['s_namespace'];	// namespace
		$mwoTitle = Title::newFromText($strRTitle,$intNSpace);
		$w3oPage->Use_TitleObject($mwoTitle);
		$txtDate = $w3oPage->GetPropVal('When_Posted');
		$intDate = strtotime($txtDate);
		$arSort[$intDate] = $mwoTitle;
	    }
	    krsort($arSort);

	    // display the sorted entries
	    foreach ($arSort as $intDate => $mwoTitle) {
		$w3oPage->Use_TitleObject($mwoTitle);
		$out .= $w3oPage->RenderSummary();
	    }
	    return $out;
	} else {
	    return '<i>no blog entries yet</i>';
	}
    }

    public function Engine() {
	$dbr =& wfGetDB( DB_SLAVE );
	$db = new fcDataConn_SMW($dbr);
	return $db;
    }

}

class Blog_Entry extends w3smwPage {
    public function RenderSummary() {
	$strRTitle = $this->GetPropVal('Title');	// raw title
	$strDTitle = fcDataConn_MW::VisualizeTitle($strRTitle);

	$mwoTitle = $this->MW_Object();
	$urlTitle = $mwoTitle->getFullURL();
	$htTitle = '<a href="'.$urlTitle.'">'.$strDTitle.'</a>';

	//$txtDate = $this->GetPropVal('_dat');
	$txtDate = $this->GetPropVal('When_Posted');
	$dtDate = strtotime($txtDate);
	$ftDate = date('Y/m/d',$dtDate);

	$txtAbove = $this->GetPropVal('TextAbove');	// this is the "preview"
	$htAbove = $this->PageEnv()->Parse_WikiText($txtAbove);

	$txtUser = $this->GetPropVal('User');
	$mwoUser = User::newFromName($txtUser);
	$mwoUTitle = $mwoUser->getUserPage();
	$urlUser = $mwoUTitle->getFullURL();
	$htUser = '<a href="'.$urlUser.'">'.$txtUser.'</a>';

	$out = '<h2>'.$htTitle.'</h2>'
	  .'<span class=blog-excerpt-header><span class=blog-excerpt-title>'
	  .'</span></span><span class=blog-excerpt-attrib><span class=blog-excerpt-author>by '.$htUser
	  .'</span> (<span class=blog-excerpt-timestamp>'.$ftDate.'</span>)</span>'
	  .$htAbove
	  .' <b><i><a href="'.$urlTitle.'">more...</a></i></b>';

	return $out;
    }
}

new w3tpl_module_Blogging();	// class will self-register
