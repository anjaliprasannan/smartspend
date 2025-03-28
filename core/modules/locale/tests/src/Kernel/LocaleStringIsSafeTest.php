<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Kernel;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests locale translation safe string handling.
 *
 * @group locale
 */
class LocaleStringIsSafeTest extends KernelTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['locale', 'locale_test'];

  /**
   * Tests for locale_string_is_safe().
   */
  public function testLocaleStringIsSafe(): void {
    // Check a translatable string without HTML.
    $string = 'Hello world!';
    $result = locale_string_is_safe($string);
    $this->assertTrue($result);

    // Check a translatable string which includes trustable HTML.
    $string = 'Hello <strong>world</strong>!';
    $result = locale_string_is_safe($string);
    $this->assertTrue($result);

    // Check an untranslatable string which includes unsafe HTML (according to
    // the locale_string_is_safe() function definition).
    $string = 'Hello <img src="world.png" alt="world" />!';
    $result = locale_string_is_safe($string);
    $this->assertFalse($result);

    // Check a translatable string which includes a token in an href attribute.
    $string = 'Hi <a href="[current-user:url]">user</a>';
    $result = locale_string_is_safe($string);
    $this->assertTrue($result);

    // Check a translatable string which includes a wbr tag.
    $string = 'DrupalLocaleModule<wbr>Test<wbr>Example';
    $result = locale_string_is_safe($string);
    $this->assertTrue($result);
  }

  /**
   * Tests if a translated and tokenized string is properly escaped by Twig.
   *
   * In each assert* call we add a new line at the expected result to match the
   * newline at the end of the template file.
   */
  public function testLocalizedTokenizedString(): void {
    $tests_to_do = [
      1 => [
        'original' => 'Go to the <a href="[locale_test:security_test1]">frontpage</a>',
        'replaced' => 'Go to the &lt;a href=&quot;javascript:alert(&amp;#039;Hello!&amp;#039;);&quot;&gt;frontpage&lt;/a&gt;',
      ],
      2 => [
        'original' => 'Hello <strong>[locale_test:security_test2]</strong>!',
        'replaced' => 'Hello &lt;strong&gt;&amp;lt;script&amp;gt;alert(&amp;#039;Hello!&amp;#039;);&amp;lt;/script&amp;gt;&lt;/strong&gt;!',
      ],
    ];

    foreach ($tests_to_do as $i => $test) {
      $original_string = $test['original'];
      $rendered_original_string = \Drupal::theme()->render('locale_test_tokenized', ['content' => $original_string]);
      // Twig assumes that strings are unsafe so it escapes them, and so the
      // original and the rendered version should be different.
      $this->assertNotEquals(
        $original_string . "\n",
        $rendered_original_string,
        'Security test ' . $i . ' before translation'
      );

      // Pass the original string to the t() function to get it marked as safe.
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $safe_string = $this->t($original_string);
      $rendered_safe_string = \Drupal::theme()->render('locale_test_tokenized', ['content' => $safe_string]);
      // t() function always marks the string as safe so it won't be escaped,
      // and should be the same as the original.
      $this->assertSame($original_string . "\n", (string) $rendered_safe_string, 'Security test ' . $i . ' after translation before token replacement');

      // Replace tokens in the safe string to inject it with dangerous content.
      // @see locale_test_tokens().
      $unsafe_string = \Drupal::token()->replace($safe_string);
      $rendered_unsafe_string = \Drupal::theme()->render('locale_test_tokenized', ['content' => $unsafe_string]);
      // Token replacement changes the string so it is not marked as safe
      // anymore. Check it is escaped the way we expect.
      $this->assertEquals($test['replaced'] . "\n", $rendered_unsafe_string, 'Security test ' . $i . ' after translation  after token replacement');
    }
  }

}
