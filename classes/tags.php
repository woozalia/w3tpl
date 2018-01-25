<?php namespace w3tpl;
/*
  PURPOSE: Base classes for tags
  HISTORY:
    2017-10-29 started
*/

abstract class xcTag {

    // ++ GLOBAL ++ //
    
    // ACTION: load all tag files found in the tag handlers folder ($fpTags)
    static public function LoadAll($fpTags) {
	$poDir = dir($fpTags);		// dir() returns a native PHP object
	while (FALSE !== ($fnFile = $poDir->read())) {
	    if (($fnFile!='.') && ($fnFile!='..')) {	// TODO: this is probably redundant - these would also fail !is_dir()
		$fs = $fpTags.'/'.$fnFile;
		if (!is_dir($fs)) {	// ignore subfolders
		    $fnLower = strtolower($fnFile);
		    // check for .php extension
		    if (substr($fnLower,-4) == '.php') {
			$sTagName = substr($fnLower,0,strlen($fnLower)-4);
			
			global $wgParser;
			global $wgAutoloadClasses;
			
			// tag name found - process it:
			$sClass = 'w3tpl\\xcTag_'.$sTagName;
			$sFx = $sClass.'::Call';
			$wgAutoloadClasses[$sClass] = $fs;
			if (is_callable($sFx)) {
			    //$wgParser->setHook( $sTagName,	array( $sClass,	'Call' ) );	// 2017-12-13 API change??
			    $wgParser->setHook( $sTagName,$sFx );
			} else {
			    throw new \exception('w3tpl internal error: "'.$sFx.'" cannot be called (for tag &lt;'.$sTagName.'&gt;).');
			}
		    }
		}
	    }
	}
	$poDir->close();
    }
    
    // -- GLOBAL -- //
    // ++ ENTRY POINT ++ //

    static public function Call($sInput, array $arArgs, \Parser $mwoParser = NULL, $mwoFrame = FALSE) {
	$sClass = get_called_class();	// static equivalent for get_class($this)
	$xoTag = new $sClass();
	$xoTag->Setup($sInput,$arArgs,$mwoParser,$mwoFrame);
	
	return $xoTag->FigureReturnValue();
    }
    public function Setup($sInput, array $arArgs, \Parser $mwoParser = NULL, $mwoFrame = FALSE) {
	$this->SetInput($sInput);
	$this->SetArguments($arArgs);
	$this->SetParser($mwoParser);
	// not even sure what Frame is for, so not doing anything with it
    }
    abstract public function Go();
    
    // -- ENTRY POINT -- //
    // ++ PROPERTIES ++ //
    
    abstract protected function TagName();	// maybe there's a way to get this from MW?

    private $sInput;
    protected function SetInput($s) {
	$this->sInput = $s;
    }
    protected function GetInput() {
	return $this->sInput;
    }
    
    private $arArgs;
    protected function SetArguments(array $arArgs) {
	$this->arArgs = $arArgs;
    }
    protected function GetArguments() {
	return $this->arArgs;
    }
    protected function ArgumentExists($sName) {
	return array_key_exists($sName,$this->arArgs);
    }
    protected function ArgumentValue($sName) {
	return $this->arArgs[$sName];
    }
    protected function ArgumentValueNz($sName,$sDefault=NULL) {
	if ($this->ArgumentExists($sName)) {
	    $out = $this->ArgumentValue($sName);
	} else {
	    $out = $sDefault;
	}
	return $out;
    }
    protected function RequireArgument($sName) {
	if ($this->ArgumentExists($sName)) {
	    $sOut = $this->ArgumentValue($sName);
	} else {
	    $sTag = $this->TagName();
	    $this->AddError("&lt;$sTag&gt; tag needs a '$sName' attribute.");
	    $sOut = NULL;
	}
	return $sOut;
    }

    private $mwoParser;
    protected function SetParser(\Parser $mwo) {
	$this->mwoParser = $mwo;
    }
    protected function GetParser() {
	return $this->mwoParser;
    }
    
    private $bIsolateOutput = FALSE;
    protected function SetIsolateOutput($b) {
	$this->bIsolateOutput = $b;
    }
    protected function GetIsolateOutput() {
	return $this->bIsolateOutput;
    }
    protected function GetMarkerType() {
	return $this->GetIsolateOutput() ? 'nowiki' : NULL;
    }
    protected function FigureReturnValue() {
	$sOut = $this->Go() . $this->GetMessages();
	$sMarker = $this->GetMarkerType();
	if (is_null($sMarker)) {
	    return $sOut;
	} else {
	    return array
	      ( 
		$sOut,
		"markerType" => $sMarker
	      );
	}
    }
    
    // -- PROPERTIES -- //
    // ++ OUTPUT ++ //
    
    private $sMsg = NULL;
    private $isOk = TRUE;
    protected function AddMessage($sMsg) {
	$this->sMsg .= $sMsg;
    }
    protected function AddError($sMsg) {	// TODO: formatting to make output more obviously an error
	$this->AddMessage($sMsg);
	$this->isOk = FALSE;
    }
    public function GetMessages() {
	return $this->sMsg;
    }
    protected function IsOkay() {
	return $this->isOk;
    }
    
    // -- OUTPUT -- //
}
