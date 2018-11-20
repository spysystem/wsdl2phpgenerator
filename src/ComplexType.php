<?php

/**
 * @package Generator
 */
namespace Wsdl2PhpGenerator;

use DateTime;
use \Exception;
use RuntimeException;
use Wsdl2PhpGenerator\PhpSource\PhpClass;
use Wsdl2PhpGenerator\PhpSource\PhpDocComment;
use Wsdl2PhpGenerator\PhpSource\PhpDocElementFactory;
use Wsdl2PhpGenerator\PhpSource\PhpFunction;
use Wsdl2PhpGenerator\PhpSource\PhpVariable;

/**
 * ComplexType
 *
 * @package Wsdl2PhpGenerator
 * @author Fredrik Wallgren <fredrik.wallgren@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
class ComplexType extends Type
{
    /**
     * Base type that the type extends
     *
     * @var ComplexType
     */
    protected $baseType;

    /**
     * The members in the type
     *
     * @var Variable[]
     */
    protected $members;

    /**
     * @var
     */
    protected $abstract;

    protected $bHasDateTimeUseClause	= false;

    /**
     * Construct the object
     *
     * @param ConfigInterface $config The configuration
     * @param string $name The identifier for the class
     */
    public function __construct(ConfigInterface $config, $name)
    {
        parent::__construct($config, $name, null);
        $this->members = [];
        $this->baseType = null;
        $this->abstract = false;
    }

    /**
     * Implements the loading of the class object
     *
     * @throws Exception if the class is already generated(not null)
     */
    protected function generateClass(): void
    {
        if ($this->class !== null) {
            throw new RuntimeException('The class has already been generated');
        }

        $classBaseType = $this->getBaseTypeClass();

        $oClassComment	= new PhpDocComment('Class '.$this->phpIdentifier);

        $this->class = new PhpClass(
            $this->phpIdentifier,
            false,
            $classBaseType,
            $oClassComment,
            false,
            $this->abstract
        );

        $constructorComment = new PhpDocComment();
        $constructorSource = '';
        $constructorParameters = [];
        $accessors = [];

        // Add base type members to constructor parameter list first and call base class constructor
        $parentMembers = $this->getBaseTypeMembers($this);
        if (!empty($parentMembers)) {
            foreach ($parentMembers as $member) {
                $name = Validator::validateAttribute($member->getName());

                if (!$member->getNullable()) {
                    $constructorComment->addParam(PhpDocElementFactory::getParam($member->getCommentType(), $name, ''));
                    $constructorParameters[$name]	= $member->getTypeHint();
                }
            }
            $constructorSource .= "\t".'parent::__construct(' . $this->buildParametersString($constructorParameters, false) . ');' . PHP_EOL;
        }

        // Add member variables
        foreach ($this->members as $member) {
            $type = Validator::validateType($member->getType());

            $strCommentType	= $member->getCommentType();

            $name = Validator::validateAttribute($member->getName());
            $strTypeHint	= $member->getTypeHint();

            $comment = new PhpDocComment();
            $comment->setVar(PhpDocElementFactory::getVar($strCommentType, $name, ''));
            $var = new PhpVariable('protected', $name, '', $comment);
            $this->class->addVariable($var);

            if (!$member->getNullable()) {
                if ($type === DateTime::class) {
                	if(!$this->bHasDateTimeUseClause)
					{
						$this->class->addUseClause(DateTime::class);
						$this->bHasDateTimeUseClause	= true;
					}
                    if ($this->config->get('constructorParamsDefaultToNull')) {
                        $constructorSource .= "\t".'$this->' . $name . ' = $' . $name . ' ? $' . $name . '->format(\DateTime::ATOM) : null;' . PHP_EOL;
                    } else {
                        $constructorSource .= "\t".'$this->' . $name . ' = $' . $name . '->format(\DateTime::ATOM);' . PHP_EOL;
                    }
                } else {
                    $constructorSource .= "\t".'$this->' . $name . ' = $' . $name . ';' . PHP_EOL;
                }
                $constructorComment->addParam(PhpDocElementFactory::getParam($strCommentType, $name, ''));
                $constructorParameters[$name] = $strTypeHint;
            }

            $getterComment = new PhpDocComment();
            $getterComment->setReturn(PhpDocElementFactory::getReturn($strCommentType, ''));
            if ($type === DateTime::class) {
                $getterCode =
					"\t".'if ($this->' . $name . ' == null)' . PHP_EOL .
					"\t".'{' . PHP_EOL .
                    "\t\t".'return null;' . PHP_EOL .
					"\t".'}' . PHP_EOL .
					"\t".'else' . PHP_EOL .
					"\t".'{' . PHP_EOL .
					"\t\t".'try' . PHP_EOL .
					"\t\t".'{' . PHP_EOL .
					"\t\t\t".'return new \DateTime($this->' . $name . ');' . PHP_EOL .
					"\t\t".'}' . PHP_EOL .
					"\t\t".'catch (\Exception $oException)' . PHP_EOL .
					"\t\t".'{' . PHP_EOL .
					"\t\t\t".'return false;' . PHP_EOL .
					"\t\t".'}' . PHP_EOL .
					"\t".'}' . PHP_EOL;
            } else {
                $getterCode = "\t".'return $this->' . $name . ';' . PHP_EOL;
            }
            $getter = new PhpFunction('public', 'get' . ucfirst($name), '', $getterCode, $getterComment, $member->getTypeHint(true));
            $accessors[] = $getter;

            $setterComment = new PhpDocComment();
            $setterComment->addParam(PhpDocElementFactory::getParam($strCommentType, $name, ''));
            $setterComment->setReturn(PhpDocElementFactory::getReturn($this->phpIdentifier, ''));
            if ($type === DateTime::class) {
                if ($member->getNullable()) {
                    $setterCode =
						"\t".'if ($' . $name . ' === null)' . PHP_EOL .
						"\t".'{' . PHP_EOL .
						"\t\t".'$this->' . $name . ' = null;' . PHP_EOL .
						"\t".'}' . PHP_EOL .
						"\t".'else' . PHP_EOL .
						"\t".'{' . PHP_EOL .
						"\t\t".'$this->' . $name . ' = $' . $name . '->format(\DateTime::ATOM);' . PHP_EOL .
						"\t".'}' . PHP_EOL;
                } else {
                    $setterCode = "\t".'$this->' . $name . ' = $' . $name . '->format(\DateTime::ATOM);' . PHP_EOL;
                }
            } else {
                $setterCode = "\t".'$this->' . $name . ' = $' . $name . ';' . PHP_EOL;
            }
            $setterCode .= "\t".'return $this;' . PHP_EOL;
            $setter = new PhpFunction(
                'public',
                'set' . ucfirst($name),
                $this->buildParametersString(
                    array($name => $strTypeHint),
                    true,
                    // If the type of a member is nullable we should allow passing null to the setter. If the type
                    // of the member is a class and not a primitive this is only possible if setter parameter has
                    // a default null value. We can detect whether the type is a class by checking the type hint.
                    $member->getNullable() && ( ($strTypeHint ?? '') !== '')
                ),
                $setterCode,
                $setterComment,
				$this->phpIdentifier
            );
            $accessors[] = $setter;
        }

        $constructor = new PhpFunction(
            'public',
            '__construct',
            $this->buildParametersString(
                $constructorParameters,
                true,
                $this->config->get('constructorParamsDefaultToNull')
            ),
            $constructorSource,
            $constructorComment
        );
        $this->class->addFunction($constructor);

        foreach ($accessors as $accessor) {
            $this->class->addFunction($accessor);
        }
    }

    /**
     * Determine parent class
     *
     * @return string|null
     *   Returns a string containing the PHP identifier for the parent class
     *   or null if there is no applicable parent class.
     */
    public function getBaseTypeClass(): ?string
	{
        // If we have a base type which is different than the current class then extend that.
        // It is actually possible to have different classes with the same name as PHP SoapClient has a poor
        // understanding of namespaces. Two types with the same name but in different namespaces will have the same
        // identifier.
        if ($this->baseType !== null && $this->baseType !== $this) {
            return $this->baseType->getPhpIdentifier();
        }

        return null;
    }

    /**
     * Returns the base type for the type if any.
     *
     * @return ComplexType|null
     *   The base type or null if the type has no base type.
     */
    public function getBaseType(): ?ComplexType
	{
        return $this->baseType;
    }

    /**
     * Set the base type
     *
     * @param ComplexType $type
     */
    public function setBaseType(ComplexType $type): void
	{
        $this->baseType = $type;
    }

    /**
     * @return bool
     */
    public function getAbstract(): bool
	{
        return $this->abstract;
    }

    /**
     * @param bool $abstract
     */
    public function setAbstract($abstract): void
	{
        $this->abstract = $abstract;
    }

	/**
	 * Adds the member. Overwrites members with same name
	 *
	 * @param string $type
	 * @param string $name
	 * @param bool $nullable
	 * @param string $strExtraType
	 */
    public function addMember(string $type, string $name, bool $nullable, string $strExtraType = ''): void
	{
        $this->members[$name] = new Variable($type, $name, $nullable, $strExtraType);
    }

    /**
     * Get type member list
     *
     * @return Variable[]
     */
    public function getMembers(): array
	{
        return $this->members;
    }

    /**
     * Generate a string representing the parameters for a function e.g. "type1 $param1, type2 $param2, $param3"
     *
     * @param array $parameters A map of parameters. Keys are parameter names and values are parameter types.
     *                          Parameter types may be empty. In that case they are not used.
     * @param bool $includeType Whether to include the parameters types in the string
     * @param bool $defaultNull Whether to set the default value of parameters to null.
     * @return string The parameter string.
     */
    protected function buildParametersString(array $parameters, bool $includeType = true, bool $defaultNull = false): string
	{
        $parameterStrings = [];
        foreach ($parameters as $name => $type) {
            $parameterString = '$' . $name;
            if (!empty($type) && $includeType) {
                $parameterString = $type . ' ' . $parameterString;
            }
            if ($defaultNull) {
                $parameterString .= ' = null';
            }
            $parameterStrings[] = $parameterString;
        }

        return implode(', ', $parameterStrings);
    }

    /**
     * Get members from base types all the way through the type hierarchy.
     *
     * @param ComplexType $type The type to retrieve base type members from.
     * @return Variable[] Member variables from all base types.
     */
    protected function getBaseTypeMembers(ComplexType $type): array
	{
        if ($type->baseType === null) {
            return [];
        }

        // Only get members from the base type if it differs from the current class. It is possible that they will be
        // the same due to poor handling of namespaces in PHP SoapClients.
        if ($type === $type->baseType) {
            return [];
        }

        return array_merge($this->getBaseTypeMembers($type->baseType), $type->baseType->getMembers());
    }
}
