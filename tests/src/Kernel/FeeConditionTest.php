<?php

namespace Drupal\Tests\commerce_fee\Kernel;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_fee\Entity\Fee;
use Drupal\Tests\commerce\Kernel\CommerceKernelTestBase;

/**
 * Tests fee conditions.
 *
 * @group commerce
 */
class FeeConditionTest extends CommerceKernelTestBase {

  /**
   * The test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'commerce_order',
    'path',
    'commerce_product',
    'commerce_fee',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_order_item');
    $this->installEntitySchema('commerce_fee');
    $this->installEntitySchema('commerce_product_variation');
    $this->installEntitySchema('commerce_product');
    $this->installConfig([
      'profile',
      'commerce_order',
      'commerce_product',
      'commerce_fee',
    ]);

    OrderItemType::create([
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default',
    ])->save();

    $this->order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'order_items' => [],
    ]);
  }

  /**
   * Tests fees with an order condition.
   */
  public function testOrderCondition() {
    // Starts now, enabled. No end time. Matches orders under $20 or over $100.
    $fee = Fee::create([
      'name' => 'Fee 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'plugin' => [
        'target_plugin_id' => 'order_percentage',
        'target_plugin_configuration' => [
          'amount' => '0.10',
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'order_total_price',
          'target_plugin_configuration' => [
            'operator' => '<',
            'amount' => [
              'number' => '20.00',
              'currency_code' => 'USD',
            ],
          ],
        ],
        [
          'target_plugin_id' => 'order_email',
          'target_plugin_configuration' => [
            'mail' => 'not_test@example.com',
          ],
        ],
      ],
      'condition_operator' => 'OR',
    ]);
    $fee->save();

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 3,
      'unit_price' => [
        'number' => '10.00',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $result = $fee->applies($this->order);
    $this->assertFalse($result);

    $order_item->setQuantity(1);
    $order_item->save();
    $this->order->save();
    $result = $fee->applies($this->order);
    $this->assertTrue($result);

    $order_item->setQuantity(3);
    $order_item->save();
    $this->order->setEmail('not_test@example.com');
    $this->order->save();
    $result = $fee->applies($this->order);
    $this->assertTrue($result);
  }

  /**
   * Tests fees with an order item condition.
   */
  public function testOrderItemCondition() {
    // Starts now, enabled. No end time.
    $fee = Fee::create([
      'name' => 'Fee 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'plugin' => [
        'target_plugin_id' => 'order_percentage',
        'target_plugin_configuration' => [
          'amount' => '0.10',
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'order_total_price',
          'target_plugin_configuration' => [
            'operator' => '>',
            'amount' => [
              'number' => '10.00',
              'currency_code' => 'USD',
            ],
          ],
        ],
        [
          'target_plugin_id' => 'order_item_quantity',
          'target_plugin_configuration' => [
            'operator' => '>',
            'quantity' => 2,
          ],
        ],
      ],
      'condition_operator' => 'AND',
    ]);
    $fee->save();

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 2,
      'unit_price' => [
        'number' => '20.00',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();

    $result = $fee->applies($this->order);
    $this->assertFalse($result);

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 4,
      'unit_price' => [
        'number' => '20.00',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();

    $result = $fee->applies($this->order);
    $this->assertTrue($result);
  }

}
