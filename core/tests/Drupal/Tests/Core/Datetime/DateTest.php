<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Datetime;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\FormattedDateDiff;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @coversDefaultClass \Drupal\Core\Datetime\DateFormatter
 * @group Datetime
 */
class DateTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked string translation.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * The mocked string translation.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * The mocked date formatter class.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The date formatter class where methods can be stubbed.
   *
   * @var \Drupal\Core\Datetime\DateFormatter|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $dateFormatterStub;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');

    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->entityTypeManager->expects($this->any())->method('getStorage')->with('date_format')->willReturn($entity_storage);

    $this->languageManager = $this->createMock('Drupal\Core\Language\LanguageManagerInterface');
    $this->languageManager->expects($this->any())
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => $this->randomMachineName(2)]));
    $this->stringTranslation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
    $this->requestStack = $this->createMock('Symfony\Component\HttpFoundation\RequestStack');

    $config_factory = $this->getConfigFactoryStub(['system.date' => ['country' => ['default' => 'GB']]]);
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('language_manager', $this->languageManager);
    \Drupal::setContainer($container);

    $this->dateFormatter = new DateFormatter($this->entityTypeManager, $this->languageManager, $this->stringTranslation, $this->getConfigFactoryStub(), $this->requestStack);

    $this->dateFormatterStub = $this->getMockBuilder('\Drupal\Core\Datetime\DateFormatter')
      ->setConstructorArgs([$this->entityTypeManager, $this->languageManager, $this->stringTranslation, $this->getConfigFactoryStub(), $this->requestStack])
      ->onlyMethods(['formatDiff'])
      ->getMock();
  }

  /**
   * Tests the formatInterval method.
   *
   * @dataProvider providerTestFormatInterval
   *
   * @covers ::formatInterval
   */
  public function testFormatInterval($interval, $granularity, $expected, $langcode = NULL): void {
    // Mocks a simple formatPlural implementation.
    $this->stringTranslation->expects($this->any())
      ->method('translateString')
      ->willReturnCallback(function (TranslatableMarkup $arg) {
        return $arg->getUntranslatedString();
      });

    // Check if the granularity is specified.
    if ($granularity) {
      $result = $this->dateFormatter->formatInterval($interval, $granularity, $langcode);
    }
    else {
      $result = $this->dateFormatter->formatInterval($interval);
    }
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    $this->assertEquals(new TranslatableMarkup($expected, [], ['langcode' => $langcode], $this->stringTranslation), $result);
  }

  /**
   * Provides some test data for the format interval test.
   */
  public static function providerTestFormatInterval() {
    $data = [
      // Checks for basic seconds.
      [1, 1, '1 sec'],
      [1, 2, '1 sec'],
      [2, 1, '2 sec'],
      [2, 2, '2 sec'],
      // Checks for minutes with seconds.
      [61, 1, '1 min'],
      [61, 2, '1 min 1 sec'],
      [62, 2, '1 min 2 sec'],
      [121, 1, '2 min'],
      [121, 2, '2 min 1 sec'],
      // Check for hours with minutes and seconds.
      [3601, 1, '1 hour'],
      [3601, 2, '1 hour'],
      // Check for higher units.
      [86401, 1, '1 day'],
      [604800, 1, '1 week'],
      [2592000 * 2, 1, '2 months'],
      [31536000 * 2, 1, '2 years'],
      // Check for a complicated one with months weeks and days.
      [2592000 * 2 + 604800 * 3 + 86400 * 4, 3, '2 months 3 weeks 4 days'],
      // Check for the langcode.
      [61, 1, '1 min', 'xxx-lolspeak'],
      // Check with an unspecified granularity.
      [61, NULL, '1 min 1 sec'],
    ];

    return $data;
  }

  /**
   * Tests the formatInterval method for 0 second.
   */
  public function testFormatIntervalZeroSecond(): void {
    $result = $this->dateFormatter->formatInterval(0, 1, 'xxx-lolspeak');
    $this->assertEquals(new TranslatableMarkup('0 sec', [], ['langcode' => 'xxx-lolspeak'], $this->stringTranslation), $result);
  }

  /**
   * Tests the getSampleDateFormats method.
   *
   * @covers \Drupal\Core\Datetime\DateFormatter::getSampleDateFormats
   */
  public function testGetSampleDateFormats(): void {
    $timestamp = strtotime('2015-03-22 14:23:00');
    $expected = $this->dateFormatter->getSampleDateFormats('en', $timestamp, 'Australia/Sydney');

    // Removed characters related to timezone 'e' and 'T', as test does not have
    // timezone set.
    // cspell:disable-next-line
    $date_characters = 'dDjlNSwzWFmMntLoYyaABgGhHisuIOPZcrU';
    $date_chars = str_split($date_characters);

    foreach ($date_chars as $val) {
      $this->assertEquals($expected[$val], date($val, $timestamp));
    }
  }

  /**
   * Tests the formatTimeDiffUntil method.
   *
   * @covers ::formatTimeDiffUntil
   */
  public function testFormatTimeDiffUntil(): void {
    $expected = '1 second';
    $request_time = self::createTimestamp('2013-12-11 10:09:08');
    $timestamp = self::createTimestamp('2013-12-11 10:09:09');
    $options = [];

    // Mocks the formatDiff function of the DateFormatter object.
    $this->dateFormatterStub
      ->expects($this->exactly(2))
      ->method('formatDiff')
      ->willReturnMap([
        [$timestamp, $request_time, $options, $expected],
        [$timestamp, $request_time, $options + ['return_as_object' => TRUE], new FormattedDateDiff('1 second', 1)],
      ]);

    $request = Request::createFromGlobals();
    $request->server->set('REQUEST_TIME', $request_time);
    // Mocks a the request stack getting the current request.
    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->assertEquals($expected, $this->dateFormatterStub->formatTimeDiffSince($timestamp, $options));
    $options['return_as_object'] = TRUE;
    $expected_object = new FormattedDateDiff('1 second', 1);
    $this->assertEquals($expected_object, $this->dateFormatterStub->formatTimeDiffSince($timestamp, $options));
  }

  /**
   * Tests the formatTimeDiffSince method.
   *
   * @covers ::formatTimeDiffSince
   */
  public function testFormatTimeDiffSince(): void {
    $expected = '1 second';
    $timestamp = self::createTimestamp('2013-12-11 10:09:07');
    $request_time = self::createTimestamp('2013-12-11 10:09:08');
    $options = [];

    // Mocks the formatDiff function of the DateFormatter object.
    $this->dateFormatterStub
      ->expects($this->exactly(2))
      ->method('formatDiff')
      ->willReturnMap([
        [$request_time, $timestamp, $options, $expected],
        [$request_time, $timestamp, $options + ['return_as_object' => TRUE], new FormattedDateDiff('1 second', 1)],
      ]);

    $request = Request::createFromGlobals();
    $request->server->set('REQUEST_TIME', $request_time);
    // Mocks a the request stack getting the current request.
    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->willReturn($request);

    $this->assertEquals($expected, $this->dateFormatterStub->formatTimeDiffUntil($timestamp, $options));
    $options['return_as_object'] = TRUE;
    $expected_object = new FormattedDateDiff('1 second', 1);
    $this->assertEquals($expected_object, $this->dateFormatterStub->formatTimeDiffUntil($timestamp, $options));
  }

  /**
   * Tests the formatDiff method.
   *
   * @dataProvider providerTestFormatDiff
   *
   * @covers ::formatDiff
   */
  public function testFormatDiff(string $expected, int $max_age, int $timestamp1, int $timestamp2, array $options = []): void {
    // Mocks a simple translateString implementation.
    $this->stringTranslation->expects($this->any())
      ->method('translateString')
      ->willReturnCallback(function (TranslatableMarkup $arg) {
        return $arg->getUntranslatedString();
      });

    if (isset($options['langcode'])) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $expected_markup = new TranslatableMarkup($expected, [], ['langcode' => $options['langcode']], $this->stringTranslation);
    }
    else {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $expected_markup = new TranslatableMarkup($expected, [], [], $this->stringTranslation);
    }
    $this->assertEquals($expected_markup, $this->dateFormatter->formatDiff($timestamp1, $timestamp2, $options));

    $options['return_as_object'] = TRUE;
    $expected_object = new FormattedDateDiff($expected, $max_age);
    $this->assertEquals($expected_object, $this->dateFormatter->formatDiff($timestamp1, $timestamp2, $options));
  }

  /**
   * Data provider for testFormatDiff().
   */
  public static function providerTestFormatDiff(): array {
    // This is the fixed request time in the test.
    $request_time = self::createTimestamp('2013-12-11 10:09:08');

    $granularity_3 = ['granularity' => 3];
    $granularity_4 = ['granularity' => 4];

    $langcode_en = ['langcode' => 'en'];
    $langcode_lolspeak = ['langcode' => 'xxx-lolspeak'];

    $non_strict = ['strict' => FALSE];

    $data = [
      // Checks for equal timestamps.
      ['0 seconds', 0, $request_time, $request_time],

      // Checks for seconds only.
      ['1 second', 1, self::createTimestamp('2013-12-11 10:09:07'), $request_time],
      ['1 second', 1, self::createTimestamp('2013-12-11 10:09:07'), $request_time],
      ['1 second', 1, self::createTimestamp('2013-12-11 10:09:07'), $request_time, $granularity_3 + $langcode_en],
      ['1 second', 1, self::createTimestamp('2013-12-11 10:09:07'), $request_time, $granularity_4 + $langcode_lolspeak],
      ['2 seconds', 1, self::createTimestamp('2013-12-11 10:09:06'), $request_time],
      ['59 seconds', 1, self::createTimestamp('2013-12-11 10:08:09'), $request_time],
      ['59 seconds', 1, self::createTimestamp('2013-12-11 10:08:09'), $request_time],

      // Checks for minutes and possibly seconds.
      ['1 minute', 60, self::createTimestamp('2013-12-11 10:08:08'), $request_time],
      ['1 minute', 60, self::createTimestamp('2013-12-11 10:08:08'), $request_time],
      ['1 minute 1 second', 1, self::createTimestamp('2013-12-11 10:08:07'), $request_time],
      ['1 minute 59 seconds', 1, self::createTimestamp('2013-12-11 10:07:09'), $request_time],
      ['2 minutes', 60, self::createTimestamp('2013-12-11 10:07:08'), $request_time],
      ['2 minutes 1 second', 1, self::createTimestamp('2013-12-11 10:07:07'), $request_time],
      ['2 minutes 2 seconds', 1, self::createTimestamp('2013-12-11 10:07:06'), $request_time],
      ['2 minutes 2 seconds', 1, self::createTimestamp('2013-12-11 10:07:06'), $request_time, $granularity_3],
      ['2 minutes 2 seconds', 1, self::createTimestamp('2013-12-11 10:07:06'), $request_time, $granularity_4],
      ['30 minutes', 60, self::createTimestamp('2013-12-11 09:39:08'), $request_time],
      ['59 minutes 59 seconds', 1, self::createTimestamp('2013-12-11 09:09:09'), $request_time],
      ['59 minutes 59 seconds', 1, self::createTimestamp('2013-12-11 09:09:09'), $request_time],

      // Checks for hours and possibly minutes or seconds.
      ['1 hour', 3600, self::createTimestamp('2013-12-11 09:09:08'), $request_time],
      ['1 hour', 3600, self::createTimestamp('2013-12-11 09:09:08'), $request_time],
      ['1 hour', 3600, self::createTimestamp('2013-12-11 09:09:07'), $request_time],
      ['1 hour', 3600, self::createTimestamp('2013-12-11 09:09:06'), $request_time],
      ['1 hour 1 minute', 60, self::createTimestamp('2013-12-11 09:08:08'), $request_time],
      ['1 hour 1 minute 1 second', 1, self::createTimestamp('2013-12-11 09:08:07'), $request_time, $granularity_3],
      ['1 hour 1 minute 2 seconds', 1, self::createTimestamp('2013-12-11 09:08:06'), $request_time, $granularity_4],
      ['1 hour 30 minutes', 60, self::createTimestamp('2013-12-11 08:39:08'), $request_time],
      ['2 hours', 3600, self::createTimestamp('2013-12-11 08:09:08'), $request_time],
      ['23 hours 59 minutes', 60, self::createTimestamp('2013-12-10 10:10:08'), $request_time],

      // Checks for days and possibly hours, minutes or seconds.
      ['1 day', 86400, self::createTimestamp('2013-12-10 10:09:08'), $request_time],
      ['1 day', 86400, self::createTimestamp('2013-12-10 10:09:07'), $request_time],
      ['1 day 1 hour', 3600, self::createTimestamp('2013-12-10 09:09:08'), $request_time],
      ['1 day 1 hour 1 minute', 60, self::createTimestamp('2013-12-10 09:08:07'), $request_time, $granularity_3 + $langcode_en],
      ['1 day 1 hour 1 minute 1 second', 1, self::createTimestamp('2013-12-10 09:08:07'), $request_time, $granularity_4 + $langcode_lolspeak],
      ['1 day 2 hours 2 minutes 2 seconds', 1, self::createTimestamp('2013-12-10 08:07:06'), $request_time, $granularity_4],
      ['2 days', 86400, self::createTimestamp('2013-12-09 10:09:08'), $request_time],
      ['2 days', 86400, self::createTimestamp('2013-12-09 10:07:08'), $request_time],
      ['2 days 2 hours', 3600, self::createTimestamp('2013-12-09 08:09:08'), $request_time],
      ['2 days 2 hours 2 minutes', 60, self::createTimestamp('2013-12-09 08:07:06'), $request_time, $granularity_3 + $langcode_en],
      ['2 days 2 hours 2 minutes 2 seconds', 1, self::createTimestamp('2013-12-09 08:07:06'), $request_time, $granularity_4 + $langcode_lolspeak],

      // Checks for weeks and possibly days, hours, minutes or seconds.
      ['1 week', 7 * 86400, self::createTimestamp('2013-12-04 10:09:08'), $request_time],
      ['1 week 1 day', 86400, self::createTimestamp('2013-12-03 10:09:08'), $request_time],
      ['2 weeks', 7 * 86400, self::createTimestamp('2013-11-27 10:09:08'), $request_time],
      ['2 weeks 2 days', 86400, self::createTimestamp('2013-11-25 08:07:08'), $request_time],
      ['2 weeks 2 days 2 hours 2 minutes', 60, self::createTimestamp('2013-11-25 08:07:08'), $request_time, $granularity_4],
      ['4 weeks', 7 * 86400, self::createTimestamp('2013-11-13 10:09:08'), $request_time],
      ['4 weeks 1 day', 86400, self::createTimestamp('2013-11-12 10:09:08'), $request_time],

      // Checks for months and possibly days, hours, minutes or seconds.
      ['1 month', 30 * 86400, self::createTimestamp('2013-11-11 10:09:08'), $request_time],
      ['1 month', 30 * 86400, self::createTimestamp('2013-11-11 10:09:07'), $request_time],
      ['1 month', 30 * 86400, self::createTimestamp('2013-11-11 09:09:08'), $request_time],
      ['1 month', 30 * 86400, self::createTimestamp('2013-11-11 09:08:07'), $request_time, $granularity_3],
      ['1 month', 30 * 86400, self::createTimestamp('2013-11-11 09:08:07'), $request_time, $granularity_4],
      ['1 month 4 weeks', 7 * 86400, self::createTimestamp('2013-10-13 10:09:08'), $request_time],
      ['1 month 4 weeks 1 day', 86400, self::createTimestamp('2013-10-13 10:09:08'), $request_time, $granularity_3],
      ['1 month 4 weeks', 7 * 86400, self::createTimestamp('2013-10-12 10:09:08'), $request_time],
      ['1 month 4 weeks 2 days', 86400, self::createTimestamp('2013-10-12 10:09:08'), $request_time, $granularity_3],
      ['2 months', 30 * 86400, self::createTimestamp('2013-10-11 10:09:08'), $request_time],
      ['2 months', 30 * 86400, self::createTimestamp('2013-10-10 10:09:08'), $request_time],
      ['2 months', 30 * 86400, self::createTimestamp('2013-10-09 08:07:06'), $request_time],
      ['2 months', 30 * 86400, self::createTimestamp('2013-10-09 08:07:06'), $request_time, $granularity_3],
      ['2 months', 30 * 86400, self::createTimestamp('2013-10-09 08:07:06'), $request_time, $granularity_4],
      ['6 months', 30 * 86400, self::createTimestamp('2013-06-09 10:09:08'), $request_time],
      ['11 months', 30 * 86400, self::createTimestamp('2013-01-11 07:09:08'), $request_time],
      ['11 months 4 weeks', 7 * 86400, self::createTimestamp('2012-12-12 10:09:08'), $request_time],
      ['11 months 4 weeks 2 days', 86400, self::createTimestamp('2012-12-12 10:09:08'), $request_time, $granularity_3],

      // Checks for years and possibly months, days, hours, minutes or seconds.
      ['1 year', 365 * 86400, self::createTimestamp('2012-12-11 10:09:08'), $request_time],
      ['1 year', 365 * 86400, self::createTimestamp('2012-12-11 10:08:08'), $request_time],
      ['1 year', 365 * 86400, self::createTimestamp('2012-12-10 10:09:08'), $request_time],
      ['2 years', 365 * 86400, self::createTimestamp('2011-12-11 10:09:08'), $request_time],
      ['2 years', 365 * 86400, self::createTimestamp('2011-12-11 10:07:08'), $request_time],
      ['2 years', 365 * 86400, self::createTimestamp('2011-12-09 10:09:08'), $request_time],
      ['2 years 2 months', 30 * 86400, self::createTimestamp('2011-10-09 08:07:06'), $request_time, $granularity_3],
      ['2 years 2 months', 30 * 86400, self::createTimestamp('2011-10-09 08:07:06'), $request_time, $granularity_4],
      ['10 years', 365 * 86400, self::createTimestamp('2003-12-11 10:09:08'), $request_time],
      ['100 years', 365 * 86400, self::createTimestamp('1913-12-11 10:09:08'), $request_time],

      // Checks the non-strict option vs. strict (default).
      ['1 second', 1, self::createTimestamp('2013-12-11 10:09:08'), self::createTimestamp('2013-12-11 10:09:07'), $non_strict],
      ['0 seconds', 0, self::createTimestamp('2013-12-11 10:09:08'), self::createTimestamp('2013-12-11 10:09:07')],

      // Checks granularity limit.
      ['2 years 3 months 1 week', 7 * 86400, self::createTimestamp('2011-08-30 11:15:57'), $request_time, $granularity_3],
    ];

    return $data;
  }

  /**
   * Tests FormattedDateDiff.
   *
   * @covers \Drupal\Core\Datetime\FormattedDateDiff::toRenderable
   * @covers \Drupal\Core\Datetime\FormattedDateDiff::getString
   * @covers \Drupal\Core\Datetime\FormattedDateDiff::getCacheMaxAge
   */
  public function testFormattedDateDiff(): void {
    $string = '10 minutes';
    $max_age = 60;
    $object = new FormattedDateDiff($string, $max_age);

    // Test conversion to a render array.
    $expected = [
      '#markup' => $string,
      '#cache' => [
        'max-age' => $max_age,
      ],
    ];
    $this->assertEquals($expected, $object->toRenderable());

    // Test retrieving the formatted time difference string.
    $this->assertEquals($string, $object->getString());

    // Test applying cacheability data to an existing build.
    $build = [];
    CacheableMetadata::createFromObject($object)->applyTo($build);
    $this->assertEquals($max_age, $build['#cache']['max-age']);
  }

  /**
   * Tests that an RFC2822 formatted date always returns an English string.
   *
   * @see http://www.faqs.org/rfcs/rfc2822.html
   *
   * @covers ::format
   */
  public function testRfc2822DateFormat(): void {
    $timestamp = 1549110600;
    $langcodes = array_keys(LanguageManager::getStandardLanguageList());
    $langcodes[] = NULL;
    foreach ($langcodes as $langcode) {
      $formatted_date = $this->dateFormatter->format($timestamp, 'custom', 'r', 'Europe/Berlin', $langcode);
      // Check that RFC2822 format date is returned regardless of langcode.
      $this->assertSame('Sat, 02 Feb 2019 13:30:00 +0100', $formatted_date);
    }
  }

  /**
   * Creates a UNIX timestamp given a date and time string.
   *
   * @param string $dateTimeString
   *   The formatted date and time string. The format is year-month-day
   *   hour:minute:seconds (e.g. 2013-12-11 10:09:08).
   *
   * @return int
   *   The UNIX timestamp.
   */
  private static function createTimestamp(string $dateTimeString): int {
    return \DateTime::createFromFormat('Y-m-d G:i:s', $dateTimeString)->getTimestamp();
  }

}
