<?php

/**
 * Determine if the SAML response is valid using a provided x509 certificate.
 */
class OneLogin_Saml_XmlSec
{
    /**
     * A SamlResponse class provided to the constructor.
     *
     * @var OneLogin_Saml_Settings
     */
    protected $_settings;

    /**
     * The document to be tested.
     *
     * @var DomDocument
     */
    protected $_document;

    /**
     * Construct the SamlXmlSec object.
     *
     * @param OneLogin_Saml_Settings $settings a SamlResponse settings object containing the necessary
     *                                         x509 certicate to test the document
     * @param OneLogin_Saml_Response $response the document to test
     */
    public function __construct(OneLogin_Saml_Settings $settings, OneLogin_Saml_Response $response)
    {
        $this->_settings = $settings;
        $this->_document = clone $response->document;
    }

    /**
     * Verify that the document only contains a single Assertion.
     *
     * @return bool TRUE if the document passes
     */
    public function validateNumAssertions()
    {
        $rootNode = $this->_document;
        $assertionNodes = $rootNode->getElementsByTagName('Assertion');

        return 1 == $assertionNodes->length;
    }

    /**
     * Verify that the document is still valid according.
     *
     * @return bool
     */
    public function validateTimestamps()
    {
        $rootNode = $this->_document;
        $timestampNodes = $rootNode->getElementsByTagName('Conditions');
        for ($i = 0; $i < $timestampNodes->length; ++$i) {
            $nbAttribute = $timestampNodes->item($i)->attributes->getNamedItem('NotBefore');
            $naAttribute = $timestampNodes->item($i)->attributes->getNamedItem('NotOnOrAfter');
            if ($nbAttribute && strtotime($nbAttribute->textContent) > time()) {
                return false;
            }
            if ($naAttribute && strtotime($naAttribute->textContent) <= time()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     *
     * @return bool
     */
    public function isValid()
    {
        $singleAssertion = $this->validateNumAssertions();
        if (!$singleAssertion) {
            throw new Exception('Multiple assertions are not supported');
        }

        $validTimestamps = $this->validateTimestamps();
        if (!$validTimestamps) {
            throw new Exception('Timing issues (please check your clock settings)');
        }

        $objXMLSecDSig = new XMLSecurityDSig();

        $objDSig = $objXMLSecDSig->locateSignature($this->_document);
        if (!$objDSig) {
            throw new Exception('Cannot locate Signature Node');
        }
        $objXMLSecDSig->canonicalizeSignedInfo();
        $objXMLSecDSig->idKeys = ['ID'];

        $objKey = $objXMLSecDSig->locateKey();
        if (!$objKey) {
            throw new Exception('We have no idea about the key');
        }

        try {
            $objXMLSecDSig->validateReference();
        } catch (Exception $e) {
            throw new Exception('Reference Validation Failed');
        }

        XMLSecEnc::staticLocateKeyInfo($objKey, $objDSig);

        $objKey->loadKey($this->_settings->idpPublicCertificate, false, true);

        return 1 === $objXMLSecDSig->verify($objKey);
    }
}
