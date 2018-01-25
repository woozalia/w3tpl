<?php namespace w3tpl;
/*
  PURPOSE: class for managing w3tpl plugin modules
  RULES:
    The modules folder should only searched on request, e.g. to display what modules are available.
      (I'm not even going to implement this right away.)
  HISTORY:
    2017-11-09 rebuilding for w3tpl2
    2018-01-23 consolidated LoadFile() and GetModule(), but then split them up again with a clearer delineation:
      LoadModule() always loads the module file but does not instantiate the module class
	If not previously loaded, stores the *class name* (not the object)
      GetModule() always instantiates the module class
*/
abstract class xcModule {

    // ++:: GLOBAL ::++ //

      // ++ PROPERTIES ++ //
    
    static private $mwoParser;
    static protected function SetParser(\Parser $mwo) {
	self::$mwoParser = $mwo;
    }
    static protected function GetParser() {
	return self::$mwoParser;
    }
    
    static private $fpMods;
    static public function SetFolderPath($fpMods) {
	self::$fpMods = $fpMods;
    }
    static protected function GetFolderPath() {
	return self::$fpMods;
    }
    
      // -- PROPERTIES -- //
      // ++ EVENTS ++ //
    
    /*----
      ACTION: Load a module file
	Primarily, this ensures that the module class is defined and its name is known.
      INPUT: keyname of module
	* used to build filename and, by default, module class name
	* should be base name of file - no extension, no path
      RETURNS: string or (if loaded elsewhere) object
    */
    static private $arMods=array();
    static protected function LoadModule($sName) {
	if (array_key_exists($sName,self::$arMods)) {
	    $xMod = self::$arMods[$sName];	// string or object
	} else {
	    $fs = self::GetFolderPath().'/'.$sName.'.php';
	    if (file_exists($fs)) {
		unset($csModuleClass);	// cs = configuration string
		include($fs);
		if (isset($csModuleClass)) {
		    $sClass = __NAMESPACE__.'\\'.$csModuleClass;
		} else {
		    $sClass = __CLASS__.'_'.$sName;
		}
		$xMod = $sClass;	// loaded but not instantiated
	    } else {
		throw new \exception("Cannot load module [$sName] because module file [$fs] was not found.");
	    }
	    self::$arMods[$sName] = $xMod;
	}
	return $xMod;
    }
    static protected function GetModule($sName) {
	$xMod = self::LoadModule($sName);
	if (is_string($xMod)) {
	    // file has been loaded but class not instantiated: instantiate it
	    $oMod = new $xMod();
	    self::$arMods[$sName] = $oMod;	// save object
	} else {
	    if (is_null($xMod)) {
		throw new \exception("Internal error: trying to retrieve class for [$sName] before it has been loaded.");
	    } else {
	    // class must have already been instantiated
		$oMod = $xMod;
	    }
	}
	return $oMod;
    }
    /* 2018-01-23 take 2
    static protected function GetModule($sName) {
	if (!array_key_exists($sName,self::$arMods)) {
	    $fs = self::GetFolderPath().'/'.$sName.'.php';
	    if (file_exists($fs)) {
		unset($csModuleClass);	// cs = configuration string
		include($fs);
		if (!isset($csModuleClass)) {
		    $csModuleClass = __CLASS__.'_'.$sName;
		}
		$sClassFull = __NAMESPACE__.'\\'.$csModuleClass;
		$oMod = new $sClassFull();
		self::$arMods[$sName] = $oMod;
	    } else {
		echo '<pre id=register-module>';
		throw new \exception("Attempted to load W3TPL module [$sName] from nonexistent file [$fs].");
	    }
	}
	return self::$arMods[$sName];
    } */
    /* 2018-01-23 old code
    static public function LoadFile($sName) {
	$fs = self::GetFolderPath().'/'.$sName.'.php';
	if (file_exists($fs)) {
	    require_once($fs);
	} else {
	    echo '<pre id=register-module>';
	    throw new \exception("Attempted to load W3TPL module [$sName] from nonexistent file [$fs].");
	}
    }
    // ACTION: if module has not been loaded, load it first
    static private $arModules = array();
    static protected function GetModule($sName) {
	if (!array_key_exists($sName,self::$arModules)) {
	    self::LoadFile($sName);
	    $sClass = __CLASS__.'_'.$sName;
	    $oMod = new $sClass();
	    self::$arModules[$sName] = $oMod;
	}
	return self::$arModules[$sName];
    }
    */
    static public function Call($sModuleName, $sFunctionName, array $arArgs, \Parser $mwo) {
	self::SetParser($mwo);
	$oMod = self::GetModule($sModuleName);
	if (method_exists($oMod, $sFunctionName)) {
	    $out = $oMod->$sFunctionName($arArgs);
	} else {
	    $oTrace = new \fcStackTrace();
	    $out = "<b>Error</b>: Function [$sFunctionName] not found in plugin module."
	      .'<br> - <b>module</b>: '.$sModuleName
	      .'<br> - <b>class</b>: '.get_class($oMod)
	      .'<br> - <b>method</b>: '.$sFunctionName
	      .'<br> - <b>stack trace</b>:<small>'
	      .$oTrace->RenderAllRows()
	      .'</small>'
	      ;
	}
	return $out;
    }
    
    // --:: GLOBAL ::-- //
    // ++ SERVICES ++ //
    
    /*----
      HISTORY:
	2012-09-18 changed from protected to public so that child-objects can use it
	2017-11-14 moved from w3tpl_module (w3tpl1) to xcModule (w3tpl2); changed back to protected
	  (What did I mean by "child-objects"? Document.)
    */
    protected function Parse_WikiText($iText) {
	$out = self::GetParser()->recursiveTagParse($iText);
	return $out;
    }
    
    // -- SERVICES -- //
}

// PURPOSE: xcModule which supports Semantic MediaWiki 
abstract class xcModule_SMW extends xcModule {

      // ++ CLASSES ++ //
      
    // OVERRIDE
    protected function DatabaseClass() {
	return '\fcDataConn_SMW';
    }

      // -- CLASSES -- //

}