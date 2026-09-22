<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace customfield_timerange;

use core_customfield\api;

/**
 * Base class for custom fields data controllers
 *
 * This class is a wrapper around the persistent data class that allows to define
 * how the element behaves in the instance edit forms.
 *
 * Contrôle le formulaire d'édition des données d'une instance de champ personnalisé.
 * Enregistre un JSON au format {'start':{'hour':12,'minute':0},'end':{'hour':13,'minute':30}}.
 * À l'affichage, la valeur est formatée en 12h00-13h30.
 *
 * @package   customfield_timerange
 * @copyright 2026 Université Rennes 2
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_controller extends \core_customfield\data_controller {
    /**
     * Valeur minimale pour le champ "heure".
     */
    const MIN_HOUR_FIELD = 0;

    /**
     * Valeur maximale pour le champ "heure".
     */
    const MAX_HOUR_FIELD = 23;

    /**
     * Valeur minimale pour le champ "minute".
     */
    const MIN_MINUTE_FIELD = 0;

    /**
     * Valeur maximale pour le champ "minute".
     */
    const MAX_MINUTE_FIELD = 59;

    /**
     * Return the name of the field in the db table {customfield_data} where the data is stored
     *
     * Must be one of the following:
     *   intvalue - can store integer values, this field is indexed
     *   decvalue - can store decimal values
     *   shortcharvalue - can store character values up to 255 characters long, this field is indexed
     *   charvalue - can store character values up to 1333 characters long, this field is not indexed but
     *     full text search is faster than on field 'value'
     *   value - can store character values of unlimited length ("text" field in the db)
     *
     * @return string
     */
    public function datafield(): string {
        return 'shortcharvalue';
    }

    /**
     * Add a field to the instance edit form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform) {
        global $CFG;

        $field = $this->get_field();
        $config = $field->get('configdata');

        $name = $this->get_form_element_name();
        foreach (['start', 'end'] as $lapse) {
            for ($i = self::MIN_HOUR_FIELD; $i <= self::MAX_HOUR_FIELD; $i++) {
                $hours[$i] = sprintf("%02d", $i);
            }

            for ($i = self::MIN_MINUTE_FIELD; $i < self::MAX_MINUTE_FIELD; $i += 5) {
                $minutes[$i] = sprintf("%02d", $i);
            }

            $elementname = sprintf('%s[%s]', $name, $lapse);
            $elementlabel = get_string(sprintf('%s_time', $lapse), 'customfield_timerange');

            $hourfieldname = sprintf('%s[hour]', $elementname);
            $hourlabel = get_string(sprintf('%s_hour', $lapse), 'customfield_timerange');

            $minutefieldname = sprintf('%s[minute]', $elementname);
            $minutelabel = get_string(sprintf('%s_minute', $lapse), 'customfield_timerange');

            $buttonarray = [];
            $buttonarray[] =& $mform->createElement('select', $hourfieldname, $hourlabel, $hours);
            $buttonarray[] =& $mform->createElement('select', $minutefieldname, $minutelabel, $minutes);
            $mform->addGroup($buttonarray, $elementname, $elementlabel, [' '], false);

            // Définit la valeur par défaut.
            if (!$this->get('id')) {
            foreach (['hour' => $hourfieldname, 'minute' => $minutefieldname] as $unit => $fieldname) {
                $mform->setDefault($fieldname, $config['defaultvalue'][$lapse][$unit]);

                if ($field->get_configdata_property('required')) {
                    $mform->addRule($fieldname, null, 'required', null, 'client');
                }
            }
            }
        }
    }

    /**
     * Validates data for this field.
     *
     * Called from instance edit form in validation()
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array {
        $errors = parent::instance_form_validation($data, $files);

        $elementname = $this->get_form_element_name();
        if (array_key_exists($elementname, $data) === false) {
            return [];
        }

        $units = [];
        $units['hour'] = [self::MIN_HOUR_FIELD, self::MAX_HOUR_FIELD, HOURSECS];
        $units['minute'] = [self::MIN_MINUTE_FIELD, self::MAX_MINUTE_FIELD, MINSECS];

        $seconds = ['start' => 0, 'end' => 0];

        foreach ($seconds as $lapse => $unused) {
            foreach ($units as $unit => $limits) {
                [$min, $max, $tosecond] = $limits;

                $unitname = sprintf('%s_%s', $lapse, $unit);
                $errorkey = sprintf('%s[%s][%s]', $elementname, $lapse, $unit);

                $value = intval($data[$elementname][$lapse][$unit]);
                if ($value < $min || $value > $max) {
                    $errors[$errorkey] = get_string(
                        'the_value_of_the_field_X_must_be_between_Y_and_Z',
                        'customfield_timerange',
                        ['name' => get_string($unitname, 'customfield_timerange'), 'min' => $min, 'max' => $max]
                    );
                    continue;
                }

                $seconds[$lapse] += $value * $tosecond;
            }
        }

        if ($errors === []) {
            if ($seconds['start'] >= $seconds['end']) {
                $errors[sprintf('%s[start]', $elementname)] = get_string(
                    'the_start_time_must_be_less_than_the_end_time',
                    'customfield_timerange'
                );
            }
        }

        return $errors;
    }

    /**
     * Saves the data coming from form
     *
     * @param \stdClass $datanew data coming from the form
     */
    public function instance_form_save(\stdClass $datanew) {
        $elementname = $this->get_form_element_name();
        if (property_exists($datanew, $elementname) === false) {
            return;
        }

        $data = [];
        foreach (['start', 'end'] as $lapse) {
            if (array_key_exists($lapse, $datanew->{$elementname}) === false) {
                return;
            }

            foreach (['hour' => HOURSECS, 'minute' => MINSECS] as $unit => $seconds) {
                if (array_key_exists($unit, $datanew->{$elementname}[$lapse]) === false) {
                    return;
                }

                $data[$lapse][$unit] = intval($datanew->{$elementname}[$lapse][$unit]);
            }
        }

        $json = json_encode($data);
        $intvalue = $data['start']['hour'] * HOURSECS + $data['start']['minute'] * MINSECS;

        $this->data->set('intvalue', $intvalue);
        $this->data->set($this->datafield(), $json);
        $this->data->set('value', $json);
        $this->save();
    }

    /**
     * Prepares the custom field data related to the object to pass to mform->set_data() and adds them to it
     *
     * This function must be called before calling $form->set_data($object);
     *
     * @param \stdClass $instance the entity that has custom fields, if 'id' attribute is present the custom
     *    fields for this entity will be added, otherwise the default values will be added.
     */
    public function instance_form_before_set_data(\stdClass $instance) {
        $value = $this->get($this->datafield());
        if ($value === null) {
            $value = $this->get_default_value();
        }

        $data = json_decode($value, $associative = true);
        if (isset($data->main, $data->additional) === false) {
            $data = json_decode($this->get_default_value(), $associative = true);
        }

        $instance->{$this->get_form_element_name()} = $data;
    }

    /**
     * Returns the default value as it would be stored in the database (not in human-readable format).
     *
     * @return mixed
     */
    public function get_default_value() {
        return json_encode($this->get_field()->get_configdata_property('defaultvalue'));
    }

    /**
     * Returns the value as it is stored in the database or default value if data record is not present
     *
     * @return mixed
     */
    public function get_value() {
        $value = $this->get($this->datafield());
        if ($value === null) {
            return $this->get_default_value();
        }

        $data = json_decode($value);
        if (isset($data->start->hour, $data->start->minute, $data->end->hour, $data->end->minute) === false) {
            return $this->get_default_value();
        }

        return json_encode([
            'start' => ['hour' => $data->start->hour, 'minute' => $data->start->minute],
            'end' => ['hour' => $data->end->hour, 'minute' => $data->end->minute],
        ]);
    }

    /**
     * Returns value in a human-readable format
     *
     * @return mixed|null value or null if empty
     */
    public function export_value() {
        $value = json_decode($this->get_value(), $associative = true);

        return get_string('formatted_value', 'customfield_timerange', [
            'start_hour' => sprintf('%02d', $value['start']['hour']),
            'start_minute' => sprintf('%02d', $value['start']['minute']),
            'end_hour' => sprintf('%02d', $value['end']['hour']),
            'end_minute' => sprintf('%02d', $value['end']['minute']),
        ]);
    }

    /**
     * Compare l'objet courant avec un autre objet.
     *
     * @param \core_customfield\data_controller $otherobject Un objet a comparé avec l'objet courant.
     *
     * @return int Retourne 0 si les 2 objets sont identiques,
     *             un entier négatif si l'objet courant est inférieur à l'autre objet ou
     *             un entier positif si l'objet courant est supérieur à l'autre objet.
     */
    public function compare_with(\core_customfield\data_controller $otherobject): int {
        $value1 = json_decode($this->get_value(), $associative = true);
        $value2 = json_decode($otherobject->get_value(), $associative = true);

        if ($value1 === $value2) {
            return 0;
        }

        if (empty($value1) === true || isset($value1['start_hour'], $value1['start_minute']) === false) {
            return -1;
        }

        if (empty($value2) === true || isset($value2['start_hour'], $value2['start_minute']) === false) {
            return 1;
        }

        return strcoll($value1['start_hour'] . $value1['start_minute'], $value2['start_hour'] . $value2['start_minute']);
    }

}
