<?php
/**
 * @package Wsdl2PhpGenerator
 */
namespace Wsdl2PhpGenerator;

/**
 * Very stupid data type to use instead of array
 *
 * @package Wsdl2PhpGenerator
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class Variable
{
    /**
     * @var string The type
     */
    private $type;

    /**
     * @var string The name
     */
    private $name;

    /**
     * @var boolean nullable
     */
    private $nullable;

    /** @var string Extra Type for Enum variables */
    private $strExtraType;

    /** @var string Validated value for the current type*/
    private $strValidatedType;

	/**
	 * @param string $type
	 * @param string $name
	 * @param bool $nullable
	 * @param $strExtraType
	 */
    public function __construct($type, $name, $nullable, $strExtraType)
    {
        $this->type				= $type;
        $this->name				= $name;
        $this->nullable			= $nullable;
        $this->strExtraType		= $strExtraType;
        $this->strValidatedType	= Validator::validateType($this->type);
    }

    /**
     * @return string
     */
    public function getType(): string
	{
        return $this->type;
    }

	/**
	 * @param bool $bForceNull
	 *
	 * @return string
	 */
    public function getTypeHint(bool $bForceNull = false): string
	{
		if($this->strExtraType !== '')
		{
			return '';
		}

		$strNull	= $bForceNull || $this->nullable ? '?' : '';

		if(strpos($this->strValidatedType, '[]') !== false)
		{
			return $strNull.'array';
		}

		return $strNull.$this->strValidatedType;
	}

	/**
	 * @return string
	 */
	public function getCommentType(): string
	{
		$strCommentType	= $this->strValidatedType;

		if($this->strExtraType !== '')
		{
			$strCommentType	.='|'.$this->strExtraType;
		}

		if($this->nullable)
		{
			$strCommentType = 'null|'.$strCommentType;
		}

		return $strCommentType;
	}

    /**
     * @return string
     */
    public function getName(): string
	{
        return $this->name;
    }

    /**
     * @return boolean
     */
    public function getNullable(): bool
	{
        return $this->nullable;
    }

	/**
	 * @return string
	 */
    public function getExtraType(): string
	{
		return $this->strExtraType;
	}

    /**
     * @return boolean
     */
    public function isArray(): bool
	{
        return substr($this->type, -2, 2) === '[]';
    }
}
