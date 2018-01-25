<?php namespace w3tpl;
/*
  PURPOSE: class for managing w3tpl functions
  HISTORY:
    2017-11-02 rebuilding from w3tpl1
*/
class xcFunc {

    // ++ SETUP ++ //

    /*----
      SETUP: can either be fully provisioned, or just given a name and asked
	to look up its provisions in the database.
    */
    public function __construct(\Parser $mwo,$sName) {
	$this->SetParser($mwo);
	$this->SetName($sName);
    }
    public function SetDefinition(array $arParams, $sCode) {
	$this->SetParameters($arParams);
	$this->SetCode($sCode);
    }

    private $sName;
    protected function SetName($s) {
	$this->sName = $s;
    }
    protected function GetName() {
	return $this->sName;
    }
    
    private $arParams;
    protected function SetParameters(array $ar) {
	$this->arParams = $ar;
    }
    protected function GetParameters() {
	return $this->arParams;
    }
    
    private $arArgs;
    public function SetArguments(array $ar) {
	$this->arArgs = $ar;
    }
    /*----
      ACTION: add the given string as the value of the final function argument
      USAGE: for passing tag contents as an argument
      PUBLIC so <call> tag can pass it
    */
    public function AddArgument($sValue) {
	$this->arArgs[] = $sValue;
    }
    protected function GetArguments() {
	return $this->arArgs;
    }
    public function DumpArguments() {
	return \fcArray::Render($this->arArgs);
    }
    
    /*----
      ACTION: calculate the input arguments to use by combining the tag arguments
	with the parameters and defaults and create/set a variable for each one
      RETURNS: nothing
    */
    protected function UseArguments() {
	$arParams = $this->GetParameters();
	$arArgs = $this->GetArguments();
	foreach ($arParams as $sName => $sDefault) {
	    if (array_key_exists($sName,$arArgs)) {
		$sValue = $arArgs[$sName];
		unset($arArgs[$sName]);
	    } else {
		// if parameter isn't named in tag arguments
		if (array_key_exists('*tag_input',$arArgs)) {
		    $sValue = $arArgs['*tag_input'];
		} else {
		    $sValue = $sDefault;
		}
	    }
	    $oVar = xcVar::GetVariable_FromExpression($sName,TRUE);	// TRUE = create if needed
	    if (is_null($oVar)) {
		throw new \exception("INTERNAL ERROR: could not make variable from expression [$sName].");
	    }
	    $oVar->SetValue($sValue);
	    
	    //global $wgOut;
	    //$wgOut->addHTML("SETTING VARIABLE [$sName] TO [$sValue]<br>");
	    //$wgOut->addHTML('VAR DUMP:'.xcVar::DumpAll());
	    
	}
    }
    
    private $sCode;
    protected function SetCode($s) {
	$this->sCode = $s;
    }
    protected function GetCode() {
	return $this->sCode;
    }

    private $mwoParser;
    protected function SetParser(\Parser $mwo) {
	$this->mwoParser = $mwo;
    }
    protected function GetParser() {
	return $this->mwoParser;
    }

    // -- SETUP -- //
    // ++ REPOSITORY ++ //

      //+in-memory+//
    
    static private $arFx = array();
    /*
    static protected function PutFunction(xcFunc $of) {
	$sName = $of->GetName();
	self::$arFx[$sName] = $of;
    } */
    static protected function GetFunction($sName) {
	if (array_key_exists($sName,self::$arFx)) {
	    return self::$arFx[$sName];
	} else {
	    // fx() is not loaded, so load it:
	    
	}
    }
    
      //-in-memory-//
      //+on-disk+//
    
    /*----
      ACTION: Save the current function definition to the database
      NOTES:
	the function data is stored in page_props (added 2011-07-24; before that, you had to include the definition)
	  using prefix-marked strings, with ">" as the prefix because it should never be in a function or argument name:
	    $fkey = '>fx()>'.$funcName;
    */
    public function Store() {
	$oProps = new \fcMWSiteProperties($this->GetParser());
	$oProps->SaveArray($this->GetDefinition());
    }
    /*----
      ACTION: Load the current function's definition from the database
      INPUT: $this->GetName()
    */
    public function Fetch() {
	$sName = $this->GetName();
	// WORKING HERE
	$key = ">fx()>$sName";
	$oProps = new \fcMWSiteProperties($this->GetParser());
	$ar = $oProps->LoadValues($key);
	
	$this->PutDefinition($ar);
    }
      
      //-on-disk-//

    // -- REPOSITORY -- //
    // ++ DEFINITION ++ //

    // RETURNS: Single-line summary of function definition (name + args)
    public function SummaryLine() {
	
	$sArgs = NULL;
	$arArgs = $this->GetArguments();
	if (!is_array($arArgs)) {
	    throw new \exception('w3tpl:func internal error: non-array received for tag arguments');
	}
	foreach ($arArgs as $sName => $sValue) {
	    if (!is_null($sArgs)) {
		$sArgs .= ' ,';
	    }
	    $sArgs .= $sName . '=' . $sValue;
	}
	
	$sParams = NULL;
	$arParams = $this->GetParameters();
	foreach ($arParams as $sName => $sDefault) {
	    if (!is_null($sParams)) {
		$sParams .= ' ,';
	    }
	    $sParams .= $sName;
	    if ($sDefault != '') {
		$sParams .= '='.$sDefault;
	    }
	}
    
	return $this->GetName()."($sParams) <= ($sArgs)";
    }
    /*----
      RETURNS: array containing complete function definition, suitable for storing in page properties
      MIRROR: PutDefinition();
      NOTE: This is a little counterintuitive:
	GET puts together the definition for STORE
	PUT unpacks a FETCHED definition 
    */
    public function GetDefinition() {
	$arArgs = $this->GetArguments();	
	
	// store the function's permissions
	$strPerms = NULL;

	/* 2017-11-03 document before supporting
	if ($this->isOkRaw) {
	    $strPerms .= ' raw';
	}
	if ($this->isOkSQL) {
	    $strPerms .= ' sql';
	}
	$arFProps['perms'] = $strPerms;
	*/
	
	// store the function's code
	$arFProps['code'] = $this->GetCode();
	
	// store the argument data
	/* old code
	foreach ($arArgs as $name => $val) {
	    $arFProps['arg'][$name] = $val;
	}
	*/
	// 2017-11-04 I mean, doesn't this do the same thing?
	$arFProps['arg'] = $arArgs;
	$arOut['fx()'][$this->GetName()] = $arFProps;

	return $arOut;
    }
    /*----
      ACTION: provision the function object from the given properties array
      INPUT: 
	$arDef = array containing complete function definition, as retrieved from Site Properties
	$this->GetName() = name of function to store/update
      MIRROR: GetDefinition();
    */
    public function PutDefinition(array $arDef) {
	if (!array_key_exists('code',$arDef)) {
	    echo 'function definition:'.\fcArray::Render($arDef);
	    $sName = $this->GetName();
	    throw new exception("Code not found for function \"$sName()\".");
	}
	$this->SetDefinition(
	  \fcArray::Nz($arDef,'arg'),
	  $arDef['code']
	  );
	
	/* 2017-11-04 support when usage case is documented
	$sPerms = $arDef['perms'];

	$this->isOkRaw = strpos($sPerms,' raw');
	$this->isOkSQL = strpos($sPerms,' sql');
	*/
    }

    // -- DEFINITION -- //
    // ++ ACTION ++ //

    /*----
      NOTE: There was a bit of a kluge in effect where any argument that had the same name as an existing variable
	caused the existing variable to have its value saved so that it could be temporarily overwritten by the
	argument value; the global's original value was restored after execution.
	
	This seems like the wrong way to do it. Either globals should be referenced differently, or arguments should
	be referenced differently, or we just shouldn't name arguments the same as any globals we might be using.
	
      HISTORY:
	2017-11-05 major rewrite of all the things: turned off preservations of global variables named the same as arguments
    */
    public function Execute() {
    // set global variables from passed arguments
	$this->UseArguments();
    
/* 2017-11-05 no longer preserving globals
//	if ($this->HasArgs()) {
	    $arArgs = $this->GetArguments();
	    foreach ($arArgs as $sName => $sValue) {
	    // WORKING HERE
		if (W3VarExists($name)) {
		    $oldVars[$name] = W3GetVal($name);
		}
		W3SetVar($name, $value);
	    }
//	}
*/
    // parse (execute) the function code
	//$out = "[CODE]<br><pre>".htmlspecialchars($this->GetCode()).'</pre><br>[/CODE]<br>';
	//$out .= $this->SummaryLine();
	
	$sCodeRaw = $this->GetCode();

	// strip out all beginning/ending whitespace and CRLFs from each line
	$sCode = NULL;
	$tok = strtok($sCodeRaw,"\n");
	while ($tok !== false) {
	    $sLine = trim($tok);
	    $sCode .= $sLine;

	    $tok = strtok("\n");
	}
	// this probably belongs somewhere else, e.g. GetInput() -- so commenting it out for now:
	//$sCode = str_replace('\n',"\n",$sCode);	// allow \n to mean "actual CRLF here"
			
	//$htCode = htmlspecialchars($sCode);
	//echo "CODE TO EXECUTE[$htCode]";
/*
	$sCodeWorks = '{{#set:Summary=“ Five members of [[Donald Trump|Trump]]\'s [[evangelical]] executive advisory board, including the founder of the [[FRC|Family Research Council]] [[James Dobson]], [https://www.thegailygrind.com/2017/08/31/trump-advisers-sign-nashville-statement-denouncing-homosexual-immorality-transgenderism/ signed the Nashville Statement]. The Nashville statement dictated that [[transgender]] people should not be tolerated in society, and that anyone who tolerates them is not a [[Christian]].”}}“ Five members of [[Donald Trump|Trump]]\'s [[evangelical]] executive advisory board, including the founder of the [[FRC|Family Research Council]] [[James Dobson]], [https://www.thegailygrind.com/2017/08/31/trump-advisers-sign-nashville-statement-denouncing-homosexual-immorality-transgenderism/ signed the Nashville Statement]. The Nashville statement dictated that [[transgender]] people should not be tolerated in society, and that anyone who tolerates them is not a [[Christian]].”';
*/	
	
	$sParsed = $this->GetParser()->recursiveTagParseFully( $sCode );
	$sParsed = $this->GetParser()->recursiveTagParse( $sParsed );	// KLUGE because the first one leaves stuff unparsed
/*	
	global $wgOut;
	$sCode = $sParsed;	// just for debugging
	if ($sCodeWorks == $sCode) {
	    $wgOut->addHTML( "<b>IT'S THE SAME THING</b>" );
	} else {
	    $wgOut->addHTML( "<b>THEY ARE DIFFERENT!</b>" );

	    $wgOut->addHTML( "<br>PARSED:" );
	    $nLen = strlen($sCode);
	    for($idx=0; $idx<$nLen; $idx++) {
		$ch = substr($sCode,$idx,1);
		$wgOut->addHTML( ' '.ord($ch) );
	    }
	    $wgOut->addHTML('<br>PARSED: '.htmlspecialchars($sCode));
	    
	    $wgOut->addHTML( "<br>WORKS:" );
	    for($idx=0; $idx<$nLen; $idx++) {
		$ch = substr($sCodeWorks,$idx,1);
		$wgOut->addHTML( ' '.ord($ch) );
	    }
	    $wgOut->addHTML('<br>WORKS: '.htmlspecialchars($sCodeWorks));
	}
*/	
	
	//$out .= "[OUTPUT]$sParsed[/OUTPUT]<br>";
	$out = $sParsed;

/* 2017-11-05 no longer preserving globals
    // restore original variables (old value if any, or remove from list)
	//if ($this->HasArgs()) {		// apparently the state can change
	    foreach ($this->vArgs as $name => $value) {
		if (isset($oldVars[$name])) {
		    W3SetVar($name, $oldVars[$name]);
		} else {
		    W3KillVar($name);
		}
	    }
	//}
*/
	return $out;
    }

    // -- ACTION -- //
}