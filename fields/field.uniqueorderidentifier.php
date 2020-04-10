<?php

declare(strict_types=1);

class FieldUniqueOrderIdentifier extends Field implements ExportableField, ImportableField
{
    public function __construct()
    {
        parent::__construct();
        $this->_name = __('Unique Order Identifier');
        $this->_required = true;

        $this->set('required', 'yes');
    }

    public function commit()
    {
        if (false == parent::commit() || false === $this->get('id')) {
            return false;
        }

        return FieldManager::saveSettings($this->get('id'), [
            'sequence_length' => (int) $this->get('sequence_length'),
            'prefix' => strtoupper($this->get('prefix')),
            'enable_rand' => ($this->get('enable_rand') ? $this->get('enable_rand') : 'off'),
            'enable_checksum' => ($this->get('enable_checksum') ? $this->get('enable_checksum') : 'off'),
        ]);
    }

    public function checkFields(array &$errors, $checkForDuplicates = true)
    {
        parent::checkFields($errors, $checkForDuplicates);

        if (strlen(trim($this->get('prefix'))) <= 0) {
            $errors['prefix'] = __('This is a required field.');
        } elseif (false == preg_match('@^[A-Z]{1,3}$@i', $this->get('prefix'))) {
            $errors['prefix'] = __('Must be 1 to 3 characters from A-Z.');
        }

        if (strlen(trim($this->get('sequence_length'))) <= 0) {
            $errors['sequence_length'] = __('This is a required field.');
        } elseif (false == preg_match('@^[1-9]$@i', $this->get('sequence_length'))) {
            $errors['sequence_length'] = __('Must be a number between 1 and 9.');
        }

        return
            !empty($errors)
                ? self::__ERROR__
                : self::__OK__
        ;
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

    /**
     * Returns a new, unique, identifier value using the following format:.
     *
     * [PREFIX][SEQ]-[RAND]-[CHECKSUM]
     *
     * e.g. R00001-837-19
     *
     * PREFIX       - Up to 3, uppercase, characters specified in the section field
     *                  settings
     *
     * SEQ          - A zero padded number based on sequence of existing values. The
     *                  length is specified in the section field settings.
     *
     * RAND         - [optional] 3 random digits. This makes it harder to predict subsequent
     *                  order numbers. Highly recommended to keep this enabled.
     *                  Can be disabled in section field settings.
     *
     * CHECKSUM     - [optional] the sum of all the preceeding digits. This can be
     *                  used to ensure an order identifier is valid. Can be disabled
     *                  in section field settings. Ignored if RAND is disabled.
     *
     * @return string order identifier value
     */
    private function generateIdentifier(int &$seq = null)
    {
        // Find the current sequence number
        $lastInSequence = Symphony::Database()->fetchVar('seq', 0, sprintf(
            'SELECT `seq` FROM `tbl_entries_data_%d` ORDER BY `seq` DESC LIMIT 1',
            $this->get('id')
        ));

        $seq = (
            null == $lastInSequence
            ? 1
            : (int) $lastInSequence + 1
        );

        $seq = str_pad((string) $seq, (int) $this->get('sequence_length'), '0', STR_PAD_LEFT);

        // Put it all together now
        $identifier = "{$this->get('prefix')}{$seq}";
        if ('yes' == $this->get('enable_rand')) {
            $rand = rand(100, 999);
            $identifier = sprintf(
                '%s-%s%s',
                $identifier,
                $rand,
                'yes' == $this->get('enable_checksum')
                    ? '-'.$this->sum_digits_in_string("{$seq}{$rand}")
                    : ''
            );
        }

        return $identifier;
    }

    private function sum_digits_in_string(string $value): int
    {
        $count = 0;
        for ($ii = 0; $ii < strlen($value); ++$ii) {
            if (false == is_numeric($value[$ii])) {
                continue;
            }
            $count += (int) $value[$ii];
        }

        return $count;
    }

    private function getCurrentIdentifier(int $entryId): ?\stdClass
    {
        $identifier = Symphony::Database()->fetch(sprintf(
            'SELECT `value`, `seq` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d LIMIT 1',
            $this->get('id'),
            $entryId
        ));

        return false == $identifier || true == empty($identifier) ? null : (object) $identifier[0];
    }

    private function isIdenfitierUnique(string $value, ?int $entryId = null): bool
    {
        $count = (int) Symphony::Database()->fetchVar('count', 0, sprintf(
            "SELECT COUNT(*) as `count`
            FROM `tbl_entries_data_%d`
            WHERE `value` = '%s' %s",
            $this->get('id'),
            $value,
            null != $entryId ? " AND `entry_id` != {$entryId}" : ''
        ));

        return $count <= 0;
    }

    /*-------------------------------------------------------------------------
        Setup:
    -------------------------------------------------------------------------*/

    public function createTable()
    {
        return Symphony::Database()->query(sprintf(
            'CREATE TABLE IF NOT EXISTS `tbl_entries_data_%s` (
              `id` int(11) unsigned NOT null auto_increment,
              `entry_id` int(11) unsigned NOT null,
              `value` varchar(80) default null,
              `seq` int(11) unsigned NOT null,
              PRIMARY KEY  (`id`),
              UNIQUE KEY `entry_id` (`entry_id`),
              KEY `value` (`value`),
              KEY `seq` (`seq`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;',
            $this->get('id')
        ));
    }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

    public function findDefaults(array &$settings)
    {
        if (false == isset($settings['prefix'])) {
            $settings['prefix'] = 'R';
        }

        if (false == isset($settings['sequence_length'])) {
            $settings['sequence_length'] = 4;
        }

        if (false == isset($settings['enable_rand'])) {
            $settings['default_state'] = 'on';
        }

        if (false == isset($settings['enable_checksum'])) {
            $settings['default_state'] = 'on';
        }
    }

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        //########## PREFIX CHARACTER ###########
        $label = Widget::Label(__('Prefix Character'));
        $label->setAttribute('class', 'column');
        $label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][prefix]', $this->get('prefix')));

        if (isset($errors['prefix'])) {
            $div = new XMLElement('div');
            $div->appendChild($label);
            $wrapper->appendChild(Widget::Error($div, $errors['prefix']));
        } else {
            $wrapper->appendChild($label);
        }

        $wrapper->appendChild(new XMLElement('p', __('Must be a single character from A-Z'), array('class' => 'help')));
        //################################

        //########## SEQUENCE LENGTH ###########
        $label = Widget::Label(__('Sequence Length'));
        $label->setAttribute('class', 'column');
        $label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][sequence_length]', $this->get('sequence_length')));

        if (isset($errors['sequence_length'])) {
            $div = new XMLElement('div');
            $div->appendChild($label);
            $wrapper->appendChild(Widget::Error($div, $errors['sequence_length']));
        } else {
            $wrapper->appendChild($label);
        }

        $wrapper->appendChild(new XMLElement('p', __('Must be a number between 1 and 9.'), array('class' => 'help')));
        //################################

        //########## RANDOM DIGITS CHECKBOX ###########
        $label = Widget::Label();
        $input = Widget::Input('fields['.$this->get('sortorder').'][enable_rand]', 'yes', 'checkbox');
        if ('yes' == $this->get('enable_rand')) {
            $input->setAttribute('checked', 'checked');
        }
        $label->setValue($input->generate().' '.__('Include 3 random digits in identifiers'));
        $wrapper->appendChild($label);
        //################################

        //########## CHECKSUM CHECKBOX ###########
        $label = Widget::Label();
        $input = Widget::Input('fields['.$this->get('sortorder').'][enable_checksum]', 'yes', 'checkbox');
        if ('yes' == $this->get('enable_checksum')) {
            $input->setAttribute('checked', 'checked');
        }
        $label->setValue($input->generate().' '.__('Include checksum at end of identifier.'));
        $wrapper->appendChild($label);
        $wrapper->appendChild(new XMLElement('p', __('Note: This is ignored if random digits has not been enabled'), array('class' => 'help')));
        //################################

        $this->appendStatusFooter($wrapper);
    }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entryId = null)
    {
        // An order idenfitier cannot be changed once it is saved. Look up the existing
        // value first and if it's set, use that instead.
        if (null != $entryId && null != $identifier = $this->getCurrentIdentifier((int) $entryId)) {
            if (strlen(trim($identifier->value)) > 0) {
                $data['value'] = $identifier->value;
            }
        }

        $value = General::sanitize(
            true == isset($data['value'])
            ? $data['value']
            : $this->generateIdentifier($seq)
        );

        $label = Widget::Label($this->get('label'));

        // if ("yes" !== $this->get("required")) {
        //     $label->appendChild(new XMLElement("i", __("Optional")));
        // }

        $input = Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, (0 != strlen($value) ? $value : null));
        $input->setAttribute('disabled', 'disabled');
        $label->appendChild($input);

        if (null != $flagWithError) {
            $wrapper->appendChild(Widget::Error($label, $flagWithError));
        } else {
            $wrapper->appendChild($label);
        }
    }

    public function checkPostFieldData($data, &$message, $entryId = null)
    {
        $message = null;

        if (is_array($data) && isset($data['value'])) {
            $data = $data['value'];
        }

        if (null == $data || 0 == strlen(trim($data))) {
            $data = $this->generateIdentifier($seq);
        }

        // An order idenfitier cannot be changed once it is saved. Look up the existing
        // value first and if it's set, use that instead.
        if (null != $entryId && null != $identifier = $this->getCurrentIdentifier((int) $entryId)) {
            if (strlen(trim($identifier->value)) > 0) {
                $data = $identifier->value;
            }
        }

        if (false == $this->isIdenfitierUnique($data, (int) $entryId)) {
            $message = "Identifier {$data} is not unique.";

            return self::__ERROR__;
        }

        return self::__OK__;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entryId = null)
    {
        $status = self::__OK__;

        if (null == $data || 0 == strlen(trim($data))) {
            $data = $this->generateIdentifier($seq);
        }

        // An order idenfitier cannot be changed once it is saved. Look up the existing
        // value first and if it's set, use that instead.
        if (null != $entryId && null != $identifier = $this->getCurrentIdentifier((int) $entryId)) {
            if (strlen(trim($identifier->value)) > 0) {
                $data = $identifier->value;
                $seq = $identifier->seq;
            }
        }

        if (false == $this->isIdenfitierUnique($data, (int) $entryId)) {
            $message = "Identifier {$data} is not unique.";
            $status = self::__ERROR__;

            return false;
        }

        $result = ['value' => $data, 'seq' => $seq];

        return $result;
    }

    /*-------------------------------------------------------------------------
        Output:
    -------------------------------------------------------------------------*/

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entryId = null)
    {
        $value = $data['value'];

        if (true === $encode) {
            $value = General::sanitize($value);
        } else {
            include_once TOOLKIT.'/class.xsltprocess.php';

            if (false == General::validateXML($data['value'], $errors, false, new XsltProcess())) {
                $value = html_entity_decode($data['value'], ENT_QUOTES, 'UTF-8');
                $value = $this->__replaceAmpersands($value);

                if (false == General::validateXML($value, $errors, false, new XsltProcess())) {
                    $value = General::sanitize($data['value']);
                }
            }
        }

        $wrapper->appendChild(
            new XMLElement($this->get('element_name'), $value, ['seq' => $data['seq']])
        );
    }

    /*-------------------------------------------------------------------------
        Import:
    -------------------------------------------------------------------------*/

    public function getImportModes()
    {
        return [
            'getValue' => ImportableField::STRING_VALUE,
            'getPostdata' => ImportableField::ARRAY_VALUE,
        ];
    }

    public function prepareImportValue($data, $mode, $entryId = null)
    {
        $message = $status = null;
        $modes = (object) $this->getImportModes();

        if ($mode === $modes->getValue) {
            return $data;
        } elseif ($mode === $modes->getPostdata) {
            return $this->processRawFieldData($data, $status, $message, true, $entryId);
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
        return [
            'getUnformatted' => ExportableField::UNFORMATTED,
            'getPostdata' => ExportableField::POSTDATA,
        ];
    }

    /**
     * Give the field some data and ask it to return a value using one of many
     * possible modes.
     *
     * @param mixed $data
     * @param int   $mode
     * @param int   $entryId
     *
     * @return string|null
     */
    public function prepareExportValue($data, $mode, $entryId = null)
    {
        $modes = (object) $this->getExportModes();

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

        if (true == self::isFilterRegex($data[0])) {
            $this->buildRegexSQL($data[0], ['value'], $joins, $where);
        } elseif (true == $andOperation) {
            foreach ($data as $value) {
                ++$this->_key;
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
            if (false == is_array($data)) {
                $data = [$data];
            }

            foreach ($data as &$value) {
                $value = $this->cleanValue($value);
            }

            ++$this->_key;
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
        if (true == in_array(strtolower($order), ['random', 'rand'])) {
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
