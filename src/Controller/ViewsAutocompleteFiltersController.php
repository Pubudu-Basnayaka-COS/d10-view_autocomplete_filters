<?php

/**
 * @file
 * Contains \Drupal\views_autocomplete_filters\Controller\ViewsAutocompleteFiltersController.
 */

namespace Drupal\views_autocomplete_filters\Controller;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns autocomplete responses for taxonomy terms.
 */
class ViewsAutocompleteFiltersController implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')->get('taxonomy_term'),
      $container->get('entity.manager')
    );
  }

  /**
   * Retrieves suggestions for taxonomy term autocompletion.
   *
   * This function outputs term name suggestions in response to Ajax requests
   * made by the taxonomy autocomplete widget for taxonomy term reference
   * fields. The output is a JSON object of plain-text term suggestions, keyed
   * by the user-entered value with the completed term name appended.
   * Term names containing commas are wrapped in quotes.
   *
   * For example, suppose the user has entered the string 'red fish, blue' in
   * the field, and there are two taxonomy terms, 'blue fish' and 'blue moon'.
   * The JSON output would have the following structure:
   * @code
   *   {
   *     "red fish, blue fish": "blue fish",
   *     "red fish, blue moon": "blue moon",
   *   };
   * @endcode
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $entity_type
   *   The entity_type.
   * @param string $field_name
   *   The name of the term reference field.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   *   When valid field name is specified, a JSON response containing the
   *   autocomplete suggestions for taxonomy terms. Otherwise a normal response
   *   containing an error message.
   */
  public function autocomplete(Request $request, $view_name, $view_display, $filter_name, $view_args) {
    $matches = array();
    $string = $request->query->get('q');
    // Get view and execute.
    $view_name = 'xxx';
    $view_display = 'page_1';
    $view = Views::getView($view_name);
    $view->setDisplay($view_display);
    if (!empty($view_args)) {
      $view->setArguments(explode('||', $view_args));
    }
    // Set display and display handler vars for quick access.
    //$display = $view->display[$view_display];
    $displayHandler = $view->display_handler;
  
    // Force "Display all values" for arguments set,
    // to get results no matter of Not Contextual filter present settings.
    $arguments = $displayHandler->getOption('arguments');
    if (!empty($arguments)) {
      foreach ($arguments as $k => $argument) {
        $arguments[$k]['default_action'] = 'ignore';
      }
      $displayHandler->setOption('arguments', $arguments);
    }
    $matches = $view->result;

    // Get exposed filter options for our field.
    // Also, check if filter is exposed and autocomplete is enabled for this
    // filter (and if filter exists/exposed at all).
    $filters = $displayHandler->getOption('filters');
    if (empty($filters[$filter_name]['exposed']) || empty($filters[$filter_name]['expose']['autocomplete_filter'])) {
      throw new NotFoundHttpException();
    }
    $filter = $filters[$filter_name];
    $exposeOptions = $filter['expose'];

    // Do not filter if the string length is less that minimum characters setting.
    if (strlen(trim($string)) < $exposeOptions['autocomplete_min_chars']) {
      $matches[''] = '<div class="reference-autocomplete">' . t('The %string should have at least %min_chars characters.', array('%string' => $string, '%min_chars' => $exposeOptions['autocomplete_min_chars'])) . '</div>';
      return drupal_json_output($matches);
    }

    // Determine fields which will be used for output.
    if (empty($exposeOptions['autocomplete_field']) && !empty($filter['name']) ) {
      if ($view->getHandler($display_name, 'field', $filters[$filter_name]['id'])) {
        $field_names = array([$filter_name]['id']);
        // force raw data for no autocomplete field defined.
        $exposeOptions['autocomplete_raw_suggestion'] = 1;
        $exposeOptions['autocomplete_raw_dropdown'] = 1;
      }
      else {
        // Field is not set, report about it to watchdog and return empty array.
        watchdog('views_autocomplete_filters', 'Field for autocomplete filter %label is not set in view %view, display %display', array(
          '%label' => $exposeOptions['label'],
          '%view' => $view->name,
          '%display' => $display->id,
        ), WATCHDOG_ERROR);
        return new JsonResponse($matches);
      }
    }
    // Text field autocomplete filter.
    elseif (!empty($exposeOptions['autocomplete_field'])) {
      $field_names = array($exposeOptions['autocomplete_field']);
    }
    // For combine fields autocomplete filter.
    elseif (!empty($filter['fields'])) {
      $field_names = array_keys($filter['fields']);
    }

    // Get fields options and check field exists in this display.
    foreach ($field_names as $field_name) {
      $fieldOptions = $view->getHandler($view_display, 'field', $field_name);
      if (empty($fieldOptions)) {
        // Field not exists, report about it to watchdog and return empty array.
        watchdog('views_autocomplete_filters', 'Field for autocomplete filter %label not exists in view %view, display %display', array(
          '%label' => $exposeOptions['label'],
          '%view' => $view->name,
          '%display' => $display->id,
        ), WATCHDOG_ERROR);
        return new JsonResponse($matches);
      }
    }
    // Collect exposed filter values and set them to the view.
    if (!empty($exposeOptions['autocomplete_dependent'])) {
      $exposedInput = $view->getExposedInput() ;
    }
    else {
      $exposedInput = array();
    }
    $exposedInput[$exposeOptions['identifier']] = $string;
    $view->setExposedInput($exposedInput);

    // Disable cache for view, because caching autocomplete is a waste of time and memory.
    $displayHandler->setOption('cache', array('type' => 'none'));

    // Force limit for results.
    if (empty($exposeOptions['autocomplete_items'])) {
      $pager_type = 'none';
    }
    else {
      $pager_type = 'some';
    }
    $pager = array(
      'type' => $pager_type,
      'options' => array(
        'items_per_page' => $exposeOptions['autocomplete_items'],
        'offset' => 0,
      ),
    );
    $displayHandler->setOption('pager', $pager);

    // Execute view query.
    $view->preExecute();
    $view->execute();
    $view->postExecute();
    $display_handler = $view->display_handler;
  
    // Render field on each row and fill matches array.
    $useRawSuggestion = !empty($exposeOptions['autocomplete_raw_suggestion']);
    $useRawDropdown = !empty($exposeOptions['autocomplete_raw_dropdown']);

    $view->row_index = 0;
    foreach ($view->result as $index => $row) {
      $view->row_index = $index;
      $rendered_field = $raw_field = '';
      $stylePluginBase = $display_handler->getPlugin('style');
      //print_r($stylePluginBase);
  
      foreach ($field_names as $field_name) {
        // Render field only if suggestion or dropdown item not in RAW format.
        if (!$useRawSuggestion || !$useRawDropdown) {
          $rendered_field = $view->StylePluginBase->getField($index, $field_name);
        }
        // Get the raw field value only if suggestion or dropdown item is in RAW format.
        if ($useRawSuggestion || $useRawDropdown) {
          $raw_field = $stylePluginBase->getFieldValue($index, $field_name);
          if (!is_array($raw_field)) {
            $raw_field = array(array('value' => $raw_field));
          }
        }
  
        if (empty($raw_field)) {
          $raw_field = array(array('value' => $rendered_field));
        }
        foreach ($raw_field as $delta => $item) {
          if (isset($item['value']) && strstr(Unicode::strtolower($item['value']), Unicode::strtolower($string))) {
            $dropdown = $useRawDropdown ? String::checkPlain($item['value']) : $rendered_field;
            if ($dropdown != '') {
              $suggestion = $useRawSuggestion ? String::checkPlain($item['value']) : $rendered_field;
              $suggestion = String::decodeEntities($suggestion);

              // Add a class wrapper for a few required CSS overrides.
              $matches[$suggestion] = '<div class="reference-autocomplete">' . $dropdown . '</div>';
            }
          }
        }
      }
    }
    unset($view->row_index);

    if (empty($matches)) {
      $matches[''] = '<div class="reference-autocomplete">' . t('The %string return no results. Please try something else.', array('%string' => $string)) . '</div>';
    }

    return new JsonResponse($matches);
  }

}
