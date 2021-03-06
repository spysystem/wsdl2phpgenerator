<?php


namespace Wsdl2PhpGenerator\Xml;


use DOMDocument;
use DOMElement;
use Exception;
use Wsdl2PhpGenerator\ConfigInterface;
use Wsdl2PhpGenerator\StreamContextFactory;

/**
 * A SchemaDocument represents an XML element which contains type elements.
 *
 * The element may reference other schemas to generate a tree structure.
 */
class SchemaDocument extends XmlNode
{

    /**
     * The url representing the location of the schema.
     *
     * @var string
     */
    protected $url;


    /**
     * The schemas which are referenced by the current schema.
     *
     * @var SchemaDocument[]
     */
    protected $references;

    /**
     * The urls of schemas which have already been loaded.
     *
     * We keep a record of these to avoid cyclical imports.
     *
     * @var string[]
     */
    protected static $loadedUrls;

	/**
	 * SchemaDocument constructor.
	 *
	 * @param ConfigInterface $config
	 * @param                 $xsdUrl
	 *
	 * @throws Exception
	 */
    public function __construct(ConfigInterface $config, $xsdUrl)
    {
        $this->url = $xsdUrl;

        // Generate a stream context used by libxml to access external resources.
        // This will allow DOMDocument to load XSDs through a proxy.
        $streamContextFactory = new StreamContextFactory();
        libxml_set_streams_context($streamContextFactory->create($config));

        $document = new DOMDocument();
        $loaded = $document->load($xsdUrl);
        if (!$loaded) {
            throw new Exception('Unable to load XML from '. $xsdUrl);
        }

        parent::__construct($document, $document->documentElement);
		// Register the schema to avoid cyclical imports.
		self::$loadedUrls[$xsdUrl] = $this;

        // Locate and instantiate schemas which are referenced by the current schema.
        // A reference in this context can either be
        // - an import from another namespace: http://www.w3.org/TR/xmlschema-1/#composition-schemaImport
        // - an include within the same namespace: http://www.w3.org/TR/xmlschema-1/#compound-schema
        $this->references = [];
        foreach ($this->xpath(  '//wsdl:import/@location|' .
                                '//s:import/@schemaLocation|' .
                                '//s:include/@schemaLocation') as $reference) {
            $referenceUrl = $reference->value;
            if (strpos($referenceUrl, '//') === false) {
                $referenceUrl = \dirname($xsdUrl) . '/' . $referenceUrl;
            }

			if (!array_key_exists($referenceUrl, self::$loadedUrls)) {
				$this->references[] = new SchemaDocument($config, $referenceUrl);
			} else {
				// Refer to the previously loaded schema.
				$this->references[] = self::$loadedUrls[$referenceUrl];
			}
        }
    }

	/**
	 * Parses the schema for a type with a specific name.
	 *
	 * @param string $name The name of the type
	 * @return DOMElement|null Returns the type node with the provided if it is found. Null otherwise.
	 */
	public function findTypeElement(string $name): ?DOMElement
	{
		return $this->findTypeElementFromSchema($name);
	}

	/**
	* Parses the schema for a type with a specific name. Checks whether imports have been checked before, to
	* circumvent endless loops.
	*
	* @param string $name The name of the type.
	* @param SchemaDocument[] $checkedImports
	* @return DOMElement|null Returns the type node with the provided if it is found. Null otherwise.
	*/
	private function findTypeElementFromSchema($name, array &$checkedImports = array()): ?DOMElement
	{
        $type = null;

        $elements = $this->xpath('//s:simpleType[@name=%s]|//s:complexType[@name=%s]', $name, $name);
        if ($elements->length > 0) {
            $type = $elements->item(0);
        } else {
			$elements = $this->xpath('//s:element[@name=%s]', $name, $name);
			if ($elements->length > 0) {
				$oType = $elements->item(0);

				/** @var DOMElement $oElement */
				$oElement = null;

				/** @var DOMElement $oChildNode */
				foreach($oType->childNodes as $oChildNode)
				{
					if($oChildNode->nodeType === XML_ELEMENT_NODE)
					{
						$oElement	= $oChildNode;
					}
				}

				if($oElement !== null && strpos($oElement->tagName, 'complexType') !== false) {
					$type = $oElement;
				}
			}
		}

		if ($type === null) {
			foreach ($this->references as $import) {
				if (\in_array($import, $checkedImports, true)) {
					continue;
				}
				$checkedImports[] = $import;
				$type = $import->findTypeElementFromSchema($name, $checkedImports);
				if ($type !== null) {
					break;
				}
			}
		}

		return $type;
	}
}
