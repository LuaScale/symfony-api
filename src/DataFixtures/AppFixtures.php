<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Shop;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const SELLER_EMAIL = 'vendeur@collector.shop';
    public const SELLER_PASSWORD = 'change-this-fixture-password';
    public const BUYER_EMAIL = 'acheteur@collector.shop';
    public const BUYER_PASSWORD = 'buyer-fixture-password';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Categories
        $figurines = $this->makeCategory('Figurines Vintage', 'figurines-vintage', $manager);
        $comics = $this->makeCategory('Comics & BD', 'comics-bd', $manager);
        $retro = $this->makeCategory('Retro Gaming', 'retro-gaming', $manager);
        $this->makeCategory('Jouets Vintage', 'jouets-vintage', $manager);

        // Users
        $sellerPassword = $_ENV['FIXTURE_USER_PASSWORD'] ?? self::SELLER_PASSWORD;
        $seller = $this->makeUser('vendeur@collector.shop', 'RetroHunter', $sellerPassword, true, $manager);
        $buyer = $this->makeUser('acheteur@collector.shop', 'CollectoBuyer', self::BUYER_PASSWORD, true, $manager);

        // Shops
        $caverne = $this->makeShop('La Caverne aux Merveilles', 'Spécialiste des jouets des années 80.', $seller, $manager);
        $comicHub = $this->makeShop('Comic Hub Paris', 'Comics rares et éditions collector.', $buyer, $manager);

        // Items for La Caverne aux Merveilles
        $this->makeItem('Goldorak Jumbo Shogun', 'Figurine géante en plastique, très bon état, boite d\'origine.', 25000, 'VALIDATED', $caverne, $figurines, $manager);
        $this->makeItem('Mazinger Z – édition limitée', 'Figurine métal années 70, quelques traces d\'usure.', 18500, 'VALIDATED', $caverne, $figurines, $manager);
        $this->makeItem('Game Boy Color – Violet', 'Console en état de marche, avec jeu Tetris.', 7500, 'DRAFT', $caverne, $retro, $manager);
        $this->makeItem('Sega Mega Drive – complète', 'Console + 3 manettes + 12 jeux dont Sonic 1 et 2.', 12000, 'VALIDATED', $caverne, $retro, $manager);

        // Items for Comic Hub Paris
        $this->makeItem('Batman #1 – Réédition 1966', 'Réédition numérotée en très bon état, sous blister.', 4500, 'VALIDATED', $comicHub, $comics, $manager);
        $this->makeItem('Astérix – La Zizanie (1970)', 'Album original première édition, légères traces.', 3200, 'REJECTED', $comicHub, $comics, $manager);

        $manager->flush();
    }

    private function makeCategory(string $name, string $slug, ObjectManager $manager): Category
    {
        $cat = new Category();
        $cat->setName($name);
        $cat->setSlug($slug);
        $manager->persist($cat);

        return $cat;
    }

    private function makeUser(string $email, string $pseudo, string $plainPassword, bool $verified, ObjectManager $manager): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified($verified);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $manager->persist($user);

        return $user;
    }

    private function makeShop(string $name, string $description, User $owner, ObjectManager $manager): Shop
    {
        $shop = new Shop();
        $shop->setName($name);
        $shop->setDescription($description);
        $shop->setOwner($owner);
        $manager->persist($shop);

        return $shop;
    }

    private function makeItem(string $name, string $description, int $price, string $status, Shop $shop, Category $category, ObjectManager $manager): Item
    {
        $item = new Item();
        $item->setName($name);
        $item->setDescription($description);
        $item->setPrice($price);
        $item->setStatus($status);
        $item->setShop($shop);
        $item->setCategory($category);
        $item->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($item);

        return $item;
    }
}
