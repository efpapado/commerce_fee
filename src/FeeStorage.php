<?php

namespace Drupal\commerce_fee;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the fee storage.
 */
class FeeStorage extends CommerceContentEntityStorage implements FeeStorageInterface {

  /**
   * The time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new FeeStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection to be used.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database, EntityManagerInterface $entity_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, EventDispatcherInterface $event_dispatcher, TimeInterface $time) {
    parent::__construct($entity_type, $database, $entity_manager, $cache, $language_manager, $memory_cache, $event_dispatcher);

    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database'),
      $container->get('entity.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function loadAvailable(OrderInterface $order) {
    $today = gmdate('Y-m-d', $this->time->getRequestTime());
    $query = $this->getQuery();
    $or_condition = $query->orConditionGroup()
      ->condition('end_date', $today, '>=')
      ->notExists('end_date');
    $query
      ->condition('stores', [$order->getStoreId()], 'IN')
      ->condition('order_types', [$order->bundle()], 'IN')
      ->condition('start_date', $today, '<=')
      ->condition('status', TRUE)
      ->condition($or_condition);
    $result = $query->execute();
    if (empty($result)) {
      return [];
    }

    $fees = $this->loadMultiple($result);
    return $fees;
  }

}
