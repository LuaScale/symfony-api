<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Shop;
use PHPUnit\Framework\TestCase;

final class RelationsTest extends TestCase
{
    public function testShopAddItemWiresOwningSide(): void
    {
        $shop = new Shop();
        $item = new Item();

        $shop->addItem($item);

        self::assertSame($shop, $item->getShop());
        self::assertTrue($shop->getItems()->contains($item));
    }

    public function testCategoryAddItemWiresOwningSide(): void
    {
        $category = new Category();
        $item = new Item();

        $category->addItem($item);

        self::assertSame($category, $item->getCategory());
        self::assertTrue($category->getItems()->contains($item));
    }
}

