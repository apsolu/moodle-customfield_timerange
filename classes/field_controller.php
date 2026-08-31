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

/**
 * Base class for custom fields controllers
 *
 * This class is a wrapper around the persistent field class that allows to define the field
 * configuration
 *
 * Contrôle le formulaire d'édition d'une instance de champ personnalisé dans l'administration.
 * Enregistre un entier représentant des secondes. À l'affichage, la valeur est formatée en HH:MM.
 *
 * @package customfield_timerange
 * @copyright 2026 Université Rennes 2
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_controller extends \core_customfield\field_controller {
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
     * Add fields for editing a date field.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_definition(\MoodleQuickForm $mform) {
        // Add elements.
        $mform->addElement('header', 'header_default_settings', get_string('default_field_settings', 'customfield_timerange'));
        $mform->setExpanded('header_default_settings', true);

        for ($i = self::MIN_HOUR_FIELD; $i <= self::MAX_HOUR_FIELD; $i++) {
            $hours[$i] = sprintf("%02d", $i);
        }

        for ($i = self::MIN_MINUTE_FIELD; $i < self::MAX_MINUTE_FIELD; $i += 5) {
            $minutes[$i] = sprintf("%02d", $i);
        }

        foreach (['start', 'end'] as $lapse) {
            foreach (['hour' => $hours, 'minute' => $minutes] as $unit => $options) {
                $labelname = sprintf('%s_%s', $lapse, $unit);
                $elementname = sprintf('configdata[defaultvalue][%s][%s]', $lapse, $unit);

                $mform->addElement('select', $elementname, get_string($labelname, 'customfield_timerange'), $options);
            }
        }

                /*
        $mform->addElement('select', 'configdata[defaultvalue][start][hour]', get_string('start_hour', 'customfield_timerange'), $hours);
        $mform->addElement('select', 'configdata[defaultvalue][start][minute]', get_string('start_minute', 'customfield_timerange'), $minutes);
        $mform->addElement('select', 'configdata[defaultvalue][end][hour]', get_string('end_hour', 'customfield_timerange'), $hours);
        $mform->addElement('select', 'configdata[defaultvalue][end][minute]', get_string('end_minute', 'customfield_timerange'), $minutes);
                 */
    }

    /**
     * Validate the data from the config form.
     *
     * @param array $data
     * @param array $files
     * @return array associative array of error messages
     */
    public function config_form_validation(array $data, $files = []): array {
        $errors = [];

        $units = [];
        $units['hour'] = [self::MIN_HOUR_FIELD, self::MAX_HOUR_FIELD, HOURSECS];
        $units['minute'] = [self::MIN_MINUTE_FIELD, self::MAX_MINUTE_FIELD, MINSECS];

        $seconds = ['start' => 0, 'end' => 0];

        foreach ($seconds as $lapse => $unused) {
            foreach ($units as $unit => $limits) {
                [$min, $max, $tosecond] = $limits;

                $unitname = sprintf('%s_%s', $lapse, $unit);
                $errorkey = sprintf('configdata[defaultvalue][%s][%s]', $lapse, $unit);

                /*
                if (array_key_exists($unit, $data['configdata']['defaultvalue'][$lapse]) === false) {
                    $errors[$errorkey] = get_string(
                        'the_value_of_the_field_X_is_missing',
                        'customfield_timerange',
                        get_string($unitname, 'customfield_timerange')
                    );
                    continue;
                }
                 */

                $value = intval($data['configdata']['defaultvalue'][$lapse][$unit]);
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
                $errors['configdata[defaultvalue][start][hour]'] = get_string(
                    'the_start_time_must_be_less_than_the_end_time',
                    'customfield_timerange'
                );
            }
        }

        return $errors;
    }
}
