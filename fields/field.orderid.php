<?php

require_once FACE . '/interface.exportablefield.php';
require_once FACE . '/interface.importablefield.php';

class FieldOrderID extends Field implements ExportableField, ImportableField
{
    public function __construct()
    {
        parent::__construct();
        $this->_name = __('Order ID');
        $this->_required = true;

        $this->set('required', 'no');
    }

    /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

    public function canFilter()
    {
        return true;
    }

    public function canPrePopulate()
    {
        return true;
    }

    public function isSortable()
    {
        return true;
    }

    public function allowDatasourceParamOutput()
    {
        return true;
    }

    /*-------------------------------------------------------------------------
        Utilities:
    -------------------------------------------------------------------------*/

    private function generateNewOrderIdValue() {
        // Order IDs should look like this:
        // RXXXXX-YYY-L
        // P - signifies this is an order (purchase)
        // XXXXX - a zero padded sequential number. Starting at 00001
        // YYY - 3 random digits. Makes it harder to guess a valid order number
        // L - checksum. Should equal the number of digits used

        // Find the most recent order number, deconstruct it and increment
        $lastOrderId = Symphony::Database()->fetchVar(
            'value', 0,
            "SELECT `value` FROM `tbl_entries_data_" . $this->get('id') . "` ORDER BY `entry_id` DESC LIMIT 1"
        );

        $sequence = 1;

        if($lastOrderId != NULL) {
            $matches = NULL;
            preg_match("/^R(\d{5})/i", $lastOrderId, $matches);
            $sequence = (int)$matches[1] + 1;
        }

        $sequence = str_pad($sequence, 5, "0", STR_PAD_LEFT);
        $randomDigits = str_pad(rand(1, 999), 4, "0", STR_PAD_LEFT);

        return sprintf(
            "R%s%s-%d",
            $sequence,
            $randomDigits,
            strlen($sequence.$randomDigits)
        );
    }

    private function getExistingOrderId($entryId) {
        $existingOrderId = Symphony::Database()->fetchVar(
            'value', 0,
            sprintf(
                "SELECT `value` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d LIMIT 1",
                $this->get('id'),
                $entryId
            )
        );

        //var_dump($existingOrderId); die;

        return $existingOrderId;
    }

    /*-------------------------------------------------------------------------
        Setup:
    -------------------------------------------------------------------------*/

    public function createTable()
    {
        return Symphony::Database()->query(
            "CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
              `id` int(11) unsigned NOT null auto_increment,
              `entry_id` int(11) unsigned NOT null,
              `value` varchar(36) default null,
              PRIMARY KEY  (`id`),
              UNIQUE KEY `entry_id` (`entry_id`),
              KEY `value` (`value`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
        );
    }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        // Requirements and table display
        $this->appendStatusFooter($wrapper);
    }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
    {
        $value = General::sanitize(isset($data['value']) ? $data['value'] : $this->generateNewOrderIdValue());
        $label = Widget::Label($this->get('label'));

        if ($this->get('required') !== 'yes') {
            $label->appendChild(new XMLElement('i', __('Optional')));
        }

        $input = Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, (strlen($value) != 0 ? $value : null));
        $input->setAttribute("disabled", "disabled");
        $label->appendChild($input);

        if ($flagWithError != null) {
            $wrapper->appendChild(Widget::Error($label, $flagWithError));
        } else {
            $wrapper->appendChild($label);
        }
    }

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $message = null;

        if (is_array($data) && isset($data['value'])) {
            $data = $data['value'];
        }

        if(strlen(trim($data)) == 0) {
            $data = $this->generateNewOrderIdValue();
        }

        // Order ID cannot be changed once it is saved. Look up the existing
        // order ID first and if it's set, use that instead.
        if($entry_id != NULL) {
            $existingOrderId = $this->getExistingOrderId($entry_id);
            if($existingOrderId != NULL && strlen(trim($existingOrderId)) > 0) {
                $data = $existingOrderId;
            }
        }

        return self::__OK__;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $status = self::__OK__;

        if(strlen(trim($data)) == 0) {
            $data = $this->generateNewOrderIdValue();
        }

        // Order ID cannot be changed once it is saved. Look up the existing
        // order ID first and if it's set, use that instead.
        if($entry_id != NULL) {
            $existingOrderId = $this->getExistingOrderId($entry_id);
            if($existingOrderId != NULL && strlen(trim($existingOrderId)) > 0) {
                $data = $existingOrderId;
            }
        }

        $result = [
            'value' => $data
        ];

        return $result;
    }

    /*-------------------------------------------------------------------------
        Output:
    -------------------------------------------------------------------------*/

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        $value = $data['value'];

        if ($encode === true) {
            $value = General::sanitize($value);
        } else {
            include_once TOOLKIT . '/class.xsltprocess.php';

            if (!General::validateXML($data['value'], $errors, false, new XsltProcess)) {
                $value = html_entity_decode($data['value'], ENT_QUOTES, 'UTF-8');
                $value = $this->__replaceAmpersands($value);

                if (!General::validateXML($value, $errors, false, new XsltProcess)) {
                    $value = General::sanitize($data['value']);
                }
            }
        }

        $wrapper->appendChild(
            new XMLElement($this->get('element_name'), $value)
        );
    }

    /*-------------------------------------------------------------------------
        Import:
    -------------------------------------------------------------------------*/

    public function getImportModes()
    {
        return array(
            'getValue' =>       ImportableField::STRING_VALUE,
            'getPostdata' =>    ImportableField::ARRAY_VALUE
        );
    }

    public function prepareImportValue($data, $mode, $entry_id = null)
    {
        $message = $status = null;
        $modes = (object)$this->getImportModes();

        if ($mode === $modes->getValue) {
            return $data;
        } elseif ($mode === $modes->getPostdata) {
            return $this->processRawFieldData($data, $status, $message, true, $entry_id);
        }

        return null;
    }

    /*-------------------------------------------------------------------------
        Export:
    -------------------------------------------------------------------------*/

    /**
     * Return a list of supported export modes for use with `prepareExportValue`.
     *
     * @return array
     */
    public function getExportModes()
    {
        return array(
            'getUnformatted' => ExportableField::UNFORMATTED,
            'getPostdata' =>    ExportableField::POSTDATA
        );
    }

    /**
     * Give the field some data and ask it to return a value using one of many
     * possible modes.
     *
     * @param mixed $data
     * @param integer $mode
     * @param integer $entry_id
     * @return string|null
     */
    public function prepareExportValue($data, $mode, $entry_id = null)
    {
        $modes = (object)$this->getExportModes();

        // Export unformatted:
        if ($mode === $modes->getUnformatted || $mode === $modes->getPostdata) {
            return isset($data['value'])
                ? $data['value']
                : null;
        }

        return null;
    }

    /*-------------------------------------------------------------------------
        Filtering:
    -------------------------------------------------------------------------*/

    public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false)
    {
        $field_id = $this->get('id');

        if (self::isFilterRegex($data[0])) {
            $this->buildRegexSQL($data[0], array('value'), $joins, $where);
        } else if ($andOperation) {
            foreach ($data as $value) {
                $this->_key++;
                $value = $this->cleanValue($value);
                $joins .= "
                    LEFT JOIN
                        `tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
                        ON (e.id = t{$field_id}_{$this->_key}.entry_id)
                ";
                $where .= "
                    AND (
                        t{$field_id}_{$this->_key}.value = '{$value}'
                    )
                ";
            }
        } else {
            if (!is_array($data)) {
                $data = array($data);
            }

            foreach ($data as &$value) {
                $value = $this->cleanValue($value);
            }

            $this->_key++;
            $data = implode("', '", $data);
            $joins .= "
                LEFT JOIN
                    `tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
                    ON (e.id = t{$field_id}_{$this->_key}.entry_id)
            ";
            $where .= "
                AND (
                    t{$field_id}_{$this->_key}.value IN ('{$data}')
                )
            ";
        }

        return true;
    }

    /*-------------------------------------------------------------------------
        Sorting:
    -------------------------------------------------------------------------*/

    public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC')
    {
        if (in_array(strtolower($order), array('random', 'rand'))) {
            $sort = 'ORDER BY RAND()';
        } else {
            $sort = sprintf(
                'ORDER BY (
                    SELECT %s
                    FROM tbl_entries_data_%d AS `ed`
                    WHERE entry_id = e.id
                ) %s',
                '`ed`.value',
                $this->get('id'),
                $order
            );
        }
    }

}
