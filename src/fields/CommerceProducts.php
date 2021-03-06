<?php
namespace verbb\feedme\fields;

use verbb\feedme\base\Field;
use verbb\feedme\base\FieldInterface;

use Craft;
use craft\commerce\elements\Product as ProductElement;
use craft\helpers\Db;

use Cake\Utility\Hash;

class CommerceProducts extends Field implements FieldInterface
{
    // Properties
    // =========================================================================

    public static $name = 'CommerceProducts';
    public static $class = 'craft\commerce\fields\Products';
    public static $elementType = 'craft\commerce\elements\Product';


    // Templates
    // =========================================================================

    public function getMappingTemplate()
    {
        return 'feed-me/_includes/fields/commerce_products';
    }


    // Public Methods
    // =========================================================================

    public function parseField()
    {
        $value = $this->fetchArrayValue();

        $settings = Hash::get($this->field, 'settings');
        $sources = Hash::get($this->field, 'settings.sources');
        $limit = Hash::get($this->field, 'settings.limit');
        $match = Hash::get($this->fieldInfo, 'options.match', 'title');
        $node = Hash::get($this->fieldInfo, 'node');

        $typeIds = [];

        if (is_array($sources)) {
            foreach ($sources as $source) {
                list($type, $uid) = explode(':', $source);
                $typeIds[] = $uid;
            }
        } else if ($sources === '*') {
            $typeIds = '*';
        }

        $foundElements = [];

        if (!$value) {
            return $foundElements;
        }

        foreach ($value as $dataValue) {
            // Prevent empty or blank values (string or array), which match all elements
            if (empty($dataValue)) {
                continue;
            }

            // If we're using the default value - skip, we've already got an id array
            if ($node === 'usedefault') {
                $foundElements = $value;
                break;
            }

            // Because we can match on element attributes and custom fields, AND we're directly using SQL
            // queries in our `where` below, we need to check if we need a prefix for custom fields accessing
            // the content table.
            $columnName = $match;

            if (Craft::$app->getFields()->getFieldByHandle($match)) {
                $columnName = Craft::$app->getFields()->oldFieldColumnPrefix . $match;
            }
            
            $query = ProductElement::find();

            $criteria['status'] = null;
            $criteria['typeId'] = $typeIds;
            $criteria['limit'] = $limit;
            $criteria['where'] = ['=', $columnName, $dataValue];

            Craft::configure($query, $criteria);

            $ids = $query->ids();

            $foundElements = array_merge($foundElements, $ids);
        }

        // Check for field limit - only return the specified amount
        if ($foundElements && $limit) {
            $foundElements = array_chunk($foundElements, $limit)[0];
        }

        $foundElements = array_unique($foundElements);

        return $foundElements;
    }

}