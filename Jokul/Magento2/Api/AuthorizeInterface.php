<?php
namespace Jokul\Magento2\Api;

/**
 * Interface TestInterface
 * @package Jokul\Magento2\Api
 */
interface AuthorizeInterface
{
    /**
     * Set Authorize ID based on provided invoice number and authorize ID.
     *
     * @api
     * @param string $invoiceNumber Invoice number associated with the authorization.
     * @param string $authorizeId Authorization ID.
     * @return string JSON-encoded response.
     */
    public function setAuthorizeId($invoiceNumber, $authorizeId);

    /**
     * Check the validity of the provided signature.
     *
     * This method compares the provided signature with the expected signature
     * based on certain criteria to ensure the integrity and authenticity of the data.
     * If the signature is valid, the method returns true; otherwise, it returns false.
     *
     * @param string $signature The signature to be checked.
     *
     * @return bool True if the signature is valid; otherwise, false.
     */
    public function checkSignature($signature, $invoiceNumber, $authorizeId);
}
