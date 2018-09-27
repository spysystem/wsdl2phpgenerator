<?php /** @noinspection ThrowRawExceptionInspection */

/**
 * @package phpSource
 */
namespace Wsdl2PhpGenerator\PhpSource;

use Exception;

/**
 * Class that represents the source code for a class in php
 *
 * @package phpSource
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class PhpClass extends PhpElement
{
    /**
     *
     * @var array An array of strings, contains all the file names to include for the class
     * @access private
     */
    private $dependencies;

    /**
     *
     * @var bool If the class should be protected by a if(!class_exists() statement
     * @access private
     */
    private $classExists;

    /**
     *
     * @var bool If the class is final
     * @access private
     */
    private $final;

    /**
     *
     * @var string
     * @access private
     */
    private $extends;

    /**
     *
     * @var string[]
     * @access private
     */
    private $implements;

    /**
     *
     * @var string
     * @access private
     */
    private $default;

    /**
     *
     * @var array Array of constants key = name of constant value = value of constant
     */
    private $constants;

    /**
     *
     * @var PhpVariable[]
     * @access private
     */
    private $variables;

    /**
     *
     * @var PhpFunction[]
     * @access private
     */
    private $functions;

    /**
     *
     * @var PhpDocComment A description of the class in phpdoc format
     * @access private
     */
    private $comment;

	/**
	 *
	 * @var string[] Array of strings containing "use" clauses
	 * @access private
	 */
    private $uses;

    /**
     *
     * @var bool If the class is abstract.
     * @access private
     */
    private $abstract;

    /**
     *
     * @param string $identifier
     * @param bool $classExists
     * @param string $extends A string of the class that this class extends
     * @param PhpDocComment $comment
     * @param bool $final
     * @param bool $abstract
     */
    public function __construct($identifier, $classExists = false, $extends = '', PhpDocComment $comment = null, $final = false, $abstract = false)
    {
        $this->dependencies = [];
        $this->classExists = $classExists;
        $this->comment = $comment;
        $this->final = $final;
        $this->identifier = $identifier;
        $this->access = '';
        $this->extends = $extends;
        $this->implements = [];
        $this->constants = [];
        $this->variables = [];
        $this->functions = [];
        $this->indentionStr = "\t"; // Use tab as indention
        $this->abstract = $abstract;
        $this->uses = [];
    }

    /**
     *
     * @return string Returns the compete source code for the class
     */
    public function getSource(): string
    {
        $ret = '';

        if ($this->classExists) {
            $ret .= 'if (!class_exists("' . $this->identifier . '", false)) ' . PHP_EOL . '{' . PHP_EOL;
        }

        if (\count($this->dependencies) > 0) {
            foreach ($this->dependencies as $file) {
                $ret .= 'include_once(\'' . $file . '\');' . PHP_EOL;
            }
            $ret .= PHP_EOL;
        }

		if(\count($this->uses) > 0)
		{
			sort($this->uses);
			foreach($this->uses as $strUseClause)
			{
				$ret .= 'use '.$strUseClause.';'.PHP_EOL;
			}
		}

        if ($this->comment !== null) {
            $ret .= $this->comment->getSource();
        }

        if ($this->final) {
            $ret .= 'final ';
        }

        if ($this->abstract) {
            $ret .= 'abstract ';
        }

        $ret .= 'class ' . $this->identifier;

        if (!empty($this->extends)) {
            $ret .= ' extends ' . $this->extends;
        }

        if (\count($this->implements) > 0) {
            $ret .= ' implements ' . implode(', ', $this->implements);
        }

        $ret .= PHP_EOL . '{' . PHP_EOL;

        if ($this->default !== null) {
            $ret .= $this->getIndentionStr() . 'const __default = ' . $this->default . ';' . PHP_EOL;
        }

        if (\count($this->constants) > 0) {
            foreach ($this->constants as $name => [$access, $value]) {
                $ret .= $this->getIndentionStr() . $access .' const ' . $name . ' = \'' . $value . '\';' . PHP_EOL;
            }
            $ret .= PHP_EOL;
        }

        if (\count($this->variables) > 0) {
            foreach ($this->variables as $variable) {
                $variable->setIndentionStr($this->getIndentionStr());
                $ret .= $variable->getSource();
            }
        }

        if (\count($this->functions) > 0) {
            foreach ($this->functions as $function) {
                $function->setIndentionStr($this->getIndentionStr());
                $ret .= $function->getSource();
            }
        }

        $ret .= PHP_EOL . '}' . PHP_EOL;

        if ($this->classExists) {
            $ret .= PHP_EOL . '}' . PHP_EOL;
        }

        return $ret;
    }

    /**
     * Adds a dependency to be loaded for the class to use
     * Only adds it if it does not already exist
     *
     * @param string $filename
     */
    public function addDependency($filename): void
	{
        if (!\in_array($filename, $this->dependencies, true)) {
            $this->dependencies[] = $filename;
        }
    }

	/**
	 * Adds a use clause to the top of the file.
	 *
	 * @param $strUse
	 */
	public function addUseClause($strUse): void
	{
		if(!\in_array($strUse, $this->uses, true))
		{
			$this->uses[]	= $strUse;
		}
	}

    /**
     * @param string|\string[] $classes  $filename
     */
    public function addImplementation($classes): void
	{
        $classes = (array)$classes;
        $this->implements = array_merge($this->implements, $classes);
    }

    /**
     * Set default value
     *
     * @param $const
     */
    public function setDefault($const): void
	{
        $this->default = $const;
    }

	/**
	 * Adds a constant to the class. If no name is supplied and the value is a string the value is used as name otherwise exception is raised
	 *
	 * @param mixed  $value
	 * @param string $name
	 * @param string $strAccess
	 *
	 * @throws Exception
	 */
    public function addConstant($value = null, $name = '', string $strAccess = PhpElement::Access_Public): void
	{
        if ($value === null) {
            throw new Exception('No value supplied');
        }

        // If no name is supplied use the value as name
        if ($name === '') {
            if (\is_string($value)) {
                $name = $value;
            } else {
                throw new Exception('No name supplied');
            }
        }

        if (array_key_exists($name, $this->constants)) {
            throw new Exception('A constant of the name (' . $name . ') does already exist.');
        }

        $this->constants[$name] = [$strAccess, $value];
    }

    /**
     * Adds a variable to the class
     * Throws Exception if the variable does already exist
     *
     * @param PhpVariable $variable The variable object to add
     * @access public
     * @throws Exception If the variable name already exists
     */
    public function addVariable(PhpVariable $variable): void
	{
        if ($this->variableExists($variable->getIdentifier())) {
            throw new Exception('A variable of the name (' . $variable->getIdentifier() . ') does already exist.');
        }

        $this->variables[$variable->getIdentifier()] = $variable;
    }

    /**
     * Adds a function to the class
     * Overwrites
     *
     * @param PhpFunction $function The function object to add
     * @access public
     * @throws Exception If the function name already exists
     */
    public function addFunction(PhpFunction $function): void
	{
        if ($this->functionExists($function->getIdentifier())) {
            throw new Exception('A function of the name (' . $function->getIdentifier() . ') does already exist.');
        }

        $this->functions[$function->getIdentifier()] = $function;
    }

    /**
     * Checks if a variable with the same name does already exist
     *
     * @access public
     * @param string $identifier
     * @return bool
     */
    public function variableExists($identifier): bool
	{
        return array_key_exists($identifier, $this->variables);
    }

    /**
     * Checks if a function with the same name does already exist
     *
     * @access public
     * @param string $identifier
     * @return bool
     */
    public function functionExists($identifier): bool
	{
        return array_key_exists($identifier, $this->functions);
    }
}
