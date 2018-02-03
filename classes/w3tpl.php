<?php namespace w3tpl;
/*
  HISTORY:
    2017-10-29 split off from w3tpl.body.php
    2017-11-09 added $wgW3xAllowRawHTML option
      To allow only on protected pages:
	$wgW3xAllowRawHTML = $wgTitle->isProtected ('edit');
      To allow on all pages (e.g. on a site where only approved members can edit):
	$wgW3xAllowRawHTML = TRUE;
    2018-01-26 added $wgW3xSupportSMW option
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

    	// make sure the application object exists and is the class we need
	xcApp::Make();
	if (self::GetProvideSMWSupport()) {
	    xcApp::Me()->SetDatabaseClass('fcDataConn_SMW');	// use db class with SMW support
	}
	
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
    
    // ++ CONFIGURATION ++ //
    
    static public function GetAllowRawHTML() {
	global $wgW3xAllowRawHTML;
	
	return $wgW3xAllowRawHTML;
    }
    static protected function GetProvideSMWSupport() {
	global $wgW3xSupportSMW;
	
	return $wgW3xSupportSMW;	// might need to check for empty($wgW3xSupportSMW)
    }
    
    // -- CONFIGURATION -- //
}
class xcApp extends \fcApp_MW {

    // ++ FRAMEWORK ++ //
    
    private $oMod;
    public function SetModule(xcModule $oModule) {
	$this->oMod = $oModule;
    }
    public function GetModule() {
	return $this->oMod;
    }
    public function GetDataHelper() {
	return $this->GetModule()->GetDataHelper();
    }
    
    // -- FRAMEWORK -- //

}