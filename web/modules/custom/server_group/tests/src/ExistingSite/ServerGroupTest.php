<?php

namespace Drupal\Tests\server_group\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * A test class for group.
 */
class ServerGroupTest extends ExistingSiteBase {

  /**
   * Test group subscription logic.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testGroup() {
    // Creates a user. Will be automatically cleaned up at the end of the test.
    $author = $this->createUser();
    $authenticated_user = $this->createUser();

    // Create a "Group A" group. Will be automatically cleaned up at end of
    // test.
    $node = $this->createNode([
      'title' => 'Group A',
      'type' => 'group',
      'uid' => $author->id(),
    ]);
    $this->assertEquals($author->id(), $node->getOwnerId());

    $assertion_text = "Hi {$authenticated_user->getAccountName()}, click here if you would like to subscribe to this group called Group A";

    // We assert as group onwer page does not contain subscription text.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($assertion_text);

    // We assert as authenticated user that page contains needed text.
    $this->drupalLogin($authenticated_user);
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains($assertion_text);

  }

}
