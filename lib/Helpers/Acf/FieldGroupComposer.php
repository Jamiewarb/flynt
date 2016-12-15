<?php

namespace Flynt\Helpers\Acf;

use RecursiveDirectoryIterator;
use ACFComposer\ACFComposer;
use Flynt\Helpers\Utils;
use Flynt\Core;

class FieldGroupComposer {
  const FILTER_NAMESPACE = 'Flynt/Components';
  const FIELD_GROUPS_DIR = '/config/fieldGroups';

  protected static $fieldGroupsLoaded = false;

  public static function init() {
    add_action(
      'Flynt/registerComponent',
      ['Flynt\Helpers\Acf\FieldGroupComposer', 'addFieldFilters'],
      11,
      2
    );

    add_action(
      'acf/init',
      ['Flynt\Helpers\Acf\FieldGroupComposer', 'loadFieldGroups']
    );
  }

  public static function loadFieldGroups() {
    // prevent this running more than once
    if (self::$fieldGroupsLoaded) return;

    // Load field groups from files after ACF initializes
    $dir = get_template_directory() . self::FIELD_GROUPS_DIR;

    if (!is_dir($dir)) {
      trigger_error("[ACF] Cannot load field groups: {$dir} is not a valid directory!", E_USER_WARNING);
      return;
    }

    Core::iterateDirectory($dir, function ($file) {
      if ($file->getExtension() === 'json') {
        $filePath = $file->getPathname();
        $config = json_decode(file_get_contents($filePath), true);
        ACFComposer::registerFieldGroup($config);
      }
    });

    self::$fieldGroupsLoaded = true;
  }

  public static function addFieldFilters($componentPath, $componentName) {
    // load fields.json if it exists
    $filePath = $componentPath . '/fields.json';
    if(!is_file($filePath)) return;
    $fields = json_decode(file_get_contents($filePath), true);

    // make sure naming convention is kept
    $componentName = ucfirst($componentName);

    // add filters
    foreach ($fields as $groupKey => $groupValue) {
      $groupKey = ucfirst($groupKey);
      $filterName = self::FILTER_NAMESPACE . "/{$componentName}/{$groupKey}";

      add_filter($filterName, function ($config) use ($groupValue) {
        return $groupValue;
      });
      if (Utils::isAssoc($groupValue) && array_key_exists('sub_fields', $groupValue)) {
        $filterName .= '/SubFields';
        $subFields = $groupValue['sub_fields'];

        add_filter($filterName, function ($subFieldsconfig) use ($subFields) {
          return $subFields;
        });
        self::addFilterForSubFields($filterName, $subFields);
      } elseif (is_array($groupValue)) {
        self::addFilterForSubFields($filterName, $groupValue);
      }
    }
  }

  protected static function addFilterForSubFields($parentFilterName, $subFields) {
    foreach ($subFields as $subField) {
      if (!array_key_exists('name', $subField)) {
        trigger_error('[ACF] Name is missing in Sub Field while adding Filter: ' . $parentFilterName, E_USER_WARNING);
        continue;
      }
      $subFieldName = ucfirst($subField['name']);
      $subFilterName = $parentFilterName . "/{$subFieldName}";

      add_filter($subFilterName, function ($subFieldConfig) use ($subField) {
        return $subField;
      });
    }
  }
}