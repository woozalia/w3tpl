<?php
/*
 HISTORY:
  2012-03-18 extracting the parsing functions (currently in development, not usable) to separate file
*/
/*
 Each of these classes is initialized by passing it the remaining text
 They each parse that text into an array of objects, and return any remaining text for the caller to finish parsing.
*/
abstract class clsW3Code {
	private $vText;

	public function __construct($iText) {
		$this->vText = $iText;
	}
	public function Dump() {
		return $vText;
	}
}
abstract class clsW3Code_sub extends clsW3Code {
// Code with ending delimiter, after which remaining text must be parsed by caller
	private $vCloser;
	public $strRemain;

	public function __construct($iText,$iCloser='') {
		$this->vText = $iText;
		$this->vCloser = $iCloser;
	}
}
class clsW3Code_body extends clsW3Code {
	private $vLines;

	public function parse() {
		$strToParse = $this->vText;
	
		$objChunk = NULL;
		while ($strToParse != '') {
			$objLine = new clsW3Code_line($strToParse);
			$strToParse = $objLine->strRemain;
			$this->vLines[] = $objLine;
		}
	}
	public function Dump() {
		$out = '<ul>';
		foreach ($this->vLines as $line) {
			$out .= '<li>'.$line->Dump();
		}
		$out .= '</ul>';
		return $out;
	}
}
class clsW3Code_line extends clsW3Code_sub {
	private $vTokens;

	public function parse() {
		$strToParse = $this->vText;
		$isDone = FALSE;
		$objChunk = NULL;
		for ($i = 0; ($ch=substr($strToParse,$i,1)) && !$isDone; $i++) {
			$doAdd = TRUE;		// TRUE = add this character to the token buffer
			$isTok = FALSE;		// TRUE = a token has been completed; save it and clear buffer
			switch ($ch) {
			// whitespace
			case ' ':	// space
			case "\t":	// tab
				$isTok = TRUE;
				$doAdd = FALSE;		// don't include unquoted whitespace
				break;
			case '"':
				$isTok = TRUE;
				$doAdd = FALSE;		// don't include paren in token
				$objChunk = new clsW3_qstring(substr($input,$i+1));
				$strToParse = $objChunk->strRemain;
				break;
			case '(':
				$isTok = TRUE;		// start new token
				$doAdd = FALSE;		// don't include paren in token
				$objChunk = new clsW3Code_line(substr($input,$i+1),')');
				$strToParse = $objChunk->strRemain;
				break;
			case '{':
				$isTok = TRUE;		// start new token
				$doAdd = FALSE;		// don't include braces in token
				$objChunk = new clsW3Code_body(substr($input,$i+1),'}');
				$strToParse = $objChunk->strRemain;
				break;
			case ';':
				$isTok = TRUE;		// start new token
				$doAdd = FALSE;		// don't include braces in token
				$isDone = TRUE;
				break;
			case "\n":
				$doAdd = false;		// don't include linebreaks in $clause
				break;
			default:
				$isDone = ($ch == $this->vCloser);
			}
			if ($doAdd) {
				$token .= $ch;
			}
			if ($isTok) {	// TRUE = a token has been completed; save it and clear buffer
				if ($token) {
					$objTok = new clsW3_token($token);
					$this->vTokens[] = $objTok;
				}
				$token = NULL;
			}
			if (is_object($objChunk)) {
				$this->vTokens[] = $objChunk;
				$objChunk = NULL;
			}
		}
		$this->strRemain = substr($strToParse,$i+1);
	}
}
class clsW3_token extends clsW3Code {

}
class clsW3_phrase extends clsW3Code {
	private $vList;

}
/*
class clsW3Token_rawexp extends clsW3Token {
	public __construct($iText) {
	}
}
*/
class clsW3_qstring extends clsW3Code {
	public function parse() {
		$isDone = FALSE;
		for ($i = 0; ($ch=substr($input,$i,1)) && !$isDone; $i++) {
			switch ($ch) {
			case '\\':
				$inEsc = true;
				break;
			case '"':
				$isDone = !$inEsc;
			}
		}
	}
}
/*
 TOKEN TYPES - for future use:
	() phrase
	{} brace phrase
	non-quoted expression
	quoted string
*/
class clsW3ParsedLine {
	private $tokens;

	public function __construct($iTokens) {
		$this->tokens = $iTokens;
	}
}
class clsW3ParsedChunk {
	private $lines;	// array of clsW3ParsedLines

	public function __construct($iLines) {
		$this->lines = $iLines;
	}
}

class clsW3Command {
	private $statemt;
	private $clause;

	public function clsW3Command($iStatemt='', $iClause='') {
		$this->init($iStatemt, $iClause);
	}
	public function init($iStatemt, $iClause) {
		$this->statemt = trim($iStatemt);
		$this->clause = $iClause;
	}
	public function execute($iStatemt, $iClause) {	// init + exec
		$this->init($iStatemt, $iClause);
		return $this->exec();
	}
	public function exec() {
		global $w3step;
	
		$strStmt = $this->statemt;
		if ($w3step) {
			$out = '<br><b>CMD</b>: {'.$strStmt.'} <b>CLAUSE</b>: {'.$this->clause.'}';
		}
/*
A command is in any of these forms:
	variable action-operator expression;
	expression;
	keyword (expression) {clause} [keyword (expression) {clause} ... ];
An expression is in any of these forms:
	function(expression[, expression[, ...]])
	variable compare-operator expression
	variable
	constant
A constant is either a quoted string or a number
A variable is invoked by name


*/
// parse the command:
		for ($i = 0; $ch=substr($strStmt,$i,1); $i++) {
			$isArgs = FALSE;
			if ($isTok) {
				//if (!$inQuo) {
				if ($cmd == '') {
					$cmd = $phrase;
					$phrase = '';
				}
			} elseif ($isArgs) {
				$args = $phrase;
				$phrase = '';
			} else {
				$phrase .= $ch;
			}
		}
		W3AddTrace('TPL layer 2: CMD=[<u>'.$cmd.'</u>] ARGS=[<u>'.$args.'</u>] leftover=[<u>'.$phrase.'</u>]');
//		return $out;
	}
}
