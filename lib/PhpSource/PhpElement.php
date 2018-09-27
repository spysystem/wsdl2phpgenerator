<?php
/**
 * @package phpSource
 */
namespace Wsdl2PhpGenerator\PhpSource;

/**
 * Abstract base class for all PHP elements, variables, functions and classes etc.
 *
 * @package phpSource
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
abstract class PhpElement
{
	public const Access_Public				= 'public';
	public const Access_Protected			= 'protected';
	public const Access_Private				= 'private';
	public const Access_PublicStatic		= 'public static';
	public const Access_ProtectedStatic		= 'protected static';
	public const Access_PrivateStatic		= 'private static';

    /**
     *
     * @var string The access of the function |public|private|protected
     * @access protected
     */
    protected $access;

    /**
     *
     * @var string The identifier of the element
     * @access protected
     */
    protected $identifier;

    /**
     *
     * @var string The string to use for indention for the element
     */
    protected $indentionStr;

    /**
     * Function to be overloaded, return the source code of the specialized element
     *
     * @access public
     * @return string
     */
    abstract public function getSource(): string;

    /**
     *
     * @return string The access of the element
     */
    public function getAccess(): string
	{
        return $this->access;
    }

    /**
     *
     * @return string The identifier, name, of the element
     */
    public function getIdentifier(): string
	{
        return $this->identifier;
    }

    /**
     *
     * @return string Returns the indention string
     */
    public function getIndentionStr(): string
	{
        return $this->indentionStr;
    }

    /**
     *
     * @param string $indentionStr Sets the indention string to use
     */
    public function setIndentionStr($indentionStr): void
	{
        $this->indentionStr = $indentionStr;
    }

    /**
     * Takes a string and prepends ith with the current indention string
     * Has support for multiple lines
     *
     * @param string $source
     * @return string
     */
    public function getSourceRow($source): string
	{
        if (strpos($source, PHP_EOL) === false) {
            return $this->indentionStr . $source . PHP_EOL;
        }

        $ret = '';
        $rows = explode(PHP_EOL, $source);
        if (trim($rows[0]) === '') {
            $rows = array_splice($rows, 1);
        }
        if (trim($rows[\count($rows) - 1]) === '') {
            $rows = array_splice($rows, 0, \count($rows) - 1);
        }
        foreach ($rows as $row) {
            $ret .= $this->indentionStr . $row . PHP_EOL;
        }

        return $ret;
    }
}
