<?php

namespace Drupal\server_group\Plugin\EntityViewBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\server_general\EntityViewBuilder\NodeViewBuilderAbstract;
use Drupal\server_general\LineSeparatorTrait;
use Drupal\server_general\SocialShareTrait;
use Drupal\server_general\TitleAndLabelsTrait;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The "Node Group" plugin.
 *
 * @EntityViewBuilder(
 *   id = "node.group",
 *   label = @Translation("Node - Group"),
 *   description = "Node view builder for Group bundle."
 * )
 */
class NodeGroup extends NodeViewBuilderAbstract {

  use RedirectDestinationTrait;
  use LineSeparatorTrait;
  use SocialShareTrait;
  use TitleAndLabelsTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->ogAccess = $container->get('og.access');
    return $plugin;
  }

  /**
   * Build full view mode.
   *
   * @param array $build
   *   The existing build.
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array.
   */
  public function buildFull(array $build, NodeInterface $entity) {

    $element = $this->buildHeader($entity);
    $elements[] = $this->wrapContainerWide($element);

    // Main content and sidebar.
    $element = $this->buildMainAndSidebar($entity);
    $elements[] = $this->wrapContainerWide($element);

    $elements = $this->wrapContainerVerticalSpacingBig($elements);
    $build[] = $this->wrapContainerBottomPadding($elements);

    return $build;
  }

  /**
   * Build the Main content and the sidebar.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array
   *
   * @throws \IntlException
   */
  protected function buildMainAndSidebar(NodeInterface $entity): array {
    $main_elements = [];
    $sidebar_elements = [];
    $social_share_elements = [];

    // Get main section text.
    $main_elements[] = $this->getMainSectionText($entity);

    // Add a line separator above the social share buttons.
    $social_share_elements[] = $this->buildLineSeparator();
    $social_share_elements[] = $this->buildSocialShare($entity);

    $sidebar_elements[] = $this->wrapContainerVerticalSpacing($social_share_elements);

    return [
      '#theme' => 'server_theme_main_and_sidebar',
      '#main' => $this->wrapContainerVerticalSpacingBig($main_elements),
      '#sidebar' => $this->wrapContainerVerticalSpacingBig($sidebar_elements),
    ];

  }

  /**
   * Build the header.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The entity.
   *
   * @return array
   *   Render array
   *
   * @throws \IntlException
   */
  protected function buildHeader(NodeInterface $entity): array {
    $elements = [];

    $elements[] = $this->buildConditionalPageTitle($entity);

    $elements = $this->wrapContainerVerticalSpacing($elements);
    return $this->wrapContainerNarrow($elements);
  }

  /**
   * Get main section text.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Group in context.
   *
   * @return array
   *   Main section text.
   */
  private function getMainSectionText(EntityInterface $entity): array {
    $user = User::load($this->currentUser->id());
    if (Og::isGroup($entity->getEntityTypeId(), $entity->bundle()) == FALSE) {
      return [];
    }
    $group = $entity;
    $group_name = $group->getTitle();
    // If the user is authenticated, set up the subscribe link.
    if ($this->shouldAllowSubscription($group, $user)) {
      $user_name = $user->getAccountName();
      $parameters = [
        'entity_type_id' => $group->getEntityTypeId(),
        'group' => $group->id(),
        'og_membership_type' => OgMembershipInterface::TYPE_DEFAULT,
      ];

      $url = Url::fromRoute('og.subscribe', $parameters);
      $link = Link::fromTextAndUrl(t('click here'), $url)->toString();

      return [
        '#theme' => 'server_theme_prose_text',
        '#text' => $this->t('Hi @user_name, @link if you would like to subscribe to this group called @group_name',
          [
            '@user_name' => $user_name,
            '@link' => $link,
            '@group_name' => $group_name,
          ]),
      ];
    }
    elseif ($user->isAnonymous()) {
      // User is anonymous, link to user login and redirect back to here.
      $url = Url::fromRoute('user.login', [], ['query' => $this->getDestinationArray()]);
      $link = Link::fromTextAndUrl(t('clicking here'), $url);
      return [
        '#theme' => 'server_theme_prose_text',
        '#text' => t("Please login to register to this group by @link", ['@link' => $link->toString()]),
      ];
    }

    return [];
  }

  /**
   * Check if we can allow subscription.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   Group in context.
   * @param \Drupal\user\Entity\User $user
   *   User in context.
   *
   * @return bool
   *   True if subscription is allowed.
   */
  private function shouldAllowSubscription(EntityInterface $group, User $user): bool {
    $valid_user = $user && $user->isAuthenticated();
    if (!$valid_user) {
      return FALSE;
    }
    if ($this->isGroupOwner($group, $user) || $this->isGroupMember($group, $user)) {
      return FALSE;
    }
    return $this->checkUserAccess($group, $user);
  }

  /**
   * Check group ownership.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   Group in context.
   * @param \Drupal\user\Entity\User $user
   *   User in context.
   *
   * @return bool
   *   True if user is group owner.
   */
  private function isGroupOwner(EntityInterface $group, User $user): bool {
    return $group->getOwnerId() == $user->id();
  }

  /**
   * Check group membership.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   Group in context.
   * @param \Drupal\user\Entity\User $user
   *   User in context.
   *
   * @return bool
   *   True if user is group member.
   */
  private function isGroupMember(EntityInterface $group, User $user): bool {
    return Og::isMember($group, $user, OgMembershipInterface::ALL_STATES);
  }

  /**
   * Checks if a user can subscribe to a group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   Group in context.
   * @param \Drupal\user\Entity\User $user
   *   User in context.
   *
   * @return bool
   *   True if user has access to subcription.
   */
  private function checkUserAccess(EntityInterface $group, User $user): bool {
    /** @var \Drupal\Core\Access\AccessResult $access */
    if (($access = $this->ogAccess->userAccess($group, 'subscribe without approval', $user)) && $access->isAllowed()) {
      return TRUE;
    }
    elseif (($access = $this->ogAccess->userAccess($group, 'subscribe', $user)) && $access->isAllowed()) {
      return TRUE;
    }
    return FALSE;
  }

}
