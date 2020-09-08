<?php
/**
 *
 * User: leogent <leogent@gmail.com>
 * Date: 2/10/19
 * Time: 3:23 AM
 */

namespace Jokul\Magento2\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface TransactionSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get blocks list.
     *
     * @return \Jokul\Magento2\Api\Data\TransactionInterface[]
     */
    public function getItems();

    /**
     * Set blocks list.
     *
     * @param \Jokul\Magento2\Api\Data\TransactionInterface[] $items
     * @return $this
     */
    public function setItems(array $items);

}