<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Rest;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Cache\Cache;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\EntityTestHelper;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\Before;

/**
 * Resource test base for the comment entity.
 */
abstract class CommentResourceTestBase extends EntityResourceTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['comment', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'comment';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'status' => "The 'administer comments' permission is required.",
    'uid' => "The 'administer comments' permission is required.",
    'pid' => NULL,
    'entity_id' => NULL,
    'name' => "The 'administer comments' permission is required.",
    'homepage' => "The 'administer comments' permission is required.",
    'created' => "The 'administer comments' permission is required.",
    'changed' => NULL,
    'thread' => NULL,
    'entity_type' => NULL,
    'field_name' => NULL,
  ];

  /**
   * @var \Drupal\comment\CommentInterface
   */
  protected $entity;

  /**
   * Marks some tests as skipped because XML cannot be deserialized.
   */
  #[Before]
  public function commentResourceTestBaseSkipTests(): void {
    if (static::$format === 'xml' && in_array($this->name(), ['testPostDxWithoutCriticalBaseFields', 'testPostSkipCommentApproval'], TRUE)) {
      $this->markTestSkipped('Deserialization of the XML format is not supported.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['access comments', 'view test entity']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole(['post comments']);
        break;

      case 'PATCH':
        // Anonymous users are not ever allowed to edit their own comments. To
        // be able to test PATCHing comments as the anonymous user, the more
        // permissive 'administer comments' permission must be granted.
        // @see \Drupal\comment\CommentAccessControlHandler::checkAccess
        if (static::$auth) {
          $this->grantPermissionsToTestedRole(['edit own comments']);
        }
        else {
          $this->grantPermissionsToTestedRole(['administer comments']);
        }
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer comments']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "bar" bundle for the "entity_test" entity type and create.
    $bundle = 'bar';
    EntityTestHelper::createBundle($bundle, NULL, 'entity_test');

    // Create a comment field on this bundle.
    $this->addDefaultCommentField('entity_test', 'bar', 'comment');

    // Create a "Camelids" test entity that the comment will be assigned to.
    $commented_entity = EntityTest::create([
      'name' => 'Camelids',
      'type' => 'bar',
      'comment' => CommentItemInterface::OPEN,
    ]);
    $commented_entity->save();

    // Create a "Llama" comment.
    $comment = Comment::create([
      'comment_body' => [
        'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
        'format' => 'plain_text',
      ],
      'entity_id' => $commented_entity->id(),
      'entity_type' => 'entity_test',
      'field_name' => 'comment',
    ]);
    $comment->setSubject('Llama')
      ->setOwnerId(static::$auth ? $this->account->id() : 0)
      ->setPublished()
      ->setCreatedTime(123456789)
      ->setChangedTime(123456789);
    $comment->save();

    return $comment;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $author = User::load($this->entity->getOwnerId());
    return [
      'cid' => [
        ['value' => 1],
      ],
      'uuid' => [
        ['value' => $this->entity->uuid()],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'comment_type' => [
        [
          'target_id' => 'comment',
          'target_type' => 'comment_type',
          'target_uuid' => CommentType::load('comment')->uuid(),
        ],
      ],
      'subject' => [
        [
          'value' => 'Llama',
        ],
      ],
      'status' => [
        [
          'value' => TRUE,
        ],
      ],
      'created' => [
        [
          'value' => (new \DateTime())->setTimestamp(123456789)->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'changed' => [
        [
          'value' => (new \DateTime())->setTimestamp((int) $this->entity->getChangedTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'default_langcode' => [
        [
          'value' => TRUE,
        ],
      ],
      'uid' => [
        [
          'target_id' => (int) $author->id(),
          'target_type' => 'user',
          'target_uuid' => $author->uuid(),
          'url' => base_path() . 'user/' . $author->id(),
        ],
      ],
      'pid' => [],
      'entity_type' => [
        [
          'value' => 'entity_test',
        ],
      ],
      'entity_id' => [
        [
          'target_id' => 1,
          'target_type' => 'entity_test',
          'target_uuid' => EntityTest::load(1)->uuid(),
          'url' => base_path() . 'entity_test/1',
        ],
      ],
      'field_name' => [
        [
          'value' => 'comment',
        ],
      ],
      'name' => [],
      'homepage' => [],
      'thread' => [
        [
          'value' => '01/',
        ],
      ],
      'comment_body' => [
        [
          'value' => 'The name "llama" was adopted by European settlers from native Peruvians.',
          'format' => 'plain_text',
          'processed' => '<p>The name &quot;llama&quot; was adopted by European settlers from native Peruvians.</p>' . "\n",
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'comment_type' => [
        [
          'target_id' => 'comment',
        ],
      ],
      'entity_type' => [
        [
          'value' => 'entity_test',
        ],
      ],
      'entity_id' => [
        [
          'target_id' => (int) EntityTest::load(1)->id(),
        ],
      ],
      'field_name' => [
        [
          'value' => 'comment',
        ],
      ],
      'subject' => [
        [
          'value' => 'Drama llama',
        ],
      ],
      'comment_body' => [
        [
          'value' => 'Llamas are awesome.',
          'format' => 'plain_text',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPatchEntity() {
    return array_diff_key($this->getNormalizedPostEntity(), ['entity_type' => TRUE, 'entity_id' => TRUE, 'field_name' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags() {
    return Cache::mergeTags(parent::getExpectedCacheTags(), ['config:filter.format.plain_text']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    return Cache::mergeContexts(['languages:language_interface', 'theme'], parent::getExpectedCacheContexts());
  }

  /**
   * Tests POSTing a comment without critical base fields.
   *
   * Tests with the most minimal normalization possible: the one returned by
   * ::getNormalizedPostEntity().
   *
   * But Comment entities have some very special edge cases:
   * - base fields that are not marked as required in
   *   \Drupal\comment\Entity\Comment::baseFieldDefinitions() yet in fact are
   *   required.
   * - base fields that are marked as required, but yet can still result in
   *   validation errors other than "missing required field".
   */
  public function testPostDxWithoutCriticalBaseFields(): void {
    $this->initAuthentication();
    $this->provisionEntityResource();
    $this->setUpAuthorization('POST');

    $url = $this->getEntityResourcePostUrl()->setOption('query', ['_format' => static::$format]);
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = static::$mimeType;
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options = array_merge_recursive($request_options, $this->getAuthenticationRequestOptions('POST'));

    // DX: 422 when missing 'entity_type' field.
    $request_options[RequestOptions::BODY] = $this->serializer->encode(array_diff_key($this->getNormalizedPostEntity(), ['entity_type' => TRUE]), static::$format);
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, "Unprocessable Entity: validation failed.\nentity_type: This value should not be null.\n", $response);

    // DX: 422 when missing 'entity_id' field.
    $request_options[RequestOptions::BODY] = $this->serializer->encode(array_diff_key($this->getNormalizedPostEntity(), ['entity_id' => TRUE]), static::$format);
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, "Unprocessable Entity: validation failed.\nentity_id: This value should not be null.\n", $response);

    // DX: 422 when missing 'field_name' field.
    $request_options[RequestOptions::BODY] = $this->serializer->encode(array_diff_key($this->getNormalizedPostEntity(), ['field_name' => TRUE]), static::$format);
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(422, "Unprocessable Entity: validation failed.\nfield_name: This value should not be null.\n", $response);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'GET':
        return "The 'access comments' permission is required and the comment must be published.";

      case 'POST':
        return "The 'post comments' permission is required.";

      case 'PATCH':
        return "The 'edit own comments' permission is required, the user must be the comment author, and the comment must be published.";

      case 'DELETE':
      default:
        // \Drupal\comment\CommentAccessControlHandler::checkAccess() does not
        // specify a reason for not allowing a comment to be deleted.
        return '';
    }
  }

  /**
   * Tests POSTing a comment with and without 'skip comment approval'.
   */
  public function testPostSkipCommentApproval(): void {
    $this->initAuthentication();
    $this->provisionEntityResource();
    $this->setUpAuthorization('POST');

    // Create request.
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = static::$mimeType;
    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;
    $request_options = array_merge_recursive($request_options, $this->getAuthenticationRequestOptions('POST'));
    $request_options[RequestOptions::BODY] = $this->serializer->encode($this->getNormalizedPostEntity(), static::$format);

    $url = $this->getEntityResourcePostUrl()->setOption('query', ['_format' => static::$format]);

    // Status should be FALSE when posting as anonymous.
    $response = $this->request('POST', $url, $request_options);
    $unserialized = $this->serializer->deserialize((string) $response->getBody(), get_class($this->entity), static::$format);
    $this->assertResourceResponse(201, FALSE, $response);
    $this->assertFalse($unserialized->isPublished());

    // Make sure the role save below properly invalidates cache tags.
    $this->refreshVariables();

    // Grant anonymous permission to skip comment approval.
    $this->grantPermissionsToTestedRole(['skip comment approval']);

    // Status should be TRUE when posting as anonymous and skip comment
    // approval.
    $response = $this->request('POST', $url, $request_options);
    $unserialized = $this->serializer->deserialize((string) $response->getBody(), get_class($this->entity), static::$format);
    $this->assertResourceResponse(201, FALSE, $response);
    $this->assertTrue($unserialized->isPublished());
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedEntityAccessCacheability($is_authenticated) {
    // @see \Drupal\comment\CommentAccessControlHandler::checkAccess()
    return parent::getExpectedUnauthorizedEntityAccessCacheability($is_authenticated)
      ->addCacheTags(['comment:1']);
  }

}
