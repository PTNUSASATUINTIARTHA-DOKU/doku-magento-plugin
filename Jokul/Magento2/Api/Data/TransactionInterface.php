<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 2/10/19
 * Time: 2:37 AM
 */

namespace Jokul\Magento2\Api\Data;


interface TransactionInterface
{

    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    const ID      = 'id';
    const STORE_ID = 'store_id';
    const QUOTE_ID = 'quote_id';
    const ORDER_ID = 'order_id';
    const TRANS_ID_MERCHANT = 'trans_id_merchant';
    const PAYMENT_CHANNEL_ID = 'payment_channel_id';
    const VA_NUMBER = 'va_number';
    const ORDER_STATUS = 'order_status';
    const REQUEST_PARAMS = 'request_params';
    const IDENTIFY_PARAMS = 'identify_params';
    const REDIRECT_PARAMS = 'redirect_params';
    const NOTIFY_PARAMS = 'notify_params';
    const REVIEW_PARAMS = 'review_params';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const ADMIN_FEE_TYPE = 'admin_fee_type';
    const ADMIN_FEE_AMOUNT = 'admin_fee_amount';
    const ADMIN_FEE_TRX_AMOUNT = 'admin_fee_trx_amount';
    const DISCOUNT_TYPE = 'discount_type';
    const DISCOUNT_AMOUNT = 'discount_amount';
    const DISCOUNT_TRX_AMOUNT = 'discount_trx_amount';
    const EXPIRED_AT_GMT = 'expired_at_gmt';
    const EXPIRED_AT_STORETIMEZONE = 'expired_at_storetimezone';
    const DOKU_GRAND_TOTAL = 'doku_grand_total';
    const CUSTOMER_EMAIL = 'customer_email';


}