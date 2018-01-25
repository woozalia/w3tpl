<?php namespace w3tpl;
/*
  HISTORY:
    2017-10-29 split off from w3tpl.body.php
    2017-11-09 added *AllowRawHTML() option
      To allow only on protected pages:
	$wgW3xAllowRawHTML = $wgTitle->isProtected ('edit');
      To allow on all pages (e.g. on a site where only approved members can edit):
	$wgW3xAllowRawHTML = TRUE;
*/
class xcW3TPL {

    /* 2017-12-15 Apparently this really just isn't the way to do it. The class isn't loaded at LocalSettings time.
    
    // AllowRawHTML: TRUE = ok to echo unfiltered HTML tags, e.g. <iframe>; FALSE = display tags visibly
    static private $bAllowRawHTML = FALSE;
    static public function SetAllowRawHTML($b) {
	self::$bAllowRawHTML = $b;
    }
    static public function GetAllowRawHTML() {
	return self::$bAllowRawHTML;
    }
*/
    static public function init() {
	//global $wgParser;
	//global $wgAutoloadClasses;
	//global $wgExtW3TPL;
	//global $wgW3RawOk;

	\fcApp_MW::Make();	// make sure the application object exists
	
	$fpSelf = dirname( __FILE__, 2 );
	$fpTags = $fpSelf.'/tags';	// Can't see any reason for this not to be hard-coded
	xcTag::LoadAll($fpTags);
	
	xcModule::SetFolderPath($fpSelf.'/plugins');	// same (maybe change to "modules", though)

/* NEW STYLE but manual
	$wgParser->setHook( 'dump',	array( 'w3tpl\\xcTag_dump',	'Call' ) );
	$wgParser->setHook( 'func',	array( 'w3tpl\\xcTag_func',	'Call' ) );
	$wgParser->setHook( 'get',	array( 'w3tpl\\xcTag_get',	'Call' ) );
	$wgParser->setHook( 'hide',	array( 'w3tpl\\xcTag_hide',	'Call' ) );
	$wgParser->setHook( 'let',	array( 'w3tpl\\xcTag_let',	'Call' ) );
*/
// TODO: create this list from files in w3tpl/tags - but also need to register autoload classes somehow

	/* OLD STYLE
	$wgParser->setHook( 'arg',	'efW3Arg' );
	$wgParser->setHook( 'call',	'efW3Call' );
	$wgParser->setHook( 'class',	'efW3Class' );
	$wgParser->setHook( 'dump',	'efW3Dump' );
	$wgParser->setHook( 'echo',	'efW3Echo' );
	$wgParser->setHook( 'else',	'efW3Else' );
	$wgParser->setHook( 'exec',	'efW3Exec' );
	$wgParser->setHook( 'for',	'efW3For' );
	$wgParser->setHook( 'func',	'efW3Func' );
	$wgParser->setHook( 'hide',	'efW3Hide' );
	$wgParser->setHook( 'if',	'efW3If' );
	$wgParser->setHook( 'let',	'efW3Let' );
	$wgParser->setHook( 'load',	'efW3Load' );
	$wgParser->setHook( 'save',	'efW3Save' );
	//$wgParser->setHook( 'trace',	'efW3Trace' );
	$wgParser->setHook( 'w3tpl',	'efW3TPLRender' );
	$wgParser->setHook( 'xploop',	'efW3Xploop' );
	*/
	return TRUE;
    }
}