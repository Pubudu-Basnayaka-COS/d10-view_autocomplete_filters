<?php

/**
 * @file
 * Definition of Drupal\views_autocomplete_filters\Plugin\views\filter\ViewsAutocompleteFiltersString.
 */

namespace Drupal\views_autocomplete_filters\Plugin\views\filter;

use Drupal\Component\Utility\String as UtilityString;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\String;

/**
 * Basic textfield filter to handle string filtering commands
 * including equality, like, not like, etc.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_autocomplete_filters_string")
 */
class ViewsAutocompleteFiltersString extends String {

  // exposed filter options
  var $alwaysMultiple = TRUE;

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['expose']['contains']['required'] = array('default' => FALSE, 'bool' => TRUE);
    $options['expose']['contains'] += array(
      'autocomplete_filter' => array('default' => 0),
      'autocomplete_items' => array('default' => 10),
      'autocomplete_field' => array('default' => ''),
      'autocomplete_raw_suggestion' => array('default' => TRUE),
      'autocomplete_raw_dropdown' => array('default' => TRUE),
      'autocomplete_dependent' => array('default' => FALSE),
    );

    return $options;
  }

  /**
   * Build the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    if ($this->canExpose() && !empty($form['expose'])) {
      $field_options_all = $this->view->display_handler->getFieldLabels();
      // Limit options to fields with the same name.
      foreach ($this->view->display_handler->getHandlers('field') as $id => $handler) {
        if ($handler->real_field == $this->real_field) {
          $field_options[$id] = $field_options_all[$id];
        }
      }
      if (empty($field_options)) {
        $field_options[''] = $this->t('<Add some fields to view>');
      }
      elseif (empty($this->options['expose']['autocomplete_field']) && !empty($field_options[$this->options['id']])) {
        $this->options['expose']['autocomplete_field'] = $this->options['id'];
      }

      // Build form elements for the right side of the exposed filter form
      $form['expose'] += array(
        'autocomplete_filter' => array(
          '#type' => 'checkbox',
          '#title' => $this->t('Use Autocomplete'),
          '#default_value' => $this->options['expose']['autocomplete_filter'],
          '#description' => $this->t('Use Autocomplete for this filter.'),
        ),
        'autocomplete_items' => array(
          '#type' => 'textfield',
          '#title' => $this->t('Maximum number of items in Autocomplete'),
          '#default_value' => $this->options['expose']['autocomplete_items'],
          '#description' => $this->t('Enter 0 for no limit.'),
          '#states' => array(
            'visible' => array('
              :input[name="options[expose][autocomplete_filter]"]' => array('checked' => TRUE),
            ),
          ),
        ),
        'autocomplete_dependent' => array(
          '#type' => 'checkbox',
          '#title' => $this->t('Suggestions depend on other filter fields'),
          '#default_value' => $this->options['expose']['autocomplete_dependent'],
          '#description' => $this->t('Autocomplete suggestions will be filtered by other filter fields'),
          '#states' => array(
            'visible' => array('
              :input[name="options[expose][autocomplete_filter]"]' => array('checked' => TRUE),
            ),
          ),
        ),
        'autocomplete_field' => array(
          '#type' => 'select',
          '#title' => $this->t('Field with autocomplete results'),
          '#default_value' => $this->options['expose']['autocomplete_field'],
          '#options' => $field_options,
          '#description' => $this->t('Selected field will be used for dropdown results of autocomplete. In most cases it should be the same field you use for filter.'),
          '#states' => array(
            'visible' => array('
              :input[name="options[expose][autocomplete_filter]"]' => array('checked' => TRUE),
            ),
          ),
        ),
        'autocomplete_raw_dropdown' => array(
          '#type' => 'checkbox',
          '#title' => $this->t('Unformatted dropdown'),
          '#default_value' => $this->options['expose']['autocomplete_raw_dropdown'],
          '#description' => $this->t('Use unformatted data from database for dropdown list instead of field formatter result. Value will be printed as plain text.'),
          '#states' => array(
            'visible' => array('
              :input[name="options[expose][autocomplete_filter]"]' => array('checked' => TRUE),
            ),
          ),
        ),
        'autocomplete_raw_suggestion' => array(
          '#type' => 'checkbox',
          '#title' => $this->t('Unformatted suggestion'),
          '#default_value' => $this->options['expose']['autocomplete_raw_suggestion'],
          '#description' => $this->t('The same as above, but for suggestion (text appearing inside textfield when item is selected).'),
          '#states' => array(
            'visible' => array('
              :input[name="options[expose][autocomplete_filter]"]' => array('checked' => TRUE),
            ),
          ),
        ),
      );
    }
  }

  public function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    if (empty($form_state->exposed) || empty($this->options['expose']['autocomplete_filter'])) {
      // It's not an exposed form or autocomplete is not enabled.
      return;
    }

    if (empty($form['value']['#type']) || $form['value']['#type'] !== 'textfield') {
      // Not a textfield.
      return;
    }

    // Add autocomplete path to the exposed textfield.
    $form['value']['#autocomplete_path'] = 'autocomplete_filter/' . $this->options['id'] . '/' . $this->view->storage->id . '/' . $this->view->current_display;

    // Add JS script with core autocomplete overrides to the end of JS files
    // list to be sure it is added after the "misc/autocomplete.js" file. Also
    // mark the field with special class.
    if (!empty($this->options['expose']['autocomplete_dependent'])) {
      $file_path = drupal_get_path('module', 'views_autocomplete_filters') . '/js/views-autocomplete-filters-dependent.js';
      drupal_add_js($file_path, array(
        'weight' => 99,
      ));

      $form['value']['#attributes']['class'][] = 'views-ac-dependent-filter';
    }
  }

}
