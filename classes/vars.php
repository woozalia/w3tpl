<?php namespace w3tpl;
/*
  PURPOSE: class for managing tag-variables in w3tpl
  RULES:
    * each xcVar object has a unique name that includes any array indexes it might have
    * That unique name can be used to access its storage location in the master array, or its value on the current page.
    * If the object is an array element, then the storage name follows the format 'varname/index'.
    * Variable expressions use a more natural syntax, e.g. varname[index].
    * An *expression* can be:
    ** a literal value - "this is a string"
    ** a reference to a variable - "$theVar"
    ** a reference to a system value - @row.fieldname
    ** a reference to a function's output - (not sure what the syntax should be)
    * A variable reference can be:
    ** scalar value - "$aScalar"
    ** an array - pointer to a storage location that has subvalues - should probably be like "$anArray[]"
    ** array element - "$anArray[index_expression]" where "index_expression" is an expression
    * We automatically find the value of all inner elements as needed, but leave the outermost unresolved
	in order to allow for different operations depending on context.
	
    This is not a hierarchy; all variables live in a flat array managed statically by the class.
  HISTORY:
    2012-06-03 IS THERE ANY REASON not to rename clsW3VarName to clsW3Var?
    2015-09-10 Renamed clsW3VarName to clsW3Var.
    2015-09-28 extracted from W3TPL.php
    2016-09-19 adapting for w3tpl2
    2017-10-29 take 2... renaming cw3Variable to xcVar, making things work for real...
    2017-10-30 brutally rewriting 
*/

class xcVar {

    // ++ SETUP ++ //

    protected static function GetVar($sNameExpr) {
	throw new \exception('2017-10-30 This will need rewriting.');
	$sClass = __CLASS__;
	$oVar = new $sClass;
	$oVar->FetchVar_byNameExpr($sNameExpr);
	return $oVar;
    }
    protected static function GetVarVal($sNameExpr) {
	throw new \exception('2017-10-30 This will need rewriting.');
	$oVar = self::GetVar($sNameExpr);
	return $oVar->GetValue();
    }
    
    // -- SETUP -- //
    // ++ REPOSITORY ++ //
    
    static private $arVars=array();
    static protected function GetVars() {
	return self::$arVars;
    }
    protected function SpawnNode($sName) {
	throw new \exception('2017-10-30 What is this even for.');
	$oNode = self::SpawnVariable();
	$oNode->SetParent($this);
	$oNode->SetName($sName);
	return $oNode;
    }
    static protected function VariableExists($sName) {
	return array_key_exists($sName,self::$arVars);
    }
    // ASSUMES: variable exists
    static protected function GetVariableObject($sName) {
	return self::$arVars[$sName];
    }
    /*----
      INPUT: $sName = name by which this should be saved for later use; NULL = temp variable (used for parsing)
      TODO: should probably be renamed SpawnVariableObject()
    */
    static protected function SpawnVariable($sName=NULL) {
	$sClass = get_called_class();	// static equivalent for get_class($this)
	$oVar = new $sClass();
	if (!is_null($sName)) {
	    $oVar->SetName($sName);
	    self::$arVars[$sName] = $oVar;
	}
	return $oVar;
    }
    /*-----
      HISTORY:
	2018-01-28 created; why didn't this already exist?
    */
    static protected function MakeVariableObject($sName) {
	if (self::VariableExists($sName)) {
	    $oVar = self::GetVariableObject($sName);
	} else {
	    $oVar = self::SpawnVariable($sName);
	}
	return $oVar;
    }
    // PUBLIC so tags can look up values
    static public function GetVariableValue($sName) {
	if (self::VariableExists($sName)) {
	
	    $oVar = self::GetVariableObject($sName);
	    $rtn = $oVar->GetValue();
	    
	} else {
	    $rtn = NULL;
	}
	return $rtn;
    }
    static public function SetVariableValue($sName,$sValue) {
	if (self::VariableExists($sName)) {
	    $oVar = self::GetVariableObject($sName);
	} else {
	    $oVar = self::SpawnVariable($sName);
	}
	$oVar->SetValue($sValue);
    }
    
    // -- REPOSITORY -- //
    // ++ PARSING ++ //
    
    /*----
      ACTION: creates/retrieves a variable-object from a descriptor in script syntax
      TODO: Should probably be renamed MakeVariable_fromExpression(), because it checks for existing var
      INPUT: $sExpr = expression that evaluates to a name
	Must be a regular (non-sysvar) variable.
	May include array offset.
	Should not include the "$".
       RETURNS: discovered or created variable object
      PUBLIC because variable-accessing tags (<let>,<get>...) need to use it
      NOTE: Does not support @vars because those are actually values, not variables.
      HISTORY:
	2017-10-30 was not working before; wasn't really written, even
	2018-01-28 removing support for arrays; brackets will be treated as part of variable name
    */
    static public function GetVariable_fromExpression($sExpr,$doCreate=FALSE) {
	// normalize all name strings to lower case
	$sExpr = strtolower($sExpr);

	/*
	if (substr($sExpr, -1) == ']') {	// is the expression an array element?
	
	    // name includes index offset, so parse as array element
	    $idxOpen = strpos($sExpr,'[');
	    if ($idxOpen) {
		$sElemOuter = substr($sExpr,$idxOpen);
		$sElemInner = substr($sElemOuter,1,strlen($sElemOuter)-2);
		$sBase = substr($sExpr,0,$idxOpen);
		$oBase = self::MakeVariableObject($sBase);
		echo "ELEM=[$sElemInner] BASE=[$sBase]";
		$oVar = $this->CreateNode_fromScriptIndex($sElem);
	    } else {
		throw new \exception('TODO: report malformed variable here');
	    }
	} else { */
	    // expression is a literal variable name
	    if (self::VariableExists($sExpr)) {
		$oVar = $oVar = self::GetVariableObject($sExpr);
	    } else {
		if ($doCreate) {
		    $oVar = self::SpawnVariable($sExpr);
		} else {
		    $oVar = NULL;
		}
	    }
	//}
	return $oVar;
    }
    /*----
      ACTION: takes an expression which may be a variable reference and returns its value
      INPUT: $sExpr = a value expression
	If the expression does not start with a "$", it's taken as a literal and returned verbatim.
	If the expression starts with "$", it's taken as a variable reference and
	  the remainder is passed to GetVariable_fromExpression(); the return from there is
	  then queried to get its value.
	TODO: strip enclosing quotes
	TODO: recognize functions
      RETURNS: string
      PUBLIC so tags can use it
      HISTORY:
	2017-10-30 written from scratch (I think there must have been a similar function *somewhere* in old w3tpl...)
	2017-11-05 made public for <call> tag
	2018-01-23 call to GetVariable_fromExpression() in '$' had no argument!
    */
    static public function GetValue_fromExpression($sExpr) {
	//global $wgOut;	// for debugging
	
	$ch = substr($sExpr,0,1);
	switch ($ch) {
	  case '$':
	    $sVar = substr($sExpr,1);	// remainder after '$'
	    $oVar = self::GetVariable_fromExpression($sVar);
	    $rtn = $oVar->GetValue();
	    break;
	  case '@':
	    $sFxName = substr($sExpr,1);
	    $rtn = self::GetValue_fromSysFunction($sFxName);
	    break;
	  default:
	    $rtn = $sExpr;
	}
	return $rtn;
    }
    /*----
      HISTORY:
	2017-11-14 adapting from W3GetSysData()
    */
    static protected function GetValue_fromSysFunction($sName) {
	global $wgTitle;
	//global $wgOut;	// for debugging
	global $sql;

	$out = '';

	$arParts =  explode('.', $sName);
	if (isset($arParts[1])) {
	    $sParamRaw = $arParts[1];
	    $sParam = strtolower($sParamRaw);
	} else {
	    $sParam = NULL;
	}
	$sPart0 = $arParts[0];
	//$wgOut->addHTML("FX: [$sPart0] REQ: [$sParam] <br>");

	switch ($sPart0) {
	  case 'title':
	    switch ($sParam) {
	      case 'id':
		$out = $wgTitle->getArticleID();
		break;
	      case 'full':	// namespace:subject
		$out = $wgTitle->getPrefixedText();
		//$wgOut->addHTML("FULL TITLE: [$out]<br>");
		break;
	      case 'subject':	// just the part after the namespace and before any slashes
		$out = $wgTitle->getBaseText();
		break;
	      case 'name':		// just the part after the namespace
		$out = $wgTitle->getText();
		break;
	      case 'url':
		$out = $wgTitle->getFullURL();
		break;
	      case 'dbkey':		// as stored in db tables
		$out = $wgTitle->getDBkey();
		break;
	    }
	    break;
	  /* 2017-11-14 this is going to need some work, and we don't need it at the moment.
	  case 'row':
	    $sField = $sParamRaw;	// field name
	    $sSet = '@@row@@';

	    $out = NULL;
    // This is a horrible kluge necessitated by the 2 different ways of accessing data in <for>
	    //clsModule::LoadFunc('NzArray');
	    $val = \fcArray::Nz($wgW3_data,$strSet);
	    if (!is_null($val)) {
		$vRow = $val;
		if (is_array($vRow)) {
		    if (array_key_exists($strFld,$vRow)) {
			$out = $vRow[$strFld];
		    } else {
			$strErr = 'Dataset ['.$strSet.'] has no field named ['.$strFld.'].';
			//throw new exception($strErr);
			$out = '<b>Error</b>: '.$strErr;
		    }
		} else {
		    if (isset($vRow->$strFld)) {
			$out = $vRow->$strFld;
		    }
		}
	    }
	    W3AddTrace('DATA['.$strSet.']['.$strFld.'] => ['.$out.']');
	    break;
	  case 'mem':
	    $out = memory_get_usage(TRUE);
	    break;
	  case 'user':
	    switch ($strParam) {
	      case 'login':
		$out = $wgUser->getName();
		break;
	      case 'dbkey':
		$out = $wgUser->getTitleKey();
		break;
	      case 'id':
		$out = $wgUser->getID();
		break;
	      case 'can':
		$out = $wgUser->isAllowed($strParts[2]);
		break;
	      case 'rights':
		$arrRights = $wgUser->getRights();
		$out = '';
		foreach ($arrRights as $key=>$val) {
		    $out .= '\\'.$val;
		}
		break;
	      case 'email':
		$out = $wgUser->getEmail();
		break;
	      case 'name':
		$out = $wgUser->getRealName();
		break;
	      default:
		$out = '?'.$strParam;
	    }
	    break;
	  case 'http':
	    $strName = clsArray::Nz($strParts,2);
	    switch ($strParam) {
	      case 'get':
		if (empty($strName)) {
			# TO DO: return raw query
		} else {
			//$out = $wgRequest->getText($strParam);
			if (isset($_GET[$strName])) {
			    $out = $_GET[$strName];
			} else {
			    $out = NULL;	// maybe this should be a list? or raw query, unparsed?
			}
		}
		break;
	      case 'post':
		if ($strParam) {
			if (isset($_POST[$strParam])) {
				$out = $_POST[$strParam];
			} else {
				$out = NULL;	// maybe this should be a list?
			}
		}
		W3AddTrace(' GET POST ['.ShowHTML($strParam).']: ['.$out.']');
		break;
	      case 'req':
		if ($strParam) {
		    //$out = $wgRequest->getVal($strName);
		    $out = clsArray::Nz($_REQUEST,$strName);
		    W3AddTrace('NAME=['.$strName.'] RESULT=['.$out.']');
		} else {
		    $out = NULL;	// maybe this should be a list?
		}
		break;
	    }
	    break;
	  case 'query':	// DEPRECATED -- same as http.get; eliminate eventually
	    if ($strParam == '') {
		    // never used
	    } else {
		//$out = $wgRequest->getText($strParam);
		if (isset($_GET[$strParam])) {
		    $out = $_GET[$strParam];
		} else {
		    $out = '';
		}
	    }
	    break;
	  case 'post':	// DEPRECATED -- same as http.post; eliminate eventually
	    if ($strParam) {
		if (array_key_exists($strParam,$_POST)) {
		    $out = $_POST[$strParam];
		} else {
		    $out = NULL;
		}
	    }
	    W3AddTrace(' GET POST ['.ShowHTML($strParam).']: ['.$out.']');
	    break;
	  case 'env':
	      if ($strParam) {
		      if (array_key_exists($strParam,$_ENV)) {
			      $out = $_ENV[$strParam];
		      } else {
			      $out = NULL;
		      }
	      }
	      break;
	  case 'db':
	    switch ($strParam) {
	      case 'sql':
		$out = $sql;
		break;
	      default:
		$out = 'unknown subtype for db: ['.$strParam.']';
	    }
	    break;
	*/
	  /* This is mainly for debugging (later it may be useful for library maintenance), so I'm
	    not going to try to make it forward-compatible with the changes I expect to make later,
	    i.e. having functions as an object type.
	    SYNTAX: @func.name.def|page
	  */
	  /* 2017-11-14 document the need for this before fixing
	  case 'func':
	  global $wgW3_funcs;

	    $fname = $strParam;
	    $fobj = $wgW3_funcs[$fname];

	    switch ($strParts[2]) {
	      case 'def':
		$out = $fobj->dump();
		break;
	      case 'page':
		// not implemented yet
		break;
	    }
	    break;
	  */
	}

	return $out;
    }

    /*----
      INPUT: $sName = expression that evaluates to a name
      RETURNS: variable node path array
      RULES:
	* double-indirection ($$name) is not handled
    */
    /* 2017-10-30 nothing uses this
    static protected function ParseNameExpr($sName) {
	// variable names and indexes are case-insensitive
	$sName = strtolower($sName);
	// find first opening bracket (if any):
	$idxOpen = strpos($sName,'[');
	if (is_null($idxOpen)) {
	    $arOut[] = $sName;	// expression is literal, and we're done
	} elseif ($idxOpen !== FALSE) {
	    $arOut[] = $sName;	// save part before brackets as first element
	    while ($idxOpen !== FALSE) {
		$idxShut = fcString::FindPair($sName,'[',']');
		if ($idxShut === FALSE) {
		    throw new exception("Found left bracket without matching right bracket in name expression \"$sName\".");
		}
		$sIndex = substr($sName,$idxOpen+1,$idxShut-$idxOpen-1);	// get contents of bracket pair
		$arOut[] = self::ParseExprValue($sIndex);
	    }
	}
	return $arOut;
    } */
    /*----
      NOT TESTED
      HISTORY:
	2012-06-04 substantial rewrite of this and associated methods
	2016-09-19 updated
      OUTPUT:
	RETURNS parsed value
    */
    /* 2017-10-30 nothing uses this
    static protected function ParseExprValue($sExpr) {
	$chFirst = substr($sExpr,0,1);
	switch ($chFirst) {
	  case '$':
	    $sName = strtolower(substr($sExpr,1));
	    $sValue = self::GetVariableValue($sName);
	    // TODO: make this work for arrays
	    break;
	  case '@':
	    $sSysRef = strtolower(substr($strExpr,1));
	    $sValue = self::GetSysData($sSysRef);
	    break;
	  default:
	    // it's a literal, so the string is the value
	    $sValue = $sExpr;
	}

	return $sValue;
    } */
    
    // -- PARSING -- //
    // ++ ACTIONS ++ //

    /*----
      ACTION: Clears the variable's value from page storage and removes any array elements
    */
    public function ClearStored() {
	$this->Fetch();	// make sure any stored array elements are loaded so they don't get overlooked
	if ($this->HasNodes()) {
	    $ar = $this->GetNodes();
	    foreach ($ar as $key => $oVar) {
		$oVar->SetValue(NULL);
		$oVar->ClearStored();
	    }
	}
	$this->SetValue(NULL);
	$this->Store();
    }

    // -- ACTIONS -- //
    // ++ ATTRIBUTES ++ //

    private $sName;
    protected function SetName($sName,$sIndex=NULL) {
	$this->sName = $sName;
	return;	// 2016-11-07 writing still in progress; fail gracefully
	$this->SetIndex($sIndex);
    }
    // PUBLIC so caller can store it externally
    public function GetName() {
	return $this->sName;
    }
    /*----
      RETURNS: full variable name -- including array index, if present
      HISTORY:
	2011-09-19 crude implementation so we can store array indexes in page properties
	2017-12-14 I think for now I decided that vars would be flat, not a tree; removing "parent" references
    */
    public function GetStorageName() {
	$out = $this->GetName();
	return $out;
    }
    
    // -- ATTRIBUTES -- //
    // ++ VALUE ACCESS ++ //
    
    private $sValue;
    // PUBLIC because tags need to be able to alter variable values
    public function SetValue($s=NULL) {

    /*
    global $wgOut;
    $wgOut->addHTML("SETTING VALUE OF [".$this->GetName()."] TO [$s]<br>");
    if ($s == '{{#set:') {
	throw new \exception('Where is this coming from?');
    } */

	$this->sValue = $s;
    }
    public function GetValue() {
	return $this->sValue;
    }
    
    // -- VALUE ACCESS -- //
    // ++ VALUE OPERATIONS ++ //

    /* 2017-12-14 why do we do these here?
    public function Increment() {
	return $this->sValue++;
    }
    public function Decrement() {
	return $this->sValue--;
    }
    public function ToUpper() {
	$this->sValue = strtoupper($this->sValue);
    }
    public function ToLower() {
	$this->sValue = strtolower($this->sValue);
    }
    public function Append($s) {
	$this->sValue .= $s;
    } */

    // -- VALUE OPERATIONS -- //
    // ++ DATA WRITE ++ //
    
    public function SaveValue_toCurrentPage(\Parser $mwoParser) {
	global $wgTitle;
	
	$oProps = new \fcMWProperties_page($mwoParser,$wgTitle);
	$oProps->SaveValue($this->GetStorageName(),$this->GetValue());
    }
    
    // -- DATA WRITE -- //
    // ++ DEBUGGING ++ //
    
    protected function DumpSelf() {
	$sName = $this->GetName();
	$sValue = $this->GetValue();
	$out = "<li>[<b>$sName</b>] = [$sValue]\n";
    
    /* 2017-11-01 old code
	$out = 'clsW3Var.Trace: '.
	  'Name=['.$this->Name.'] '.
	  'Val=['.$strVal.'] '.
	  'Index=['.$this->Index.'] '.
	  TrueFalseHTML('is var',$this->IsVar()).' '.
	  TrueFalseHTML('is element',$this->IsElem()).' '.
	  TrueFalseHTML('is func',$this->isFunc);
	return $out;
    */
	return $out;
    }

    // -- DEBUGGING -- //
    // ++ DEPRECATED ++ //
    
    
    /*----
      OLD STYLE, NOT UPDATED
      ACTION:
	* parses iNameExpr to derive its value
	* initializes this variable as the result of that
      OUTPUT:
	RETURNS: nothing (for now)
	SETS internal field "Value"
	CALLS ParseName(), which also sets internal fields
      USED BY: Fetch()
      ASSUMES: this is a VARIABLE (ELEMENT or ARRAY), not a function or something else
    */
    protected function Setup_byNameExpr($sNameExpr) {
	throw new exception('2017-12-14 Does anything still use this?');

	$this->ParseName($sNameExpr);
	$strName = $this->GetName();	// get name parsed from expression

	if ($this->IsElem()) {
	    $strIndex = $this->Index;
	    $strTrace = ' - INDEX ['.$strIndex.']=>';
	    if (isset($wgW3Vars[$strName][$strIndex])) {
		$this->Value = $wgW3Vars[$strName][$strIndex];
		$strTrace .= '&ldquo;'.$this->Value.'&rdquo;';
	    } else {
		$this->Value = NULL;
		$strTrace .= 'NULL';
	    }
	} else {
	    if (isset($wgW3Vars[$strName])) {
		$this->Value = $wgW3Vars[$strName];
		$strTrace = ' - VAR => '.$this->SummarizeValue();
	    } else {
		$this->Value = NULL;
		$strTrace = ' - NULL';
	    }
	}
    }
    /*----
      NOT TESTED
      ACTION: Creates a node and sets the node's array index from the first bracketed expression.
	Passes any remaining string to the node to parse out any additional subnodes.
      RETURNS: final variable object created
      HISTORY:
	2016-09-19 written
	2018-01-28 used to assume first character is '[', but now brackets are stripped off
    */
    protected function CreateNode_fromScriptIndex($sElem) {
	throw new exception('2017-11-02 This will need checking.'); // This method of doing arrays is maybe different.
	// get the first bracketed expression
	$idxShut = strpos($sElem,']');
	$sExpr = substr($sElem,1,$idxShut-1);		// expression for new node's index
	$sIndex = $this->ParseExprValue($sExpr);	// index for new node
	$sAfter = substr($sName,$idxShut+1);		// get the rest of the string after the first bracket pair
	if (empty($sAfter)) {
	    return $this;
	} else {
	    $oSub = $this->SpawnNode($sIndex);
	    $oNode = $oSub->CreateNode_fromScriptIndex($sAfter);
	    return $oNode;
	}
    }
    public function ParseExpr_toName($iExpr) {
	throw new \exception('2017-10-30 This needs updating.');
	$this->ExprRaw = $iExpr;
	$this->Name = $this->ParseExpr($iExpr);
	W3AddTrace(' STORED ['.$this->Name.'] as NAME');
    }
    public function ParseExpr_toValue($iExpr) {
	throw new \exception('2017-10-30 This needs updating.');
	$this->ExprRaw = $iExpr;
	$this->Value = $this->ParseExpr($iExpr);
	W3AddTrace(' STORED ['.$this->Value.'] as VALUE');
    }
    /*----
      RETURNS: summary of the current Value
	If it's a scalar, just returns it.
	If it's something else, describes it.
	This is just quick & dirty for now; could be expanded later.
      PUBLIC because this class is sometimes instantiated, and we need to show its values in the trace
    */
    public function SummarizeValue() {
	throw new \exception('2017-10-30 This needs updating.');
	if (is_array($this->Value)) {
	    return '(array['.count($this->Value).'])';
	} else {
	    return '['.$this->Value.']';
	}
    }
    /*---
      ACTION: Saves the variable in page properties
	If the variable is an array, saves each element in a separate property
      HISTORY:
	2011-09-19 written to encapsulate existing functionality in <let>
    */
    public function Save(fcMWProperties_page $iProps) {
	throw new \exception('2017-10-30 This needs updating.');
	if ($this->IsArray()) {
	    $iProps->SaveArray($this->Value,$this->FullName());
	} else {
	    $iProps->SaveVal($this->Name,$this->Value);
	}
    }
    /*----
      ACTION: Loads the variable from page properties
    */
    public function Load(fcMWProperties_page $iProps) {
	throw new \exception('2017-10-30 This needs updating.');
	$this->Value = $iProps->LoadVal($this->Name);
	$this->Store();
    }
    /*----
      ACTION: Loads the variable from page properties as an array
    */
    public function LoadArray(fcMWProperties_page $iProps) {
	throw new \exception('2017-10-30 This needs updating.');
	W3AddTrace(' -- LoadArray ENTER');
	$arVal = $iProps->LoadValues($this->Name);
	if (is_array($arVal)) {
	    W3AddTrace(' -- values for ['.$this->Name.'] array:');
	    foreach ($arVal as $key => $val) {
		W3AddTrace(' --- ['.$key.'] = ['.$val.']');
		$objNew = new clsW3Var();
		$objNew->Name = $this->Name;
		$objNew->Index = $key;
		$objNew->Value = $val;
		$objNew->Store();
	    }
	} else {
	    W3AddTrace(' -- not an array');
	}
	W3AddTrace(' -- LoadArray EXIT');
    }
    /*----
      ACTION: Loads all of the page's properties as an array
    */
    public function LoadAll(fcMWProperties_page $iProps) {
	throw new \exception('2017-10-30 This needs updating.');
	W3AddTrace(' -- LoadAll ENTER');
	$arVal = $iProps->LoadVal();	// load all page properties
	if (is_array($arVal)) {
	    W3AddTrace(' -- all values for current page:');
	    foreach ($arVal as $key => $val) {
		W3AddTrace(' --- ['.$key.'] = ['.$val.']');
		$objNew = new clsW3Var();
		$objNew->Name = $this->Name;
		$objNew->Index = $key;
		$objNew->Value = $val;
		$objNew->Store();
	    }
	} else {
	    W3AddTrace(' -- array not returned! (BUG)');
	}
	W3AddTrace(' -- LoadAll EXIT');
    }
    public function Fetch() {
	throw new \exception('2017-10-30 This needs updating.');

	$sName = strtolower($this->GetName());
	if ($this->isFunc) {
	    $this->Value = W3GetSysData($this->Name);
	} elseif ($this->IsVar()) {
	    $this->FetchVar_byNameExpr($strName);
	} else {
		// literal value - already set.
	}
	W3AddTrace('clsW3Var.Fetch: value='.$this->SummarizeValue());
	$this->Trace();
    }
    public function Store() {
	throw new \exception('2017-10-30 This needs updating.');
	global $wgW3Vars;

	$strName = $this->Name;
	$strVal = $this->Value;
	if ($this->isFunc) {
	    W3SetSysData($strName,$strVal);
	} elseif ($strName) {
	    $strName = strtolower($strName);
	    if ($this->isElem()) {
		    $strIndex = $this->Index;
		    $wgW3Vars[$strName][$strIndex] = $strVal;
		    W3AddTrace(__METHOD__.': '.$strName.'['.$strIndex.'] &larr; &ldquo;'.$strVal.'&rdquo;');
	    } else {
		    $wgW3Vars[$strName] = $strVal;
		    W3AddTrace(__METHOD__.': ['.$strName.'] &larr; &ldquo;'.$strVal.'&rdquo;');
	    }
	    $this->Trace();
	} else {
	    W3AddTrace(__METHOD__.': ERROR: no name for value &ldquo;'.$strVal.'&rdquo;');
	    $this->Trace();
	}
    }
    /*----
      PURPOSE: does the Fetch() work now and returns the variable's value
      USAGE: mostly for debugging
    */
    public function Value() {
	throw new \exception('2017-10-30 This needs updating.');
	$this->Fetch();
	return $this->Value;
    }

    public function DoSort($iRev=FALSE, $iVal=FALSE) {
	throw new \exception('2017-10-30 This needs updating.');
	if (is_array($this->Value)) {
	    if ($iVal) {
	    // sort by value
		    if ($iRev) {
			    $ok = arsort($this->Value);
		    } else {
			    $ok = asort($this->Value);
		    }
	    } else {
		    if ($iRev) {
			    $ok = arsort($this->Value);
		    } else {
			    $ok = ksort($this->Value);
		    }
	    }
	    if ($ok) {
		    W3AddTrace('LET sort ['.$this->Name.'] OK');
	    } else {
		    W3AddTrace('LET sort ['.$this->Name.'] <b>ERROR</b>: failed.');
	    }
	} else {
	    W3AddTrace('LET sort <b>ERROR</b>: ['.$this->Name.'] is not an array.');
	}
    }

    public function CheckCopy($iArgs) {
	throw new \exception('2017-11-01 This has been moved to xcTag_Var.');
	if ($iArgs->Exists('copy')) {
	    $strArgVal = $iArgs->GetArgVal('copy');
	    // copy the value of the variable named by the "copy" argument, but not its name:
	    $strVarVal = self::GetVarVal($strArgVal);
	    $this->Value = $strVarVal;

	    //$this->Store();	// update master variable array
	    $strDbg = 'CheckCopy() set from arg copy=['.$strArgVal.'] value=['.$strVarVal.']; result = '.$this->RenderDump();
	    W3AddTrace($strDbg);	// show raw input
	    return TRUE;
	} else {
	    return FALSE;
	}
    }

    public function Trace() {
	throw new \exception('2017-10-30 This needs updating.');
	W3AddTrace($this->RenderDump());
    }
    public function Dump() {
	throw new \exception('2017-10-30 This needs updating.');
	echo $this->RenderDump();
    }
    static public function DumpAll() {
	$arVars = xcVar::GetVars();
	if (count($arVars) > 0) {
	    $out = "<li><b>Variables</b>:\n<ul class='dump'>\n";
	    foreach ($arVars as $oVar) {
		$out .= $oVar->DumpSelf();
	    }
	    $out .= "</ul>\n";
	} else {
	    $out = "<li><i>No variables are set.</i>\n";
	}
	return $out;
    }
    public function RenderDumpArray() {
	throw new \exception('2017-10-30 This needs updating.');
	return clsArray::Render($this->Value);
    }
    
    // -- DEPRECATED -- //
}
