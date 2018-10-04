<?php
/**
 * @package phpSource
 */
namespace Wsdl2PhpGenerator\PhpSource;

/**
 * Class that represents the source code for a function in php
 *
 * @package phpSource
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class PhpFunction extends PhpElement
{
    /**
     *
     * @var string String containing the params to the function
     * @access private
     */
    private $params;

    /**
     *
     * @var string The code inside {}
     * @access private
     */
    private $source;

    /**
     *
     * @var PhpDocComment A comment in phpdoc format that describes the function
     * @access private
     */
    private $comment;

	/**
	 *
	 * @var string PHP 7.1 Return type
	 */
	private $return;

	/**
	 * @var bool true is return type is nullable
	 */
	private $bReturnIsNullable;


	/**
	 *
	 * @param string        $access
	 * @param string        $identifier
	 * @param string        $params
	 * @param string        $source
	 * @param PhpDocComment $comment
	 * @param string        $strReturn
	 * @param bool          $bReturnIsNullable
	 */
    public function __construct($access, $identifier, $params, $source, PhpDocComment $comment = null, string $strReturn = '', bool $bReturnIsNullable = false)
    {
        $this->access				= $access;
        $this->identifier			= $identifier;
        $this->params				= $params;
        $this->source				= $source;
        $this->comment				= $comment;
        $this->return				= $strReturn;
        $this->bReturnIsNullable	= $bReturnIsNullable;
    }

    /**
     *
     * @return string Returns the complete source code for the function
     * @access public
     */
    public function getSource(): string
    {
        $ret = '' . PHP_EOL;

        if ($this->comment !== null) {
            $ret .= $this->getSourceRow($this->comment->getSource());
        }

		$strReturn = '';

		if($this->return !== '')
		{
			$strReturn	= ': ';

			if($this->bReturnIsNullable)
			{
				$strReturn .= '?';
			}

			$strReturn .= $this->return;
		}

        $ret .= $this->getSourceRow($this->access . ' function ' . $this->identifier . '(' . $this->params . ')'.$strReturn);
        $ret .= $this->getSourceRow('{');
        $ret .= $this->getSourceRow($this->source);
        $ret .= $this->getSourceRow('}');

        return $ret;
    }
}
