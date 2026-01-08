<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ProductController extends AbstractController
{
    #[Route('/legacy/products', name: 'legacy_create_product', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $jsonContent = $request->getContent();

        try {
            $product = $serializer->deserialize($jsonContent, Product::class, 'json');
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $entityManager->persist($product);
        $entityManager->flush();

        return $this->json($product, 201);
    }
}