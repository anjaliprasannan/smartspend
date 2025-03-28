<?php

namespace Drupal\Component\Utility;

/**
 * Provides helpers for dealing with variables.
 *
 * @ingroup utility
 */
class Variable {

  /**
   * Generates a human-readable name for a callable.
   *
   * @param callable $callable
   *   A callable.
   *
   * @return string
   *   A human-readable name for the callable.
   */
  public static function callableToString($callable): string {
    if ($callable instanceof \Closure) {
      return '[closure]';
    }
    elseif (is_array($callable) && $callable) {
      if (is_object($callable[0])) {
        $callable[0] = get_class($callable[0]);
      }
      return implode('::', $callable);
    }
    elseif (is_string($callable)) {
      return $callable;
    }
    else {
      return '[unknown]';
    }
  }

  /**
   * Drupal-friendly var_export().
   *
   * @param mixed $var
   *   The variable to export.
   * @param string $prefix
   *   A prefix that will be added at the beginning of every lines of the
   *   output.
   *
   * @return string
   *   The variable exported in a way compatible to Drupal's coding standards.
   */
  public static function export($var, $prefix = '') {
    if (is_array($var)) {
      if (empty($var)) {
        $output = '[]';
      }
      else {
        $output = "[\n";
        // Don't export keys if the array is non associative.
        $export_keys = array_values($var) != $var;
        foreach ($var as $key => $value) {
          $output .= '  ' . ($export_keys ? static::export($key) . ' => ' : '') . static::export($value, '  ') . ",\n";
        }
        $output .= ']';
      }
    }
    elseif (is_bool($var)) {
      $output = $var ? 'TRUE' : 'FALSE';
    }
    elseif (is_string($var)) {
      if (str_contains($var, "\n") || str_contains($var, "'")) {
        // If the string contains a line break or a single quote, use the
        // double quote export mode. Encode backslash, dollar symbols, and
        // double quotes and transform some common control characters.
        $var = str_replace(['\\', '$', '"', "\n", "\r", "\t"], ['\\\\', '\$', '\"', '\n', '\r', '\t'], $var);
        $output = '"' . $var . '"';
      }
      else {
        $output = "'" . $var . "'";
      }
    }
    elseif (is_object($var) && get_class($var) === 'stdClass') {
      // var_export() will export stdClass objects using an undefined
      // magic method __set_state() leaving the export broken. This
      // workaround avoids this by casting the object as an array for
      // export and casting it back to an object when evaluated.
      $output = '(object) ' . static::export((array) $var, $prefix);
    }
    else {
      // @todo var_export() does not use long array syntax. Fix in
      // https://www.drupal.org/project/drupal/issues/3476894
      $output = var_export($var, TRUE);
    }

    if ($prefix) {
      $output = str_replace("\n", "\n$prefix", $output);
    }

    return $output;
  }

}
