<?php
namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Shop;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
private UserPasswordHasherInterface $hasher;

public function __construct(UserPasswordHasherInterface $hasher)
{
$this->hasher = $hasher;
}

public function load(ObjectManager $manager): void
{
$category = new Category();
$category->setName('Figurines Vintage');
$category->setSlug('figurines-vintage');
$manager->persist($category);

$user = new User();
$user->setEmail('vendeur@collector.shop');
$user->setPseudo('RetroHunter');
$user->setRoles(['ROLE_USER']);
$user->setIsVerified(true);
// Use an environment-configurable password for fixtures; default is for development only.
    $plainPassword = $_ENV['FIXTURE_USER_PASSWORD'] ?? 'change-this-fixture-password';
    $password = $this->hasher->hashPassword($user, $plainPassword);
$user->setPassword($password);
$manager->persist($user);

$shop = new Shop();
$shop->setName('La Caverne aux Merveilles');
$shop->setDescription('Spécialiste des jouets des années 80.');
$shop->setOwner($user);
$manager->persist($shop);

$item = new Item();
$item->setName('Goldorak Jumbo Shogun');
$item->setDescription('Figurine géante en plastique, très bon état, boite d\'origine.');
$item->setPrice(25000);
$item->setStatus('VALIDATED');
$item->setShop($shop);
$item->setCategory($category);
$item->setCreatedAt(new DateTimeImmutable());
$manager->persist($item);

$manager->flush();
}
}
