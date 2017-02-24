<?php

namespace Drupal\views_autocomplete_filters\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFulltext;

/**
 * Autocomplete for Search API fulltext search for the view to handle fulltext
 * search filtering.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_autocomplete_filters_search_api_fulltext")
 */
class ViewsAutocompleteFiltersSearchApiFulltext extends SearchApiFulltext {

  // Exposed filter options.
  var $alwaysMultiple = TRUE;

  use ViewsAutocompleteFiltersTrait;

}
