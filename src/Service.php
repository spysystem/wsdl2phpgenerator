<?php

/**
 * @package Wsdl2PhpGenerator
 */
namespace Wsdl2PhpGenerator;

use Wsdl2PhpGenerator\PhpSource\PhpClass;
use Wsdl2PhpGenerator\PhpSource\PhpDocComment;
use Wsdl2PhpGenerator\PhpSource\PhpDocElementFactory;
use Wsdl2PhpGenerator\PhpSource\PhpFunction;
use Wsdl2PhpGenerator\PhpSource\PhpVariable;

/**
 * Service represents the service in the wsdl
 *
 * @package Wsdl2PhpGenerator
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class Service implements ClassGenerator
{

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var PhpClass The class used to create the service.
     */
    private $class;

    /**
     * @var string The name of the service
     */
    private $identifier;

    /**
     * @var Operation[] An array containing the operations of the service
     */
    private $operations;

    /**
     * @var string The description of the service used as description in the phpdoc of the class
     */
    private $description;

    /**
     * @var Type[] An array of Types
     */
    private $types;

    /**
     * @param ConfigInterface $config Configuration
     * @param string $identifier The name of the service
     * @param array $types The types the service knows about
     * @param string $description The description of the service
     */
    public function __construct(ConfigInterface $config, $identifier, array $types, $description)
    {
        $this->config = $config;
        $this->identifier = $identifier;
        $this->description = $description;
        $this->operations = [];
        $this->types = [];
        foreach ($types as $type) {
            $this->types[$type->getIdentifier()] = $type;
        }
    }

	/**
	 * @return PhpClass Returns the class, generates it if not done
	 * @throws \Exception
	 */
    public function getClass(): PhpClass
    {
        if ($this->class === null) {
            $this->generateClass();
        }

        return $this->class;
    }

    /**
     * Returns an operation provided by the service based on its name.
     *
     * @param string $operationName The name of the operation.
     *
     * @return Operation|null The operation or null if it does not exist.
     */
    public function getOperation($operationName): ?Operation
	{
        return $this->operations[$operationName] ?? null;
    }

    /**
     * Returns the description of the service.
     *
     * @return string The service description.
     */
    public function getDescription(): string
	{
        return $this->description;
    }

    /**
     * Returns the identifier for the service ie. the name.
     *
     * @return string The service name.
     */
    public function getIdentifier(): string
	{
        return $this->identifier;
    }

    /**
     * Returns a type used by the service based on its name.
     *
     * @param string $identifier The identifier for the type.
     *
     * @return Type|null The type or null if the type does not exist.
     */
    public function getType($identifier): ?Type
	{
        return $this->types[$identifier] ?? null;
    }
    /**
     * Returns all types defined by the service.
     *
     * @return Type[] An array of types.
     */
    public function getTypes(): array
	{
        return $this->types;
    }

	/**
	 * @param string $strContent
	 * @param int    $iExtraTabs
	 * @return mixed
	 */
	protected function adjustArrayNotation(string $strContent, int $iExtraTabs = 0)
	{
		$strReturn	= $this->config->get('bracketedArrays')? str_replace(array('array (', ')'), array('[', ']'), $strContent): $strContent;
		$strReturn	= preg_replace('/ {2}/m', "\t", $strReturn);

		return $this->alignArrayItems($strReturn, $iExtraTabs);
	}

	/**
	 * @param string $strString
	 * @param int    $iExtraTabs
	 *
	 * @return string
	 */
	public function alignArrayItems(string $strString, int $iExtraTabs): string
	{
		$arrLines	= explode(PHP_EOL, $strString);
		$strReturn	= '';

		$iMaxIndexLength	= 0;
		# first loop: find biggest word on left side
		foreach($arrLines as $strLine)
		{
			if(strpos($strLine, '=>') !== false)
			{
				$arrTokens      = explode('=>', $strLine);
				$iCurrentLength = \strlen(trim($arrTokens[0]));
				if($iCurrentLength > $iMaxIndexLength)
				{
					$iMaxIndexLength	= $iCurrentLength;
				}
			}
		}

		# Calculate number of tabs to cover the longest index
		$iMaxTabs	= (int)ceil($iMaxIndexLength/4) + ( $iMaxIndexLength % 4 === 0 ? 1 : 0);

		foreach ($arrLines as $strLine)
		{
			if(strpos($strLine, '=>') !== false)
			{
				$arrTokens		= explode('=>', $strLine);
				$iCurrentLength	= \strlen(trim($arrTokens[0]));
				$iTabsToAdd		= $iMaxTabs - (int)floor($iCurrentLength / 4);
				$strLine		= rtrim($arrTokens[0]).str_repeat("\t", $iTabsToAdd).'=> '.trim($arrTokens[1]);
			}

			$strReturn	.= ($strReturn === '' ? $strLine : str_repeat("\t", $iExtraTabs) . $strLine) . PHP_EOL;
		}

		return trim($strReturn, PHP_EOL);
	}

	/**
	 * Generates the class if not already generated
	 *
	 * @throws \Exception
	 */
    public function generateClass(): void
    {
        $arrayPrefix = $this->config->get('bracketedArrays')? '[': 'array(';
        $arraySuffix = $this->config->get('bracketedArrays')? ']': ')';

        $name = $this->identifier;

        // Generate a valid class name
        $name = Validator::validateClass($name, $this->config->get('namespaceName'));

        // uppercase the name
        $name = ucfirst($name);

		$strDescription = "Class $name \n".$this->description;
		// Create the class object
		$oClassComment = new PhpDocComment($strDescription);
		$oClassComment->setPackage(PhpDocElementFactory::getPackage($this->config->get('namespaceName')));
		$strSoapClientClass	= $this->config->get('soapClientClass');
		$this->class = new PhpClass($name, false, substr(strrchr($strSoapClientClass, "\\"), 1), $oClassComment);

		$this->class->addConstant($this->config->get('inputFile'), 'WsdlUrl');
		$this->class->addUseClause(trim($strSoapClientClass, '\\'));
		$this->class->addUseClause('Exception');
		$this->class->addUseClause('SoapFault');

        $oCreateComment	= new PhpDocComment();
        $oCreateComment->setReturn(PhpDocElementFactory::getReturn($name, ''));

        $arrParameters			= [];
        $arrSoapClientOptions	= $this->config->get('soapClientOptions');

		if(isset($arrSoapClientOptions['login']))
		{
			$arrSoapClientOptions['login']	= 'USERNAME-PLACEHOLDER';
			$arrParameters[]				= 'string $strUsername';
			$oCreateComment->addParam(PhpDocElementFactory::getParam('string', 'strUsername', 'Username for Basic Authentication'));
		}

		if(isset($arrSoapClientOptions['password']))
		{
			$arrSoapClientOptions['password']	= 'PASSWORD-PLACEHOLDER';
			$arrParameters[]					= 'string $strPassword';
			$oCreateComment->addParam(PhpDocElementFactory::getParam('string', 'strPassword', 'Password for Basic Authentication'));
		}

		if($arrSoapClientOptions['useLocationInsideSoapClientOptions'])
		{
			unset($arrSoapClientOptions['useLocationInsideSoapClientOptions']);
			$arrSoapClientOptions['location']	= 'LOCATION-PLACEHOLDER';
		}

		$arrParameters[]	= 'string $strURL = self::WsdlUrl';
		$oCreateComment->addParam(PhpDocElementFactory::getParam('string', 'strURL', 'URL or path for WSDL file'));
		$oCreateComment->addThrows(PhpDocElementFactory::getThrows('Exception', ''));

        $strCreateSource	= '	return new static(' . $this->adjustArrayNotation(var_export($arrSoapClientOptions, true), 1) . ', $strURL);' . PHP_EOL;

        $strCreateSource	= str_replace(["'USERNAME-PLACEHOLDER'", "'PASSWORD-PLACEHOLDER'", "'LOCATION-PLACEHOLDER'"], ['$strUsername', '$strPassword', '$strURL'], $strCreateSource);

        $oCreateFunction	= new PhpFunction('public static', 'CreateService', implode(', ', $arrParameters), $strCreateSource, $oCreateComment, $name);


        $this->class->addFunction($oCreateFunction);

        // Create the constructor
        $comment = new PhpDocComment();
        $comment->addParam(PhpDocElementFactory::getParam('string', 'strWsdl', 'The wsdl file to use'));
        $comment->addParam(PhpDocElementFactory::getParam('array', 'arrOptions', 'A array of config values'));
        $comment->addThrows(PhpDocElementFactory::getThrows('Exception', ''));

        $source = '
	if ($strWsdl === \'\')
	{
		throw new Exception(\'Missing WSDL!\');
	}
	foreach (self::$arrClassMap as $strKey => $mValue)
	{
		if (!isset($arrOptions[\'classmap\'][$strKey]))
		{
			$arrOptions[\'classmap\'][$strKey]	= $mValue;
		}
	}' . PHP_EOL;
        $source .= '	parent::__construct($strWsdl, $arrOptions);' . PHP_EOL;

        $function = new PhpFunction('public', '__construct', 'array $arrOptions = ' . $arrayPrefix.$arraySuffix . ', string $strWsdl = self::WsdlUrl', $source, $comment);

        // Add the constructor
        $this->class->addFunction($function);

        #region __doRequest

		$comment = new PhpDocComment();
		$comment->addParam(PhpDocElementFactory::getParam('string', 'request', ''));
		$comment->addParam(PhpDocElementFactory::getParam('string', 'location', ''));
		$comment->addParam(PhpDocElementFactory::getParam('string', 'action', ''));
		$comment->addParam(PhpDocElementFactory::getParam('int', 'version', ''));
		$comment->addParam(PhpDocElementFactory::getParam('int', 'one_way', ''));
		$comment->setReturn(PhpDocElementFactory::getReturn('string|null', ''));

		$source	= '
	$this->request	= $request;
	
	return parent::__doRequest($request, $location, $action, $version, $one_way);' . PHP_EOL;

		$function = new PhpFunction('public', '__doRequest', '$request, $location, $action, $version, $one_way = 0', $source, $comment, 'string', true);

		$this->class->addFunction($function);

		#endregion

        #region __doRequest

		$comment = new PhpDocComment();
		$comment->setReturn(PhpDocElementFactory::getReturn('string', ''));

		$source	= '
		return $this->request ?? \'\';' . PHP_EOL;

		$function = new PhpFunction('public', '__getLastRequest', '', $source, $comment, 'string');

		$this->class->addFunction($function);

		#endregion

        // Generate the classmap
        $name = 'arrClassMap';
        $comment = new PhpDocComment();
        $comment->setVar(PhpDocElementFactory::getVar('array', $name, 'The defined classes'));

        $init = [];
        foreach ($this->types as $type) {
            if ($type instanceof ComplexType) {
                $init[$type->getIdentifier()] = $type->getPhpIdentifier().'::class';
            }
        }

        $strClassMap = $this->adjustArrayNotation(var_export($init, true));

        $strClassMap	= preg_replace("/=> '(.*)'/m", '=> $1', $strClassMap);

        $var = new PhpVariable('private static', $name, $strClassMap, $comment);

        // Add the classmap variable
        $this->class->addVariable($var);

        $oRequestComment	= new PhpDocComment();
        $oRequestComment->setVar(PhpDocElementFactory::getVar('string', 'request', 'Last request made'));
        $oRequestVar	= new PhpVariable('private ', 'request', "''", $oRequestComment);

        $this->class->addVariable($oRequestVar);

        // Add all methods
        foreach ($this->operations as $operation) {
            $name = Validator::validateOperation($operation->getName());

            $comment = new PhpDocComment($operation->getDescription());
            $comment->setReturn(PhpDocElementFactory::getReturn($operation->getReturns().'|null', ''));
            $comment->addThrows(PhpDocElementFactory::getThrows('SoapFault', ''));

            foreach ($operation->getParams() as $param => $hint) {
                $arr = $operation->getPhpDocParams($param, $this->types);
                $comment->addParam(PhpDocElementFactory::getParam($arr['type'], $arr['name'], $arr['desc']));
            }

            $source = "\t".'return $this->__soapCall(\'' . $operation->getName() . '\', ' . $arrayPrefix . $operation->getParamStringNoTypeHints() . $arraySuffix . ');' . PHP_EOL;

            $paramStr = $operation->getParamString($this->types);

            $strReturnType	= '';

            if(array_key_exists($operation->getReturns(), $init))
			{
				$strReturnType = $operation->getReturns();
			}

            $function = new PhpFunction('public', $name, $paramStr, $source, $comment, $strReturnType, true);

            if (!$this->class->functionExists($function->getIdentifier())) {
                $this->class->addFunction($function);
            }
        }
    }

    /**
     * Add an operation to the service.
     *
     * @param Operation $operation The operation to be added.
     */
    public function addOperation(Operation $operation): void
	{
        $this->operations[$operation->getName()] = $operation;
    }
}
