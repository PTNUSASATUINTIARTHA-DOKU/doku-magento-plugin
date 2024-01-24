<?php
namespace Jokul\Magento2\Model;

use Jokul\Magento2\Api\AuthorizeInterface;
use Jokul\Magento2\Model\JokulConfigProvider;
use Jokul\Magento2\Helper\Data;
use Jokul\Magento2\Helper\Logger;
use Magento\Framework\App\ResourceConnection;

class Authorize implements AuthorizeInterface
{

    private $resourceConnection;
    protected $config;
    protected $helper;
    protected $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        Data $helper,
        JokulConfigProvider $config,
        Logger $loggerInterface
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->helper = $helper;
        $this->config = $config;
        $this->logger = $loggerInterface;
    }

    /**
     * Set Authorize ID based on provided invoice number and authorize ID.
     *
     * @api
     * @param string $invoiceNumber Invoice number associated with the authorization.
     * @param string $authorizeId Authorization ID.
     * @return string Response indicating success.
     */
    public function setAuthorizeId($invoiceNumber, $authorizeId)
    {
        $this->logger->doku_log('Save Authorize ID', 'START');
        $headers = getallheaders();
        $this->logger->doku_log('headers', json_encode($headers));
        $this->logger->doku_log('INVOICE', $invoiceNumber);
        $this->logger->doku_log('AUTHORIZE ID', $authorizeId);

        if ($this->checkSignature($headers, $invoiceNumber, $authorizeId)) {
            try {
                $this->logger->doku_log('Save Authorize ID', 'FIND TRANSACTION AND SAVE AUTHORIZE ID');
                $tableName = $this->resourceConnection->getTableName('jokul_transaction');
                $connection = $this->resourceConnection->getConnection();
                
                $updatedTransaction = [
                    'authorize_id' => $authorizeId,
                ];
                
                $where = ['invoice_number = ?' => $invoiceNumber];
                
                $affectedRows = $connection->update($tableName, $updatedTransaction, $where);
                
                if ($affectedRows > 0) {
                    $this->logger->doku_log('Save Authorize ID', 'SUCCESS');
                    $response = ['message' => 'SUCCESS'];
                } else {
                    $this->logger->doku_log('Save Authorize ID', 'ERROR');
                    $response = ['message' => 'ERROR'];
                }
            } catch (\Exception $e) {
                $this->logger->doku_log('Save Authorize ID', $e->getMessage());
                $response = ['message' => 'Error' . $e->getMessage()];
            }
        } else {
            $response = ['message' => 'SIGNATURE NOT MATCH'];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        $this->logger->doku_log('Save Authorize ID', 'END');
        exit;

    }

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
    public function checkSignature($headers, $invoiceNumber, $authorizeId) {
        $signature = $headers['Signature'] ?? '';
        $requestTimestamp = $headers['Request-Timestamp'] ?? '';
        $requestId = $headers['Request-Id'] ?? '';
        $requestTarget = "/rest/V1/authorize";

        $config = $this->config->getAllConfig();
        $sharedKey = $this->config->getSharedKey();
        $clientId = $config['payment']['core']['client_id'];

        $signatureParams = array(
            "clientId" => $clientId,
            "key" => $sharedKey,
            "requestTarget" => $requestTarget,
            "requestId" => $requestId,
            "requestTimestamp" => $requestTimestamp
        );

        $this->logger->doku_log('clientId', $clientId);
        $this->logger->doku_log('requestTarget', $requestTarget);
        $this->logger->doku_log('requestId', $requestId);
        $this->logger->doku_log('requestTimestamp', $requestTimestamp);

        $params = array(
            'invoice_number' => $invoiceNumber,
            'authorize_id' => $authorizeId
        );

        $generatedSignature = $this->helper->doCreateRequestSignature($signatureParams, $params);
        $this->logger->doku_log('Generated Signature', $generatedSignature);
        $this->logger->doku_log('Actual Signature', $signature);
        $this->logger->doku_log('================================================', "========");
        return ($signature === $generatedSignature);
    }


}
