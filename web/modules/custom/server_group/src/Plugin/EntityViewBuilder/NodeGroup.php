<?php

namespace Drupal\server_group\Plugin\EntityViewBuilder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\og\Og;
use Drupal\og\OgAccessInterface;
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
   * Constructor for NodeGroup.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The og access.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, EntityRepositoryInterface $entity_repository, OgAccessInterface $og_access) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $current_user, $entity_repository);
    $this->ogAccess = $og_access;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('og.access')
    );
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
        '#text' => t('Hi @user_name, @link if you would like to subscribe to this group called @group_name',
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
   */
  private function shouldAllowSubscription(EntityInterface $group, User $user) {
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
   */
  private function isGroupOwner(EntityInterface $group, User $user) {
    return $group->getOwnerId() == $user->id();
  }

  /**
   * Check group membership.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   Group in context.
   * @param \Drupal\user\Entity\User $user
   *   User in context.
   */
  private function isGroupMember(EntityInterface $group, User $user) {
    $storage = $this->entityTypeManager->getStorage('og_membership');
    $props = [
      'uid' => $user ? $user->id() : 0,
      'entity_type' => $group->getEntityTypeId(),
      'entity_bundle' => $group->bundle(),
      'entity_id' => $group->id(),
    ];
    $memberships = $storage->loadByProperties($props);
    /** @var \Drupal\og\OgMembershipInterface $membership */
    return reset($memberships);
  }

  /**
   * Checks if a user can subscribe to a group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   Group in context.
   * @param \Drupal\user\Entity\User $user
   *   User in context.
   */
  private function checkUserAccess(EntityInterface $group, User $user) {
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
