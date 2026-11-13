<?php

namespace Drupal\Tests\commerce_promo_bar\Kernel;

use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promo_bar\Entity\PromoBar;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Drupal\commerce_promo_bar\PromoBarVisibility;
use Drupal\commerce_promo_bar\PromoBarVisibilityInterface;
use Drupal\path_alias\Entity\PathAlias;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the page visibility of promo bars.
 */
#[CoversClass(PromoBarVisibility::class)]
#[Group('commerce_promo_bar')]
#[RunTestsInSeparateProcesses]
class PromoBarVisibilityTest extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path_alias',
    'commerce_promotion',
    'commerce_promo_bar',
    'color_field',
  ];

  /**
   * The promo bar visibility.
   */
  protected PromoBarVisibilityInterface $visibility;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('commerce_promo_bar');
    $this->installConfig(['commerce_promo_bar']);
    $this->visibility = $this->container->get('commerce_promo_bar.visibility');
  }

  /**
   * Tests showing and hiding for explicit paths.
   */
  public function testExplicitPath(): void {
    $this->assertTrue($this->visibility->isVisible($this->createPromoBar(''), '/anything'));

    $show = $this->createPromoBar("/blog\n/blog/*", TRUE);
    $hide = $this->createPromoBar("/blog\n/blog/*", FALSE);
    foreach (['/blog', '/Blog/', '/blog/post?page=1#top', 'blog/post'] as $path) {
      $this->assertTrue($this->visibility->isVisible($show, $path), $path);
      $this->assertFalse($this->visibility->isVisible($hide, $path), $path);
    }
    foreach (['/', '/shop', '/blogger'] as $path) {
      $this->assertFalse($this->visibility->isVisible($show, $path), $path);
      $this->assertTrue($this->visibility->isVisible($hide, $path), $path);
    }
  }

  /**
   * Tests that aliases and internal paths match each other.
   */
  public function testAlias(): void {
    // Aliases are only looked up for the path roots of existing routes.
    $this->container->get('router.builder')->rebuild();
    PathAlias::create(['path' => '/user/5', 'alias' => '/Summer-Sale'])->save();

    $by_alias = $this->createPromoBar('/summer-sale', TRUE);
    $this->assertTrue($this->visibility->isVisible($by_alias, '/user/5'));
    $this->assertTrue($this->visibility->isVisible($by_alias, '/summer-sale'));
    $this->assertFalse($this->visibility->isVisible($by_alias, '/user/6'));

    $by_internal_path = $this->createPromoBar('/user/5', TRUE);
    $this->assertTrue($this->visibility->isVisible($by_internal_path, '/Summer-Sale'));
    $this->assertTrue($this->visibility->isVisible($by_internal_path, '/user/5'));
  }

  /**
   * Tests that "/" counts as the front page.
   */
  public function testFrontPage(): void {
    $this->config('system.site')->set('page.front', '/node/1')->save();

    $promo_bar = $this->createPromoBar('<front>', TRUE);
    $this->assertTrue($this->visibility->isVisible($promo_bar, '/'));
    $this->assertTrue($this->visibility->isVisible($promo_bar, '/node/1'));
    $this->assertFalse($this->visibility->isVisible($promo_bar, '/node/2'));
  }

  /**
   * Tests that the current path is used without an explicit path.
   */
  public function testCurrentPath(): void {
    $promo_bar = $this->createPromoBar('/blog', TRUE);

    $this->container->get('path.current')->setPath('/blog');
    $this->assertTrue($this->visibility->isVisible($promo_bar));
    // An explicit path wins over the current path.
    $this->assertFalse($this->visibility->isVisible($promo_bar, '/shop'));

    $this->container->get('path.current')->setPath('/shop');
    $this->assertFalse($this->visibility->isVisible($promo_bar));
  }

  /**
   * Creates a promo bar.
   *
   * @param string $pages
   *   The pages.
   * @param bool $visibility
   *   TRUE to show for the listed pages, FALSE to hide.
   *
   * @return \Drupal\commerce_promo_bar\Entity\PromoBarInterface
   *   The saved promo bar.
   */
  protected function createPromoBar(string $pages, bool $visibility = TRUE): PromoBarInterface {
    $promo_bar = PromoBar::create([
      'label' => 'Promo bar',
      'body' => ['value' => 'Promo bar', 'format' => 'plain_text'],
      'stores' => [$this->store->id()],
      'pages' => $pages,
      'visibility' => $visibility,
      'status' => TRUE,
    ]);
    $promo_bar->save();
    return $promo_bar;
  }

}
