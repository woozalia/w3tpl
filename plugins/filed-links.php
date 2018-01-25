<?php namespace w3tpl;
/*
  PURPOSE: Link-filing management functions for w3tpl
  NOTE: psycrit code expects this to be named filed-links.php, not filedLinks.php
  HISTORY:
    2011-10-16 w3tpl code started to get too ugly, so pushing out some functionality into callable modules.
    2017-12-13 this was adapted to use namespace w3tpl sometime recently (see classes/modules)
*/
$csModuleClass = 'xcModule_FiledLinks';
//new w3tpl_module_FiledLinks();	// class will self-register

class xcModule_FiledLinks extends xcModule {

/*
    private $oDB;
    protected function DB_read() {
	if (empty($this->oDB)) {
	    //$oDB = \fcApp_MW::Me()->GetDatabase();
	    $oDB = $this->GetDatabase();
	    $this->oDB = $oDB;
	}
	return $this->oDB;
    }
*/
    // TAG-CALLABLE FUNCTIONS

    /*----
      INPUT:
	"title" and "topic" are actually both category names;
	   the list of titles generated will be all pages that contain both of them.
	"topic" defaults to "Data/links"
	"xtopic" is a single topic to exclude; later I'll implement xtopics, which will be a list
    */
    protected function TagAPI_Links_forTopic(array $iArgs) {
	global $wgContLang,$wgCapitalLinks;

	//$sTitle = clsMWData::NormalizeTitle($iArgs['title'],NS_MAIN);
	$sTitle = \fcDataConn_MW::NormalizeTitle($iArgs['title'],NS_MAIN);
	$sTopic = \fcArray::Nz($iArgs,'topic','Data/links');
	$sXTopic =  \fcArray::Nz($iArgs,'xtopic');

	$db = \fcApp::Me()->GetDatabase();
	$arrTiI = $db->Titles_forTopic_arr($sTitle);	// titles having $sTitle as topic
	$arrToI = $db->Titles_forTopic_arr($sTopic);	// titles having $sTopic as topic
	$arrExc = $db->Titles_forTopic_arr($sXTopic);	// titles having xtopic

	// find intersection of TiI and ToI lists
	$arrInc = array_intersect_key($arrTiI,$arrToI);

	// remove exc titles from inc title list
	if (is_array($arrExc)) {
	    foreach ($arrExc as $id => $sTitle) {
		if (array_key_exists($id,$arrInc)) {
		    unset($arrInc[$id]);
		}
	    }
	}

	// render the results
	$out = $this->RenderStart();
	foreach ($arrInc as $id => $sTitle) {
	    if (!empty($id)) {	// this is a kluge. need to find out why array has an empty entry.
		$arProps = $db->Props_forPage_arr($id);
		$out .= $this->RenderLine($id,$arProps);
	    }
	}
	$out .= $this->RenderStop();

	return $out;

// OLD CODE STARTS HERE

	if ($wgCapitalLinks) {
	    $sTopic = ucfirst($sTopic);
	    $sXTopic = ucfirst($sXTopic);
	}
	$mwoTitle = \Title::newFromText($sTitleRaw);
	if (is_object($mwoTitle)) {
	    $sqlTitle = $mwoTitle->getDBkey();
	    //$strTopicSQL = SQLValue($strTopicRaw);
	    //$strTopicNS = $wgContLang->getNSText( NS_CATEGORY );
/*
  DATA DETAILS
    cl_from <- $idFrom is the ID of a Title containing a category tag
    cl_to is the text of the name (no namespace) of the category being linked to
*/

/* This doesn't do parameter replacement, which we need now
	    $sql = <<<__END__
SELECT
 clp.cl_from AS idFrom,
 pp.pp_propname AS prop_name,
 pp.pp_value AS prop_val
FROM ((categorylinks AS clp
 LEFT JOIN page AS p ON clp.cl_from=p.page_id)
 LEFT JOIN categorylinks AS cld ON cld.cl_from=p.page_id)
 LEFT JOIN page_props AS pp ON pp.pp_page=p.page_id
WHERE (cld.cl_to="$strTopicSQL") AND (clp.cl_to="$strTitleSQL")
ORDER BY page_title DESC;
__END__;
*/
	    // get a database connection object
	    $dbr =& wfGetDB( DB_SLAVE );

	    $arTables = array(
		'clp'		=> 'categorylinks',
		'p'		=> 'page',
		'cld'		=> 'categorylinks',
		'pp'		=> 'page_props'
		);
	    $arJoins = array(
		'p'	=> array('LEFT JOIN','clp.cl_from=p.page_id'),
		'cld'	=> array('LEFT JOIN','cld.cl_from=p.page_id'),
		'pp'	=> array('LEFT JOIN','pp.pp_page=p.page_id'),
	      );

	    // execute SQL and get data
	    $res = $dbr->select(
	      $arTables,	// tables (string or array)
	      array(		// vars (array)
		'idFrom'	=> 'clp.cl_from',
		'prop_name'	=> 'pp.pp_propname',
		'prop_val'	=> 'pp.pp_value'
		),
	      array(		// conditions
		'cld.cl_to="'.$sTopic.'"',
		'clp.cl_to="'.$sqlTitle.'"'
		),
	      __METHOD__,
	      array(		// options
		'ORDER BY'	=> 'page_title DESC',
		),
	      $arJoins		// joins (array)
	    );

	    // process the data
	    if ($dbr->numRows($res)) {
		$idLast = 0;
		$out = $this->RenderStart();
		while ( $row = $dbr->fetchObject($res) ) {
		    $idTitle = $row->idFrom;

		    if ($idTitle != $idLast) {
			$strXTopicRaw = NULL;	// what was this supposed to be?
			// TODO: either figure out a purpose for $strXTopicRaw, or gut the first half of the 'if' below.
			if (!is_null($strXTopicRaw)) {
			    $arCatgs = TopicsForTitle($idTitle);
			    $okTitle = !array_key_exists($strXTopic,$arCatgs);
			} else {
			    $okTitle = TRUE;
			}
			if ($okTitle) {
			    if ($idLast) {
				$out .= $this->RenderLine($idLast,$arProps);
			    }

			    $idLast = $idTitle;
			    $arProps = array();
			}
		    }
		    $arProps[$row->prop_name] = $row->prop_val;
		}
		// TODO: this final iteration may need further checking
		$out .= $this->RenderLine($idLast,$arProps);
		$out .= $this->RenderStop();
	    } else {
		$out = '<small>No links filed yet for topic &ldquo;'.$strTitleRaw.'&rdquo;.</small>';
	    }
	    $dbr->freeResult( $res );
	} else {
	    $out = 'Could not load requested title ['.$strTitleRaw.'].';
	}

	// tidy up
	return $out;
    }

    /*----
      PURPOSE: Mainly for inheritance, so we can easily modify what w3f_Links_forTopic() displays
      OUTPUT: starts a <ul> list
    */
    protected function RenderStart() {
	return '<ul>';
    }
    /*----
      PURPOSE: Mainly for inheritance, so we can easily modify what w3f_Links_forTopic() displays
      OUTPUT: ends a <ul> list
    */
    protected function RenderStop() {
	return '</ul>';
    }
    protected function RenderLine($idTitle,array $arProps=NULL) {
	$mwoTitle = \Title::newFromID($idTitle);

	if (is_object($mwoTitle)) {
	    $mwoTalk = $mwoTitle->getTalkPage();
	    $urlMain = $mwoTitle->getLinkUrl();
	    $urlTalk = $mwoTalk->getLinkUrl();
	    $ok = FALSE;
	    if (is_array($arProps)) {
		if (array_key_exists('data>',$arProps)) {
		    $ok = TRUE;
		    $sDate = \fcArray::Nz($arProps,'data>date');
		    $sTitle = \fcArray::Nz($arProps,'data>title');
		    if (is_null($sTitle)) {
			$htLine = 'No title found. Here is what was found:<pre>'.print_r($arProps,TRUE).'</pre>';
		    } else {
			if (array_key_exists('data>source',$arProps)) {
			    $sSource = $arProps['data>source'];
			    $htSource = ' at '.$sSource;
			} else {
			    $htSource = '';
			}

			if (array_key_exists('data>textshort',$arProps)) {
			    $wtSumm = $arProps['data>textshort'];
			} else {
			    $wtSumm = '[2]'.\fcArray::Nz($arProps,'data>text');	// this may need to be shortened somehow
			    // maybe after the first \n?
			}
			$htSumm = $this->Parse_WikiText($wtSumm);

			$htLine = '<b>'.$sDate.'</b> ';
			$htLine .= '<b>[</b><a title="discuss this article" href="'.$urlTalk.'">Talk</a>|<a title="summary and index data" href="'.$urlMain.'">Index</a><b>]</b> ';
			if (array_key_exists('data>url',$arProps)) {
			    $urlLink = $arProps['data>url'];
			    $htLine .= '<i><a title="go to the original article'.$htSource.'" href="'.$urlLink.'">'.$sTitle.'</a></i>';
			} else {
			    $htLine .= '<i>'.$sTitle.'</i>';
			}
			$htLine .= ' &sect; '.$htSumm;
		    }
		}
	    }
	    if (!$ok) {
		$sTitle = $mwoTitle->getText();
		$htLine = '<a title="summary and index data (this needs to be updated)" href="'.$urlMain.'">'.$sTitle.'</a>';
	    }
	} else {
	    $htLine = 'No page for ID='.$idTitle;
	}
	$out = "\n<li>$htLine</li>";
	return $out;
    }

    // SUB-FUNCTIONS

}

// HELPER CLASSES

// UTILITY FUNCTIONS

function Link_forPage($iPage) {
    $objTitle = Title::newFromText($iPage);
    $htLink = $objTitle->getFullURL();
    return $htLink;
}
function Link_forURLorPage($iURL,$iPage) {
    if (is_null($iPage)) {
	return $iURL;
    } else {
	return Link_forPage($iPage);
    }
}
function DBkeyToDisplay($iTitle) {
    // there's got to be a MW function somewhere that does this more thoroughly...
    return str_replace ('_',' ',$iTitle);
}
function ArrayToString(array $iList=NULL, $iSep=',') {
    if (is_array($iList)) {
	$str = implode($iSep,$iList);
    } else {
	$str = NULL;
    }
    return $str;
}
/*----
  RETURNS: array of categories (by name) assigned to the given title
    array[category name] = row from categorylinks
  INPUT: iTitle = ID of the title to check
  HISTORY:
    2012-07-13 Adapted from code in Title::getParent() (Title.php), MW 1.19.1
*/
function TopicsForTitle($iTitle) {
    $dbr = wfGetDB( DB_SLAVE );

    $res = $dbr->select( 'categorylinks', '*',
	    array(
		    'cl_from' => $iTitle,
	    ),
	    __METHOD__,
	    array()
    );

    $arOut = NULL;
    if ( $dbr->numRows( $res ) > 0 ) {
	foreach ( $res as $row ) {
	    $arOut[$row->cl_to] = $row;
	}
    }
    return $arOut;
}