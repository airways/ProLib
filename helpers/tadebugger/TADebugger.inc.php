<?php

$sharedDebugger = new TADebugger();


function TAenableHandlers() {

	set_exception_handler( 'TAexception_handler' );
	set_error_handler( 'TAerror_handler' );
	
	trigger_error( "PHPDebugger started", E_USER_NOTICE );
	
	assert_options(ASSERT_ACTIVE, 		1);
	assert_options(ASSERT_WARNING, 		0);
	assert_options(ASSERT_QUIET_EVAL, 	1);
	assert_options(ASSERT_CALLBACK, 'TAAssert_handler');
	
} // TAenableHandlers

function TAexception_handler($exception) {
	
	global
		$sharedDebugger;
		
	if ( isset( $sharedDebugger ) ) {
		$sharedDebugger->postMessage( "PHP[EXC]: " . $exception->getMessage()  );		
	}

} // TAexception_handler


function TAAssert_handler( $file, $line, $code ) {
	
	global
		$sharedDebugger;

   echo "<hr>Assertion Failed:
       File '$file'<br />
       Line '$line'<br />
       Code '$code'<br /><hr />";
			
	if ( isset( $sharedDebugger ) ) {
		$sharedDebugger->postMessage( "PHP[EXC]: " . $exception->getMessage()  );		
	}

} // TAAssert_handler



function TAerror_handler($errno, $errstr, $errfile, $errline) {
	
	global
		$sharedDebugger;
		
	if ( isset( $sharedDebugger ) ) {
		$sharedDebugger->postSource( $errfile );
		
		if ( file_exists( $errfile ) ) {

			$theSource = file( $errfile );
			$sharedDebugger->postMessage( '  ... ' . $theSource[ $errline-1 ] );
			
		}
		
   	$simpErrStr = str_replace( $sharedDebugger->myAppBasePath, "./", $errstr );

	$errfile = $sharedDebugger->shrinkPathUsingAppBasePath( $errfile );
	$sharedDebugger->postMessage( "PHP[E " . $errno . "] '" . $simpErrStr . "' [" . $errfile . " : " . $errline . "]" );
	}

} // TAerror_handler


class TADebugger {
	
	var
		$myDebugHost,
		$myDebugPort,
		$myPDebugHost,
		$myPDebugPort,
		$myInspectedVariables,
		$myPostedFiles,
		$errortypes,
		$myAppBasePath;
	
	// public
	function TADebugger() {
		
		global
			$sharedDebugger;
			
		// Define deault values for PHPDebugger
		$this->myDebugHost = 'localhost';
		$this->myDebugPort = 8881;
		$this->myInspectedVariables = array();

		$this->myPostedFiles = array();
		$this->myAppBasePath = "";
		
		$this->errortypes = array (
	               E_ERROR              => 'Error',
	               E_WARNING            => 'Warning',
	               E_PARSE              => 'Parsing Error',
	               E_NOTICE            	=> 'Notice',
	               E_CORE_ERROR        	=> 'Core Error',
	               E_CORE_WARNING      	=> 'Core Warning',
	               E_COMPILE_ERROR      => 'Compile Error',
	               E_COMPILE_WARNING    => 'Compile Warning',
	               E_USER_ERROR       	=> 'User Error',
	               E_USER_WARNING      	=> 'User Warning',
	               E_USER_NOTICE        => 'User Notice',
	               E_STRICT            	=> 'Runtime Notice',
	               E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
	               );


		$this->enableStrictMode();
		TAenableHandlers();
		
		$this->setAppBasePath( dirname(  __FILE__ ) );
		
		if ( isset( $_SERVER['QUERY_STRING']  ) && ( '' != $_SERVER['QUERY_STRING']  )) {
	        $this->postMessage( ""  );	
	        $this->postMessage( ""  ); 			
			$this->postMessage( "Request :: " . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] );			
		} else {
	        $this->postMessage( ""  );	
	        $this->postMessage( ""  );			
			$this->postMessage( "Request :: " . $_SERVER['PHP_SELF'] );			
		}
		
	}

	function __destruct() {
		// Inform debugger, that invocation has finished
		$this->postMessage( "Request :: Done" );	
    } // __destruct
	
	function setAppBasePath( $aBasePath ) {
	
		$this->myAppBasePath = $aBasePath;
	} // setAppBasePath
	
	
	function enableStrictMode( ) {
		
		error_reporting( E_ALL | E_STRICT );

	} // enableStrictMode

	
	// public
	function setHost( $aHost ) {
		$this->myDebugHost = $aHost;
	}
	
	// public 
	function setPort( $aPort ) {
		$this->myDebugPort = $aPort;
	}
	
	// private
	function postData( $msg, $data_to_send ) {
	
	  $res = "";
	
	  $fp = fsockopen( $this->myDebugHost, $this->myDebugPort );
	
	  if ( !$fp ) {
    	return $res;
	  }

	
	  $completeMsg = "<debug><type>" . $msg . "</type><data>" . $data_to_send . "</data></debug>";
	
	  fputs( $fp, $completeMsg );
		
		
	  // currently we don't wait for backup from the debug console
	  fclose($fp);
	  return $res;
	
	  while(!feof($fp)) {
	      $res .= fgets($fp, 128);
	  }
	  fclose($fp);

	  return $res;
	}
	
	
	
	// public
	function postSource( $aFilePath ) {
		
		// zurück, falls schon einmal gesendet
		foreach( $this->myPostedFiles as $key => $value )
		{
			if ( $value === $aFilePath ) {
				return;
			}
		}
			
		if ( file_exists( $aFilePath ) ) {
			
			$sourceFile = file( $aFilePath );
			$sourceFile = implode( "", $sourceFile );
			
			$aFilePath = $this->shrinkPathUsingAppBasePath( $aFilePath );
			$sourceFile = "<name>" . base64_encode( $aFilePath ) . "</name><source>" . base64_encode( $sourceFile ) . "</source>";
		
			// $answer = $this->PostData( "source", $this->cDataElementRaw( $sourceFile ) );
			$answer = $this->postData( "source", $sourceFile );
			
		} else {
			echo 'dss';
		}

		array_push( $this->myPostedFiles, $aFilePath );		
		
	} // postSource
	
	// public
	function postState( ) {
		
		$theXMLPacket = '<state>';
		foreach( $this->myInspectedVariables as $numIndex => $infoPackage )
		{
			$infoString = $infoPackage['info'];
			$variable = $infoPackage['ref'];
			
			$theXMLPacket .= '<var>';
			$theXMLPacket .= $this->cDataElement( 'info', base64_encode( $infoString ) );

			if ( is_array( $variable ) ) {
				$theXMLPacket .= $this->cDataElement( 'value', base64_encode( var_export($variable, true) ) );				
			} else if ( is_string( $variable ) ) {
				$theXMLPacket .= $this->cDataElement( 'value', base64_encode( '"' . $variable . '"' ) );				
			} else {
				$theXMLPacket .= $this->cDataElement( 'value', base64_encode( $variable ) );
			}

			$theXMLPacket .= '</var>';
		}
		$theXMLPacket .= '</state>';
				
		$answer = $this->postData( "state", $theXMLPacket  );
		
		
	} //postState
	
	// public
	function postTrace( ) {

		$theXMLPacket = $this->getXMLForBacktrace();
		
		$answer = $this->postData( "trace", $theXMLPacket  );
		
	} // postTrace
	
	
	
	// public
	function postMessage( $aMessage ) {

		$answer = $this->postData( "message", $this->cDataElementRaw( $aMessage ) );
		
	} // postMessage
	
	
	
	// private
	function cDataElement( $elementName, $value ) {

		// <![CDATA[alpha]]>
		return "<" . $elementName . "><![CDATA[" . $value . "]]></" . $elementName. ">";

		
	}
	
	// private
	function cDataElementRaw( $value ) {

		// <![CDATA[alpha]]>
		return "<![CDATA[" . $value . "]]>";

	}

	// private
	function shrinkPathUsingAppBasePath( $aPath ) {
		
		$fileName = $aPath;
		if ( $this->myAppBasePath === substr( $aPath , 0, strlen($this->myAppBasePath)) ) {
			$fileName = './' . substr( $aPath, -(strlen($aPath)-strlen($this->myAppBasePath)) );
		}
		
		return $fileName;
	}
	
	// private
	function getXMLForBacktrace() {
	
		$rawTrace = debug_backtrace();
		if ( ! is_array( $rawTrace ) ) {
			return '';
		}
		
		
		
		$xmlResult = '<trace>';
		
		foreach( $rawTrace as $callID => $callDetails )
		{

			if (  ( isset( $callDetails['class'] ) )
			   && ( 'TADebugger' === $callDetails['class'] )
			   ) {
				continue;
			}
			$xmlResult .= '<call>';

			foreach( $callDetails as $callProperty => $callPropertyValue )
			{
				switch( $callProperty ) {
					case 'file':
					
						$xmlResult .= $this->cDataElement( 'file', $this->shrinkPathUsingAppBasePath( $callPropertyValue ) );
						$this->postSource( $callPropertyValue );
						break;
						
					case 'line':
						$xmlResult .= $this->cDataElement( 'line', $callPropertyValue );
						break;
						
					case 'function':
						$xmlResult .= $this->cDataElement( 'function', $callPropertyValue );
						break;
						
					case 'class':
						$xmlResult .= $this->cDataElement( 'class', $callPropertyValue );
						break;
						
					case 'object':
/*
["object"]=>
object(CToDoList)#3 (3) {
  ["myDBLink:protected"]=>
  resource(23) of type (mysql link)
  ["myResult:// private"]=>
  bool(true)
  ["myQuery:// private"]=>
  string(90) "INSERT INTO toDoItem ( refToDoList, title, sortIndex,  done)   VALUES ( 3, 'sdf' , -1, 0 )"
}
*/						
						break;
					case 'type':
						$xmlResult .= $this->cDataElement( 'type', $callPropertyValue );
						break;
					case 'args':
						$xmlResult .= '<args>';
				    	foreach( $callPropertyValue as $varNameType => $varValue )
				    	{
							if ( is_string( $varValue ) ) {
								$xmlResult .= $this->cDataElement( 'arg', '"' . $varValue . '"' );
							} else {
								$xmlResult .= $this->cDataElement( 'arg', $varValue);
							}
				    	} 
						$xmlResult .= '</args>';
						break;
					case 'file':
						break;
				} // switch
				
			} // for
			
		$xmlResult .= '</call>';
			

		} // for
		
		$xmlResult .= '</trace>';
		
		
		return $xmlResult;
		
	} // getXMLForBacktrace


	// public
	function traceVariable( &$aVariable, $infoString ) {
		
		$infoPackage = array(
				'info' => $infoString,
				'ref' => &$aVariable
			);
			
		array_push( $this->myInspectedVariables, $infoPackage );
	}

	// private
	function string2XML( $aVariable ) {

		return $aVariable;
		
	} // string2XML
	
}





?>